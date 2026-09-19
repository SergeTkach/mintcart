<?php

use MintCart\Core\Migration\MigrationInterface;

/**
 * Phase 11 (Update Manager) — the `mc_installed_file` ledger
 * `docs/adr/005-theme-extension-update-rollback.md` decision #1 named as a
 * future increment's job: "one row per (package_name, file_path),
 * overwritten on every successful install of that package," recording the
 * sha256 written to disk so a later rollback increment can detect files
 * changed since install (current on-disk sha256 vs. this table's last
 * recorded value — never against the incoming package's own expected
 * content, which would flag every routine content update as a manual
 * edit).
 *
 * `MintCart\Update\PackageFileInstaller::install()` already returns exactly
 * this shape (`installed: [{path, sha256}]`) from every `files` step run —
 * see `MintCart\Update\InstalledFileLedger::record()`, this table's one
 * real consumer, wired into `UpdateOrchestrator::apply()` right after the
 * `files` step succeeds.
 */
return new class implements MigrationInterface {
    public function up(object $db): void
    {
        $db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "mc_installed_file` (
                `installed_file_id` INT(11) NOT NULL AUTO_INCREMENT,
                `package_name` VARCHAR(255) NOT NULL,
                `file_path` VARCHAR(255) NOT NULL,
                `sha256` CHAR(64) NOT NULL,
                `installed_at` DATETIME NOT NULL,
                PRIMARY KEY (`installed_file_id`),
                UNIQUE KEY `package_file` (`package_name`, `file_path`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
    }
};
