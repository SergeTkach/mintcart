<?php
/**
 * Standalone smoke test for MintCart\Blocks\TestimonialsBlockProvider.
 *
 * Pure normalization logic, no OpenCart/DB dependency - exercised directly
 * against build()'s input/output contract.
 *
 * Run: php tests/mintcart/blocks/testimonials-block-provider-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/blocks/testimonialsblockprovider.php';

use MintCart\Blocks\TestimonialsBlockProvider;

/** @var string[] $failures */
$failures = [];

function assertSame____($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

$provider = new TestimonialsBlockProvider();

// 1. Empty settings/content -> empty title, no items.
$result = $provider->build();

assertSame____('', $result['title'], 'Scenario 1: empty content should default title to an empty string', $failures);
assertSame____([], $result['items'], 'Scenario 1: no content items should produce an empty items list', $failures);

// 2. Real settings/content, matched by array position, normalized.
$result = $provider->build(
    [
        'items' => [
            ['avatar' => 'catalog/avatar1.jpg', 'avatar_width' => '80', 'avatar_height' => '80', 'rating' => '5'],
            ['rating' => '3'],
        ],
    ],
    [
        'title' => 'What our customers say',
        'items' => [
            ['quote' => 'Fantastic service!', 'author_name' => 'Jane Doe', 'author_role' => 'CEO, Acme'],
            ['quote' => 'Would buy again.', 'author_name' => 'John Smith'],
        ],
    ]
);

assertSame____('What our customers say', $result['title'], 'Scenario 2: content title should pass through', $failures);
assertSame____(2, count($result['items']), 'Scenario 2: both content items should be normalized', $failures);

assertSame____('Fantastic service!', $result['items'][0]['quote'], 'Scenario 2: item 1 quote should pass through', $failures);
assertSame____('Jane Doe', $result['items'][0]['author_name'], 'Scenario 2: item 1 author_name should pass through', $failures);
assertSame____('CEO, Acme', $result['items'][0]['author_role'], 'Scenario 2: item 1 author_role should pass through', $failures);
assertSame____('catalog/avatar1.jpg', $result['items'][0]['avatar'], 'Scenario 2: item 1 avatar should be matched from settings by array position', $failures);
assertSame____(80, $result['items'][0]['avatar_width'], 'Scenario 2: item 1 avatar_width should normalize a numeric string to int', $failures);
assertSame____(5, $result['items'][0]['rating'], 'Scenario 2: item 1 rating should normalize a numeric string to int', $failures);

assertSame____('Would buy again.', $result['items'][1]['quote'], 'Scenario 2: item 2 quote should pass through', $failures);
assertSame____('', $result['items'][1]['author_role'], 'Scenario 2: item 2 missing author_role should default to empty string', $failures);
assertSame____(null, $result['items'][1]['avatar'], 'Scenario 2: item 2 with no settings avatar should default to null', $failures);
assertSame____(64, $result['items'][1]['avatar_width'], 'Scenario 2: item 2 with no avatar_width setting should default to 64', $failures);
assertSame____(3, $result['items'][1]['rating'], 'Scenario 2: item 2 rating should normalize', $failures);

// 3. Invalid items are dropped, not propagated: missing quote, missing
//    author_name, non-array item, and an out-of-range rating clamped to
//    null rather than passed through as garbage.
$result = $provider->build(
    [
        'items' => [
            [],
            [],
            [],
            ['rating' => '11'],
        ],
    ],
    [
        'items' => [
            ['quote' => '', 'author_name' => 'No Quote'],
            ['quote' => 'No Author', 'author_name' => ''],
            'not-an-array',
            ['quote' => 'Valid quote', 'author_name' => 'Valid Author'],
        ],
    ]
);

assertSame____(1, count($result['items']), 'Scenario 3: only the one fully valid item should survive normalization', $failures);
assertSame____('Valid quote', $result['items'][0]['quote'], 'Scenario 3: the surviving item should be the valid one', $failures);
assertSame____(null, $result['items'][0]['rating'], 'Scenario 3: an out-of-range rating (11) should normalize to null, not pass through', $failures);

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo "PASS: TestimonialsBlockProvider smoke test (3 scenarios)\n";
exit(0);
