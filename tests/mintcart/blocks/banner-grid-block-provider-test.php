<?php
/**
 * Standalone smoke test for MintCart\Blocks\BannerGridBlockProvider.
 *
 * Pure normalization logic, no OpenCart/DB dependency - exercised directly
 * against build()'s input/output contract.
 *
 * Run: php tests/mintcart/blocks/banner-grid-block-provider-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/blocks/bannergridblockprovider.php';

use MintCart\Blocks\BannerGridBlockProvider;

/** @var string[] $failures */
$failures = [];

function assertSame___($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

$provider = new BannerGridBlockProvider();

// 1. Empty settings/content -> documented defaults (3 columns, no items,
//    blank title).
$result = $provider->build();

assertSame___('', $result['title'], 'Scenario 1: empty content should default title to an empty string', $failures);
assertSame___(3, $result['columns'], 'Scenario 1: no columns setting should default to 3', $failures);
assertSame___(4, $result['col_width'], 'Scenario 1: 3 columns should give a col_width of 4 (12/3)', $failures);
assertSame___([], $result['items'], 'Scenario 1: no items setting should default to an empty list', $failures);

// 2. Real settings/content items, matched up by position.
$result = $provider->build(
    [
        'columns' => '4',
        'items' => [
            ['image' => 'catalog/banner/summer.jpg', 'url' => 'index.php?route=product/category&path=20', 'image_width' => '600', 'image_height' => '400'],
            ['image' => 'catalog/banner/winter.jpg', 'url' => 'index.php?route=product/category&path=21'],
        ],
    ],
    [
        'title' => 'Featured collections',
        'items' => [
            ['alt' => 'Summer collection', 'caption' => 'Up to 50% off'],
            ['alt' => 'Winter collection'],
        ],
    ]
);

assertSame___('Featured collections', $result['title'], 'Scenario 2: content title should pass through', $failures);
assertSame___(4, $result['columns'], 'Scenario 2: a numeric-string columns setting should normalize to int', $failures);
assertSame___(3, $result['col_width'], 'Scenario 2: 4 columns should give a col_width of 3 (12/4)', $failures);
assertSame___(2, count($result['items']), 'Scenario 2: both items should be kept', $failures);
assertSame___('catalog/banner/summer.jpg', $result['items'][0]['image'], 'Scenario 2: item 1 image should pass through', $failures);
assertSame___(600, $result['items'][0]['image_width'], 'Scenario 2: a numeric-string image_width should normalize to int', $failures);
assertSame___('Summer collection', $result['items'][0]['alt'], 'Scenario 2: item 1 alt should match by position', $failures);
assertSame___('Up to 50% off', $result['items'][0]['caption'], 'Scenario 2: item 1 caption should match by position', $failures);
assertSame___(null, $result['items'][1]['image_width'], 'Scenario 2: item 2 with no image_width should default to null', $failures);
assertSame___('Winter collection', $result['items'][1]['alt'], 'Scenario 2: item 2 alt should match by position even with no caption', $failures);
assertSame___('', $result['items'][1]['caption'], 'Scenario 2: item 2 with no caption should default to an empty string', $failures);

// 3. Invalid/out-of-range values fall back to defaults; an item with no
//    image is dropped rather than rendering an empty <img>.
$result = $provider->build([
    'columns' => '7',
    'items' => [
        ['url' => 'index.php?route=product/category&path=22'],
        ['image' => 'catalog/banner/spring.jpg'],
    ],
]);

assertSame___(3, $result['columns'], 'Scenario 3: an out-of-range columns value should fall back to the default (3)', $failures);
assertSame___(1, count($result['items']), 'Scenario 3: an item with no image should be dropped', $failures);
assertSame___('catalog/banner/spring.jpg', $result['items'][0]['image'], 'Scenario 3: the remaining item should be the one with an image', $failures);

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo "PASS: BannerGridBlockProvider smoke test (3 scenarios)\n";
exit(0);
