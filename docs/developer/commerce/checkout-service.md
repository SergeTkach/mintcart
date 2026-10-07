# Mint checkout (`CheckoutService` + `mintcart/checkout/*`)

Single-page checkout for `mint_default` (Functional Scope §12, Technical
Plan §27, Implementation Guide §61). Architecture decision: ADR 009.

```
checkout/checkout (stock controller, unchanged)
  └─ mint_default/template/checkout/checkout.twig
       └─ assets/src/js/components/checkout.js
            └─ assets/src/js/commerce/checkout-client.js
                 └─ ControllerMintcartCheckout  state / update / confirm
                      ├─ MintCart\Commerce\CheckoutService            (pure rules)
                      ├─ Compatibility\OpenCart3\ShippingQuoteAdapter  (shipping quotes)
                      ├─ Compatibility\OpenCart3\TotalsAdapter         (order totals)
                      ├─ Compatibility\OpenCart3\PaymentMethodsAdapter (payment methods)
                      └─ Compatibility\OpenCart3\CheckoutOrderAdapter  (order data + addOrder)
```

## Endpoints

| Route | Method | Writes session | Returns |
|---|---|---|---|
| `mintcart/checkout/state` | GET | recalculates methods if an address is already set | full state (incl. `countries`) |
| `mintcart/checkout/update` | POST, any subset of the form | yes, no validation | state, or `{redirect}` |
| `mintcart/checkout/confirm` | POST, whole form | yes, then validates | `{errors, state}`, `{redirect}` or `{payment_html}` |

`{redirect}` is returned when the cart can no longer be checked out
(empty / out of stock without backorders / below a minimum) or a guest
tries to check out where guest checkout is off.

Posted fields: `firstname lastname email telephone` (ignored for a logged-in
customer — their account data is used), `country_id zone_id city address_1
postcode` (plus `company address_2` if a theme adds them),
`shipping_method` (`code.quote`), `payment_method` (code), `comment`,
`agree`.

### Session keys written

Exactly the stock ones, so extensions and `checkout/confirm.php` read a
normal checkout: `account = 'guest'`, `guest[...]`, `payment_address`,
`shipping_address` (same address — one-address decision), `shipping_methods`,
`shipping_method`, `payment_methods`, `payment_method`, `comment`, and
finally `order_id`.

### State shape

```json
{
  "logged": false, "can_guest": true, "shipping_required": true, "initialized": true,
  "contact": { "firstname": "", "lastname": "", "email": "", "telephone": "" },
  "address": { "company": "", "address_1": "", "address_2": "", "city": "", "postcode": "", "country_id": 220, "zone_id": 3490 },
  "comment": "",
  "login_html": "Already have an account? <a href=\"…\">Log in</a>",
  "countries": [{ "country_id": 220, "name": "Ukraine", "postcode_required": false }],
  "zones": [{ "zone_id": 3490, "name": "Kyiv" }],
  "shipping": { "methods": [{ "method": "Flat Rate", "options": [{ "id": "flat.flat", "title": "Flat Shipping Rate", "text": "$5.00" }] }], "selected": "flat.flat" },
  "payment": { "methods": [{ "code": "cod", "title": "Cash On Delivery", "terms": "" }], "selected": "cod" },
  "agree_html": "",
  "items": [ /* CartService::snapshot() items, via mintcart/cart/snapshot */ ],
  "totals": { "lines": [{ "code": "sub_total", "title": "Sub-Total", "text": "$20.00" }], "total": "$25.00" }
}
```

`countries` is only in `state` (page boot), not in `update`.

## Recalculation order

`update`/`confirm` → `persist()`: write contact + address → re-quote
shipping for the address → pick shipping (keep previous if still offered,
else first valid) → compute totals (shipping now included) → re-offer
payment methods for that total → pick payment (same rule) → save comment.

## `CheckoutService` (pure)

- `canCheckout(products, hasVouchers, hasStock, stockCheckout)` — stock cart gate.
- `validateContact(input)` / `validateAddress(input, country, zoneIds)` →
  `field => language key` (`error_firstname`, …), same limits as stock
  `guest.php`. A zone is required whenever the country has zones.
- `buildAddress(contact, input, country, zone)` — the stock session address shape.
- `pickShippingMethod(id, methodData)` / `pickPaymentMethod(code, methods)`.
- `buildOrderData(ctx, taxOf, voucherCode)` — the `addOrder()` array,
  moved out of `checkout/confirm.php`.

## JS

- `checkout-client.js`: `fetchCheckoutState()`, `updateCheckout(formData, signal)`,
  `confirmCheckout(formData)`. `updateCheckout` dispatches
  `mc:checkout:updated` (`detail` = state) on `document`.
- `components/checkout.js`: `initCheckout()` (wired in `catalog.js`), hooks
  are all `data-mc-checkout-*` attributes in the template.

## Theme override

A theme that wants different checkout DOM overrides
`template/checkout/checkout.twig`, keeps the `data-mc-checkout*` hooks (or
ships its own script against the three endpoints). Strings come from
`catalog/language/<lang>/checkout/checkout.php` (`text_mc_*`,
`button_mc_place_order`).

## Not done yet

Address book for logged-in customers (their default address is prefilled,
a changed address is used for the order but not saved to the book),
registration during checkout, custom fields, captcha, separate billing
address, coupon/voucher/reward entry on the page, auto-click of simple
payment confirm buttons.

## Tests

- `tests/mintcart/commerce/checkout-service-test.php`
- `tests/mintcart/compatibility/opencart3/payment-methods-adapter-test.php`
- `tests/mintcart/compatibility/opencart3/checkout-order-adapter-test.php`
