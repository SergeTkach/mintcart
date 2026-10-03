# `MintCart\Core\Migration\MigrationManager`

Schema-change runner for MintCart-owned tables (`MINTCART_TECHNICAL_PLAN.md`
§7 "Migration Manager", `MINTCART_IMPLEMENTATION_GUIDE.md` §40;
`CLAUDE.md` §12 migrations policy).

PHP: `upload/system/library/mintcart/core/migration/migrationmanager.php`
(class `MintCart\Core\Migration\MigrationManager`) and
`upload/system/library/mintcart/core/migration/migrationinterface.php`
(`MintCart\Core\Migration\MigrationInterface`)

Migration files: `upload/system/mintcart/migrations/*.php` — see that
directory's own `README.md` for the naming convention and file-shape
template used when actually writing a migration; this document covers the
runner's architecture and its existing consumers instead of repeating that
how-to.

Test: `tests/mintcart/core/migration-manager-test.php`
(`php tests/mintcart/core/migration-manager-test.php`) and
`tests/mintcart/core/mc-block-migrations-test.php` for the first real
migrations (`mc_page`/`mc_block`, Phase 10).

Consumers: `MintCart\Core\HealthCheck` (via `ModelMintcartHealth`, see
`docs/developer/core/health-check.md`) and
`MintCart\Update\UpdateOrchestrator` (see
`docs/developer/update/update-orchestrator.md`) — both described below.

## Design

`MigrationManager` takes OpenCart's own `\DB` instance, the table prefix and
a migrations directory, and exposes three operations:

```php
$manager = new MigrationManager($db, DB_PREFIX, DIR_SYSTEM . 'mintcart/migrations');

$manager->applied();  // string[] — filenames already recorded as applied, ascending
$manager->pending();  // string[] — filenames on disk but not yet applied, ascending
$manager->run();      // ['applied' => string[], 'skipped' => string[]]
```

`$db` is typed `object`, not OpenCart's concrete `\DB` class — it only needs
to duck-type `query()`/`escape()`. This keeps the class testable with a
lightweight in-memory double (no MySQL needed to exercise the ordering/
failure-handling logic) without introducing a MintCart-owned DB interface
that has no other consumer (`CLAUDE.md` §85/§86).

### Discovery and ordering

`discover()` globs `*.php` in the migrations directory and keeps only
filenames matching `^\d{12}_[a-z0-9_]+\.php$` (the
`YYYYMMDDHHMM_description.php` convention), then sorts them
lexicographically — which is also chronological, since the sortable
timestamp prefix is the first thing an ASCII sort compares. A file that
doesn't match the pattern (wrong prefix length, stray non-migration file
dropped in the same directory) is silently skipped, not treated as an
error.

### Apply-once tracking

Applied filenames are recorded in `{DB_PREFIX}mc_migration`
(`migration_id`, `migration` UNIQUE, `applied_at`), created on first use via
`ensureSchema()` (`CREATE TABLE IF NOT EXISTS`) rather than shipped as
migration `#1` — the same way most migration runners bootstrap their own
bookkeeping table before anything can be tracked in it.

`ensureSchema()` is memoized per `MigrationManager` instance
(`$schemaEnsured`) and is called internally by `applied()`, not just
`run()` — so **any** caller can safely ask `pending()`/`applied()` at any
time, including before a single migration has ever run on a fresh install.
This matters concretely: `applied()`'s `SELECT` runs against a real
`\DB\MySQLi` with `mysqli_report(MYSQLI_REPORT_ERROR)` enabled
(`system/library/db/mysqli.php`), which throws on a `SELECT` against a
table that was never `CREATE`d — exactly what a brand-new install looks
like. `HealthCheck` was the first caller to hit this (it asks `pending()`
outside of any `run()`), which is how the gap was found and fixed; see
`docs/developer/core/health-check.md`'s "A latent `MigrationManager` bug"
section for the full incident writeup. The memoization means a `run()`
(which internally calls `applied()` twice — once directly for `skipped`,
once again inside `pending()`) still only issues one `CREATE TABLE IF NOT
EXISTS`, not two.

### Running

`run()` applies every pending file in order via `runOne()`:

1. `require` the file, which must `return` an object implementing
   `MigrationInterface` — anything else throws `\RuntimeException`
   immediately, before any query runs.
2. Wrap the migration in `START TRANSACTION` / `COMMIT` / `ROLLBACK`.
   **Caveat documented directly in the source:** `CREATE`/`ALTER TABLE`
   implicitly commit in MySQL, so this transaction only buys atomicity for
   DML-only migrations — a `CREATE TABLE` migration that fails partway
   through cannot actually be rolled back by this mechanism. It is kept
   anyway per `CLAUDE.md` §12 ("critical multi-step operations use
   transactions where appropriate") — a no-op cost on DDL migrations, real
   protection on DML ones, so there's no reason to special-case it away.
3. On success, insert a tracking row for the filename and commit.
4. On any `\Throwable`, roll back and re-throw as a `\RuntimeException`
   wrapping the original exception — **the whole run stops on the first
   failure**; later pending migrations are not attempted, and earlier ones
   in the same `run()` call stay applied (this is also why
   `UpdateOrchestrator`'s own step sequence treats a `migrations` failure
   as non-reversible for the already-applied `files` step one level up —
   see below).

## How the two existing consumers use this

### `HealthCheck`'s `pending_migrations` signal

`ModelMintcartHealth::getSignals()` computes
`count((new MigrationManager(...))->pending())` and passes it to
`HealthCheck::evaluate()`, which reports `'ok'` when zero and `'warning'`
(deliberately not `'error'`) otherwise — a store mid-update with files
deployed but migrations not yet run is an expected, transient state. See
`docs/developer/core/health-check.md`.

### `UpdateOrchestrator`'s `migrations` step

`MintCart\Update\UpdateOrchestrator::apply()` runs a fixed step sequence —
`download → verify → maintenance → files → migrations → cache_clear →
health_check → done` (`MINTCART_TECHNICAL_PLAN.md` §33) — and calls
`$this->migrations->run()` as its `migrations` step, immediately after
`files` and immediately before `cache_clear`. Consistent with `run()`'s own
contract, a `migrations` failure here does not attempt to undo the already-
installed `files` step; it simply stops the orchestrator's step sequence at
that point, leaving the install in a state the next update attempt's
`pending()` can still see and retry. See
`docs/developer/update/update-orchestrator.md`.

## What this intentionally does not do

- **No down/rollback migrations.** `MigrationInterface` only declares
  `up()`. `MINTCART_FUNCTIONAL_SCOPE.md` §21 treats theme/extension file
  rollback and database rollback as a separate, harder, still-researched
  problem — `CLAUDE.md` explicitly says not to promise an automatic DB
  rollback without a reliable mechanism. Nothing here should be read as
  that mechanism.
- **No cross-request locking.** Concurrent `run()` calls (e.g. two admin
  requests triggering an update at once) are not guarded against here; the
  `UNIQUE KEY` on `migration` would turn a genuine double-apply attempt
  into a failed `INSERT` rather than silent data corruption, but this class
  does not itself serialize `run()` calls. No current consumer calls
  `run()` from more than one place per request, so this has not needed a
  solution yet (`CLAUDE.md` §85).
- **No per-migration metadata beyond the filename and timestamp applied.**
  No author, no description column, no "what tables did this touch" — the
  filename's own `description` segment is the only record of intent,
  matching the file-shape convention in
  `upload/system/mintcart/migrations/README.md`.

## Verified / not verified

- `php -l` clean on both class files and the test file.
- `tests/mintcart/core/migration-manager-test.php` (ordering, apply-once,
  failure-stops-the-run, the pre-`ensureSchema()` fix regression scenario)
  and `tests/mintcart/core/mc-block-migrations-test.php` both pass against
  the current code, via an in-memory `FakeDb` double — no real MySQL
  server in this environment.
- **Not verified** — same pre-existing gap as every other core-service doc
  in this repo: no live MySQL/OpenCart install here. A human should run
  `php upload/index.php?route=mintcart/health` (or trigger an actual
  `UpdateOrchestrator::apply()`) against a real database once and confirm
  `{DB_PREFIX}mc_migration` is created and populated as described, and that
  a deliberately-failing migration stops the run without applying anything
  after it.
