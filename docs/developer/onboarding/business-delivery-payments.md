# Business Settings — `Business → Delivery` / `Business → Payments`

`MINTCART_PRODUCT_VISION.md` §6.1/§8, `MINTCART_FUNCTIONAL_SCOPE.md` §3,
`MINTCART_TECHNICAL_PLAN.md` §23, `MINTCART_IMPLEMENTATION_GUIDE.md` §56.

Two facades over standard OpenCart storage/extensions (`CLAUDE.md`
§6/§39) — **not** a second shipping/payment subsystem
(`MINTCART_TECHNICAL_PLAN.md` §23 explicitly rules that out). A `Business
→ Contacts` facade over `config`/`mintcart_contacts` settings is a
separate, independent piece of Phase 14 (a different open increment at
the time this was written — check `docs/IMPLEMENTATION_STATUS.md`'s
Phase 14 section for its current status).

PHP:

- `upload/admin/controller/mintcart/business/delivery.php`
  (`ControllerMintcartBusinessDelivery`, route `mintcart/business/delivery`)
- `upload/admin/controller/mintcart/business/payments.php`
  (`ControllerMintcartBusinessPayments`, route `mintcart/business/payments`)

Views: `upload/admin/view/template/mintcart/business/{delivery,payments}_list.twig`

Language: `upload/admin/language/en-gb/mintcart/business/{delivery,payments}.php`

Menu: `upload/admin/controller/common/column_left.php`'s new "Business"
group (`menu-mintcart-business`) — permission-gated the same way every
other menu group already is.

## What this is, and isn't

Both screens list the store's **installed** shipping/payment extensions
(`ModelSettingExtension::getInstalled('shipping'|'payment')` — the exact
source `ControllerExtensionExtensionShipping`/`...Payment`'s own
`getList()` already use for `Extensions → Shipping Methods`/`Payment
Methods`) and let a merchant flip each one's enabled state with a single
click, without opening its full settings form. That full settings form —
cost, tax class, geo zone, API keys, whatever a given extension needs —
still lives entirely on that extension's own `extension/shipping/<code>`/
`extension/payment/<code>` screen, linked from here as "Configure", never
duplicated. Installing a *new* shipping/payment method (the "Manage"
link, pointing at the existing `extension/extension/shipping`/`...payment`
screen) is also left to OpenCart's own existing flow — this facade is
about the everyday "turn Cash on Delivery off for the holidays" action,
not extension lifecycle management.

An extension whose controller file no longer exists on disk (uninstalled
by deleting the file directly rather than through the admin) is silently
skipped from the list — the same defensive check
`ControllerExtensionExtensionShipping::getList()` already performs for
its own listing.

## Why a merge-then-save toggle, not `editSettingValue()`

`ModelSettingSetting::editSettingValue($code, $key, $value)` looks like
the obvious one-line toggle, but it is a plain `UPDATE ... WHERE code =
... AND key = ...` with **no INSERT fallback** — it silently updates zero
rows if that key has never been saved before (e.g. an extension installed
but never opened/saved once). `getSetting($code)` +
`editSetting($code, $merged)` (merge-then-save) is used instead — the
same pattern `ControllerMintcartWizard::saveStoreStep()` and
`ControllerMintcartBusinessContacts::save()` already establish for the
identical reason: `editSetting()` DELETEs every existing row for a
setting code before reinserting only the keys it's handed, so toggling
just the `*_status` key without re-supplying every other key that
extension already has saved (cost, tax class, geo zone, ...) would
silently wipe them on the very first toggle.

## Why the legacy admin theme, not `mc-*`/Bootstrap 5

Same reason `ControllerDesignHomepage`
(`docs/developer/blocks/homepage-admin.md` "Why this screen uses the
legacy admin theme") and `ControllerExtensionDashboardMintcartReadiness`
(`docs/developer/onboarding/store-readiness.md` "Dashboard widget") both
already give: the shared admin `header`/`column_left`/`footer` chrome
wraps every admin screen, and MintCart's own compiled asset bundle
(`assets/dist/{css,js}`) has never been enqueued into it anywhere in this
repo — doing so here would load Bootstrap 5 CSS globally alongside the
legacy admin theme's Bootstrap 3 CSS on every other admin screen too,
which `CLAUDE.md` §10 forbids.

## Why two near-duplicate controllers, not one shared base class

`ControllerMintcartBusinessDelivery`/`...Payments` are nearly identical —
same `getExtensionRows()`/`toggle()`/`validateToggle()` shape, differing
only in the extension `type` string and a few text keys. This mirrors
stock OpenCart's own precedent for the exact same problem:
`admin/controller/extension/extension/shipping.php` and `.../payment.php`
are themselves two separate, near-identical files, not one parameterized
class. A shared base class here would need to extend OpenCart's own
`Controller` (for `$this->load`/`$this->model_*`/`$this->language`
magic-property access), which no other `MintCart\*` namespaced class in
this codebase does — every other Mint library class (`ActiveTheme`,
`ComponentRenderer`, `ProductViewProvider`, ...) is deliberately
OpenCart-independent so it stays unit-testable in isolation. Introducing
the first exception to that pattern for two ~120-line controllers would
cost more than the duplication it removes (`CLAUDE.md` §17/§86 "don't
over-abstract").

## Verified

- `php -l` clean on every touched/added PHP file, re-swept across the
  full `upload/`/`tests/` tree.
- Both `.twig` files' `{% if %}`/`{% endif %}`/`{% for %}`/`{% endfor %}`
  tag counts checked balanced (5/5, 2/2 each), same script-based
  technique used for every `.twig` file in this repo.
- Full `tests/mintcart/**/*-test.php` suite re-run end to end — unaffected
  (no new pure logic to test — this increment is OpenCart controller/Twig
  wiring around existing `ModelSettingExtension`/`ModelSettingSetting`
  calls, the same "no new unit test" precedent
  `ControllerExtensionDashboardMintcartReadiness` already set), still
  passing, no regressions.
- No JS/CSS touched — `npm run build` not re-run for this increment.

**Not verified:** no live MySQL/admin session in this environment — a
human should confirm both menu entries appear once
`mintcart/business/delivery`/`mintcart/business/payments` access is
granted, each list shows the store's real installed shipping/payment
extensions with the correct enabled/disabled state, the toggle button
flips status and redirects back correctly, "Configure" opens that
extension's real settings form unchanged, and — most importantly — that
toggling status does **not** disturb any of that extension's other saved
settings (cost, tax class, geo zone, API keys, ...). See
`docs/MANUAL_VERIFICATION.md`'s new Phase 14 entry.

## Next step

- A "Business → Contacts" facade (if not already shipped by a parallel
  increment — check `docs/IMPLEMENTATION_STATUS.md`'s Phase 14 section).
- Once merged alongside a Contacts facade, reconcile the two "Business"
  menu-group definitions in `column_left.php` into one (expected small
  conflict, same shape every parallel Wizard-step/entity-picker increment
  in this project has already reconciled).
- Wiring Phase 13's still-unbuilt `ready` Wizard step to
  `MintCart\Onboarding\StoreReadiness` (already shipped, Phase 14).
