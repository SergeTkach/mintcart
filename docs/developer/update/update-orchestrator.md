# Update Manager — Orchestrator

`MintCart\Update\UpdateOrchestrator`
(`upload/system/library/mintcart/update/updateorchestrator.php`)

## What this is

Chains the `download` through `health check` steps of the Update Manager
flow described in `MINTCART_TECHNICAL_PLAN.md` §33 and
`MINTCART_IMPLEMENTATION_GUIDE.md` §69:

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

Every individual step already existed as its own pure, unit-tested
primitive before this class was written: `PackageDownloader` (`download`),
`PackageVerifier` (`verify`), `MaintenanceMode` (`maintenance`),
`PackageFileInstaller` (`files`), the Phase 1
`MintCart\Core\Migration\MigrationManager` (`migrations`),
`MintCart\Core\Cache\CacheInvalidator` (`cache clear`) and
`MintCart\Core\HealthCheck` (`health check`). Each one's own docblock named
"an orchestrator that chains these steps end to end" as the natural next
increment once they all existed (`docs/developer/update/package-file-installer.md`'s
own "Next step" note, most recently). This class is that orchestrator — it
owns sequencing and failure handling, not any step's own logic
(`CLAUDE.md` §38E: reuse by responsibility, not by copying logic into a new
class).

## What's deliberately still out of scope

- **`check`/`compatibility`.** These are earlier decision points a caller
  makes *before* deciding to apply a specific manifest at all — e.g.
  showing an admin user "an update is available and compatible" via
  `UpdateChecker` and letting them confirm. `apply()` still starts from
  "the operator has already decided to apply this exact manifest."
- ~~Archive extraction.~~ **Wired in (optional).** `MintCart\Update\PackageExtractor`
  (`docs/developer/update/package-extractor.md`) is now an optional 7th
  constructor argument. When injected, `apply()` calls `extract($downloadPath,
  $extractedDir)` between `download` and `verify`, populating `$extractedDir`
  from the archive itself. When omitted (`null`, the default), `apply()`
  behaves exactly as before: `$extractedDir` is assumed already populated by
  the caller.
- ~~`backup`.~~ **Wired in (optional).** `MintCart\Update\BackupPlanner`
  (8th constructor argument) and `MintCart\Update\BackupExecutor`
  (`docs/developer/update/backup-executor.md`, 9th argument) are injected as
  a pair — passing only one throws `\InvalidArgumentException` at
  construction time, since a half-configured backup is a caller bug, not a
  "skip this step" signal. When both are provided, `apply()` resolves which
  of the manifest's declared paths currently exist under `$destRoot` (and
  their sizes), builds a plan via `BackupPlanner::plan()` using an
  injectable `$timestampFactory` (10th argument, defaults to
  `date('YmdHis')`), and snapshots them via `BackupExecutor::backup()`
  between `verify` and `maintenance` — before anything is overwritten, and
  before the store is even put into maintenance mode. When omitted, no
  snapshot is taken, exactly as before.
- ~~The `mc_installed_file` ledger.~~ **Wired in (optional).**
  `MintCart\Update\InstalledFileLedger`
  (`docs/developer/update/installed-file-ledger.md`, 11th constructor
  argument) is injected the same independently-optional way as
  `cache`/`healthCheck`. When provided, `apply()` calls
  `InstalledFileLedger::record($manifest->name(), $result['files']['installed'])`
  right after the `files` step succeeds — the exact input
  `PackageFileInstaller::install()`'s own docblock named as "the exact
  input the future `mc_installed_file` ledger needs once a real caller
  writes it." When omitted (the default), no ledger write is attempted,
  exactly as before this wiring existed.
- **Any admin controller/route.** No `mintcart/update/*` route exists yet.
  This class is the flow's orchestration primitive; wiring a real admin
  screen (upload a package, show progress, call `apply()`) around it is
  later work.

## `apply()`

```php
$orchestrator = new UpdateOrchestrator(
    new PackageDownloader(),
    new MaintenanceMode($settingsAdapter),
    new PackageFileInstaller(),
    new MigrationManager($db, DB_PREFIX, $migrationsPath),
    new CacheInvalidator([$cache, 'delete']),   // optional
    new HealthCheck(),                            // optional
    new PackageExtractor(),                       // optional — extract download archive
    new BackupPlanner(),                          // optional, must pair with BackupExecutor
    new BackupExecutor(),                         // optional, must pair with BackupPlanner
    null,                                          // $timestampFactory — optional, defaults to date('YmdHis')
    new InstalledFileLedger($db, DB_PREFIX)       // optional — records files-step sha256s
);

$result = $orchestrator->apply(
    $manifest,
    'https://updates.example.com/mint_default-1.1.0.zip',
    DIR_SYSTEM . 'storage/download/mint_default-1.1.0.zip', // download destination
    DIR_SYSTEM . 'storage/download/mint_default-1.1.0/',    // extraction destination
    DIR_APPLICATION . '..',                                  // live install root
    $healthSignals                                           // optional, passed to HealthCheck::evaluate()
);

// $result['status'] === 'done' | 'failed'
// $result['completed_steps'] === ['download', 'extract', 'verify', 'backup', 'maintenance', 'files', 'migrations', 'cache_clear', 'health_check', 'done']
// (with PackageExtractor/BackupPlanner+BackupExecutor omitted, 'extract'/'backup' are
// simply absent from completed_steps and $extractedDir is assumed already populated —
// the exact same 8-step sequence this class always produced)
```

- **Stops at the first failed step.** Every step already completed stays in
  effect — e.g. a `migrations` failure does not undo the `files` step,
  matching `MigrationManager::run()`'s own "stops on first failure, earlier
  migrations stay applied" contract one level up. `$result['failed_step']`
  and `$result['error']` name exactly where and why.
- **Maintenance-mode safety.** `maintenance` is the first step whose
  failure leaves lasting state: once `MaintenanceMode::enable()` has run,
  `apply()` never calls `disable()` on a failure path — a half-applied
  update must never be silently reopened to shoppers. On success,
  `disable()` only runs if the store was **not** already in maintenance
  mode before `apply()` was called (`$result['maintenance']['was_already_enabled']`),
  so an operator who put the store into maintenance for unrelated reasons
  never has it silently reopened by this update.
- **`cache`/`healthCheck` are optional constructor arguments.** Passing
  `null` (the default) skips that step entirely: `cache_clear` is recorded
  as `false` (nothing to invalidate) rather than pretending a clear
  happened, and the `health_check` result key is simply absent rather than
  a fabricated result.
- **`extractor`/`backupPlanner`+`backupExecutor` are also optional
  constructor arguments**, but unlike `cache`/`healthCheck` (always
  recorded in `completed_steps`, whether skipped or not), an omitted
  `extract`/`backup` step is not inserted into `completed_steps` at all —
  the step structurally does not apply to that call, rather than running
  as a no-op. `backupPlanner`/`backupExecutor` must be passed together
  (both `null` or both set); passing only one throws
  `\InvalidArgumentException` at construction time.
- **`installedFiles` (`InstalledFileLedger`) is independently optional**,
  same as `cache`/`healthCheck` — but its call is not a named flow step of
  its own (it is not one of the `check → ... → done` sequence names), so it
  never appears in `completed_steps`; a failure while recording surfaces as
  a `migrations`-step failure (the next named step after `files`), since
  the ledger write happens between the two.
- **No OpenCart dependency of its own.** Every dependency is injected —
  `MigrationManager`'s `$db` still only needs to duck-type OpenCart's
  `\DB` (`query()`/`escape()`), `MaintenanceMode` needs a `SettingsAdapter`,
  `CacheInvalidator` needs a `callable(string): void`. This class adds no
  new OpenCart coupling beyond what its constituent steps already required.

## Verified

`php -l` clean on the modified file and across the full `upload`/`tests`
tree. `tests/mintcart/update/update-orchestrator-test.php` (11 scenarios,
up from 10 — **new:** injecting an `InstalledFileLedger` records the
files step's own `path`/`sha256` under the manifest's own package name,
verified against a `FakeLedgerDb2` double that parses the `INSERT`
statement's own values, same technique
`tests/mintcart/update/installed-file-ledger-test.php` uses for the class
itself. Earlier scenarios unchanged: full happy path in step order; a store already in maintenance
mode before `apply()` stays in maintenance mode after a successful run; a
checksum mismatch fails at `verify` before maintenance mode is ever
touched; an extracted package missing a declared file also fails at
`verify`; a mid-flow failure during `files` leaves the store in
maintenance mode rather than reopening it; a rejected download URL scheme
fails at `download` with nothing else touched; omitting the optional
`cache`/`healthCheck` dependencies skips those two steps without
fabricating a result; **new:** injecting a real `PackageExtractor` extracts
a real zip fixture (built with `ZipArchive`, same technique
`package-extractor-test.php` uses) into `$extractedDir` before `verify`,
with `extract` inserted into `completed_steps` between `download` and
`verify`; **new:** injecting `BackupPlanner`+`BackupExecutor` together
snapshots a pre-existing file about to be overwritten into
`system/storage/mintcart/backup/<package>/<timestamp>/` before
`maintenance` runs, verified by reading the snapshotted content back off
disk and confirming it holds the *pre-update* value while the live file
holds the *post-update* value; **new:** passing only one of
`backupPlanner`/`backupExecutor` throws `\InvalidArgumentException` at
construction) — all passing, reusing the exact same in-memory `FakeDb`
(`tests/mintcart/core/migration-manager-test.php`) and `FakeSettingModel`
(`tests/mintcart/update/maintenance-mode-test.php`) doubles those sibling
tests already established. Full `tests/mintcart/**/*-test.php` suite
re-run end to end (84 files, up from 83) — all passing, no regressions.
`tests/mintcart/core/mc-block-migrations-test.php` was updated for the
new `202609191200_create_mc_installed_file.php` migration now discovered
alongside the Homepage Builder ones it was already asserting on.

**Not verified:** no live MySQL/filesystem/HTTP in this environment, same
pre-existing gap every other Update Manager primitive in this codebase
carries — there is no real download/admin-controller pipeline yet for this
orchestrator to run against on a live install (extraction and backup
themselves are now exercised end to end against real files, same as
`PackageExtractor`'s/`BackupExecutor`'s own tests already were).
