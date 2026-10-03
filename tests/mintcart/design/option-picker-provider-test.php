<?php

/**
 * Standalone smoke test for MintCart\Design\OptionPickerProvider.
 *
 * No PHPUnit/Composer in the repo yet, same as every other tests/mintcart
 * file. OptionPickerProvider has no OpenCart dependency by design, so this
 * runs entirely against plain arrays shaped like
 * ModelCatalogOption::getOptions()'s real return rows.
 *
 * Run: php tests/mintcart/design/option-picker-provider-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/design/optionpickerprovider.php';

use MintCart\Design\OptionPickerProvider;

$failures = [];

function assertSame($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

$provider = new OptionPickerProvider();

// 1. Empty result set.
$result = $provider->build([], 1, 20);
assertSame([], $result['items'], 'empty: items is an empty array', $failures);
assertSame(0, $result['total'], 'empty: total is 0', $failures);

// 2. Fewer rows than one page - everything comes back, total matches row count.
$rows = [
    ['option_id' => '5', 'name' => 'Size', 'type' => 'select'],
    ['option_id' => '6', 'name' => 'Color', 'type' => 'radio'],
];
$result = $provider->build($rows, 1, 20);
assertSame(2, $result['total'], 'one page: total counts every filtered row', $failures);
assertSame(2, count($result['items']), 'one page: both rows returned', $failures);
assertSame(5, $result['items'][0]['id'], 'one page: option_id cast to int and renamed to id', $failures);
assertSame('Size', $result['items'][0]['name'], 'one page: name passed through', $failures);
assertSame(null, $result['items'][0]['image'], 'one page: image is always null (options have no image of their own)', $failures);
assertSame('select', $result['items'][0]['type'], 'one page: type passed through for the first row', $failures);
assertSame('radio', $result['items'][1]['type'], 'one page: type passed through for the second row', $failures);

// 3. Paging: page 2 of a 5-row set with limit 2 returns rows 3-4, total still 5.
$rows = [
    ['option_id' => 1, 'name' => 'A', 'type' => 'text'],
    ['option_id' => 2, 'name' => 'B', 'type' => 'text'],
    ['option_id' => 3, 'name' => 'C', 'type' => 'text'],
    ['option_id' => 4, 'name' => 'D', 'type' => 'text'],
    ['option_id' => 5, 'name' => 'E', 'type' => 'text'],
];
$result = $provider->build($rows, 2, 2);
assertSame(5, $result['total'], 'paging: total reflects the full filtered set, not just this page', $failures);
assertSame(2, count($result['items']), 'paging: page 2 returns 2 items', $failures);
assertSame('C', $result['items'][0]['name'], 'paging: page 2 starts at the 3rd row', $failures);
assertSame('D', $result['items'][1]['name'], 'paging: page 2 ends at the 4th row', $failures);

// 4. Last (partial) page.
$result = $provider->build($rows, 3, 2);
assertSame(1, count($result['items']), 'last page: partial page returns only the remaining row', $failures);
assertSame('E', $result['items'][0]['name'], 'last page: correct row', $failures);

// 5. Option name is HTML-decoded and stripped, same normalization as
//    category/manufacturer (option names are free text via CKEditor-less
//    plain input, but can still carry escaped entities from legacy data).
$rows = [
    ['option_id' => 40, 'name' => 'Size &amp; Fit', 'type' => 'select'],
];
$result = $provider->build($rows, 1, 20);
assertSame('Size & Fit', $result['items'][0]['name'], 'decode: &amp; entity decoded to plain text', $failures);

// 6. page/limit below 1 are clamped rather than producing a negative
//    array_slice offset or a division-by-zero on the JS side's
//    Math.ceil(total / pageSize).
$rows = [
    ['option_id' => 1, 'name' => 'A', 'type' => 'text'],
    ['option_id' => 2, 'name' => 'B', 'type' => 'text'],
];
$result = $provider->build($rows, 0, 0);
assertSame(1, count($result['items']), 'clamping: page 0 and limit 0 are clamped to page 1 / limit 1', $failures);
assertSame('A', $result['items'][0]['name'], 'clamping: clamped page 1 starts at the first row', $failures);

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo 'PASS: OptionPickerProvider smoke test (6 scenarios)' . "\n";
exit(0);
