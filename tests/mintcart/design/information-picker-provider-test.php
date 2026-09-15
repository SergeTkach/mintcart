<?php

/**
 * Standalone smoke test for MintCart\Design\InformationPickerProvider.
 *
 * No PHPUnit/Composer in the repo yet, same as every other tests/mintcart
 * file. InformationPickerProvider has no OpenCart dependency by design, so
 * this runs entirely against plain arrays shaped like
 * ModelCatalogInformation::getInformations()'s real return rows
 * (information_id, title - the model's query joins in title from
 * information_description, so it's always present).
 *
 * Run: php tests/mintcart/design/information-picker-provider-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/design/informationpickerprovider.php';

use MintCart\Design\InformationPickerProvider;

$failures = [];

function assertSame($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

$provider = new InformationPickerProvider();

// 1. Empty result set.
$result = $provider->build([], '', 1, 20);
assertSame([], $result['items'], 'empty: items is an empty array', $failures);
assertSame(0, $result['total'], 'empty: total is 0', $failures);

// 2. Fewer rows than one page, no filter - everything comes back, image is
//    always null (information pages have no image concept).
$rows = [
    ['information_id' => '3', 'title' => 'About Us'],
    ['information_id' => '5', 'title' => 'Delivery Information'],
];
$result = $provider->build($rows, '', 1, 20);
assertSame(2, $result['total'], 'one page: total counts every row', $failures);
assertSame(2, count($result['items']), 'one page: both rows returned', $failures);
assertSame(3, $result['items'][0]['id'], 'one page: information_id cast to int and renamed to id', $failures);
assertSame('About Us', $result['items'][0]['name'], 'one page: title renamed to name', $failures);
assertSame(null, $result['items'][0]['image'], 'one page: image is always null', $failures);

// 3. Name filtering - unlike category/manufacturer, this provider itself
//    filters, since ModelCatalogInformation::getInformations() has no
//    filter_name support to do it in SQL. Case-insensitive substring match.
$rows = [
    ['information_id' => 1, 'title' => 'About Us'],
    ['information_id' => 2, 'title' => 'Delivery Information'],
    ['information_id' => 3, 'title' => 'Privacy Policy'],
];
$result = $provider->build($rows, 'delivery', 1, 20);
assertSame(1, $result['total'], 'filter: only the matching row counts toward total', $failures);
assertSame('Delivery Information', $result['items'][0]['name'], 'filter: case-insensitive substring match', $failures);

$result = $provider->build($rows, 'INFO', 1, 20);
assertSame(1, $result['total'], 'filter: uppercase needle still matches a lowercase substring', $failures);
assertSame('Delivery Information', $result['items'][0]['name'], 'filter: matches anywhere in the title, not just the start', $failures);

$result = $provider->build($rows, 'nonexistent', 1, 20);
assertSame(0, $result['total'], 'filter: no match returns an empty result, not an error', $failures);
assertSame([], $result['items'], 'filter: no match returns an empty items array', $failures);

// 4. Paging happens after filtering, over the filtered set.
$rows = [
    ['information_id' => 1, 'title' => 'Page A'],
    ['information_id' => 2, 'title' => 'Page B'],
    ['information_id' => 3, 'title' => 'Page C'],
    ['information_id' => 4, 'title' => 'Page D'],
    ['information_id' => 5, 'title' => 'Page E'],
];
$result = $provider->build($rows, '', 2, 2);
assertSame(5, $result['total'], 'paging: total reflects the full set, not just this page', $failures);
assertSame(2, count($result['items']), 'paging: page 2 returns 2 items', $failures);
assertSame('Page C', $result['items'][0]['name'], 'paging: page 2 starts at the 3rd row', $failures);
assertSame('Page D', $result['items'][1]['name'], 'paging: page 2 ends at the 4th row', $failures);

// 5. Last (partial) page.
$result = $provider->build($rows, '', 3, 2);
assertSame(1, count($result['items']), 'last page: partial page returns only the remaining row', $failures);
assertSame('Page E', $result['items'][0]['name'], 'last page: correct row', $failures);

// 6. Title is HTML-decoded and stripped (defensive - titles are plain
//    text in practice, but could contain entities from a copy-paste).
$rows = [['information_id' => 9, 'title' => 'Terms &amp; Conditions']];
$result = $provider->build($rows, '', 1, 20);
assertSame('Terms & Conditions', $result['items'][0]['name'], 'decode: &amp; entity decoded to plain text', $failures);

// 7. page/limit below 1 are clamped rather than producing a negative
//    array_slice offset or a division-by-zero on the JS side's
//    Math.ceil(total / pageSize).
$rows = [
    ['information_id' => 1, 'title' => 'A'],
    ['information_id' => 2, 'title' => 'B'],
];
$result = $provider->build($rows, '', 0, 0);
assertSame(1, count($result['items']), 'clamping: page 0 and limit 0 are clamped to page 1 / limit 1', $failures);
assertSame('A', $result['items'][0]['name'], 'clamping: clamped page 1 starts at the first row', $failures);

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo 'PASS: InformationPickerProvider smoke test (7 scenarios)' . "\n";
exit(0);
