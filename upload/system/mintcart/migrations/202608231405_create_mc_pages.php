<?php

use MintCart\Core\Migration\MigrationInterface;

/**
 * Phase 10 (Homepage Builder) — Block API storage, part 1: pages.
 *
 * `mc_page` is the container a set of `mc_block` rows renders into.
 * MINTCART_FUNCTIONAL_SCOPE.md §10 and MINTCART_TECHNICAL_PLAN.md §17 scope
 * the Homepage Builder to a single page ("Homepage only") for now, but the
 * table is still page-shaped (not a single hardcoded homepage row) because
 * MINTCART_TECHNICAL_PLAN.md §16 documents `mc_page` as a real table with a
 * `code` identity, not a bespoke "homepage settings" table — the same
 * table shape a future second page (e.g. a landing page) would reuse.
 * `code` is how a page is looked up; only one row ('home') is expected to
 * exist until Homepage Builder's renderer/admin land.
 *
 * Column shape mirrors OpenCart's own `oc_category` / `oc_category_description`
 * split (structural row + one localized description row per language,
 * composite PK, no FOREIGN KEY constraints — OpenCart 3 does not use them
 * anywhere in its own schema, cascade is handled at the application layer).
 */
return new class implements MigrationInterface {
    public function up(object $db): void
    {
        $db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "mc_page` (
                `page_id` INT(11) NOT NULL AUTO_INCREMENT,
                `code` VARCHAR(64) NOT NULL,
                `status` TINYINT(1) NOT NULL DEFAULT '1',
                `date_added` DATETIME NOT NULL,
                `date_modified` DATETIME NOT NULL,
                PRIMARY KEY (`page_id`),
                UNIQUE KEY `code` (`code`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "mc_page_description` (
                `page_id` INT(11) NOT NULL,
                `language_id` INT(11) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                PRIMARY KEY (`page_id`, `language_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
    }
};
