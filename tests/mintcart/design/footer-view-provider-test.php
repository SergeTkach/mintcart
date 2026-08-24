<?php
/**
 * Standalone smoke test for MintCart\Design\FooterViewProvider.
 *
 * No PHPUnit/Composer in the repo yet, same as every other MintCart smoke
 * test. FooterViewProvider has no OpenCart dependency by design, so this
 * runs entirely against plain arrays.
 *
 * Run: php tests/mintcart/design/footer-view-provider-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/design/footerviewprovider.php';

use MintCart\Design\FooterViewProvider;

$failures = [];

function assertTrue(bool $condition, string $message, array &$failures): void
{
    if (!$condition) {
        $failures[] = $message;
    }
}

function assertSame($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

$provider = new FooterViewProvider();

// 1. No arguments -> every section defaults to empty/disabled, never null/error.
$view = $provider->build();

assertSame([], $view['columns'], 'defaults: columns empty', $failures);
assertSame([null, null, null], array_values($view['contacts']), 'defaults: contacts all null', $failures);
assertSame([], $view['social'], 'defaults: social empty', $failures);
assertSame([], $view['payments'], 'defaults: payments empty', $failures);
assertTrue(!$view['newsletter']['enabled'], 'defaults: newsletter disabled', $failures);
assertSame(null, $view['newsletter']['action'], 'defaults: newsletter action null', $failures);
assertSame('', $view['powered'], 'defaults: powered empty string', $failures);

// 2. Columns: a link missing label/href is dropped; a column with no
// surviving links is dropped entirely (never renders an empty <ul>).
$view = $provider->build([
    ['title' => 'Information', 'links' => [
        ['label' => 'Contact Us', 'href' => 'index.php?route=information/contact'],
        ['label' => 'Delivery Information'], // no href - dropped
    ]],
    ['title' => 'Empty Column', 'links' => [
        ['href' => 'index.php?route=x'], // no label - dropped
    ]],
]);

assertSame(1, count($view['columns']), 'columns: empty column dropped, one survives', $failures);
assertSame('Information', $view['columns'][0]['title'], 'columns: surviving column title', $failures);
assertSame(1, count($view['columns'][0]['links']), 'columns: link missing href dropped', $failures);
assertSame('Contact Us', $view['columns'][0]['links'][0]['label'], 'columns: surviving link label', $failures);

// 3. Contacts pass through as given (already-resolved OpenCart config values).
$view = $provider->build([], [
    'phone' => '+1 555 0100',
    'email' => 'store@example.com',
    'address' => '1 Mint Street',
]);

assertSame('+1 555 0100', $view['contacts']['phone'], 'contacts: phone passes through', $failures);
assertSame('store@example.com', $view['contacts']['email'], 'contacts: email passes through', $failures);
assertSame('1 Mint Street', $view['contacts']['address'], 'contacts: address passes through', $failures);

// 4. Social link missing network/href is dropped; label defaults to
// ucfirst(network) when not supplied.
$view = $provider->build([], [], [
    ['network' => 'facebook', 'href' => 'https://facebook.com/store'],
    ['network' => 'twitter'], // no href - dropped
]);

assertSame(1, count($view['social']), 'social: link missing href dropped', $failures);
assertSame('Facebook', $view['social'][0]['label'], 'social: label defaults to ucfirst(network)', $failures);

// 5. Payment missing name is dropped; icon defaults to null.
$view = $provider->build([], [], [], [
    ['name' => 'Visa', 'icon' => 'catalog/payments/visa.svg'],
    ['icon' => 'catalog/payments/mystery.svg'], // no name - dropped
]);

assertSame(1, count($view['payments']), 'payments: entry missing name dropped', $failures);
assertSame('Visa', $view['payments'][0]['name'], 'payments: surviving name', $failures);
assertSame('catalog/payments/visa.svg', $view['payments'][0]['icon'], 'payments: icon passes through', $failures);

// 6. Newsletter only enables when both enabled=true AND a real action URL
// are supplied - no subscribe endpoint exists yet, so "enabled" alone
// (no action) must not render a form with nowhere to submit to.
$view = $provider->build([], [], [], [], ['enabled' => true]);
assertTrue(!$view['newsletter']['enabled'], 'newsletter: enabled=true without action stays disabled', $failures);

$view = $provider->build([], [], [], [], ['enabled' => true, 'action' => 'index.php?route=account/newsletter']);
assertTrue($view['newsletter']['enabled'], 'newsletter: enabled=true with action is enabled', $failures);
assertSame('index.php?route=account/newsletter', $view['newsletter']['action'], 'newsletter: action passes through', $failures);

// 7. Powered text passes through unchanged (caller already resolved the
// language string + store name, this class never touches i18n).
$view = $provider->build([], [], [], [], null, 'Powered By MintCart');
assertSame('Powered By MintCart', $view['powered'], 'powered: passes through unchanged', $failures);

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo 'PASS: FooterViewProvider smoke test (' . 7 . " scenarios)\n";
exit(0);
