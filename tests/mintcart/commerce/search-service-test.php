<?php
/**
 * Standalone smoke test for MintCart\Commerce\SearchService.
 *
 * No PHPUnit/Composer in the repo yet, same as every other tests/mintcart
 * file. SearchService has no OpenCart dependency by design, so this runs
 * entirely against plain arrays shaped like ControllerMintcartSearch's
 * already-resolved rows (image already resized, price/special already
 * formatted or false).
 *
 * Run: php tests/mintcart/commerce/search-service-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/commerce/searchservice.php';

use MintCart\Commerce\SearchService;

$failures = [];

function assertSame($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

$service = new SearchService();

// 1. Empty query / no matches.
$result = $service->results([], 0);
assertSame([], $result['items'], 'empty: items is an empty array', $failures);
assertSame(0, $result['total'], 'empty: total is 0', $failures);

// 2. A page of rows, total larger than the page - a quick-suggestions
//    list only ever returns a handful of rows even when far more match.
$rows = [
    ['id' => '42', 'name' => 'Apple Cinema 30"', 'url' => 'index.php?route=product/product&product_id=42', 'image' => 'image/cache/apple-100x100.png', 'price' => '$100.00', 'special' => false],
    ['id' => '28', 'name' => 'Canon EOS 5D', 'url' => 'index.php?route=product/product&product_id=28', 'image' => null, 'price' => '$80.00', 'special' => '$70.00'],
];
$result = $service->results($rows, 137);
assertSame(137, $result['total'], 'paged: total is the caller-supplied filtered count, not count($rows)', $failures);
assertSame(2, count($result['items']), 'paged: both rows on this page returned', $failures);
assertSame(42, $result['items'][0]['id'], 'paged: id cast to int', $failures);
assertSame('Apple Cinema 30"', $result['items'][0]['name'], 'paged: name passed through', $failures);
assertSame('image/cache/apple-100x100.png', $result['items'][0]['image'], 'paged: a resolved image passes through', $failures);
assertSame(false, $result['items'][0]['special'], 'paged: special false (no special price) is preserved, not cast to null', $failures);
assertSame(null, $result['items'][1]['image'], 'paged: a product with no image resolves to null', $failures);
assertSame('$70.00', $result['items'][1]['special'], 'paged: a real special price passes through', $failures);

// 3. A row with price/special omitted entirely defaults to false, same
//    "false, not null" convention ProductViewProvider::build() uses.
$rows = [['id' => 1, 'name' => 'No Price Keys', 'url' => '#', 'image' => null]];
$result = $service->results($rows, 1);
assertSame(false, $result['items'][0]['price'], 'missing keys: price defaults to false when omitted', $failures);
assertSame(false, $result['items'][0]['special'], 'missing keys: special defaults to false when omitted', $failures);

// 4. Name is HTML-decoded and stripped, same normalization every other
//    *Provider/*Service in this codebase applies.
$rows = [['id' => 9, 'name' => 'Salt &amp; Pepper Set', 'url' => '#', 'image' => null]];
$result = $service->results($rows, 1);
assertSame('Salt & Pepper Set', $result['items'][0]['name'], 'decode: &amp; entity decoded to plain text', $failures);

// 5. A negative total (should never happen from a real COUNT(*) query, but
//    results() shouldn't hand JS a negative number) is clamped to 0.
$result = $service->results([], -3);
assertSame(0, $result['total'], 'clamping: a negative total is clamped to 0', $failures);

if ($failures) {
    fwrite(STDERR, count($failures) . " failure(s):\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "- {$failure}\n");
    }
    exit(1);
}

echo "PASS: SearchService smoke test (5 scenarios)\n";
exit(0);
