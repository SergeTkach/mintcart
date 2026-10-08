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
        $controllerDir = defined('DIR_OPENCART')
            ? DIR_OPENCART . 'admin/controller/'
            : DIR_APPLICATION . 'controller/';

        AdminPermissions::grant($db, DB_PREFIX, $controllerDir);
    }
};
