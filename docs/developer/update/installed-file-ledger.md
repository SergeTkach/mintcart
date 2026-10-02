# Update Manager — Installed File Ledger

`MintCart\Update\InstalledFileLedger`
(`upload/system/library/mintcart/update/installedfileledger.php`)

## What this is

The `mc_installed_file` table `docs/adr/005-theme-extension-update-rollback.md`
decision #1 designed but deliberately did not build: "one row per
`(package_name, file_path)`, overwritten on every successful install of
that package," recording the sha256 written to disk so a future rollback
increment can detect a file changed since install — comparing the file's
*current* on-disk sha256 against this table's *last recorded* value for
that path, **never** against the *incoming* package's own expected
content (which would flag every routine content update as a manual edit).

`MintCart\Update\PackageFileInstaller::install()`'s `installed` entries
(`array{path: string, sha256: string}`) are exactly this table's input —
that class's own docblock named this table as "the exact input the future
`mc_installed_file` ledger needs once a real caller writes it."
`MintCart\Update\UpdateOrchestrator::apply()` is that real caller: it
calls `record()` right after its `files` step succeeds (see
`docs/developer/update/update-orchestrator.md`).

Migration: `upload/system/mintcart/migrations/202609191200_create_mc_installed_file.php`
(InnoDB/utf8mb4 per `CLAUDE.md` §12, `UNIQUE KEY` on `(package_name, file_path)`).

## `record()` / `forPackage()`

```php
$ledger = new InstalledFileLedger($db, DB_PREFIX);

$ledger->record($manifest->name(), $result['files']['installed']);
// upserts one row per installed file, keyed by (package_name, file_path)

$ledger->forPackage('mint_default');
// ['theme.json' => '<sha256>', 'catalog/view/theme/mint_default/template/mint/components/product-card.twig' => '<sha256>', ...]
```

- **Upsert, not insert.** A re-install of the same package overwrites its
  previous rows' `sha256`/`installed_at` (`INSERT ... ON DUPLICATE KEY
  UPDATE`) rather than accumulating duplicate rows — "not a version
  history," matching decision #5's identical call for snapshot retention.
- **`$installedAt` defaults to `date('Y-m-d H:i:s')`** when omitted —
  injectable for deterministic tests, the same reasoning
  `UpdateOrchestrator`'s own `$timestampFactory` already established.
- `$db` only needs to duck-type OpenCart's `\DB` (`query()`/`escape()`) —
  same as `MintCart\Core\Migration\MigrationManager`, kept testable with a
  lightweight double rather than introducing a new MintCart-owned DB
  interface with no other consumer.

## What's deliberately not attempted here

- ~~No "changed since install" comparison~~ **Done** —
  `MintCart\Update\ChangedFileDetector`
  (`docs/developer/update/changed-file-detector.md`) now takes exactly
  `forPackage()`'s own return shape and compares it against a live file's
  current sha256 (decision #1's actual detection logic). No caller wires
  it into an admin screen yet — see that class's own "What's deliberately
  not done yet."
- **No restore/rollback.** This class only ever writes/reads the ledger
  table, never touches a live file. Restoring from a snapshot remains a
  separate, not-yet-built capability per the ADR.
- **No retention/pruning.** Every row for a package is simply overwritten
  on its next install; nothing here deletes a package's rows once they
  stop being current (e.g. an uninstalled extension's leftover rows are
  not cleaned up).

Verified: `php -l` clean on both new files
(`installedfileledger.php`, the migration).
`tests/mintcart/update/installed-file-ledger-test.php` (6 scenarios —
`record()`/`forPackage()` round-trip, a second package's rows never leak
into the first's, re-recording the same `(package_name, file_path)`
overwrites rather than duplicates, the `installedAt` default produces a
well-formed row, an unknown package returns an empty array, and a value
containing a single quote round-trips correctly through `escape()`), plus
a new `UpdateOrchestrator` scenario (11 total, up from 10) confirming
`apply()` calls `record()` with the manifest's own package name and the
`files` step's own path/sha256 right after that step succeeds. Full
`tests/mintcart/**/*-test.php` suite re-run end to end (84 files, up from
83) — all passing, no regressions.

**Not verified:** no live MySQL in this environment, same pre-existing gap
every other Mint-owned table in this codebase carries — the `INSERT ...
ON DUPLICATE KEY UPDATE` query has never run against a real MySQL/MariaDB
server, only against the in-memory `FakeLedgerDb`/`FakeLedgerDb2` doubles
described above.
