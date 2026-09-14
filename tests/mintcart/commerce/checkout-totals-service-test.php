<?php
/**
 * Standalone smoke test for MintCart\Commerce\CheckoutTotalsService.
 *
 * No PHPUnit/Composer in the repo yet, same as cart-service-test.php.
 * CheckoutTotalsService has no OpenCart dependency by design (the
 * caller-supplied $formatMoney callback stands in for
 * $this->currency->format() here), so this runs entirely against plain
 * arrays.
 *
 * Run: php tests/mintcart/commerce/checkout-totals-service-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/commerce/checkouttotalsservice.php';

use MintCart\Commerce\CheckoutTotalsService;

$failures = [];

function assertSameCheckoutTotals($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

$service = new CheckoutTotalsService();
$format = fn (float $value): string => '$' . number_format($value, 2);

// 1. Empty totals list normalizes to an empty list.
assertSameCheckoutTotals([], $service->normalize([], $format), 'normalize: no raw totals is an empty list', $failures);

// 2. A single raw total line becomes a {code, title, text} row, with the
//    raw numeric value run through the formatter and dropped from the
//    public shape.
$lines = $service->normalize(
    [['code' => 'sub_total', 'title' => 'Sub-Total', 'value' => 49.5, 'sort_order' => 1]],
    $format
);
assertSameCheckoutTotals(
    [['code' => 'sub_total', 'title' => 'Sub-Total', 'text' => '$49.50']],
    $lines,
    'normalize: one raw line becomes one formatted display row',
    $failures
);

// 3. Order is preserved exactly as given (TotalsAdapter's job, not this
//    class's) - a deliberately "wrong" input order comes back unchanged.
$lines = $service->normalize(
    [
        ['code' => 'total', 'title' => 'Total', 'value' => 100.0, 'sort_order' => 9],
        ['code' => 'sub_total', 'title' => 'Sub-Total', 'value' => 100.0, 'sort_order' => 1],
    ],
    $format
);
assertSameCheckoutTotals(['total', 'sub_total'], array_column($lines, 'code'), 'normalize: does not re-sort - input order passes through as-is', $failures);

// 4. Multiple lines, e.g. sub-total + shipping + tax + total, all format
//    independently.
$lines = $service->normalize(
    [
        ['code' => 'sub_total', 'title' => 'Sub-Total', 'value' => 100.0, 'sort_order' => 1],
        ['code' => 'shipping', 'title' => 'Flat Shipping Rate', 'value' => 5.0, 'sort_order' => 3],
        ['code' => 'tax', 'title' => 'VAT (20%)', 'value' => 21.0, 'sort_order' => 8],
        ['code' => 'total', 'title' => 'Total', 'value' => 126.0, 'sort_order' => 9],
    ],
    $format
);
assertSameCheckoutTotals(
    ['$100.00', '$5.00', '$21.00', '$126.00'],
    array_column($lines, 'text'),
    'normalize: each line is formatted independently via the same callback',
    $failures
);
assertSameCheckoutTotals(
    ['Sub-Total', 'Flat Shipping Rate', 'VAT (20%)', 'Total'],
    array_column($lines, 'title'),
    'normalize: titles pass through unchanged',
    $failures
);

if ($failures) {
    fwrite(STDERR, count($failures) . " failure(s):\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "- {$failure}\n");
    }
    exit(1);
}

echo "PASS: CheckoutTotalsService smoke test (4 scenarios)\n";
exit(0);
