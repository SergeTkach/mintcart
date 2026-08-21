# MintCart migrations

Schema changes for MintCart-owned tables go here — never as ad-hoc `ALTER`/`CREATE`
statements scattered across installers or controllers (see `CLAUDE.md` §12/§40).

## Naming

```
YYYYMMDDHHMM_description.php
```

Example: `202608211200_create_mc_page.php`

Files are applied in filename order by
`MintCart\Core\Migration\MigrationManager`, so the timestamp prefix also fixes
the apply order.

## File shape

Each file must `return` an object implementing
`MintCart\Core\Migration\MigrationInterface`. An anonymous class is the
expected shape — no separate class file, no autoloading concerns:

```php
<?php

use MintCart\Core\Migration\MigrationInterface;

return new class implements MigrationInterface {
    public function up(object $db): void
    {
        $db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "mc_page` (
                `page_id` INT(11) NOT NULL AUTO_INCREMENT,
                `code` VARCHAR(64) NOT NULL,
                `status` TINYINT(1) NOT NULL DEFAULT 1,
                PRIMARY KEY (`page_id`),
                UNIQUE KEY `code` (`code`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
    }
};
```

## Rules

- New MintCart tables use `InnoDB` / `utf8mb4` (`CLAUDE.md` §12).
- A migration runs at most once — `MigrationManager` records every applied
  filename in `{DB_PREFIX}mc_migration` and skips it on later runs.
- A failing migration rolls back and stops the whole run; later pending
  migrations are not attempted.
- Do not duplicate data OpenCart already stores (store name, contacts,
  currency, payment/shipping config, ...) — see `CLAUDE.md` §6/§39. This
  directory is for Mint-specific tables only.
- This directory is currently empty: no MintCart-owned table exists yet.
  The first real migration lands with whichever feature first needs one
  (e.g. `mc_page`/`mc_block` for the Homepage Builder).
