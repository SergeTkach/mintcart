<?php
/**
 * Standalone smoke test for MintCart\Blocks\CtaBlockProvider.
 *
 * Pure normalization logic, no OpenCart/DB dependency - exercised directly
 * against build()'s input/output contract.
 *
 * Run: php tests/mintcart/blocks/cta-block-provider-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/blocks/ctablockprovider.php';

use MintCart\Blocks\CtaBlockProvider;

/** @var string[] $failures */
$failures = [];

function assertSame____($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

$provider = new CtaBlockProvider();

// 1. Empty settings/content -> all fields default to an empty string
//    (blocks/cta.twig renders nothing in this state).
$result = $provider->build();

assertSame____('', $result['title'], 'Scenario 1: empty content should default title to an empty string', $failures);
assertSame____('', $result['text'], 'Scenario 1: empty content should default text to an empty string', $failures);
assertSame____('', $result['cta_label'], 'Scenario 1: empty content should default cta_label to an empty string', $failures);
assertSame____('', $result['cta_url'], 'Scenario 1: empty content should default cta_url to an empty string', $failures);

// 2. Real content, with a complete button, passes through.
$result = $provider->build(
    [],
    ['title' => 'Ready to start?', 'text' => 'Set up your store today.', 'cta_label' => 'Get started', 'cta_url' => 'index.php?route=product/category&path=20']
);

assertSame____('Ready to start?', $result['title'], 'Scenario 2: content title should pass through', $failures);
assertSame____('Set up your store today.', $result['text'], 'Scenario 2: content text should pass through', $failures);
assertSame____('Get started', $result['cta_label'], 'Scenario 2: a complete button label should pass through', $failures);
assertSame____('index.php?route=product/category&path=20', $result['cta_url'], 'Scenario 2: a complete button url should pass through', $failures);

// 3. A label with no url (or vice versa) normalizes to "no button" rather
//    than rendering a dead or unlabeled link.
$result = $provider->build([], ['title' => 'Title only', 'cta_label' => 'Shop now']);

assertSame____('Title only', $result['title'], 'Scenario 3: title should still pass through with a half-set button', $failures);
assertSame____('', $result['cta_label'], 'Scenario 3: a label with no url should normalize to no button (empty label)', $failures);
assertSame____('', $result['cta_url'], 'Scenario 3: a label with no url should normalize to no button (empty url)', $failures);

$result = $provider->build([], ['cta_url' => 'index.php?route=product/special']);

assertSame____('', $result['cta_label'], 'Scenario 3: a url with no label should normalize to no button (empty label)', $failures);
assertSame____('', $result['cta_url'], 'Scenario 3: a url with no label should normalize to no button (empty url)', $failures);

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo "PASS: CtaBlockProvider smoke test (3 scenarios)\n";
exit(0);
