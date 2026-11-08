<?php

use MintCart\Core\Migration\MigrationInterface;

/**
 * Nova Poshta delivery (`shipping/mc_novaposhta`): the cities and branches the
 * checkout searches, a local copy refilled from the extension's admin screen
 * (a city search or a branch list straight from the API is slow and breaks
 * with the API). Extension-owned data, not store data: nothing else reads it.
 * `sync_id` marks the rows written by one refresh, so rows Nova Poshta no
 * longer lists are deleted once the refresh has finished.
 */
return new class implements MigrationInterface {
    public function up(object $db): void
    {
        $db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "mc_novaposhta_city` (
                `city_ref` CHAR(36) NOT NULL,
                `name` VARCHAR(128) NOT NULL,
                `name_ru` VARCHAR(128) NOT NULL DEFAULT '',
                `area` VARCHAR(128) NOT NULL DEFAULT '',
                `area_ru` VARCHAR(128) NOT NULL DEFAULT '',
                `sync_id` INT(11) NOT NULL DEFAULT 0,
                PRIMARY KEY (`city_ref`),
                KEY `name` (`name`),
                KEY `name_ru` (`name_ru`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "mc_novaposhta_warehouse` (
                `point_ref` CHAR(36) NOT NULL,
                `city_ref` CHAR(36) NOT NULL,
                `number` INT(11) NOT NULL DEFAULT 0,
                `postomat` TINYINT(1) NOT NULL DEFAULT 0,
                `description` VARCHAR(255) NOT NULL,
                `description_ru` VARCHAR(255) NOT NULL DEFAULT '',
                `sync_id` INT(11) NOT NULL DEFAULT 0,
                PRIMARY KEY (`point_ref`),
                KEY `city_ref` (`city_ref`, `postomat`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
    }
};
