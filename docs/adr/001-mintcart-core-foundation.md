# 001 — MintCart Core foundation: autoloading and migrations

## Context

Phase 1 (`MINTCART_TECHNICAL_PLAN.md` §42) needs a `MintCart\` PHP namespace,
a bootstrap/registry bridge, and a migration manager, without introducing
OCMOD, core patches, or a new framework (`CLAUDE.md` §1, §8, §37).

Two open questions had to be settled before writing any code:

1. How do `MintCart\*` classes get autoloaded without a Composer dependency
   or a core file edit?
2. What format do migrations take, and how are they tracked/applied?

## Decision

### Autoloading

OpenCart 3's `system/startup.php` already registers a generic autoloader:

```php
function library($class) {
    $file = DIR_SYSTEM . 'library/' . str_replace('\\', '/', strtolower($class)) . '.php';
    ...
}
spl_autoload_register('library');
```

This resolves any class name — namespaced or not — to a lowercased path
under `system/library/`. `MintCart\Core\Migration\MigrationManager` therefore
resolves to `system/library/mintcart/core/migration/migrationmanager.php`
with zero extra configuration.

We use this directly instead of adding a Composer `psr-4` autoloader. The
`DIR_STORAGE/vendor/autoload.php` hook lower in `startup.php` would work too,
but only after installation (`DIR_STORAGE` is undefined pre-install) and it
pulls in a build step (`composer install`) for something OpenCart's own
autoloader already does for free.

Consequence: `MintCart\*` class files must live under
`upload/system/library/mintcart/` and their path must be the lowercased,
slash-joined namespace — same rule OpenCart's own non-namespaced libraries
already follow.

### Migrations

`MigrationManager` (`MintCart\Core\Migration`) tracks applied migrations in
`{DB_PREFIX}mc_migration`, created on demand (`CREATE TABLE IF NOT EXISTS`)
rather than shipped as migration #1 — the tracking table cannot log its own
creation. Migration files are plain PHP under
`upload/system/mintcart/migrations/`, named `YYYYMMDDHHMM_description.php`
per `CLAUDE.md` §40, each `return`ing an anonymous class implementing
`MigrationInterface::up(object $db)`. Filename order fixes apply order; each
file is wrapped in `START TRANSACTION` / `COMMIT` / `ROLLBACK` and applied at
most once. A failing migration rolls back and stops the run.

`$db` is typed as `object`, not OpenCart's concrete `\DB` class: `\DB` has no
interface to depend on and cannot be given one without a core edit, so a
duck-typed `object` (query()/escape()) keeps `MigrationManager` decoupled and
testable with a lightweight double instead of a live MySQL connection — see
`tests/mintcart/core/migration-manager-test.php`.

## Consequences

- No Composer dependency introduced for MintCart Core itself.
- MintCart class file layout is constrained to mirror the namespace exactly
  (same constraint OpenCart's own libraries already have).
- `CREATE`/`ALTER TABLE` migrations implicitly commit in MySQL regardless of
  the surrounding `START TRANSACTION`; the transaction wrapper still gives
  atomicity to any future DML-only migration at no cost to DDL ones.
- `Bootstrap::boot()` has no automatic call site yet — nothing in the request
  lifecycle invokes it. The first real caller (Setup Wizard, Update Manager,
  or an admin extension controller) wires it explicitly; this avoids adding
  a core/index.php hook before there is a concrete consumer.
