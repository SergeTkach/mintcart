<?php
namespace MintCart\Update;

/**
 * The "backup" step of the Update Manager flow (check → compatibility →
 * backup → download → verify → maintenance → files → migrations → cache
 * clear → health check → done — `MINTCART_TECHNICAL_PLAN.md` §33 /
 * `MINTCART_IMPLEMENTATION_GUIDE.md` §69): given a package manifest,
 * decides exactly which currently-installed files an update is about to
 * overwrite or delete, and where a snapshot of them should be written
 * before the update proceeds.
 *
 * Design follows `docs/adr/005-theme-extension-update-rollback.md`'s
 * answers to Functional Scope §21's rollback research questions:
 * - decision #3 (files a new package removes): `PackageManifest::removes()`
 *   is backed up through the same plan as an overwritten `files()` path,
 *   never deleted without a snapshot.
 * - decision #6 (disk limits): `hasEnoughDiskSpace()` is a pre-flight
 *   check meant to run before any file is touched, refusing to *start*
 *   rather than aborting partway through a copy already in progress.
 * - decision #7 (security): `snapshotPath()` places snapshots under
 *   `storage/mintcart/backup/`, outside any web-servable path —
 *   the same convention OpenCart already uses for its own non-servable
 *   files (`storage/cache`, `storage/logs`, ...).
 *
 * Deliberately has **no OpenCart dependency** and does **no filesystem
 * I/O of its own** (no `disk_free_space()`, no `filesize()`, no actual
 * copying) — same "resolved values in, normalized contract out" shape as
 * every other `MintCart\Update`/`MintCart\Seo` primitive in this codebase
 * (`PackageManifest`, `UpdateChecker`, `HreflangBuilder`, ...). A caller
 * resolves which of a manifest's declared paths currently exist on disk
 * (and their sizes) upstream; this class only decides what belongs in the
 * backup and whether there is room for it.
 *
 * What this deliberately does not do yet (see the ADR's "Consequences"
 * section) — each needs a real caller (the Update Manager's own `files`
 * step) that does not exist yet, per `CLAUDE.md` §85:
 * - No `mc_installed_file` ledger (per-file change detection via sha256,
 *   decision #1) — this class only knows what a manifest declares, not
 *   whether an installed file was hand-edited since its last install.
 * - No actual file copy/restore — `plan()` only decides *what* to copy;
 *   performing the copy is the flow's own `files` step.
 * - No manifest-to-manifest diffing across versions (decision #2).
 */
final class BackupPlanner
{
    /**
     * @param array<string,int> $existingFiles map of relative file path =>
     *        current size in bytes, for every path the caller has already
     *        confirmed exists on disk. A path the manifest declares
     *        (via `files()` or `removes()`) that is **not** a key in this
     *        map is treated as not currently installed — nothing to
     *        snapshot for it (a brand-new file the update only adds has
     *        nothing to back up).
     * @param string $timestamp snapshot identifier, e.g. a `YmdHis` string
     *        the caller generates. Not generated inside this class so the
     *        same plan can be recomputed deterministically in a test.
     *
     * @return array{
     *     package: string,
     *     snapshot_dir: string,
     *     files: string[],
     *     total_bytes: int
     * }
     */
    public function plan(PackageManifest $manifest, array $existingFiles, string $timestamp): array
    {
        $candidates = [];

        foreach (array_merge($manifest->files(), $manifest->removes()) as $path) {
            if (!in_array($path, $candidates, true)) {
                $candidates[] = $path;
            }
        }

        $files = [];
        $totalBytes = 0;

        foreach ($candidates as $path) {
            if (array_key_exists($path, $existingFiles)) {
                $files[] = $path;
                $totalBytes += $existingFiles[$path];
            }
        }

        return [
            'package' => $manifest->name(),
            'snapshot_dir' => self::snapshotPath($manifest->name(), $timestamp),
            'files' => $files,
            'total_bytes' => $totalBytes,
        ];
    }

    /**
     * Per ADR 005 decision #7: snapshots live outside any web-servable
     * path, the same `storage/` convention OpenCart already uses
     * for its own non-servable files — so a snapshot of a file like
     * `config.php` can never be fetched directly over HTTP.
     */
    public static function snapshotPath(string $packageName, string $timestamp): string
    {
        return 'storage/mintcart/backup/' . $packageName . '/' . $timestamp;
    }

    /**
     * Per ADR 005 decision #6: a pre-flight check, sized for both the
     * snapshot about to be written and the incoming package's own files
     * (an update copies new files onto the same disk a snapshot was just
     * written to) — call this *before* any file is touched. This must
     * never be used to abort a copy that has already begun; refusing to
     * start is the entire point of a pre-flight check.
     */
    public function hasEnoughDiskSpace(int $snapshotBytes, int $incomingPackageBytes, int $freeBytes): bool
    {
        return $freeBytes >= ($snapshotBytes + $incomingPackageBytes);
    }
}
