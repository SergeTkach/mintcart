<?php

/**
 * Standalone smoke test for MintCart\Catalog\BulkProductUpdateValidator.
 *
 * No PHPUnit/Composer in the repo yet, same as every other tests/mintcart
 * file. BulkProductUpdateValidator has no OpenCart dependency by design,
 * so this runs entirely against plain strings/arrays.
 *
 * Run: php tests/mintcart/catalog/bulk-product-update-validator-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/catalog/bulkproductupdatevalidator.php';

use MintCart\Catalog\BulkProductUpdateValidator;

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

$validator = new BulkProductUpdateValidator();

// --- isValidField() ---

assertTrue($validator->isValidField('status'), 'isValidField: status is valid', $failures);
assertTrue($validator->isValidField('quantity'), 'isValidField: quantity is valid', $failures);
assertTrue($validator->isValidField('price'), 'isValidField: price is valid', $failures);
assertTrue($validator->isValidField('manufacturer_id'), 'isValidField: manufacturer_id is valid', $failures);
assertTrue($validator->isValidField('stock_status_id'), 'isValidField: stock_status_id is valid', $failures);
assertTrue($validator->isValidField('special_price'), 'isValidField: special_price is valid', $failures);
assertTrue(!$validator->isValidField('category_ids'), 'isValidField: category_ids is not (yet) a whitelisted field', $failures);
assertTrue(!$validator->isValidField(''), 'isValidField: blank is not valid', $failures);

// --- validateValue() - status ---

assertSame(null, $validator->validateValue('status', '0'), 'status: 0 is valid', $failures);
assertSame(null, $validator->validateValue('status', '1'), 'status: 1 is valid', $failures);
assertTrue($validator->validateValue('status', '2') !== null, 'status: 2 is rejected', $failures);
assertTrue($validator->validateValue('status', 'enabled') !== null, 'status: a non-numeric value is rejected', $failures);
assertTrue($validator->validateValue('status', '') !== null, 'status: blank is rejected', $failures);

// --- validateValue() - quantity ---

assertSame(null, $validator->validateValue('quantity', '0'), 'quantity: 0 is valid', $failures);
assertSame(null, $validator->validateValue('quantity', '250'), 'quantity: a positive whole number is valid', $failures);
assertTrue($validator->validateValue('quantity', '-5') !== null, 'quantity: a negative number is rejected', $failures);
assertTrue($validator->validateValue('quantity', '2.5') !== null, 'quantity: a decimal is rejected (must be a whole number)', $failures);
assertTrue($validator->validateValue('quantity', 'ten') !== null, 'quantity: a non-numeric value is rejected', $failures);
assertTrue($validator->validateValue('quantity', '') !== null, 'quantity: blank is rejected', $failures);

// --- validateValue() - price ---

assertSame(null, $validator->validateValue('price', '0'), 'price: 0 is valid', $failures);
assertSame(null, $validator->validateValue('price', '19.99'), 'price: a decimal is valid', $failures);
assertSame(null, $validator->validateValue('price', '100'), 'price: a whole number is valid', $failures);
assertTrue($validator->validateValue('price', '-1') !== null, 'price: a negative number is rejected', $failures);
assertTrue($validator->validateValue('price', 'free') !== null, 'price: a non-numeric value is rejected', $failures);
assertTrue($validator->validateValue('price', '') !== null, 'price: blank is rejected', $failures);

// --- validateValue() - manufacturer_id ---

assertSame(null, $validator->validateValue('manufacturer_id', '0'), 'manufacturer_id: 0 (no manufacturer) is valid', $failures);
assertSame(null, $validator->validateValue('manufacturer_id', '12'), 'manufacturer_id: a positive whole number is valid', $failures);
assertTrue($validator->validateValue('manufacturer_id', '-1') !== null, 'manufacturer_id: a negative number is rejected', $failures);
assertTrue($validator->validateValue('manufacturer_id', '2.5') !== null, 'manufacturer_id: a decimal is rejected (must be a whole number)', $failures);
assertTrue($validator->validateValue('manufacturer_id', 'acme') !== null, 'manufacturer_id: a non-numeric value is rejected', $failures);
assertTrue($validator->validateValue('manufacturer_id', '') !== null, 'manufacturer_id: blank is rejected', $failures);

// --- validateValue() - stock_status_id ---

assertSame(null, $validator->validateValue('stock_status_id', '0'), 'stock_status_id: 0 (none) is valid', $failures);
assertSame(null, $validator->validateValue('stock_status_id', '7'), 'stock_status_id: a positive whole number is valid', $failures);
assertTrue($validator->validateValue('stock_status_id', '-1') !== null, 'stock_status_id: a negative number is rejected', $failures);
assertTrue($validator->validateValue('stock_status_id', '2.5') !== null, 'stock_status_id: a decimal is rejected (must be a whole number)', $failures);
assertTrue($validator->validateValue('stock_status_id', 'instock') !== null, 'stock_status_id: a non-numeric value is rejected', $failures);
assertTrue($validator->validateValue('stock_status_id', '') !== null, 'stock_status_id: blank is rejected', $failures);

// --- validateValue() - special_price ---

assertSame(null, $validator->validateValue('special_price', ''), 'special_price: blank (remove special) is valid', $failures);
assertSame(null, $validator->validateValue('special_price', '0'), 'special_price: 0 is valid', $failures);
assertSame(null, $validator->validateValue('special_price', '9.99'), 'special_price: a decimal is valid', $failures);
assertTrue($validator->validateValue('special_price', '-1') !== null, 'special_price: a negative number is rejected', $failures);
assertTrue($validator->validateValue('special_price', 'free') !== null, 'special_price: a non-numeric value is rejected', $failures);

// --- validateValue() - unknown field, defensive ---

assertTrue($validator->validateValue('category_ids', '5') !== null, 'validateValue: an unwhitelisted field always errors, defensively', $failures);

// --- normalizeProductIds() ---

assertSame([], $validator->normalizeProductIds([]), 'normalizeProductIds: empty input returns empty list', $failures);
assertSame([42, 7], $validator->normalizeProductIds(['42', 7]), 'normalizeProductIds: casts strings to int, preserves order', $failures);
assertSame([5], $validator->normalizeProductIds(['5', '5', 5]), 'normalizeProductIds: de-duplicates', $failures);
assertSame([3], $validator->normalizeProductIds(['0', '-1', 'abc', '3']), 'normalizeProductIds: drops zero/negative/non-numeric ids', $failures);

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo "PASS: BulkProductUpdateValidator smoke test (46 scenarios)\n";
exit(0);
