# Setup Wizard (Phase 13)

`MINTCART_FUNCTIONAL_SCOPE.md` §2, `MINTCART_IMPLEMENTATION_GUIDE.md` §54.
Three working steps so far — **Welcome**, **Store**, and **Ready** (the
Store Readiness checklist, Phase 14's `StoreReadiness` service surfaced
inside the Wizard flow rather than only as a standalone dashboard widget).
The rest of the flow (`contacts` → `language_currency` → `delivery` →
`payment` → `design` → `first_product`) is listed in the step nav as a
roadmap but has no real content yet — visiting or submitting any of those
steps falls back to the live resume point (`CLAUDE.md` §85: no UI ahead of
a real, working consumer). Note `ready` sits last in `WizardProgress::STEPS`
but is implemented ahead of the steps before it in the canonical order —
`IMPLEMENTED_STEPS` doesn't have to be a strict prefix of `STEPS`, it only
has to list which steps have real content today; `currentStep()` walks
`IMPLEMENTED_STEPS` in its own order, so completing `store` advances
straight to `ready`, skipping the unimplemented steps between them, same
as it already skipped nothing when only `welcome`/`store` existed.

PHP:
- `upload/system/library/mintcart/onboarding/wizardprogress.php` —
  `MintCart\Onboarding\WizardProgress`
- `upload/admin/controller/mintcart/wizard.php` —
  `ControllerMintcartWizard`, route `mintcart/wizard`

Language: `upload/admin/language/en-gb/mintcart/wizard.php`

Twig: `upload/admin/view/template/mintcart/wizard.twig`

Test: `tests/mintcart/onboarding/wizard-progress-test.php` (9 scenarios)

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

The pre-existing terminal "You're off to a great start" screen
(`is_ready` — shown once every *implemented* step, `ready` included, is
completed) is intentionally left as-is: its "the rest of the Setup Wizard
... is coming in a future update" copy remains accurate (`contacts`
through `first_product` are still unimplemented), and it is not
redundant with the `ready` step's own checklist — one is "here is how
ready your real store is today", the other is "there is no more Wizard
content to show you right now, go to your dashboard".

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

- The `contacts`/`language_currency`/`delivery`/`payment`/`design`/
  `first_product` steps — each needs its own real form + save logic,
  following the same shape `store` established.
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
