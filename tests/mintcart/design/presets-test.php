<?php
/**
 * Standalone smoke test for the Phase 9 preset CSS files
 * (docs/developer/theme-api/presets.md).
 *
 * No PHPUnit/Composer/CSS-parser dependency in the repo, same as every
 * other MintCart smoke test - this checks the preset source files as
 * plain text, which is enough to catch the two real ways a preset could
 * break the "tokens/settings only, never a bare :root" contract:
 * accidentally leaking into the global default, or silently drifting out
 * of index.css's import list / mint_default's theme.json manifest.
 *
 * Run: php tests/mintcart/design/presets-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

$failures = [];

function assertTrue(bool $condition, string $message, array &$failures): void
{
    if (!$condition) {
        $failures[] = $message;
    }
}

function assertContains(string $needle, string $haystack, string $message, array &$failures): void
{
    if (!str_contains($haystack, $needle)) {
        $failures[] = $message;
    }
}

function assertNotContains(string $needle, string $haystack, string $message, array &$failures): void
{
    if (str_contains($haystack, $needle)) {
        $failures[] = $message;
    }
}

$root = __DIR__ . '/../../..';

$warmCss = (string) file_get_contents($root . '/assets/src/css/presets/warm.css');
$cleanCss = (string) file_get_contents($root . '/assets/src/css/presets/clean.css');
$indexCss = (string) file_get_contents($root . '/assets/src/css/index.css');
$manifest = json_decode(
    (string) file_get_contents($root . '/upload/catalog/view/theme/mint_default/theme.json'),
    true
);

// 1. warm.css never redefines a bare :root - every rule must be scoped
// under [data-mc-preset="warm"], so the preset has zero effect unless
// that attribute is set on the page.
assertTrue(
    preg_match('/(?<!\])\s*:root\s*\{/', $warmCss) === 0,
    'presets/warm.css must not declare a bare :root block (only :root[data-mc-preset=...])',
    $failures
);
assertContains(
    "[data-mc-preset='warm']",
    $warmCss,
    'presets/warm.css must scope its overrides under [data-mc-preset="warm"]',
    $failures
);

// 2. clean.css is documentation-only by design (Clean == the unmodified
// baseline in settings/tokens.css) - it must not declare any --mc- custom
// property, or it would silently diverge from that baseline.
assertTrue(
    preg_match('/--mc-[a-z-]+\s*:/', $cleanCss) === 0,
    'presets/clean.css must not redefine any --mc-* token (Clean is the unmodified baseline)',
    $failures
);

// 3. State/functional colors carry meaning (stock status, form errors),
// not brand identity - a preset must never re-tune them.
foreach (['--mc-success', '--mc-danger', '--mc-warning', '--mc-info'] as $stateToken) {
    assertNotContains(
        $stateToken . ':',
        str_replace(' ', '', $warmCss),
        "presets/warm.css must not redefine the functional state token {$stateToken}",
        $failures
    );
}

// 4. Both presets must actually be wired into the build (index.css import
// list) and declared on the theme that ships them (mint_default/theme.json).
assertContains(
    "@import './presets/clean.css';",
    $indexCss,
    'assets/src/css/index.css must import presets/clean.css',
    $failures
);
assertContains(
    "@import './presets/warm.css';",
    $indexCss,
    'assets/src/css/index.css must import presets/warm.css',
    $failures
);
assertTrue(
    is_array($manifest) && is_array($manifest['presets'] ?? null)
        && in_array('clean', $manifest['presets'], true)
        && in_array('warm', $manifest['presets'], true),
    'mint_default/theme.json "presets" must list both "clean" and "warm"',
    $failures
);

// 5. The two "card look" / "button look" component-local overrides called
// out by the Phase 9 checklist must be present (not just the semantic
// color/radius tokens they're derived from).
foreach (['--mc-product-card-radius', '--mc-card-radius'] as $cardToken) {
    assertContains($cardToken . ':', $warmCss, "presets/warm.css must override {$cardToken} (card look)", $failures);
}
assertContains('--mc-button-radius:', $warmCss, 'presets/warm.css must override --mc-button-radius (button look)', $failures);

if ($failures) {
    fwrite(STDERR, count($failures) . " failure(s):\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "- {$failure}\n");
    }
    exit(1);
}

echo "presets-test.php: all assertions passed\n";
exit(0);
