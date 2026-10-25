<?php

use MintCart\Core\AdminPermissions;
use MintCart\Core\Migration\MigrationInterface;

/**
 * SEO -> URL Generator: the admin permission for its screen, and an event that loads the SEO URL
 * auto-fill (assets/dist/js/admin.min.js) on the product, category, information and brand forms,
 * which do not load MintCart's admin bundle themselves. Regular OpenCart events - no controller patch.
 */
return new class implements MigrationInterface {
    private const FORMS = [
        'catalog/product/add', 'catalog/product/edit',
        'catalog/category/add', 'catalog/category/edit',
        'catalog/information/add', 'catalog/information/edit',
        'catalog/manufacturer/add', 'catalog/manufacturer/edit',
    ];

    public function up(object $db): void
    {
        if (defined('DIR_OPENCART')) {
            $controllerDir = DIR_OPENCART . 'admin/controller/';
        } elseif (defined('DIR_APPLICATION')) {
            $controllerDir = DIR_APPLICATION . 'controller/';
        } else {
            return;
        }

        AdminPermissions::grant($db, DB_PREFIX, $controllerDir);

        $db->query("DELETE FROM `" . DB_PREFIX . "event` WHERE `code` LIKE 'mintcart_seo_url_autofill%'");

        foreach (self::FORMS as $i => $route) {
            $db->query("INSERT INTO `" . DB_PREFIX . "event` SET `code` = 'mintcart_seo_url_autofill_" . $i . "', `trigger` = 'admin/controller/" . $route . "/before', `action` = 'mintcart/seo/url_generator/assets', `status` = 1, `sort_order` = 0");
        }
    }
};
