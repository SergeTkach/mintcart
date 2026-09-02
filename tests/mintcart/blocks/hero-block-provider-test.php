<?php
/**
 * Standalone smoke test for MintCart\Blocks\HeroBlockProvider.
 *
 * Pure normalization logic, no OpenCart/DB dependency - exercised directly
 * against build()'s input/output contract.
 *
 * Run: php tests/mintcart/blocks/hero-block-provider-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/blocks/heroblockprovider.php';

use MintCart\Blocks\HeroBlockProvider;

/** @var string[] $failures */
$failures = [];

function assertSame____($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

$provider = new HeroBlockProvider();

// 1. Empty settings/content -> documented defaults: every text field blank,
//    no image, default width/height even though no image is set (a
//    resolvable-image-later state shouldn't need the block re-saved).
$result = $provider->build();

assertSame____('', $result['title'], 'Scenario 1: empty content should default title to an empty string', $failures);
assertSame____('', $result['subtitle'], 'Scenario 1: empty content should default subtitle to an empty string', $failures);
assertSame____('', $result['cta_label'], 'Scenario 1: empty content should default cta_label to an empty string', $failures);
assertSame____('', $result['cta_url'], 'Scenario 1: empty settings should default cta_url to an empty string', $failures);
assertSame____(null, $result['image'], 'Scenario 1: no image setting should default to null', $failures);
assertSame____(1920, $result['image_width'], 'Scenario 1: no image_width setting should default to 1920', $failures);
assertSame____(600, $result['image_height'], 'Scenario 1: no image_height setting should default to 600', $failures);

// 2. Real settings/content pass through, normalized.
$result = $provider->build(
    ['image' => 'catalog/banners/hero.jpg', 'image_width' => '1600', 'image_height' => '500', 'cta_url' => 'index.php?route=product/special'],
    ['title' => 'Summer Sale', 'subtitle' => 'Up to 50% off', 'cta_label' => 'Shop now']
);

assertSame____('Summer Sale', $result['title'], 'Scenario 2: content title should pass through', $failures);
assertSame____('Up to 50% off', $result['subtitle'], 'Scenario 2: content subtitle should pass through', $failures);
assertSame____('Shop now', $result['cta_label'], 'Scenario 2: content cta_label should pass through', $failures);
assertSame____('index.php?route=product/special', $result['cta_url'], 'Scenario 2: settings cta_url should pass through', $failures);
assertSame____('catalog/banners/hero.jpg', $result['image'], 'Scenario 2: settings image should pass through unmodified (no resizing here)', $failures);
assertSame____(1600, $result['image_width'], 'Scenario 2: a numeric-string image_width should normalize to int', $failures);
assertSame____(500, $result['image_height'], 'Scenario 2: a numeric-string image_height should normalize to int', $failures);

// 3. Invalid/out-of-range values fall back to defaults rather than
//    propagating garbage (e.g. an empty-string image, a non-positive
//    image_width).
$result = $provider->build(['image' => '', 'image_width' => '-100', 'image_height' => '0']);

assertSame____(null, $result['image'], 'Scenario 3: an empty-string image should normalize to null, not pass through as a blank path', $failures);
assertSame____(1920, $result['image_width'], 'Scenario 3: a non-positive image_width should fall back to the default (1920)', $failures);
assertSame____(600, $result['image_height'], 'Scenario 3: a non-positive image_height should fall back to the default (600)', $failures);

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo "PASS: HeroBlockProvider smoke test (3 scenarios)\n";
exit(0);
