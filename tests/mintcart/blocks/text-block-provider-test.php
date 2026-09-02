<?php
/**
 * Standalone smoke test for MintCart\Blocks\TextBlockProvider.
 *
 * Pure normalization logic, no OpenCart/DB dependency - exercised directly
 * against build()'s input/output contract, same shape as
 * products-block-provider-test.php.
 *
 * Run: php tests/mintcart/blocks/text-block-provider-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/blocks/textblockprovider.php';

use MintCart\Blocks\TextBlockProvider;

/** @var string[] $failures */
$failures = [];

function assertSame____($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

$provider = new TextBlockProvider();

// 1. Empty settings/content -> documented defaults (blank title/content,
//    left alignment).
$result = $provider->build();

assertSame____('', $result['title'], 'Scenario 1: empty content should default title to an empty string', $failures);
assertSame____('', $result['content'], 'Scenario 1: empty content should default content to an empty string', $failures);
assertSame____('left', $result['text_align'], 'Scenario 1: no text_align setting should default to left', $failures);

// 2. Real settings/content pass through, normalized.
$result = $provider->build(
    ['text_align' => 'CENTER'],
    ['title' => 'About us', 'content' => '<p>Hello <strong>world</strong>.</p>']
);

assertSame____('About us', $result['title'], 'Scenario 2: content title should pass through', $failures);
assertSame____('<p>Hello <strong>world</strong>.</p>', $result['content'], 'Scenario 2: content HTML should pass through unescaped/unmodified', $failures);
assertSame____('center', $result['text_align'], 'Scenario 2: an uppercase text_align setting should normalize to lowercase', $failures);

// 3. An unrecognized text_align value falls back to the default rather
//    than propagating garbage into the Twig template's class conditional.
$result = $provider->build(['text_align' => 'sideways'], ['content' => 'Some text.']);

assertSame____('left', $result['text_align'], 'Scenario 3: an unrecognized text_align value should fall back to the default (left)', $failures);

// 4. Non-string content/title values (e.g. a stray array/number from a
//    corrupted settings_json) degrade to empty strings rather than a
//    PHP type error reaching the caller.
$result = $provider->build(['text_align' => 42], ['title' => ['not', 'a', 'string'], 'content' => null]);

assertSame____('', $result['title'], 'Scenario 4: a non-string title should default to an empty string', $failures);
assertSame____('', $result['content'], 'Scenario 4: a non-string content should default to an empty string', $failures);
assertSame____('left', $result['text_align'], 'Scenario 4: a non-string text_align should default to left', $failures);

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo "PASS: TextBlockProvider smoke test (4 scenarios)\n";
exit(0);
