<?php

use MintCart\Core\Migration\MigrationInterface;

/**
 * Phase 10 (Homepage Builder) — Block API storage, part 2: blocks.
 *
 * `mc_block` is one instance of a block type (Hero, Products, Categories, ...)
 * placed on an `mc_page`. MINTCART_TECHNICAL_PLAN.md §16 and
 * MINTCART_IMPLEMENTATION_GUIDE.md §34 both document this exact table shape
 * (`block_id`, `page`, `type`, `sort_order`, `status`, `settings_json`) and
 * explicitly warn against a separate SQL table per block type — `type` plus
 * one shared `settings_json` blob is how a Hero block and a Products block
 * share one table instead of `mc_block_hero` / `mc_block_products` / ...
 *
 * `settings_json` (LONGTEXT, not the MySQL JSON column type) holds
 * non-localized, provider-specific settings for that block instance (e.g.
 * Products block's `limit`/`category_id`/`sort`). LONGTEXT is used instead
 * of JSON for broad MySQL/MariaDB version compatibility on typical shared
 * hosting (MINTCART_TECHNICAL_PLAN.md §2's "deployable on typical shared
 * hosting" goal) — the same reasoning OpenCart's own core schema follows,
 * where it never uses the JSON column type either.
 *
 * `mc_block_description` holds the per-language slice of a block's content
 * (e.g. a Hero's title/subtitle/CTA label, a Text block's body) — kept in
 * its own `content_json` column, separate from `mc_block.settings_json`,
 * so structural settings never get duplicated per language and localized
 * text never has to be threaded through the language-agnostic row. No
 * block provider/renderer consumes either JSON blob's shape yet (Phase 10's
 * "Block Provider / Renderer / Template separation" and first block types
 * are separate, not-yet-started checklist items) — only the storage
 * contract is established here, matching how `mc_page`/`mc_page_description`
 * (this same run) and every previous phase's migrations landed ahead of
 * their consumers.
 */
return new class implements MigrationInterface {
    public function up(object $db): void
    {
        $db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "mc_block` (
                `block_id` INT(11) NOT NULL AUTO_INCREMENT,
                `page_id` INT(11) NOT NULL,
                `type` VARCHAR(64) NOT NULL,
                `sort_order` INT(3) NOT NULL DEFAULT '0',
                `status` TINYINT(1) NOT NULL DEFAULT '1',
                `settings_json` LONGTEXT NOT NULL,
                `date_added` DATETIME NOT NULL,
                `date_modified` DATETIME NOT NULL,
                PRIMARY KEY (`block_id`),
                KEY `page_id` (`page_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "mc_block_description` (
                `block_id` INT(11) NOT NULL,
                `language_id` INT(11) NOT NULL,
                `content_json` LONGTEXT NOT NULL,
                PRIMARY KEY (`block_id`, `language_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
    }
};
