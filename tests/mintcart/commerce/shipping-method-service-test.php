<?php
/**
 * Standalone smoke test for MintCart\Commerce\ShippingMethodService.
 *
 * No PHPUnit/Composer in the repo yet, same as checkout-totals-service-test.php.
 * ShippingMethodService has no OpenCart dependency, so this runs entirely
 * against plain arrays shaped like
 * MintCart\Compatibility\OpenCart3\ShippingQuoteAdapter::calculate()'s
 * return value.
 *
 * Run: php tests/mintcart/commerce/shipping-method-service-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/commerce/shippingmethodservice.php';

use MintCart\Commerce\ShippingMethodService;

$failures = [];

function assertSameShippingMethodService($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

$service = new ShippingMethodService();

// 1. Empty method data normalizes to an empty list.
assertSameShippingMethodService([], $service->normalize([]), 'normalize: no methods is an empty list', $failures);

// 2. A single method with a single quote option becomes one
//    {method, options: [{id, title, text}]} entry, with `id` built as
//    "<code>.<quote_key>" - the exact shape shipping_method.php::save()
//    expects back in $this->request->post['shipping_method'].
$methods = $service->normalize([
    'flat' => [
        'title' => 'Flat Shipping Rate',
        'quote' => ['flat' => ['code' => 'flat.flat', 'title' => 'Flat Shipping Rate', 'cost' => 5.0, 'tax_class_id' => 0, 'text' => '$5.00']],
        'sort_order' => 1,
        'error' => false,
    ],
]);
assertSameShippingMethodService(
    [['method' => 'Flat Shipping Rate', 'options' => [['id' => 'flat.flat', 'title' => 'Flat Shipping Rate', 'text' => '$5.00']]]],
    $methods,
    'normalize: one method with one quote option',
    $failures
);

// 3. Order is preserved exactly as given (ShippingQuoteAdapter's job, not
//    this class's).
$methods = $service->normalize([
    'weight' => ['title' => 'Weight Based Shipping', 'quote' => ['weight' => ['code' => 'weight.weight', 'title' => 'Weight Based Shipping', 'cost' => 8.0, 'tax_class_id' => 0, 'text' => '$8.00']], 'sort_order' => 9, 'error' => false],
    'flat' => ['title' => 'Flat Shipping Rate', 'quote' => ['flat' => ['code' => 'flat.flat', 'title' => 'Flat Shipping Rate', 'cost' => 5.0, 'tax_class_id' => 0, 'text' => '$5.00']], 'sort_order' => 1, 'error' => false],
]);
assertSameShippingMethodService(['Weight Based Shipping', 'Flat Shipping Rate'], array_column($methods, 'method'), 'normalize: does not re-sort - input order passes through as-is', $failures);

// 4. A method offering multiple quote options (e.g. a table-rate
//    extension) produces multiple options under the same method entry,
//    each with its own "<code>.<quote_key>" id.
$methods = $service->normalize([
    'ups' => [
        'title' => 'UPS',
        'quote' => [
            'ground' => ['code' => 'ups.ground', 'title' => 'UPS Ground', 'cost' => 10.0, 'tax_class_id' => 0, 'text' => '$10.00'],
            'next_day' => ['code' => 'ups.next_day', 'title' => 'UPS Next Day Air', 'cost' => 35.0, 'tax_class_id' => 0, 'text' => '$35.00'],
        ],
        'sort_order' => 5,
        'error' => false,
    ],
]);
assertSameShippingMethodService(1, count($methods), 'normalize: multiple quote options stay under one method entry', $failures);
assertSameShippingMethodService(['ups.ground', 'ups.next_day'], array_column($methods[0]['options'], 'id'), 'normalize: each quote option gets its own "<code>.<quote_key>" id', $failures);

if ($failures) {
    fwrite(STDERR, count($failures) . " failure(s):\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "- {$failure}\n");
    }
    exit(1);
}

echo "PASS: ShippingMethodService smoke test (4 scenarios)\n";
exit(0);
