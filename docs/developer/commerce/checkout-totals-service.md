# `MintCart\Commerce\CheckoutTotalsService`

Normalizes computed order totals for the Theme API's checkout-totals
contract (`MINTCART_TECHNICAL_PLAN.md` §27, `MINTCART_IMPLEMENTATION_GUIDE.md`
§61, `MINTCART_FUNCTIONAL_SCOPE.md` §12: "При зміні адреси/доставки:
актуалізувати shipping; payment; totals; availability") — the same
normalization role `CartService::snapshot()`/`ProductViewProvider` play
elsewhere, applied to a checkout's total lines.

PHP: `upload/system/library/mintcart/commerce/checkouttotalsservice.php`
(class `MintCart\Commerce\CheckoutTotalsService`)

Test: `tests/mintcart/commerce/checkout-totals-service-test.php`
(`php tests/mintcart/commerce/checkout-totals-service-test.php`)

Consumer: `upload/catalog/controller/mintcart/checkout.php`
(`ControllerMintcartCheckout::totals()`, route `mintcart/checkout/totals`)

## Why a new controller instead of extending an existing checkout controller

`checkout/payment_method.php` and `checkout/confirm.php` already compute
totals (now both via `MintCart\Compatibility\OpenCart3\TotalsAdapter`, see
`docs/developer/compatibility/totals-adapter.md`), but neither returns
them as a small, theme-consumable JSON response on their own — recomputing
and re-rendering totals is entangled with each controller's specific
step (choosing a payment method; finalizing the order). A future
Mint-native checkout UI needs to re-show totals the instant the cart,
shipping method, or address changes, independent of which checkout step
is active. Same reasoning `ControllerMintcartCart`/`ControllerMintcartSearch`
already established (`docs/developer/commerce/cart-service.md`,
`docs/developer/commerce/search-service.md`): a new, fully-additive
MintCart-owned controller, **zero upstream diff** for this endpoint
itself (the two existing controllers were touched only to remove the
duplicate totals-computation loop, not to add this route).

## Design

`CheckoutTotalsService` has **no OpenCart dependency** — no `\Registry`,
no `\Currency`. Computing the raw totals (`TotalsAdapter::calculate()`)
and formatting money (`$this->currency->format()`) both stay
`ControllerMintcartCheckout`'s job upstream of this class, exactly the
split `CartService::snapshot()` already established for cart totals.

### `normalize(array $rawTotals, callable $formatMoney): array`

```php
$service->normalize(
    $totalsAdapterResult['totals'], // [{code, title, value, sort_order}, ...]
    fn ($value) => $this->currency->format($value, $this->session->data['currency'])
);

// => [ {code: 'sub_total', title: 'Sub-Total', text: '$100.00'}, ... ]
```

Takes `$formatMoney` as an injected callback rather than reaching for
OpenCart's currency service directly — the same "presentation values
resolved upstream, this class only reshapes the array" contract
`CartService::snapshot()` uses for cart totals. Does **not** re-sort —
`TotalsAdapter::calculate()` already returns `totals` sorted by
`sort_order`; re-sorting here would just be a second, redundant pass over
data that is already correct.

`sort_order` and the raw numeric `value` are deliberately dropped from the
public output shape: order is already correct on the way in, and a caller
that needs the raw number for anything beyond display (e.g. client-side
math) has `TotalsAdapter::calculate()`'s own return value available
upstream of this call, so there is no case yet where duplicating it here
serves a real consumer (`CLAUDE.md` §85).

## `ControllerMintcartCheckout` actions

| Route | Method | Notes |
|---|---|---|
| `mintcart/checkout/totals` | GET, no mutation | Recomputes the current session cart's totals via `TotalsAdapter` and returns `{lines: [{code, title, text}, ...], total: string}`. Reflects whatever is already in session (`shipping_method`, cart contents) — it does not itself set an address or shipping method; those stay `checkout/shipping_address.php`/`checkout/shipping_method.php`'s job, unchanged. |

## What this is not

- **Not `QuickOrderService`.** A separate, still-unclaimed-as-of-this-writing
  Phase 15 item (`MINTCART_TECHNICAL_PLAN.md` §28) — see
  `docs/IMPLEMENTATION_STATUS.md`'s Phase 15 section for current status.
- **Not a modern checkout UI.** No Twig template, no JS component calls
  this endpoint yet — same "ships ahead of its first consumer" pattern
  documented throughout `docs/IMPLEMENTATION_STATUS.md` (`CLAUDE.md` §85).
  A future checkout UI recalculating totals on address/shipping-method
  change (`MINTCART_FUNCTIONAL_SCOPE.md` §12) is the natural first real
  caller.
- **Not a second payment/shipping/totals system.** Every existing `total`
  extension keeps computing exactly as before
  (`docs/developer/compatibility/totals-adapter.md`) — this only exposes
  their already-existing combined output as JSON.

## Definition of Done checklist

- [x] Documented contract (this file)
- [x] No OpenCart dependency in `CheckoutTotalsService` itself —
      unit-testable in isolation
- [x] No OCMOD; the new controller is fully additive (zero existing
      OpenCart controller/template modified for this route)
- [x] Smoke test covering an empty totals list, a single line, input-order
      preservation, and several lines formatted independently
- [ ] Live verification against a real install — this environment has no
      MySQL server (same pre-existing gap noted throughout
      `docs/IMPLEMENTATION_STATUS.md`); a human should confirm
      `mintcart/checkout/totals` returns the expected JSON shape for a
      real cart, with and without a shipping method selected in session
- [ ] No Mint-native checkout UI calling this endpoint yet — see "What
      this is not" above
