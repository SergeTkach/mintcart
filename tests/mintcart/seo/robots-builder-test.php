<?php
/**
 * Standalone smoke test for MintCart\Seo\RobotsBuilder.
 *
 * No PHPUnit/Composer in the repo yet, same as every other tests/mintcart/
 * smoke test. RobotsBuilder has no OpenCart dependency by design, so this
 * runs entirely against plain strings.
 *
 * Run: php tests/mintcart/seo/robots-builder-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/seo/robotsbuilder.php';

use MintCart\Seo\RobotsBuilder;

$failures = [];

function assertSame($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

$builder = new RobotsBuilder();

// 1. An ordinary storefront route (category/product/manufacturer/home) is
//    index, follow by default.
assertSame(['index' => true, 'follow' => true], $builder->directives('product/category'), 'directives: an ordinary route is index+follow', $failures);
assertSame('index, follow', $builder->metaContent('product/category'), 'metaContent: an ordinary route formats as "index, follow"', $failures);

// 2. checkout/* routes are noindex, follow.
foreach (['checkout/cart', 'checkout/checkout', 'checkout/success', 'checkout/failure', 'checkout/guest', 'checkout/register', 'checkout/confirm'] as $route) {
    assertSame(['index' => false, 'follow' => true], $builder->directives($route), "directives: {$route} is noindex+follow", $failures);
}

// 3. account/* routes are noindex, follow.
foreach (['account/account', 'account/login', 'account/register', 'account/wishlist', 'account/order', 'account/order/info', 'account/download'] as $route) {
    assertSame(['index' => false, 'follow' => true], $builder->directives($route), "directives: {$route} is noindex+follow", $failures);
}

// 4. metaContent() formats a noindex route correctly.
assertSame('noindex, follow', $builder->metaContent('checkout/cart'), 'metaContent: checkout/cart formats as "noindex, follow"', $failures);

// 5. product/search with results is index, follow (a normal, valuable page).
assertSame(['index' => true, 'follow' => true], $builder->directives('product/search', true), 'directives: product/search with results is index+follow', $failures);

// 6. product/search with no results is noindex, follow (thin content, but
//    its own links are still worth following).
assertSame(['index' => false, 'follow' => true], $builder->directives('product/search', false), 'directives: product/search with no results is noindex+follow', $failures);

// 7. $hasResults is ignored for every route other than product/search.
assertSame(['index' => true, 'follow' => true], $builder->directives('product/category', false), 'directives: $hasResults is ignored outside product/search', $failures);
assertSame(['index' => false, 'follow' => true], $builder->directives('checkout/cart', false), 'directives: $hasResults does not change a NOINDEX_ROUTES entry', $failures);

// 8. NOINDEX_ROUTES has no duplicate entries.
assertSame(count(RobotsBuilder::NOINDEX_ROUTES), count(array_unique(RobotsBuilder::NOINDEX_ROUTES)), 'NOINDEX_ROUTES: every entry is unique', $failures);

// 9. Every NOINDEX_ROUTES value looks like a real route= string (contains
//    a "/", no whitespace) — a defensive shape check, not a live-controller
//    check (that was done by hand before writing this class, see the
//    class's own docblock).
foreach (RobotsBuilder::NOINDEX_ROUTES as $route) {
    assertSame(true, strpos($route, '/') !== false, "NOINDEX_ROUTES: '{$route}' looks like a route (contains '/')", $failures);
    assertSame(false, strpos($route, ' ') !== false, "NOINDEX_ROUTES: '{$route}' has no whitespace", $failures);
}

// 10. HAS_RESULTS_REGISTRY_KEY is the plain string constant
//     ControllerProductSearch::index()/ControllerCommonHeader::index()
//     bridge search.php's real "did this search match anything" signal
//     through - a defensive shape/regression check, not a live-registry
//     check (no \Registry exists in this standalone test, by design; see
//     the constant's own docblock and docs/developer/seo/robots.md).
assertSame(true, is_string(RobotsBuilder::HAS_RESULTS_REGISTRY_KEY), 'HAS_RESULTS_REGISTRY_KEY is a string', $failures);
assertSame(true, RobotsBuilder::HAS_RESULTS_REGISTRY_KEY !== '', 'HAS_RESULTS_REGISTRY_KEY is non-empty', $failures);

if ($failures) {
    fwrite(STDERR, count($failures) . " failure(s):\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "- {$failure}\n");
    }
    exit(1);
}

echo "PASS: RobotsBuilder smoke test (" . (10 + count(RobotsBuilder::NOINDEX_ROUTES) * 2) . " assertions)\n";
exit(0);
