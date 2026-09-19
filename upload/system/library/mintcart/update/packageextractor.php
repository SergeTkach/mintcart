<?php
namespace MintCart\Update;

/**
 * Archive extraction for the Update Manager flow (`MINTCART_TECHNICAL_PLAN.md`
 * §33 / `MINTCART_IMPLEMENTATION_GUIDE.md` §69):
 *
 * ```text
 * check → compatibility → backup → download → verify → maintenance →
 * files → migrations → cache clear → health check → done
 * ```
 *
 * `PackageDownloader`, `PackageVerifier` and `UpdateOrchestrator` have all
 * named the same gap directly in their own docblocks: an archive is
 * downloaded (`PackageDownloader`), but nothing in this codebase turns that
 * downloaded file into the extracted directory `PackageVerifier::missingFiles()`,
 * `PackageFileInstaller::install()` and `UpdateOrchestrator::apply()` all
 * already assume as an input. This class is that missing step — same
 * "pure, OpenCart-independent primitive first, wire a caller once it exists"
 * pattern every other `MintCart\Update\*` class already used
 * (`CLAUDE.md` §85).
 *
 * Only two responsibilities belong here, deliberately, same split
 * `PackageDownloader`/`PackageFileInstaller` already use for their own
 * single on-disk operation:
 *
 * - **Zip-slip protection.** Every entry name in the archive is checked
 *   for a `..` path segment or an absolute path (leading `/`, `\`, or a
 *   Windows drive letter) *before* anything is extracted — a malicious or
 *   corrupt archive must never be able to write outside the destination
 *   directory. This mirrors `PackageFileInstaller::assertNoTraversal()`'s
 *   reasoning one level up (manifest paths there, archive entry names
 *   here).
 * - **Atomic extraction.** The archive is extracted to a temp directory
 *   next to the real destination first, then that temp directory replaces
 *   the destination via `rename()` — the same temp-then-rename pattern
 *   `PackageDownloader::writeAtomically()`/`PackageFileInstaller::copyFile()`
 *   already use for files, applied here to a whole directory tree, so a
 *   request that dies mid-extraction never leaves a half-extracted
 *   directory at the real destination path. An existing destination
 *   directory (e.g. a retried/resumed update) is replaced wholesale, not
 *   merged into — avoids silently mixing an old extraction's leftover
 *   files with the new one.
 *
 * Deliberately not attempted here: checksum verification of the archive
 * itself (`PackageVerifier::verifyChecksum()`'s job, against the
 * still-compressed file, before this class ever runs), confirming the
 * extracted contents match what the manifest declares
 * (`PackageVerifier::missingFiles()`'s job, after this class runs), and
 * any manifest awareness at all — this class only knows about the archive
 * and a destination directory, exactly like `PackageDownloader` only knows
 * about a URL and a destination file.
 *
 * Uses PHP's built-in `ZipArchive` directly rather than an injectable
 * transport (unlike `PackageDownloader`, which duck-types its transport
 * because this environment has no outbound network access) — extracting a
 * local zip file needs no network, so this class's own tests exercise the
 * real extraction path end to end, including the zip-slip rejection, by
 * building small real zip fixtures on disk.
 */
final class PackageExtractor
{
    /**
     * Extracts every entry in the zip archive at $archivePath into
     * $destinationDir, replacing $destinationDir wholesale if it already
     * exists. Returns the relative paths of every *file* entry extracted
     * (directory entries are omitted — they carry no content for a caller
     * to verify/copy), sorted for deterministic output.
     *
     * @return string[]
     *
     * @throws \RuntimeException if $archivePath does not exist, is not a
     *                            readable zip archive, is empty (zero
     *                            entries), or extraction/directory
     *                            replacement fails partway through.
     * @throws \RuntimeException if any entry name contains a `..` segment
     *                            or is an absolute path — a zip-slip
     *                            attempt, never extracted.
     */
    public function extract(string $archivePath, string $destinationDir): array
    {
        if (!is_file($archivePath)) {
            throw new \RuntimeException("Archive not found at \"{$archivePath}\".");
        }

        $zip = new \ZipArchive();
        $opened = $zip->open($archivePath);

        if ($opened !== true) {
            throw new \RuntimeException(
                "Failed to open archive \"{$archivePath}\" (ZipArchive error code " . var_export($opened, true) . ')'
            );
        }

        if ($zip->numFiles === 0) {
            $zip->close();

            throw new \RuntimeException("Archive \"{$archivePath}\" contains no entries.");
        }

        try {
            $fileEntries = self::assertSafeEntriesAndCollectFiles($zip);

            $destinationDir = rtrim($destinationDir, '/\\');
            $tempDir = $destinationDir . '.part-' . uniqid('', true);

            if (!@mkdir($tempDir, 0777, true) && !is_dir($tempDir)) {
                throw new \RuntimeException("Could not create temporary extraction directory \"{$tempDir}\".");
            }

            if (!$zip->extractTo($tempDir)) {
                self::removeDirectory($tempDir);

                throw new \RuntimeException("Failed to extract archive \"{$archivePath}\" to \"{$tempDir}\".");
            }
        } finally {
            $zip->close();
        }

        if (is_dir($destinationDir)) {
            self::removeDirectory($destinationDir);
        }

        if (!@rename($tempDir, $destinationDir)) {
            self::removeDirectory($tempDir);

            throw new \RuntimeException("Could not move extracted package into place at \"{$destinationDir}\".");
        }

        sort($fileEntries);

        return $fileEntries;
    }

    /**
     * Validates every entry name in $zip is safe to extract, then returns
     * the subset naming an actual file (not a directory entry, i.e. not
     * ending in `/`).
     *
     * @return string[]
     *
     * @throws \RuntimeException on the first unsafe entry name.
     */
    private static function assertSafeEntriesAndCollectFiles(\ZipArchive $zip): array
    {
        $fileEntries = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            if ($name === false) {
                continue;
            }

            self::assertSafeEntryName($name);

            if (substr($name, -1) !== '/') {
                $fileEntries[] = $name;
            }
        }

        return $fileEntries;
    }

    private static function assertSafeEntryName(string $name): void
    {
        if ($name === '') {
            return;
        }

        $segments = preg_split('#[/\\\\]+#', $name);

        if ($segments !== false && in_array('..', $segments, true)) {
            throw new \RuntimeException(
                "Archive entry \"{$name}\" contains a \"..\" segment — refusing to extract (zip-slip)."
            );
        }

        if ($name[0] === '/' || $name[0] === '\\' || preg_match('#^[a-zA-Z]:#', $name) === 1) {
            throw new \RuntimeException(
                "Archive entry \"{$name}\" is an absolute path — refusing to extract (zip-slip)."
            );
        }
    }

    private static function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;

            if (is_dir($path) && !is_link($path)) {
                self::removeDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}
