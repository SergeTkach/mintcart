# Live product price (`mintcart/price`)

The product page price follows the chosen quantity and options and shows
the **total** the cart will charge.

- Service: `MintCart\Commerce\ProductPriceCalculator`
  (`upload/system/library/mintcart/commerce/productpricecalculator.php`) —
  pure rules, no OpenCart dependency. Test:
  `php tests/mintcart/commerce/product-price-calculator-test.php`.
- Endpoint: `route=mintcart/price` (`catalog/controller/mintcart/price.php`),
  POST `product_id`, `quantity`, `option[...]`.
- JS: `assets/src/js/components/product-price.js` (wired in `catalog.js`).

## Rules (= `system/library/cart/cart.php`, OpenCart 3.0.5.1)

1. Base = `product.price`.
2. Quantity discount tier: first `product_discount` row (customer group,
   date window; `ORDER BY quantity DESC, priority ASC, price ASC`) with
   `quantity <=` the product's **total quantity in the cart plus the chosen
   quantity** (all option combinations count). Rows with quantity 0/1
   apply too.
3. An active special replaces the price — **even when a tier is cheaper**.
   Stock OpenCart behavior; kept so the page never disagrees with the cart.
4. Option add-ons (`+`/`-`) for select / radio / each checked checkbox are
   added after that. Option stock is not considered (the cart doesn't).
5. Tax and currency per unit, then × quantity (same as the cart page).

Lessons from Cartello's `priceLive()`: it used "cheaper of special and
tier wins" and skipped out-of-stock option add-ons — both disagree with the
cart, so the shown total and the charged total could differ.

## Response

```json
{ "current": "$459.20", "original": "$507.20", "tax": "$376.00" }
```

- `current` — what the cart charges for this quantity.
- `original` — regular total (base + options), only when higher than
  `current` (a special or a tier is saving money); else `false`.
- `tax` — `current` without tax, when prices are shown with tax.
- All `false` when prices are hidden from guests (`config_customer_price`).

## Markup hooks

```twig
<div class="mc-price …" data-mc-product-price data-mc-product-id="{{ product_id }}" aria-live="polite">
    <span class="mc-price__current" data-mc-price-current>…</span>
    <span class="mc-price__original" data-mc-price-original hidden>…</span>
    <span data-mc-price-tax>…</span>
</div>
```

Every `[data-mc-price-current]` on the page is updated (the buy bar has
one). `.mc-price` gets `is-loading` while a request is in flight and
`mc-price--special` while `original` is shown. Requests are debounced
(150 ms) and a newer one aborts the older. On load the price is
recalculated only when the starting quantity isn't 1 (product minimum).

Verified 2026-10-08: product 42, 12 pcs + options — endpoint `$2,097.60`,
cart line total `$2,097.60`.
