<?php
/**
 * Standalone smoke test for MintCart\Blocks\ProductsBlockProvider.
 *
 * Pure normalization logic, no OpenCart/DB dependency - exercised directly
 * against build()'s input/output contract.
 *
 * Run: php tests/mintcart/blocks/products-block-provider-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/blocks/productsblockprovider.php';

use MintCart\Blocks\ProductsBlockProvider;

/** @var string[] $failures */
$failures = [];

function assertSame___($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

$provider = new ProductsBlockProvider();

// 1. Empty settings/content -> documented defaults (limit 8, latest first,
//    no category filter, blank title).
$result = $provider->build();

assertSame___('', $result['title'], 'Scenario 1: empty content should default title to an empty string', $failures);
assertSame___(null, $result['category_id'], 'Scenario 1: no category_id setting should default to null (no filter)', $failures);
assertSame___(8, $result['limit'], 'Scenario 1: no limit setting should default to 8', $failures);
assertSame___('p.date_added', $result['sort'], 'Scenario 1: no sort setting should default to p.date_added', $failures);
assertSame___('DESC', $result['order'], 'Scenario 1: no order setting should default to DESC', $failures);

// 2. Real settings/content pass through, normalized.
$result = $provider->build(
    ['category_id' => '42', 'limit' => '4', 'sort' => 'p.viewed', 'order' => 'asc'],
    ['title' => 'Bestsellers']
);

assertSame___('Bestsellers', $result['title'], 'Scenario 2: content title should pass through', $failures);
assertSame___(42, $result['category_id'], 'Scenario 2: a numeric-string category_id should normalize to int', $failures);
assertSame___(4, $result['limit'], 'Scenario 2: a numeric-string limit should normalize to int', $failures);
assertSame___('p.viewed', $result['sort'], 'Scenario 2: a real sort setting should pass through unchanged', $failures);
assertSame___('ASC', $result['order'], 'Scenario 2: a lowercase order setting should normalize to uppercase', $failures);

// 3. Invalid/out-of-range values fall back to defaults rather than
//    propagating garbage into the product query a controller builds from
//    this output (e.g. a negative limit, an unrecognized order direction).
$result = $provider->build(['category_id' => '0', 'limit' => '-5', 'order' => 'sideways']);

assertSame___(null, $result['category_id'], 'Scenario 3: category_id=0 should normalize to null (no filter), not (int)0 passed through', $failures);
assertSame___(8, $result['limit'], 'Scenario 3: a non-positive limit should fall back to the default (8)', $failures);
assertSame___('DESC', $result['order'], 'Scenario 3: an unrecognized order value should fall back to the default (DESC)', $failures);

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo "PASS: ProductsBlockProvider smoke test (3 scenarios)\n";
exit(0);
