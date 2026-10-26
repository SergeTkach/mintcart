<?php

use MintCart\Core\AdminPermissions;
use MintCart\Core\Migration\MigrationInterface;

/**
 * Marketing → Badges (admin/controller/mintcart/badge.php) shipped after the
 * first permissions grant; AdminPermissions::grant() is idempotent, so
 * running it again picks up the new screen.
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
