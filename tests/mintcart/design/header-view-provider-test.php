<?php
/**
 * Standalone smoke test for MintCart\Design\HeaderViewProvider.
 *
 * No PHPUnit/Composer in the repo yet, same as every other MintCart smoke
 * test. HeaderViewProvider has no OpenCart dependency by design, so this
 * runs entirely against plain arrays.
 *
 * Run: php tests/mintcart/design/header-view-provider-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/design/headerviewprovider.php';

use MintCart\Design\HeaderViewProvider;

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

$provider = new HeaderViewProvider();

// 1. No arguments -> every section defaults to empty/safe, never null/error.
$view = $provider->build();

assertSame('', $view['branding']['name'], 'defaults: branding name empty string', $failures);
assertSame(null, $view['branding']['logo'], 'defaults: branding logo null', $failures);
assertSame('', $view['branding']['home'], 'defaults: branding home empty string', $failures);
assertSame(false, $view['account']['logged'], 'defaults: account logged false', $failures);
assertSame(null, $view['account']['first_name'], 'defaults: account first_name null', $failures);
assertSame(null, $view['account']['wishlist_count'], 'defaults: account wishlist_count null', $failures);
assertSame(null, $view['contacts']['phone'], 'defaults: contacts phone null', $failures);

// 2. Branding passes through as given (already-resolved OpenCart config values).
$view = $provider->build([
    'name' => 'Mint Store',
    'logo' => 'image/catalog/logo.png',
    'home' => 'index.php?route=common/home',
]);

assertSame('Mint Store', $view['branding']['name'], 'branding: name passes through', $failures);
assertSame('image/catalog/logo.png', $view['branding']['logo'], 'branding: logo passes through', $failures);
assertSame('index.php?route=common/home', $view['branding']['home'], 'branding: home passes through', $failures);

// 3. Account: logged state, first name and wishlist count pass through;
// wishlist_count is cast to int.
$view = $provider->build([], ['logged' => true, 'first_name' => 'Jane', 'wishlist_count' => '3']);

assertTrue($view['account']['logged'], 'account: logged true passes through', $failures);
assertSame('Jane', $view['account']['first_name'], 'account: first_name passes through', $failures);
assertSame(3, $view['account']['wishlist_count'], 'account: wishlist_count cast to int', $failures);

// 4. Contacts pass through as given (mirrors FooterViewProvider's contacts).
$view = $provider->build([], [], ['phone' => '+1 555 0100']);
assertSame('+1 555 0100', $view['contacts']['phone'], 'contacts: phone passes through', $failures);

// 5. Links: every documented key is always present (null when not supplied);
// an unrecognized key is dropped, never exposed to a theme.
$view = $provider->build([], [], [], [
    'account' => 'index.php?route=account/account',
    'checkout' => 'index.php?route=checkout/checkout',
    'not_a_real_link' => 'index.php?route=malicious/thing',
]);

assertSame(count(['account', 'login', 'register', 'order', 'transaction', 'download', 'logout', 'wishlist', 'shopping_cart', 'checkout', 'contact']), count($view['links']), 'links: every documented key present', $failures);
assertSame('index.php?route=account/account', $view['links']['account'], 'links: account passes through', $failures);
assertSame('index.php?route=checkout/checkout', $view['links']['checkout'], 'links: checkout passes through', $failures);
assertSame(null, $view['links']['login'], 'links: unsupplied key defaults to null', $failures);
assertTrue(!array_key_exists('not_a_real_link', $view['links']), 'links: unrecognized key dropped', $failures);

// 6. Widgets: pre-rendered HTML fragments pass through untouched; an
// unrecognized key is dropped, same rule as links.
$view = $provider->build([], [], [], [], [
    'menu' => '<ul id="menu"><li>Category</li></ul>',
    'cart' => '<div id="cart-total">0 item(s)</div>',
    'not_a_widget' => '<script>evil()</script>',
]);

assertSame(count(['menu', 'search', 'cart', 'language', 'currency']), count($view['widgets']), 'widgets: every documented key present', $failures);
assertSame('<ul id="menu"><li>Category</li></ul>', $view['widgets']['menu'], 'widgets: menu HTML passes through untouched', $failures);
assertSame('<div id="cart-total">0 item(s)</div>', $view['widgets']['cart'], 'widgets: cart HTML passes through untouched', $failures);
assertSame(null, $view['widgets']['search'], 'widgets: unsupplied key defaults to null', $failures);
assertTrue(!array_key_exists('not_a_widget', $view['widgets']), 'widgets: unrecognized key dropped', $failures);

// 7. An empty-string value is normalized to null, same rule FooterViewProvider
// applies to its own optional fields (an empty widget fragment is "no
// widget", not a truthy empty string a theme would render a wrapper for).
$view = $provider->build([], [], [], ['login' => ''], ['search' => '']);
assertSame(null, $view['links']['login'], 'links: empty string normalized to null', $failures);
assertSame(null, $view['widgets']['search'], 'widgets: empty string normalized to null', $failures);

// 8. Announcement: absent/blank text -> null; text is trimmed; only http(s)
// or site-relative urls survive (no javascript:, no protocol-relative).
assertSame(null, $provider->build()['announcement'], 'announcement: default null', $failures);
assertSame(null, $provider->build([], [], [], [], [], ['text' => '   ', 'url' => 'https://x.test'])['announcement'], 'announcement: blank text -> null', $failures);

$view = $provider->build([], [], [], [], [], ['text' => ' Free shipping over $50 ', 'url' => 'https://shop.test/sale']);
assertSame(['text' => 'Free shipping over $50', 'url' => 'https://shop.test/sale'], $view['announcement'], 'announcement: text trimmed, https url kept', $failures);

$view = $provider->build([], [], [], [], [], ['text' => 'Sale', 'url' => '/index.php?route=product/special']);
assertSame('/index.php?route=product/special', $view['announcement']['url'], 'announcement: relative url kept', $failures);

foreach (['javascript:alert(1)', '//evil.test', 'ftp://x.test'] as $bad) {
    $view = $provider->build([], [], [], [], [], ['text' => 'Sale', 'url' => $bad]);
    assertSame(null, $view['announcement']['url'], 'announcement: unsafe url dropped (' . $bad . ')', $failures);
}

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo 'PASS: HeaderViewProvider smoke test (' . 8 . " scenarios)\n";
exit(0);
