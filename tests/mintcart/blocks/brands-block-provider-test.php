<?php
/**
 * Standalone smoke test for MintCart\Blocks\BrandsBlockProvider.
 *
 * Pure normalization logic, no OpenCart/DB dependency - exercised directly
 * against build()'s input/output contract, same style as
 * tests/mintcart/blocks/products-block-provider-test.php.
 *
 * Run: php tests/mintcart/blocks/brands-block-provider-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/blocks/brandsblockprovider.php';

use MintCart\Blocks\BrandsBlockProvider;

/** @var string[] $failures */
$failures = [];

function assertSame____($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

$provider = new BrandsBlockProvider();

// 1. Empty settings/content -> blank title, no items.
$result = $provider->build();

assertSame____('', $result['title'], 'Scenario 1: empty content should default title to an empty string', $failures);
assertSame____([], $result['items'], 'Scenario 1: no settings items should default to an empty list', $failures);

// 2. Real settings/content items pass through, matched by array position,
//    with dimensions normalized to int and defaulted when missing.
$result = $provider->build(
    [
        'items' => [
            ['image' => 'catalog/brand-a.png', 'url' => 'index.php?route=product/manufacturer.info&manufacturer_id=1', 'image_width' => '200', 'image_height' => '100'],
            ['image' => 'catalog/brand-b.png'],
        ],
    ],
    [
        'title' => 'Our Brands',
        'items' => [
            ['alt' => 'Brand A'],
            ['alt' => 'Brand B'],
        ],
    ]
);

assertSame____('Our Brands', $result['title'], 'Scenario 2: content title should pass through', $failures);
assertSame____(2, count($result['items']), 'Scenario 2: both items should be present', $failures);
assertSame____('catalog/brand-a.png', $result['items'][0]['image'], 'Scenario 2: item 1 image should pass through', $failures);
assertSame____('index.php?route=product/manufacturer.info&manufacturer_id=1', $result['items'][0]['url'], 'Scenario 2: item 1 url should pass through', $failures);
assertSame____(200, $result['items'][0]['image_width'], 'Scenario 2: a numeric-string image_width should normalize to int', $failures);
assertSame____(100, $result['items'][0]['image_height'], 'Scenario 2: a numeric-string image_height should normalize to int', $failures);
assertSame____('Brand A', $result['items'][0]['alt'], 'Scenario 2: item 1 alt should be matched by array position', $failures);
assertSame____('', $result['items'][1]['url'], 'Scenario 2: item 2 with no url should default to an empty string', $failures);
assertSame____(160, $result['items'][1]['image_width'], 'Scenario 2: item 2 with no image_width should default to 160', $failures);
assertSame____(80, $result['items'][1]['image_height'], 'Scenario 2: item 2 with no image_height should default to 80', $failures);
assertSame____('Brand B', $result['items'][1]['alt'], 'Scenario 2: item 2 alt should be matched by array position', $failures);

// 3. An item with no image is dropped rather than rendered as an empty
//    <img> - same "skip, don't emit garbage" rule BannerGridBlockProvider
//    follows.
$result = $provider->build(
    [
        'items' => [
            ['image' => '', 'url' => 'index.php?route=product/manufacturer'],
            ['image' => 'catalog/brand-c.png'],
        ],
    ],
    ['items' => [['alt' => 'No image'], ['alt' => 'Brand C']]]
);

assertSame____(1, count($result['items']), 'Scenario 3: an item with a blank image should be dropped', $failures);
assertSame____('Brand C', $result['items'][0]['alt'], 'Scenario 3: the surviving item should be the one with an image', $failures);

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo "PASS: BrandsBlockProvider smoke test (3 scenarios)\n";
exit(0);
