<?php
namespace MintCart\Update;

/**
 * The `files` step of the Update Manager flow (`MINTCART_TECHNICAL_PLAN.md`
 * §33 / `MINTCART_IMPLEMENTATION_GUIDE.md` §69):
 *
 * ```text
 * check → compatibility → backup → download → verify → maintenance →
 * files → migrations → cache clear → health check → done
 * ```
 *
 * By the time this step runs, `PackageVerifier::verifyChecksum()` has
 * already confirmed the downloaded archive's integrity, the archive has
 * been extracted to a local directory, `BackupPlanner::plan()` has already
 * decided what to snapshot, and `MaintenanceMode` has already put the
 * store into maintenance. This class performs the actual on-disk change:
 * copying every path `PackageManifest::files()` declares from the
 * extracted package into the live install, and deleting every path
 * `PackageManifest::removes()` declares (ADR 005 decision #3) — never the
 * other way around, and never a path outside those two explicit lists.
 *
 * Design notes:
 * - **Pre-flight, not partial.** Before touching any file, `install()`
 *   reuses `PackageVerifier::missingFiles()` to confirm every declared
 *   `files()` path is actually present in the extracted package, and
 *   throws without writing anything if not — same "refuse to start rather
 *   than abort partway through" philosophy `BackupPlanner::hasEnoughDiskSpace()`
 *   already established for ADR 005 decision #6. A caller that wants a
 *   pre-flight check for `removes()` paths uses `missingRemoves()`
 *   separately — a `removes()` path already absent from the live install
 *   is not an error (see below), so it is not folded into the same
 *   all-or-nothing gate as `files()`.
 * - **Atomic per file.** Each copy writes to a `.part-<uniqid>` sibling of
 *   the real destination first, then `rename()`s it into place — the same
 *   temp-file-then-rename pattern already used elsewhere in this codebase
 *   for atomic writes, so a request that dies mid-copy never leaves a
 *   half-written file at the real destination path.
 * - **Idempotent removes.** A `removes()` path that is already absent from
 *   the live install (e.g. a retried/resumed update) is treated as already
 *   satisfied, not an error — matches `MaintenanceMode::enable()`/
 *   `disable()`'s own idempotency.
 * - **No path traversal.** A declared path containing a `..` segment is
 *   rejected before anything is touched — a manifest-authoring bug, not a
 *   normal "file missing" outcome, so it throws rather than failing
 *   closed.
 * - **Returns per-file sha256.** `install()`'s `installed` entries carry
 *   the sha256 of the file actually written to $destRoot — the exact
 *   input the future `mc_installed_file` ledger (ADR 005 decision #1)
 *   needs once a real caller writes it via the Migration Manager. This
 *   class does not write that ledger itself (no table exists yet —
 *   `CLAUDE.md` §85: no API ahead of a real consumer).
 *
 * Deliberately has no OpenCart dependency (no `\Registry`, no `\DB`, no
 * `file_get_contents()` over HTTP) — same shape as `PackageVerifier`/
 * `BackupPlanner`/`PackageManifest`. A real Update Manager orchestrator
 * (not yet built) is expected to call `BackupPlanner::plan()` first, then
 * this class, in sequence.
 */
final class PackageFileInstaller
{
    /**
     * Copies every `$manifest->files()` path from $sourceDir into
     * $destRoot, then deletes every `$manifest->removes()` path from
     * $destRoot.
     *
     * @return array{
     *     installed: array<int, array{path: string, sha256: string}>,
     *     removed: string[]
     * }
     *
     * @throws \InvalidArgumentException if any declared path contains a
     *                                    `..` segment (manifest-authoring
     *                                    bug — path traversal is never
     *                                    valid here).
     * @throws \RuntimeException if any `files()` path is missing from
     *                            $sourceDir (nothing is written — see
     *                            class docblock), or if a copy fails
     *                            partway through (disk error, permissions).
     */
    public function install(PackageManifest $manifest, string $sourceDir, string $destRoot): array
    {
        self::assertNoTraversal(array_merge($manifest->files(), $manifest->removes()));

        $missing = PackageVerifier::missingFiles($sourceDir, $manifest);

        if ($missing !== []) {
            throw new \RuntimeException(
                'Cannot install: missing from extracted package: ' . implode(', ', $missing)
            );
        }

        $sourceDir = rtrim($sourceDir, '/\\');
        $destRoot = rtrim($destRoot, '/\\');

        $installed = [];

        foreach ($manifest->files() as $relativePath) {
            $installed[] = [
                'path' => $relativePath,
                'sha256' => self::copyFile(
                    $sourceDir . '/' . ltrim($relativePath, '/\\'),
                    $destRoot . '/' . ltrim($relativePath, '/\\')
                ),
            ];
        }

        $removed = [];

        foreach ($manifest->removes() as $relativePath) {
            $destPath = $destRoot . '/' . ltrim($relativePath, '/\\');

            if (is_file($destPath)) {
                if (!@unlink($destPath)) {
                    throw new \RuntimeException("Failed to remove \"{$relativePath}\" from destination");
                }

                $removed[] = $relativePath;
            }
        }

        return [
            'installed' => $installed,
            'removed' => $removed,
        ];
    }

    /**
     * Every `$manifest->removes()` path that is NOT present under
     * $destRoot, as manifest-relative paths — mirrors
     * `PackageVerifier::missingFiles()`'s shape/semantics but for the
     * opposite question ("what would removal actually delete?"). A
     * caller may use this to report what a `removes()` step will do
     * before running `install()`; an empty result here is not an error
     * (see class docblock's "idempotent removes" note).
     *
     * @return string[]
     */
    public static function missingRemoves(string $destRoot, PackageManifest $manifest): array
    {
        $destRoot = rtrim($destRoot, '/\\');
        $missing = [];

        foreach ($manifest->removes() as $relativePath) {
            if (!is_file($destRoot . '/' . ltrim($relativePath, '/\\'))) {
                $missing[] = $relativePath;
            }
        }

        return $missing;
    }

    /**
     * Copies $sourcePath to $destPath via a temp-file-then-rename swap so
     * a request that dies mid-copy never leaves a half-written file at
     * $destPath, creating any missing destination parent directories
     * first. Returns the sha256 of the file actually left at $destPath.
     */
    private static function copyFile(string $sourcePath, string $destPath): string
    {
        $destDir = dirname($destPath);

        if (!is_dir($destDir) && !@mkdir($destDir, 0777, true) && !is_dir($destDir)) {
            throw new \RuntimeException("Failed to create destination directory \"{$destDir}\"");
        }

        $tempPath = $destPath . '.part-' . uniqid('', true);

        if (!@copy($sourcePath, $tempPath)) {
            throw new \RuntimeException("Failed to copy \"{$sourcePath}\" to \"{$tempPath}\"");
        }

        if (!@rename($tempPath, $destPath)) {
            @unlink($tempPath);

            throw new \RuntimeException("Failed to move \"{$tempPath}\" into place at \"{$destPath}\"");
        }

        $hash = hash_file('sha256', $destPath);

        if ($hash === false) {
            throw new \RuntimeException("Failed to hash \"{$destPath}\" after install");
        }

        return $hash;
    }

    /**
     * @param string[] $relativePaths
     *
     * @throws \InvalidArgumentException on the first path containing a
     *                                    `..` segment.
     */
    private static function assertNoTraversal(array $relativePaths): void
    {
        foreach ($relativePaths as $relativePath) {
            $segments = preg_split('#[/\\\\]+#', $relativePath);

            if ($segments !== false && in_array('..', $segments, true)) {
                throw new \InvalidArgumentException(
                    "Manifest path \"{$relativePath}\" contains a \"..\" segment — not allowed"
                );
            }
        }
    }
}
