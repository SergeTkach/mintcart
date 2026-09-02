<?php
/**
 * Standalone smoke test for MintCart\Blocks\NewsletterBlockProvider.
 *
 * Pure normalization logic, no OpenCart/DB dependency - exercised directly
 * against build()'s input/output contract, same style as
 * tests/mintcart/blocks/products-block-provider-test.php.
 *
 * Run: php tests/mintcart/blocks/newsletter-block-provider-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/blocks/newsletterblockprovider.php';

use MintCart\Blocks\NewsletterBlockProvider;

/** @var string[] $failures */
$failures = [];

function assertSame_____($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

$provider = new NewsletterBlockProvider();

// 1. Empty settings/content -> blank title/text, disabled form (no action
//    configured), method defaults to post.
$result = $provider->build();

assertSame_____('', $result['title'], 'Scenario 1: empty content should default title to an empty string', $failures);
assertSame_____('', $result['text'], 'Scenario 1: empty content should default text to an empty string', $failures);
assertSame_____(false, $result['enabled'], 'Scenario 1: no action setting should default enabled to false', $failures);
assertSame_____('', $result['action'], 'Scenario 1: no action setting should default to an empty string', $failures);
assertSame_____('post', $result['method'], 'Scenario 1: no method setting should default to post', $failures);

// 2. A real action makes the form enabled; content fields pass through.
$result = $provider->build(
    ['action' => 'index.php?route=extension/module/newsletter.subscribe', 'method' => 'GET'],
    ['title' => 'Stay in the loop', 'text' => 'Get updates and offers.', 'placeholder' => 'you@example.com', 'submit_label' => 'Subscribe']
);

assertSame_____('Stay in the loop', $result['title'], 'Scenario 2: content title should pass through', $failures);
assertSame_____('Get updates and offers.', $result['text'], 'Scenario 2: content text should pass through', $failures);
assertSame_____('you@example.com', $result['placeholder'], 'Scenario 2: content placeholder should pass through', $failures);
assertSame_____('Subscribe', $result['submit_label'], 'Scenario 2: content submit_label should pass through', $failures);
assertSame_____(true, $result['enabled'], 'Scenario 2: a real action should enable the form', $failures);
assertSame_____('index.php?route=extension/module/newsletter.subscribe', $result['action'], 'Scenario 2: action should pass through', $failures);
assertSame_____('get', $result['method'], 'Scenario 2: an uppercase method should normalize to lowercase', $failures);

// 3. A blank/whitespace-only action must not enable the form - same
//    "no dead-end form" rule FooterViewProvider::buildNewsletter() enforces.
$result = $provider->build(['action' => '   '], ['title' => 'Newsletter']);

assertSame_____(false, $result['enabled'], 'Scenario 3: a whitespace-only action should not enable the form', $failures);
assertSame_____('', $result['action'], 'Scenario 3: a whitespace-only action should normalize to an empty string', $failures);

// 4. An unrecognized method falls back to the default rather than
//    propagating garbage into the rendered <form method="...">.
$result = $provider->build(['action' => 'index.php?route=x', 'method' => 'delete']);

assertSame_____('post', $result['method'], 'Scenario 4: an unrecognized method should fall back to the default (post)', $failures);

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo "PASS: NewsletterBlockProvider smoke test (4 scenarios)\n";
exit(0);
