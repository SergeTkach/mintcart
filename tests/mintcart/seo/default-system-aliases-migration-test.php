<?php
/**
 * Test for migration 202610221300_default_system_url_aliases.php against an
 * in-memory \DB double: aliases are added per language, a route that already
 * has an alias and a keyword that is already taken are skipped.
 *
 * Run: php tests/mintcart/seo/default-system-aliases-migration-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

define('DB_PREFIX', 'oc_');

require __DIR__ . '/../../../upload/system/library/mintcart/core/migration/migrationinterface.php';

$migration = require __DIR__ . '/../../../upload/system/mintcart/migrations/202610221300_default_system_url_aliases.php';

final class FakeDb
{
    /** @var string[] */
    public array $inserts = [];

    public function query(string $sql): object
    {
        $sql = trim(preg_replace('/\s+/', ' ', $sql));

        if (str_contains($sql, 'FROM `oc_language`')) {
            return (object) ['rows' => [['language_id' => '1'], ['language_id' => '2']]];
        }

        if (str_starts_with($sql, 'SELECT `query`, `keyword` FROM `oc_seo_url`')) {
            // language 1 already has a custom alias for the cart; language 2 already uses "search" as a keyword
            if (str_contains($sql, "`language_id` = '1'")) {
                return (object) ['rows' => [['query' => 'checkout/cart', 'keyword' => 'basket']]];
            }

            return (object) ['rows' => [['query' => 'information/information&information_id=4', 'keyword' => 'search']]];
        }

        if (str_starts_with($sql, 'INSERT INTO `oc_seo_url`')) {
            $this->inserts[] = $sql;
        }

        return (object) ['rows' => []];
    }

    public function escape(string $value): string
    {
        return addslashes($value);
    }
}

$failures = [];

function check($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

$db = new FakeDb();
$migration->up($db);

$has = static function (string $language, string $route, string $alias) use ($db): bool {
    foreach ($db->inserts as $sql) {
        if (str_contains($sql, "`language_id` = '$language'") && str_contains($sql, "`query` = '$route'") && str_contains($sql, "`keyword` = '$alias'")) {
            return true;
        }
    }

    return false;
};

check(true, $has('1', 'product/search', 'search'), 'language 1: search alias added', $failures);
check(true, $has('1', 'checkout/checkout', 'checkout'), 'language 1: checkout alias added', $failures);
check(false, $has('1', 'checkout/cart', 'cart'), 'language 1: cart skipped (the route already has an alias)', $failures);
check(true, $has('2', 'checkout/cart', 'cart'), 'language 2: cart alias added', $failures);
check(false, $has('2', 'product/search', 'search'), 'language 2: "search" keyword is taken, skipped', $failures);
check(true, $has('2', 'product/manufacturer', 'brands'), 'manufacturer listing gets "brands"', $failures);
check((12 - 1) + (12 - 1), count($db->inserts), 'one insert per route and language, minus the two skipped', $failures);

if ($failures) {
    fwrite(STDERR, "FAIL\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "PASS: default system URL aliases migration (7 checks)\n";
