<?php

use MintCart\Core\Migration\MigrationInterface;

/**
 * Setup Wizard autostart: opening the admin dashboard goes to the wizard
 * until it is finished or skipped (ControllerMintcartWizard::autostart()).
 * A regular OpenCart event - no controller patch.
 */
return new class implements MigrationInterface {
    public function up(object $db): void
    {
        $db->query("DELETE FROM `" . DB_PREFIX . "event` WHERE `code` = 'mintcart_wizard_autostart'");
        $db->query("INSERT INTO `" . DB_PREFIX . "event` SET `code` = 'mintcart_wizard_autostart', `trigger` = 'admin/controller/common/dashboard/before', `action` = 'mintcart/wizard/autostart', `status` = 1, `sort_order` = 0");
    }
};
