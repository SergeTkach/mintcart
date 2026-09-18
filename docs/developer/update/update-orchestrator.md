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

- **`check`/`compatibility`/`backup`.** These are earlier decision points a
  caller makes *before* deciding to apply a specific manifest at all — e.g.
  showing an admin user "an update is available and compatible" via
  `UpdateChecker` and letting them confirm, or calling `BackupPlanner::plan()`
  followed by `MintCart\Update\BackupExecutor::backup()`
  (`docs/developer/update/backup-executor.md`) to actually snapshot files
  before anything is fetched. Both planning and execution now exist as
  standalone, tested primitives, but neither is called from `apply()`
  itself — wiring a `backup` step in between `verify` and `maintenance`
  would change `apply()`'s own tested signature/behavior, the same
  deliberately-deferred reasoning `PackageExtractor`'s doc gives for not
  wiring itself into `apply()` either. `apply()` starts from "the operator
  has already decided to apply this exact manifest, and a backup (if any)
  has already been taken," the same way `PackageVerifier`/
  `PackageFileInstaller` already assume their own inputs are already
  resolved rather than re-deciding them one level up.
- **Archive extraction.** No extractor exists anywhere in this codebase yet
  — both `PackageDownloader`'s and `PackageFileInstaller`'s docblocks
  already named this the same open gap. `apply()` takes an
  already-extracted directory (`$extractedDir`) as a plain input parameter,
  exactly like `PackageVerifier::missingFiles()`/`PackageFileInstaller::install()`
  already do. A real caller is expected to extract the archive
  `download` just wrote to `$downloadPath` before calling `apply()` — how
  (ZipArchive vs. something else, streaming vs. full extract) is its own
  design decision, deliberately not made here.
- **The `mc_installed_file` ledger** (ADR 005 decision #1). `apply()`'s
  result carries every installed file's sha256 under `files.installed[]`
  (from `PackageFileInstaller::install()`) — the exact input that ledger
  needs — but this class does not persist it. No table exists yet; adding
  one is a Migration Manager change for whoever builds the admin screen
  around this orchestrator, not this class's job.
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
    new HealthCheck()                            // optional
);

$result = $orchestrator->apply(
    $manifest,
    'https://updates.example.com/mint_default-1.1.0.zip',
    DIR_SYSTEM . 'storage/download/mint_default-1.1.0.zip', // download destination
    DIR_SYSTEM . 'storage/download/mint_default-1.1.0/',    // already-extracted source
    DIR_APPLICATION . '..',                                  // live install root
    $healthSignals                                           // optional, passed to HealthCheck::evaluate()
);

// $result['status'] === 'done' | 'failed'
// $result['completed_steps'] === ['download', 'verify', 'maintenance', 'files', 'migrations', 'cache_clear', 'health_check', 'done']
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
- **No OpenCart dependency of its own.** Every dependency is injected —
  `MigrationManager`'s `$db` still only needs to duck-type OpenCart's
  `\DB` (`query()`/`escape()`), `MaintenanceMode` needs a `SettingsAdapter`,
  `CacheInvalidator` needs a `callable(string): void`. This class adds no
  new OpenCart coupling beyond what its constituent steps already required.

## Verified

`php -l` clean on the new file and across the full `upload`/`tests` tree.
New `tests/mintcart/update/update-orchestrator-test.php` (7 scenarios — full
happy path in step order; a store already in maintenance mode before
`apply()` stays in maintenance mode after a successful run; a checksum
mismatch fails at `verify` before maintenance mode is ever touched; an
extracted package missing a declared file also fails at `verify`; a
mid-flow failure during `files` leaves the store in maintenance mode
rather than reopening it; a rejected download URL scheme fails at
`download` with nothing else touched; omitting the optional `cache`/
`healthCheck` dependencies skips those two steps without fabricating a
result) — all passing, reusing the exact same in-memory `FakeDb`
(`tests/mintcart/core/migration-manager-test.php`) and `FakeSettingModel`
(`tests/mintcart/update/maintenance-mode-test.php`) doubles those sibling
tests already established. Full `tests/mintcart/**/*-test.php` suite
re-run end to end (81 files, up from 80) — all passing, no regressions.

**Not verified:** no live MySQL/filesystem/HTTP in this environment, same
pre-existing gap every other Update Manager primitive in this codebase
carries — there is no real download/extraction/admin-controller pipeline
yet for this orchestrator to run against on a live install.
