<?php
/**
 * Standalone smoke test for MintCart\Seo\SystemRoutes.
 *
 * No PHPUnit/Composer in the repo yet, same as every other tests/mintcart/
 * smoke test. SystemRoutes has no OpenCart dependency by design, so this
 * runs entirely against plain strings/arrays.
 *
 * Run: php tests/mintcart/seo/system-routes-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/seo/systemroutes.php';

use MintCart\Seo\SystemRoutes;

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

// 1. The whitelist matches the twelve system pages MINTCART_FUNCTIONAL_SCOPE.md
// §4 names explicitly, keyed by a short code.
assertSame(
    ['search', 'cart', 'checkout', 'contact', 'account', 'login', 'register', 'wishlist', 'compare', 'special', 'manufacturer', 'sitemap'],
    array_keys(SystemRoutes::ROUTES),
    'ROUTES: has exactly the twelve documented system-page codes, in order',
    $failures
);

// 2. Every route value is a real OpenCart route= string (controller/action
// shape), not e.g. a URL or a keyword.
foreach (SystemRoutes::ROUTES as $code => $route) {
    assertTrue(strpos($route, '/') !== false, "ROUTES: '{$code}' value looks like a route (contains '/')", $failures);
    assertTrue(strpos($route, ' ') === false, "ROUTES: '{$code}' value has no whitespace", $failures);
}

// 3. Route values are unique - two system pages must never collide on the
// same underlying OpenCart route.
assertSame(count(SystemRoutes::ROUTES), count(array_unique(SystemRoutes::ROUTES)), 'ROUTES: every route value is unique', $failures);

// 4. labelKey() produces a predictable, code-specific language key.
assertSame('entry_system_route_cart', SystemRoutes::labelKey('cart'), 'labelKey: builds entry_system_route_<code>', $failures);

// 5. isValidAlias(): blank is always valid (means "no alias set").
assertTrue(SystemRoutes::isValidAlias(''), 'isValidAlias: blank is valid', $failures);

// 6. isValidAlias(): lowercase letters/digits/hyphens/underscores are valid.
assertTrue(SystemRoutes::isValidAlias('cart'), 'isValidAlias: plain word is valid', $failures);
assertTrue(SystemRoutes::isValidAlias('my-cart_2'), 'isValidAlias: hyphen/underscore/digit mix is valid', $failures);

// 7. isValidAlias(): rejects characters that would break path-segment matching.
assertTrue(!SystemRoutes::isValidAlias('my cart'), 'isValidAlias: space rejected', $failures);
assertTrue(!SystemRoutes::isValidAlias('cart/extra'), 'isValidAlias: slash rejected', $failures);
assertTrue(!SystemRoutes::isValidAlias('Cart'), 'isValidAlias: uppercase rejected', $failures);
assertTrue(!SystemRoutes::isValidAlias('cärt'), 'isValidAlias: non-ASCII rejected', $failures);

// 8. isValidAlias(): 100-character boundary.
assertTrue(SystemRoutes::isValidAlias(str_repeat('a', 100)), 'isValidAlias: exactly 100 chars is valid', $failures);
assertTrue(!SystemRoutes::isValidAlias(str_repeat('a', 101)), 'isValidAlias: 101 chars is rejected', $failures);

if ($failures) {
    fwrite(STDERR, count($failures) . " failure(s):\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "- {$failure}\n");
    }
    exit(1);
}

echo "PASS: SystemRoutes smoke test (8 scenarios)\n";
exit(0);
