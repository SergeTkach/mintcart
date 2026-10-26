<?php

use MintCart\Core\AdminPermissions;
use MintCart\Core\Migration\MigrationInterface;

/**
 * SEO -> Redirects: the table of old addresses and where they go, the admin permission for the
 * screen, and the events that write a redirect when a page's SEO URL keyword is changed.
 *
 * `old_path` is the keyword path without slashes at the ends (`old-mug`, `kitchen/old-mug`), lower
 * case; `language_id` 0 = any language. A row points either to a page (`route` + `args`, resolved
 * to its current SEO URL when the redirect fires, so a later keyword change does not break it) or to a
 * free `target_url` (manual redirects). 191 characters keep the unique key inside the 767-byte index
 * limit of older MySQL / MariaDB with utf8mb4.
 */
return new class implements MigrationInterface {
    private const TRACKED = [
        'catalog/product/editProduct',
        'catalog/category/editCategory',
        'catalog/information/editInformation',
        'catalog/manufacturer/editManufacturer',
    ];

    public function up(object $db): void
    {
        $db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "mc_redirect` (
                `redirect_id` INT(11) NOT NULL AUTO_INCREMENT,
                `store_id` INT(11) NOT NULL DEFAULT '0',
                `language_id` INT(11) NOT NULL DEFAULT '0',
                `old_path` VARCHAR(191) NOT NULL,
                `route` VARCHAR(64) NOT NULL DEFAULT '',
                `args` VARCHAR(255) NOT NULL DEFAULT '',
                `target_url` VARCHAR(512) NOT NULL DEFAULT '',
                `code` SMALLINT(3) NOT NULL DEFAULT '301',
                `source` VARCHAR(16) NOT NULL DEFAULT 'manual',
                `hits` INT(11) NOT NULL DEFAULT '0',
                `date_added` DATETIME NOT NULL,
                `last_hit` DATETIME DEFAULT NULL,
                PRIMARY KEY (`redirect_id`),
                UNIQUE KEY `store_language_path` (`store_id`, `language_id`, `old_path`),
                KEY `old_path` (`old_path`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $db->query("DELETE FROM `" . DB_PREFIX . "event` WHERE `code` LIKE 'mintcart_seo_redirect_track%'");

        foreach (self::TRACKED as $i => $route) {
            $db->query("INSERT INTO `" . DB_PREFIX . "event` SET `code` = 'mintcart_seo_redirect_track_" . $i . "', `trigger` = 'admin/model/" . $route . "/before', `action` = 'mintcart/seo/redirects/track', `status` = 1, `sort_order` = 0");
        }

        if (defined('DIR_OPENCART')) {
            $controllerDir = DIR_OPENCART . 'admin/controller/';
        } elseif (defined('DIR_APPLICATION')) {
            $controllerDir = DIR_APPLICATION . 'controller/';
        } else {
            return;
        }

        AdminPermissions::grant($db, DB_PREFIX, $controllerDir);
    }
};
