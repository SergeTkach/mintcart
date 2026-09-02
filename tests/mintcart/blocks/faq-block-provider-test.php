<?php
/**
 * Standalone smoke test for MintCart\Blocks\FaqBlockProvider.
 *
 * Pure normalization logic, no OpenCart/DB dependency - exercised directly
 * against build()'s input/output contract, same style as
 * tests/mintcart/blocks/products-block-provider-test.php.
 *
 * Run: php tests/mintcart/blocks/faq-block-provider-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/blocks/faqblockprovider.php';

use MintCart\Blocks\FaqBlockProvider;

/** @var string[] $failures */
$failures = [];

function assertSame____($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

$provider = new FaqBlockProvider();

// 1. Empty settings/content -> blank title, no items.
$result = $provider->build();

assertSame____('', $result['title'], 'Scenario 1: empty content should default title to an empty string', $failures);
assertSame____([], $result['items'], 'Scenario 1: no items setting should default to an empty list', $failures);

// 2. Real content passes through, trimmed.
$result = $provider->build([], [
    'title' => 'Frequently Asked Questions',
    'items' => [
        ['question' => '  Do you ship internationally?  ', 'answer' => '  Yes, worldwide.  '],
        ['question' => 'What is your return policy?', 'answer' => '30 days, no questions asked.'],
    ],
]);

assertSame____('Frequently Asked Questions', $result['title'], 'Scenario 2: content title should pass through', $failures);
assertSame____(2, count($result['items']), 'Scenario 2: both valid items should be kept', $failures);
assertSame____('Do you ship internationally?', $result['items'][0]['question'], 'Scenario 2: a question should be trimmed', $failures);
assertSame____('Yes, worldwide.', $result['items'][0]['answer'], 'Scenario 2: an answer should be trimmed', $failures);

// 3. An item missing a question, missing an answer, or not an array at
//    all should be dropped rather than reaching the template half-empty.
$result = $provider->build([], [
    'items' => [
        ['question' => 'No answer here', 'answer' => ''],
        ['question' => '', 'answer' => 'No question here'],
        'not-an-array',
        ['question' => 'Valid pair', 'answer' => 'Valid answer'],
    ],
]);

assertSame____(1, count($result['items']), 'Scenario 3: only the one fully-valid item should survive normalization', $failures);
assertSame____('Valid pair', $result['items'][0]['question'], 'Scenario 3: the surviving item should be the fully-valid one', $failures);

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo "PASS: FaqBlockProvider smoke test (3 scenarios)\n";
exit(0);
