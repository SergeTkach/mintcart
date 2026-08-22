<?php
/**
 * Standalone smoke test for MintCart\Commerce\ProductViewProvider.
 *
 * No PHPUnit/Composer in the repo yet, same as the Phase 1 migration
 * manager test. ProductViewProvider has no OpenCart dependency by design,
 * so this runs entirely against plain arrays.
 *
 * Run: php tests/mintcart/commerce/product-view-provider-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/commerce/productviewprovider.php';

use MintCart\Commerce\ProductViewProvider;

$failures = [];

function assertTrue(bool $condition, string $message, array &$failures): void
{
    if (!$condition) {
        $failures[] = $message;
    }
}

function assertSame($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

$provider = new ProductViewProvider();

// 1. Minimal required fields, everything else defaulted.
$view = $provider->build([
    'id' => 42,
    'name' => 'Apple Cinema 30"',
    'url' => 'index.php?route=product/product&product_id=42',
    'image' => 'catalog/apple_cinema_30.jpg',
]);

assertSame(42, $view['product']['id'], 'minimal: id passes through', $failures);
assertSame('Apple Cinema 30"', $view['product']['image_alt'], 'minimal: image_alt defaults to name', $failures);
assertSame(false, $view['product']['price'], 'minimal: price defaults to false', $failures);
assertSame([], $view['product']['badges'], 'minimal: no badges without special/stock/new data', $failures);
assertTrue($view['product']['stock']['in_stock'], 'minimal: in_stock defaults true when quantity is unknown', $failures);
assertSame(1, $view['product']['minimum'], 'minimal: minimum defaults to 1', $failures);
assertSame('category', $view['context']['listing_type'], 'minimal: context.listing_type defaults to category', $failures);
assertTrue($view['product']['actions']['wishlist'], 'minimal: wishlist action always available', $failures);
assertSame(null, $view['product']['options_summary'], 'minimal: options_summary defaults to null', $failures);

// 2. Required key missing throws.
try {
    $provider->build(['name' => 'No id', 'url' => '#', 'image' => 'x.jpg']);
    $failures[] = 'missing id: expected InvalidArgumentException, none thrown';
} catch (\InvalidArgumentException $e) {
    // expected
}

// 3. Special price -> sale badge, purchasable.
$view = $provider->build([
    'id' => 1,
    'name' => 'Product One',
    'url' => '#',
    'image' => 'x.jpg',
    'price' => '$100.00',
    'special' => '$80.00',
    'quantity' => 5,
]);

assertSame([['type' => 'sale', 'label' => null]], $view['product']['badges'], 'special price: sale badge present', $failures);
assertTrue($view['product']['actions']['add_to_cart'], 'special price + in stock: add_to_cart true', $failures);

// 4. Out of stock -> out-of-stock badge, not purchasable, add_to_cart/quick_order false.
$view = $provider->build([
    'id' => 2,
    'name' => 'Product Two',
    'url' => '#',
    'image' => 'x.jpg',
    'price' => '$50.00',
    'quantity' => 0,
    'stock_label' => 'Out Of Stock',
]);

assertSame(
    [['type' => 'out-of-stock', 'label' => 'Out Of Stock']],
    $view['product']['badges'],
    'out of stock: out-of-stock badge present with resolved label',
    $failures
);
assertTrue(!$view['product']['stock']['in_stock'], 'out of stock: stock.in_stock is false', $failures);
assertTrue(!$view['product']['actions']['add_to_cart'], 'out of stock: add_to_cart is false', $failures);
assertTrue(!$view['product']['actions']['quick_order'], 'out of stock: quick_order is false', $failures);
assertTrue($view['product']['actions']['wishlist'], 'out of stock: wishlist stays available', $failures);

// 5. Hidden price (customer-group price restriction) -> not purchasable even in stock.
$view = $provider->build([
    'id' => 3,
    'name' => 'Product Three',
    'url' => '#',
    'image' => 'x.jpg',
    'price' => false,
    'quantity' => 10,
]);

assertTrue(!$view['product']['actions']['add_to_cart'], 'hidden price: add_to_cart is false even in stock', $failures);

// 6. is_new flag opts into the "new" badge; not derived automatically.
$view = $provider->build([
    'id' => 4,
    'name' => 'Product Four',
    'url' => '#',
    'image' => 'x.jpg',
    'is_new' => true,
]);

assertSame([['type' => 'new', 'label' => null]], $view['product']['badges'], 'is_new flag: new badge present', $failures);

// 7. display/context overrides pass through.
$view = $provider->build(
    ['id' => 5, 'name' => 'Product Five', 'url' => '#', 'image' => 'x.jpg'],
    ['show_description' => true, 'action_variant' => 'compact'],
    ['listing_type' => 'search', 'position' => 3]
);

assertTrue($view['display']['show_description'], 'display override: show_description true', $failures);
assertSame('compact', $view['display']['action_variant'], 'display override: action_variant compact', $failures);
assertSame('search', $view['context']['listing_type'], 'context override: listing_type search', $failures);
assertSame(3, $view['context']['position'], 'context override: position 3', $failures);

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo 'PASS: ProductViewProvider smoke test (' . 9 . " scenarios)\n";
exit(0);
