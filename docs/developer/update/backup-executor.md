# Update Manager — Backup Executor

`MintCart\Update\BackupExecutor`
(`upload/system/library/mintcart/update/backupexecutor.php`)

## What this is

Executes the "backup" step of the Update Manager flow described in
`MINTCART_TECHNICAL_PLAN.md` §33 and `MINTCART_IMPLEMENTATION_GUIDE.md` §69:

```text
check
→ compatibility
→ backup
→ download
→ verify
→ maintenance
→ files
→ migrations
→ cache clear
→ health check
→ done
```

`MintCart\Update\BackupPlanner::plan()` already decides *which*
currently-installed files an update is about to overwrite or delete, and
*where* a snapshot of them belongs
(`docs/adr/005-theme-extension-update-rollback.md` decisions #3/#6/#7) —
but that class's own docblock says plainly: "performing the copy is the
flow's own `files` step." Nothing in this codebase ever did that copy.
`PackageFileInstaller::install()` (the actual `files` step) only copies
the *new* package's files into the live install; it never calls
`BackupPlanner` at all. `BackupExecutor` is the missing execution half of
`BackupPlanner::plan()` — the same "planner decides, executor performs"
split `PackageExtractor`'s own doc already draws between `PackageVerifier`
and itself, applied here to the backup step instead of extraction.

## `backup()`

```php
$planner = new BackupPlanner();
$plan = $planner->plan($manifest, $existingFiles, '20260918120000');

$executor = new BackupExecutor();
$result = $executor->backup($plan, DIR_OPENCART); // ['snapshot_dir' => ..., 'backed_up' => [...]]
```

`$plan` is exactly `BackupPlanner::plan()`'s own return shape — this class
takes no `PackageManifest` and does not recompute anything `plan()`
already decided. `$installRoot` is the live install root both the
existing files being snapshotted and the snapshot directory itself
(`$plan['snapshot_dir']`, per `BackupPlanner::snapshotPath()`) are
relative to — the same root `PackageFileInstaller::install()`'s own
`$destRoot` parameter already means.

Design choices, deliberately mirroring `PackageExtractor::extract()`:

- **Atomic as a whole.** Files are copied into a `.part-<uniqid>` temp
  directory next to the real snapshot directory first, then that temp
  directory replaces the real one via `rename()`. A request that dies
  mid-backup never leaves a half-written snapshot at the real path a
  later restore might trust.
- **Wholesale replace, not merge.** A pre-existing snapshot directory at
  the same path (e.g. a retried update reusing the same timestamp) is
  replaced entirely — a stale leftover file from a previous, possibly
  failed backup attempt must never silently survive alongside the new
  one.
- **No path traversal.** A `$plan['files']` entry containing a `..`
  segment throws `\InvalidArgumentException` before anything is touched —
  mirrors `PackageFileInstaller::assertNoTraversal()`'s reasoning one
  level up (manifest-declared paths there, planner-declared paths here).

A `$plan['files']` path that no longer exists under `$installRoot` at
backup time (e.g. removed by something else between `plan()` and this
call) is **silently skipped, not an error** — mirrors
`BackupPlanner::plan()`'s own "not currently installed" handling for a
path missing from its `$existingFiles` map.

## Deliberately not attempted here

- **Restore.** This class only ever writes *into* the snapshot directory,
  never back into the live install. Restoring from a snapshot is a
  separate, not-yet-built capability —
  `docs/adr/005-theme-extension-update-rollback.md` leaves rollback
  execution itself as a later increment.
- **Disk-space pre-flight.** `BackupPlanner::hasEnoughDiskSpace()` already
  answers "is there room" and is meant to run *before* this class does any
  I/O. This class does not re-check free space itself.
- **Wiring into `UpdateOrchestrator::apply()`.** That would add a `backup`
  step between `verify` and `maintenance`, changing `apply()`'s own
  tested signature/behavior — a separate, focused increment, same
  reasoning `PackageExtractor`'s own doc gives for not wiring itself into
  `apply()` either. (Both `PackageExtractor` and `BackupExecutor` now
  await the same kind of `apply()` signature change — a natural candidate
  to do together in one follow-up increment rather than two separate
  signature changes to the same method.)
- **Retention/cleanup of old snapshots.** Functional Scope §21 names
  "retention policy" as an open research question; nothing here deletes a
  snapshot directory other than the one it is directly replacing.

## What's deliberately not done yet

- No caller wires `BackupPlanner` + `BackupExecutor` into
  `UpdateOrchestrator::apply()` (see above).
- No restore/rollback execution (see above).
- No admin controller/`mintcart/update/*` route calls any of this yet.

Verified: `php -l` clean;
`tests/mintcart/update/backup-executor-test.php` (6 scenarios — backing up
existing plan files and returning their sorted relative paths, silently
skipping a plan file that no longer exists, an empty plan still producing
a well-formed empty snapshot directory, a stale pre-existing snapshot
directory replaced wholesale rather than merged into, no leftover
`.part-*` temp directory after success, and a `..` plan file path rejected
before anything is written). Pure PHP against real files/directories on
disk, no OpenCart dependency and no network dependency, so — like
`PackageExtractor`'s own test — there is no "never exercised end to end"
caveat for the happy path.
