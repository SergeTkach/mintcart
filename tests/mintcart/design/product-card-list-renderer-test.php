<?php
/**
 * Standalone smoke test for MintCart\Design\ActiveTheme and
 * MintCart\Design\ProductCardListRenderer.
 *
 * No PHPUnit/Composer test runner in the repo yet, same as every other
 * MintCart smoke test. ActiveTheme is pure-function and needs no fixtures.
 * ProductCardListRenderer's success path (a real rendered product-card)
 * is NOT exercised here for the same reason component-renderer-test.php's
 * scenario 8+ can't: this environment has no `vendor/twig/twig` installed,
 * so ComponentRenderer::render() cannot actually run Twig. What IS fully
 * testable without Twig: ComponentRenderer::resolve() throwing when a
 * component exists nowhere in the chain (verified by
 * component-renderer-test.php scenario 5) happens *before* render() ever
 * touches Twig - so pointing ProductCardListRenderer at a themes root with
 * no product-card.twig anywhere exercises its real catch/disable/degrade
 * behaviour end-to-end, the same failure path
 * ControllerProductCategory/ControllerProductSearch rely on in production.
 *
 * Run: php tests/mintcart/design/product-card-list-renderer-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/design/activetheme.php';
require __DIR__ . '/../../../upload/system/library/mintcart/design/componentrenderer.php';
require __DIR__ . '/../../../upload/system/library/mintcart/design/productcardlistrenderer.php';
require __DIR__ . '/../../../upload/system/library/mintcart/commerce/productviewprovider.php';

use MintCart\Design\ActiveTheme;
use MintCart\Design\ComponentRenderer;
use MintCart\Design\ProductCardListRenderer;

function buildEmptyThemeFixture(): string
{
    $root = sys_get_temp_dir() . '/mintcart-product-card-list-renderer-test-' . bin2hex(random_bytes(8));

    // A Mint-aware theme.json but deliberately no product-card.twig anywhere
    // in the chain (not even mint_default) - ComponentRenderer::resolve()
    // must throw, which is exactly the failure this renderer is meant to
    // degrade gracefully from.
    mkdir($root . '/some_theme/template/mint/components', 0777, true);

    file_put_contents($root . '/some_theme/theme.json', json_encode([
        'name' => 'some_theme',
        'code' => 'some_theme',
        'version' => '1.0.0',
        'mint_api' => '^1.0',
        'parent' => null,
        'presets' => [],
    ]));

    return $root;
}

function removeDirectory_(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    foreach (scandir($path) as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $itemPath = $path . '/' . $item;

        is_dir($itemPath) ? removeDirectory_($itemPath) : unlink($itemPath);
    }

    rmdir($path);
}

/** @var string[] $failures */
$failures = [];

function assertSame__($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

// --- MintCart\Design\ActiveTheme -------------------------------------------

// 1. 'default' config_theme resolves to theme_default_directory.
assertSame__(
    'legacy_directory',
    ActiveTheme::directory('default', 'legacy_directory'),
    "Scenario 1: config_theme='default' resolves to theme_default_directory",
    $failures
);

// 2. A non-'default' config_theme passes through unchanged.
assertSame__(
    'mint_default',
    ActiveTheme::directory('mint_default', 'default'),
    'Scenario 2: a custom config_theme passes through unchanged',
    $failures
);

// 3. A null config_theme (unset setting) falls back the same as 'default'.
assertSame__(
    'legacy_directory',
    ActiveTheme::directory(null, 'legacy_directory'),
    'Scenario 3: null config_theme falls back to theme_default_directory',
    $failures
);

// 4. isMintAware() is true only when the resolved directory ships a theme.json.
$root = buildEmptyThemeFixture();

assertSame__(
    true,
    ActiveTheme::isMintAware($root, 'some_theme'),
    'Scenario 4: isMintAware() is true when theme.json exists',
    $failures
);

assertSame__(
    false,
    ActiveTheme::isMintAware($root, 'no_such_theme'),
    'Scenario 4: isMintAware() is false when the theme directory does not exist',
    $failures
);

// 5. isMintAware() also works against the real, shipped repo themes.
$realThemesRoot = __DIR__ . '/../../../upload/catalog/view/theme';

assertSame__(
    true,
    ActiveTheme::isMintAware($realThemesRoot, 'mint_default'),
    'Scenario 5: the real mint_default theme is Mint-aware',
    $failures
);

assertSame__(
    false,
    ActiveTheme::isMintAware($realThemesRoot, 'default'),
    "Scenario 5: the real legacy 'default' theme is not Mint-aware",
    $failures
);

// --- MintCart\Design\ProductCardListRenderer --------------------------------

// 6. A missing product-card.twig (nowhere in the chain) is caught, the
// renderer disables itself, onError is invoked once, and the caller gets
// null back rather than an uncaught exception.
$componentRenderer = new ComponentRenderer($root, 'some_theme');
$listRenderer = new ProductCardListRenderer($componentRenderer);

$onErrorCalls = [];

$card = $listRenderer->render(
    ['id' => 42, 'name' => 'Widget', 'url' => 'index.php?route=product/product&product_id=42', 'image' => 'placeholder.png'],
    [],
    ['listing_type' => 'search', 'position' => 1],
    [],
    function (\Throwable $e, array $product) use (&$onErrorCalls): void {
        $onErrorCalls[] = $product['id'];
    }
);

assertSame__(null, $card, 'Scenario 6: a resolve() failure returns null, not an exception', $failures);
assertSame__(false, $listRenderer->isEnabled(), 'Scenario 6: the renderer disables itself after a failure', $failures);
assertSame__([42], $onErrorCalls, 'Scenario 6: onError is invoked exactly once with the failing product', $failures);

// 7. Once disabled, a second call for a different product is skipped
// entirely - onError is NOT called again (the whole list degrades once,
// it does not re-attempt and re-log per remaining product).
$card2 = $listRenderer->render(
    ['id' => 43, 'name' => 'Gadget', 'url' => 'index.php?route=product/product&product_id=43', 'image' => 'placeholder.png'],
    [],
    ['listing_type' => 'search', 'position' => 2],
    [],
    function (\Throwable $e, array $product) use (&$onErrorCalls): void {
        $onErrorCalls[] = $product['id'];
    }
);

assertSame__(null, $card2, 'Scenario 7: a disabled renderer returns null for the next product too', $failures);
assertSame__([42], $onErrorCalls, 'Scenario 7: onError is not invoked again once disabled', $failures);

// 8. A fresh renderer instance starts enabled (isEnabled() true) - a new
// request/list gets its own instance, past failures never leak across
// requests since nothing is static/shared here.
$freshListRenderer = new ProductCardListRenderer($componentRenderer);
assertSame__(true, $freshListRenderer->isEnabled(), 'Scenario 8: a fresh renderer instance starts enabled', $failures);

removeDirectory_($root);

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo 'PASS: ActiveTheme / ProductCardListRenderer smoke test (' . 8 . " scenarios)\n";
exit(0);
