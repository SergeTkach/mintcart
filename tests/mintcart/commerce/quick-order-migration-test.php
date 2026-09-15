<?php
/**
 * Standalone smoke test for the real Phase 15 (Commerce API) migration:
 * `202609041200_create_mc_quick_order.php`.
 *
 * Same approach as tests/mintcart/core/mc-block-migrations-test.php: runs
 * the actual migration file through MigrationManager against an in-memory
 * \DB double and asserts on the CREATE TABLE statement it produces, so a
 * schema typo/regression is caught without needing a real MySQL server
 * (still not available in this environment).
 *
 * Run: php tests/mintcart/commerce/quick-order-migration-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

define('DB_PREFIX', 'oc_');

require __DIR__ . '/../../../upload/system/library/mintcart/core/migration/migrationinterface.php';
require __DIR__ . '/../../../upload/system/library/mintcart/core/migration/migrationmanager.php';

use MintCart\Core\Migration\MigrationManager;

/**
 * In-memory double for OpenCart's \DB — records every query verbatim.
 * Same shape as mc-block-migrations-test.php's FakeDb.
 */
final class FakeDb
{
    /** @var string[] */
    public array $rowsMigration = [];

    /** @var string[] */
    public array $log = [];

    public function query(string $sql): object
    {
        $sql = trim($sql);
        $this->log[] = preg_replace('/\s+/', ' ', $sql);

        if (str_starts_with($sql, 'CREATE TABLE')) {
            return $this->result([]);
        }

        if (str_starts_with($sql, 'SELECT `migration`')) {
            $rows = array_map(fn ($m) => ['migration' => $m], $this->rowsMigration);

            return $this->result($rows);
        }

        if (str_starts_with($sql, 'INSERT INTO')) {
            preg_match("/VALUES \('(.+)', NOW\(\)\)/", $sql, $matches);
            $this->rowsMigration[] = stripslashes($matches[1]);

            return $this->result([]);
        }

        return $this->result([]);
    }

    public function escape(string $value): string
    {
        return addslashes($value);
    }

    private function result(array $rows): object
    {
        $result = new stdClass();
        $result->rows = $rows;
        $result->row = $rows[0] ?? [];
        $result->num_rows = count($rows);

        return $result;
    }
}

$failures = [];

function check(bool $condition, string $message, array &$failures): void
{
    if (!$condition) {
        $failures[] = $message;
    }
}

$db = new FakeDb();
$manager = new MigrationManager($db, DB_PREFIX, __DIR__ . '/../../../upload/system/mintcart/migrations');
$manager->run();

$createStatements = array_filter($db->log, fn ($q) => str_starts_with($q, 'CREATE TABLE'));

$mcQuickOrder = null;

foreach ($createStatements as $sql) {
    if (str_contains($sql, 'CREATE TABLE IF NOT EXISTS `oc_mc_quick_order`')) {
        $mcQuickOrder = $sql;
    }
}

check($mcQuickOrder !== null, 'mc_quick_order table should be created', $failures);

if ($mcQuickOrder !== null) {
    check(str_contains($mcQuickOrder, '`quick_order_id` INT(11) NOT NULL AUTO_INCREMENT'), 'mc_quick_order.quick_order_id should be an auto-increment PK', $failures);
    check(str_contains($mcQuickOrder, 'PRIMARY KEY (`quick_order_id`)'), 'mc_quick_order should have quick_order_id as its PK', $failures);
    check(str_contains($mcQuickOrder, '`product_id` INT(11) NOT NULL'), 'mc_quick_order should carry product_id', $failures);
    check(str_contains($mcQuickOrder, '`product_name` VARCHAR(255) NOT NULL'), 'mc_quick_order should snapshot product_name (product may later be renamed/deleted)', $failures);
    check(str_contains($mcQuickOrder, "`quantity` INT(4) NOT NULL DEFAULT '1'"), 'mc_quick_order.quantity should default to 1', $failures);
    check(str_contains($mcQuickOrder, '`name` VARCHAR(255) NOT NULL'), 'mc_quick_order should carry the requester name', $failures);
    check(str_contains($mcQuickOrder, '`telephone` VARCHAR(64)'), 'mc_quick_order should carry a telephone column', $failures);
    check(str_contains($mcQuickOrder, '`email` VARCHAR(255)'), 'mc_quick_order should carry an email column', $failures);
    check(str_contains($mcQuickOrder, "`status` VARCHAR(32) NOT NULL DEFAULT 'new'"), "mc_quick_order.status should default to 'new' (a lead, not an order status)", $failures);
    check(str_contains($mcQuickOrder, 'KEY `status` (`status`)'), 'mc_quick_order should index status (an admin requests list filters/sorts by it)', $failures);
    check(str_contains($mcQuickOrder, 'ENGINE=InnoDB'), 'mc_quick_order should use InnoDB (CLAUDE.md §12)', $failures);
    check(str_contains($mcQuickOrder, 'CHARSET=utf8mb4'), 'mc_quick_order should use utf8mb4 (CLAUDE.md §12)', $failures);
    check(!str_contains($mcQuickOrder, 'FOREIGN KEY'), 'mc_quick_order should not use FOREIGN KEY constraints, matching OpenCart 3\'s own schema convention', $failures);
}

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo "PASS: mc_quick_order migration smoke test\n";
exit(0);
