<?php
/**
 * Standalone smoke test for MintCart\Blocks\HtmlBlockProvider.
 *
 * Pure normalization logic, no OpenCart/DB dependency - exercised directly
 * against build()'s input/output contract.
 *
 * Run: php tests/mintcart/blocks/html-block-provider-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/blocks/htmlblockprovider.php';

use MintCart\Blocks\HtmlBlockProvider;

/** @var string[] $failures */
$failures = [];

function assertSame___($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

$provider = new HtmlBlockProvider();

// 1. No content at all -> empty html string, never null/false.
$result = $provider->build();

assertSame___('', $result['html'], 'Scenario 1: missing html content should default to an empty string', $failures);

// 2. Real content passes through unchanged - this block's whole purpose is
//    to let raw admin-authored markup reach the template untouched.
$result = $provider->build([], ['html' => '<div class="promo"><strong>Sale!</strong></div>']);

assertSame___(
    '<div class="promo"><strong>Sale!</strong></div>',
    $result['html'],
    'Scenario 2: real html content should pass through unchanged',
    $failures
);

// 3. A non-string html value (bad/legacy data) should not propagate into
//    the template as anything other than a safe empty string.
$result = $provider->build([], ['html' => ['not', 'a', 'string']]);

assertSame___('', $result['html'], 'Scenario 3: a non-string html value should normalize to an empty string', $failures);

// 4. settings_json is accepted but ignored - the HTML block has no
//    structural settings, only proving the shared build(settings, content)
//    shape doesn't choke on an arbitrary settings array.
$result = $provider->build(['unused' => true], ['html' => '<p>Hi</p>']);

assertSame___('<p>Hi</p>', $result['html'], 'Scenario 4: settings_json should be accepted but have no effect on the output', $failures);

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo "PASS: HtmlBlockProvider smoke test (4 scenarios)\n";
exit(0);
