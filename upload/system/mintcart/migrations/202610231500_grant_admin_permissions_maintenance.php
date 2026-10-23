<?php

use MintCart\Core\AdminPermissions;
use MintCart\Core\Migration\MigrationInterface;

/**
 * System > Maintenance > Storage (admin/controller/mintcart/maintenance/storage.php)
 * shipped after the earlier permission grants; AdminPermissions::grant() is
 * idempotent, so running it again picks up the new screen.
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
