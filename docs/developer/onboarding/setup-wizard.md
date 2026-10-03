# Setup Wizard (Phase 13)

`MINTCART_FUNCTIONAL_SCOPE.md` §2, `MINTCART_IMPLEMENTATION_GUIDE.md` §54.
Eight working steps so far — **Welcome**, **Store**, **Contacts**,
**Language & currency**, **Delivery**, **Payment**, **First product**, and
**Ready** (the Store Readiness checklist, Phase 14's `StoreReadiness`
service surfaced inside the Wizard flow rather than only as a standalone
dashboard widget). The remaining step (`design`) is listed in the step
nav as a roadmap but has no real content yet — visiting or submitting
it falls back to the live resume point (`CLAUDE.md`
§85: no UI ahead of a real, working consumer). `currentStep()` walks
`IMPLEMENTED_STEPS` in its own order (`['welcome', 'store', 'contacts',
'language_currency', 'delivery', 'payment', 'first_product', 'ready']`),
which today is `STEPS` minus the still-unimplemented `design` — it
doesn't have to be a prefix in general (an earlier revision of this file
had `ready` implemented ahead of
`contacts`/`language_currency`/`delivery` for a time), it only has to list
which steps have real content, in the order a store should complete them.

PHP:
- `upload/system/library/mintcart/onboarding/wizardprogress.php` —
  `MintCart\Onboarding\WizardProgress`
- `upload/admin/controller/mintcart/wizard.php` —
  `ControllerMintcartWizard`, route `mintcart/wizard`

Language: `upload/admin/language/en-gb/mintcart/wizard.php`

Twig: `upload/admin/view/template/mintcart/wizard.twig`

Test: `tests/mintcart/onboarding/wizard-progress-test.php` (12 scenarios)

Fully additive — no existing OpenCart controller, model or template is
modified. Same "0 controller patches" shape as `ControllerMintcartCart`
(Phase 15).

## `WizardProgress`

Pure step-sequencing logic, no OpenCart dependency (same shape as
`ProductViewProvider`/`FooterViewProvider`):

```php
$progress = new \MintCart\Onboarding\WizardProgress($completedSteps);

$progress->steps();          // full roadmap, in canonical order
$progress->isImplemented($step);
$progress->isCompleted($step);
$progress->currentStep();    // first implemented-but-not-completed step, or null
$progress->isReady();        // true once every implemented step is completed
$progress->withCompleted($step); // returns the completed-list to persist next
```

It never mutates itself and never touches storage — the controller loads
the completed-steps array, builds a `WizardProgress`, and persists whatever
`withCompleted()` returns.

## Why `oc_setting`, not a new `mc_*` table

Wizard progress ("which steps are done") is genuinely Mint-specific state —
stock OpenCart has no concept of a setup wizard — but it's a handful of
key/value flags, not a new entity. OpenCart's own `setting` table (backing
`ModelSettingSetting::getSetting()`/`editSetting()`) already exists exactly
for this shape of data (it's how every module's settings and the global
`config_*` values are stored today), so this reuses it under a dedicated
`mintcart_wizard` code rather than adding a migration + table for four
booleans' worth of state (`CLAUDE.md` §17 — no abstraction the general
mechanism already covers).

Stored as:
- `mintcart_wizard_completed_steps` — JSON-serialized array of completed step
  codes (via `editSetting()`'s existing `serialized` column, the same
  mechanism module array-settings already use)

The **Store** step's own data (`config_name`/`config_email`/
`config_telephone`/`config_address`) is *not* Mint-specific — it's exactly
what `Settings → Store` already manages — so it's written straight into the
standard `config` setting code, not duplicated into a Mint table
(`CLAUDE.md` §6/§39).

## The `editSetting()` partial-write trap

`ModelSettingSetting::editSetting($code, $data)` **deletes every existing
row for `$code` first**, then re-inserts only the keys present in `$data`.
`ControllerSettingSetting::index()` gets away with calling
`editSetting('config', $this->request->post)` because that POST body is the
*entire* Settings → Store form (hundreds of fields). Calling
`editSetting('config', $partialFourFieldArray)` directly — which is what a
naive Wizard Store-step save would do, since it only collects four fields —
would silently **wipe out every other `config_*` setting** (currency, tax,
checkout, SMTP, ...) on the very first Wizard save.

`ControllerMintcartWizard::saveStoreStep()` avoids this by reading the
store's full existing `config` settings first and merging the four new
values on top before calling `editSetting()`:

```php
$existing = $this->model_setting_setting->getSetting('config');
$merged = array_merge($existing, [
    'config_name'      => ...,
    'config_email'     => ...,
    'config_telephone' => ...,
    'config_address'   => ...,
]);
$this->model_setting_setting->editSetting('config', $merged);
```

This was caught by reading the actual model implementation before writing
the save path (`CLAUDE.md` §84/§10 — never assume an existing OpenCart flow
from memory), not discovered after the fact.

## The Contacts step — social profile links

`Contacts` collects up to four optional social profile URLs (Facebook,
Instagram, Twitter/X, YouTube). All four are optional — a store with no
social presence yet is a completely valid, finishable state; a filled-in
field must pass `filter_var(..., FILTER_VALIDATE_URL)` **and** start with
`http://`/`https://`. The scheme check is deliberate, not redundant:
`FILTER_VALIDATE_URL` only checks syntax, so it happily accepts
`javascript:...`/`data:...` as "valid" — without the extra check, a stored
link like that would execute script in a storefront visitor's browser the
moment they clicked it (this value is later rendered as a plain `href` on
the live footer).

Unlike Store, this data is genuinely Mint-specific — OpenCart has no
built-in storage for social profile links — so it's saved under its own
`mintcart_contacts` setting code (`mintcart_contacts_social`, a
JSON-serialized list of `{network, href}`) rather than merged into
`config`. There's no `editSetting()` partial-write trap to guard against
here the way `saveStoreStep()` has to for `config`: nothing else writes to
`mintcart_contacts`, so a bare `editSetting('mintcart_contacts', [...])`
call is safe.

The stored shape is deliberately identical to
`MintCart\Design\FooterViewProvider::build()`'s `$social` parameter
(`docs/developer/components/footer.md`) — `ControllerCommonFooter::index()`
now reads this same setting and passes it straight through with no
reshaping, so a social link saved here renders on the live storefront
footer without any translation step. This closes the "no storage yet" gap
Phase 7's Footer sub-step originally left for `$social` (payments and
newsletter remain unimplemented — no Business Settings/Commerce API
consumer for either yet).

## Language & currency step

`config_language`/`config_currency` are two more keys in the same standard
`config` setting row `saveStoreStep()` already merges into — exactly the
fields `Settings → Store`'s own General tab manages
(`upload/admin/controller/setting/store.php`, checked before writing this
step per `CLAUDE.md` §84/§10), not a new Mint-owned duplicate
(`CLAUDE.md` §6/§39). `saveLanguageCurrencyStep()` reuses the same
merge-then-save shape `saveStoreStep()` established, for the same reason:
`editSetting()` deletes every existing row for a code first, so a bare
two-field write would wipe out every other `config_*` setting.

Validation (`validateLanguageCurrencyStep()`) checks the submitted code
against `ModelLocalisationLanguage::getLanguageByCode()`/
`ModelLocalisationCurrency::getCurrencyByCode()` — it must be a code that
actually exists in `oc_language`/`oc_currency`. It deliberately does **not**
also require `status = 1` (enabled): `Settings → Store`'s own language/
currency dropdowns (`getLanguages()`/`getCurrencies()` with no arguments)
list every row regardless of status, so this step mirrors that screen's
existing permissiveness rather than inventing a stricter rule that screen
doesn't itself enforce.

The step's `<select>` options come from the same `getLanguages()`/
`getCurrencies()` calls `Settings → Store` uses, keyed by `code`.

## The Delivery step reuses Business → Delivery's data, not a second toggle system

`Delivery` shows the store's installed shipping extensions
(`ModelSettingExtension::getInstalled('shipping')`, filtered to extensions
that still have a real controller file on disk — the same defensive check
`ControllerMintcartBusinessDelivery::getExtensionRows()` established in
Phase 14) with each one's enabled state read from the exact
`shipping_<code>_status` config key every stock shipping extension
controller already saves under. A "Manage delivery methods" link points at
the full `Business → Delivery` screen (`mintcart/business/delivery`) for
anyone who wants the complete list/experience outside the Wizard; cost, tax
class, geo zone and every other per-extension setting still live only on
that extension's own `extension/shipping/<code>` screen — this step
(like `Business → Delivery` before it) never becomes a second shipping
subsystem (`MINTCART_TECHNICAL_PLAN.md` §23).

Enabling/disabling a method happens through its own dedicated action,
`toggleDelivery()` (route `mintcart/wizard.toggleDelivery`, POST, redirects
back to `?step=delivery`) — a small, deliberate duplicate of
`ControllerMintcartBusinessDelivery::toggle()`'s merge-then-save logic
(`getSetting('shipping_<code>')`, flip the `_status` key, `editSetting()`
the merged result — never a bare `editSetting('shipping_<code>',
['..._status' => ...])`, which would wipe the extension's other settings,
the same `editSetting()` partial-write trap documented above for `config`).
This mirrors the project's own precedent for `Business → Delivery` vs.
`Business → Payments` themselves — `docs/developer/onboarding/business-delivery-payments.md`
explicitly chose two small near-duplicate controllers over a shared base
class for this exact shape of logic (`CLAUDE.md` §17) — extended here to a
third call site rather than introducing a shared service three controllers
would depend on for one method's worth of logic.

One deliberate behavioral difference from `ControllerMintcartBusinessDelivery::toggle()`:
that action has its own explicit `hasPermission('modify', 'mintcart/business/delivery')`
check; `toggleDelivery()` does not, matching the rest of
`ControllerMintcartWizard` having no `hasPermission()` gate anywhere (see
below) rather than partially adopting Business → Delivery's stricter
posture for just this one action.

Submitting the step itself (`POST step=delivery`, the "Continue" button)
has **no required fields and nothing of its own to save** — enabling a
shipping method is a side effect of `toggleDelivery()`, not something the
Continue submission collects, and Functional Scope §2 explicitly allows
"optional steps" to be skipped, so a store that hasn't enabled any shipping
method yet can still continue past this step. The Ready step's own
`StoreReadiness` checklist (`shipping`) is what actually surfaces "no
shipping method enabled yet" as outstanding, independent of whether this
Wizard step itself has been marked completed.

## The Payment step mirrors Delivery exactly

`Payment` is the same shape as `Delivery` above, for the `payment`
extension type instead of `shipping`: `loadPaymentExtensions()` lists the
store's installed payment extensions
(`ModelSettingExtension::getInstalled('payment')`, filtered to extensions
that still have a real controller file on disk, the same defensive check
`loadShippingExtensions()` uses) with each one's enabled state read from
`payment_<code>_status` — the exact config key
`ControllerMintcartBusinessPayments::getExtensionRows()` (Phase 14) already
reads/writes for the same extensions. A "Manage payment methods" link
points at the full `Business → Payments` screen
(`mintcart/business/payments`) for the complete list/experience; every
other per-extension setting still lives only on that extension's own
`extension/payment/<code>` screen — this step never becomes a second
payment subsystem (`MINTCART_TECHNICAL_PLAN.md` §23), same as `Delivery`.

Enabling/disabling a method happens through `togglePayment()` (route
`mintcart/wizard.togglePayment`, POST, redirects back to `?step=payment`)
— the same merge-then-save shape `toggleDelivery()` uses
(`getSetting('payment_<code>')`, flip the `_status` key, `editSetting()`
the merged result), and the same deliberate small duplicate rather than a
shared helper (`CLAUDE.md` §17/§38E — three controllers now share this
exact shape: `ControllerMintcartBusinessDelivery::toggle()`,
`ControllerMintcartBusinessPayments::toggle()`, and this one).

Submitting the step itself (`POST step=payment`, "Continue") has no
required fields and nothing of its own to save, for the same reason
`Delivery`'s Continue submission doesn't: enabling a payment method is
`togglePayment()`'s job, and Functional Scope §2 allows this optional step
to be skipped — the Ready step's own `StoreReadiness` checklist
(`payment`) is what surfaces "no payment method enabled yet" as
outstanding.

## The First product step writes a real, ordinary product

`validateFirstProductStep()`/`saveFirstProductStep()` only ask for
`product_name` (required) and `price` (required, must be numeric and
non-negative) plus two optional fields (`quantity`, `description`) — not
the full admin product form. Every other field
`ModelCatalogProduct::addProduct()` requires (checked against
`upload/admin/model/catalog/product.php` before writing this, per
`CLAUDE.md` §84/§10) gets a safe, ordinary default: no model/SKU/barcodes,
available from today, a physical/shippable item, sold at store 0, zero
weight/dimensions, the store's own existing `config_weight_class_id`/
`config_length_class_id` settings (not a new Mint-owned duplicate,
`CLAUDE.md` §39), and `status = 1` (enabled) so the product immediately
counts toward `MintCart\Onboarding\StoreReadiness`'s own `first_product`
signal (`ModelMintcartReadiness::getSignals()`, `filter_status => 1`) and
is visible on the storefront right away — the point of this step is a
store that can actually take a test order once it's done.

`product_description` is written once per installed language
(`ModelLocalisationLanguage::getLanguages()`), all with the same
submitted name/description — this step collects one name, not a
per-language form, the same simplification every other single-value
Wizard field (`config_name`, etc.) already makes; a store that needs
per-language product copy edits it from `Catalog → Products` afterward,
same as any other OpenCart product.

`loadDefaultStockStatusId()` picks whichever installed stock status is
actually *named* "In Stock" (never the seed data's hardcoded id `7` —
a store that edited/reordered `Localisation → Stock Statuses` is trusted
over the install seed), falling back to the first available row. There is
no standard OpenCart "default stock status" config setting to reuse the
way weight/length class have one (`Settings → Option` has no such field
either), so this is the one genuinely new default-selection rule in this
step.

This step collects **no** image/options/categories/SEO keyword — all
editable later from `Catalog → Products`, consistent with
`MINTCART_PRODUCT_VISION.md` §5 ("ask only what the user actually needs
to decide right now"). Submitting with `first_product_action=skip` (the
"Skip for now" button) bypasses validation and saving entirely and just
marks the step completed with no product created, per Functional Scope
§2's "optional steps can be skipped" — the same precedent `delivery`
already set for a step with no required outcome.

## The `ready` step reuses `StoreReadiness`, it doesn't recompute it

`ControllerMintcartWizard::loadReadiness()` calls the exact same
`MintCart\Onboarding\StoreReadiness::evaluate()` + `ModelMintcartReadiness::getSignals()`
pair `ControllerMintcartReadiness` (the `mintcart/readiness` JSON endpoint,
Phase 14) already uses, with the same checklist labels loaded from that
same controller's own language file (`mintcart/readiness`, not a second
copy of the seven `text_check_*` strings in `mintcart/wizard`'s language
file) — one computation, two callers, per `StoreReadiness`'s own docblock
("`ModelMintcartReadiness` ... no new Mint-specific table for any of
this ... dashboard widget, Wizard 'Ready' step ... not a second copy of
the computation"). This deliberately calls the model directly rather than
hitting the `mintcart/readiness` route internally, so it does **not** go
through that controller's own `hasPermission('access', 'mintcart/readiness')`
gate — consistent with the rest of `ControllerMintcartWizard` having no
`hasPermission()` gate of its own (see below).

The step renders `readiness.percent` through the `mc-progress` component
(`docs/developer/components/progress.md` — it explicitly lists "a Setup
Wizard step indicator" as an intended use case) plus a plain checklist
list, each item badged `mc-badge--success`/plain via `check.complete`. No
new component needed — `mc-progress`, `mc-badge`, `mc-card` are all
already-shipped Phase 4/11 primitives. Submitting the step (`POST
step=ready`) has no fields to validate/save (unlike `store`) — it only
marks `ready` completed, the same generic `withCompleted()` path every
step (including the still-unimplemented ones) will eventually use.

The terminal "You're off to a great start" screen (`is_ready` — shown once
every *implemented* step, `ready` included, is completed) names only the
steps still genuinely unimplemented in its "coming in a future update"
list — `delivery`, `payment` and `first_product` were each dropped when
that step landed; today it names only `design`.
It is not redundant with the `ready` step's own checklist — one is "here
is how ready your real store is today", the other is "there is no more
Wizard content to show you right now, go to your dashboard".

## Why a standalone page, not the usual admin chrome

`upload/admin/view/template/mintcart/wizard.twig` is a complete, self-
contained HTML document — it does **not** include
`common/header`/`common/column_left`/`common/footer`, and
`ControllerMintcartWizard` never calls `$this->load->controller('common/...')`
the way `ControllerCommonDashboard` does.

This is deliberate, not an oversight: every other admin page shares the
legacy Bootstrap 3 + jQuery + Font Awesome chrome those common controllers
render, and a previous attempt to add a plain `<link>` to
`assets/dist/css/index.min.css` on a page sharing that chrome broke live
Bootstrap 3 widgets (see `docs/IMPLEMENTATION_STATUS.md`'s Phase 15 "human
session" update — reverted, not theoretical). A brand-new, standalone
Wizard page has no legacy markup to conflict with, so it can safely load
**only** Bootstrap 5 + `mc-*` — nothing else. This is the first live page in
MintCart to actually enqueue `assets/dist/css/index.min.css`.

The page intentionally ships CSS-only (no `<script>` tag) — nothing on the
Welcome/Store steps needs JS (plain server-rendered forms, full-page
POST → redirect → GET), so there's no reason to also try enqueuing
`assets/dist/js/admin.min.js` on a live page in the same increment. That
remains open for whichever future step first needs client-side behavior
(e.g. an Entity/Media Picker-driven step).

Asset path: `HTTP_CATALOG . 'assets/dist/css/index.min.css'` — `HTTP_CATALOG`
is the same constant `ControllerCommonHeader` already uses for storefront
links from within admin; `assets/` is a sibling of `admin/`/`catalog/` under
`upload/` (see `.gitignore`'s own comment on the `upload/assets/` dev-time
copy), so `HTTP_CATALOG` (the storefront's own base URL) is the correct base
to resolve it from, not `HTTP_SERVER` (admin's own base, one directory
level off).

## No `hasPermission()` gate

Unlike most admin controllers, `ControllerMintcartWizard::index()` does not
call `$this->user->hasPermission(...)`. This is meant to be the first screen
a freshly-installed admin sees — the same reasoning
`ControllerCommonDashboard::index()` already relies on (Dashboard has no
permission gate either), since a brand-new install's default user group may
not have an explicit grant for a route that didn't exist when that group's
permissions were seeded. A valid admin session (`user_token`) is still
required, same as every other admin route — this only skips the *additional*
per-action permission check.

## Step-order enforcement

`ControllerMintcartWizard::resolveViewableStep()` (GET) and `handleSubmit()`
(POST) both re-derive the current resume step from stored progress and
reject anything else — a `?step=` query param or a POST `step` field can
never be used to view or submit a step ahead of what's actually been
completed. Re-visiting an already-completed step (e.g. `store` after it's
done) is allowed, for review; skipping ahead is not.

## What's explicitly not done yet, by design

- The `design` step needs its own real form/save logic (likely a preset
  picker once `docs/adr/004-design-token-presets.md`'s preset mechanism
  has a live admin UI consumer).
- The First product step has no image upload (same `McMediaPicker`
  upload-endpoint gap as Store's logo field, see below) and no
  category/manufacturer assignment — both deliberately out of scope for
  "just enough to take a test order"; a human should edit the created
  product from `Catalog → Products` to add either.
- Store step's **logo** upload (Functional Scope §2 lists it under Store) —
  deferred; needs `McMediaPicker` (Phase 11, already shipped) wired to a
  real upload endpoint, which doesn't exist yet.
- No link to `mintcart/wizard` exists anywhere in the admin UI yet (no
  redirect-after-install, no dashboard entry point) — reaching it today
  requires navigating to the route directly. Wiring that in is a natural
  next step once more of the flow exists, or could land standalone.
- `assets/dist/js/admin.min.js` is still not enqueued anywhere (CSS-only
  page, see above) — same pre-existing gap as the rest of the admin, just
  scoped out of this increment rather than solved.
- No live end-to-end request test — same pre-existing gap noted throughout
  this repo (no MySQL/OpenCart bootstrap in this environment).
  `WizardProgress` (the only OpenCart-independent logic) is unit-tested
  standalone; the controller itself is `php -l`-clean and manually reviewed
  against the real `ModelSettingSetting` implementation, same verification
  depth every other controller-wiring increment in this repo has used.
