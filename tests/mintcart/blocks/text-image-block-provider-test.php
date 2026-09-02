<?php
/**
 * Standalone smoke test for MintCart\Blocks\TextImageBlockProvider.
 *
 * Pure normalization logic, no OpenCart/DB dependency - exercised directly
 * against build()'s input/output contract.
 *
 * Run: php tests/mintcart/blocks/text-image-block-provider-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/blocks/textimageblockprovider.php';

use MintCart\Blocks\TextImageBlockProvider;

/** @var string[] $failures */
$failures = [];

function assertSame____($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

$provider = new TextImageBlockProvider();

// 1. Empty settings/content -> a plain-text block (no image), documented
//    defaults (800x600, image_position 'right', even though there is no
//    image to position).
$result = $provider->build();

assertSame____('', $result['title'], 'Scenario 1: empty content should default title to an empty string', $failures);
assertSame____('', $result['body'], 'Scenario 1: empty content should default body to an empty string', $failures);
assertSame____(null, $result['image'], 'Scenario 1: no image setting should default to null (plain text block)', $failures);
assertSame____('', $result['image_alt'], 'Scenario 1: no image_alt content should default to an empty string', $failures);
assertSame____(800, $result['image_width'], 'Scenario 1: no image_width setting should default to 800', $failures);
assertSame____(600, $result['image_height'], 'Scenario 1: no image_height setting should default to 600', $failures);
assertSame____('right', $result['image_position'], 'Scenario 1: no image_position setting should default to right', $failures);

// 2. Real settings/content pass through, normalized (a "Text + Image"
//    block with the image on the left).
$result = $provider->build(
    ['image' => 'catalog/about.jpg', 'image_width' => '480', 'image_height' => '320', 'image_position' => 'LEFT'],
    ['title' => 'About us', 'body' => '<p>Hello</p>', 'image_alt' => 'Our team']
);

assertSame____('About us', $result['title'], 'Scenario 2: content title should pass through', $failures);
assertSame____('<p>Hello</p>', $result['body'], 'Scenario 2: content body should pass through unescaped', $failures);
assertSame____('catalog/about.jpg', $result['image'], 'Scenario 2: a real image setting should pass through', $failures);
assertSame____('Our team', $result['image_alt'], 'Scenario 2: content image_alt should pass through', $failures);
assertSame____(480, $result['image_width'], 'Scenario 2: a numeric-string image_width should normalize to int', $failures);
assertSame____(320, $result['image_height'], 'Scenario 2: a numeric-string image_height should normalize to int', $failures);
assertSame____('left', $result['image_position'], 'Scenario 2: an uppercase image_position should normalize to lowercase', $failures);

// 3. Invalid/out-of-range values fall back to defaults rather than
//    propagating garbage into the template (e.g. a blank image string, a
//    non-positive width, an unrecognized position).
$result = $provider->build(['image' => '', 'image_width' => '-10', 'image_position' => 'center']);

assertSame____(null, $result['image'], 'Scenario 3: a blank image string should normalize to null, not an empty src attribute', $failures);
assertSame____(800, $result['image_width'], 'Scenario 3: a non-positive image_width should fall back to the default (800)', $failures);
assertSame____('right', $result['image_position'], 'Scenario 3: an unrecognized image_position value should fall back to the default (right)', $failures);

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo "PASS: TextImageBlockProvider smoke test (3 scenarios)\n";
exit(0);
