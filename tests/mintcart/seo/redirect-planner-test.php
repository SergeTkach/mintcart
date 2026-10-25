<?php
/**
 * Smoke test for MintCart\Seo\RedirectPlanner.
 *
 * Run: php tests/mintcart/seo/redirect-planner-test.php
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/seo/redirectplanner.php';

use MintCart\Seo\RedirectPlanner;

$failures = [];
$check = function ($ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
    }
};

$all = ['dirty' => true, 'slash' => true, 'page1' => true];
$none = ['dirty' => false, 'slash' => false, 'page1' => false];
$seo = 'http://shop.test/white-mug';

// dirty URL -> the SEO URL
$check(RedirectPlanner::plan('/index.php?route=product/product&product_id=5', $seo, $all) === $seo, 'a dirty URL is redirected');
$check(RedirectPlanner::plan('/index.php?route=product/product&product_id=5', $seo, $none) === null, 'switched off: no redirect');
// nested category path -> the product's own URL
$check(RedirectPlanner::plan('/kitchen/white-mug', $seo, $all) === $seo, 'a category path in a product URL is redirected');
// already canonical
$check(RedirectPlanner::plan('/white-mug', $seo, $all) === null, 'the canonical URL is served');
$check(RedirectPlanner::plan('/white-mug?utm_source=x&sort=p.price', $seo, $all) === null, 'other query parameters do not matter');
// trailing slash
$check(RedirectPlanner::plan('/white-mug/', $seo, $all) === $seo, 'a trailing slash is redirected');
$check(RedirectPlanner::plan('/white-mug/', $seo, ['dirty' => true, 'slash' => false, 'page1' => true]) === null, 'slash switched off');
// page=1
$check(RedirectPlanner::plan('/mugs?page=1', 'http://shop.test/mugs', $all) === 'http://shop.test/mugs', 'page=1 is redirected');
$check(RedirectPlanner::plan('/mugs?page=2', 'http://shop.test/mugs?page=2', $all) === null, 'page=2 is served');
$check(RedirectPlanner::plan('/mugs?page=11', 'http://shop.test/mugs?page=11', $all) === null, 'page=11 is not page=1');
// the generator found no keyword: leave it alone
$check(RedirectPlanner::plan('/index.php?route=account/order&order_id=3', 'http://shop.test/index.php?route=account/order&amp;order_id=3', $all) === null, 'no keyword: no redirect');
// language folder
$check(RedirectPlanner::plan('/index.php?route=product/product&product_id=5', 'http://shop.test/uk/white-mug', $all) === 'http://shop.test/uk/white-mug', 'language folder in the target');
$check(RedirectPlanner::plan('/uk/white-mug', 'http://shop.test/uk/white-mug', $all) === null, 'language folder: already canonical');
// root
$check(RedirectPlanner::plan('/', 'http://shop.test/', $all) === null, 'the root is served');
// case-sensitive paths (a keyword is lower case; an upper case request is a duplicate)
$check(RedirectPlanner::plan('/White-Mug', $seo, $all) === $seo, 'a different case is redirected');

$check(RedirectPlanner::skipsRoute('extension/payment/x/callback') && RedirectPlanner::skipsRoute('api/cart') && !RedirectPlanner::skipsRoute('product/product'), 'routes that are never redirected');
$check(RedirectPlanner::oldPath('/Old-Page/') === 'old-page', 'old path form');

if ($failures) {
    echo implode("\n", $failures), "\n";
    exit(1);
}

echo "OK redirect-planner-test\n";
