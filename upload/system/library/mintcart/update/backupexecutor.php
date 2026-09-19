<?php
namespace MintCart\Update;

/**
 * Executes the "backup" step of the Update Manager flow
 * (`MINTCART_TECHNICAL_PLAN.md` §33 / `MINTCART_IMPLEMENTATION_GUIDE.md`
 * §69):
 *
 * ```text
 * check → compatibility → backup → download → verify → maintenance →
 * files → migrations → cache clear → health check → done
 * ```
 *
 * `BackupPlanner::plan()` already decides *which* currently-installed
 * files an update is about to overwrite or delete, and *where* a snapshot
 * of them belongs (`docs/adr/005-theme-extension-update-rollback.md`
 * decisions #3/#6/#7) — but, by that class's own docblock, "performing
 * the copy is the flow's own `files` step," and nothing in this codebase
 * ever did that copy: `PackageFileInstaller::install()` (the `files` step)
 * only copies the *new* package's files into the live install, it never
 * calls `BackupPlanner` at all. This class is the missing execution half
 * of `BackupPlanner::plan()` — same "planner decides, executor performs"
 * split `PackageExtractor`'s own docblock already draws between
 * `PackageVerifier` (decides) and itself (performs), applied here to the
 * backup step instead of extraction.
 *
 * Design mirrors `PackageExtractor::extract()`'s own choices, deliberately
 * the same shape:
 *
 * - **Atomic as a whole.** Files are copied into a `.part-<uniqid>` temp
 *   directory next to the real snapshot directory first, then that temp
 *   directory replaces the real one via `rename()` — a request that dies
 *   mid-backup never leaves a half-written snapshot at the real path a
 *   later restore might trust.
 * - **Wholesale replace, not merge.** A pre-existing snapshot directory at
 *   the same path (e.g. a retried/resumed update reusing the same
 *   timestamp) is replaced entirely, not merged into — a stale leftover
 *   file from a previous, possibly-failed backup attempt must never
 *   silently survive alongside the new one.
 * - **No path traversal.** A `$plan['files']` entry containing a `..`
 *   segment is rejected before anything is touched — mirrors
 *   `PackageFileInstaller::assertNoTraversal()`'s reasoning one level up
 *   (that class's manifest-declared paths there, this class's
 *   planner-declared paths here).
 *
 * A `$plan['files']` path that no longer exists under `$installRoot` at
 * backup time (e.g. removed by something else between `plan()` and this
 * call) is silently skipped, not an error — mirrors
 * `BackupPlanner::plan()`'s own "not currently installed" handling for a
 * path missing from its `$existingFiles` map, and
 * `PackageFileInstaller::install()`'s "idempotent removes" precedent for
 * treating an already-absent path as already satisfied rather than a
 * failure.
 *
 * Deliberately not attempted here, same "primitive first, wire a caller
 * once it exists" pattern every other `MintCart\Update\*` class already
 * used (`CLAUDE.md` §85):
 *
 * - **Restore.** This class only ever writes into the snapshot directory,
 *   never back into the live install — restoring from a snapshot is a
 *   separate, not-yet-built capability (`docs/adr/005-theme-extension-update-rollback.md`
 *   leaves rollback execution itself as a later increment).
 * - **Disk-space pre-flight.** `BackupPlanner::hasEnoughDiskSpace()`
 *   already answers "is there room," and is meant to run *before* this
 *   class does any I/O — this class does not re-check free space itself.
 * - **Wiring into `UpdateOrchestrator::apply()`.** That would add a
 *   `backup` step between `verify` and `maintenance`, changing `apply()`'s
 *   own tested signature/behavior — a separate, focused increment, same
 *   reasoning `PackageExtractor`'s own doc gives for not wiring itself
 *   into `apply()` either.
 * - **Retention/cleanup of old snapshots.** Functional Scope §21 names
 *   "retention policy" as an open research question; nothing here deletes
 *   an old snapshot directory other than the one it is directly replacing.
 */
final class BackupExecutor
{
    /**
     * Copies every path in `$plan['files']` from `$installRoot` into the
     * snapshot directory `$plan['snapshot_dir']` (itself relative to
     * `$installRoot` — the same convention `BackupPlanner::snapshotPath()`
     * already documents), preserving each file's relative path under that
     * snapshot directory.
     *
     * @param array{package: string, snapshot_dir: string, files: string[], total_bytes: int} $plan
     *        the exact shape `BackupPlanner::plan()` returns.
     *
     * @return array{snapshot_dir: string, backed_up: string[]} `backed_up`
     *         is sorted for deterministic output and may be a subset of
     *         `$plan['files']` (see class docblock's "silently skipped"
     *         note).
     *
     * @throws \InvalidArgumentException if any `$plan['files']` entry
     *                                    contains a `..` segment.
     * @throws \RuntimeException if a temp directory cannot be created, a
     *                            copy fails partway through, or the
     *                            temp-to-real rename fails.
     */
    public function backup(array $plan, string $installRoot): array
    {
        self::assertNoTraversal($plan['files']);

        $installRoot = rtrim($installRoot, '/\\');
        $snapshotDir = $installRoot . '/' . ltrim($plan['snapshot_dir'], '/\\');
        $tempDir = $snapshotDir . '.part-' . uniqid('', true);

        if (!@mkdir($tempDir, 0777, true) && !is_dir($tempDir)) {
            throw new \RuntimeException("Could not create temporary backup directory \"{$tempDir}\".");
        }

        $backedUp = [];

        foreach ($plan['files'] as $relativePath) {
            $sourcePath = $installRoot . '/' . ltrim($relativePath, '/\\');

            if (!is_file($sourcePath)) {
                // Already gone since plan() ran — nothing to snapshot, not
                // an error (see class docblock).
                continue;
            }

            self::copyFile($sourcePath, $tempDir . '/' . ltrim($relativePath, '/\\'));
            $backedUp[] = $relativePath;
        }

        if (is_dir($snapshotDir)) {
            self::removeDirectory($snapshotDir);
        }

        if (!@rename($tempDir, $snapshotDir)) {
            self::removeDirectory($tempDir);

            throw new \RuntimeException("Could not move backup snapshot into place at \"{$snapshotDir}\".");
        }

        sort($backedUp);

        return [
            'snapshot_dir' => $plan['snapshot_dir'],
            'backed_up' => $backedUp,
        ];
    }

    /**
     * Copies $sourcePath to $destPath via a temp-file-then-rename swap,
     * same pattern `PackageFileInstaller::copyFile()`/
     * `PackageDownloader::writeAtomically()` already use, creating any
     * missing destination parent directories first.
     */
    private static function copyFile(string $sourcePath, string $destPath): void
    {
        $destDir = dirname($destPath);

        if (!is_dir($destDir) && !@mkdir($destDir, 0777, true) && !is_dir($destDir)) {
            throw new \RuntimeException("Failed to create backup directory \"{$destDir}\".");
        }

        $tempPath = $destPath . '.part-' . uniqid('', true);

        if (!@copy($sourcePath, $tempPath)) {
            throw new \RuntimeException("Failed to copy \"{$sourcePath}\" to \"{$tempPath}\".");
        }

        if (!@rename($tempPath, $destPath)) {
            @unlink($tempPath);

            throw new \RuntimeException("Failed to move \"{$tempPath}\" into place at \"{$destPath}\".");
        }
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
                    "Backup plan path \"{$relativePath}\" contains a \"..\" segment — not allowed"
                );
            }
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
