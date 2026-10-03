# `MintCart\Commerce\ShippingMethodService`

Normalizes computed shipping method quotes for the Theme API's
checkout-shipping-methods contract (`MINTCART_TECHNICAL_PLAN.md` §27,
`MINTCART_IMPLEMENTATION_GUIDE.md` §61, `MINTCART_FUNCTIONAL_SCOPE.md` §12:
"При зміні адреси/доставки: актуалізувати shipping; payment; totals;
availability") — the same normalization role `CheckoutTotalsService`
(`docs/developer/commerce/checkout-totals-service.md`) plays for totals,
applied to shipping method quotes.

PHP: `upload/system/library/mintcart/commerce/shippingmethodservice.php`
(class `MintCart\Commerce\ShippingMethodService`)

Test: `tests/mintcart/commerce/shipping-method-service-test.php`
(`php tests/mintcart/commerce/shipping-method-service-test.php`)

Consumer: `upload/catalog/controller/mintcart/checkout.php`
(`ControllerMintcartCheckout::shippingMethods()`, route
`mintcart/checkout/shipping_methods`)

## Why a new endpoint on the existing `ControllerMintcartCheckout`

Same controller `CheckoutTotalsService` already added — a second action on
it rather than a new controller, since both actions serve the same "a
future Mint-native checkout UI needs a normalized, non-mutating preview"
purpose for the same page (`MINTCART_TECHNICAL_PLAN.md` §27's
`Contacts → Address → Delivery → Payment → Review` flow), just for
different pieces of checkout state.

## Design

`ShippingMethodService` has **no OpenCart dependency**, same as
`CheckoutTotalsService` — but unlike it, takes **no `$formatMoney`
callback**. Every stock `extension/shipping/*.php` model already returns
its quote's `text` key pre-formatted via `$this->currency->format()` (see
e.g. `upload/catalog/model/extension/shipping/flat.php`), so there is no
raw numeric value left for this class to format — only to reshape from
`ShippingQuoteAdapter::calculate()`'s per-extension shape into a flat,
theme-consumable list.

### `normalize(array $methodData): array`

```php
$service->normalize($adapterResult);

// => [
//   {method: 'Flat Shipping Rate', options: [{id: 'flat.flat', title: 'Flat Shipping Rate', text: '$5.00'}]},
//   ...
// ]
```

One entry per shipping extension, with every quote option it offers (most
extensions offer exactly one; a table-rate style extension can offer
several under the same method). `options[].id` is built as exactly
`"<method_code>.<quote_key>"` — the same string
`checkout/shipping_method.php::save()` already expects back in
`$this->request->post['shipping_method']`
(`explode('.', $this->request->post['shipping_method'])`), so a future
Mint-native checkout UI can post this `id` straight through to that
existing, unmodified endpoint with no translation layer, per
`MINTCART_TECHNICAL_PLAN.md` §27's "поверх стандартних OpenCart ...
totals; order model." Does **not** re-sort — `ShippingQuoteAdapter::calculate()`
already returns its result sorted by `sort_order`.

`sort_order`/`error`/`cost`/`tax_class_id` are deliberately dropped from
the public output shape — no real consumer needs them yet (`CLAUDE.md`
§85).

## `ControllerMintcartCheckout` actions (this increment)

| Route | Method | Notes |
|---|---|---|
| `mintcart/checkout/shipping_methods` | GET, no mutation | Computes shipping method quotes for the address given as request parameters (`country_id`, `zone_id`, `postcode`, `city`, `address_1`, `firstname`, `lastname`, `company`, `zone_code`, `iso_code_2`, `iso_code_3` — all optional, default to `''`/`0`) via `ShippingQuoteAdapter`, and returns `{methods: [{method, options: [{id, title, text}]}]}`. Unlike `totals()`, this does **not** read or write `$this->session->data['shipping_address']`/`['shipping_methods']` — it is a pure preview of what a given address would quote, independent of whatever is currently saved in session. |

## What this is not

- **Not a replacement for `checkout/shipping_address.php`/
  `checkout/shipping_method.php`.** Those remain the only code that
  persists a customer's actual shipping address/method selection into
  session and, eventually, the order — this endpoint only previews quotes,
  it never saves anything.
- **Not a modern checkout UI.** No Twig template, no JS component calls
  this endpoint yet — same "ships ahead of its first consumer" pattern
  documented throughout `docs/IMPLEMENTATION_STATUS.md` (`CLAUDE.md` §85).
  A future checkout UI recalculating shipping options as the customer
  fills in their address (`MINTCART_FUNCTIONAL_SCOPE.md` §12) is the
  natural first real caller — it would call this endpoint, let the
  customer pick an `options[].id`, then post that exact string to the
  existing `checkout/shipping_method.php` `save()` action to actually
  select it.
- **Not address validation or a live-rate carrier integration.** See
  `docs/developer/compatibility/shipping-quote-adapter.md`'s own "What
  this is not".

## Definition of Done checklist

- [x] Documented contract (this file)
- [x] No OpenCart dependency in `ShippingMethodService` itself —
      unit-testable in isolation
- [x] No OCMOD; zero existing OpenCart controller/template modified for
      this route (see `docs/developer/compatibility/shipping-quote-adapter.md`
      for why `checkout/shipping_method.php` itself needed no change)
- [x] Smoke test covering an empty result, a single method/single option,
      input-order preservation, and a method offering multiple options
- [ ] Live verification against a real install — this environment has no
      MySQL server (same pre-existing gap noted throughout
      `docs/IMPLEMENTATION_STATUS.md`); a human should confirm
      `mintcart/checkout/shipping_methods?country_id=...&zone_id=...`
      returns the expected quotes for a real installed shipping extension
- [ ] No Mint-native checkout UI calling this endpoint yet — see "What
      this is not" above
