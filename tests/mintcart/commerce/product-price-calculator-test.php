<?php
/**
 * Standalone smoke test for MintCart\Commerce\ProductPriceCalculator.
 *
 * The calculator must price exactly like OpenCart 3.0.5.1's cart
 * (system/library/cart/cart.php), so the product page never shows a total
 * the cart then disagrees with.
 *
 * Run: php tests/mintcart/commerce/product-price-calculator-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/commerce/productpricecalculator.php';

use MintCart\Commerce\ProductPriceCalculator;

$failures = [];

function assertSame($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

$calc = new ProductPriceCalculator();

// Discount rows in the cart's ORDER BY: quantity DESC, priority ASC, price ASC.
$tiers = [
    ['quantity' => '30', 'price' => '66.0000'],
    ['quantity' => '20', 'price' => '77.0000'],
    ['quantity' => '10', 'price' => '88.0000'],
];

// --- unitPrice() ---

// 1. Below every tier: base price.
assertSame(['regular' => 100.0, 'charged' => 100.0], $calc->unitPrice(100.0, $tiers, null, 0.0, 9), 'below tiers -> base', $failures);

// 2. Exactly on a tier boundary: that tier.
assertSame(88.0, $calc->unitPrice(100.0, $tiers, null, 0.0, 10)['charged'], 'quantity == tier -> tier price', $failures);

// 3. Between tiers: the highest tier not above the quantity.
assertSame(77.0, $calc->unitPrice(100.0, $tiers, null, 0.0, 25)['charged'], 'between tiers -> highest reached', $failures);

// 4. Special wins even when a tier is cheaper (stock cart behavior).
assertSame(90.0, $calc->unitPrice(100.0, $tiers, 90.0, 0.0, 30)['charged'], 'special overrides a cheaper tier', $failures);

// 5. Options are added after the special; regular = base + options.
assertSame(['regular' => 110.0, 'charged' => 100.0], $calc->unitPrice(100.0, [], 90.0, 10.0, 1), 'options on top of special', $failures);

// 6. A quantity 0 row matches every quantity (the cart applies it too).
assertSame(95.0, $calc->unitPrice(100.0, [['quantity' => '0', 'price' => '95']], null, 0.0, 1)['charged'], 'quantity 0 row applies', $failures);

// 7. Same quantity, two rows: the first one (priority, then price) wins.
assertSame(80.0, $calc->unitPrice(100.0, [['quantity' => '5', 'price' => '80'], ['quantity' => '5', 'price' => '70']], null, 0.0, 5)['charged'], 'first row of equal quantity wins', $failures);

// --- optionPrice() ---

$options = [
    ['product_option_id' => 1, 'type' => 'radio', 'product_option_value' => [
        ['product_option_value_id' => 11, 'price' => '10.0000', 'price_prefix' => '+'],
        ['product_option_value_id' => 12, 'price' => '4.0000', 'price_prefix' => '-'],
    ]],
    ['product_option_id' => 2, 'type' => 'checkbox', 'product_option_value' => [
        ['product_option_value_id' => 21, 'price' => '3.0000', 'price_prefix' => '+'],
        ['product_option_value_id' => 22, 'price' => '5.0000', 'price_prefix' => '+'],
    ]],
    ['product_option_id' => 3, 'type' => 'select', 'product_option_value' => [
        ['product_option_value_id' => 31, 'price' => '2.0000', 'price_prefix' => '+'],
    ]],
    ['product_option_id' => 4, 'type' => 'text', 'product_option_value' => []],
];

// 8. Nothing selected: 0.
assertSame(0.0, $calc->optionPrice($options, []), 'no options -> 0', $failures);

// 9. '+' and '-' prefixes.
assertSame(10.0, $calc->optionPrice($options, [1 => '11']), 'radio + prefix', $failures);
assertSame(-4.0, $calc->optionPrice($options, [1 => '12']), 'radio - prefix', $failures);

// 10. Every checked checkbox value counts.
assertSame(8.0, $calc->optionPrice($options, [2 => ['21', '22']]), 'all checked checkboxes', $failures);

// 11. Mixed selection, text option ignored.
assertSame(15.0, $calc->optionPrice($options, [1 => '11', 2 => ['21'], 3 => '31', 4 => 'hello']), 'mixed selection', $failures);

// 12. Malformed input (array for radio, scalar for checkbox, unknown ids) adds nothing.
assertSame(0.0, $calc->optionPrice($options, [1 => ['11'], 2 => '21', 3 => '999', 9 => '11']), 'malformed input ignored', $failures);

if ($failures) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: $failure\n");
    }
    exit(1);
}

echo "ProductPriceCalculator: all tests passed\n";
