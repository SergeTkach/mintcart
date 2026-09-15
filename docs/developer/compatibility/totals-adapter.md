# `MintCart\Compatibility\OpenCart3\TotalsAdapter`

The `Compatibility` layer's second adapter
(`MINTCART_TECHNICAL_PLAN.md` §5, `MINTCART_IMPLEMENTATION_GUIDE.md` §38:
"Mint code → Adapter → OpenCart model/service/storage") — normalizes
OpenCart 3's `total` extension pipeline (sub-total, shipping, tax, coupon,
the final "Total" line, ...) behind one method, so Mint code that needs a
cart/order's computed totals does not need to know OpenCart's own
installed-extensions-plus-config-flags-plus-pass-by-reference-array
mechanics.

PHP: `upload/system/library/mintcart/compatibility/opencart3/totalsadapter.php`
(class `MintCart\Compatibility\OpenCart3\TotalsAdapter`)

Test: `tests/mintcart/compatibility/totals-adapter-test.php`
(`php tests/mintcart/compatibility/totals-adapter-test.php`)

Consumers:
- `upload/catalog/controller/checkout/payment_method.php` (existing
  OpenCart controller, refactored — see `docs/upstream-diff.md`)
- `upload/catalog/controller/checkout/confirm.php` (existing OpenCart
  controller, refactored — see `docs/upstream-diff.md`)
- `upload/catalog/controller/mintcart/checkout.php`
  (`ControllerMintcartCheckout::totals()`, new — see
  `docs/developer/commerce/checkout-totals-service.md`)

## Why this exists

`checkout/payment_method.php` and `checkout/confirm.php` each inlined an
**identical** ~30-line loop: load every installed `total` extension model
(`setting/extension` → `getExtensions('total')`), sort them by their own
`total_<code>_sort_order` config value, then for each one whose
`total_<code>_status` flag is on, load `extension/total/<code>` and call
its `getTotal($total_data)` — where `$total_data` is a plain array whose
`totals`/`taxes`/`total` keys are themselves references to the caller's
own variables (`'totals' => &$totals`, ...), because OpenCart's dynamic
`$this->{'model_extension_total_' . $code}->getTotal(...)` call can't keep
real PHP reference *parameters* across that magic-property dispatch — the
comment both original files carried, kept verbatim on this class, explains
why the wrapper array exists at all.

This was left duplicated (not extracted) the first time it was noticed,
following this repo's own established precedent for two call sites
(`CLAUDE.md` §85/§86 — see `ActiveTheme`/`ProductCardListRenderer`'s
history in `docs/upstream-diff.md`: not extracted until a **third** real
consumer needed the same computation). `ControllerMintcartCheckout::totals()`
— a new, fully-additive AJAX endpoint for re-showing order totals without a
full page reload (`MINTCART_FUNCTIONAL_SCOPE.md` §12, `MINTCART_TECHNICAL_PLAN.md`
§27) — is that third consumer, so the extraction happened now.

## Contract

```php
$adapter = new \MintCart\Compatibility\OpenCart3\TotalsAdapter(
    $config,    // OpenCart's Config object - needs get(string $key)
    $load,      // OpenCart's Loader object ($this->load) - needs model(string $route): void
    $registry   // OpenCart's Registry object ($this->registry) - needs get()/has()
);

$result = $adapter->calculate($taxes); // $taxes: seed array, e.g. $this->cart->getTaxes()

// $result = [
//     'totals' => [ ['code' => 'sub_total', 'title' => 'Sub-Total', 'value' => 49.5, 'sort_order' => 1], ... ],
//     'taxes'  => [ /* tax_rate_id => amount */ ],
//     'total'  => 61.4,
// ];
```

All three constructor collaborators are duck-typed against OpenCart's own
core engine classes (`Config`, `Loader`, `Registry`) rather than a single
`\Registry`-only dependency, because the loop genuinely needs all three
capabilities: `Loader::model()` to dynamically load whichever
`extension/total/<code>` classes are installed, `Registry::get()` to read
back the proxy `Loader::model()` just registered (the same
`$this->{'model_extension_total_' . $code}` magic-property trick
`Controller`/`Model`'s own `__get` normally hides — this class has neither
magic method, so it reads the registry directly), and `Config::get()` for
the `total_*_status`/`total_*_sort_order` flags. Any `Controller` subclass
already has all three via `$this->config`/`$this->load`/`$this->registry`
and needs nothing heavier.

`calculate()`'s `totals` return value is **always** sorted by `sort_order`
— one original call site (`confirm.php`) applied that sort a second time
after the loop, the other (`payment_method.php`) never needed to (it only
reads the scalar `total`, never renders the list). Sorting once, inside
this class, is not a behavior change for either — see
`docs/upstream-diff.md`'s entries for both files.

## What this is not

- **Not a full checkout-totals presentation layer.** Raw `value`s here are
  plain floats, not currency-formatted strings — that normalization step
  is `MintCart\Commerce\CheckoutTotalsService`'s job
  (`docs/developer/commerce/checkout-totals-service.md`), kept as a
  separate pure class with no OpenCart dependency, the same split
  `CartService`/`ProductViewProvider` already established.
- **Not a new totals *system*.** Every `total` extension (shipping,
  coupon, reward points, third-party payment surcharges, ...) is untouched
  and keeps computing exactly as it always did — this class only collects
  and sorts their output instead of three near-identical inline copies of
  the same collection loop.

## Definition of Done checklist

- [x] Documented contract (this file)
- [x] No OCMOD; only two existing OpenCart files touched, both to remove
      duplication (see `docs/upstream-diff.md`)
- [x] Smoke test covering "nothing installed", "installed but disabled",
      "installed and enabled", "sorted by `sort_order` regardless of
      installation order", and "a seeded `taxes` array is visible to and
      summed by a total extension"
- [ ] Live verification against a real install — this environment has no
      MySQL server (same pre-existing gap noted throughout
      `docs/IMPLEMENTATION_STATUS.md`); a human should confirm
      `checkout/payment_method`/`checkout/confirm` still compute identical
      totals after this refactor, and that `mintcart/checkout/totals`
      returns the expected JSON shape for a real cart.
