# `MintCart\Onboarding\StoreReadiness`

Computes "how ready is this store to take an order"
(`MINTCART_PRODUCT_VISION.md` §3/§6.1, `MINTCART_TECHNICAL_PLAN.md` §22,
`MINTCART_IMPLEMENTATION_GUIDE.md` §55) — a normalized checklist plus a
percentage, **derived** from the checklist, never hardcoded.

PHP: `upload/system/library/mintcart/onboarding/storereadiness.php`
(class `MintCart\Onboarding\StoreReadiness`)

Test: `tests/mintcart/onboarding/store-readiness-test.php`
(`php tests/mintcart/onboarding/store-readiness-test.php`)

Consumer: `upload/admin/controller/mintcart/readiness.php`
(`ControllerMintcartReadiness`, route `mintcart/readiness`, GET, JSON)

## Design

`StoreReadiness` has **no OpenCart dependency** — no `\Registry`, `\DB`,
`\Config` — same "resolved values in, normalized contract out" shape as
`CartService`/`ProductViewProvider`. It takes an array of already-resolved
booleans (`$signals`) and returns:

```php
[
    'percent' => 57,               // int, derived from complete/total, never hardcoded
    'checks' => [
        ['key' => 'logo', 'label' => 'Store logo uploaded', 'complete' => true],
        ['key' => 'contacts', 'label' => 'Store contact details configured', 'complete' => true],
        ['key' => 'language', 'label' => 'Storefront language configured', 'complete' => true],
        ['key' => 'currency', 'label' => 'Storefront currency configured', 'complete' => true],
        ['key' => 'shipping', 'label' => 'At least one shipping method enabled', 'complete' => false],
        ['key' => 'payment', 'label' => 'At least one payment method enabled', 'complete' => false],
        ['key' => 'first_product', 'label' => 'At least one product added', 'complete' => false],
    ],
    'ready_for_test_order' => false, // narrower than `percent` — see below
]
```

A missing signal key is treated as incomplete rather than throwing, so a
caller can pass a partial signal set while a signal type is still
unimplemented (none are, today — all seven ship in this same increment).

`labels` (the class's second, optional argument) lets a caller substitute
translated strings without touching the `key`/order contract — the admin
controller passes the loaded `mintcart/readiness` language strings this
way.

### Why `ready_for_test_order` is separate from `percent`

A store can look 100% ready on the checklist (logo, contacts, language,
currency all set) and still not be able to actually complete a checkout —
it also needs a purchasable product and *both* a shipping and a payment
method wired up. `ready_for_test_order` is `true` only when
`first_product && shipping && payment` are all true, independent of the
other four checks and independent of the overall percentage. This mirrors
the Product Vision's Time-to-First-**Test**-Order framing directly, rather
than overloading a single percentage to answer two different questions
("is the store configured" vs. "can I place an order right now").

## Where each signal actually comes from

`StoreReadiness::evaluate()` itself doesn't know or care — that's
`ModelMintcartReadiness::getSignals()`'s job
(`upload/admin/model/mintcart/readiness.php`), reading OpenCart's own
existing storage, no new Mint-specific table (`CLAUDE.md` §39/§6):

| Signal | Source |
| --- | --- |
| `logo` | `config_logo` setting non-empty |
| `contacts` | `config_telephone` + `config_email` + `config_address` all non-empty |
| `language` | `oc_language` has at least one row with `status = 1` |
| `currency` | `oc_currency` has at least one row with `status = 1` |
| `shipping` | at least one `setting/extension`-installed `shipping` extension has its `shipping_<code>_status` config flag on |
| `payment` | same as `shipping`, for `payment` |
| `first_product` | `catalog/product`'s `getTotalProducts(['filter_status' => 1])` > 0 |

The `<type>_<code>_status` convention isn't invented here — it's the exact
setting key every stock shipping/payment extension controller already
saves under (e.g. `extension/shipping/flat.php` saves
`shipping_flat_status`), confirmed by reading that controller before
writing `hasEnabledExtension()`, per `CLAUDE.md` §10/§84. "Installed"
(present in the `extension` table) is deliberately treated as
insufficient on its own — an extension can be installed but left disabled,
which still leaves the store unable to actually ship or take payment.

## Why a new controller/route instead of a dashboard widget directly

`mintcart/readiness` is a plain read-only JSON endpoint, not a rendered
dashboard panel. Per `CLAUDE.md` §85 ("API appears when there's a real
consumer, not before"), this endpoint is itself the first real consumer of
`StoreReadiness` — the smallest self-contained slice that proves the
service end-to-end without also deciding the dashboard widget's markup,
Setup Wizard's "Ready" step design, and CSS at the same time. Both are
listed as **next** consumers in `docs/IMPLEMENTATION_STATUS.md`'s Phase
14 entry, expected to call this exact same JSON contract rather than
re-deriving the checklist.

## Verified / not verified

- `php -l` clean on every new file.
- `tests/mintcart/onboarding/store-readiness-test.php` (7 scenarios: empty
  signals, all-true signals, a partial 3-of-7 and 4-of-7 ratio confirming
  the percentage is computed rather than hardcoded, the
  `ready_for_test_order` AND-of-three rule, fixed checklist key order,
  label overrides).
- **Not verified** — same pre-existing gap as every other controller/model
  wiring increment in this repo: no live MySQL/OpenCart admin session in
  this environment. A human should hit `mintcart/readiness` on a real
  install (as a logged-in admin user with the auto-discovered
  `mintcart/readiness` permission) and confirm each signal flips as
  expected — e.g. enabling a payment extension flips `payment` to `true`
  and moves `percent`/`ready_for_test_order` accordingly.
