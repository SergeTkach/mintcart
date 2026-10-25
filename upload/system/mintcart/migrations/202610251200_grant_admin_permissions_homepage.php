<?php

use MintCart\Core\AdminPermissions;
use MintCart\Core\Migration\MigrationInterface;

/**
 * Design > Homepage (admin/controller/design/homepage.php) and design/media sit
 * outside admin/controller/mintcart/, so no earlier grant covered them and the
 * menu entry stayed hidden for the Administrator group. AdminPermissions::grant()
 * now includes them and is idempotent.
 */
return new class implements MigrationInterface {
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
    }
};
