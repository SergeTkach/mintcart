<?php

use MintCart\Core\AdminPermissions;
use MintCart\Core\Migration\MigrationInterface;

/**
 * Full admin user groups get access/modify on every MintCart admin screen
 * (admin/controller/mintcart/**) - see MintCart\Core\AdminPermissions.
 * Runs from the installer (DIR_OPENCART = store root) or from an admin
 * request (DIR_APPLICATION = admin/).
 */
return new class implements MigrationInterface {
    public function up(object $db): void
    {
        if (defined('DIR_OPENCART')) {
            $controllerDir = DIR_OPENCART . 'admin/controller/';
        } elseif (defined('DIR_APPLICATION')) {
            $controllerDir = DIR_APPLICATION . 'controller/';
        } else {
            return; // no OpenCart context (e.g. a standalone test run)
        }

        AdminPermissions::grant($db, DB_PREFIX, $controllerDir);
    }
};
