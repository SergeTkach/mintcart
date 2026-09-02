<?php

/**
 * Standalone smoke test for MintCart\Design\ManufacturerPickerProvider.
 *
 * No PHPUnit/Composer in the repo yet, same as every other tests/mintcart
 * file. ManufacturerPickerProvider has no OpenCart dependency by design,
 * so this runs entirely against plain arrays shaped like the controller's
 * already-augmented rows (ModelCatalogManufacturer::getManufacturers()'s
 * real return rows, plus the `image_url` key ControllerMintcartEntity
 * resolves before calling build()).
 *
 * Run: php tests/mintcart/design/manufacturer-picker-provider-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/design/manufacturerpickerprovider.php';

use MintCart\Design\ManufacturerPickerProvider;

$failures = [];

function assertSame($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

$provider = new ManufacturerPickerProvider();

// 1. Empty result set.
$result = $provider->build([], 1, 20);
assertSame([], $result['items'], 'empty: items is an empty array', $failures);
assertSame(0, $result['total'], 'empty: total is 0', $failures);

// 2. Fewer rows than one page - everything comes back, total matches row
//    count. Unlike CategoryPickerProvider, image is a real resolved URL
//    when the controller found one.
$rows = [
    ['manufacturer_id' => '12', 'name' => 'Apple', 'image_url' => 'image/cache/catalog/apple-100x100.png'],
    ['manufacturer_id' => '9', 'name' => 'Canon', 'image_url' => null],
];
$result = $provider->build($rows, 1, 20);
assertSame(2, $result['total'], 'one page: total counts every filtered row', $failures);
assertSame(2, count($result['items']), 'one page: both rows returned', $failures);
assertSame(12, $result['items'][0]['id'], 'one page: manufacturer_id cast to int and renamed to id', $failures);
assertSame('Apple', $result['items'][0]['name'], 'one page: name passed through', $failures);
assertSame('image/cache/catalog/apple-100x100.png', $result['items'][0]['image'], 'one page: a resolved image_url passes through as image', $failures);
assertSame(null, $result['items'][1]['image'], 'one page: a manufacturer with no image resolves to null, not a missing key', $failures);

// 3. A row missing the image_url key entirely (defensive - the controller
//    always sets it today, but build() shouldn't warn/fatal if it didn't).
$rows = [['manufacturer_id' => 1, 'name' => 'No Image Key Set']];
$result = $provider->build($rows, 1, 20);
assertSame(null, $result['items'][0]['image'], 'missing key: image defaults to null when image_url is absent', $failures);

// 4. Paging: page 2 of a 5-row set with limit 2 returns rows 3-4, total
//    still 5.
$rows = [
    ['manufacturer_id' => 1, 'name' => 'A', 'image_url' => null],
    ['manufacturer_id' => 2, 'name' => 'B', 'image_url' => null],
    ['manufacturer_id' => 3, 'name' => 'C', 'image_url' => null],
    ['manufacturer_id' => 4, 'name' => 'D', 'image_url' => null],
    ['manufacturer_id' => 5, 'name' => 'E', 'image_url' => null],
];
$result = $provider->build($rows, 2, 2);
assertSame(5, $result['total'], 'paging: total reflects the full filtered set, not just this page', $failures);
assertSame(2, count($result['items']), 'paging: page 2 returns 2 items', $failures);
assertSame('C', $result['items'][0]['name'], 'paging: page 2 starts at the 3rd row', $failures);
assertSame('D', $result['items'][1]['name'], 'paging: page 2 ends at the 4th row', $failures);

// 5. Last (partial) page.
$result = $provider->build($rows, 3, 2);
assertSame(1, count($result['items']), 'last page: partial page returns only the remaining row', $failures);
assertSame('E', $result['items'][0]['name'], 'last page: correct row', $failures);

// 6. Name is HTML-decoded and stripped, same normalization
//    CategoryPickerProvider applies (defensive - manufacturer names are
//    plain text in practice, but a stored name could still contain
//    entities from a copy-paste).
$rows = [['manufacturer_id' => 40, 'name' => 'Bath &amp; Body', 'image_url' => null]];
$result = $provider->build($rows, 1, 20);
assertSame('Bath & Body', $result['items'][0]['name'], 'decode: &amp; entity decoded to plain text', $failures);

// 7. page/limit below 1 are clamped rather than producing a negative
//    array_slice offset or a division-by-zero on the JS side's
//    Math.ceil(total / pageSize).
$rows = [
    ['manufacturer_id' => 1, 'name' => 'A', 'image_url' => null],
    ['manufacturer_id' => 2, 'name' => 'B', 'image_url' => null],
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

echo 'PASS: ManufacturerPickerProvider smoke test (7 scenarios)' . "\n";
exit(0);
