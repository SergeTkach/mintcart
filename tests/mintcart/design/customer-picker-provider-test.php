<?php

/**
 * Standalone smoke test for MintCart\Design\CustomerPickerProvider.
 *
 * No PHPUnit/Composer in the repo yet, same as every other tests/mintcart
 * file. CustomerPickerProvider has no OpenCart dependency by design, so
 * this runs entirely against plain arrays shaped like the controller's
 * already-paged rows (ModelCustomerCustomer::getCustomers()'s real return
 * rows) plus a separately-supplied filtered total
 * (ModelCustomerCustomer::getTotalCustomers()'s real return value) - unlike
 * CategoryPickerProvider/ManufacturerPickerProvider, this provider does not
 * page in PHP, so build() takes (rows, total) rather than (rows, page, limit).
 *
 * Run: php tests/mintcart/design/customer-picker-provider-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/design/customerpickerprovider.php';

use MintCart\Design\CustomerPickerProvider;

$failures = [];

function assertSame($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

$provider = new CustomerPickerProvider();

// 1. Empty result set.
$result = $provider->build([], 0);
assertSame([], $result['items'], 'empty: items is an empty array', $failures);
assertSame(0, $result['total'], 'empty: total is 0', $failures);

// 2. A single already-paged page: rows passed straight through, total
//    reflects the caller-supplied filtered count (which may be larger than
//    the page, unlike CategoryPickerProvider where total == count($rows)).
$rows = [
    ['customer_id' => '12', 'name' => 'Jane Doe'],
    ['customer_id' => '9', 'name' => 'John Smith'],
];
$result = $provider->build($rows, 47);
assertSame(47, $result['total'], 'paged: total is the caller-supplied filtered count, not count(rows)', $failures);
assertSame(2, count($result['items']), 'paged: both rows on this page are returned', $failures);
assertSame(12, $result['items'][0]['id'], 'paged: customer_id cast to int and renamed to id', $failures);
assertSame('Jane Doe', $result['items'][0]['name'], 'paged: name passed through', $failures);
assertSame(null, $result['items'][0]['image'], 'paged: image is always null - customers have no image concept', $failures);
assertSame(null, $result['items'][1]['image'], 'paged: image is always null for every row, not just the first', $failures);

// 3. No pagination math happens inside build() itself - a caller passing
//    more rows than a normal page size still gets every row back verbatim
//    (paging is the SQL query's job, upstream of this class).
$rows = [
    ['customer_id' => 1, 'name' => 'A'],
    ['customer_id' => 2, 'name' => 'B'],
    ['customer_id' => 3, 'name' => 'C'],
];
$result = $provider->build($rows, 3);
assertSame(3, count($result['items']), 'no re-paging: build() does not slice rows itself', $failures);
assertSame('C', $result['items'][2]['name'], 'no re-paging: last row present unmodified', $failures);

// 4. Name is HTML-decoded and stripped, same normalization every other
//    *PickerProvider applies (defensive - CONCAT(firstname, ' ', lastname)
//    is plain text in practice, but a stored name could still contain
//    entities from a copy-paste).
$rows = [['customer_id' => 40, 'name' => 'Marks &amp; Spencer']];
$result = $provider->build($rows, 1);
assertSame('Marks & Spencer', $result['items'][0]['name'], 'decode: &amp; entity decoded to plain text', $failures);

// 5. A negative total (should never happen from a real COUNT(*) query, but
//    build() shouldn't hand JS a negative number that would break
//    Math.ceil(total / pageSize) on the client side).
$result = $provider->build([], -1);
assertSame(0, $result['total'], 'clamping: a negative total is clamped to 0', $failures);

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo 'PASS: CustomerPickerProvider smoke test (5 scenarios)' . "\n";
exit(0);
