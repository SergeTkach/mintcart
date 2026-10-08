<?php

declare(strict_types=1);

namespace MintCart\Commerce;

/**
 * Unit price of a product for a given quantity and option selection, by the
 * exact rules OpenCart 3.0.5.1's cart applies (`system/library/cart/cart.php`
 * getProducts()), so the live price on the product page is the price the
 * cart will actually charge:
 *
 * 1. Base = the product's own `price`.
 * 2. Quantity discount: the first `product_discount` row (rows ordered
 *    quantity DESC, priority ASC, price ASC) whose `quantity` <= the TOTAL
 *    quantity of this product in the cart, including lines with other
 *    options - replaces the base. Rows with quantity 0/1 count too (the cart
 *    applies them; the stock product page ignores quantity 0 rows).
 * 3. Special: if one is active it replaces the price, even when a discount
 *    tier would be cheaper (stock OpenCart behavior, kept on purpose -
 *    showing "cheaper wins" here would disagree with the cart).
 * 4. Option prices (select/radio value, every checked checkbox value;
 *    `+`/`-` prefix) are added on top, after the special. Value stock is not
 *    considered - the cart does not consider it either.
 *
 * No OpenCart dependency: the caller loads the rows (same SQL filters as the
 * cart: customer group, date window) and applies tax/currency per unit.
 *
 * Contract: docs/developer/commerce/product-price.md
 */
final class ProductPriceCalculator
{
    private const PRICED_OPTION_TYPES = ['select', 'radio', 'checkbox'];

    /**
     * @param array<int, array{quantity: int|string, price: float|string}> $discounts
     *        Active rows for the customer group, ordered quantity DESC,
     *        priority ASC, price ASC (the cart's ORDER BY).
     * @return array{regular: float, charged: float}
     *         Unit prices before tax: `regular` = base + options (the
     *         undiscounted price), `charged` = what the cart charges -
     *         special, else discount tier, else base; plus options.
     */
    public function unitPrice(float $basePrice, array $discounts, ?float $special, float $optionPrice, int $totalQuantity): array
    {
        $price = $basePrice;

        foreach ($discounts as $discount) {
            if ((int)$discount['quantity'] <= $totalQuantity) {
                $price = (float)$discount['price'];
                break;
            }
        }

        if ($special !== null) {
            $price = $special;
        }

        return [
            'regular' => $basePrice + $optionPrice,
            'charged' => $price + $optionPrice,
        ];
    }

    /**
     * Sum of the selected option values' price modifiers.
     *
     * @param array<int, array> $productOptions OpenCart getProductOptions()
     *        rows (product_option_id, type, product_option_value[]).
     * @param array<int|string, mixed> $selected The posted `option` array:
     *        product_option_id => product_option_value_id (or a list of them
     *        for checkboxes).
     */
    public function optionPrice(array $productOptions, array $selected): float
    {
        $total = 0.0;

        foreach ($productOptions as $option) {
            $id = $option['product_option_id'];

            if (!isset($selected[$id]) || !in_array($option['type'], self::PRICED_OPTION_TYPES, true)) {
                continue;
            }

            if ($option['type'] === 'checkbox') {
                if (!is_array($selected[$id])) {
                    continue;
                }

                $chosen = array_map('intval', $selected[$id]);
            } else {
                if (is_array($selected[$id])) {
                    continue;
                }

                $chosen = [(int)$selected[$id]];
            }

            foreach ($option['product_option_value'] as $value) {
                if (!in_array((int)$value['product_option_value_id'], $chosen, true)) {
                    continue;
                }

                if ($value['price_prefix'] === '+') {
                    $total += (float)$value['price'];
                } elseif ($value['price_prefix'] === '-') {
                    $total -= (float)$value['price'];
                }
            }
        }

        return $total;
    }
}
