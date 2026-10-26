<?php

use MintCart\Core\Migration\MigrationInterface;

/**
 * Ukrposhta delivery (`shipping/mc_ukrposhta`): the cities and post offices
 * the checkout searches. Ukrposhta's address classifier has no city search,
 * so the extension keeps this copy, refilled region by region from its admin
 * screen. Extension-owned data, not store data: nothing else reads it.
 */
return new class implements MigrationInterface {
    public function up(object $db): void
    {
        $db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "mc_ukrposhta_city` (
                `city_ref` CHAR(32) NOT NULL,
                `region_id` INT(11) NOT NULL,
                `region_name` VARCHAR(128) NOT NULL DEFAULT '',
                `district` VARCHAR(128) NOT NULL DEFAULT '',
                `name` VARCHAR(128) NOT NULL,
                PRIMARY KEY (`city_ref`),
                KEY `name` (`name`),
                KEY `region_id` (`region_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "mc_ukrposhta_branch` (
                `post_code` VARCHAR(16) NOT NULL,
                `city_ref` CHAR(32) NOT NULL,
                `description` VARCHAR(255) NOT NULL,
                `phone` VARCHAR(64) NOT NULL DEFAULT '',
                PRIMARY KEY (`post_code`),
                KEY `city_ref` (`city_ref`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
    }
};
