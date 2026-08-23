<?php
/**
 * Standalone smoke test for MintCart\Design\ProductCardListRenderer.
 *
 * No PHPUnit/Composer test runner in the repo yet (same as every other
 * MintCart smoke test so far). Uses the real OpenCart `system/library/log.php`
 * (only OpenCart dependency: a defined, writable DIR_LOGS) so
 * MintCart\Core\Logger works standalone, same approach
 * component-renderer-test.php already uses for the real, shipped
 * mint_default/product-card.twig file instead of a fixture.
 *
 * ComponentRenderer::render()'s success path (an actually-resolved
 * product-card.twig rendered via OpenCart's `\Template('Twig')` adaptor) is
 * NOT exercised here - this environment has no `vendor/twig/twig` installed,
 * the same pre-existing gap noted in every earlier Phase 6/8 test. What IS
 * fully testable without Twig: ComponentRenderer::resolve() throws before
 * ever reaching the Twig adaptor when no component template exists anywhere
 * in the fallback chain - which is exactly the "degrade once, then stay
 * degraded" failure path this class exists to guarantee.
 *
 * Run: php tests/mintcart/design/product-card-list-renderer-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

define('DIR_LOGS', sys_get_temp_dir() . '/mintcart-product-card-list-renderer-test-logs-' . bin2hex(random_bytes(8)) . '/');
mkdir(DIR_LOGS, 0777, true);

require __DIR__ . '/../../../upload/system/library/log.php';
require __DIR__ . '/../../../upload/system/library/mintcart/core/logger.php';
require __DIR__ . '/../../../upload/system/library/mintcart/design/componentrenderer.php';
require __DIR__ . '/../../../upload/system/library/mintcart/commerce/productviewprovider.php';
require __DIR__ . '/../../../upload/system/library/mintcart/design/productcardlistrenderer.php';

use MintCart\Commerce\ProductViewProvider;
use MintCart\Core\Logger;
use MintCart\Design\ComponentRenderer;
use MintCart\Design\ProductCardListRenderer;

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

// An empty themes root - resolve('product-card') finds it nowhere, not even
// Mint Default, so ComponentRenderer::render() always throws \RuntimeException
// for every call against it. This is enough to exercise the whole
// degrade-and-stay-degraded contract without needing a working Twig runtime.
$emptyThemesRoot = sys_get_temp_dir() . '/mintcart-product-card-list-renderer-test-themes-' . bin2hex(random_bytes(8));
mkdir($emptyThemesRoot . '/some_theme', 0777, true);

$text = [
    'badge' => ['sale' => 'Sale', 'out-of-stock' => 'Out of Stock', 'new' => 'New'],
    'wishlist' => 'Add to Wish List',
    'compare' => 'Compare this Product',
    'rated' => 'Rated %s out of 5 stars',
    'tax' => 'Ex Tax:',
    'add_to_cart' => 'Add to Cart',
    'quick_order' => 'Quick Order',
];

$validProduct = [
    'id' => 42,
    'name' => 'Apple Cinema 30"',
    'url' => 'index.php?route=product/product&product_id=42',
    'image' => 'catalog/apple_cinema_30.jpg',
];

// 1. Missing component template: render() returns null, does not throw, and
//    the failure is logged rather than silently swallowed.
$renderer = new ProductCardListRenderer(
    new ComponentRenderer($emptyThemesRoot, 'some_theme'),
    new ProductViewProvider(),
    $text,
    new Logger('product-card-list-renderer-test.log')
);

assertTrue(!$renderer->hasFailed(), 'fresh instance: hasFailed() starts false', $failures);

$result = $renderer->render($validProduct);

assertSame(null, $result, 'missing template: render() returns null instead of throwing', $failures);
assertTrue($renderer->hasFailed(), 'missing template: hasFailed() becomes true after the first failure', $failures);
assertTrue(
    is_file(DIR_LOGS . 'product-card-list-renderer-test.log') && filesize(DIR_LOGS . 'product-card-list-renderer-test.log') > 0,
    'missing template: failure is logged',
    $failures
);

// 2. Once failed, render() short-circuits without even reaching
//    ProductViewProvider::build() - proven by passing data that would
//    otherwise throw \InvalidArgumentException (a missing required key),
//    which must NOT propagate now that the renderer has already failed.
try {
    $second = $renderer->render(['id' => 99]);
    assertSame(null, $second, 'already-failed: subsequent render() calls keep returning null', $failures);
} catch (\Throwable $e) {
    $failures[] = 'already-failed: render() must not throw once hasFailed() is true, got ' . get_class($e);
}

// 3. A fresh instance against the same broken theme fails independently -
//    the class holds no static/shared state across instances.
$secondRenderer = new ProductCardListRenderer(
    new ComponentRenderer($emptyThemesRoot, 'some_theme'),
    new ProductViewProvider(),
    $text,
    new Logger('product-card-list-renderer-test.log')
);

assertTrue(!$secondRenderer->hasFailed(), 'independent instance: does not inherit a previous instance\'s failed state', $failures);

// 4. ProductViewProvider validation errors (e.g. a missing required key) are
//    caught the same way a ComponentRenderer failure is - render() degrades
//    instead of letting the exception escape.
$thirdRenderer = new ProductCardListRenderer(
    new ComponentRenderer($emptyThemesRoot, 'some_theme'),
    new ProductViewProvider(),
    $text,
    new Logger('product-card-list-renderer-test.log')
);

$invalidResult = $thirdRenderer->render(['name' => 'No id', 'url' => '#', 'image' => 'x.jpg']);

assertSame(null, $invalidResult, 'invalid product data: render() degrades instead of throwing', $failures);
assertTrue($thirdRenderer->hasFailed(), 'invalid product data: hasFailed() becomes true', $failures);

if ($failures) {
    echo 'FAIL: ProductCardListRenderer smoke test (' . count($failures) . " failure(s))\n";

    foreach ($failures as $failure) {
        echo ' - ' . $failure . "\n";
    }

    exit(1);
}

echo 'PASS: ProductCardListRenderer smoke test (' . 4 . " scenarios)\n";
