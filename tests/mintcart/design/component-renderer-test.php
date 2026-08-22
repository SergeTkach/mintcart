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

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo 'PASS: ComponentRenderer smoke test (' . 7 . " scenarios)\n";
exit(0);
