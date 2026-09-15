<?php

use MintCart\Core\Migration\MigrationInterface;

/**
 * Phase 15 (Commerce API) — Quick Order storage.
 *
 * MINTCART_FUNCTIONAL_SCOPE.md §13 / MINTCART_TECHNICAL_PLAN.md §28 describe
 * Quick Order as a lightweight "product + quantity + name + phone/email"
 * request, distinct from a full OpenCart order (billing/shipping address,
 * payment method, totals breakdown). Forcing that into `oc_order` with
 * placeholder address/payment data would corrupt every report and extension
 * that assumes an `oc_order` row is a real, fulfillable order — so this is a
 * genuinely Mint-specific entity (CLAUDE.md §6/§39: new Mint tables only for
 * functionality OpenCart's own schema has no room for), not a duplicate of
 * existing OpenCart storage.
 *
 * A request is a *lead* for staff to call back and turn into a real order
 * (or reject) — `status` tracks that follow-up, it is not an order status.
 * No FOREIGN KEY to `oc_product` (OpenCart 3's own schema uses none either,
 * cascade handled at the application layer) — `product_name` is a snapshot
 * so a request stays readable even if the product is later renamed/deleted.
 */
return new class implements MigrationInterface {
    public function up(object $db): void
    {
        $db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "mc_quick_order` (
                `quick_order_id` INT(11) NOT NULL AUTO_INCREMENT,
                `product_id` INT(11) NOT NULL,
                `product_name` VARCHAR(255) NOT NULL,
                `quantity` INT(4) NOT NULL DEFAULT '1',
                `name` VARCHAR(255) NOT NULL,
                `telephone` VARCHAR(64) NOT NULL DEFAULT '',
                `email` VARCHAR(255) NOT NULL DEFAULT '',
                `store_id` INT(11) NOT NULL DEFAULT '0',
                `language_id` INT(11) NOT NULL DEFAULT '0',
                `ip` VARCHAR(45) NOT NULL DEFAULT '',
                `status` VARCHAR(32) NOT NULL DEFAULT 'new',
                `date_added` DATETIME NOT NULL,
                PRIMARY KEY (`quick_order_id`),
                KEY `status` (`status`),
                KEY `date_added` (`date_added`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
    }
};
