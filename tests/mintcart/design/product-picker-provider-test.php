<?php

/**
 * Standalone smoke test for MintCart\Design\ProductPickerProvider.
 *
 * No PHPUnit/Composer in the repo yet, same as every other tests/mintcart
 * file. ProductPickerProvider has no OpenCart dependency by design, so
 * this runs entirely against plain arrays shaped like the controller's
 * already-paged, already-augmented rows (ModelCatalogProduct::getProducts()'s
 * real return rows with `start`/`limit` applied, plus the `image_url` key
 * ControllerMintcartEntity resolves before calling build()) and the
 * separately-supplied filtered total from getTotalProducts().
 *
 * Unlike CategoryPickerProvider/ManufacturerPickerProvider, this provider
 * takes no $page/$limit - paging already happened in SQL, so build() only
 * normalizes rows and passes the caller-supplied total through.
 *
 * Run: php tests/mintcart/design/product-picker-provider-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/design/productpickerprovider.php';

use MintCart\Design\ProductPickerProvider;

$failures = [];

function assertSame($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

$provider = new ProductPickerProvider();

// 1. Empty result set.
$result = $provider->build([], 0);
assertSame([], $result['items'], 'empty: items is an empty array', $failures);
assertSame(0, $result['total'], 'empty: total is 0', $failures);

// 2. A page of rows, total larger than the page (unlike category/manufacturer,
//    total is caller-supplied, not count($rows) - this is the point of SQL-level
//    paging).
$rows = [
    ['product_id' => '42', 'name' => 'Apple Cinema 30"', 'image_url' => 'image/cache/catalog/apple-100x100.png'],
    ['product_id' => '28', 'name' => 'Canon EOS 5D', 'image_url' => null],
];
$result = $provider->build($rows, 137);
assertSame(137, $result['total'], 'paged: total is the caller-supplied filtered count, not count($rows)', $failures);
assertSame(2, count($result['items']), 'paged: both rows on this page returned', $failures);
assertSame(42, $result['items'][0]['id'], 'paged: product_id cast to int and renamed to id', $failures);
assertSame('Apple Cinema 30"', $result['items'][0]['name'], 'paged: name passed through', $failures);
assertSame('image/cache/catalog/apple-100x100.png', $result['items'][0]['image'], 'paged: a resolved image_url passes through as image', $failures);
assertSame(null, $result['items'][1]['image'], 'paged: a product with no image resolves to null, not a missing key', $failures);

// 3. A row missing the image_url key entirely (defensive - the controller
//    always sets it today, but build() shouldn't warn/fatal if it didn't).
$rows = [['product_id' => 1, 'name' => 'No Image Key Set']];
$result = $provider->build($rows, 1);
assertSame(null, $result['items'][0]['image'], 'missing key: image defaults to null when image_url is absent', $failures);

// 4. Name is HTML-decoded and stripped, same normalization
//    CategoryPickerProvider/ManufacturerPickerProvider apply.
$rows = [['product_id' => 40, 'name' => 'Salt &amp; Pepper Set', 'image_url' => null]];
$result = $provider->build($rows, 1);
assertSame('Salt & Pepper Set', $result['items'][0]['name'], 'decode: &amp; entity decoded to plain text', $failures);

// 5. A negative total (should never happen, but defensive against a
//    malformed caller) is clamped to 0 rather than passed through negative.
$result = $provider->build([], -5);
assertSame(0, $result['total'], 'clamping: a negative total is clamped to 0', $failures);

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo 'PASS: ProductPickerProvider smoke test (5 scenarios)' . "\n";
exit(0);
