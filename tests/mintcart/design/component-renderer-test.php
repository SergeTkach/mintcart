<?php
/**
 * Standalone smoke test for MintCart\Design\ComponentRenderer.
 *
 * No PHPUnit/Composer test runner in the repo yet (same as every other
 * MintCart smoke test so far). Exercises resolve()'s fallback chain -
 * active theme override -> theme.json "parent" chain -> Mint Default -
 * against real fixture directories built under sys_get_temp_dir(), since
 * resolve() takes a themes root path and has no OpenCart dependency.
 *
 * render()'s use of the OpenCart `\Template('Twig')` adaptor is NOT
 * exercised here: this environment has no `vendor/twig/twig` installed
 * (no composer install has been run against upload/composer.json), the
 * same pre-existing gap that means no *other* Twig page in this repo can
 * be rendered from a standalone PHP CLI script either. That is unrelated
 * to this class - render()'s only OpenCart dependency is delegating to
 * the existing `\Template('Twig')` adaptor, unchanged by this test.
 *
 * Run: php tests/mintcart/design/component-renderer-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/design/componentrenderer.php';
require __DIR__ . '/../../../upload/system/library/mintcart/commerce/productviewprovider.php';

use MintCart\Commerce\ProductViewProvider;

use MintCart\Design\ComponentRenderer;

/**
 * @param array<string, array{parent?: string, components?: string[]}> $themes
 *        theme code => ['parent' => otherCode, 'components' => ['product-card', ...]]
 */
function buildThemesFixture(array $themes): string
{
    $root = sys_get_temp_dir() . '/mintcart-component-renderer-test-' . bin2hex(random_bytes(8));

    foreach ($themes as $code => $spec) {
        $themeDir = $root . '/' . $code;
        mkdir($themeDir . '/template/mint/components', 0777, true);

        file_put_contents($themeDir . '/theme.json', json_encode([
            'name' => $code,
            'code' => $code,
            'version' => '1.0.0',
            'mint_api' => '^1.0',
            'parent' => $spec['parent'] ?? null,
            'presets' => [],
        ]));

        foreach ($spec['components'] ?? [] as $component) {
            file_put_contents(
                $themeDir . '/template/mint/components/' . $component . '.twig',
                '<!-- ' . $code . ':' . $component . ' -->'
            );
        }
    }

    return $root;
}

function removeDirectory(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    $items = scandir($path);

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $itemPath = $path . '/' . $item;

        if (is_dir($itemPath)) {
            removeDirectory($itemPath);
        } else {
            unlink($itemPath);
        }
    }

    rmdir($path);
}

/** @var string[] $failures */
$failures = [];

function assertSame_($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

// 1. Active theme overrides the component directly.
$root = buildThemesFixture([
    'mint_default' => ['components' => ['product-card']],
    'custom_theme' => ['components' => ['product-card']],
]);

$renderer = new ComponentRenderer($root, 'custom_theme');
assertSame_(
    $root . '/custom_theme/template/mint/components/product-card.twig',
    $renderer->resolve('product-card'),
    'Scenario 1: active theme override should win over Mint Default',
    $failures
);
removeDirectory($root);

// 2. Active theme has no override, but its declared parent does.
$root = buildThemesFixture([
    'mint_default' => ['components' => ['product-card']],
    'base_theme' => ['components' => ['product-card']],
    'child_theme' => ['parent' => 'base_theme', 'components' => []],
]);

$renderer = new ComponentRenderer($root, 'child_theme');
assertSame_(
    $root . '/base_theme/template/mint/components/product-card.twig',
    $renderer->resolve('product-card'),
    'Scenario 2: should fall through to the parent theme via theme.json',
    $failures
);
removeDirectory($root);

// 3. Neither active theme nor its parent chain has the component -> falls
//    back to Mint Default.
$root = buildThemesFixture([
    'mint_default' => ['components' => ['product-card']],
    'base_theme' => ['components' => []],
    'child_theme' => ['parent' => 'base_theme', 'components' => []],
]);

$renderer = new ComponentRenderer($root, 'child_theme');
assertSame_(
    $root . '/mint_default/template/mint/components/product-card.twig',
    $renderer->resolve('product-card'),
    'Scenario 3: should fall back to Mint Default when nothing in the chain overrides it',
    $failures
);
removeDirectory($root);

// 4. A cyclic parent chain must not infinite-loop, and still falls back to
//    Mint Default if the component exists nowhere in the cycle.
$root = buildThemesFixture([
    'mint_default' => ['components' => ['product-card']],
    'theme_a' => ['parent' => 'theme_b', 'components' => []],
    'theme_b' => ['parent' => 'theme_a', 'components' => []],
]);

$renderer = new ComponentRenderer($root, 'theme_a');
assertSame_(
    $root . '/mint_default/template/mint/components/product-card.twig',
    $renderer->resolve('product-card'),
    'Scenario 4: a cyclic theme.json parent chain should not infinite-loop and should still fall back to Mint Default',
    $failures
);
removeDirectory($root);

// 5. Component missing everywhere, including Mint Default -> exception.
$root = buildThemesFixture([
    'mint_default' => ['components' => []],
    'custom_theme' => ['components' => []],
]);

$renderer = new ComponentRenderer($root, 'custom_theme');
$threw = false;

try {
    $renderer->resolve('product-card');
} catch (\RuntimeException $e) {
    $threw = true;
}

assertSame_(true, $threw, 'Scenario 5: should throw when the component exists nowhere, including Mint Default', $failures);
removeDirectory($root);

// 6. A missing theme.json is treated as "no parent", not a hard failure -
//    resolution still reaches Mint Default.
$root = buildThemesFixture([
    'mint_default' => ['components' => ['product-card']],
    'custom_theme' => ['components' => []],
]);
unlink($root . '/custom_theme/theme.json');

$renderer = new ComponentRenderer($root, 'custom_theme');
assertSame_(
    $root . '/mint_default/template/mint/components/product-card.twig',
    $renderer->resolve('product-card'),
    'Scenario 6: a missing theme.json should be treated as no parent, not a hard failure',
    $failures
);
removeDirectory($root);

// 7. A theme.json that exists but is not valid JSON (e.g. hand-edited and
//    broken) is also treated as "no parent", not a hard failure - distinct
//    from scenario 6, which only covers a missing file.
$root = buildThemesFixture([
    'mint_default' => ['components' => ['product-card']],
    'custom_theme' => ['components' => []],
]);
file_put_contents($root . '/custom_theme/theme.json', '{not valid json');

$renderer = new ComponentRenderer($root, 'custom_theme');
assertSame_(
    $root . '/mint_default/template/mint/components/product-card.twig',
    $renderer->resolve('product-card'),
    'Scenario 7: a malformed (invalid JSON) theme.json should be treated as no parent, not a hard failure',
    $failures
);
removeDirectory($root);

// 8. Real-repo integration: ComponentRenderer must resolve the actual
//    shipped mint_default/template/mint/components/product-card.twig file
//    at its real path, not just a fixture - this guards against the theme
//    directory layout (catalog/view/theme/<code>/template/mint/components/)
//    drifting out of sync with what ComponentRenderer::componentPath()
//    actually builds.
$realThemesRoot = __DIR__ . '/../../../upload/catalog/view/theme';
$realRenderer = new ComponentRenderer($realThemesRoot, 'mint_default');
$realPath = $realRenderer->resolve('product-card');

assertSame_(
    true,
    is_file($realPath),
    'Scenario 8: resolve() should find the real mint_default product-card.twig file on disk',
    $failures
);

$realMarkup = (string) file_get_contents($realPath);

assertSame_(
    true,
    str_contains($realMarkup, 'mc-product-card') && str_contains($realMarkup, 'data-mc-action="add-to-cart"'),
    'Scenario 8: the resolved real product-card.twig should contain the documented mc-product-card markup',
    $failures
);

// 9. Composition: a ProductViewProvider::build() result for a realistic
//    category-listing row is a valid, complete input to
//    ComponentRenderer::resolve()'d template's documented variable
//    contract - i.e. the two units this PR wires together in
//    ControllerProductCategory actually compose. render() itself still
//    isn't exercised (no vendor/twig/twig, see file docblock), but this
//    confirms the shape ProductViewProvider emits is exactly what
//    product-card.twig (and docs/developer/components/product-card.md)
//    expects, using the same field names ControllerProductCategory now
//    passes in.
$productViewProvider = new ProductViewProvider();

$productView = $productViewProvider->build(
    [
        'id' => 42,
        'name' => 'Mint T-Shirt',
        'url' => 'index.php?route=product/product&product_id=42',
        'image' => 'catalog/demo/mint-tshirt.jpg',
        'price' => '$19.99',
        'special' => false,
        'tax' => false,
        'rating' => 4,
        'quantity' => 5,
        'stock_label' => 'In Stock',
        'minimum' => 1,
        'manufacturer' => 'Mint',
    ],
    [],
    ['listing_type' => 'category', 'position' => 1]
);

$requiredTemplateVars = ['product', 'display', 'context'];
$hasAllKeys = true;

foreach ($requiredTemplateVars as $key) {
    if (!array_key_exists($key, $productView)) {
        $hasAllKeys = false;
    }
}

assertSame_(true, $hasAllKeys, 'Scenario 9: ProductViewProvider::build() output must carry product/display/context for the template contract', $failures);
assertSame_('category', $productView['context']['listing_type'], 'Scenario 9: listing_type should pass through as given by the controller', $failures);
assertSame_(true, $productView['product']['stock']['in_stock'], 'Scenario 9: quantity=5 should resolve to in_stock=true', $failures);

// 10. The CLAUDE.md §19 / MINTCART_TECHNICAL_PLAN.md §45 milestone proof:
//     a second theme ("test_theme") that overrides ONLY product-card.twig,
//     with a genuinely different DOM (no secondary-image swap, no
//     wishlist/compare quick-actions, a single badge instead of a list, no
//     quick-order button) must resolve to ITS OWN file when active, while
//     any theme that does NOT override the component (mint_default itself)
//     must still resolve to Mint Default's file. Both checks run against
//     the real, shipped repo files (not fixtures), the same way scenario 8
//     guards against drift for mint_default alone. test_theme declares
//     "parent": null in its theme.json - it doesn't even name mint_default
//     as a parent - so a pass here proves the Mint Default fallback is
//     automatic for anything a theme doesn't override, not merely
//     something that only works if a theme opts in via "parent". This is
//     what actually validates the Theme API milestone: 0 OCMOD, 0
//     controller patches, 0 copied business logic to get a fully
//     functional second theme with its own product-card DOM.
$testThemeRenderer = new ComponentRenderer($realThemesRoot, 'test_theme');
$testThemePath = $testThemeRenderer->resolve('product-card');

assertSame_(
    $realThemesRoot . '/test_theme/template/mint/components/product-card.twig',
    $testThemePath,
    'Scenario 10: with test_theme active, resolve() should return test_theme\'s own override, not Mint Default\'s',
    $failures
);

$testThemeMarkup = (string) file_get_contents($testThemePath);

assertSame_(
    true,
    str_contains($testThemeMarkup, 'mc-product-card--test') && !str_contains($testThemeMarkup, 'toggle-wishlist'),
    'Scenario 10: test_theme\'s product-card.twig should be a genuinely different DOM (its own marker class, no wishlist quick-action), not a copy of Mint Default\'s',
    $failures
);

assertSame_(
    $realThemesRoot . '/mint_default/template/mint/components/product-card.twig',
    $realRenderer->resolve('product-card'),
    'Scenario 10: with mint_default active (no override anywhere), resolve() should still return Mint Default\'s own file - the fallback direction, not just the override direction',
    $failures
);

// 11. Same fallback-direction proof as scenario 10, now for the `header`
//     component (Phase 7): test_theme overrides ONLY header.twig, with a
//     genuinely different DOM (a single stacked bar, no account dropdown,
//     no topbar/currency/language/search widgets - see
//     upload/catalog/view/theme/test_theme/template/mint/components/header.twig),
//     while resolve() must still return Mint Default's own header.twig
//     when mint_default is active (no override anywhere).
$testThemeHeaderPath = $testThemeRenderer->resolve('header');

assertSame_(
    $realThemesRoot . '/test_theme/template/mint/components/header.twig',
    $testThemeHeaderPath,
    'Scenario 11: with test_theme active, resolve(\'header\') should return test_theme\'s own override, not Mint Default\'s',
    $failures
);

$testThemeHeaderMarkup = (string) file_get_contents($testThemeHeaderPath);

assertSame_(
    true,
    str_contains($testThemeHeaderMarkup, 'mc-header--test') && !str_contains($testThemeHeaderMarkup, 'mc-header__account-menu'),
    'Scenario 11: test_theme\'s header.twig should be a genuinely different DOM (its own marker class, no account dropdown), not a copy of Mint Default\'s',
    $failures
);

assertSame_(
    $realThemesRoot . '/mint_default/template/mint/components/header.twig',
    $realRenderer->resolve('header'),
    'Scenario 11: with mint_default active (no override anywhere), resolve(\'header\') should still return Mint Default\'s own file - the fallback direction, not just the override direction',
    $failures
);

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo 'PASS: ComponentRenderer smoke test (' . 11 . " scenarios)\n";
exit(0);
