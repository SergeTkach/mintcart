# Business Settings — `Business → Contacts`

`MINTCART_PRODUCT_VISION.md` §6.1/§8, `MINTCART_FUNCTIONAL_SCOPE.md` §3,
`MINTCART_TECHNICAL_PLAN.md` §23, `MINTCART_IMPLEMENTATION_GUIDE.md` §56.

A UI facade over standard OpenCart storage — **not** a second data store
(`CLAUDE.md` §6/§39). `Business → Contacts` is the first of three planned
facades (`Contacts`, `Delivery`, `Payments`); `Delivery`/`Payments` are not
built yet (see "Next step" below).

PHP: `upload/admin/controller/mintcart/business/contacts.php`
(class `ControllerMintcartBusinessContacts`, route
`mintcart/business/contacts`)

View: `upload/admin/view/template/mintcart/business/contacts_form.twig`

Language: `upload/admin/language/en-gb/mintcart/business/contacts.php`

Menu: `upload/admin/controller/common/column_left.php`'s new "Business"
group (permission-gated the same way every other menu group already is —
the entry only appears once `mintcart/business/contacts` access is
granted).

## Why this exists alongside the Setup Wizard

`ControllerMintcartWizard`'s Store and Contacts steps
(`docs/developer/onboarding/setup-wizard.md`) already collect
`config_telephone`/`config_email`/`config_address` and social profile
links — but only as a one-time onboarding flow. A shop owner who wants to
update their phone number six months later shouldn't have to re-walk the
Wizard. `Business → Contacts` is that "come back any time" screen,
reachable from the normal admin nav, editing the **exact same** storage:

- `config_telephone`/`config_email`/`config_address` — OpenCart's own
  `config` setting keys, merge-then-saved the same way
  `ControllerMintcartWizard::saveStoreStep()` already does (see below).
- `mintcart_contacts_social` — the Mint-specific setting the Wizard's
  Contacts step introduced, in the exact `{network, href}` shape
  `MintCart\Design\FooterViewProvider::build()`'s `$social` parameter
  already expects.

Editing here is immediately visible on the storefront footer (Phase 7) and
to `MintCart\Onboarding\StoreReadiness`'s `contacts` signal (Phase 14) —
one computation/storage, multiple editors, not a parallel copy.

## `MintCart\Onboarding\SocialProfileLinks`

New this run: `upload/system/library/mintcart/onboarding/socialprofilelinks.php`.
A small, pure (no OpenCart dependency), unit-tested helper —
`tests/mintcart/onboarding/social-profile-links-test.php`, 9 scenarios —
extracted specifically so this controller's social-link
validate/normalize/re-key logic doesn't have to be copy-pasted from
`ControllerMintcartWizard`'s own inline version:

- `isValidUrl(string $url): bool` — blank is always valid (every network
  is optional); a filled value must pass `FILTER_VALIDATE_URL` **and**
  start with `http://`/`https://`. The scheme check matters:
  `FILTER_VALIDATE_URL` alone accepts `javascript:...`/`data:...` as
  "valid" (it only checks syntax), and a stored value like that would
  execute script in a storefront visitor's browser the moment they clicked
  the rendered `href` — the same fix the Wizard's own Contacts step
  applied to its inline check.
- `normalize(array $urlsByNetwork): array` — posted `network => url` map
  in, blank-dropped/trimmed `{network, href}[]` list out (NETWORKS order),
  the exact shape written to `mintcart_contacts_social`.
- `byNetwork(array $social): array` — the inverse, re-keying a stored list
  by network so a form can re-populate each field; ignores entries with a
  missing key or a network outside `NETWORKS` rather than trusting storage
  as-is.

### Why this isn't a refactor of `ControllerMintcartWizard`

`ControllerMintcartWizard`'s own Contacts-step validation
(`validateContactsStep()`/`saveContactsStep()`/`loadContactsSocial()`)
does the same three things inline, with its own private `SOCIAL_NETWORKS`
constant — genuine duplication `CLAUDE.md` §38E would normally say to
extract. It's deliberately left alone this run: at the time this was
written, pull request #52 ("Setup Wizard: Contacts step") had
`upload/admin/controller/mintcart/wizard.php` open for a different,
unmerged change. Retargeting that controller at `SocialProfileLinks` now
would touch the same file #52 is touching, risking a merge collision for
no functional benefit this run (the Wizard's own copy already works and
is already tested). `SocialProfileLinks` exists so a **future** run can
fold the Wizard's copy into it once #52 has landed, without this screen
needing to change again.

## Validation / save (mirrors `ControllerMintcartWizard::saveStoreStep()`)

`config_telephone`/`config_email`/`config_address` are **merged**, never
bare-saved: `ModelSettingSetting::editSetting()` DELETEs every existing row
for a setting code before re-inserting only the keys it's handed, so
saving a 3-field partial without merging the store's full existing
`config` settings first would silently wipe out every other `config_*`
setting (store name, currency, tax, checkout, ...) on save. Checked
against the real model (`upload/admin/model/setting/setting.php`) before
writing this, not assumed from memory (`CLAUDE.md` §84/§10) — same trap
`ControllerMintcartWizard::saveStoreStep()`'s own doc comment already
documents.

`mintcart_contacts_social` has no such trap: nothing else writes to the
`mintcart_contacts` setting code, so `editSetting('mintcart_contacts',
[...])` is a plain, safe overwrite of that one key.

Validation rules (identical to the Wizard's Store/Contacts steps, so a
value valid in one screen is valid in the other):

- Telephone: 3–32 characters.
- Email: `FILTER_VALIDATE_EMAIL`, max 96 characters.
- Address: 3–256 characters.
- Each social URL: blank, or `SocialProfileLinks::isValidUrl()`.

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
which `CLAUDE.md` §10 forbids. This screen uses the same
`.panel`/`.form-horizontal`/`.has-error`/`.text-danger` markup every other
legacy admin form (`setting/setting.twig`, `marketing/contact.twig`)
already uses.

## Verified

- `php -l` clean on every touched/added PHP file, and re-swept across the
  full `upload/`/`tests/` tree.
- `tests/mintcart/onboarding/social-profile-links-test.php` (9 scenarios:
  blank valid, http(s) valid, `javascript:`/`data:` rejected, garbage
  rejected, `normalize()` order/blank-dropping, empty input, `byNetwork()`
  inverse, malformed-entry defensiveness, round-trip) — passing.
- Full `tests/mintcart/**/*-test.php` suite re-run end to end (35 files,
  was 34) — all passing, no regressions.
- `contacts_form.twig`'s `{% if %}`/`{% endif %}`/`{% for %}`/`{% endfor
  %}` tag counts checked balanced (16/16, 1/1), same script-based
  technique used for every `.twig` file in this repo.
- `npm`/esbuild not touched — no JS/CSS changed by this increment.

**Not verified:** no live MySQL/admin session in this environment — a
human should confirm the menu entry appears once
`mintcart/business/contacts` access is granted, the form saves/re-populates
correctly (including social links), and that a change here is immediately
reflected in both the live storefront footer and
`mintcart/readiness`'s `contacts` signal. See `docs/MANUAL_VERIFICATION.md`'s
new Phase 14 entry.

## Next step

`Business → Delivery` and `Business → Payments` — each reads the standard
OpenCart shipping/payment extension list (`extension` table +
`model_setting_extension`) and presents a simplified enable/configure
view, per `MINTCART_TECHNICAL_PLAN.md` §23 ("не створювати другу shipping
subsystem"). Neither is started. Also: once PR #52 (Wizard Contacts step)
merges, retarget `ControllerMintcartWizard`'s own inline social-link
validation at `MintCart\Onboarding\SocialProfileLinks` to remove the
now-temporary duplication this doc describes above.
