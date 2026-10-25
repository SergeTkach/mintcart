<?php
/**
 * Standalone smoke test for MintCart\Seo\SitemapUrlMap and the sitemap's
 * hreflang alternates (SitemapBuilder).
 *
 * Run: php tests/mintcart/seo/sitemap-url-map-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/seo/languageprefix.php';
require __DIR__ . '/../../../upload/system/library/mintcart/seo/sitemapurlmap.php';
require __DIR__ . '/../../../upload/system/library/mintcart/seo/sitemapbuilder.php';

use MintCart\Seo\LanguagePrefix;
use MintCart\Seo\SitemapBuilder;
use MintCart\Seo\SitemapUrlMap;

$failures = [];
$check = function ($ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
    }
};

$map = new SitemapUrlMap([
    'en-gb' => ['product_id=5' => 'red-shoes', 'category_id=1' => 'shoes', 'category_id=2' => 'red'],
    'uk-ua' => ['product_id=5' => 'chervoni-vzuttya', 'category_id=1' => 'vzuttya'],
], 'http://shop.test', new LanguagePrefix(['en-gb', 'uk-ua'], 'en-gb'));

$check($map->entity('en-gb', 'product/product', 'product_id', 5) === 'http://shop.test/red-shoes', 'default language: keyword at the root');
$check($map->entity('uk-ua', 'product/product', 'product_id', 5) === 'http://shop.test/uk/chervoni-vzuttya', 'other language: folder + its own keyword');
$check($map->entity('uk-ua', 'information/information', 'information_id', 9) === 'http://shop.test/uk/index.php?route=information/information&information_id=9', 'no keyword: plain link with the folder');
$check($map->category('en-gb', '1_2') === 'http://shop.test/shoes/red', 'category path joins keywords');
$check($map->category('uk-ua', '1_2') === 'http://shop.test/uk/index.php?route=product/category&path=1_2', 'one missing keyword makes the whole path plain');

$xml = (new SitemapBuilder())->urlset([[
    'loc' => 'http://shop.test/red-shoes',
    'alternates' => [
        ['hreflang' => 'en-gb', 'href' => 'http://shop.test/red-shoes'],
        ['hreflang' => 'uk-ua', 'href' => 'http://shop.test/uk/chervoni-vzuttya'],
        ['hreflang' => 'uk-ua', 'href' => 'http://dup.test/'],
        ['hreflang' => 'x-default', 'href' => 'http://shop.test/red-shoes'],
    ],
]]);

$check(strpos($xml, 'xmlns:xhtml="http://www.w3.org/1999/xhtml"') !== false, 'xhtml namespace declared');
$check(substr_count($xml, '<xhtml:link') === 3, 'alternates written, repeated hreflang dropped');
$check(strpos($xml, '<xhtml:link rel="alternate" hreflang="uk-ua" href="http://shop.test/uk/chervoni-vzuttya" />') !== false, 'alternate markup');
$check(strpos((new SitemapBuilder())->urlset([['loc' => 'http://a.test/']]), 'xhtml') === false, 'no namespace without alternates');
$check(strpos($xml, 'xmlns:image') === false, 'no image namespace without images');

if ($failures) {
    echo implode("\n", $failures), "\n";
    exit(1);
}
echo "OK\n";
