# Update Manager rollback — Changed File Detector

`MintCart\Update\ChangedFileDetector`
(`upload/system/library/mintcart/update/changedfiledetector.php`)

## What this is

`docs/adr/005-theme-extension-update-rollback.md` decision #1 ("How to
reliably detect changed files") describes the comparison: a file counts
as "changed since install" when its **current on-disk** sha256 no longer
matches the **last recorded** value for that path — never against the
*incoming* package's expected content, which would flag every routine
content update as a manual edit.

`MintCart\Update\InstalledFileLedger` (`docs/developer/update/installed-file-ledger.md`)
already records that "last recorded value" per `(package_name, file_path)`
and already has a real caller (`UpdateOrchestrator::apply()`, right after
its `files` step succeeds), but its own "What's deliberately not attempted
here" section named the comparison itself as still missing — "no real
consumer yet (`CLAUDE.md` §85)." `ChangedFileDetector` is that comparison:
same "pure, OpenCart-independent primitive first" pattern `PackageVerifier`/
`PackageManifest`/`BackupPlanner` already established. It takes an
already-fetched ledger snapshot (`InstalledFileLedger::forPackage()`'s own
return shape) and the live filesystem — no `\Registry`, no `\DB` — so it
needs no OpenCart bootstrap to test.

## `detect()`

```php
$snapshot = $ledger->forPackage('mint_default'); // file_path => sha256
$result = ChangedFileDetector::detect('/path/to/live/install', $snapshot);
// [
//   'modified'  => ['catalog/view/theme/mint_default/.../header.twig'],
//   'missing'   => ['catalog/view/theme/mint_default/.../old-file.css'],
//   'unchanged' => ['catalog/view/theme/mint_default/.../product-card.twig', ...],
// ]
```

- Walks `$ledgerSnapshot` (not the filesystem) — a path the ledger never
  recorded is simply not considered, same "only check what's declared"
  shape `PackageVerifier::missingFiles()` uses against a manifest's own
  `files()` list.
- **`modified`** — the path still exists as a regular file but its current
  sha256 no longer matches the recorded one (hand-edited, or overwritten
  by something other than a tracked install).
- **`missing`** — the path is no longer a regular file at all (deleted, or
  replaced by a directory/symlink). Kept separate from `modified` because
  a caller building a rollback/restore UI needs to know which files it can
  actually diff-and-restore-in-place versus which it has to recreate from
  scratch.
- **`unchanged`** — current sha256 matches the recorded one exactly.
- Hex comparison is case-insensitive and constant-time (`hash_equals()`),
  matching `PackageVerifier::verifyChecksum()`'s own convention.
- A trailing slash on `$baseDir` is tolerated (same convention as
  `PackageVerifier::missingFiles()`).
- An empty snapshot (a package `InstalledFileLedger::forPackage()` has no
  rows for) returns three empty lists — nothing to compare, not an error.

## `hasChanges()`

```php
if (ChangedFileDetector::hasChanges($installDir, $snapshot)) {
    // warn before this update's `files` step overwrites something by hand
}
```

The yes/no a caller checks before deciding whether to surface a "some
files were changed by hand" warning at all, without inspecting the full
`detect()` breakdown. `true` when `detect()` would report at least one
`modified` or `missing` path; a `missing` file still counts as a change
worth flagging (its content was lost, not merely edited).

## What's deliberately not done yet

- **No restore.** This class only reports; it never writes a file.
  Restoring from a backup snapshot remains ADR 005's separate,
  not-yet-built rollback capability — `BackupPlanner`/`BackupExecutor`
  already snapshot files before an update overwrites them, but nothing
  yet offers to copy a snapshot back.
- **No manifest-diff classification** (ADR decision #2 — comparing an old
  package's `PackageManifest::files()` against a new one's to classify
  *why* a file is touched). This class only answers "is this specific
  path's on-disk content different from what was last installed," which
  is decision #1, not #2.
- **No `removes()` handling** (ADR decision #3). `PackageManifest` has no
  `removes` field yet, so nothing here decides what should happen to a
  ledger-recorded path that a new package version no longer declares.
- **No caller yet.** No admin screen or `UpdateOrchestrator` step calls
  `detect()`/`hasChanges()` — the admin `mintcart/update/*` route itself
  is still an open architectural question flagged by earlier runs (where
  does a package manifest/download URL come from). This class only
  supplies the comparison primitive that route (or a dedicated rollback
  screen) will need once that question is answered.

Verified: `php -l` clean;
`tests/mintcart/update/changed-file-detector-test.php` (15 scenarios — an
unchanged file, a hand-edited file, a deleted file, a mixed snapshot
exercising all three states together with `hasChanges()` checked at each
step, an empty snapshot, a case-insensitive recorded-hash match, and a
nested relative path under a trailing-slash `$baseDir`). Full
`tests/mintcart/**/*-test.php` suite re-run end to end (85 files, up from
84) — all passing, no regressions. Pure PHP against temp-directory
fixtures, no OpenCart dependency, so no live-install verification gap
applies here (same as `PackageVerifier`/`InstalledFileLedger` themselves).
