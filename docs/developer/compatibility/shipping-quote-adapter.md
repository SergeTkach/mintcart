# `MintCart\Compatibility\OpenCart3\ShippingQuoteAdapter`

A `Compatibility\OpenCart3` adapter (`MINTCART_TECHNICAL_PLAN.md` §5,
`MINTCART_IMPLEMENTATION_GUIDE.md` §38: "Mint code → Adapter → OpenCart
model/service/storage") — normalizes OpenCart 3's `shipping` extension
quote pipeline behind one method, the same role `TotalsAdapter`
(`docs/developer/compatibility/totals-adapter.md`) plays for the `total`
extension pipeline.

PHP: `upload/system/library/mintcart/compatibility/opencart3/shippingquoteadapter.php`
(class `MintCart\Compatibility\OpenCart3\ShippingQuoteAdapter`)

Test: `tests/mintcart/compatibility/opencart3/shipping-quote-adapter-test.php`
(`php tests/mintcart/compatibility/opencart3/shipping-quote-adapter-test.php`)

Consumer: `upload/catalog/controller/mintcart/checkout.php`
(`ControllerMintcartCheckout::shippingMethods()`, new — see
`docs/developer/commerce/shipping-method-service.md`)

## Why this exists

`checkout/shipping_method.php::index()` computes shipping method quotes:
load every installed `shipping` extension (`setting/extension` →
`getExtensions('shipping')`), skip any whose own `shipping_<code>_status`
flag is off, load `extension/shipping/<code>` for every enabled one and
call its `getQuote($address)`, collect non-empty results into an
associative array keyed by extension code, then sort by each result's own
`sort_order` via `array_multisort()` (which preserves the string keys —
the same lookup `save()` relies on when it reads
`$this->session->data['shipping_methods'][$shipping[0]]['quote'][$shipping[1]]`
back out).

`ControllerMintcartCheckout::shippingMethods()` needs this exact
computation to serve a normalized JSON preview of available shipping
methods to a future Mint-native checkout UI
(`MINTCART_FUNCTIONAL_SCOPE.md` §12: "При зміні адреси/доставки:
актуалізувати shipping; payment; totals; availability") — rather than
duplicate the loop a second time, it is extracted here.

## Zero upstream diff for this increment

Unlike `TotalsAdapter` (which replaced the *same* duplicated loop in two
existing controllers, `checkout/payment_method.php`/`checkout/confirm.php`),
`checkout/shipping_method.php` is the **only** existing call site for this
loop — it is not duplicated anywhere else in this codebase, so there is no
existing duplication for this adapter to clean up. `checkout/shipping_method.php`
is left completely untouched: this is a new, fully-additive class with a
single new consumer, the same "zero existing OpenCart file touched"
pattern `CartService`/`SearchService`/`QuickOrderService` already
established (`CLAUDE.md` §11).

## Design

Same three duck-typed collaborators `TotalsAdapter` takes (`Config`,
`Loader`, `Registry`) and for the same reason — `Loader::model()` to
dynamically load each `extension/shipping/<code>` model,
`Registry::get()` to read back the proxy it just registered,
`Config::get()` for the `shipping_<code>_status` flag.

Deliberately does **not** read or write `$this->session->data` — unlike
`checkout/shipping_method.php::index()`, which stores its result in
`$this->session->data['shipping_methods']` as a side effect. The address
to quote against is a plain input parameter, and the computed methods are
the return value; nothing is persisted. This mirrors
`ControllerMintcartCheckout::totals()`'s own "recompute, do not mutate
session" shape (`docs/developer/commerce/checkout-totals-service.md`).

### `calculate(array $address): array`

```php
$adapter = new \MintCart\Compatibility\OpenCart3\ShippingQuoteAdapter($this->config, $this->load, $this->registry);
$adapter->calculate(['country_id' => 222, 'zone_id' => 3573]);

// => ['flat' => ['title' => 'Flat Shipping Rate', 'quote' => ['flat' => [...]], 'sort_order' => 1, 'error' => false], ...]
```

`$address` must contain whatever keys the store's *installed* shipping
extensions actually read from `getQuote($address)` — every stock
geo-zone-based extension (`flat`, `free`, `weight`, `item`, `pickup`, ...)
only needs `country_id`/`zone_id`; a few (`fedex`, `auspost`, `royal_mail`)
also read `postcode`/`iso_code_2`/`zone_code`/name/address fields. The
caller assembles this array — the same "caller supplies the input, this
class only computes" split `TotalsAdapter::calculate($taxes)` uses for its
seed `$taxes` parameter.

An extension that is disabled, or whose `getQuote()` returns an
empty/falsy result (no applicable geo zone, a live API call failing), is
simply absent from the returned array — never a placeholder entry, same
behavior as the original loop.

## What this is not

- **Not a live-rate API integration.** This only reuses whatever
  `getQuote()` each installed extension model already implements — no new
  carrier integration, no new HTTP calls beyond what an extension like
  `fedex`/`auspost` already makes on its own.
- **Not address validation.** The caller is responsible for the address
  shape; this class does not check the address is complete or valid
  beyond what each extension's own `getQuote()` does internally.

## Definition of Done checklist

- [x] Documented contract (this file)
- [x] No OpenCart dependency beyond duck-typed `Config`/`Loader`/`Registry` —
      unit-testable in isolation
- [x] Zero upstream diff — `checkout/shipping_method.php` untouched
- [x] Smoke test covering no extensions, disabled extension, enabled
      extension, an enabled extension with no applicable quote, and
      sort-order-preserving-string-keys across multiple extensions
- [ ] Live verification against a real install — this environment has no
      MySQL server (same pre-existing gap noted throughout
      `docs/IMPLEMENTATION_STATUS.md`); a human should confirm
      `mintcart/checkout/shipping_methods` returns the expected quotes for
      a real address against real installed shipping extensions
