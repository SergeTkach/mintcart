<?php
/**
 * Standalone smoke test for the real Phase 10 (Homepage Builder) migrations:
 * `202608231405_create_mc_pages.php` and `202608231406_create_mc_blocks.php`.
 *
 * Unlike migration-manager-test.php (which drives MigrationManager's logic
 * against disposable fixture migrations), this test runs the actual
 * migration files shipped in upload/system/mintcart/migrations/ through
 * MigrationManager and asserts on the CREATE TABLE statements they produce
 * — so a schema typo/regression here is caught without needing a real
 * MySQL server (still not available in this environment).
 *
 * Run: php tests/mintcart/core/mc-block-migrations-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

define('DB_PREFIX', 'oc_');

require __DIR__ . '/../../../upload/system/library/mintcart/core/migration/migrationinterface.php';
require __DIR__ . '/../../../upload/system/library/mintcart/core/migration/migrationmanager.php';

use MintCart\Core\Migration\MigrationManager;

/**
 * In-memory double for OpenCart's \DB. Records every query verbatim so this
 * test can assert on the exact CREATE TABLE statements the real migrations
 * emit, instead of re-implementing a SQL parser.
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

        if (str_starts_with($sql, 'INSERT INTO') && str_contains($sql, 'mc_migration')) {
            preg_match("/VALUES \('(.+)', NOW\(\)\)/", $sql, $matches);
            $this->rowsMigration[] = stripslashes($matches[1]);

            return $this->result([]);
        }

        // START TRANSACTION / COMMIT / ROLLBACK
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

$result = $manager->run();

// MigrationManager discovers every file in the real migrations directory,
// not just the two this test is named after — 202609041200_create_mc_quick_order.php
// (Phase 15) and 202609191200_create_mc_installed_file.php (Phase 11) are
// unrelated to Homepage Builder but live alongside these and are expected
// to apply too. Their own schemas are covered by
// tests/mintcart/commerce/quick-order-migration-test.php and
// tests/mintcart/update/installed-file-ledger-test.php, not here.
check(
    $result['applied'] === [
        '202608231405_create_mc_pages.php',
        '202608231406_create_mc_blocks.php',
        '202609041200_create_mc_quick_order.php',
        '202609191200_create_mc_installed_file.php',
        '202610081200_grant_mintcart_admin_permissions.php',
        '202610081201_register_wizard_autostart.php',
        '202610221200_create_mc_product_badge.php',
        '202610221201_grant_admin_permissions_badges.php',
        '202610221202_product_badge_definitions.php',
        '202610221300_default_system_url_aliases.php',
        '202610221301_alias_reviews_page.php',
        '202610231500_grant_admin_permissions_maintenance.php',
        '202610251000_create_ukrposhta_directory.php',
        '202610251200_grant_admin_permissions_homepage.php',
        '202610251300_grant_admin_permissions_seo_meta.php',
        '202610251400_seo_url_autofill.php',
    ],
    'all real migrations should apply in filename order, got: ' . json_encode($result['applied']),
    $failures
);

$createStatements = array_filter($db->log, fn ($q) => str_starts_with($q, 'CREATE TABLE'));

$findCreate = function (string $table) use ($createStatements): ?string {
    foreach ($createStatements as $sql) {
        if (str_contains($sql, "CREATE TABLE IF NOT EXISTS `oc_{$table}`")) {
            return $sql;
        }
    }

    return null;
};

$mcPage = $findCreate('mc_page');
$mcPageDescription = $findCreate('mc_page_description');
$mcBlock = $findCreate('mc_block');
$mcBlockDescription = $findCreate('mc_block_description');

check($mcPage !== null, 'mc_page table should be created', $failures);
check($mcPageDescription !== null, 'mc_page_description table should be created', $failures);
check($mcBlock !== null, 'mc_block table should be created', $failures);
check($mcBlockDescription !== null, 'mc_block_description table should be created', $failures);

if ($mcPage !== null) {
    check(str_contains($mcPage, '`page_id` INT(11) NOT NULL AUTO_INCREMENT'), 'mc_page.page_id should be an auto-increment PK', $failures);
    check(str_contains($mcPage, 'UNIQUE KEY `code` (`code`)'), 'mc_page.code should be unique (page lookup key)', $failures);
    check(str_contains($mcPage, 'ENGINE=InnoDB'), 'mc_page should use InnoDB (CLAUDE.md §12)', $failures);
    check(str_contains($mcPage, 'CHARSET=utf8mb4'), 'mc_page should use utf8mb4 (CLAUDE.md §12)', $failures);
}

if ($mcPageDescription !== null) {
    check(str_contains($mcPageDescription, 'PRIMARY KEY (`page_id`, `language_id`)'), 'mc_page_description should have a composite (page_id, language_id) PK, matching oc_category_description', $failures);
}

if ($mcBlock !== null) {
    check(str_contains($mcBlock, '`type` VARCHAR(64) NOT NULL'), 'mc_block.type should identify the block variant (Hero/Products/...)', $failures);
    check(str_contains($mcBlock, '`sort_order`'), 'mc_block should carry a sort_order for drag-and-drop ordering', $failures);
    check(str_contains($mcBlock, '`settings_json` LONGTEXT NOT NULL'), 'mc_block.settings_json should exist and not be the MySQL JSON column type (broad host compatibility)', $failures);
    check(str_contains($mcBlock, 'KEY `page_id` (`page_id`)'), 'mc_block should index page_id (a page\'s blocks are always looked up together)', $failures);
    check(str_contains($mcBlock, 'ENGINE=InnoDB'), 'mc_block should use InnoDB (CLAUDE.md §12)', $failures);
}

if ($mcBlockDescription !== null) {
    check(str_contains($mcBlockDescription, 'PRIMARY KEY (`block_id`, `language_id`)'), 'mc_block_description should have a composite (block_id, language_id) PK', $failures);
    check(str_contains($mcBlockDescription, '`content_json` LONGTEXT NOT NULL'), 'mc_block_description should carry localized content separate from mc_block.settings_json', $failures);
}

// Idempotency: a second run against the same tracking state must not
// attempt to recreate anything (MigrationManager's own responsibility, but
// worth pinning here too since it is what makes migrations replay-safe).
$result2 = $manager->run();
check($result2['applied'] === [], 'a second run must not re-apply already-applied migrations', $failures);

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo 'PASS: mc_page/mc_block migrations smoke test (' . count($createStatements) . " CREATE TABLE statements verified)\n";
exit(0);
