<?php
/**
 * Standalone smoke test for MintCart\Blocks\BenefitsBlockProvider.
 *
 * Pure normalization logic, no OpenCart/DB dependency - exercised directly
 * against build()'s input/output contract.
 *
 * Run: php tests/mintcart/blocks/benefits-block-provider-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/blocks/benefitsblockprovider.php';

use MintCart\Blocks\BenefitsBlockProvider;

/** @var string[] $failures */
$failures = [];

function assertSame___($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

$provider = new BenefitsBlockProvider();

// 1. Empty settings/content -> no items, blank title.
$result = $provider->build();

assertSame___('', $result['title'], 'Scenario 1: empty content should default title to an empty string', $failures);
assertSame___([], $result['items'], 'Scenario 1: no items settings/content should produce an empty items list', $failures);

// 2. Real settings/content items merge by position: icon (settings) +
//    title/description (content).
$result = $provider->build(
    [
        'items' => [
            ['icon' => 'truck'],
            ['icon' => 'shield-check'],
        ],
    ],
    [
        'title' => 'Why shop with us',
        'items' => [
            ['title' => 'Free shipping', 'description' => 'On all orders over $50'],
            ['title' => 'Secure payment', 'description' => ''],
        ],
    ]
);

assertSame___('Why shop with us', $result['title'], 'Scenario 2: content title should pass through', $failures);
assertSame___(2, count($result['items']), 'Scenario 2: both items should be produced', $failures);
assertSame___('truck', $result['items'][0]['icon'], 'Scenario 2: item 1 icon should pass through from settings', $failures);
assertSame___('Free shipping', $result['items'][0]['title'], 'Scenario 2: item 1 title should pass through from content', $failures);
assertSame___('On all orders over $50', $result['items'][0]['description'], 'Scenario 2: item 1 description should pass through from content', $failures);
assertSame___('shield-check', $result['items'][1]['icon'], 'Scenario 2: item 2 icon should pass through from settings', $failures);
assertSame___('Secure payment', $result['items'][1]['title'], 'Scenario 2: item 2 title should pass through from content', $failures);
assertSame___('', $result['items'][1]['description'], 'Scenario 2: item 2 description should default to an empty string', $failures);

// 3. A content item with no title is skipped (not rendered as an empty
//    card); an item with a title but no matching settings icon falls back
//    to the default icon.
$result = $provider->build(
    ['items' => [[], ['icon' => 'lock']]],
    ['items' => [['title' => ''], ['title' => 'Secure checkout']]]
);

assertSame___(1, count($result['items']), 'Scenario 3: a blank-title item should be skipped, leaving one item', $failures);
assertSame___('lock', $result['items'][0]['icon'], 'Scenario 3: the surviving item should keep its matched settings icon', $failures);
assertSame___('Secure checkout', $result['items'][0]['title'], 'Scenario 3: the surviving item should keep its title', $failures);

$result = $provider->build([], ['items' => [['title' => 'No icon set']]]);

assertSame___('check', $result['items'][0]['icon'], 'Scenario 3: a missing settings icon should fall back to the default (check)', $failures);

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo "PASS: BenefitsBlockProvider smoke test (3 scenarios)\n";
exit(0);
