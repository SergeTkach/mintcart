<?php

use MintCart\Core\Migration\MigrationInterface;

/**
 * Manual product badges (stickers) - Marketing → Badges.
 *
 * OpenCart has no place for "put a Bestseller label on this product", and
 * the automatic badges (sale / out of stock / new) are computed, not stored,
 * so this is a Mint-specific table (CLAUDE.md §6/§39). One row = one sticker
 * on one product; a product may carry several, shown in `sort_order`.
 * `label` is a single text for all languages (stickers are short brand words
 * like "Hit" or "-20%"); `color` is an optional #rrggbb, empty = the theme's
 * neutral mc-badge look. No FOREIGN KEY, same as the rest of OpenCart's schema:
 * rows of a deleted product are removed by the admin model and ignored by
 * the storefront query otherwise.
 */
return new class implements MigrationInterface {
    public function up(object $db): void
    {
        $db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "mc_product_badge` (
                `badge_id` INT(11) NOT NULL AUTO_INCREMENT,
                `product_id` INT(11) NOT NULL,
                `label` VARCHAR(32) NOT NULL,
                `color` VARCHAR(7) NOT NULL DEFAULT '',
                `sort_order` INT(3) NOT NULL DEFAULT '0',
                `date_added` DATETIME NOT NULL,
                PRIMARY KEY (`badge_id`),
                KEY `product_id` (`product_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
    }
};
