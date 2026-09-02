<?php
/**
 * Standalone smoke test for MintCart\Blocks\CategoriesBlockProvider.
 *
 * Pure normalization logic, no OpenCart/DB dependency - exercised directly
 * against build()'s input/output contract.
 *
 * Run: php tests/mintcart/blocks/categories-block-provider-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/blocks/categoriesblockprovider.php';

use MintCart\Blocks\CategoriesBlockProvider;

/** @var string[] $failures */
$failures = [];

function assertSame___($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

$provider = new CategoriesBlockProvider();

// 1. Empty settings/content -> documented defaults (limit 6, top-level
//    categories, blank title).
$result = $provider->build();

assertSame___('', $result['title'], 'Scenario 1: empty content should default title to an empty string', $failures);
assertSame___(0, $result['parent_id'], 'Scenario 1: no parent_id setting should default to 0 (top-level)', $failures);
assertSame___(6, $result['limit'], 'Scenario 1: no limit setting should default to 6', $failures);

// 2. Real settings/content pass through, normalized.
$result = $provider->build(
    ['parent_id' => '25', 'limit' => '4'],
    ['title' => 'Shop by Category']
);

assertSame___('Shop by Category', $result['title'], 'Scenario 2: content title should pass through', $failures);
assertSame___(25, $result['parent_id'], 'Scenario 2: a numeric-string parent_id should normalize to int', $failures);
assertSame___(4, $result['limit'], 'Scenario 2: a numeric-string limit should normalize to int', $failures);

// 3. Invalid/out-of-range values fall back to defaults rather than
//    propagating garbage into the query a controller builds from this
//    output (e.g. a negative limit, a negative parent_id).
$result = $provider->build(['parent_id' => '-3', 'limit' => '-5']);

assertSame___(0, $result['parent_id'], 'Scenario 3: a negative parent_id should normalize to 0 (top-level), not propagate negative', $failures);
assertSame___(6, $result['limit'], 'Scenario 3: a non-positive limit should fall back to the default (6)', $failures);

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo "PASS: CategoriesBlockProvider smoke test (3 scenarios)\n";
exit(0);
