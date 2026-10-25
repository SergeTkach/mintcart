<?php
/**
 * Standalone smoke test for MintCart\Commerce\CheckoutService.
 *
 * CheckoutService has no OpenCart dependency, so this runs against plain
 * arrays shaped like OpenCart's own session/cart/model data.
 *
 * The buildOrderData() scenarios pin the exact `$order_data` array
 * `checkout/confirm.php` used to assemble inline (it now delegates here via
 * CheckoutOrderAdapter) - every key and value below is what that stock code
 * produced for the same inputs, so a drift from stock order shape fails here.
 *
 * Run: php tests/mintcart/commerce/checkout-service-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/commerce/phonemask.php';
require __DIR__ . '/../../../upload/system/library/mintcart/commerce/checkoutservice.php';

use MintCart\Commerce\CheckoutService;

$failures = [];

function assertSameCheckoutService($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

$service = new CheckoutService();

// --- canCheckout() ---------------------------------------------------------

$row = fn ($id, $qty, $min) => ['product_id' => $id, 'quantity' => $qty, 'minimum' => $min];

assertSameCheckoutService(false, $service->canCheckout([], false, true, false), 'canCheckout: empty cart', $failures);
assertSameCheckoutService(true, $service->canCheckout([], true, true, false), 'canCheckout: vouchers only', $failures);
assertSameCheckoutService(false, $service->canCheckout([$row(1, 1, 1)], false, false, false), 'canCheckout: out of stock, no backorders', $failures);
assertSameCheckoutService(true, $service->canCheckout([$row(1, 1, 1)], false, false, true), 'canCheckout: out of stock, backorders allowed', $failures);
assertSameCheckoutService(false, $service->canCheckout([$row(1, 1, 2)], false, true, false), 'canCheckout: below minimum', $failures);
// Two option-variant rows of the same product count together toward its minimum.
assertSameCheckoutService(true, $service->canCheckout([$row(1, 1, 2), $row(1, 1, 2)], false, true, false), 'canCheckout: minimum summed across rows', $failures);

// --- validateContact() -----------------------------------------------------

$contact = ['firstname' => 'Олена', 'lastname' => 'Коваль', 'email' => 'olena@example.com', 'telephone' => '+380501234567'];

assertSameCheckoutService([], $service->validateContact($contact), 'validateContact: valid (multibyte names)', $failures);
assertSameCheckoutService(
    ['firstname' => 'error_firstname', 'lastname' => 'error_lastname', 'email' => 'error_email', 'telephone' => 'error_telephone'],
    $service->validateContact(['firstname' => '', 'lastname' => str_repeat('я', 33), 'email' => 'nope', 'telephone' => '12']),
    'validateContact: every field invalid, stock guest.php limits',
    $failures
);

// --- validateAddress() -----------------------------------------------------

$address = ['address_1' => 'вул. Хрещатик, 1', 'city' => 'Київ', 'postcode' => '', 'country_id' => '220', 'zone_id' => '3490'];
$ukraine = ['country_id' => 220, 'postcode_required' => 0];

assertSameCheckoutService([], $service->validateAddress($address, $ukraine, [3490, 3491]), 'validateAddress: valid', $failures);
assertSameCheckoutService(
    ['postcode' => 'error_postcode'],
    $service->validateAddress($address, ['postcode_required' => 1], [3490]),
    'validateAddress: postcode required by country',
    $failures
);
assertSameCheckoutService(
    ['country_id' => 'error_country', 'zone_id' => 'error_zone'],
    $service->validateAddress(['address_1' => 'abc', 'city' => 'ab', 'zone_id' => ''], null, []),
    'validateAddress: no country, no zone',
    $failures
);
// zone_id 0 ("None") is only valid for a country without zones.
assertSameCheckoutService(['zone_id' => 'error_zone'], $service->validateAddress(['zone_id' => '0'] + $address, $ukraine, [3490]), 'validateAddress: zone 0 rejected when country has zones', $failures);
assertSameCheckoutService([], $service->validateAddress(['zone_id' => '0'] + $address, $ukraine, []), 'validateAddress: zone 0 accepted for zoneless country', $failures);

// --- buildAddress() --------------------------------------------------------

$built = $service->buildAddress(
    $contact,
    $address,
    ['name' => 'Ukraine', 'iso_code_2' => 'UA', 'iso_code_3' => 'UKR', 'address_format' => ''],
    ['name' => 'Kyiv', 'code' => 'KY']
);

assertSameCheckoutService(
    [
        'firstname' => 'Олена', 'lastname' => 'Коваль', 'company' => '', 'address_1' => 'вул. Хрещатик, 1', 'address_2' => '',
        'postcode' => '', 'city' => 'Київ', 'country_id' => 220, 'zone_id' => 3490, 'country' => 'Ukraine',
        'iso_code_2' => 'UA', 'iso_code_3' => 'UKR', 'address_format' => '', 'zone' => 'Kyiv', 'zone_code' => 'KY',
        'custom_field' => [],
    ],
    $built,
    'buildAddress: stock session address shape, name from contact',
    $failures
);

// --- pickShippingMethod() / pickPaymentMethod() -----------------------------

$shipping = [
    'broken' => ['title' => 'Broken', 'quote' => [], 'sort_order' => 0, 'error' => 'No rates'],
    'flat'   => ['title' => 'Flat', 'quote' => ['flat' => ['code' => 'flat.flat', 'title' => 'Flat Rate']], 'sort_order' => 1, 'error' => false],
    'pickup' => ['title' => 'Pickup', 'quote' => ['pickup' => ['code' => 'pickup.pickup', 'title' => 'Pickup']], 'sort_order' => 2, 'error' => false],
];

assertSameCheckoutService('pickup.pickup', $service->pickShippingMethod('pickup.pickup', $shipping)['id'], 'pickShippingMethod: keeps requested', $failures);
assertSameCheckoutService('flat.flat', $service->pickShippingMethod('gone.gone', $shipping)['id'], 'pickShippingMethod: falls back to first valid, skipping errored', $failures);
assertSameCheckoutService(null, $service->pickShippingMethod('', []), 'pickShippingMethod: nothing available', $failures);

$payment = ['cod' => ['code' => 'cod', 'title' => 'COD'], 'bank_transfer' => ['code' => 'bank_transfer', 'title' => 'Bank']];

assertSameCheckoutService('bank_transfer', $service->pickPaymentMethod('bank_transfer', $payment)['code'], 'pickPaymentMethod: keeps requested', $failures);
assertSameCheckoutService('cod', $service->pickPaymentMethod('', $payment)['code'], 'pickPaymentMethod: falls back to first', $failures);
assertSameCheckoutService(null, $service->pickPaymentMethod('cod', []), 'pickPaymentMethod: nothing available', $failures);

// --- buildOrderData() ------------------------------------------------------

$ctx = [
    'store'             => ['invoice_prefix' => 'INV-2026-00', 'store_id' => 0, 'store_name' => 'Mint Shop', 'store_url' => 'http://shop.test/'],
    'customer'          => ['customer_id' => 0, 'customer_group_id' => 1] + $contact + ['custom_field' => []],
    'payment_address'   => $built,
    'payment_method'    => ['code' => 'cod', 'title' => 'Cash On Delivery', 'terms' => '', 'sort_order' => 1],
    'shipping_required' => true,
    'shipping_address'  => $built,
    'shipping_method'   => ['code' => 'flat.flat', 'title' => 'Flat Rate', 'cost' => 5.0],
    'products'          => [[
        'product_id' => 42, 'name' => 'Mug', 'model' => 'M-1', 'download' => [], 'quantity' => 2, 'subtract' => 1,
        'price' => 10.0, 'total' => 20.0, 'tax_class_id' => 9, 'reward' => 0,
        'option' => [[
            'product_option_id' => 1, 'product_option_value_id' => 2, 'option_id' => 3, 'option_value_id' => 4,
            'name' => 'Colour', 'value' => 'Green', 'type' => 'select', 'price' => 0,
        ]],
    ]],
    'vouchers'          => [[
        'description' => 'Gift', 'to_name' => 'A', 'to_email' => 'a@example.com', 'from_name' => 'B',
        'from_email' => 'b@example.com', 'voucher_theme_id' => 1, 'message' => 'Hi', 'amount' => 25.0,
    ]],
    'comment'           => 'Call first',
    'totals'            => [['code' => 'total', 'title' => 'Total', 'value' => 70.0, 'sort_order' => 9]],
    'total'             => 70.0,
    'marketing'         => ['tracking' => '', 'affiliate_id' => 0, 'commission' => 0, 'marketing_id' => 0],
    'locale'            => ['language_id' => 1, 'currency_id' => 3, 'currency_code' => 'UAH', 'currency_value' => 1.0],
    'client'            => ['ip' => '127.0.0.1', 'forwarded_ip' => '', 'user_agent' => 'UA', 'accept_language' => 'uk'],
];

$taxOf = fn ($price, $taxClassId) => $taxClassId === 9 ? $price * 0.2 : 0.0;
$voucherCode = fn () => 'VCODE00001';

$order = $service->buildOrderData($ctx, $taxOf, $voucherCode);

$addressExpect = fn ($prefix) => [
    $prefix . '_firstname' => 'Олена', $prefix . '_lastname' => 'Коваль', $prefix . '_company' => '',
    $prefix . '_address_1' => 'вул. Хрещатик, 1', $prefix . '_address_2' => '', $prefix . '_city' => 'Київ',
    $prefix . '_postcode' => '', $prefix . '_zone' => 'Kyiv', $prefix . '_zone_id' => 3490,
    $prefix . '_country' => 'Ukraine', $prefix . '_country_id' => 220, $prefix . '_address_format' => '',
    $prefix . '_custom_field' => [],
];

$expected = [
    'totals' => $ctx['totals'], 'invoice_prefix' => 'INV-2026-00', 'store_id' => 0, 'store_name' => 'Mint Shop', 'store_url' => 'http://shop.test/',
    'customer_id' => 0, 'customer_group_id' => 1, 'firstname' => 'Олена', 'lastname' => 'Коваль',
    'email' => 'olena@example.com', 'telephone' => '+380501234567', 'custom_field' => [],
] + $addressExpect('payment') + [
    'payment_method' => 'Cash On Delivery', 'payment_code' => 'cod',
] + $addressExpect('shipping') + [
    'shipping_method' => 'Flat Rate', 'shipping_code' => 'flat.flat',
    'products' => [[
        'product_id' => 42, 'name' => 'Mug', 'model' => 'M-1',
        'option' => [[
            'product_option_id' => 1, 'product_option_value_id' => 2, 'option_id' => 3, 'option_value_id' => 4,
            'name' => 'Colour', 'value' => 'Green', 'type' => 'select',
        ]],
        'download' => [], 'quantity' => 2, 'subtract' => 1, 'price' => 10.0, 'total' => 20.0, 'tax' => 2.0, 'reward' => 0,
    ]],
    'vouchers' => [[
        'description' => 'Gift', 'code' => 'VCODE00001', 'to_name' => 'A', 'to_email' => 'a@example.com', 'from_name' => 'B',
        'from_email' => 'b@example.com', 'voucher_theme_id' => 1, 'message' => 'Hi', 'amount' => 25.0,
    ]],
    'comment' => 'Call first', 'total' => 70.0,
    'tracking' => '', 'affiliate_id' => 0, 'commission' => 0, 'marketing_id' => 0,
    'language_id' => 1, 'currency_id' => 3, 'currency_code' => 'UAH', 'currency_value' => 1.0,
    'ip' => '127.0.0.1', 'forwarded_ip' => '', 'user_agent' => 'UA', 'accept_language' => 'uk',
];

ksort($expected);
ksort($order);

assertSameCheckoutService($expected, $order, 'buildOrderData: shipping order matches stock confirm.php shape', $failures);

// Without shipping, stock confirm.php blanked every shipping_* key to '' (custom field to []).
$noShip = $service->buildOrderData(
    ['shipping_required' => false, 'shipping_address' => null, 'shipping_method' => null] + $ctx,
    $taxOf,
    $voucherCode
);

$blankShipping = array_fill_keys(array_keys($addressExpect('shipping')), '');
$blankShipping['shipping_custom_field'] = [];
$blankShipping['shipping_method'] = '';
$blankShipping['shipping_code'] = '';

$actualShipping = array_intersect_key($noShip, $blankShipping);
ksort($blankShipping);
ksort($actualShipping);

assertSameCheckoutService($blankShipping, $actualShipping, 'buildOrderData: no-shipping order blanks shipping_* like stock', $failures);
assertSameCheckoutService(count($order), count($noShip), 'buildOrderData: same key set with or without shipping', $failures);

// A missing payment method title/code becomes '' (stock isset() fallbacks).
$noPayment = $service->buildOrderData(['payment_method' => null] + $ctx, $taxOf, $voucherCode);
assertSameCheckoutService(['', ''], [$noPayment['payment_method'], $noPayment['payment_code']], 'buildOrderData: missing payment method blanks', $failures);

if ($failures) {
    fwrite(STDERR, "FAIL\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "OK checkout-service-test\n";
exit(0);
