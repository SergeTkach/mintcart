# `MintCart\Commerce\QuickOrderService`

Validates a Quick Order request — "product ID, quantity, required options,
name, phone/email, basic validation"
(`MINTCART_FUNCTIONAL_SCOPE.md` §13, `MINTCART_TECHNICAL_PLAN.md` §28,
`MINTCART_IMPLEMENTATION_GUIDE.md` §60).

PHP: `upload/system/library/mintcart/commerce/quickorderservice.php`
(class `MintCart\Commerce\QuickOrderService`)

Test: `tests/mintcart/commerce/quick-order-service-test.php`
(`php tests/mintcart/commerce/quick-order-service-test.php`)

Migration: `upload/system/mintcart/migrations/202609041200_create_mc_quick_order.php`,
covered by `tests/mintcart/commerce/quick-order-migration-test.php`

Consumer: `upload/catalog/controller/mintcart/quickorder.php`
(`ControllerMintcartQuickorder`, route `mintcart/quickorder/submit`) +
`assets/src/js/components/quick-order.js` (`initQuickOrderActions()`) +
`assets/src/js/commerce/quick-order-client.js` (`submitQuickOrder()`)

## Why a request is a lead, not an order

Every source document describes Quick Order with a deliberately small field
set: product, quantity, name, phone/email. No billing/shipping address, no
payment method. That is not enough to build a valid `oc_order` row —
`checkout/order.php::addOrder()` requires a full address/payment/shipping
field list — and forcing placeholder values into it would corrupt every
report and extension that assumes an `oc_order` row is a real, fulfillable
order.

So a Quick Order request is stored as its own thing: a **lead** for staff to
call back and turn into a real order (or reject), in a new Mint-owned
`mc_quick_order` table (`CLAUDE.md` §6/§39 — new Mint tables only for
functionality OpenCart's own schema has no room for). This also sidesteps
the exact risk PR #61's own body flagged: building real order-creation
logic without a live MySQL server to verify `addOrder()`'s full field list
against. Converting a confirmed lead into a real order (once the missing
address/payment details are collected) is a deliberately separate,
not-yet-built follow-up — see "Not done yet" below.

## Design

`QuickOrderService` has **no OpenCart dependency** — no `\Registry`, `\DB`.
Fetching the product, checking required options/recurring plans (delegated
to `MintCart\Commerce\CartService::validateOptions()`, not re-derived here)
and persisting the request all stay `ControllerMintcartQuickorder`'s job
upstream of this class, the same split `CartService`/`SearchService`
already established.

### `validate(array $request): array`

```text
{
  valid: bool,
  errors: { field: string, ... },   // error_* language key per field, empty when valid
  quantity: int,                     // always >= 1, even when invalid (see below)
  name: string,                      // trimmed
  telephone: string,                 // trimmed
  email: string                      // trimmed
}
```

Rules:

- `quantity` — omitted/empty defaults to `1` (a quick-order form always
  sends a value, but the field is optional in the contract). Present but
  not a positive integer → `errors.quantity = 'error_quantity'`, and the
  returned `quantity` is still clamped to `1` so a controller doesn't have
  to guess a fallback itself.
- `name` — required, non-empty after `trim()` → `errors.name = 'error_name'`.
- `telephone`/`email` — **at least one** of the two is required
  (`MINTCART_FUNCTIONAL_SCOPE.md` §13 says "phone/email", not "phone and
  email") → `errors.contact = 'error_contact'` when both are empty. If an
  email is present, it must pass `FILTER_VALIDATE_EMAIL` →
  `errors.contact = 'error_email'` (phone format is intentionally **not**
  validated beyond non-empty — international formats vary too widely for a
  meaningful regex, and "basic validation" per the Functional Scope does
  not ask for a phone-number library).

Required-option/recurring-plan handling is **not** part of this class — the
same "can't offer a picker from a listing card, redirect to the product
page" fallback `ControllerMintcartCart::add()` already uses is applied by
`ControllerMintcartQuickorder::submit()` itself, before `validate()` is
even called, by reusing `CartService::validateOptions()`.

## `ControllerMintcartQuickorder::submit()`

`route=mintcart/quickorder/submit`, POST `product_id`, `quantity`,
`option[]`, `name`, `telephone`, `email`.

1. Product must exist → else `{error: text}`.
2. Required options/recurring plan missing → `{error: text, redirect: <product url>}`,
   nothing recorded (same fallback `mintcart/cart/add` uses).
3. `QuickOrderService::validate()` fails → `{errors: {field: translated message}}`.
4. Valid → inserts a row via `ModelMintcartQuickorder::addRequest()` (product
   snapshot, quantity, name, telephone, email, store/language id, IP,
   `status = 'new'`) and responds `{success: true, message: text_success}`.

Fully additive — no existing OpenCart file is modified.

## JS: `quick-order-client.js` + `quick-order.js`

`submitQuickOrder(productId, {quantity, name, telephone, email})` is a pure
data layer (same shape as `cart-client.js`), resolving to one of
`{redirected: true, url}` / `{errors: {...}}` / `{success: true, message}`.

`initQuickOrderActions()` builds **one shared modal** on first
`[data-mc-action="quick-order"]` click (a listing page can render dozens of
product cards; Bootstrap's `Modal` assumes one live instance per dialog
element), populated per click from the clicked button's `data-product-id`/
`data-product-name`. Field errors render using the documented
`mc-field`/`has-error`/`aria-invalid`/`aria-describedby` contract
(`docs/developer/components/field.md`); success closes the modal and shows
a toast with the server's own translated confirmation message.

## Not done yet

- **No admin "Quick Order requests" list** — leads are recorded in
  `mc_quick_order` but nothing in Mint Admin surfaces them yet for staff to
  action. Natural next consumer of this table.
- **No lead → order conversion** — turning a confirmed lead into a real
  `oc_order` (once an address/payment method is collected from the
  customer, by phone or a follow-up form) is deliberately out of scope
  here; see "Why a request is a lead, not an order" above.
- **Not yet verified live** — this environment has no MySQL server (same
  pre-existing gap noted throughout this file); a human should confirm
  `mintcart/quickorder/submit` against a real install and click through the
  modal in a real browser. See `docs/MANUAL_VERIFICATION.md`.

## Definition of Done checklist

- [x] Documented contract (this file)
- [x] No OpenCart dependency — unit-testable in isolation
- [x] No OCMOD, no modification to any existing OpenCart file
- [x] Smoke test covering `validate()` (10 scenarios) and the migration's
      `CREATE TABLE` statement
- [ ] Live controller wiring verified against a real install — not possible
      in this environment (no MySQL); `mintcart/cart/add`'s equivalent
      round-trip was verified this way in an earlier run, this one was not
- [ ] Admin "Quick Order requests" list — not built yet
- [ ] Lead → order conversion — not built yet, deliberately (see above)
