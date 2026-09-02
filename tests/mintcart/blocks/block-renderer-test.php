<?php
/**
 * Standalone smoke test for MintCart\Blocks\BlockRenderer.
 *
 * Same style/rationale as tests/mintcart/design/component-renderer-test.php:
 * no PHPUnit/Composer test runner in this repo yet, no vendor/twig/twig
 * installed, so render()'s actual Twig output isn't exercised - only
 * resolve()'s fallback-chain behaviour (delegated to
 * MintCart\Design\ComponentRenderer with kind='blocks') and the real
 * shipped mint_default/template/mint/blocks/products.twig file.
 *
 * Run: php tests/mintcart/blocks/block-renderer-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/design/componentrenderer.php';
require __DIR__ . '/../../../upload/system/library/mintcart/blocks/blockrenderer.php';

use MintCart\Blocks\BlockRenderer;

/** @var string[] $failures */
$failures = [];

function assertSame__($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

// 1. BlockRenderer resolves under template/mint/blocks/, not
//    template/mint/components/ - proves the 'blocks' kind is actually
//    wired through to ComponentRenderer, not defaulting to 'components'.
$root = sys_get_temp_dir() . '/mintcart-block-renderer-test-' . bin2hex(random_bytes(8));
mkdir($root . '/mint_default/template/mint/blocks', 0777, true);
file_put_contents($root . '/mint_default/theme.json', json_encode([
    'name' => 'Mint Default',
    'code' => 'mint_default',
    'version' => '1.0.0',
    'mint_api' => '^1.0',
    'parent' => null,
    'presets' => [],
]));
file_put_contents($root . '/mint_default/template/mint/blocks/products.twig', '<!-- products block -->');

$renderer = new BlockRenderer($root, 'mint_default');

assertSame__(
    $root . '/mint_default/template/mint/blocks/products.twig',
    $renderer->resolve('products'),
    'Scenario 1: BlockRenderer::resolve() should look under template/mint/blocks/, not template/mint/components/',
    $failures
);

// Same fixture has no components/ directory at all - resolving a
// component name must NOT accidentally find the blocks file (kind
// isolation, not just a different subdirectory name coincidence).
$componentRenderer = new \MintCart\Design\ComponentRenderer($root, 'mint_default');
$threw = false;

try {
    $componentRenderer->resolve('products');
} catch (\RuntimeException $e) {
    $threw = true;
}

assertSame__(true, $threw, 'Scenario 1: a component-kind ComponentRenderer must not resolve a blocks-only template', $failures);

// 2. Real-repo integration: the shipped
//    mint_default/template/mint/blocks/products.twig must resolve at its
//    real path - guards against the theme directory layout
//    (catalog/view/theme/<code>/template/mint/blocks/) drifting out of
//    sync with what BlockRenderer/ComponentRenderer actually build.
$realThemesRoot = __DIR__ . '/../../../upload/catalog/view/theme';
$realRenderer = new BlockRenderer($realThemesRoot, 'mint_default');
$realPath = $realRenderer->resolve('products');

assertSame__(
    true,
    is_file($realPath),
    'Scenario 2: resolve() should find the real mint_default blocks/products.twig file on disk',
    $failures
);

$realMarkup = (string) file_get_contents($realPath);

assertSame__(
    true,
    str_contains($realMarkup, 'mc-block--products') && str_contains($realMarkup, 'product_cards'),
    'Scenario 2: the resolved real products.twig should contain the documented mc-block--products markup and loop over product_cards',
    $failures
);

// 3. Real-repo integration: the shipped
//    mint_default/template/mint/blocks/categories.twig must also resolve
//    at its real path - same guard as Scenario 2, for the Categories
//    block added alongside Products.
$realCategoriesPath = $realRenderer->resolve('categories');

assertSame__(
    true,
    is_file($realCategoriesPath),
    'Scenario 3: resolve() should find the real mint_default blocks/categories.twig file on disk',
    $failures
);

$realCategoriesMarkup = (string) file_get_contents($realCategoriesPath);

assertSame__(
    true,
    str_contains($realCategoriesMarkup, 'mc-block--categories') && str_contains($realCategoriesMarkup, 'categories'),
    'Scenario 3: the resolved real categories.twig should contain the documented mc-block--categories markup and loop over categories',
    $failures
);

// 4. Real-repo integration: the shipped
//    mint_default/template/mint/blocks/benefits.twig must also resolve -
//    guards the Benefits block against the same directory-layout drift
//    Scenario 2 guards Products against.
$realBenefitsPath = $realRenderer->resolve('benefits');

assertSame__(
    true,
    is_file($realBenefitsPath),
    'Scenario 4: resolve() should find the real mint_default blocks/benefits.twig file on disk',
    $failures
);

$realBenefitsMarkup = (string) file_get_contents($realBenefitsPath);

assertSame__(
    true,
    str_contains($realBenefitsMarkup, 'mc-block--benefits') && str_contains($realBenefitsMarkup, 'items'),
    'Scenario 4: the resolved real benefits.twig should contain the documented mc-block--benefits markup and loop over items',
    $failures
);

// 5. Real-repo integration: the shipped
//    mint_default/template/mint/blocks/faq.twig must also resolve at its
//    real path - proves a second block type shares the same 'blocks' kind
//    resolution as Products, not something Products-specific.
$realFaqPath = $realRenderer->resolve('faq');

assertSame__(
    true,
    is_file($realFaqPath),
    'Scenario 5: resolve() should find the real mint_default blocks/faq.twig file on disk',
    $failures
);

$realFaqMarkup = (string) file_get_contents($realFaqPath);

assertSame__(
    true,
    str_contains($realFaqMarkup, 'mc-block--faq') && str_contains($realFaqMarkup, 'items'),
    'Scenario 5: the resolved real faq.twig should contain the documented mc-block--faq markup and loop over items',
    $failures
);

// 6. Real-repo integration: the shipped
//    mint_default/template/mint/blocks/testimonials.twig must also
//    resolve - guards the second real block type's template against the
//    same layout drift Scenario 2 guards for products.twig.
$realTestimonialsPath = $realRenderer->resolve('testimonials');

assertSame__(
    true,
    is_file($realTestimonialsPath),
    'Scenario 6: resolve() should find the real mint_default blocks/testimonials.twig file on disk',
    $failures
);

$realTestimonialsMarkup = (string) file_get_contents($realTestimonialsPath);

assertSame__(
    true,
    str_contains($realTestimonialsMarkup, 'mc-block--testimonials') && str_contains($realTestimonialsMarkup, 'mc-testimonial__quote'),
    'Scenario 6: the resolved real testimonials.twig should contain the documented mc-block--testimonials/mc-testimonial__quote markup',
    $failures
);

// 7. Real-repo integration: the shipped
//    mint_default/template/mint/blocks/brands.twig must also resolve at
//    its real path (a second block type, proving resolve() isn't only
//    coincidentally correct for 'products').
$realBrandsPath = $realRenderer->resolve('brands');

assertSame__(
    true,
    is_file($realBrandsPath),
    'Scenario 7: resolve() should find the real mint_default blocks/brands.twig file on disk',
    $failures
);

$realBrandsMarkup = (string) file_get_contents($realBrandsPath);

assertSame__(
    true,
    str_contains($realBrandsMarkup, 'mc-block--brands') && str_contains($realBrandsMarkup, 'items'),
    'Scenario 7: the resolved real brands.twig should contain the documented mc-block--brands markup and loop over items',
    $failures
);

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo "PASS: BlockRenderer smoke test (3 scenarios)\n";
exit(0);
