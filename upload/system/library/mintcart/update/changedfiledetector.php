<?php
namespace MintCart\Update;

/**
 * "Changed since install" detection (`docs/adr/005-theme-extension-update-rollback.md`
 * decision #1) — the comparison `MintCart\Update\InstalledFileLedger`'s own
 * docblock named as the ledger's real purpose but deliberately left
 * unbuilt ("no real consumer yet — `CLAUDE.md` §85"): now that
 * `InstalledFileLedger::forPackage()` is wired into `UpdateOrchestrator::apply()`
 * and returns real (file_path => sha256) rows once a package has been
 * installed once, this class is that comparison.
 *
 * A file counts as "changed since install" when its **current on-disk**
 * sha256 no longer matches the ledger's **last recorded** value for that
 * path — per the ADR, never against the *incoming* package's expected
 * content, which would flag every routine content update as a manual
 * edit. This class only ever reads `InstalledFileLedger::forPackage()`'s
 * own return value (an already-fetched snapshot, not a live `$db`) and the
 * live filesystem — same "pure, OpenCart-independent primitive" pattern
 * `PackageVerifier`/`PackageManifest`/`BackupPlanner` already established,
 * so it needs no `\Registry`/`\DB` and is trivially testable against real
 * temp files.
 *
 * Real consumer this closes the loop for: a future rollback/"detect local
 * edits before overwriting" admin screen (ADR 005) can call
 * `detect($extension_dir, $ledger->forPackage($package_name))` and warn
 * before an update silently discards a site owner's hand edit — the exact
 * scenario `MINTCART_FUNCTIONAL_SCOPE.md` §21 describes ("client might
 * have manually changed theme files"). No such caller exists yet in this
 * increment (the admin `mintcart/update/*` route itself is still an open
 * architectural question flagged by earlier runs) — this class only
 * supplies the comparison primitive it will need.
 *
 * Deliberately not attempted here:
 * - **No restore.** This class only reports; it never writes a file.
 *   Restoring from a backup snapshot remains ADR 005's separate,
 *   not-yet-built rollback capability.
 * - **No manifest-diff classification** (ADR decision #2 — "why" a file
 *   is touched by an update). This class only answers "is this specific
 *   path's on-disk content different from what was last installed,"
 *   which is decision #1, not #2.
 * - **No `removes()` handling** (ADR decision #3 — files a new package
 *   version deletes). Nothing here decides what happens to a path that is
 *   present in the ledger but absent from a new manifest's `files()`
 *   list; that is the future rollback flow's job once `removes()` exists.
 */
final class ChangedFileDetector
{
    /**
     * Compares $ledgerSnapshot (exactly `InstalledFileLedger::forPackage()`'s
     * own return shape: file_path => sha256, relative to $baseDir) against
     * the current contents of $baseDir.
     *
     * @param array<string,string> $ledgerSnapshot
     *
     * @return array{
     *     modified: string[],
     *     missing: string[],
     *     unchanged: string[]
     * } Each list holds relative paths (the same keys $ledgerSnapshot
     *   carried), in the snapshot's own iteration order. "modified" is a
     *   path that still exists but no longer hashes to its recorded
     *   sha256; "missing" is a path the ledger recorded but that is no
     *   longer a file on disk at all (deleted or replaced by a
     *   directory/symlink target that isn't a regular file); "unchanged"
     *   is everything else. A path is never in more than one list, and
     *   every key of $ledgerSnapshot appears in exactly one of the three.
     */
    public static function detect(string $baseDir, array $ledgerSnapshot): array
    {
        $baseDir = rtrim($baseDir, '/\\');

        $modified = [];
        $missing = [];
        $unchanged = [];

        foreach ($ledgerSnapshot as $relativePath => $recordedHash) {
            $fullPath = $baseDir . '/' . ltrim($relativePath, '/\\');

            if (!is_file($fullPath)) {
                $missing[] = $relativePath;
                continue;
            }

            $actualHash = hash_file('sha256', $fullPath);

            if ($actualHash !== false && hash_equals(strtolower($recordedHash), strtolower($actualHash))) {
                $unchanged[] = $relativePath;
            } else {
                $modified[] = $relativePath;
            }
        }

        return [
            'modified' => $modified,
            'missing' => $missing,
            'unchanged' => $unchanged,
        ];
    }

    /**
     * True when `detect()` would report at least one "modified" or
     * "missing" path — the quick yes/no a caller checks before deciding
     * whether to surface a "some files were changed by hand" warning at
     * all, without needing to inspect the full breakdown.
     *
     * @param array<string,string> $ledgerSnapshot
     */
    public static function hasChanges(string $baseDir, array $ledgerSnapshot): bool
    {
        $result = self::detect($baseDir, $ledgerSnapshot);

        return $result['modified'] !== [] || $result['missing'] !== [];
    }
}
