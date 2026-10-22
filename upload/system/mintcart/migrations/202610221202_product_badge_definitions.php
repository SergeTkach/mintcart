<?php

use MintCart\Core\Migration\MigrationInterface;

/**
 * Product badges become a small set of reusable badges ("Bestseller",
 * "-20%", each with a colour) that are assigned to products, instead of one
 * free-text row per product:
 *
 *   mc_badge          - the set, edited in Marketing → Product Badges
 *   mc_product_badge  - which product carries which badge (product_id, badge_id)
 *
 * 202610221200 created mc_product_badge with a label and colour per row.
 * Existing rows are converted: each distinct label+colour becomes one
 * mc_badge, and the product keeps pointing at it. Nothing is lost on a store
 * that already added badges. No FOREIGN KEY, like the rest of OpenCart's
 * schema; the admin model deletes assignments when a badge or product goes.
 */
return new class implements MigrationInterface {
    public function up(object $db): void
    {
        $prefix = DB_PREFIX;

        $db->query("
            CREATE TABLE IF NOT EXISTS `{$prefix}mc_badge` (
                `badge_id` INT(11) NOT NULL AUTO_INCREMENT,
                `label` VARCHAR(32) NOT NULL,
                `color` VARCHAR(7) NOT NULL DEFAULT '',
                `sort_order` INT(3) NOT NULL DEFAULT '0',
                `date_added` DATETIME NOT NULL,
                PRIMARY KEY (`badge_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $db->query("RENAME TABLE `{$prefix}mc_product_badge` TO `{$prefix}mc_product_badge_old`");

        $db->query("
            CREATE TABLE `{$prefix}mc_product_badge` (
                `product_id` INT(11) NOT NULL,
                `badge_id` INT(11) NOT NULL,
                PRIMARY KEY (`product_id`, `badge_id`),
                KEY `badge_id` (`badge_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $db->query("
            INSERT INTO `{$prefix}mc_badge` (`label`, `color`, `sort_order`, `date_added`)
            SELECT `label`, `color`, MIN(`sort_order`), NOW() FROM `{$prefix}mc_product_badge_old` GROUP BY `label`, `color`
        ");

        $db->query("
            INSERT IGNORE INTO `{$prefix}mc_product_badge` (`product_id`, `badge_id`)
            SELECT o.`product_id`, b.`badge_id`
            FROM `{$prefix}mc_product_badge_old` o
            JOIN `{$prefix}mc_badge` b ON (b.`label` = o.`label` AND b.`color` = o.`color`)
        ");

        $db->query("DROP TABLE `{$prefix}mc_product_badge_old`");
    }
};
