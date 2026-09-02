<?php
/**
 * Standalone smoke test for MintCart\Blocks\GalleryBlockProvider.
 *
 * Pure normalization logic, no OpenCart/DB dependency - exercised directly
 * against build()'s input/output contract.
 *
 * Run: php tests/mintcart/blocks/gallery-block-provider-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/blocks/galleryblockprovider.php';

use MintCart\Blocks\GalleryBlockProvider;

/** @var string[] $failures */
$failures = [];

function assertSame____($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

$provider = new GalleryBlockProvider();

// 1. Empty settings/content -> documented defaults (4 columns, no items,
//    blank title).
$result = $provider->build();

assertSame____('', $result['title'], 'Scenario 1: empty content should default title to an empty string', $failures);
assertSame____(4, $result['columns'], 'Scenario 1: no columns setting should default to 4', $failures);
assertSame____(3, $result['col_width'], 'Scenario 1: 4 columns should give a col_width of 3 (12/4)', $failures);
assertSame____([], $result['items'], 'Scenario 1: no items setting should default to an empty list', $failures);

// 2. Real settings/content items, matched up by position.
$result = $provider->build(
    [
        'columns' => '6',
        'items' => [
            ['image' => 'catalog/gallery/storefront.jpg', 'image_width' => '800', 'image_height' => '600'],
            ['image' => 'catalog/gallery/workshop.jpg'],
        ],
    ],
    [
        'title' => 'Behind the scenes',
        'items' => [
            ['alt' => 'Our storefront', 'caption' => 'Downtown flagship'],
            ['alt' => 'Our workshop'],
        ],
    ]
);

assertSame____('Behind the scenes', $result['title'], 'Scenario 2: content title should pass through', $failures);
assertSame____(6, $result['columns'], 'Scenario 2: a numeric-string columns setting should normalize to int', $failures);
assertSame____(2, $result['col_width'], 'Scenario 2: 6 columns should give a col_width of 2 (12/6)', $failures);
assertSame____(2, count($result['items']), 'Scenario 2: both items should be kept', $failures);
assertSame____('catalog/gallery/storefront.jpg', $result['items'][0]['image'], 'Scenario 2: item 1 image should pass through', $failures);
assertSame____(800, $result['items'][0]['image_width'], 'Scenario 2: a numeric-string image_width should normalize to int', $failures);
assertSame____('Our storefront', $result['items'][0]['alt'], 'Scenario 2: item 1 alt should match by position', $failures);
assertSame____('Downtown flagship', $result['items'][0]['caption'], 'Scenario 2: item 1 caption should match by position', $failures);
assertSame____(null, $result['items'][1]['image_width'], 'Scenario 2: item 2 with no image_width should default to null', $failures);
assertSame____('Our workshop', $result['items'][1]['alt'], 'Scenario 2: item 2 alt should match by position even with no caption', $failures);
assertSame____('', $result['items'][1]['caption'], 'Scenario 2: item 2 with no caption should default to an empty string', $failures);

// 3. Invalid/out-of-range values fall back to defaults; an item with no
//    image is dropped rather than rendering an empty <img>.
$result = $provider->build([
    'columns' => '5',
    'items' => [
        ['image_width' => '400'],
        ['image' => 'catalog/gallery/team.jpg'],
    ],
]);

assertSame____(4, $result['columns'], 'Scenario 3: a non-divisor columns value should fall back to the default (4)', $failures);
assertSame____(1, count($result['items']), 'Scenario 3: an item with no image should be dropped', $failures);
assertSame____('catalog/gallery/team.jpg', $result['items'][0]['image'], 'Scenario 3: the remaining item should be the one with an image', $failures);

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo "PASS: GalleryBlockProvider smoke test (3 scenarios)\n";
exit(0);
