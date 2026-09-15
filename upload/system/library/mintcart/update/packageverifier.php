<?php
namespace MintCart\Update;

/**
 * The `verify` step of the Update Manager flow (`MINTCART_TECHNICAL_PLAN.md`
 * §33 / `MINTCART_IMPLEMENTATION_GUIDE.md` §69):
 *
 * ```text
 * check → compatibility → backup → download → verify → maintenance →
 * files → migrations → cache clear → health check → done
 * ```
 *
 * `PackageManifest`'s own doc (`docs/developer/update/package-manifest.md`,
 * "What's deliberately not done yet") calls this out directly: `checksum`
 * is carried as opaque data there, and "verifying it against a downloaded
 * file is the `verify` step, not this class's job." This class is that
 * step — same "pure, OpenCart-independent primitive first" pattern
 * `PackageManifest` itself, `MigrationManager` (Phase 1) and
 * `WizardProgress` (Phase 13) already used, deliberately with no caller
 * yet (no `download` step exists to hand this a real fetched archive —
 * `CLAUDE.md` §85).
 *
 * Two independent questions a caller needs answered before it is safe to
 * move on to `maintenance`/`files`:
 *
 * - Does the downloaded package archive match the checksum the manifest
 *   declared (`verifyChecksum()`)?
 * - Are all the files the manifest says it installs actually present in
 *   the extracted package (`missingFiles()`)?
 *
 * Deliberately not attempted here: no signature/PKI verification
 * (`PackageManifest`'s doc notes "No package signing" is still an open
 * gap), no archive extraction (a caller is assumed to have already
 * extracted the package before calling `missingFiles()` — extraction
 * belongs to the `download` step, not `verify`).
 */
final class PackageVerifier
{
    /**
     * True when $checksum is null/blank (nothing declared, nothing to
     * check — a manifest is not required to carry a checksum) or when the
     * file at $filePath actually hashes to the declared value.
     *
     * @throws \InvalidArgumentException if $checksum is non-empty but not
     *                                    in "algorithm:hash" form, or names
     *                                    an algorithm this PHP build's
     *                                    `hash_file()` doesn't support —
     *                                    both are manifest authoring bugs
     *                                    the caller should surface, not
     *                                    silently treat as "unverified."
     */
    public static function verifyChecksum(string $filePath, ?string $checksum): bool
    {
        if ($checksum === null || trim($checksum) === '') {
            return true;
        }

        [$algorithm, $expectedHash] = self::parseChecksum($checksum);

        if (!in_array($algorithm, hash_algos(), true)) {
            throw new \InvalidArgumentException(
                "Unsupported checksum algorithm \"{$algorithm}\" — not available via hash_algos()"
            );
        }

        if (!is_file($filePath)) {
            return false;
        }

        $actualHash = hash_file($algorithm, $filePath);

        return $actualHash !== false && hash_equals(strtolower($expectedHash), strtolower($actualHash));
    }

    /**
     * Every file $manifest declares (`files()`) that is NOT present under
     * $baseDir, as manifest-relative paths. Empty array means the
     * extracted package is complete relative to what the manifest
     * promised — it says nothing about file *contents* (that's
     * `verifyChecksum()`'s job, one level up, against the whole archive).
     *
     * @return string[]
     */
    public static function missingFiles(string $baseDir, PackageManifest $manifest): array
    {
        $baseDir = rtrim($baseDir, '/\\');
        $missing = [];

        foreach ($manifest->files() as $relativePath) {
            if (!is_file($baseDir . '/' . ltrim($relativePath, '/\\'))) {
                $missing[] = $relativePath;
            }
        }

        return $missing;
    }

    /**
     * @return array{0:string,1:string} [algorithm, hex-encoded hash]
     *
     * @throws \InvalidArgumentException when $checksum has no "algo:hash"
     *                                    separator.
     */
    private static function parseChecksum(string $checksum): array
    {
        $checksum = trim($checksum);
        $separator = strpos($checksum, ':');

        if ($separator === false) {
            throw new \InvalidArgumentException(
                "Checksum must be in \"algorithm:hash\" form (e.g. \"sha256:...\"), got: \"{$checksum}\""
            );
        }

        return [substr($checksum, 0, $separator), substr($checksum, $separator + 1)];
    }
}
