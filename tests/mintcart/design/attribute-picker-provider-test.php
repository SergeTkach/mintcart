<?php

/**
 * Standalone smoke test for MintCart\Design\AttributePickerProvider.
 *
 * No PHPUnit/Composer in the repo yet, same as every other tests/mintcart
 * file. AttributePickerProvider has no OpenCart dependency by design, so
 * this runs entirely against plain arrays shaped like
 * ModelCatalogAttribute::getAttributes()'s real return rows.
 *
 * Run: php tests/mintcart/design/attribute-picker-provider-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/design/attributepickerprovider.php';

use MintCart\Design\AttributePickerProvider;

$failures = [];

function assertSame($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

$provider = new AttributePickerProvider();

// 1. Empty result set.
$result = $provider->build([], 1, 20);
assertSame([], $result['items'], 'empty: items is an empty array', $failures);
assertSame(0, $result['total'], 'empty: total is 0', $failures);

// 2. Fewer rows than one page - everything comes back, total matches row
//    count, image is always null (attributes have no image concept).
$rows = [
    ['attribute_id' => '5', 'name' => 'Weight', 'attribute_group' => 'Physical'],
    ['attribute_id' => '2', 'name' => 'Material', 'attribute_group' => 'Physical'],
];
$result = $provider->build($rows, 1, 20);
assertSame(2, $result['total'], 'one page: total counts every filtered row', $failures);
assertSame(2, count($result['items']), 'one page: both rows returned', $failures);
assertSame(5, $result['items'][0]['id'], 'one page: attribute_id cast to int and renamed to id', $failures);
assertSame('Weight', $result['items'][0]['name'], 'one page: name passed through', $failures);
assertSame(null, $result['items'][0]['image'], 'one page: image is always null', $failures);

// 3. Paging: page 2 of a 5-row set with limit 2 returns rows 3-4, total
//    still 5.
$rows = [
    ['attribute_id' => 1, 'name' => 'A'],
    ['attribute_id' => 2, 'name' => 'B'],
    ['attribute_id' => 3, 'name' => 'C'],
    ['attribute_id' => 4, 'name' => 'D'],
    ['attribute_id' => 5, 'name' => 'E'],
];
$result = $provider->build($rows, 2, 2);
assertSame(5, $result['total'], 'paging: total reflects the full filtered set, not just this page', $failures);
assertSame(2, count($result['items']), 'paging: page 2 returns 2 items', $failures);
assertSame('C', $result['items'][0]['name'], 'paging: page 2 starts at the 3rd row', $failures);
assertSame('D', $result['items'][1]['name'], 'paging: page 2 ends at the 4th row', $failures);

// 4. Last (partial) page.
$result = $provider->build($rows, 3, 2);
assertSame(1, count($result['items']), 'last page: partial page returns only the remaining row', $failures);
assertSame('E', $result['items'][0]['name'], 'last page: correct row', $failures);

// 5. Name is HTML-decoded and stripped, same normalization
//    CategoryPickerProvider/ManufacturerPickerProvider apply (defensive -
//    attribute names are plain text in practice, but a stored name could
//    still contain entities from a copy-paste).
$rows = [['attribute_id' => 40, 'name' => 'Length &amp; Width']];
$result = $provider->build($rows, 1, 20);
assertSame('Length & Width', $result['items'][0]['name'], 'decode: &amp; entity decoded to plain text', $failures);

// 6. A row missing keys beyond id/name (defensive - the model always
//    returns more columns today, but build() only reads attribute_id/name).
$rows = [['attribute_id' => 1, 'name' => 'No Extra Columns']];
$result = $provider->build($rows, 1, 20);
assertSame('No Extra Columns', $result['items'][0]['name'], 'minimal row: build() only needs attribute_id/name', $failures);

// 7. page/limit below 1 are clamped rather than producing a negative
//    array_slice offset or a division-by-zero on the JS side's
//    Math.ceil(total / pageSize).
$rows = [
    ['attribute_id' => 1, 'name' => 'A'],
    ['attribute_id' => 2, 'name' => 'B'],
];
$result = $provider->build($rows, 0, 0);
assertSame(1, count($result['items']), 'clamping: page 0 and limit 0 are clamped to page 1 / limit 1', $failures);
assertSame('A', $result['items'][0]['name'], 'clamping: clamped page 1 starts at the first row', $failures);

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo 'PASS: AttributePickerProvider smoke test (7 scenarios)' . "\n";
exit(0);
