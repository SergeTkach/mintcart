# MintCart — Implementation Status

Tracks progress against the phases defined in `MINTCART_TECHNICAL_PLAN.md` §42 and the
starting sequence in `CLAUDE.md` §21. Update this file at the end of every work
increment (see `CLAUDE.md` §14/§20).

**Review correction (2026-09-16, Codex, PR #115):** manufacturer picker wiring
now follows Implementation Guide §§14–18/21: data hooks, token-based spacing,
and a text trigger without a legacy icon dependency. Existing OpenCart
field IDs/names are preserved. Hidden panels explicitly override flex display;
the trigger exposes its expanded state and regains focus after confirmation.
Verified: frontend production build and preset contract checks passed; an
isolated Node harness exercised the actual inline handler's open/close,
selection, focus, request parameters/abort signal, and missing-bundle paths.
This is not a live admin/browser test; that check remains outstanding.

**Review correction (2026-09-16, Codex, PR #114):** corrected the controller
comments, SEO documentation, fix registry and manual checklist to describe
the actual title-only fallback. Neither call site supplies a fallback
description; blank descriptions remain blank and the shipped headers omit
their meta tag. Runtime PHP behavior is unchanged. Verified with PHP 8.3.30:
both controllers pass syntax checks and all 9 MetaBuilder smoke scenarios pass.

Legend: `[x]` done, `[~]` in progress / partially done, `[ ]` not started.

## Phase 0 — Audit

- [x] Audit repository (upstream version, modified files, OCMOD, themes, modules, DB,
      PHP fixes, dependencies) — see `docs/audit.md`
- [x] Create/update `docs/audit.md`
- [x] Create/update `docs/IMPLEMENTATION_STATUS.md` (this file)
- [x] Record the exact OpenCart upstream baseline — OpenCart **3.0.5.1**, confirmed
      clean/unmodified (see `docs/audit.md` §1–§2)
- [x] Identify modified OpenCart core files — none found beyond the documented
      InnoDB schema normalization (see `docs/audit.md` §2)
- [x] Identify OCMOD dependencies — none present (see `docs/audit.md` §3)
- [x] Identify existing custom themes/modules/fixes — none present; only the stock
      `default` theme and stock extensions (see `docs/audit.md` §4–§5)

Phase 0 is **complete**.

**Update (2026-09-29, routine run): repo-integrity fix — missing `Config`
engine class restored.** Step 0 dedup check: `list_pull_requests` returned
15 open PRs (`#138`-`#152`) at the start of this run; none touch
`.gitignore`, `upload/system/library/config.php`, or any file this
increment adds — read every title, and confirmed the closest ones
(`#149` admin update route, `#144` Diagnostics JSON surface) don't overlap.
None of the hot shared files from §0f (Homepage Builder block-type
registry, `block.css`) are touched either.

While investigating a genuinely separate next-step candidate (wiring
`MintCart\Seo\HreflangBuilder` — see that class's own "Why no live wiring
yet" section — which needs `Config::set()`/`get()` to build a
per-language alternate URL), tracing `system/framework.php`'s `new
Config()` call turned up that `upload/system/library/config.php` (stock
OpenCart's `Config` engine class) does not exist anywhere in this
repository, was never committed even in the initial Phase 0 baseline, and
is not merely absent from this checkout — `git log --all` has no history
of it at all. Root cause: `.gitignore`'s `upload/**/config.php` rule
(intended only for the two installer-generated,
DB-credential-holding files, `upload/config.php`/`upload/admin/config.php`)
also matches this one stock framework class file — the exact same
false-positive-glob class already caught and fixed twice before for
`upload/**/test*` (`test_theme/`, then `testimonialsblockprovider.php`/
`testimonials.twig` — see the Phase 8/10 sections above). Every request
would fatal on `new Config()` in `system/framework.php` on a real install;
this had gone unnoticed because no environment used by any routine run so
far has a live PHP/MySQL server to actually boot the app (the same
"not verified live" gap noted throughout this file).

**Fix:** added a fourth targeted `!upload/system/library/config.php`
negation to `.gitignore`, alongside the existing three. Restored the file
byte-for-byte from `https://raw.githubusercontent.com/opencart/opencart/3.0.5.1/upload/system/library/config.php`
(the documented upstream baseline tag, `docs/audit.md` §1) rather than
reconstructing it from memory (`CLAUDE.md` §10/§84) — confirmed via `git
diff`-equivalent that stock, already-present, unmodified sibling files in
the same directory (`url.php`) already match that same tag's content, so
the tag itself is the right reference. Checked every other stock
`config.php` path under `upload/` against the same tag
(`system/config/default.php`/`admin.php`/`catalog.php` are not literally
named `config.php` so unaffected; `upload/config.php`/`upload/admin/config.php`
don't exist in the source tag either, since OpenCart's installer generates
them — correctly still gitignored) — confirmed `system/library/config.php`
was the only victim. This is a repository-integrity fix, not an OpenCart
bug, so it is documented here and in `docs/audit.md`'s "Correction" note
rather than `docs/fixes/registry.md` (reserved for OpenCart-side bugs per
`CLAUDE.md` §13) or `docs/upstream-diff.md` (reserved for deliberate core
*modifications*; this restores stock content unmodified).

Verified: `php -l` clean on the restored file and across the entire
`upload/` tree (no other file referenced a symbol this file's absence
would have hidden a second failure behind); `git check-ignore -v
upload/system/library/config.php` now correctly reports it as *not*
ignored. **Not verified:** no live PHP/MySQL environment here to actually
boot `index.php`/`admin/index.php` and confirm the fatal is gone in
practice — a human should do that smoke check on a real install before
trusting this beyond static inspection, same pre-existing gap as every
other increment in this file. **Next candidates:** the `HreflangBuilder`
per-language URL resolver this investigation was originally headed toward
(now unblocked from a "can I even trust `Config::set()` exists" standpoint,
though the resolver itself is still separate, real work — see that class's
own doc's "Why no live wiring yet"); the per-store variant of `Design →
System Pages` (`#146`'s own next-candidates note); the admin
`mintcart/update/*` route (`#149`, already open); or re-check `gh pr list
--state open` first, since this backlog moves fast.

## Phase 1 — Foundation

- [x] Create MintCart PHP namespace/bootstrap (`MintCart\`) — `MintCart\Core\Bootstrap`,
      autoloaded via OpenCart's existing `library()` autoloader
      (`upload/system/library/mintcart/core/bootstrap.php`); see `docs/adr/001-mintcart-core-foundation.md`
- [x] Service container / service registry bridge into OpenCart's existing registry —
      `MintCart\Core\Bootstrap` wraps `\Registry`, no parallel DI container
- [~] OpenCart adapter interfaces (`MintCart\Compatibility\OpenCart3\*`) —
      `CategoryAdapter` (this run) is the first to land with a real, live
      consumer already wired in (`ControllerCatalogCategory::move()`, Phase
      17) — see "Sub-step" below and
      `docs/developer/compatibility/category-adapter.md`.
      `SettingsAdapter`/`ExtensionAdapter`/`CustomerAdapter` exist as open,
      unmerged PRs (#81/#87/#106) at the time of this update, each shipped
      unwired (no real consumer yet, or the consumer's file was contended
      by other open PRs). `ProductAdapter`/`OrderAdapter` remain unstarted.
      **first adapter shipped:** `ExtensionAdapter` (installed-vs-enabled
      extension check, extracted out of `ModelMintcartReadiness`'s inline
      `hasEnabledExtension()`) — see
      `docs/developer/compatibility/extension-adapter.md`. Independent of
      PR #81's `SettingsAdapter` (open at the time of this run, not yet
      merged onto this branch) — no shared file between the two.
- [~] OpenCart adapter interfaces (`MintCart\Compatibility\OpenCart3\*`) — first
      adapter done: `MintCart\Compatibility\OpenCart3\SettingsAdapter`
      (`upload/system/library/mintcart/compatibility/opencart3/settingsadapter.php`),
      wrapping `ModelSettingSetting`'s `getSetting()`/`editSetting()` with a
      merge-then-save `merge()` method — see "Sub-step" below and
      `docs/developer/compatibility/settings-adapter.md`. Not wired into any
      controller yet; `ProductAdapter`/`CategoryAdapter`/`OrderAdapter`/
      `CustomerAdapter`/`ExtensionAdapter` remain unstarted (no consumer yet).
- [x] Migration manager (`mc_migration` table, ordered/logged/apply-once migrations) —
      `MintCart\Core\Migration\MigrationManager` +
      `MintCart\Core\Migration\MigrationInterface`; naming convention and file shape
      documented in `upload/system/mintcart/migrations/README.md`; no MintCart-owned
      table exists yet so the migrations directory is currently empty; covered by
      `tests/mintcart/core/migration-manager-test.php` (in-memory DB double — no
      MySQL server available in this environment; real-DB verification is still
      needed before this is exercised against a live install). **(this run,
      PR #185):** added `docs/developer/core/migration-manager.md` — the
      architecture doc for this runner (Implementation Guide §71's
      "Migrations" developer-doc topic), covering its two real consumers
      (`HealthCheck`'s `pending_migrations` signal,
      `UpdateOrchestrator`'s `migrations` step). No code changed. The
      "Slots"/"Events" doc topics from the same §71 list are intentionally
      still unwritten — neither has an implementation in the codebase yet
      (checked via `grep -rn "data-mc-slot\|mc_slot" upload/`, no matches),
      and per `CLAUDE.md` §85 a doc should not describe a speculative API
      ahead of a real consumer. A future increment's natural next step is
      implementing one concrete Theme API slot with a real template call
      site (e.g. `product.after_actions`, Technical Plan §15) before either
      doc is written.
- [x] **Theme API Slots — `MintCart\Design\SlotRegistry` (this run, closing
      the gap PR #185 flagged just above).** `MintCart\Design\SlotRegistry`
      (`upload/system/library/mintcart/design/slotregistry.php`) is a
      plain, OpenCart-independent `listen()`/`has()`/`render()` registry —
      deliberately not built on `MintCart\Core\Stage`/OpenCart's `\Event`
      (a slot answers a template's "render here" question with markup, a
      different concern than `Stage`'s before/after `$data`-mutation
      hooks around a controller method). First real template consumer:
      `product-card.twig`'s `product.after_actions` slot (both
      `mint_default` and `test_theme` — a genuinely different DOM in each,
      same slot contract, proving the Theme API milestone story extends to
      slots too), wired through `MintCart\Design\ProductCardListRenderer`'s
      new optional 3rd constructor argument (`null` by default — every
      existing call site across Category/Search/Manufacturer/Special/
      Product is byte-for-byte unaffected, same "optional nullable
      collaborator" shape `ComponentRenderer`'s own `$diagnostics`
      argument already established). No controller constructs a real
      `SlotRegistry` yet — `render()` returns `''` for every slot in this
      codebase today, which is the expected, documented state until a real
      extension needs one (`CLAUDE.md` §85), not a gap. Full writeup:
      `docs/developer/theme-api/slots.md`; contract addition documented in
      `docs/developer/components/product-card.md` ("Extension slot:
      `product.after_actions`") and
      `docs/developer/theme-api/product-card-list-renderer.md`
      ("Extension slot wiring"). **Step 0 dedup check:** `list_pull_requests`
      returned 37 open PRs (`#139`-`#188`) at the start of this run; none
      mention "slot"/"SlotRegistry" in their title, and none touch
      `productcardlistrenderer.php`, either `product-card.twig`, or
      `product-card.css` — genuinely unclaimed and uncontended. The
      product-listing controller cluster (`category.php`/`search.php`/
      `manufacturer.php`/`special.php`/`product.php`), `header.php`/
      `header.twig`/`column_left.php`, and the Homepage Builder admin
      controller were all independently confirmed hot (2+ open PRs each,
      per `#168`'s/`#185`'s/`#186`'s own trailer notes) — deliberately
      avoided entirely by keeping this increment inside
      `ProductCardListRenderer`'s own optional-argument pattern rather than
      wiring a real controller consumer, which would have required
      touching one of those contended files. Verified: `php -l` clean
      across the full `upload/`/`tests` tree; new
      `tests/mintcart/design/slot-registry-test.php` (5 scenarios) and 2
      new scenarios appended to
      `tests/mintcart/design/product-card-list-renderer-test.php` (now 10,
      up from 8) — proving the optional `SlotRegistry` is consulted with
      the product's own id when supplied, and never touched at all when
      omitted; full `tests/mintcart/**/*-test.php` suite re-run end to end
      (89 files) — all passing, no regressions; `npm run build`/`npm test`
      both clean, `mc-product-card__slot` confirmed present in both
      `assets/dist/css/index.min.css` and
      `assets/dist/css/components.min.css`. **Not verified:** no live
      MySQL/`vendor/twig/twig` session in this environment, so the
      `{% if slots[...] %}` Twig branch itself has never actually executed
      — see `docs/MANUAL_VERIFICATION.md`'s new "Theme API —
      `product.after_actions` slot" entry. **Next candidates:** a real
      `listen()` caller (an admin "Custom HTML" settings field, or a real
      third-party extension) once one exists; the same optional-argument
      pattern extended to `header.before`/`header.after` via a future
      `HeaderViewProvider` change (`header.php`/`header.twig` are
      currently hot — re-check `gh pr list --state open` before starting
      that one); an Events developer doc, still blocked on a real
      dispatch call site per the same gap `#185` flagged; or re-check
      `gh pr list --state open` first, since this backlog moves fast.
- [x] Version information — `MintCart\Core\Version`
- [x] Basic logging — `MintCart\Core\Logger` (wraps OpenCart's `\Log`, writes to a
      dedicated `mintcart.log`)
- [x] Health checks — `MintCart\Core\HealthCheck`
      (`upload/system/library/mintcart/core/healthcheck.php`), pure
      evaluator mirroring OpenCart's own installer requirements (PHP
      version, required extensions, writable paths) plus a pending-
      migrations signal and MintCart version info; wired via
      `ModelMintcartHealth` + `ControllerMintcartHealth`
      (route `mintcart/health`, GET JSON), same shape as `StoreReadiness`/
      `ControllerMintcartReadiness`. Also fixed a latent
      `MigrationManager::applied()` bug this surfaced (SELECT against a
      never-`CREATE`d tracking table on a fresh install) — see
      `docs/developer/core/health-check.md` for the full writeup, including
      why a missing signal here reports `'unknown'` rather than `StoreReadiness`'s
      "missing = not ready" convention.
- [x] CI — `.github/workflows/ci.yml`: `php-lint` (`php -l` over every `.php`
      under `upload/` and `tests/`), `php-tests` (runs every
      `tests/mintcart/**/*-test.php` smoke test, fails the job on any nonzero
      exit), `frontend-build` (`npm ci && npm run build`). Runs on push/PR
      against `master`.

**In progress.** Remaining for a future run: the other
`MintCart\Compatibility\OpenCart3\*` adapters (`ProductAdapter`/
`OrderAdapter` still unstarted; `SettingsAdapter`/`ExtensionAdapter`/
`CustomerAdapter` open as unmerged PRs) and a health-check surface.
`Bootstrap::boot()` has no automatic call site yet by design (see ADR 001)
— the first real caller wires it.

**Sub-step (this run): `CategoryAdapter`.** Step 0 dedup check against all
54 open PRs (`#52`–`#106`) found `SettingsAdapter` (#81), `ExtensionAdapter`
(#87) and `CustomerAdapter` (#106) already claim the other three named
adapters from `MINTCART_TECHNICAL_PLAN.md` §5 — `ProductAdapter`/
`CategoryAdapter`/`OrderAdapter` were the only ones left unclaimed.
`CategoryAdapter` was picked because it has a real, already-duplicated,
already-`master` call site to point at right now:
`ControllerCatalogCategory::move()` (Phase 17, merged) has an inline
`getCategory()` existence check repeated twice and a `getCategoryPath()`
row-to-`path_id`-list loop — exactly the "extract a real duplicated inline
pattern" shape `ExtensionAdapter`'s own doc describes for
`ModelMintcartReadiness`. Wired into `move()` in this same run (no other
open PR touches `upload/admin/controller/catalog/category.php`), unlike
the other three adapters which shipped unwired. No behavior change: same
existence-check semantics, same `$ancestor_path_ids` values/order. Full
writeup: `docs/developer/compatibility/category-adapter.md`; upstream-diff
addendum: `docs/upstream-diff.md`'s `category.php` entry. Verified: `php
-l` clean across every `.php` file in `upload/` and `tests/`; full
standalone `tests/mintcart/**/*-test.php` suite re-run end to end (35
files, was 34, all passing — no regressions, since no existing PHP
behavior changed beyond the two call sites now routed through the
adapter, both proven equivalent by the existing
`category-move-validator-test.php`, itself unaffected since
`CategoryMoveValidator` was not touched). Same pre-existing gap as every
earlier phase: no MySQL/OpenCart admin session in this environment — a
human should smoke-test the drag & drop `move()` endpoint (once its live
UI wiring lands) on a real install to confirm behavior is unchanged.
**Next step:** `ProductAdapter`/`OrderAdapter` are the last two unstarted
named adapters — re-check `gh pr list --state open` first, since this
backlog grows fast.
**In progress.** `MintCart\Compatibility\OpenCart3\*` adapters: `SettingsAdapter`
and `ExtensionAdapter` exist as open, unmerged PRs at the time of this update;
this run adds a fourth (`CustomerAdapter`, wrapping OpenCart's customer lookup —
see `docs/developer/compatibility/customer-adapter.md`) as its own open PR.
`ProductAdapter`, `CategoryAdapter` and `OrderAdapter` (Technical Plan §5's
remaining three named adapters) are still unstarted. A health-check surface is
also still unstarted (`HealthCheck` per PR #67 is open but unmerged as of this
update). `Bootstrap::boot()` has no automatic call site yet by design (see ADR
001) — the first real caller wires it.
**In progress.** `ExtensionAdapter` is the first `Compatibility\OpenCart3`
class to land (this run), with a real consumer already wired
(`ModelMintcartReadiness`). Remaining for a future run: the other
candidate adapters (`SettingsAdapter` — open PR #81 at the time of this
run; `ProductAdapter`/`CategoryAdapter`/`OrderAdapter`/`CustomerAdapter`
once something needs one, per `CLAUDE.md` §85) and a health-check surface.
`Bootstrap::boot()` has no automatic call site yet by design (see ADR
001) — the first real caller wires it.
**In progress.** Remaining for a future run: `MintCart\Compatibility\OpenCart3\*`
adapters — no consumer needs one yet; the natural first candidate
(`SettingsAdapter` for Business Settings/Setup Wizard, per `CLAUDE.md` §85)
has open PRs building those facades directly against OpenCart's settings
model without one, so introducing the adapter now would ship it without an
actual caller in `master` — reassess once those land. `Bootstrap::boot()`
has no automatic call site yet by design (see ADR 001) — the first real
caller wires it.
**In progress.** Remaining for a future run: wiring `SettingsAdapter` into the
three real call sites identified in its own doc (`ControllerMintcartWizard::saveStoreStep()`,
the Contacts step, the Business Delivery/Payments toggle actions — all three
in files contended by other open PRs as of this run, see the doc's "Not done
yet"), the remaining `MintCart\Compatibility\OpenCart3\*` adapters (no
consumer yet for any of them — `CLAUDE.md` §85), and a health-check surface.
`Bootstrap::boot()` has no automatic call site yet by design (see ADR 001) —
the first real caller wires it.

**Sub-step (this run): `SettingsAdapter`.** Step 0 dedup check against 28
open PRs (`#52`–`#80`) found none touching `MintCart\Compatibility` (PR #73
adds `InstallManifestParser`/`CompatibilityChecker` under the same
top-level namespace but a different subdirectory/file — no overlap) —
genuinely unclaimed, implementable as new files only. Full writeup:
`docs/developer/compatibility/settings-adapter.md`. Verified: `php -l`
clean on the new class/test and the full `upload`/`tests` tree; new
7-scenario test (`tests/mintcart/compatibility/opencart3/settings-adapter-test.php`);
full `tests/mintcart/**/*-test.php` suite re-run end to end (36 files, was
35) — all passing, no regressions (no existing PHP file touched).

## Phase 2 — Frontend foundation

- [x] `package.json` + `package-lock.json`
- [x] esbuild config — `build`/`dev` npm scripts (minified vs. sourcemapped),
      no separate config file needed at this size
- [x] `assets/src/` / `assets/dist/` structure — `assets/dist/` is generated
      by `npm run build`/`npm run dev` and gitignored, never committed
- [x] Pin and install Bootstrap 5.3.8, Swiper 14.1.0, SortableJS 1.15.7,
      Bootstrap Icons 1.13.1 via npm (no CDN) — exact pins in
      `package-lock.json`; the guide's "1.13.x"/"14.1.x" ranges resolved to
      these exact latest-in-range versions at the time this was pinned
- [x] `catalog.js`, `admin.js`, `index.css` entry points — each JS entry
      currently only imports Bootstrap 5's JS; no ecommerce/admin behavior
      yet (no real consumer — CLAUDE.md §85). `index.css` bundles Bootstrap
      5's CSS; MintCart tokens/elements/components/utilities layers are
      Phase 3/4, not yet added to the import order documented in the file.
- [x] `css/compatibility/bootstrap3-shim.css` for legacy OpenCart 3 / Bootstrap 3
      markup (do not load Bootstrap 3 and 5 globally together) —
      `assets/src/css/compatibility/bootstrap3-shim.css`; covers
      `.pull-right`/`.pull-left`, `.hidden-xs`/`.visible-xs`, `.btn-default`,
      `.panel`/`.panel-heading`/`.panel-body`, `.input-group-btn`. Uses
      `var(--mc-*, <fallback>)` since Phase 3 tokens don't exist yet.

**Phase 2 is complete.** Verified with `npm install` (0 vulnerabilities),
`npm run build` (minified output) and `npm run dev` (sourcemapped output) —
both produce `assets/dist/{js,css}/*` and the shim rules are present in the
bundled CSS. Not yet wired into any OpenCart Twig template/controller — that
starts once a theme actually needs to enqueue these bundles (Phase 6+
Product Card, or Phase 8 Theme resolver), per CLAUDE.md §85 (no API/wiring
ahead of a real consumer).

## Phase 3 — Design System (tokens)

- [x] Primitive / semantic CSS custom property tiers — `assets/src/css/settings/tokens.css`,
      on `:root`. Component-local tier (e.g. `--mc-button-bg`) is deferred to each
      component's own CSS in Phase 4 — no consumer yet (CLAUDE.md §85)
- [x] Token categories: color, typography, spacing, radius, border, shadow,
      container, motion, z-index
- [x] Neutral MintCart default tokens (no Livenza/Cartello branding carried over) —
      a mint-green primary scale plus a neutral gray scale; no author-specific
      palette from `references/`
- [x] Wired into `assets/src/css/index.css` between Bootstrap 5 and the
      compatibility shim, per the documented import order
      (`MINTCART_IMPLEMENTATION_GUIDE.md` §11)
- [x] `bootstrap3-shim.css`'s `var(--mc-*, <fallback>)` declarations now resolve
      to real token values (verified in the built bundle, see below)

**Phase 3 is complete.** Verified with `npm run build` and `npm run dev` — both
compile cleanly; `assets/dist/css/index.min.css` contains the `--mc-*` custom
properties (e.g. `--mc-primary`, `--mc-radius-md: 0.375rem`) and the shim rules
now resolve against them instead of their fallback values. No Twig/PHP consumes
these tokens yet — that starts with Phase 4 (Core UI components) or whichever
component needs styling first, per CLAUDE.md §85 (no API/wiring ahead of a real
consumer).

## Phase 4 — Core UI components

- [x] `mc-button` — `assets/src/css/components/button.css`; variants
      (primary/secondary/outline/ghost/danger), sizes (sm/lg), disabled and
      `is-loading` states; no JS needed. Doc: `docs/developer/components/button.md`
- [x] `mc-alert` — `assets/src/css/components/alert.css`; variants
      (success/danger/warning/info); optional dismiss via
      `data-mc-action="dismiss-alert"`, wired by
      `assets/src/js/components/alert.js` (imported from both `catalog.js`
      and `admin.js`). Doc: `docs/developer/components/alert.md`
- [x] `mc-field` — `assets/src/css/components/field.css`; shared
      `.mc-field__control` look for text `<input>`, `<textarea>` and
      `<select>`, plus label/help/error markup and a `has-error` state; no
      JS. Doc: `docs/developer/components/field.md`
- [x] `mc-checkbox` — `assets/src/css/components/checkbox.css`; styles the
      native `<input type="checkbox">` via `accent-color` (no custom
      faux-control/icon dependency); no JS. Doc:
      `docs/developer/components/checkbox.md`
- [x] `mc-radio` — `assets/src/css/components/radio.css`; same approach as
      `mc-checkbox` for `<input type="radio">`; no JS. Doc:
      `docs/developer/components/radio.md`
- [x] `mc-badge` — `assets/src/css/components/badge.css`; variants
      (new/sale/success/out-of-stock) plus a neutral default; the
      `--mc-badge-bg`/`--mc-badge-color` custom properties are the
      documented per-instance override point for a merchant-picked sticker
      color (`MINTCART_IMPLEMENTATION_GUIDE.md` §18's own example uses this
      component); no JS. Doc: `docs/developer/components/badge.md`
- [x] `mc-price` — `assets/src/css/components/price.css`; current/original
      (strikethrough)/tax parts, `mc-price--special` modifier for the
      special-price look; matches the `price`/`special`/`tax` fields
      `ProductViewProvider` (Technical Plan §15D) will supply once Phase 6
      exists; no JS. Doc: `docs/developer/components/price.md`
- [x] `mc-quantity` — `assets/src/css/components/quantity.css` +
      `assets/src/js/components/quantity.js` (`initQuantityStepper`, wired
      into both `catalog.js`/`admin.js`); decrement/increment via
      `data-mc-action="quantity-decrement"`/`"quantity-increment"`, clamped
      to the input's native `min`/`max`/`step`, button disabled state kept
      in sync at the bounds, dispatches a native `change` event plus
      `mc:quantity:change` (`{ detail: { value } }`, bubbling) on
      button-driven steps. Doc: `docs/developer/components/quantity.md`
- [x] `mc-rating` — `assets/src/css/components/rating.css`; read-only star
      display for the future `ProductView.rating` field (Technical Plan
      §15D); pure CSS (two stacked ★ layers, filled layer clipped by
      `--mc-rating-value`), `role="img"` + `aria-label` conveys the value to
      assistive tech, no JS. Doc: `docs/developer/components/rating.md`
- [x] `mc-breadcrumb` — `assets/src/css/components/breadcrumb.css`;
      `<nav><ol>` trail feeding the future `BreadcrumbBuilder` reusable
      action (Technical Plan §15E); separator is a CSS `::before` (no extra
      DOM node), current page is plain text with `aria-current="page"`
      rather than a link; no JS. Doc:
      `docs/developer/components/breadcrumb.md`
- [x] `mc-pagination` — `assets/src/css/components/pagination.css`;
      `<nav><ul>` page-link list feeding the future `PaginationBuilder`
      reusable action (Technical Plan §15E); links carry real `href`s (plain
      navigation, same as OpenCart today), disabled prev/next boundary is a
      non-focusable `<span aria-disabled="true">` rather than a dead `<a
      href="#">`; no JS. Doc: `docs/developer/components/pagination.md`
- [x] `mc-card` — `assets/src/css/components/card.css`; generic
      content/media card layout primitive (`__media`, `__body`, `__title`,
      `__text`, `__actions`, `__footer`), distinct from the future
      ecommerce-specific `mc-product-card` (Phase 6); `--flush` (no body
      padding) and `--hoverable` (shadow/lift affordance for a clickable
      card, respects `prefers-reduced-motion`) modifiers; no JS. Doc:
      `docs/developer/components/card.md`
- [x] `mc-modal` — `assets/src/css/components/modal.css` +
      `assets/src/js/components/modal.js` (`initModalTriggers`, wired into
      both `catalog.js`/`admin.js`); wraps Bootstrap 5's `Modal` JS (focus
      trap, `Escape`-to-close, backdrop click-to-close, scroll lock) rather
      than reimplementing dialog behavior, per
      `MINTCART_IMPLEMENTATION_GUIDE.md` §9 ("Not needed to rewrite
      Bootstrap Modal own without a product reason"). Bootstrap's own
      `.modal`/`.modal-dialog`/`.modal-content`/... classes stay as internal
      implementation (required by Bootstrap's JS/CSS); the public contract
      is `.mc-modal`/`.mc-modal__title`/`.mc-modal__close` plus
      `data-mc-action="open-modal"`/`"close-modal"` (never Bootstrap's own
      `data-bs-toggle`/`data-bs-dismiss`). Styling is retinted via
      Bootstrap's documented `--bs-modal-*`/`--bs-backdrop-*` custom
      properties reading from `--mc-*` tokens — no Bootstrap source
      recompilation. Bootstrap's `shown.bs.modal`/`hidden.bs.modal` events
      are relayed as bubbling `mc:modal:shown`/`mc:modal:hidden`
      `CustomEvent`s. Doc: `docs/developer/components/modal.md`
- [x] `mc-drawer` — `assets/src/css/components/drawer.css` +
      `assets/src/js/components/drawer.js` (`initDrawerTriggers`, wired into
      both `catalog.js`/`admin.js`); wraps Bootstrap 5's `Offcanvas` JS
      (focus trap, `Escape`-to-close, backdrop click-to-close, scroll lock)
      the same way `mc-modal` wraps `Modal`, per
      `MINTCART_IMPLEMENTATION_GUIDE.md` §9. Bootstrap's own
      `.offcanvas`/`.offcanvas-start|end|top|bottom`/`.offcanvas-header`/
      `.offcanvas-body`/`.offcanvas-title` classes stay as internal
      implementation; the public contract is
      `.mc-drawer`/`.mc-drawer__title`/`.mc-drawer__close` plus
      `data-mc-action="open-drawer"`/`"close-drawer"` (never Bootstrap's own
      `data-bs-toggle`/`data-bs-dismiss`). Styling is retinted via
      Bootstrap's documented `--bs-offcanvas-*` custom properties reading
      from `--mc-*` tokens (including the existing `--mc-z-drawer` token
      from Phase 3) — no Bootstrap source recompilation. Unlike
      `.modal-backdrop`, Bootstrap's `.offcanvas-backdrop` doesn't expose a
      z-index custom property, so `drawer.css` overrides it directly
      (`calc(var(--mc-z-drawer) - 1)`) to avoid an equal-value stacking
      collision with the drawer panel. Bootstrap's
      `shown.bs.offcanvas`/`hidden.bs.offcanvas` events are relayed as
      bubbling `mc:drawer:shown`/`mc:drawer:hidden` `CustomEvent`s. Doc:
      `docs/developer/components/drawer.md`
- [x] `mc-toast` — `assets/src/css/components/toast.css` +
      `assets/src/js/components/toast.js` (`initToastTriggers`, `showToast`,
      wired into both `catalog.js`/`admin.js`); wraps Bootstrap 5's `Toast`
      JS (show/hide, autohide with pause-on-interaction) the same way
      `mc-modal`/`mc-drawer` wrap `Modal`/`Offcanvas`, but unlike those two
      `Toast` deliberately does not trap or move focus — correct for a
      transient, non-blocking notification (Functional Scope §27); screen
      reader announcement instead relies on `role`/`aria-live`/
      `aria-atomic` on the markup itself, documented as required since
      Bootstrap's JS does not add them. `data-mc-action="open-toast"`/
      `"close-toast"` plus an exported `showToast()` (for a future Phase 15
      Cart client to call after `mc:cart:updated`) are the public trigger
      surface; `data-bs-autohide`/`data-bs-delay` remain generic Bootstrap
      configuration attributes, not part of the `mc-*` contract. Variant
      tints (`success`/`danger`/`warning`/`info`) recolor `.toast-header`
      via `color-mix()`, same approach as `mc-alert`. `.toast-container`'s
      `--bs-toast-zindex` is retinted directly (like `mc-drawer.css` did for
      `.offcanvas-backdrop`) since it's a plain wrapping element, never a
      descendant of `.mc-toast`. Doc: `docs/developer/components/toast.md`
- [x] `mc-select` — `assets/src/css/components/select.css`; standalone
      select applied directly to a `<select>` used outside a labeled
      `.mc-field` wrapper (e.g. a sort-order/per-page-limit control next to
      a product listing — `MINTCART_TECHNICAL_PLAN.md` §15E's
      `SortOptionsBuilder`/`LimitOptionsBuilder`, currently an open,
      unmerged PR at the time of this run — see "Sub-step" below); sizes
      (`--sm`/`--lg`, matching `mc-button`'s naming/scale), `[multiple]`
      handling (drops the chevron/right padding, adds a min-height for the
      listbox look), `has-error` transient state, disabled. Doc:
      `docs/developer/components/select.md`
- [x] Each shipped component so far: CSS contract (no Twig yet — no theme/
      renderer exists until Phase 8, per CLAUDE.md §85), JS only where
      actually needed (`mc-alert`'s dismiss, `mc-quantity`'s stepper,
      `mc-modal`'s open/close triggers), a11y states, doc example with a
      Definition-of-Done checklist

**Phase 4 is complete** (16 of 16 components done: `mc-button`, `mc-alert`,
`mc-field`, `mc-select`, `mc-checkbox`, `mc-radio`, `mc-badge`, `mc-price`,
`mc-quantity`, `mc-rating`, `mc-breadcrumb`, `mc-pagination`, `mc-card`,
`mc-modal`, `mc-drawer`, `mc-toast`). Verified with `npm install` (0
vulnerabilities — this run's environment needed a fresh install, all
packages resolved from the committed lockfile with no version drift),
`npm run build` (minified) and `npm run dev` (sourcemapped), both compiling
cleanly for both the full `index.css`/`index.min.css` bundle and the
Bootstrap-3-safe `components.css`/`components.min.css` bundle — the
bundled CSS contains `.mc-select`, `.mc-select--sm`/`--lg` and the
`--mc-select-*` custom properties in both (spot-checked with `grep -o`
against `assets/dist/css/*`). No PHP files were touched in this increment,
so the Phase 1 PHP `tests/mintcart/` suite is not applicable. No automated
component/visual test harness exists yet in this repo — this increment's
verification is build-compiles + bundled-output grep + manual code review
only, same gap noted in previous Phase 4 runs (not new).

**Sub-step (this run): `mc-select`.** Previous runs had left this
intentionally deferred pending "a real consumer beyond what
`mc-field__control`'s shared `<select>` styling already covers." Step 0
dedup check against the 27 open PRs at the start of this run (`#138`–`#164`)
found none building `mc-select`/`select.css`, and none touching
`assets/src/css/index.css`, `components.css` or any `components/*.css`
file — genuinely unclaimed, implementable as new files only (plus two
one-line `@import` additions). The concrete trigger for revisiting the
deferral: `MINTCART_TECHNICAL_PLAN.md` §15E names
`SortOptionsBuilder`/`LimitOptionsBuilder` as reusable actions Category/
Search/Manufacturer/Specials listings all need, and one is already an open
PR (`#157`, "Phase 8/15C/15E: SortOptionsBuilder + LimitOptionsBuilder,
wired into `category.php`") building exactly the kind of standalone,
label-less sort/limit `<select>` `mc-field__control` isn't designed for
(no room for `.mc-field`'s label/help/error markup next to a listing
toolbar). This component gives that in-flight need a component to land on
without duplicating it here — `select.css` has no PHP/Twig, so it doesn't
collide with `#157`'s files either way. `MINTCART_IMPLEMENTATION_GUIDE.md`
§45 lists `mc-select` by name in the required Phase 4 set, same as every
other already-shipped sibling component, none of which had a live
Twig/theme consumer at ship time either (Phase 8 gate, CLAUDE.md §85) —
so this isn't a new abstraction invented "just in case," it is finishing
the named Phase 4 list against a concrete, already-scoped near-term
caller.

**Next step:** wire `mc-select` into `#157`'s sort/limit control markup
once that PR merges (or into this run's own follow-up if it doesn't land
first — re-check `gh pr list --state open`/`list_pull_requests` before
assuming which). With all 16 Phase 4 components done, the next run should
move to **Phase 5 — Layout conventions**.

## Phase 5 — Layout conventions

- [x] Document Bootstrap 5 grid/utility usage rules (no parallel grid framework)
- [x] Verify responsive storefront layout achievable without Bootstrap 3

Complete. `docs/developer/layout-conventions.md` documents the rule
(Bootstrap 5 grid/utilities are the only layout system; `mc-*` is for
MintCart-owned components, never a parallel grid framework —
`MINTCART_IMPLEMENTATION_GUIDE.md` §46) with the same
Bootstrap-wraps-`mc-*` pattern already used in every Phase 4 component doc.

The "verify responsive layout without Bootstrap 3" item was checked
statically, since no Twig/theme exists yet to render a real page against
(that's Phase 8): `grep -rnE '\.(container|row|col-|g-[0-9]|d-flex|d-grid)\b'`
across `assets/src/css/components/` and `assets/src/css/settings/` found no
component CSS overriding a Bootstrap grid/utility class name (one comment
hit only), `bootstrap3-shim.css` only touches legacy leaf classes
(`.pull-right`, `.panel`, `.btn-default`, ...) never grid/container
classes, and the CSS import order (Bootstrap 5 → tokens → components →
compatibility) means the shim can only extend legacy markup, never shadow
component or grid CSS. A live browser check against a real responsive page
belongs to Phase 8 once a theme exists to render.

## Phase 6 — Product Card (first reference component)

- [x] `ProductViewProvider` contract (id, name, url, image, secondary_image, price,
      special, tax, rating, stock, badges, options_summary, actions) —
      `MintCart\Commerce\ProductViewProvider`
      (`upload/system/library/mintcart/commerce/productviewprovider.php`),
      documented in `docs/developer/commerce/product-view-provider.md`.
      Pure transform, no OpenCart dependency (formatting/tax/URL resolution
      stays OpenCart's job upstream of it); computes `stock`/`badges`/
      `actions` consistently instead of each controller doing it ad hoc.
      Actual controller wiring was deferred to Phase 8 (Theme resolver) and
      has since landed for Category, Search, Special, Manufacturer and the
      product detail page's own Related Products list — all five now via
      the shared `MintCart\Design\ProductCardListRenderer`. Phase 6's
      listing-controller wiring is complete.
- [x] `mc-product-card` Twig + CSS + JS — `assets/src/css/components/product-card.css`
      + `assets/src/js/components/product-card.js` (`initProductCardActions`,
      wired into `catalog.js` only — storefront component); documented
      template contract (no live `.twig` file — no theme/`ComponentRenderer`
      exists yet, see "No live Twig template yet" in the doc) in
      `docs/developer/components/product-card.md`. Composes `mc-badge`,
      `mc-price`, `mc-rating`, `mc-button` (Phase 4) rather than duplicating
      their CSS.
- [x] Wishlist / compare / add-to-cart / quick-order support — wishlist/compare
      are a self-contained UI toggle (button `aria-pressed` state +
      `mc:product-card:wishlist-toggle`/`mc:product-card:compare-toggle`
      events, no backend call). Add-to-cart/quick-order render in the correct
      enabled/disabled state from `product.actions` but are intentionally
      **markup + `data-mc-action` hooks only, no JS behavior yet** — actually
      adding to a cart or placing a quick order needs `CartService`/
      `QuickOrderService` (Phase 15), which don't exist yet; see the doc's "Why
      wishlist/compare toggle but add-to-cart doesn't" section.

**Phase 6 is complete** for what's buildable before the Theme resolver exists.
Both `ProductViewProvider` (previous run) and `mc-product-card` (this run) are
done and verified. What's explicitly **not** done, by design, and deferred to
later phases:
- Live controller wiring (`ControllerProductCategory`/search/manufacturer/
  special actually calling `ProductViewProvider` and rendering this template)
  — Phase 8, once `MintCart\Design\ComponentRenderer` and a real theme exist
  to resolve the template against.
- Add-to-cart/quick-order network behavior — Phase 15 (Commerce API).
- Wishlist/compare persistence — Phase 15 as well (today's toggle is a UI
  affordance + event only, no storage).

**Update (this run): the product detail page (`ControllerProductProduct`)
is wired too, closing the one listing/page gap this section's history had
repeatedly deferred.** `mint_default` previously had no
`template/product/product.twig` at all — a shopper on a Mint-aware theme
could browse Category/Search/Manufacturer/Special but the actual page they
buy from rendered nothing Mint-specific. This run's step 0 dedup check
found 20 open PRs (#52-#73) covering entity-picker backends, Wizard steps,
Business Settings facades, SEO builders and more, none touching
`catalog/controller/product/product.php` or any `product/product.twig`
path — genuinely unclaimed, and three files (`admin/controller/mintcart/entity.php`
— 5 open PRs, `admin/controller/mintcart/wizard.php` — 2, `admin/controller/common/column_left.php`
— 2+) were deliberately avoided as already hot-contended per this file's
own §0f-style guidance.

Full writeup: `docs/upstream-diff.md`'s new `product.php` entry,
`docs/developer/components/product-card.md`'s updated "Live Twig template"
section (fifth `ProductCardListRenderer` consumer), and the new
`docs/developer/components/product-gallery.md` (a genuinely new component
this page needed — a main-image/thumbnail swap `mc-product-card` has no
equivalent of). Also extends `product-card.js`'s `add-to-cart` handler to
read a linked `mc-quantity` input and product-page option fields
(backward-compatible — a grid card sets neither new attribute).
Deliberately scoped down: the Reviews tab is a read-only placeholder (no
AJAX write-review form yet — a separate, self-contained increment) and
recurring/subscription payment profiles have no picker (falls back to the
existing "missing required option" redirect, not a regression since no
Mint-native product page existed to pick one before).

Verified: `php -l` clean on both touched PHP files, re-swept across the
full `upload`/`tests` tree; full `tests/mintcart/**/*-test.php` suite
re-run end to end — all passing, no regressions; `product.twig`'s Twig tag
counts checked balanced (29 if/29 endif, 11 for/11 endfor); `npm install`
(0 vulnerabilities) + `npm run build` compile cleanly, `mc-product-gallery`/
`gallery-thumb` confirmed present in `catalog.min.js`/`index.min.css` and
absent from `admin.min.js`; `npm test` still passing. **Not verified:** no
live MySQL/`vendor/twig/twig` session in this environment — see
`docs/MANUAL_VERIFICATION.md`'s new entry for what a human should confirm
on a real install.

Verified (previous run): `php -l` on `productviewprovider.php` (clean, previous run); the
7-scenario `tests/mintcart/commerce/product-view-provider-test.php` smoke
test (previous run, still passing — `ProductViewProvider` wasn't touched this
run); `npm install` (0 vulnerabilities, fresh install this run) then `npm run
build` — both `catalog.min.js` (83.6kb, up from 83.2kb) and `index.min.css`
(255.8kb, up from 252.3kb) compile cleanly; spot-checked the bundled output
with `grep` to confirm `.mc-product-card__image--secondary`,
`.mc-product-card--compact`, `toggle-wishlist`, `toggle-compare` and
`mc:product-card:wishlist-toggle` are present in `catalog.min.js`/
`index.min.css`, and confirmed `toggle-wishlist` does **not** appear in
`admin.min.js` (product-card JS is storefront-only by design). No automated
component/visual test harness exists yet in this repo (same gap noted in
every previous Phase 4/5 run) — verification here is build-compiles +
bundled-output grep + manual review against the Definition of Done checklist.

**Update (2026-09-19, routine run): `mint_default`'s missing
`manufacturer_list.twig` + MC-0017.** `product/manufacturer`'s `info()`
route (a single brand's product listing) already had its own
`mint_default` template, but `index()` (the alphabetical brand-index page)
had none — `mint_default` fell through `event/theme.php`'s fallback chain
straight to the stock `default` theme's Bootstrap-3 template for this one
route, the last gap in this controller's two-route family. Added
`upload/catalog/view/theme/mint_default/template/product/manufacturer_list.twig`
(mirrors `manufacturer_info.twig`'s container/`mc-breadcrumb`/column
layout; `mc-button--ghost`/`--sm` for the A-Z jump links,
`mc-button--secondary` for each brand link). While building it, found and
fixed **MC-0017** (`docs/fixes/registry.md`): `ControllerProductManufacturer::index()`
never assigned `heading_title`/`text_index`/`text_no_results`/
`button_continue` to `$data` at all — the same class of bug MC-0001/
MC-0003 already fixed on `category.php`/this controller's own `info()`
method, silently blanking the page's `<h1>`, its "Brand Index:" label, and
(for a store with zero manufacturers) its empty-state message/button, on
both `default` and the new `mint_default` template alike. Full writeup:
`docs/upstream-diff.md`'s `manufacturer.php` entry and new
`manufacturer_list.twig` (new file) entry.

Step 0 dedup check: `mcp__github__list_pull_requests` (all open, not
filtered by branch name) returned 7 open PRs (#127-#133 — Update Manager
`PackageExtractor`/`BackupExecutor`, Diagnostics dev-mode admin toggle,
Related Products `McEntityPicker` field, responsive image width/height +
lazy loading, `special_price` bulk field, Wizard Delivery step). Verified
via each PR's own file list that none touch
`upload/catalog/controller/product/manufacturer.php`,
`upload/catalog/view/theme/mint_default/template/product/manufacturer_list.twig`,
or `docs/fixes/registry.md`'s MC-0017 slot (next free ID after #131's
already-filed MC-0015/MC-0016 references). No hot shared files (§0f:
Homepage Builder block-type registry, homepage block list/template,
`block.css`, plus `wizard.php`/`entity.php`/`column_left.php` per earlier
runs' own findings) are touched by any of the 7 open PRs or by this
increment. Also confirmed `docs/developer/seo/meta-builder.md`'s own
"deliberately not done yet" section explicitly rules out wiring
`MetaBuilder` into `manufacturer.php`/`search.php` (neither has an entity
meta field to fall back from — not a remaining gap, contrary to what an
earlier trailer note's summary implied without checking the doc's own
reasoning first, per `CLAUDE.md` §84).

Verified: `php -l` clean on `manufacturer.php`. The new template's Twig
`{% if %}`/`{% endif %}`/`{% for %}`/`{% endfor %}` tag counts checked
balanced (4/4, 4/4). Full `tests/mintcart/**/*-test.php` suite (81 files)
re-run end to end — all passing, no regressions (no new test file needed
— same rationale MC-0001/MC-0003 used, four already-proven
`language->get()` calls, no new pure-logic class added). **Not verified:**
no live MySQL/Twig session in this sandboxed environment — see
`docs/MANUAL_VERIFICATION.md`'s new Phase 6 entry for the full manual
script a human should run.

**Next candidates:** `product/manufacturer`'s two routes are now both
covered by `mint_default` templates — a similar "does `mint_default` have
every route its own controller uses" audit for other controllers
(`checkout/*`, `account/*` are known large gaps, out of scope for a single
increment) could surface further candidates like this one; the
`search.php`/manufacturer.php` "still unwired by design" `MetaBuilder`
note above is now double-confirmed, not a candidate; or re-check
`gh pr list --state open` first, since this backlog moves fast.

## Phase 7 — Header / Footer / Navigation

- [x] Header, Topbar, Logo, Search, Account, Cart component contracts —
      `MintCart\Design\HeaderViewProvider`
      (`upload/system/library/mintcart/design/headerviewprovider.php`),
      documented in `docs/developer/components/header.md`. Live-wired:
      `catalog/controller/common/header.php`
      (`ControllerCommonHeader::index()`) builds it and renders via
      `ComponentRenderer::render('header', ...)`, gated by the same
      `theme.json`-manifest check other Mint components use.
      `catalog/view/theme/mint_default/template/mint/components/header.twig`
      + `catalog/view/theme/mint_default/template/common/header.twig`
      (whole-page wrapper) + `assets/src/css/components/header.css`
      (`mc-header`). Theme-override proof:
      `catalog/view/theme/test_theme/template/mint/components/header.twig`
      (genuinely different DOM, `component-renderer-test.php` scenario 13 —
      renumbered during merge reconciliation, see the Footer entry's own
      scenario 11-12 below). Navigation/Search/Cart/language/currency
      **stay as pre-rendered legacy HTML fragments** inside the `widgets`
      field — see `docs/developer/components/header.md` "Why widgets stay
      legacy fragments" for the rationale (each has its own real Mint API
      planned for Phase 15/Commerce API; no second real consumer yet for a
      Mint-native Navigation component, `CLAUDE.md` §85). "Mobile
      Navigation" — **done this run**: `mc-header__nav` now carries
      Bootstrap 5's responsive off-canvas classes
      (`.offcanvas.offcanvas-lg.offcanvas-start`) plus `.mc-drawer`, opened
      below the `lg` breakpoint by a new hamburger `.mc-header__nav-toggle`
      button through the existing `data-mc-action="open-drawer"` contract —
      composing `mc-drawer` (Phase 4) exactly as this note previously
      anticipated, no new JS component. See
      `docs/developer/components/header.md` "Mobile navigation" and
      `docs/upstream-diff.md`'s `common/header.php`/`common/header.php`
      language-file entries for the full writeup, including a small
      `drawer.js` addition (`syncTriggerAriaExpanded()`) this needed —
      `mc-drawer`'s first real trigger-button consumer in this repo.
- [x] Footer, Footer Columns, Contacts, Social links, Payments, Newsletter —
      `MintCart\Design\FooterViewProvider`
      (`upload/system/library/mintcart/design/footerviewprovider.php`) +
      `assets/src/css/components/footer.css` +
      `catalog/view/theme/mint_default/template/mint/components/footer.twig`,
      documented in `docs/developer/components/footer.md`
- [x] Centralized business data (no per-theme contact duplication) — both
      Header (`contacts.phone` from `config_telephone`) and Footer
      (`contacts.phone`/`email`/`address`, same OpenCart settings) source
      from OpenCart's existing config storage, never a Mint-owned table.

**Footer sub-step is complete, including live controller wiring** (this
run). `FooterViewProvider` (contract + normalization rules), `mc-footer`
CSS, `mint_default`'s `mint/components/footer.twig`, and now
`ControllerCommonFooter::index()` actually building a `FooterViewProvider`
result and rendering it via `ComponentRenderer::render('footer', ...)` into
`$data['mc_footer']`, consumed by a new
`mint_default/template/common/footer.twig` page-level template — gated by
the same `theme.json`-manifest check `ControllerProductCategory` uses for
`product-card` (`docs/upstream-diff.md`). The controller's original
per-field `$data` is untouched, so the legacy `default` theme's footer is
unaffected. Three new language strings were added
(`text_contact_us`, `text_newsletter_email`, `text_newsletter_submit`);
`text_newsletter` itself already existed and is reused as-is. Social links,
payment badges and the newsletter subscribe action still default to
empty/disabled — no storage for the first two exists yet (Business
Settings, Phase 14) and no subscribe endpoint exists for the third
(Commerce API, Phase 15) — so `FooterViewProvider` never invents a data
source ahead of a real consumer (`CLAUDE.md` §85); see
`docs/developer/components/footer.md` for the full rationale.

**Theme override proof for `footer.twig` is now done too** (this run).
`catalog/view/theme/test_theme/` (already overriding `product-card.twig`
since Phase 8's milestone proof) now also overrides `footer.twig` with a
genuinely different DOM — a single stacked link list, an optional one-line
phone/email row, and the powered-by text, marked `mc-footer--test` instead
of `mint_default`'s grid-based `mc-footer` (no social/payments/newsletter
sections). `test_theme`'s `theme.json` still declares `"parent": null`.
`tests/mintcart/design/component-renderer-test.php` scenario 12 confirms
`resolve('footer')` returns `test_theme`'s own file when it's active and
still falls back to Mint Default's file otherwise — both directions,
against the real shipped repo files. This proves the Theme API's
component-override fallback works independently per component name (a
theme can override some components and inherit the rest), not just for the
one component (`product-card`) the original milestone proof used. Full
writeup in `docs/developer/components/footer.md`.

**Not done, by design, deferred to a future run:**
- Header, Topbar, Logo, Search, Account, Cart, Navigation, Mobile
  Navigation component contracts — not started at all yet.
- A live end-to-end request test confirming `mint_default`'s footer
  (and now `test_theme`'s override) actually renders on a real running
  install (this environment still has no MySQL/`vendor/twig/twig`) — same
  pre-existing gap noted in every earlier phase.

Verified (this run — theme override proof): `test_theme/footer.twig`'s
`if`/`for` tag nesting checked balanced (same script-based technique used
for every earlier `.twig` file in this repo); full `tests/mintcart/` suite
re-run end to end (migration manager, `ProductViewProvider` x8,
`ComponentRenderer` x12 — up from 11, new scenario 12,
`FooterViewProvider` x7) — all pass. No PHP files were touched this run
(only a new `.twig` fixture file and the test file), so no `php -l` beyond
the test file itself was needed. Same pre-existing gap as every earlier
phase: no live `config_theme=test_theme`-and-load-a-page check was possible
(no MySQL/`vendor/twig/twig` in this environment) — a human should confirm
`test_theme`'s footer actually renders with its distinct DOM before this is
considered fully closed end-to-end.

Increment 2's verification (`ControllerCommonFooter` wiring, unaffected by
this run): `php -l` on the modified controller and language file (clean);
full `tests/mintcart/` suite re-run end to end (migration manager,
`ProductViewProvider` x8, `ComponentRenderer` x11, `FooterViewProvider` x7)
— all pass, none affected by that change since neither `FooterViewProvider`
nor `ComponentRenderer` themselves were modified. No new PHP test was added
for the controller wiring itself — same approach `ControllerProductCategory`'s
wiring took (Phase 8 sub-step 2): `php -l` + manual review, since
OpenCart's `Registry`/`Config`/`Loader` aren't mockable in this environment
without a full framework bootstrap. A human should smoke-test a real page
footer on a `mint_default`-configured install (and confirm the legacy
`default` theme's footer is pixel-identical to before) before this reaches
production — see `docs/upstream-diff.md`'s new entries for the full
writeup.

Increment 1's verification (Footer provider/CSS/Twig, unaffected by this
run): `FooterViewProvider` smoke test, 7 scenarios; `ComponentRenderer`
smoke test scenario 11 (real-repo `footer.twig` resolution); `footer.twig`'s
`if`/`for` tag nesting checked balanced; `npm run build` compiles cleanly,
`mc-footer__*` classes present in the bundled
`assets/dist/css/index.min.css`.

**Header sub-step is complete too** (PR #15, `claude/charming-rubin-ozhsi6`,
merged 2026-08-24 — reconciled onto this file's Footer/Phase 6-9 state as a
separate merge commit rather than the branch itself, since Phase 6-9 had
already moved substantially by the time Header was ready to merge).
`HeaderViewProvider` (contract + normalization rules), `mc-header` CSS,
`mint_default`'s `mint/components/header.twig` +
`common/header.twig` page wrapper, and live
`ControllerCommonHeader::index()` wiring. During reconciliation,
`header.php`'s own private `mintActiveThemeDirectory()` copy (which is
what PR #15 shipped with) was swapped for the shared
`MintCart\Design\ActiveTheme::directory()`/`isMintAware()` — by merge time
every other controller in this pipeline (`footer.php`/`category.php`/
`search.php`/`special.php`/`manufacturer.php`) had already been
consolidated onto that shared class, so keeping Header's own copy would
have reintroduced the exact duplication that consolidation removed. See
`docs/upstream-diff.md`'s `header.php` entry.

Theme-override proof: `catalog/view/theme/test_theme/template/mint/components/header.twig`
— a genuinely different DOM (single stacked bar, no account dropdown, no
topbar/currency/language/search widgets). `component-renderer-test.php`
scenario 13 confirms both fallback directions (renumbered from PR #15's
original scenario 11 during reconciliation, since scenarios 11-12 were
already taken by the Footer coverage above).

Navigation/Search/Cart/language/currency **stay as pre-rendered legacy
HTML fragments** inside the `widgets` field — see
`docs/developer/components/header.md` "Why widgets stay legacy fragments"
for the rationale (each has its own real Mint API planned for Phase
15/Commerce API; no second real consumer yet for a Mint-native Navigation
component, `CLAUDE.md` §85). "Mobile Navigation" specifically is **not**
addressed — `mc-header` has no responsive off-canvas/hamburger behavior
yet, it relies on `flex-wrap` only; a dedicated mobile nav pattern (likely
composing `mc-drawer`, Phase 4) is deferred to a future run.

Verified (reconciliation): `php -l` clean on the modified `header.php`;
full `tests/mintcart/` suite re-run end to end (migration manager,
`ProductViewProvider` x8, `ComponentRenderer` x13, `FooterViewProvider` x7,
`HeaderViewProvider` x7, presets, `ProductCardListRenderer` x8) — all
pass. Same pre-existing gap as every earlier phase: no live
`config_theme`-switch-and-load-a-page check was possible (no
MySQL/`vendor/twig/twig` in this environment) — a human should confirm
Header actually renders (both `mint_default`'s and `test_theme`'s distinct
DOM) on a real install before this is considered fully closed end-to-end.

**Phase 7 is functionally complete** for the component-contract/Theme-API
story (both Header and Footer have a real `mint_default` implementation,
live controller wiring, and a `test_theme` override proving a different
DOM with 0 OCMOD). One thing remains, tracked as a follow-up rather than
blocking Phase 7 itself:
- **Mobile Navigation** — no dedicated pattern yet (see above).

**Resolved (2026-09-19, routine run): MintCart's own compiled asset bundle
is now enqueued on the storefront.** `ControllerCommonHeader::index()`
calls `$this->document->addStyle()`/`addScript()` for
`assets/dist/css/components.min.css`/`assets/dist/js/catalog.min.js` (not
`index.min.css` — that one still bundles the full Bootstrap 5 reset/grid/
utility layer that conflicts with the Bootstrap 3 stack this page still
also loads; `components.min.css` is the same bundle with only that one
`@import` line removed, already proven safe on Bootstrap-3-legacy admin
screens by an earlier run — see `docs/developer/admin/asset-enqueue.md`).
Gated by the same `isMintAware()` check `mc_header`/`mc_schema_organization`
already use, so the legacy `default` theme and any third-party theme pay
nothing. Before this, **no `mc-*` component CSS or `data-mc-*` JS hook had
any live effect on any page** — every Add to Cart/Quick Order/wishlist
button `mc-product-card` renders had no click handler at all. `default`'s
Bootstrap-3/jQuery/`common.js` stack is completely untouched — this is an
addition, not the swap `CLAUDE.md` §10/§84 still requires a full call-site
audit before attempting (unchanged, still not attempted, still a Phase 7
follow-up if ever prioritized — see `docs/developer/components/header.md`'s
"Known gap (partially resolved)"). Full writeup:
`docs/upstream-diff.md`'s `header.php` entry. Verified: `php -l` clean;
`npm install` (0 vulnerabilities), `npm run build`/`npm run dev` both
compile cleanly (no JS/CSS source touched, only which two pre-existing
build outputs get referenced); Twig tag balance re-checked (4/4/4/4); full
`tests/mintcart/**/*-test.php` suite re-run end to end (84 files) — all
passing, no regressions. **Not verified live** — no MySQL/browser session
in this environment; see `docs/MANUAL_VERIFICATION.md`'s new Phase 7
section for what a human should confirm (network tab shows both files
loading; product-card buttons actually respond to clicks now). **Next
candidates:** the still-open Mobile Navigation follow-up above; the actual
call-site audit needed to eventually replace (not just add to) the legacy
Bootstrap-3/jQuery stack; wiring a Mint-native checkout UI now that the JS
bundle can reach the storefront at all (Phase 15's `CheckoutTotalsService`
has no consumer yet); or re-check `list_pull_requests`/`gh pr list --state
open` first.

## Phase 8 — Theme resolver

- [x] `MintCart\Design\ComponentRenderer` (active theme override → parent/base
      theme → Mint Default fallback)
- [x] Wire `ControllerProductCategory` to `ProductViewProvider` + `ComponentRenderer`
- [x] Prove: a test theme overriding only `product-card.twig` gets everything else
      from Mint Default automatically, 0 OCMOD — see "Sub-step 3" below,
      `docs/adr/002-theme-api-milestone-proof.md`, and the live-verification
      fix in `docs/adr/003-page-template-fallback-fix.md`

**Phase 8 is complete and verified live** against a real install (not just
the `resolve()`-level unit test ADR 002 originally relied on). Manual
verification (`docs/MANUAL_VERIFICATION.md`) surfaced a real end-to-end gap
that ADR 002's caveat had flagged as unconfirmed: `test_theme`'s
component-only override never actually rendered on a live category page,
because OpenCart's own whole-page route resolver
(`catalog/controller/event/theme.php`) had no `mint_default` fallback tier
— only `ComponentRenderer` did. Fixed by extending that core file with the
same three-tier chain (active theme → `mint_default` if Mint-aware →
`default`); see `docs/adr/003-page-template-fallback-fix.md` and
`docs/upstream-diff.md` for the full writeup. Re-verified live via `curl`
against the real running install after the fix: `test_theme`'s distinct
product-card DOM (`mc-product-card--test`, no quick-order/wishlist/compare)
now renders correctly. Still open: confirming the fallback also holds in
the *other* direction (switching back to `mint_default` and seeing its own
DOM return) — tracked in `docs/MANUAL_VERIFICATION.md`.

A second, unrelated bug found during the same live check: `mint_default`'s
`product-card.twig` rendered `Ex Tax:: $100.00` (double colon) because
`text_tax` (`'Ex Tax:'`, already carries a colon) had a redundant `:`
appended in the template. Fixed in both the live template and its
documented contract (`docs/developer/components/product-card.md`).
**Critical architecture milestone passed** — see `CLAUDE.md` §19 / Technical
Plan §45. Phase 9+ (and the deferred Phase 7) may now proceed.

**Sub-step 1:** `MintCart\Design\ComponentRenderer`
(`upload/system/library/mintcart/design/componentrenderer.php`) resolves a
component name against the active theme, its `theme.json` "parent" chain,
then `mint_default`, deliberately separate from OpenCart's existing
`event/theme.php` (which resolves whole page routes, not components shared
across many pages) — see `docs/developer/theme-api/component-renderer.md`
for the full design rationale. A minimal `mint_default` theme directory
exists (`catalog/view/theme/mint_default/theme.json` + a real
`template/mint/components/product-card.twig` matching
`docs/developer/components/product-card.md`'s documented contract exactly).
`render()` also guards against a resolved override file that is empty
(0 bytes), short-circuiting to an empty string instead of hitting a
misleading error from the underlying `\Template\Twig` adaptor's "no `$code`
supplied" fallback.

**Sub-step 2 (this run): `ControllerProductCategory` wired.**
`upload/catalog/controller/product/category.php` now builds a
`ProductViewProvider` result per product row and renders it via
`ComponentRenderer::render('product-card', ...)` into a new
`$data['product_cards']` array, consumed by a new
`catalog/view/theme/mint_default/template/product/category.twig` page —
Mint Default's first real page template. Full compatibility rationale
(why this is safe for stores still on the legacy `default` theme, why it
can't live outside the controller) is in the new `docs/upstream-diff.md` —
this is the **first** direct OpenCart core file modification MintCart has
made (every earlier phase only added new files).

Key design decisions, tightened after review before merge:

- The new pipeline only runs when the active theme (resolved via a new
  `mintActiveThemeDirectory()` private method, mirroring `event/theme.php`'s
  own `config_theme`/`theme_default_directory` lookup) **ships a
  `theme.json` manifest** — not merely "isn't literally `'default'`". The
  first version of this gate only excluded the string `'default'`, which
  meant any unrelated third-party OC3 theme (neither `default` nor
  Mint-aware) would still pay the cost of building a `ComponentRenderer` +
  `ProductViewProvider` and rendering a `product-card` per listed product,
  for a `product_cards` array its own template never reads. Checking for
  `theme.json` instead correctly scopes this to Mint-aware themes only.
- `ComponentRenderer::render()` is now wrapped in try/catch per product; a
  failure (e.g. a missing/unreadable `product-card.twig`) logs via
  `MintCart\Core\Logger` and disables the pipeline for the rest of that
  request, rather than throwing an uncaught exception that would fatal the
  entire category page for every visitor.
- `ProductViewProvider::buildActions()` (Phase 6) had a latent gap this
  wiring surfaced: it gated `add_to_cart`/`quick_order` on stock quantity
  alone, ignoring OpenCart's `config_stock_checkout` backorder setting —
  meaning a store with backorders enabled would have gotten a disabled
  add-to-cart button on every out-of-stock product on any Mint theme. Fixed
  by accepting an `allow_out_of_stock_purchase` input (the caller's resolved
  `config_stock_checkout` value) — see
  `docs/developer/commerce/product-view-provider.md`. Added a regression
  test (`tests/mintcart/commerce/product-view-provider-test.php`, now 8
  scenarios).

The controller's original `$data['products']` array — the one
`default/template/product/category.twig` (and any third-party theme's own
category template) actually reads — is completely untouched: same keys,
same values, same computation, same order, on every theme, Mint-aware or
not. Five new language strings the product-card contract needs
(`text_rated`, `text_quick_order`, `text_badge_sale`,
`text_badge_out_of_stock`, `text_badge_new`) were added to
`catalog/language/en-gb/product/category.php`; `text_wishlist`/
`text_compare`/`text_add_to_cart` reuse the existing global
`button_wishlist`/`button_compare`/`button_cart` strings instead of
duplicating them.

Search, Manufacturer and Special are **not** wired yet — same pattern,
different controller, deliberately left for a future run rather than
building a shared `ProductListingService` ahead of a second real consumer
(`CLAUDE.md` §85).

Verified: `php -l` on both touched PHP files (clean). Two new smoke-test
scenarios added to `tests/mintcart/design/component-renderer-test.php`
(now 9 total): scenario 8 confirms `ComponentRenderer` resolves the real,
shipped `mint_default/template/mint/components/product-card.twig` at its
actual repository path (not just a fixture); scenario 9 confirms
`ProductViewProvider::build()`'s output shape is exactly what
`product-card.twig` expects, using the same field names the controller now
passes. The new page template's Twig `if`/`for`/`endif`/`endfor` tags were
checked for balanced nesting with a small script (same technique used for
Phase 6's `product-card.twig`). **No live end-to-end request test**: this
environment still has no MySQL server and no `vendor/twig/twig` installed
(no `composer install` has been run against `upload/composer.json`) — the
same pre-existing gap noted in every earlier phase, but now more consequential
since this is the first change to a controller that serves real traffic. A
human should smoke-test a real category page (ideally with `config_theme`
set to `mint_default`, and once more with it left on `default` to confirm
zero behavior change there) before this reaches production.

**Sub-step 3 — done, with one caveat.** Added
`catalog/view/theme/test_theme/` (`theme.json` with `"parent": null` —
deliberately not even declaring `mint_default` as a parent) with only a
`template/mint/components/product-card.twig` override, genuinely different
DOM from Mint Default's (single badge instead of a list, no secondary-image
swap, no wishlist/compare quick-actions, no quick-order button) while still
satisfying the same documented contract. This environment still has no
MySQL server and no `vendor/twig/twig` installed, so a real
`config_theme`-switch-and-load-a-page check wasn't possible; the closest
static proof was taken instead: `tests/mintcart/design/component-renderer-test.php`
scenario 10 confirms `ComponentRenderer::resolve()` returns `test_theme`'s
own file when it's active, and still returns Mint Default's file when
`mint_default` is active (no override anywhere) — both directions of the
fallback, against the real shipped repo files, not fixtures. Full writeup,
including the explicit caveat (resolution proven; live rendering still
needs a human), is in `docs/adr/002-theme-api-milestone-proof.md`.
**Verdict: pass** for what's testable in this environment — Phase 9+ may
proceed. A human should still confirm the override actually renders on a
real install before treating the milestone as *fully* closed end-to-end.

**Update (this run): Search is now the second listing controller wired to
`product-card`**, alongside Category. Rather than re-inline Category's own
`try`/`catch`-and-disable loop and private `mintActiveThemeDirectory()`
helper a second time, both were extracted into shared classes —
`MintCart\Design\ActiveTheme` (active-theme + Mint-awareness resolution)
and `MintCart\Design\ProductCardListRenderer` (the per-product
build-via-`ProductViewProvider`/render-via-`ComponentRenderer`/
degrade-on-failure step) — `docs/developer/theme-api/product-card-list-renderer.md`.
`ControllerProductCategory` and `ControllerCommonFooter` were both
refactored to call the shared classes instead of their own private copies,
with no behavioral change (verified: full `tests/mintcart/` suite re-run
end to end, still passing). `ControllerProductSearch` now builds
`$data['product_cards']` the same way Category does, rendered into a new
`mint_default/template/product/search.twig` (native `<form method="get">`
refine-search, no jQuery — `CLAUDE.md` §3). New test:
`tests/mintcart/design/product-card-list-renderer-test.php` (8 scenarios).
Full writeup, including a **pre-existing, unrelated gap** discovered (not
fixed) while writing the new template — `mint_default`'s (and stock
`default`'s) own `category.twig` silently renders several language-string
variables as blank because `category.php` never assigns them to `$data` —
is in `docs/upstream-diff.md`'s `search.php` entry.

**Update (a later run): Manufacturer and Special are now wired too** — see
further below for the full writeup of each. All four listing controllers
(Category, Search, Special, Manufacturer) share one
`MintCart\Design\ProductCardListRenderer`/`MintCart\Design\ActiveTheme`
pair; Phase 6/8's product-card pipeline is complete.

## Phase 9 — Presets

- [x] At least two radically different presets (e.g. Clean, Warm) on the same DOM —
      `assets/src/css/presets/clean.css` and `assets/src/css/presets/warm.css`,
      each scoped by `:root[data-mc-preset='<code>']`; both listed in
      `mint_default`'s `theme.json` `presets` array (was `[]`). Contract:
      `docs/developer/theme-api/presets.md`; decisions:
      `docs/adr/004-design-token-presets.md`
- [x] Differ in colors/fonts/radius/spacing/card look/button look only, not Twig —
      `warm.css` redeclares 25 semantic-tier `--mc-*` tokens (terracotta/gold
      palette, serif heading face, softer radius scale, roomier spacing,
      warm-tinted shadows) plus 11 preset-local `--mc-preset-warm-*`
      intermediates. Zero component CSS, zero Twig, zero component-local
      tokens touched. The card and button *look* changes because
      `mc-card`/`mc-button` read semantic tokens — the preset never names them

**Phase 9 is complete.** `clean.css` is a deliberately empty block: Clean's
values *are* `settings/tokens.css`'s values, so the file makes `clean` a real,
enumerable preset code without duplicating ~108 declarations that would then
have to never drift (`CLAUDE.md` §86). See ADR 004 decision 3.

The Preset/Theme boundary is now enforced rather than conventional:
`tests/frontend/preset-tokens-test.js` (wired to `npm test`) requires each
preset file to contain exactly one `:root[data-mc-preset='<code>']` block and
to redeclare only tokens `tokens.css` already declares, plus its own
`--mc-preset-<code>-*` intermediates. A preset that invents a token name is
introducing component-facing API no component reads; one that sets a
component-local token (`--mc-button-bg`) styles a single component out of step
with the rest. Both are now build-breaking rather than review-catchable. (An
earlier draft of this phase, superseded before merge, took the opposite
approach — directly overriding component-local tokens like
`--mc-product-card-radius`/`--mc-button-radius` with new component-scoped
selectors inside the preset file, for a more dramatic borderless-card/
pill-button look. That version is documented in ADR 004's alternatives if a
future preset genuinely needs a component-local override; the guardrail test
above will require an explicit, reviewed exception at that point rather than
letting it in silently.)

A consequence worth carrying forward: every MintCart component must read
semantic tokens for presets to reach it. Phase 4's components already do — but
as of ADR 004 that is a hard requirement, not a stylistic preference. A
component with a hardcoded color silently ignores every preset.

No `data-mc-preset` picker exists yet (that's Setup Wizard/Theme Settings
work, Phase 13/31, not started) — this phase only had to prove the token
mechanism itself, per `CLAUDE.md` §19/`MINTCART_TECHNICAL_PLAN.md` §45's
"prove the architecture before scaling features" rule.

Verified: `npm install` (0 vulnerabilities); `npm run build` (minified,
`index.min.css` 255.8kb → 257.3kb) and `npm run dev:css` (sourcemapped) both
compile cleanly; both `:root[data-mc-preset=clean]` and
`:root[data-mc-preset=warm]` are present in the bundled output, with
`--mc-primary` appearing twice (base `var(--mc-color-mint-600)`, warm
`var(--mc-preset-warm-terracotta-600)`); `npm test` passes 7 checks. The test
was **negative-tested** against three deliberate violations — an invented
token name inside the block, a second rule block, and a `.mc-product-card`
rule in a preset file — each of which failed the run with exit 1, so the
checks are not passing vacuously. No PHP touched by this phase, so the
`tests/mintcart/` suite doesn't apply.

**Not verified, needs a human:** whether the two presets actually *look* like
coherent, radically different designs — no check can assert that, and this
environment still has no MySQL/`vendor/twig/twig` to render a real page (the
same gap carried since Phase 3). Also unverified: text contrast under Warm
against WCAG 2.2 AA, whether any component is still showing mint green (which
would mean a hardcoded color, a component bug), and whether both presets apply
cleanly to `test_theme`'s different DOM. All five items are listed in
`docs/MANUAL_VERIFICATION.md`'s new Phase 9 section.

**Not wired yet, by design:** nothing writes `data-mc-preset` onto `<html>` at
runtime — Mint Default's page templates don't emit it and no admin UI selects
it. That belongs with theme settings (`MINTCART_FUNCTIONAL_SCOPE.md` §9) and
its declarative `settings.json` schema (Implementation Guide §31), neither of
which exists; building the writer ahead of the form that sets the value would
be API ahead of a consumer (`CLAUDE.md` §85). Until then a preset is inspected
by setting the attribute by hand.

**Theme live preview (`MINTCART_FUNCTIONAL_SCOPE.md` §31) — RESEARCH item
resolved, not yet implemented.** `docs/adr/008-theme-live-preview.md` fixes
the target architecture the future Theme Settings admin form must build
against: a `draft`/`published` settings-row pair per store, a signed
short-TTL preview token as an iframe query parameter (never a session flag,
never cached), token-only changes (color/spacing/`data-mc-preset`) applied
client-side via `postMessage` against ADR 004's `data-mc-preset`/`--mc-*`
mechanism with no reload, and DOM-changing theme/component changes falling
back to a real iframe reload. Explicitly not a visual page editor, per the
Functional Scope's own instruction. No code, schema, or admin screen exists
yet — this is documentation only, matching how ADR 005/006/007 resolved
their own Functional Scope RESEARCH items (rollback, autosave, editor).

## Phase 10 — Homepage Blocks

- [x] Block API storage (`mc_page`, `mc_page_description`, `mc_block`,
      `mc_block_description`) — see "Sub-step 1" below
- [x] Block Provider / Renderer / Template separation — see "Sub-step 2" below
- [x] First blocks: Products, Hero, Categories, Banner Grid, Benefits, FAQ,
      Testimonials, Brands, Text/Image, CTA, Newsletter, Spacer, Text, HTML
      and Gallery — the full first-wave block catalog
      (`MINTCART_IMPLEMENTATION_GUIDE.md` §51) is now done (see "Sub-step 2",
      `docs/developer/blocks/hero.md`, `docs/developer/blocks/categories.md`,
      "Sub-step 3" below)
- [~] Homepage Builder admin editor (add/edit/delete/enable-disable/reorder
      `mc_block` rows) — see "Sub-step 3" below. Reordering uses a plain
      per-row `sort_order` number input, **not** SortableJS/`McSortable` drag-
      and-drop — see that sub-step for why, and the real architectural
      question this run is flagging rather than guessing at.

In progress. PR #16 (storage), a previous run (Provider/Renderer + Products
block, merged to `master`), this run (Sub-step 3: the admin editor).

**Sub-step 3 (this run): Homepage Builder admin editor.** Until this run,
Phase 10's "Next step" was correct that a block instance had to be inserted
into the database by hand to ever be visible — no admin screen existed for
`mc_page`/`mc_block` at all. `ModelDesignHomepage`
(`upload/admin/model/design/homepage.php`) and `ControllerDesignHomepage`
(`upload/admin/controller/design/homepage.php`) add one: `Design > Homepage`
(new menu entry in `upload/admin/controller/common/column_left.php`, gated
by the `design/homepage` permission `user_permission.php` auto-discovers)
lists/adds/edits/deletes blocks on the `home` page, created lazily on first
visit.

This run's step-0 duplicate-work check found all fourteen of Phase 10's
remaining first-wave block types (Hero, Categories, Banner Grid, Benefits,
Text/Image, Brands, Testimonials, FAQ, CTA, Newsletter, Spacer, Text, HTML,
Gallery) already covered by open pull requests, each adding that type's own
Provider + `blocks/<type>.twig` following the shape the Products block
established — so this run picked the other unclaimed piece of Phase 10
instead (the admin editor, explicitly named in the previous "Next step") to
avoid redoing or colliding with any of that in-flight work.

**Real architectural question this run is flagging, not guessing at**
(`CLAUDE.md` §20): the new admin screen is built on the *legacy* OpenCart
admin theme's Bootstrap 3 conventions (`.panel`/`.btn`/`form-horizontal` —
the same shape `design/banner.php` and `extension/module/latest.php`
already use), **not** MintCart's own `mc-*`/Bootstrap 5 admin primitives,
even though Phase 11's `McSortable` (`docs/developer/components/sortable.md`)
was explicitly built with "Homepage Builder's block list" named as its
expected first consumer. Reason: MintCart's built asset bundle
(`assets/dist/css/index.min.css` / `assets/dist/js/admin.min.js`) has never
been enqueued into the admin theme's shared `header`/`column_left`/`footer`
chrome anywhere in this repository — and that chrome wraps *every* admin
screen, not just this one, so enqueuing it here would load MintCart's
Bootstrap 5 CSS globally alongside the legacy admin theme's Bootstrap 3 CSS
on every other admin screen too, which `CLAUDE.md` §10 explicitly forbids.
Whether to migrate the whole admin shell to Bootstrap 5, scope MintCart's
own screens some other CSS-isolated way, or keep new MintCart admin screens
on the legacy theme until a dedicated "Mint Admin Shell" phase is a real
product/architectural decision for the project owner — full writeup and the
options considered in `docs/developer/blocks/homepage-admin.md`'s "Why this
screen uses the legacy admin theme" section, plus this PR's description.
Reordering therefore uses a plain per-row `sort_order` number input (same
pattern `banner_image` rows already use) rather than `McSortable` drag-and-
drop; `docs/developer/blocks/homepage-admin.md` documents this as a
self-contained follow-up once the admin-shell question is resolved.

The block type registry (`ControllerDesignHomepage::TYPES`) lists only
`products` today — the one type with a real, merged provider. Deliberately
does not add settings-form support for any of the fourteen in-flight PRs'
block types ahead of knowing their merged shape (see above); extending it
is a one-array-entry follow-up once each lands. The `category_id` settings
field is a plain numeric input, not an `McEntityPicker`/category
autocomplete, for the same reason Phase 12's category picker backend
(`mintcart/entity/category`) was an open PR as of this run — avoiding a
second, independent category-selection UI that duplicates that work.

Verified: `php -l` clean on every touched/added PHP file; full
`tests/mintcart/` suite (12 files, including the new
`tests/mintcart/design/homepage-model-test.php`, a `FakeDb`-backed test
covering `ModelDesignHomepage`'s page-creation, block CRUD, sort-order
persistence — including its cross-page guard — and description round-trip)
passing. No PHP formatting/style tool configured in this repo to run.
`docs/upstream-diff.md` updated for the `column_left.php` menu-entry diff.
Same pre-existing gap as every earlier phase: no MySQL/live OpenCart admin
session in this environment, so no live end-to-end request was exercised —
`docs/MANUAL_VERIFICATION.md`'s Phase 10 entry and
`docs/developer/blocks/homepage-admin.md`'s "Manual verification" section
are the checklist for a human to work through on a real install.

**Hero block (merged separately, reconciled here):** `MintCart\Blocks\HeroBlockProvider`
+ `ControllerCommonHome::renderHeroBlock()` + `blocks/hero.twig` +
`.mc-block--hero` CSS — same Provider/Renderer/Template shape as Products.
Full writeup: `docs/developer/blocks/hero.md`. Not yet wired into
`ControllerDesignHomepage::TYPES` (see "Sub-step 3" above for why that's a
deliberate, separate follow-up) — visible today only via a hand-inserted
`mc_block` row with `type = 'hero'`.

**FAQ block (merged separately, reconciled here):** `MintCart\Blocks\FaqBlockProvider`
+ `ControllerCommonHome::renderFaqBlock()` + `blocks/faq.twig` +
`.mc-faq`/`__item`/`__question`/`__answer` CSS (native `<details>`/`<summary>`
disclosure, no JS). Full writeup: `docs/developer/blocks/faq.md`. Also not
yet wired into `ControllerDesignHomepage::TYPES`.

**Testimonials block (merged separately, reconciled here):**
`MintCart\Blocks\TestimonialsBlockProvider` + `ControllerCommonHome::renderTestimonialsBlock()`
+ `blocks/testimonials.twig` + `.mc-testimonial`/`__quote`/`__author`/
`__avatar`/`__author-details`/`__author-name`/`__author-role` CSS. Each
item's optional avatar is resized via `tool/image`, same `is_file()`-guarded
pattern the stock Banner module uses. Full writeup:
`docs/developer/blocks/testimonials.md`. Also not yet wired into
`ControllerDesignHomepage::TYPES`.

**Brands block (merged separately, reconciled here):**
`MintCart\Blocks\BrandsBlockProvider` + `ControllerCommonHome::renderBrandsBlock()`
+ `blocks/brands.twig` + `.mc-brands`/`.mc-brand`/`__logo` CSS — a wrapping
strip of logo links (not a fixed-column grid like Banner Grid). Full
writeup: `docs/developer/blocks/brands.md`. Also not yet wired into
`ControllerDesignHomepage::TYPES`.

**Text/Image block (merged separately, reconciled here):**
`MintCart\Blocks\TextImageBlockProvider` + `ControllerCommonHome::renderTextImageBlock()`
+ `blocks/text-image.twig` + `.mc-block__text-image-media` CSS — covers
both the "Text" and "Text + Image" first-wave catalog entries (an optional
`image` setting; a block with none renders as plain text). Body renders
through `mc-prose`. Full writeup: `docs/developer/blocks/text-image.md`.
Also not yet wired into `ControllerDesignHomepage::TYPES`.

**CTA block (merged separately, reconciled here):**
`MintCart\Blocks\CtaBlockProvider` + `ControllerCommonHome::renderCtaBlock()`
+ `blocks/cta.twig` + `.mc-block--cta`/`__cta-inner`/`__cta-text` CSS — pure
content (title, supporting text, optional button), the simplest first-wave
block type. Full writeup: `docs/developer/blocks/cta.md`. Also not yet
wired into `ControllerDesignHomepage::TYPES`.

**Newsletter block (merged separately, reconciled here):**
`MintCart\Blocks\NewsletterBlockProvider` + `ControllerCommonHome::renderNewsletterBlock()`
+ `blocks/newsletter.twig` + `.mc-block__newsletter-inner`/`__text`/`__form`
CSS — same "title/text, optional action" shape as CTA, but with an email
input + submit button instead of a link-style button. Full writeup:
`docs/developer/blocks/newsletter.md`. Also not yet wired into
`ControllerDesignHomepage::TYPES`.

**Spacer block (merged separately, reconciled here):**
`MintCart\Blocks\SpacerBlockProvider` + `ControllerCommonHome::renderSpacerBlock()`
+ `blocks/spacer.twig` + `.mc-block--spacer`/`-sm`/`-md`/`-lg`/`-xl` CSS —
the simplest block type, purely structural, no localized content. Full
writeup: `docs/developer/blocks/spacer.md`. Also not yet wired into
`ControllerDesignHomepage::TYPES`.

**Text block (merged separately, reconciled here):**
`MintCart\Blocks\TextBlockProvider` + `ControllerCommonHome::renderTextBlock()`
+ `blocks/text.twig`, rendering its content through the shared `mc-prose`
rich-text container (`assets/src/css/elements/prose.css`, also used by
Text/Image). No product/OpenCart data dependency at all. Full writeup:
`docs/developer/blocks/text.md`. Also not yet wired into
`ControllerDesignHomepage::TYPES`.

**HTML block (merged separately, reconciled here):**
`MintCart\Blocks\HtmlBlockProvider` + `ControllerCommonHome::renderHtmlBlock()`
+ `blocks/html.twig` (renders the raw `html` content via the `|raw`
filter) — the simplest block type, no product query, no CSS of its own.
Full writeup: `docs/developer/blocks/html.md`. Also not yet wired into
`ControllerDesignHomepage::TYPES`.

**Gallery block (merged separately, reconciled here):**
`MintCart\Blocks\GalleryBlockProvider` + `ControllerCommonHome::renderGalleryBlock()`
+ `blocks/gallery.twig` + `.mc-block--gallery`/`.mc-gallery__item` CSS —
same Provider/Renderer/Template shape as Banner Grid, but square-ish
images (600×600 default) and no `url`/link per item. Full writeup:
`docs/developer/blocks/gallery.md`. Also not yet wired into
`ControllerDesignHomepage::TYPES`.

**Sub-step 1:** `upload/system/mintcart/migrations/202608231405_create_mc_pages.php`
and `202608231406_create_mc_blocks.php` create `mc_page`/`mc_page_description`
and `mc_block`/`mc_block_description` exactly as documented in
`MINTCART_TECHNICAL_PLAN.md` §16 / `MINTCART_IMPLEMENTATION_GUIDE.md` §34 — one
shared table per entity type (`type` distinguishes Hero/Products/Categories/...,
not a table per block type), InnoDB/utf8mb4, composite `(entity_id, language_id)`
description PKs and no `FOREIGN KEY` constraints, mirroring OpenCart's own
`oc_category`/`oc_category_description` split. `settings_json`/`content_json`
are `LONGTEXT`, not the MySQL `JSON` column type, for broad shared-hosting
compatibility (`MINTCART_TECHNICAL_PLAN.md` §2). Verified via
`tests/mintcart/core/mc-block-migrations-test.php`, which runs the real
migration files through `MigrationManager` and asserts on the emitted
`CREATE TABLE` statements.

**Update (2026-08-24): run live against a real MySQL install** (Laragon,
`mintcart` database, `oc_` prefix) — closing the "no live MySQL in this
environment" gap this entry originally carried. Both migrations applied
cleanly; all five tables (`mc_migration`, `mc_page`, `mc_page_description`,
`mc_block`, `mc_block_description`) confirmed present via `SHOW TABLES`;
`SHOW CREATE TABLE` on `mc_page`/`mc_block` confirmed the schema matches
the migration source exactly (InnoDB, utf8mb4, `settings_json` as
`LONGTEXT` not `JSON`, correct PK/unique/index definitions); a second
`MigrationManager::run()` applied nothing, confirming idempotency against
a real tracking table, not just the in-memory fake DB double the
standalone test uses. No Block Provider/Renderer/Twig template exists
yet — storage only, matching how every previous phase's first migration
landed ahead of its consumer.

**Sub-step 2 (this run): Block Provider / Renderer / Template separation,
Products block end-to-end.** `MintCart\Blocks\BlockRenderer`
(`upload/system/library/mintcart/blocks/blockrenderer.php`) is a thin
wrapper around `MintCart\Design\ComponentRenderer` — that class's
constructor gained an optional 3rd `$kind` parameter (default
`'components'`, fully backward compatible) so Blocks reuses the exact same
tested active-theme → `theme.json` parent chain → Mint Default fallback
walk instead of a second copy of it. `MintCart\Blocks\ProductsBlockProvider`
normalizes a raw `mc_block` row's settings (`category_id`/`limit`/`sort`/
`order`, with safe defaults); it does not query products itself, same
"no OpenCart dependency" shape as `ProductViewProvider`/`FooterViewProvider`.
`ModelMintcartBlock` (`upload/catalog/model/mintcart/block.php`, a plain
OpenCart model) reads `mc_page`/`mc_block`/`mc_block_description`.
`ControllerCommonHome` (`upload/catalog/controller/common/home.php`) is the
real consumer — a new `buildMintBlocks()` stage, gated by the same
Mint-aware-theme check every other pipeline uses, loads the `'home'`
`mc_page`, renders each `type='products'` block by querying products and
reusing `ProductCardListRenderer` (same pipeline Category/Search/Special/
Manufacturer already share), and passes the result to the view as
`$data['mc_blocks']`. `mint_default/template/common/home.twig` is a new
file (the theme previously had no home template override at all, falling
through to `default`'s) that loops `mc_blocks` full-width above the
existing column/content-position layout, which is otherwise unchanged.
Full writeup: `docs/developer/blocks/products.md`; upstream diff entries
for `home.php`/`home.twig`/the new `common/home` language file in
`docs/upstream-diff.md`. Verified: `php -l` clean on every touched/added
PHP file; full `tests/mintcart/` suite (11 files, including the 2 new
`tests/mintcart/blocks/*-test.php`) passing; `npm run build:css` compiles
cleanly with the new `mc-block`/`mc-block--products` CSS
(`assets/src/css/components/block.css`). Same pre-existing gap as every
earlier phase: no MySQL/`vendor/twig/twig` in this environment, so no live
end-to-end request was exercised — a human should insert a `mc_page` row
(`code='home'`) and an `mc_block` row (`type='products'`,
`settings_json='{}'`) on a real install and confirm the homepage renders a
Products block.

**Sub-step 3 (a later run): Hero block, end to end.**
`MintCart\Blocks\HeroBlockProvider`
(`upload/system/library/mintcart/blocks/heroblockprovider.php`) normalizes
a `type='hero'` block row's settings/content (title, subtitle, cta_label,
cta_url, image, image_width, image_height) — same "no OpenCart dependency"
shape as `ProductsBlockProvider`. Unlike Products, Hero needs no product
query, so `ControllerCommonHome::renderHeroBlock()` is much smaller: it
only resolves an optional background `image` via OpenCart's `tool/image`
model (guarded with `is_file(DIR_IMAGE . ...)`, mirroring the stock Banner
module's own pattern) and renders `blocks/hero.twig` through the same
`BlockRenderer` `renderProductsBlock()` already constructs.
`buildMintBlocks()`'s per-block dispatch was reshaped from a single
`if('products')` into `if`/`elseif`/`else` so a third block type is one more
branch, not another copy of the append-if-non-empty check. New CSS
(`.mc-block--hero` in `assets/src/css/components/block.css`) composes an
inline `--mc-block-hero-image` custom property (data-driven, per
`MINTCART_IMPLEMENTATION_GUIDE.md` §18) rather than baking a `background`
shorthand into the Twig template, so a theme override can restyle the
overlay without touching how the image URL is passed in. Full writeup:
`docs/developer/blocks/hero.md`; upstream diff update in
`docs/upstream-diff.md`'s `home.php` entry. Verified: `php -l` clean on
every touched/added PHP file; the new
`tests/mintcart/blocks/hero-block-provider-test.php` (3 scenarios); full
`tests/mintcart/` suite re-run end to end (12 files) — all pass; `npm run
build` compiles cleanly, `grep` confirms `mc-block--hero`/
`mc-block__hero-subtitle` land in the bundled `index.min.css`; the new
`hero.twig`'s `if`/`endif` tag nesting checked balanced (same script-based
technique used for every earlier `.twig` file in this repo). Same
pre-existing gap as every earlier phase: no MySQL/`vendor/twig/twig` in
this environment, so no live end-to-end request was exercised — a human
should insert an `mc_block` row (`type = 'hero'`) on a real install and
confirm the homepage renders a Hero banner, both with and without a
background `image` setting.

**Sub-step 3 (this run): Categories block.** `MintCart\Blocks\CategoriesBlockProvider`
(`upload/system/library/mintcart/blocks/categoriesblockprovider.php`)
normalizes a `type='categories'` `mc_block` row's settings (`parent_id`,
`limit`), same "no OpenCart dependency" shape as `ProductsBlockProvider`.
`ControllerCommonHome::renderCategoriesBlock()` queries
`ModelCatalogCategory::getCategories($parent_id)` — the same core model
`common/menu`/`extension/module/category` already use — builds each
category's link path using that same controller's `<parent_id>_<category_id>`
convention, resizes its image via the existing
`theme_<theme>_image_category_width/height` config keys, and renders
`blocks/categories.twig` through `BlockRenderer`. `buildMintBlocks()`'s
per-block dispatch is now `if`/`elseif`/`else` (products/categories/other)
instead of a single `if`. Full writeup: `docs/developer/blocks/categories.md`;
increment note added to `docs/upstream-diff.md`'s existing `home.php` entry.
Verified: `php -l` clean on every touched/added PHP file;
`tests/mintcart/blocks/categories-block-provider-test.php` (3 scenarios,
new) and a new Scenario 3 in `block-renderer-test.php` (real
`categories.twig` resolves) both pass; full `tests/mintcart/` suite
re-run end to end (13 files) — all pass; `npm run build` compiles cleanly,
`grep` confirms `mc-category-card`/`mc-category-card__media`/
`mc-category-card__name` land in `assets/dist/css/index.min.css`; `npm
test` (preset-token contract check) still passes. Same pre-existing gap as
every earlier phase: no MySQL/`vendor/twig/twig` in this environment — a
human should insert an `mc_block` row (`type='categories'`,
`settings_json='{}'`) on a real install and confirm the homepage renders a
Categories grid.

**Sub-step 3 (this run): Banner Grid block.** `MintCart\Blocks\BannerGridBlockProvider`
(`upload/system/library/mintcart/blocks/bannergridblockprovider.php`)
normalizes a `type='banner-grid'` `mc_block` row's settings/content — a
list of items (each `image`/`url`/`image_width`/`image_height` in
`settings_json`, `alt`/`caption` in `content_json`) matched up by array
position, plus a `columns` setting (2-4, default 3) precomputed into a
`col_width` (12/columns) so the Twig template does no grid arithmetic of
its own. `ControllerCommonHome::renderBannerGridBlock()` resolves each
item's image via `$this->model_tool_image->resize()` — same
`is_file`-guarded call the stock Banner module uses — dropping any item
whose file no longer exists rather than rendering a broken `<img>`; falls
back to 600×400 when an item didn't set its own width/height. No product
query or `ProductViewProvider` involved, unlike the Products block — a
banner item's data all comes from the block's own settings. New CSS
component `assets/src/css/components/banner-grid.css`
(`.mc-banner-grid__item`/`__image`/`__caption`), imported into
`assets/src/css/index.css` after `block.css`. Full writeup:
`docs/developer/blocks/banner-grid.md`; upstream diff entry for
`home.php` extended in `docs/upstream-diff.md`. Verified: `php -l` clean
on every touched/added PHP file; full `tests/mintcart/` suite (12 files,
including the new `tests/mintcart/blocks/banner-grid-block-provider-test.php`,
3 scenarios) passing; `npm run build` (both `build:js` and `build:css`)
compiles cleanly, with `mc-banner-grid__item`/`__image`/`__caption`
present in the built `index.min.css`. Same pre-existing gap as Sub-step 2:
no MySQL/`vendor/twig/twig` in this environment, so no live end-to-end
request was exercised — a human should insert an `mc_block` row
(`type='banner-grid'`, `settings_json` with at least one item pointing at
a real file under `DIR_IMAGE`) on a real install and confirm the homepage
renders a Banner Grid block.

**Sub-step 3 (this run): Benefits block.** `MintCart\Blocks\BenefitsBlockProvider`
(`upload/system/library/mintcart/blocks/benefitsblockprovider.php`)
normalizes a `type='benefits'` `mc_block` row into a `title` plus a list of
`items` (`icon`/`title`/`description`), matching `settings_json`'s
per-item `icon` (structural) against `content_json`'s per-item
`title`/`description` (localized) by array position. Pure content block —
no product query, no image resize, no OpenCart model of any kind, so
`ControllerCommonHome::renderBenefitsBlock()` is the simplest render
method yet: normalize, then render through the existing `BlockRenderer`.
`buildMintBlocks()`'s dispatch gained an `elseif ($block['type'] ===
'benefits')` branch alongside the existing `'products'` one.
`mint_default/template/mint/blocks/benefits.twig` + `.mc-benefit`/
`__icon`/`__title`/`__description` CSS (colocated in
`assets/src/css/components/block.css`, single consumer so far). Icons are
a placeholder `data-mc-icon` attribute pending the not-yet-built
`mc_icon()` SVG renderer — same pattern `footer.twig`'s social links
already use. Full writeup: `docs/developer/blocks/benefits.md`. Verified:
`php -l` clean on every touched/added PHP file; full `tests/mintcart/`
suite (12 files, including the new
`tests/mintcart/blocks/benefits-block-provider-test.php` and a 3rd
scenario added to `tests/mintcart/blocks/block-renderer-test.php`)
passing; `npm run build` compiles cleanly, `mc-benefit`/`__icon`/`__title`/
`__description` confirmed present in the built
`assets/dist/css/index.min.css`; `benefits.twig`'s `{% if %}`/`{% for %}`
tag nesting checked balanced. Same pre-existing gap as every earlier
phase: no MySQL/`vendor/twig/twig` in this environment, so no live
end-to-end request was exercised.

**Sub-step 3 (this run): FAQ block.** `MintCart\Blocks\FaqBlockProvider`
(`upload/system/library/mintcart/blocks/faqblockprovider.php`) normalizes a
`type='faq'` `mc_block` row into `title` + a list of `{question, answer}`
items — unlike Products/Banner Grid, an FAQ item has no structural
(non-localized) data at all, so both fields live entirely in
`content_json` and `settings_json` is accepted but currently unused.
Invalid items (missing question or answer, or not an array) are dropped
during normalization rather than reaching the template half-empty.
`ControllerCommonHome::renderFaqBlock()` mirrors `renderProductsBlock()`'s
shape with no product query/image resize involved — a `null` return
(empty `items` after normalization) is skipped by `buildMintBlocks()`'s
dispatch, reshaped from a single `if('products')` into
`if`/`elseif`/`else` so a third block type is one more branch, not a
duplicated append-check. `mint_default`'s new `blocks/faq.twig` renders
each item as a native `<details>`/`<summary>` disclosure widget —
deliberately not Bootstrap's Collapse JS, since a native element already
provides expand/collapse and correct semantics with no per-instance ID
scheme needed (`MINTCART_IMPLEMENTATION_GUIDE.md` §3/§5). New
`.mc-faq`/`__item`/`__question`/`__answer` CSS colocated in the existing
`assets/src/css/components/block.css` (single consumer so far, `CLAUDE.md`
§17). Full writeup: `docs/developer/blocks/faq.md`; increment note added to
`docs/upstream-diff.md`'s existing `home.php` entry.

New test: `tests/mintcart/blocks/faq-block-provider-test.php` (3
scenarios, standalone, no OpenCart dependency), plus a 3rd scenario added
to `tests/mintcart/blocks/block-renderer-test.php` confirming the real
`faq.twig` resolves. Verified: `php -l` clean on every touched/added PHP
file, and on the full `upload/` + `tests/` tree; full
`tests/mintcart/**/*-test.php` suite passes locally (12 files, including
the new FAQ provider test); `npm install` + `npm run build` compile
cleanly, `grep` confirms `mc-faq`/`__item`/`__question`/`__answer` land in
the bundled `assets/dist/css/index.min.css`; `npm test` (preset-token
contract check) still passes, unaffected; `faq.twig`'s
`{% if %}`/`{% endif %}`/`{% for %}`/`{% endfor %}` tag nesting checked
balanced with the same script-based technique used for every earlier
`.twig` file in this repo. Not verified (same pre-existing gap as every
earlier phase): no live MySQL/`vendor/twig/twig` in this sandbox, so no
live end-to-end homepage render was exercised — a human should insert an
`mc_block` row (`type='faq'`) with a couple of `content_json` items on a
real install, confirm the homepage renders it, and click a question to
confirm the native disclosure toggle actually expands/collapses.

**Sub-step 3 (this run): Testimonials block.** A pure content block (no
product query) — `MintCart\Blocks\TestimonialsBlockProvider`
(`upload/system/library/mintcart/blocks/testimonialsblockprovider.php`)
normalizes a `type='testimonials'` row into `title` + a list of
`{quote, author_name, author_role, avatar, avatar_width, avatar_height,
rating}` items, matching structural per-item settings (`avatar`/`rating`)
against localized per-item content (`quote`/`author_name`/`author_role`)
by array position — same convention Banner-Grid/Benefits-style block
providers use. An item with a blank quote or author is dropped rather
than rendered empty; an out-of-range `rating` normalizes to `null`.
`ControllerCommonHome::renderTestimonialsBlock()` resolves each item's
optional avatar via `model_tool_image->resize()`, `is_file()`-guarded the
same way the stock Banner module is, degrading a missing file to no
avatar rather than a broken `<img>` or a dropped testimonial.
`buildMintBlocks()`'s dispatch was reshaped from a single `if('products')`
into `if`/`elseif`/`else` so a second block type is one more branch, not
a duplicated append-check. New
`template/mint/blocks/testimonials.twig` (Mint Default) reuses the exact
`mc-rating` markup `product-card.twig` already has for its optional star
rating — no new rating component introduced. New `.mc-testimonial`/
`__quote`/`__author`/`__avatar`/`__author-name`/`__author-role` CSS
colocated in the existing `assets/src/css/components/block.css` (single
consumer so far, `CLAUDE.md` §17: no dedicated file before a real second
consumer). Also fixed a `.gitignore` false positive along the way: the
pre-existing `upload/**/test*` glob (meant for runtime/scratch test data)
was silently swallowing these new source files because their names start
with "test" — same false-positive class the `test_theme/` negation
already fixed once; fixed with two more targeted `!` negations. Full
writeup: `docs/developer/blocks/testimonials.md`; upstream diff increment
in `docs/upstream-diff.md`'s existing `home.php` entry. Verified: `php -l`
clean on every touched/added PHP file and on the full `upload/` + `tests/`
tree; full `tests/mintcart/**/*-test.php` suite passes (12 files,
including the new testimonials provider test and Scenario 3 added to
`block-renderer-test.php`); `npm install` (0 vulnerabilities) +
`npm run build` compile cleanly, `mc-testimonial*` confirmed present in
the built `assets/dist/css/index.min.css`; `npm test` (preset-token
contract check) still passes, unaffected; `testimonials.twig`'s
`{% if %}`/`{% endif %}`/`{% for %}`/`{% endfor %}` tag nesting checked
balanced with the same script-based technique used for every earlier
`.twig` file in this repo. Not verified: no live MySQL/`vendor/twig/twig`
in this sandbox, so no live end-to-end homepage render was exercised —
same pre-existing gap every earlier Twig-touching phase has carried. A
human should insert an `mc_block` row (`type='testimonials'`) with a
couple of items (with and without an `avatar`/`rating`) on a real install
and confirm the homepage renders the grid correctly.

**Sub-step 3 (this run): Brands block.** `MintCart\Blocks\BrandsBlockProvider`
(`upload/system/library/mintcart/blocks/brandsblockprovider.php`)
normalizes a `type='brands'` `mc_block` row into a list of logo items
(`image`/`url`/`image_width`/`image_height` from `settings_json`, `alt`
from `content_json`, matched by array position) — same split as
`BannerGridBlockProvider`, but simpler (no `columns`, no `caption`; a
Brands block renders as a wrapping logo strip, not a fixed grid).
`ControllerCommonHome::renderBrandsBlock()` resolves each item's logo via
`tool/image`, dropping any item whose file no longer exists.
`buildMintBlocks()`'s dispatch gained an `elseif ($block['type'] === 'brands')`
branch. `mint_default/template/mint/blocks/brands.twig` + new
`.mc-brands`/`.mc-brand`/`.mc-brand__logo` CSS (colocated in the existing
`assets/src/css/components/block.css`). Full writeup:
`docs/developer/blocks/brands.md`; increment note in `docs/upstream-diff.md`'s
`home.php` entry. Verified: `php -l` clean; full `tests/mintcart/` suite
(12 files, including the new `brands-block-provider-test.php` and a 3rd
`block-renderer-test.php` scenario) passing; `npm run build` compiles
cleanly, `mc-brands`/`mc-brand`/`mc-brand__logo` confirmed present in the
built `assets/dist/css/index.min.css`; `npm test` (preset-token contract)
still passing. Same pre-existing gap as every earlier phase: no live
MySQL/Twig render exercised in this sandbox — a human should insert an
`mc_block` row (`type='brands'`) with a couple of items (with and without
a `url`) on a real install and confirm the homepage renders a logo strip.

**Sub-step 3 (this run): Text/Image block.**
`MintCart\Blocks\TextImageBlockProvider`
(`upload/system/library/mintcart/blocks/textimageblockprovider.php`)
normalizes a `type='text-image'` `mc_block` row (structural `image`/
`image_width`/`image_height`/`image_position` in `settings_json`, localized
`title`/`body`/`image_alt` in `content_json`) — no OpenCart dependency at
all, same shape as `ProductsBlockProvider`. Covers both the "Text" and
"Text + Image" catalog entries (`MINTCART_IMPLEMENTATION_GUIDE.md` §33) as
one block type, since the `image` setting is simply optional.
`ControllerCommonHome::renderTextImageBlock()` resolves the optional image
via `model_tool_image->resize()`, `is_file()`-guarded exactly like
`renderHeroBlock()`; `buildMintBlocks()`'s dispatch gained an
`elseif ($block['type'] === 'text-image')` branch.
`template/mint/blocks/text-image.twig` (Mint Default) is a two-column
Bootstrap layout (text always first in source order; `image_position:
'left'` uses Bootstrap's `.order-md-first` utility rather than a second
markup branch) that degrades to a full-width text-only column when there is
no image. `body` is rendered inside a new `.mc-prose` class — the CSS
architecture's `elements/` layer's first file
(`assets/src/css/elements/prose.css`, `MINTCART_IMPLEMENTATION_GUIDE.md`
§11/§20), since a rich-text block body is its first real consumer.
New test: `tests/mintcart/blocks/text-image-block-provider-test.php` (3
scenarios), plus a 3rd scenario added to
`tests/mintcart/blocks/block-renderer-test.php`. Full writeup:
`docs/developer/blocks/text-image.md`; increment note in
`docs/upstream-diff.md`'s existing `home.php` entry. Verified: `php -l`
clean on every touched/added PHP file; full `tests/mintcart/` suite (13
files) passing; `npm install` + `npm run build` compile cleanly, confirmed
`mc-prose`/`text-image-media` land in the bundled
`assets/dist/css/index.min.css`; `npm test` (preset-token contract check)
still passes. Same pre-existing gap as every earlier phase: no
MySQL/`vendor/twig/twig` in this environment, so no live end-to-end
homepage render was exercised — a human should insert an `mc_block` row
(`type='text-image'`) with and without an `image` setting on a real install
and confirm the homepage renders both a plain-text block and a two-column
block, image on each side.

**Sub-step 3 (this run): CTA block.** `MintCart\Blocks\CtaBlockProvider`
(`upload/system/library/mintcart/blocks/ctablockprovider.php`) normalizes a
`type='cta'` `mc_block` row's `content_json` (`title`/`text`/`cta_label`/
`cta_url` — pure content, no structural `settings_json` used, no OpenCart
dependency, same shape as `ProductsBlockProvider`). A label with no URL (or
vice versa) normalizes to "no button" rather than a dead/unlabeled link.
`ControllerCommonHome::renderCtaBlock()` is the simplest render method yet
— normalize, then render through the existing `BlockRenderer`, no
query/model/image-resize at all — `buildMintBlocks()`'s dispatch gained an
`elseif ($block['type'] === 'cta')` branch. New
`upload/catalog/view/theme/mint_default/template/mint/blocks/cta.twig`
(renders nothing when title/text/button are all empty) and
`.mc-block--cta`/`.mc-block__cta-inner`/`.mc-block__cta-text` CSS,
colocated in the existing `assets/src/css/components/block.css` (single
consumer so far). Full writeup: `docs/developer/blocks/cta.md`; increment
note in `docs/upstream-diff.md`'s existing `home.php` entry. Verified:
`php -l` clean on every touched/added PHP file and the full `upload/` +
`tests/` tree; full `tests/mintcart/` suite (12 files, including the new
`cta-block-provider-test.php` and a 3rd `block-renderer-test.php`
scenario) passing; `npm install` + `npm run build` compile cleanly,
confirmed `mc-block--cta` present in the built
`assets/dist/css/index.min.css`; `npm test` (preset-token contract check)
still passes. `cta.twig`'s `{% if %}`/`{% endif %}` tag nesting checked
balanced (4 opens, 4 closes). Not verified: no live MySQL/`vendor/twig/twig`
in this sandbox, same pre-existing gap as every earlier Twig-touching
phase — a human should insert an `mc_block` row (`type='cta'`) on a real
install and confirm the homepage renders title/text/button correctly, and
that a title-only block (no button) still renders.

**Sub-step 3 (this run): Newsletter block.** `MintCart\Blocks\NewsletterBlockProvider`
(`upload/system/library/mintcart/blocks/newsletterblockprovider.php`)
normalizes a `type='newsletter'` `mc_block` row — the simplest block type
implemented so far (no query, no image resize at all).
`ControllerCommonHome::renderNewsletterBlock()` renders it through
`BlockRenderer`; `buildMintBlocks()`'s dispatch gained an
`elseif ($block['type'] === 'newsletter')` branch. Since stock OpenCart 3
has no public "subscribe with just an email" endpoint (the closest
built-in feature, `account/newsletter`, is a logged-in customer's opt-in
checkbox, not a guest-submittable form), the block's subscribe form is
only rendered when a real `action` URL is configured on the block — the
same "no dead-end form" rule `FooterViewProvider::buildNewsletter()`
already established; with no `action`, the block still renders as a
plain title/text announcement. Full writeup, including the endpoint
rationale: `docs/developer/blocks/newsletter.md`. New CSS
(`.mc-block--newsletter`/`.mc-block__newsletter-*`) colocated in the
existing `assets/src/css/components/block.css` (single consumer so far).
New test: `tests/mintcart/blocks/newsletter-block-provider-test.php` (4
scenarios), plus a 3rd scenario added to `block-renderer-test.php`
confirming the real `newsletter.twig` resolves. Verified: `php -l` clean
on every touched/added PHP file; full `tests/mintcart/` suite (12 files)
passing; `npm run build` (`build:js` + `build:css`) compiles cleanly,
`mc-block__newsletter-inner` confirmed present in the built
`assets/dist/css/index.min.css`; `npm test` (preset-token contract) still
passing. Same pre-existing gap as every earlier phase: no live
MySQL/`vendor/twig/twig` in this sandbox — a human should insert an
`mc_block` row (`type='newsletter'`) with and without an `action` setting
on a real install and confirm both the announcement-only and
with-form variants render.

**Sub-step 3 (this run): the Spacer block.** `MintCart\Blocks\SpacerBlockProvider`
(`upload/system/library/mintcart/blocks/spacerblockprovider.php`) — the
simplest block type, purely structural: it normalizes one setting (`size`,
one of `sm`/`md`/`lg`/`xl`, default `md`), reads no localized content, and
makes no OpenCart query at all. `ControllerCommonHome::buildMintBlocks()`'s
dispatch (previously a single `if('products')`) is now
`if`/`elseif`/`else` also handling `type='spacer'` via a new
`renderSpacerBlock()` method. `blocks/spacer.twig` (Mint Default) is a
single empty `<div class="mc-block mc-block--spacer mc-block--spacer-{{
size }}" aria-hidden="true">` — no `.container`/`.row` wrapper (nothing to
constrain) and, unlike every other block, `.mc-block`'s own top/bottom
padding is zeroed for this modifier so the size *is* the space, not
padding added on top of it. Full writeup: `docs/developer/blocks/spacer.md`;
upstream diff increment note in `docs/upstream-diff.md`'s existing
`home.php` entry. Verified: `php -l` clean on every touched/added PHP
file; full `tests/mintcart/` suite (12 files, including the new
`tests/mintcart/blocks/spacer-block-provider-test.php` and a 3rd scenario
added to `block-renderer-test.php`) passing; `npm run build` compiles
cleanly, `mc-block--spacer`/`mc-block--spacer-sm|md|lg|xl` present in the
bundled `assets/dist/css/index.min.css`. Same pre-existing gap as every
earlier phase: no MySQL/`vendor/twig/twig` in this environment, so no live
end-to-end request was exercised — a human should insert an `mc_block` row
(`type='spacer'`, `settings_json='{"size":"lg"}'`) on a real install and
confirm the homepage renders it.

**Sub-step 3 (a later run): Text block + `mc-prose` shipped.**
`MintCart\Blocks\TextBlockProvider`
(`upload/system/library/mintcart/blocks/textblockprovider.php`) normalizes
a `type='text'` block's settings (`title`/`content`/`text_align`, no
OpenCart dependency — same shape as `ProductsBlockProvider`).
`ControllerCommonHome::buildMintBlocks()`'s per-block dispatch now handles
`'text'` alongside `'products'`, via a new two-line `renderTextBlock()`
(no product query at all — the simplest block type). New
`mint_default/template/mint/blocks/text.twig` renders via the also-new
`mc-prose` rich-text container
(`assets/src/css/elements/prose.css`, `MINTCART_IMPLEMENTATION_GUIDE.md`
§20 — documented since Phase 3 but built only now that a real consumer
needed it, `CLAUDE.md` §85), wired into `index.css`'s `elements` import
layer. Full writeup: `docs/developer/blocks/text.md`; upstream diff update
in `docs/upstream-diff.md`'s `home.php` entry. Verified: `php -l` clean on
every touched/added PHP file; full `tests/mintcart/` suite (12 files,
including the new `tests/mintcart/blocks/text-block-provider-test.php`,
4 scenarios, and `block-renderer-test.php`'s new scenario 3) passing;
`npm install` (0 vulnerabilities) then `npm run build` — both
`index.min.css` (271.6kb) and the JS bundles compile cleanly, `grep`
confirms `mc-prose` lands in the bundled CSS; `npm test` (the Phase 9
preset-contract guardrail) still passes, unaffected by this change. Same
pre-existing gap as every earlier phase: no MySQL/`vendor/twig/twig` in
this environment, so no live end-to-end request was exercised — a human
should insert an `mc_block` row (`type='text'`) on the `'home'` page and
confirm the homepage renders it with `mc-prose` styling.

**Sub-step 3 (this run): HTML block.** `MintCart\Blocks\HtmlBlockProvider`
(`upload/system/library/mintcart/blocks/htmlblockprovider.php`) — the
simplest Block Provider so far: no structural `settings_json` fields at
all, just a per-language raw `html` string from `content_json`, normalized
to always be a safe string (never `null`/an array). `ControllerCommonHome`
gained `renderHtmlBlock()` and its per-block dispatch now recognizes
`type = 'html'` alongside `'products'`. New template:
`mint_default/template/mint/blocks/html.twig` (renders `html|raw` inside
the shared `mc-block`/`mc-block--html` wrapper — deliberately unescaped,
since block rows are only ever admin-authored; see
`docs/developer/blocks/html.md` "Why `|raw` is safe here"). Full writeup:
`docs/developer/blocks/html.md`; upstream-diff update in
`docs/upstream-diff.md`'s `home.php` entry. Verified: `php -l` clean on
every touched/added PHP file; full `tests/mintcart/` suite (12 files,
including the new `tests/mintcart/blocks/html-block-provider-test.php`
and a 3rd scenario added to `block-renderer-test.php`) passing. No
CSS/JS changes needed (`.mc-block` already covers the wrapper; `--html`
is a scoping modifier only, same as `--products`), so `npm run build` was
not re-run this increment. Same pre-existing gap as every earlier phase:
no MySQL/`vendor/twig/twig` in this environment — a human should insert
an `mc_block` row (`type = 'html'`,
`content_json = '{"html":"<p>Hello</p>"}'`) on a real install and confirm
it renders on the homepage.

**Sub-step 3 (this run): Gallery block.** Follows the exact
Provider/Renderer/Template split Sub-step 2 established, closest in shape
to `BannerGridBlockProvider` (a list of settings/content items matched by
array position, `columns`/`col_width` precomputed for the Bootstrap grid)
but simpler: no `url` per item and no link wrapper, since a Gallery block
is a plain, non-interactive photo grid, not a set of banner links —
`blocks/gallery.twig` renders each item as a `<figure>`/`<figcaption>`,
not Banner Grid's `<a>`. New: `MintCart\Blocks\GalleryBlockProvider`
(`upload/system/library/mintcart/blocks/galleryblockprovider.php`,
`columns` constrained to `{2, 3, 4, 6}`, default 4);
`ControllerCommonHome::renderGalleryBlock()` (new `elseif` branch in
`buildMintBlocks()`'s dispatch, `tool/image` resize with a 600×600
fallback); `template/mint/blocks/gallery.twig`; new
`assets/src/css/components/gallery.css`
(`.mc-gallery__item`/`__image`/`__caption`), imported into
`assets/src/css/index.css`. Full writeup: `docs/developer/blocks/gallery.md`;
upstream diff entry added to `docs/upstream-diff.md`'s existing `home.php`
entry. Verified: `php -l` clean on every touched/added PHP file and the
full `upload/`+`tests/` tree; full `tests/mintcart/` suite (12 files,
including the new `tests/mintcart/blocks/gallery-block-provider-test.php`
and a 3rd scenario added to `block-renderer-test.php`) passing; `npm run
build` (`build:js`+`build:css`) compiles cleanly, confirmed
`mc-gallery__item`/`__image`/`__caption` present in the built
`assets/dist/css/index.min.css`; `npm test` (preset-token contract check)
still passes. Same pre-existing gap as every earlier phase: no live
MySQL/`vendor/twig/twig` in this sandbox, so no live end-to-end request
was exercised — a human should insert an `mc_block` row
(`type = 'gallery'`) pointing at a real image on a real install and
confirm the homepage renders it.

**Next step:** the full first-wave block catalog
(`MINTCART_IMPLEMENTATION_GUIDE.md` §51) is now done and merged to
`master`. Remaining Phase 10 work: wiring each block type into
`ControllerDesignHomepage::TYPES` (settings-form support in the admin
editor — currently only `products` has one), and the Homepage
drag-and-drop editor (admin UI to actually reorder `mc_page`/`mc_block`
rows via `McSortable` — currently a plain `sort_order` number input).

## Phase 11 — Mint Admin primitives

- [x] `McDrawer`, `McModal`, `McToast` — shipped as Phase 4 generic UI
      components (`assets/src/js/components/{drawer,modal,toast}.js`),
      already usable by admin screens (imported into `admin.js`).
- [x] `McConfirm` — `assets/src/css/components/confirm.css` +
      `assets/src/js/components/confirm.js` (`mcConfirm()`), a
      `window.confirm()` replacement returning `Promise<boolean>`.
      Functional Scope §5 groups this with `mc-toast`/`mc-alert` as a P0
      "Toast / Confirm / Alert primitive"; it's the one of the three that
      hadn't shipped yet. Unlike the dialog components above, it has no
      static markup — it builds its own single reusable dialog element on
      first call, since a confirmation's title/message are always specific
      to the calling action. Not imported into `catalog.js`/`admin.js` yet
      — no screen calls it yet (Bulk Editor mass-delete, Phase 16, is the
      likely first consumer); exported for direct import when one exists,
      per `CLAUDE.md` §85. Doc: `docs/developer/components/confirm.md`
- [x] `McSortable` — `mc-sortable`
      (`assets/src/js/components/sortable.js` + `assets/src/css/components/sortable.css`),
      wraps SortableJS 1.15.7 (already an npm dependency since Phase 2) for
      drag reordering, plus `data-mc-action="sortable-move-up"`/
      `"sortable-move-down"` buttons as the required non-mouse alternative
      (`MINTCART_IMPLEMENTATION_GUIDE.md` §68). Fires a bubbling
      `mc:sortable:reorder` CustomEvent (`detail.order`) on either a
      completed drag or a keyboard move, so a future consumer only needs
      one listener. Wired into `admin.js` only (every Functional Scope §6
      drag-and-drop scenario is admin-side). Doc:
      `docs/developer/components/sortable.md`. No live admin-screen
      consumer yet — same "ships ahead of its first consumer" pattern as
      every Phase 4 component (CLAUDE.md §85); most likely first caller is
      Homepage Builder's block list (Phase 10) or Categories
      drag-and-drop (Phase 17).
- [x] `McTabs` — `assets/src/js/components/tabs.js`
      (`initTabsTriggers`) + `assets/src/css/components/tabs.css`. Wraps
      Bootstrap 5's `Tab` (active-pane switching, fade transition, arrow-
      key/Home/End tablist navigation), driven only by
      `data-mc-action="show-tab"` (`data-bs-target` stays as required
      internal Bootstrap wiring, same pattern as `mc-modal`'s
      `.modal-dialog`). Admin-only (`admin.js`, not `catalog.js`) — Phase 11
      lists it as a Mint Admin primitive; entity edit forms (Product:
      General/Data/Links/...) are its first expected consumer. Contract:
      `docs/developer/components/tabs.md`.
- [x] `McProgress` — `assets/src/js/components/progress.js`
      (`initProgressBars`, `setProgress`) +
      `assets/src/css/components/progress.css`. Bootstrap 5's progress bar
      has no JS class to wrap (unlike `Modal`/`Offcanvas`/`Toast`), so this
      retints Bootstrap's own `--bs-progress-*` custom properties instead
      and owns a small imperative API (`setProgress(progress, value,
      options)`) for the one thing static markup can't express: a batch
      job/Update Manager stage updating the same bar's value over time.
      Wired into `admin.js` only (Bulk Editor and Update Manager, its
      expected first consumers, are both admin-only screens — Functional
      Scope §7/§29 "chunked; resumable; progress; failed batch reporting",
      Technical Plan §33). Single source of truth is
      `aria-valuenow`/`aria-valuemin`/`aria-valuemax`, per WAI-ARIA
      `progressbar` conventions; an `--indeterminate` modifier covers
      unknown-duration operations (e.g. "Verifying backup"), with a static
      reduced-motion fallback. Doc:
      `docs/developer/components/progress.md`.
- [x] `McEntityPicker` — `assets/src/js/components/entity-picker.js`
      (`createEntityPicker()`) + `assets/src/css/components/entity-picker.css`.
      Search/pagination/multi-select/keyboard-navigation (WAI-ARIA
      listbox roving-tabindex pattern) against a caller-supplied
      `fetchItems` data source; no dialog chrome of its own (host inside
      an existing `mc-drawer`/`mc-modal`); not wired into any live
      backend or admin screen yet — that's Phase 12's job, once a real
      consumer exists (`CLAUDE.md` §85). Doc:
      `docs/developer/components/entity-picker.md`.
- [x] `McMediaPicker` — `assets/src/js/components/media-picker.js`
      (`createMediaPicker()`) + `assets/src/css/components/media-picker.css`
      (Functional Scope §25, Media UX, priority P1). Sibling of
      `McEntityPicker`: same data-source/no-dialog-chrome/WAI-ARIA-listbox
      shape, but a thumbnail grid instead of a text list, plus an optional
      folder `<select>` (hidden unless `fetchFolders` is supplied) and
      optional upload/safe-delete affordances (hidden/disabled unless the
      host supplies `uploadFiles`/`deleteItems` — never assumes a backend
      exists). "Recent files" and "image metadata" (Functional Scope §25)
      are covered without a dedicated concept: a host-supplied `recent`
      folder entry and the optional `getMeta(item)` callback, respectively.
      **Update (this run): a real backend now exists** —
      `ModelDesignMedia`/`ControllerDesignMedia` (`design/media/list`,
      `/folders`, `/upload`, `/delete`) implement exactly the
      `fetchItems`/`fetchFolders`/`uploadFiles`/`deleteItems` shape this
      component's own usage example was already written against,
      browsing/uploading/deleting inside OpenCart's existing
      `image/catalog/` directory via the new
      `MintCart\Design\MediaPath` path-safety primitive (matching
      `common/filemanager.php`'s own proven rules exactly, that legacy
      screen untouched). No admin screen mounts `mc-media-picker`
      itself yet — still blocked on the same admin asset-enqueue
      question as `McEntityPicker`. Docs:
      `docs/developer/components/media-picker.md`,
      `docs/developer/design/media-library.md`.

**All nine Phase 11 primitives are now done:** `McDrawer`/`McModal`/
`McToast` (via Phase 4), `McConfirm`, `McSortable`, `McTabs`, `McProgress`,
`McEntityPicker` and `McMediaPicker`.

**`McMediaPicker`'s backend, this run:** step 0 dedup check (`gh pr list
--state open`, 44 open PRs at the time, `#52`-`#95`) found no PR touching
Media UX, `design/media`, `ModelDesignMedia`/`ControllerDesignMedia`, or
`MintCart\Design\MediaPath` — and none of the four "hot" contended files
this run identified (`admin/controller/mintcart/wizard.php` +
`wizard.twig` + `wizardprogress.php`, each touched by 3 open PRs — #52,
#64, #84 — and `admin/controller/mintcart/entity.php` +
`column_left.php`, per PR #83's own step-0 writeup) were touched, since
this increment is fully additive new files. `ProductViewProvider`/
`product-card.twig` (contended by PR #83, image `width`/`height`) were
also left untouched. Verified: `php -l` clean across the full
`upload`/`tests` tree; `tests/mintcart/design/media-path-test.php` (7
scenarios, pure) and the new `tests/mintcart/design/media-model-test.php`
(9 scenarios — real filesystem I/O against a temp fixture tree, including
an actual file delete and three traversal-safety rejections) both pass;
full `tests/mintcart/**/*-test.php` suite re-run end to end (35 files, was
33) — all passing, no regressions. Along the way, found and fixed a
same-prefix-sibling false positive in the path-containment check this
class extracts from `common/filemanager.php` (that legacy file's own
`substr()`-prefix comparison would treat `image/catalog-evil/` as
"within" `image/catalog/`) and a route-syntax bug in this component's own
prior-run usage example (`design/media.list`'s dot form is silently
mangled by OpenCart 3's `Action::__construct()`, never reaching the
intended controller/method) — both documented in
`docs/developer/design/media-library.md`. **Not verified:** no live
MySQL/OpenCart admin session in this environment — a human should confirm
a real multipart upload via `design/media/upload` (the one code path
`move_uploaded_file()` prevents from being exercised under the CLI SAPI)
and the `hasPermission()` gate on all four routes against a real admin
session.

`McConfirm` verified: `npm install` (0 vulnerabilities), `npm run build`
(minified) and `npm run dev` (sourcemapped) both compile cleanly;
`grep -o` against the built `assets/dist/css/index.min.css` confirms
`.mc-confirm`/`.mc-confirm__title`/`.mc-confirm__message`/
`.mc-confirm__actions` are present; confirmed `mcConfirm`/`confirm-accept`
do **not** appear in either `catalog.min.js` or `admin.min.js` (correctly
tree-shaken — nothing imports the module yet, as intended). No PHP/Twig
files touched, so the PHP `tests/mintcart/` suite and the `.twig`
tag-balance check don't apply. No automated component/visual test harness
exists yet in this repo (same gap noted in every previous Phase 4/11 run).

`McSortable` verified: `npm install` (0 vulnerabilities), `npm run build`
(minified) clean; `grep` against the built `admin.min.js` confirms
`sortable-move-up`/`mc:sortable:reorder` are present, and confirms they
are **absent** from `catalog.min.js` (admin-only, as designed); `grep`
against the built `index.min.css` confirms `.mc-sortable`/
`.mc-sortable__item`/`.mc-sortable__handle`/`.mc-sortable__label`/
`.mc-sortable__controls`/`.mc-sortable__move` are all present. No PHP
touched, so the PHP `tests/mintcart/` suite doesn't apply.

`McTabs` verified: `npm install` (0 vulnerabilities), `npm run build`
(minified) clean; `grep` against the built `admin.min.js` confirms
`show-tab`/`data-mc-action` wiring is present. No PHP touched, so the
PHP `tests/mintcart/` suite doesn't apply.

`McProgress` verified: `npm install` (0 vulnerabilities), `npm run build`
(minified) clean; `grep` against the built `admin.min.js` confirms
`initProgressBars`/`setProgress` are present; `grep` against the built
`index.min.css` confirms `.mc-progress`/`--indeterminate` are present. No
PHP touched, so the PHP `tests/mintcart/` suite doesn't apply.

**Autosave (`MINTCART_FUNCTIONAL_SCOPE.md` §22, P1/RESEARCH) — research
done this run, no code.** This item wasn't tracked anywhere in this file
before now and nothing among the 40+ PRs open at the time of this run
touches it. `docs/adr/006-autosave-strategy.md` resolves the six open
questions (scope/allowlist, trigger/transport, state machine, failure
backoff, conflict handling via a version token, navigation warning) per
`CLAUDE.md` §20's "determine before implementing." No `McAutosave`
primitive is built yet — every plausible first consumer (Homepage
Builder block settings, a future Theme settings form) sits behind the
same admin-bundle-enqueue blocker already tracked in this file (Phase 7's
"Known gap," Phases 10/12/17), and building the primitive with no
consumer to prove it against would be speculative (`CLAUDE.md` §85).
**Next step:** build `McAutosave` (`assets/src/js/components/autosave.js`,
`createAutosave(form, options)` factory, same shape as `McEntityPicker`/
`McMediaPicker`) once a real consumer exists and the admin bundle enqueue
question is resolved — see the ADR's "Consequences" section.

## Phase 12 — Entity Picker

- [x] List primitive shipped (Phase 11 — `McEntityPicker`, see above):
      search, pagination, images, multi-select, keyboard nav, selected
      state, all against a caller-supplied data source.
- [~] Filters (beyond search text) — `McEntityPicker` now supports a
      generic `[data-mc-entity-picker-filter="<key>"]` hook
      (`entity-picker.js`) and the Product backend
      (`ControllerMintcartEntity::product()`) accepts a real
      `filter_status` param; no template renders a filter element yet
      (see Product backend's own "Filters" update below) — Category/
      Manufacturer have no requirement/backend support identified yet.
- [x] Real `fetchItems` backends per entity type (products, categories,
      manufacturers, attributes, options, information pages, customers) —
      **category and manufacturer are done** (manufacturer this run), see
      below; the other five entity types still need their own.
- [~] Live wiring into an actual admin screen/field — **category,
      manufacturer, product and attribute are now done** (`product_form.twig`'s
      Category and Manufacturer fields in earlier runs, its Related
      Products field and now its Attribute tab — see the "Attribute field
      live-wired" update below); information/option/customer backends
      still have no live consumer (option is in progress in an open PR at
      the time of this note — re-check before picking it up).
- [ ] Create-new flow via drawer/modal — not attempted.

**Update (this run): the category `fetchItems` backend is done.**
`upload/admin/controller/mintcart/entity.php`
(`ControllerMintcartEntity::category()`, route `mintcart/entity/category`,
fully additive — zero existing OpenCart file modified) wraps the existing
`ModelCatalogCategory::getCategories()` call and normalizes its rows via
`MintCart\Design\CategoryPickerProvider`
(`upload/system/library/mintcart/design/categorypickerprovider.php`, no
OpenCart dependency, unit-tested) into the `{items: [{id, name, image}],
total}` shape `McEntityPicker`'s `fetchItems` contract expects. Full
rationale — why a new route instead of reshaping
`catalog/category/autocomplete` (which has five other admin-template
consumers), why paging happens in PHP instead of adding a filtered
total-count method to `ModelCatalogCategory`, and the ACL permission a
human must grant before this endpoint answers anything — is in
`docs/developer/components/entity-picker.md` "Admin backends".

**Live wiring deliberately not attempted this run.** The identified first
consumer is `product_form.twig`'s Category multi-select (search +
multi-select + removable tags — already the right UI shape for
`McEntityPicker`, so it's closer to a drop-in replacement than a new
pattern). But wiring it needs MintCart's own compiled bundle
(`assets/dist/{css,js}`) loaded on an admin page, and nothing enqueues it
there yet — the one prior attempt to enqueue the shared `index.min.css` on
a storefront page was reverted after it broke legacy Bootstrap 3 widgets
(Phase 7's "Known gap"). Admin pages are equally Bootstrap-3-based, so the
same conflict risk applies, and this environment still has no
MySQL/`vendor/twig/twig` to verify a fix visually. Shipping unverifiable,
possibly-broken markup on a real admin screen would be worse than not
wiring it yet.

Verified: `php -l` on both new PHP files (clean); new
`tests/mintcart/design/category-picker-provider-test.php` (6 scenarios —
empty set, single page, mid-list paging, last partial page, HTML-entity
decoding, page/limit clamping), passing; full `tests/mintcart/` suite
re-run end to end (12 files now, was 11) — all pass, no regressions (this
increment added one new pure class and one new additive controller,
touching nothing existing). No live end-to-end request was possible (same
pre-existing no-MySQL/no-`vendor/twig/twig` gap noted throughout this
file) — see the new Phase 12 section in `docs/MANUAL_VERIFICATION.md` for
what a human should confirm (grant the `mintcart/entity` permission, then
hit the endpoint directly).

**Update (a later run): the manufacturer `fetchItems` backend is done
too**, same shape as category —
`ControllerMintcartEntity::manufacturer()` (route
`mintcart/entity/manufacturer`) wraps `ModelCatalogManufacturer::getManufacturers()`
and normalizes rows via the new
`MintCart\Design\ManufacturerPickerProvider`
(`upload/system/library/mintcart/design/manufacturerpickerprovider.php`,
no OpenCart dependency, unit-tested — `tests/mintcart/design/manufacturer-picker-provider-test.php`,
7 scenarios). Two differences worth knowing, both documented in
`docs/developer/components/entity-picker.md`'s new "Manufacturer backend"
section: `image` is a real `model_tool_image`-resolved thumbnail URL
(not always `null` like category's, since `oc_manufacturer` has an image
column category's query doesn't) — this is the first entity backend to
actually exercise `McEntityPicker`'s thumbnail rendering; and
`filter_name` prefix-matches (`LIKE '…%'`) rather than category's
substring match (`LIKE '%…%'`), inherited unchanged from each model's
existing behavior. Live wiring deliberately not attempted, same reason as
category (MintCart's asset bundle still isn't enqueued on any admin
page) — identified first consumer is `product_form.twig`'s Manufacturer
autocomplete field. Verified: `php -l` clean on both touched/added PHP
files; full `tests/mintcart/` suite re-run end to end (15 files now, was
14) — all pass, no regressions (one new pure class, one new action on an
already-additive controller, touching nothing existing). Same
no-MySQL/no-`vendor/twig/twig` gap as every prior phase — see
`docs/MANUAL_VERIFICATION.md`'s new Phase 12 entry for what a human
should confirm, image resolution included.

**Update (a later run): the product `fetchItems` backend is done too**, and is
the first of the three to page at the SQL level rather than in PHP.
`ControllerMintcartEntity::product()` (route `mintcart/entity/product`)
wraps `ModelCatalogProduct::getProducts()`/`getTotalProducts()` (both
already accept `start`/`limit`/`filter_name`, and — unlike
`ModelCatalogCategory`/`ModelCatalogManufacturer` — `getTotalProducts($data)`
returns a real *filtered* count, not just an unfiltered store-wide total)
and normalizes the already-paged rows via the new
`MintCart\Design\ProductPickerProvider`
(`upload/system/library/mintcart/design/productpickerprovider.php`, no
OpenCart dependency, unit-tested — `tests/mintcart/design/product-picker-provider-test.php`,
5 scenarios). Its `build(array $rows, int $total)` signature deliberately
differs from `CategoryPickerProvider`/`ManufacturerPickerProvider`'s
`build(array $rows, int $page, int $limit)` — `$rows` is already the
current page and `$total` is the real filtered count, not `count($rows)`.
Image resolution follows the manufacturer backend's pattern
(`model_tool_image->resize()`, `null` when the product has no image file).
Full rationale — why paging in SQL here specifically, why a new route
instead of reshaping `catalog/product.php`'s own busier `autocomplete()`
(ten existing admin-template consumers), the identified first live
consumer (`product_form.twig`'s Related Products field) — is in
`docs/developer/components/entity-picker.md`'s new "Product backend"
section. Live wiring deliberately not attempted, same reason as
category/manufacturer (MintCart's asset bundle still isn't enqueued on
any admin page). Verified: `php -l` clean across the full `upload`/`tests`
tree; full `tests/mintcart/` suite re-run end to end (now 33 files,
including the new product-picker-provider test) — all pass, no
regressions (one new pure class, one new action on an already-additive
controller, touching nothing existing). Same no-MySQL/no-`vendor/twig/twig`
gap as every prior phase — see `docs/MANUAL_VERIFICATION.md`'s new Phase 12
entry, including the SQL-pagination-specific check (page 2 must return the
*next* page, not repeat or skip rows) that category/manufacturer's
PHP-side paging never had to verify against a live database.

**Update (a later run): the information page `fetchItems` backend is done
too**, same shape as category/manufacturer —
`ControllerMintcartEntity::information()` (route
`mintcart/entity/information`) wraps
`ModelCatalogInformation::getInformations()` and normalizes rows via the new
`MintCart\Design\InformationPickerProvider`
(`upload/system/library/mintcart/design/informationpickerprovider.php`, no
OpenCart dependency, unit-tested — `tests/mintcart/design/information-picker-provider-test.php`,
7 scenarios). One real difference from the other two, documented in
`docs/developer/components/entity-picker.md`'s new "Information page
backend" section: `ModelCatalogInformation::getInformations()` has no
`filter_name` support at all (unlike `getCategories()`/`getManufacturers()`,
which at least filter in SQL), so `InformationPickerProvider` does the name
filtering itself, in PHP, on top of the PHP-side paging category and
manufacturer already established — case-insensitive substring match. `image`
is always `null` (information pages have no image concept). Live wiring
deliberately not attempted, same reason as category/manufacturer (MintCart's
asset bundle still isn't enqueued on any admin page); unlike the other two,
this backend has no single obvious first consumer yet (see the doc section
for why) rather than one specific field to swap out. Verified: `php -l`
clean on both touched/added PHP files, and re-swept across the full
`upload/`/`tests/` tree; full `tests/mintcart/` suite re-run end to end (34
files now, was 33) — all pass, no regressions (one new pure class, one new
action on an already-additive controller, touching nothing existing). Same
no-MySQL/no-`vendor/twig/twig` gap as every prior phase — see
`docs/MANUAL_VERIFICATION.md`'s new Phase 12 entry for what a human should
confirm, including the filter-then-page ordering that has no unit-test
equivalent against a live cache.

**Update (a later run): the attribute `fetchItems` backend is done too**,
same shape as category —
`ControllerMintcartEntity::attribute()` (route `mintcart/entity/attribute`)
wraps `ModelCatalogAttribute::getAttributes()` and normalizes rows via the
new `MintCart\Design\AttributePickerProvider`
(`upload/system/library/mintcart/design/attributepickerprovider.php`, no
OpenCart dependency, unit-tested —
`tests/mintcart/design/attribute-picker-provider-test.php`, 7 scenarios).
`image` is always `null` (no image concept, same as category); paging
happens in PHP over the full filtered set, same reason as category
(`ModelCatalogAttribute` has no filtered-by-name total-count method);
`filter_name` prefix-matches (`LIKE '…%'`), same as manufacturer, not
category's substring match — all documented in
`docs/developer/components/entity-picker.md`'s new "Attribute backend"
section, including why (unlike category/manufacturer) there's no existing
autocomplete consumer this route replaces — it's a genuinely new
capability. Live wiring deliberately not attempted, same reason as
category/manufacturer (admin asset-enqueue problem, see above); unlike
those two, no obvious first live consumer has been identified yet for
this one (documented rather than guessed at). Step 0 dedup check at the
start of this run found three other open PRs: **#52** (Wizard Contacts
step, Phase 13 — different phase, no file overlap), **#53** (Phase 12
product backend, adds `ControllerMintcartEntity::product()` — a separate
method, no shared lines with this run's `attribute()`), and **#54**
(Phase 12 information-page backend, adds `::information()` — same,
separate method). None touch the Homepage Builder block-type registry,
`homepage_list.twig`, or `block.css` (the historical hot-file collision
point per this routine's own root-cause note), and neither does this run,
so that area isn't contended. Verified: `php -l` clean on every
touched/added PHP file, re-swept across the full `upload/`/`tests/` tree;
full `tests/mintcart/**/*-test.php` suite re-run end to end (35 files now,
was 34) — all pass, no regressions. No `.twig` files touched. No live
end-to-end request possible (same pre-existing no-MySQL/no-`vendor/twig/twig`
gap) — see the new Phase 12 entry in `docs/MANUAL_VERIFICATION.md`.

**Next step:** either wire the category, manufacturer, product,
information or attribute backend into a live admin screen (all blocked on
solving the admin asset-enqueue problem first — see above; Phase 13's
wizard found one way around it worth reading before attempting this: a
standalone page with no legacy chrome, see that phase's "Design decision
worth flagging"), or pick another entity type (option, customer) and
build its backend the same shape as these five.

**Update (a later run): the option `fetchItems` backend is done**, same shape
as category/manufacturer — `ControllerMintcartEntity::option()` (route
`mintcart/entity/option`) wraps `ModelCatalogOption::getOptions()` and
normalizes rows via the new `MintCart\Design\OptionPickerProvider`
(`upload/system/library/mintcart/design/optionpickerprovider.php`, no
OpenCart dependency, unit-tested — `tests/mintcart/design/option-picker-provider-test.php`,
6 scenarios). `image` is always `null` (options have no image of their
own); `filter_name` prefix-matches, same as manufacturer. Full rationale
in `docs/developer/components/entity-picker.md`'s new "Option backend"
section. Live wiring deliberately not attempted, same reason as
category/manufacturer (MintCart's asset bundle still isn't enqueued on
any admin page) — identified first consumer is `product_form.twig`'s
Option tab (`#input-option`, currently a jQuery UI autocomplete against
`catalog/option/autocomplete`). Verified: `php -l` clean across the full
`upload`/`tests` tree; full `tests/mintcart/` suite re-run end to end (34
files now) — all pass, no regressions (one new pure class, one new action
on an already-additive controller, touching nothing existing).

**Next step:** either wire the category, manufacturer, product,
information, attribute or option backend into a live admin screen (all
blocked on solving the admin asset-enqueue problem first — see above;
Phase 13's wizard found one way around it worth reading before attempting
this: a standalone page with no legacy chrome, see that phase's "Design
decision worth flagging"), or build the customer entity-picker backend,
the last remaining entity type.

**Update (a later run): the customer `fetchItems` backend is done too.**
`ControllerMintcartEntity::customer()` (route `mintcart/entity/customer`)
wraps `ModelCustomerCustomer::getCustomers()`/`getTotalCustomers()` and
normalizes rows via the new `MintCart\Design\CustomerPickerProvider`
(`upload/system/library/mintcart/design/customerpickerprovider.php`, no
OpenCart dependency, unit-tested — `tests/mintcart/design/customer-picker-provider-test.php`,
5 scenarios). Unlike category/manufacturer, this one pages at the SQL
level (`ModelCustomerCustomer::getTotalCustomers()` accepts `filter_name`
and returns a real filtered count, unlike the category/manufacturer
models) — same design `ProductPickerProvider` uses on its own open PR
(#53), for the same reason (customer tables can be large). Full rationale
in `docs/developer/components/entity-picker.md`'s new "Customer backend"
section. Live wiring deliberately not attempted, same reason as every
other backend (admin asset-enqueue problem unsolved) — no single obvious
first consumer identified for this one (documented, not guessed at).
Verified: `php -l` clean on both touched/added PHP files; full
`tests/mintcart/**/*-test.php` suite re-run end to end (34 files, up from
33 on `master`) — all pass, no regressions. Same no-MySQL/no-live-request
gap as every prior phase — see `docs/MANUAL_VERIFICATION.md`'s new Phase
12 entry (SQL-paging-specific checks included, same shape as the product
backend's own entry on its open PR).

**Next step:** all seven entity-picker backends now exist (category,
manufacturer, product, information page, attribute, option, customer).
The remaining Phase 12 work is either wiring one into a live admin screen
(blocked on the admin asset-enqueue problem — see above) or
Filters/create-new-flow (neither has a real requirement yet).

**Update (this run): the admin asset-enqueue problem is solved, and
category is wired live.** New `assets/dist/css/components.min.css`
(`index.css` minus the Bootstrap 5 import — safe alongside the admin
theme's existing Bootstrap 3, unlike `index.min.css`) +
`window.MintCart.createEntityPicker` (admin.js bridge for legacy
non-module inline `<script>` templates) +
`ControllerCatalogProduct::enqueueMintAssets()` (`$this->document->
addStyle()`/`addScript()`, OpenCart's own per-page asset mechanism — no
template change needed for `header.twig` itself). `product_form.twig`'s
Category field now mounts a real `McEntityPicker`, additive alongside the
existing jQuery autocomplete. Full writeup:
`docs/developer/admin/asset-enqueue.md` (new),
`docs/developer/components/entity-picker.md`'s updated "Admin backends"
section, `docs/upstream-diff.md`'s new `product.php`/`product_form.twig`
entry, `docs/MANUAL_VERIFICATION.md`'s new entry. This unblocks the
manufacturer/product backends' own live wiring, Phase 17's `McSortable`
category-reorder, and Phase 11's Homepage Builder JSON-textarea-to-
real-repeater follow-up — all can now reuse the same `components.min.css`
+ `admin.min.js` enqueue pattern (call `$this->document->addStyle()`/
`addScript()` with `HTTP_CATALOG . 'assets/dist/...'` on the specific
action that needs it) rather than re-solving this.

**Update (2026-09-15, routine run): Manufacturer field live-wired,
same shape as Category.** Step 0 dedup check: 5 open PRs at the start of
this run (#110/#111 — Phase 10 Diagnostics, storefront controllers only;
#112 — Phase 16 bulk `manufacturer_id` field, touches `product_list.twig`/
`ModelCatalogProduct::updateProductField()`, not `product_form.twig`;
#113 — `RobotsBuilder` into `header.php`; #114 — `MetaBuilder` into
`category.php`/`home.php`) — none touch `product_form.twig`,
`ControllerCatalogProduct::getForm()`, or
`upload/admin/language/en-gb/catalog/product.php`'s Category/Manufacturer
picker language block (#112 only adds one `text_bulk_field_manufacturer`
key further down the same file, in the separate Bulk Editor block — no
line/content overlap). Also investigated Phase 17's category
drag-and-drop live-UI wiring first (the other "next candidate" this same
asset-enqueue fix unblocked) and found a real, undocumented-until-now
architectural mismatch: `admin/view/template/catalog/category_list.twig`
is a flat, paginated table spanning every parent (sorted by name or
`sort_order` across the *entire* category tree, inherited unmodified from
stock OpenCart), while `ModelCatalogCategory::moveCategory()`'s contract
is sibling-scoped (`order[]` must be "the full new sibling order under
`parent_id`") — wiring real drag reorder onto that list would either
silently corrupt `sort_order` for siblings not on the current page, or
require guessing a UI redesign (parent-scoped drill-down/tree view) that
`CLAUDE.md` §20 reserves for a human decision. Documented as a real fork,
not attempted; picked the unambiguous Manufacturer-field mechanical
increment instead — see the fork write-up below.

`product_form.twig`'s Manufacturer field (`#input-manufacturer`/
`input[name="manufacturer_id"]`) gains the same "Browse manufacturers"
button + `.mc-entity-picker` panel pattern the Category field already
has, against the already-shipped `mintcart/entity/manufacturer` backend —
**single-select** (`multiple: false`, a product has exactly one
`manufacturer_id`), so confirming a selection overwrites the two existing
fields directly instead of appending into a well. No controller change
needed (`getForm()`'s existing `enqueueMintAssets()` already covers this
whole form). Six new `text_manufacturer_picker_*` language keys, same
file/pattern as Category's own six. Full writeup:
`docs/developer/components/entity-picker.md`'s "Manufacturer backend"
section, `docs/upstream-diff.md`'s new entry appended to the
`product.php`/`product_form.twig` block, `docs/MANUAL_VERIFICATION.md`'s
new entry.

**Verified:** `php -l` clean on the language file (no PHP library code
touched — this is Twig/JS/language-file only, reusing the already-tested
`ManufacturerPickerProvider`/`ControllerMintcartEntity::manufacturer()`
backend as-is). Twig `{% if %}`/`{% endif %}`/`{% for %}`/`{% endfor %}`
tag counts re-checked balanced (38/38, 37/37, unchanged counts since this
only added matching pairs). New inline `<script>` block extracted and
checked with `node --check` (valid syntax). Full `tests/mintcart/`
suite (77 files) re-run end to end — all passing, no regressions (no
library code changed). **Not verified:** no live MySQL/admin session in
this environment — see `docs/MANUAL_VERIFICATION.md`'s new entry for the
full manual script.

**Update (2026-09-18, routine run): Related Products field live-wired,
closing out the product backend's own "identified first consumer" note.**
Step 0 dedup check: `list_pull_requests` (all open, not filtered by
branch name) returned three open PRs — `#127` (`PackageExtractor`, Update
Manager archive extraction), `#128` (`BackupExecutor`, Update Manager
backup execution) and `#129` (admin toggle for `mintcart_dev_mode`,
System → Diagnostics). Verified via each PR's own file list: none touch
`product_form.twig`, `ControllerCatalogProduct`, or
`upload/admin/language/en-gb/catalog/product.php` — `#127`/`#128` are
scoped entirely to `upload/system/library/mintcart/update/**` + their own
docs/tests, and `#129` is scoped to a new
`upload/admin/controller/mintcart/settings/diagnostics.php` +
`column_left.php`. All three do touch `docs/IMPLEMENTATION_STATUS.md`,
but different sections (Phase 11 (Technical Plan) / Phase 10 (Technical
Plan) vs. this Phase 12 section) — a normal sibling-PR merge point, not a
collision. No hot shared files (§0f — the Homepage Builder block-type
registry, homepage block list/template, `block.css`) are touched by any
of the three or by this run.

`product_form.twig`'s Related Products field (`#input-related`/
`product_related[]`) gains the same "Browse products" button +
`.mc-entity-picker` panel pattern the Category field already established
— **multi-select** (`multiple: true`, same as Category, since a product
can have many related products), against the already-shipped,
previously-consumer-less `mintcart/entity/product` backend. Confirming a
selection appends into the exact same `#product-related{id}` well markup
(minus-circle icon + name + a `product_related[]` hidden input) the
existing jQuery UI `autocomplete()`'s own `'select'` handler already
produces, so `ControllerCatalogProduct::validateForm()`/`addProduct()`/
`editProduct()`'s handling of `product_related[]` needs no change at
all. No controller change was needed — this screen's
`enqueueMintAssets()` already covers the whole form (added for Category).
Six new `text_related_picker_*` language keys, same file/pattern as
Category's/Manufacturer's own six. Full writeup:
`docs/developer/components/entity-picker.md`'s "Product backend" section,
`docs/upstream-diff.md`'s new entry appended to the
`product.php`/`product_form.twig` block, `docs/MANUAL_VERIFICATION.md`'s
new entry.

**Verified:** `php -l` clean across the full `upload`/`tests` tree
(language file is the only PHP touched — no `upload/system/library/mintcart/*`
class changed, reusing the already-tested `ProductPickerProvider`/
`ControllerMintcartEntity::product()` backend as-is). Twig
`{% if %}`/`{% endif %}`/`{% for %}`/`{% endfor %}` tag counts re-checked
balanced (38/38, 37/37, unchanged — this only added a comment block and
non-conditional markup). New inline `<script>` block extracted and
checked with `node --check` (valid syntax). Full `tests/mintcart/**/*-test.php`
suite (81 files) re-run end to end — all passing, no regressions (no
library code changed). **Not verified:** no live MySQL/admin session in
this environment — see `docs/MANUAL_VERIFICATION.md`'s new entry for the
full manual script.

**Architectural fork identified, not resolved (Phase 17):** live
drag-and-drop wiring for `catalog/category` needs a product decision on
how the flat, cross-parent, paginated category list should represent
sibling-scoped reordering — options include (a) a parent-scoped
drill-down/tree view (bigger, real UX change), (b) restricting drag to
same-parent adjacent rows with reject-and-snap-back otherwise (partial,
potentially confusing), or (c) a "Manage order for: [parent selector]"
filter added to the existing list screen (smaller than a full tree, still
a new list-filtering feature). Not guessed at this run per `CLAUDE.md`
§20 — a future run or a human should decide before attempting Phase 17's
live UI wiring; the backend (`ControllerCatalogCategory::move()`,
`CategoryMoveValidator`, `moveCategory()`) is unaffected and remains
ready to consume whichever UI shape is chosen.

**Resolved (later routine run) — not by picking (a)/(b)/(c) above.** A
fourth, purely mechanical option none of these considered: the *save*
step splits the new visible order into per-parent groups instead of
restricting what the admin can drag. See the Phase 17 section below
("Update (routine run): live UI wiring is done...") and
`docs/developer/catalog/category-drag-drop.md`'s "Resolves the
Architectural fork..." section for the full reasoning.

**Next candidates:** the remaining Phase 12 entity types still need live
wiring (information/attribute/option/customer — none has an identified
first consumer yet, per their own backend write-ups above, so picking one
means choosing a real admin screen/field first, not guessing one); full
drag-to-re-parent for categories (a real tree/drill-down view — still
unattempted, unlike the reordering fork resolved above); Entity Picker
Filters/Create-new-flow (neither has a real requirement yet); or re-check
`gh pr list --state open` first, since this backlog moves fast.

**Update (2026-09-29, routine run): Attribute field live-wired.** Step 0
found 14 open PRs (`#138`-`#151`, all `ROUTINE-STATUS: DONE` except `#148`
`IN-PROGRESS` — "wire Entity Picker Option backend into
`product_form.twig`"). None of them touch the Attribute tab of
`product_form.twig` (`#148` is scoped to the separate Option tab further
down the same file — different tab, non-overlapping lines); none of the
Homepage Builder hot shared files (§0f — block-type registry, `block.css`)
are touched by any open PR. `product_form.twig`'s Attribute tab
(`#attribute`) is `AttributePickerProvider`/`ControllerMintcartEntity::attribute()`'s
first live consumer — exactly the "new UI affordance" the "Attribute
backend" section of `docs/developer/components/entity-picker.md` predicted
(unlike category/manufacturer/related, there was no existing single-select
attribute autocomplete field to swap out, since attribute rows are added
dynamically via `addAttribute()`). One shared `#attribute-picker`
`McEntityPicker` instance (single-select) is reused across every
dynamically-added row via event delegation on the `#attribute` table: each
row's new "Browse" button carries `data-mc-attribute-row="N"`; clicking it
records the active row, and `onConfirm` writes into that row's new
`[data-mc-attribute-name]`/`[data-mc-attribute-id]` hooks — the same
fields `attributeautocomplete()`'s own jQuery UI `select` handler already
writes to (kept as-is, not replaced, same reasoning as category/
manufacturer/related), so `ControllerCatalogProduct`'s save logic needs no
change. New language strings in
`upload/admin/language/en-gb/catalog/product.php`
(`text_attribute_picker_*`, same shape as the manufacturer/related picker
strings). Full rationale in `docs/developer/components/entity-picker.md`'s
"Attribute backend" section (now updated from "not attempted" to "done").
**Verified:** `php -l` clean across the full `upload`/`tests` tree (only
the language file is PHP — no library class changed, reusing the
already-tested `AttributePickerProvider`/`ControllerMintcartEntity::attribute()`
backend as-is); Twig `{% if %}`/`{% endif %}`/`{% for %}`/`{% endfor %}`
tag counts unchanged and balanced (38/38, 37/37 — no new conditional/loop
tags added, only markup/comments/`{{ }}` output and new inline
`<script>` statements); the two modified inline `<script>` blocks (and
all 8 other, untouched ones) extracted and checked with `node --check`
(all pass); full `tests/mintcart/**/*-test.php` suite (84 files) re-run
end to end — all passing, no regressions. **Not verified:** no live
MySQL/admin session in this environment to click through the actual
picker UI — see `docs/MANUAL_VERIFICATION.md` for the standing manual
script this falls under. **Next candidates:** the remaining entity types
needing live wiring — information and customer (option is claimed by open
PR `#148` at the time of this note — re-check `gh pr list --state open`
before touching its tab); Entity Picker Filters/Create-new-flow (neither
has a real requirement yet); resolving the Phase 17 category-list fork
above (needs a product decision first); or re-check `gh pr list --state
open` first, since this backlog moves fast.

## Phase 13 — Setup Wizard

- [~] Welcome → Store → Contacts → Language/Currency → Delivery → Payment → Design →
      First Product → Ready — **Welcome, Store and Ready implemented** (in
      that resume order — `ready` is last in the canonical step list but
      third in `IMPLEMENTED_STEPS`, so it comes right after Store, ahead of
      the still-unimplemented steps between them); the remaining six steps
      are listed in the step nav as a roadmap but have no real content yet
      (see below)
- [x] Progress saving, resume, writes to standard OpenCart settings storage —
      done for all three implemented steps; "skip optional steps" doesn't
      apply yet (none of the three implemented steps is optional)
      First Product → Ready — **Welcome, Store, Contacts and Language &
      currency implemented**; the remaining steps (`delivery`, `payment`,
      `design`, `first_product`, `ready`) are listed in the step nav as a
      roadmap but have no real content yet (see below)
- [x] Progress saving, resume, writes to standard OpenCart settings storage —
      done for all four implemented steps; "skip optional steps" doesn't
      apply to step *sequencing* yet (no implemented step can be skipped
      ahead of), but Contacts' own fields are each individually optional
      (see below)

**In progress.** `MintCart\Onboarding\WizardProgress` (pure step-sequencing
logic, no OpenCart dependency — `upload/system/library/mintcart/onboarding/wizardprogress.php`,
unit-tested standalone, `tests/mintcart/onboarding/wizard-progress-test.php`,
9 scenarios) + `ControllerMintcartWizard` (route `mintcart/wizard`, fully
additive, zero existing OpenCart file modified) + a standalone
`mintcart/wizard.twig` page (no legacy admin chrome — see below) implement
the **Welcome**, **Store** and **Ready** steps end to end: Welcome is purely
informational with a "Get started" button; Store collects
the **Welcome** and **Store** steps end to end (Contacts is written up
separately below): Welcome is purely informational with a "Get started"
button; Store collects
`config_name`/`config_email`/`config_telephone`/`config_address` (existing
OpenCart `config` settings, merged in rather than overwritten — see the
`editSetting()` partial-write trap below) with the same validation rules
`Settings → Store` already uses; Ready renders Phase 14's
`MintCart\Onboarding\StoreReadiness` checklist (same `evaluate()` call and
`mintcart/readiness` signal/label source `ControllerMintcartReadiness`'s
JSON endpoint already uses — one computation, two callers, not a second
copy) via the already-shipped `mc-progress`/`mc-badge` primitives, with a
"Finish setup" submit that has no fields of its own to validate/save.
Progress (`mintcart_wizard_completed_steps`) is stored via the standard
`oc_setting` table under a `mintcart_wizard` code — a Mint-specific
concept, but a handful of flags, not a new entity, so no new `mc_*`
table/migration was added for it (`CLAUDE.md` §17). Step-order is enforced
server-side in both directions (GET `?step=` and POST `step`): a request
can never view or submit a step ahead of the live resume point, though
revisiting an already-completed step is allowed.

**Design decision worth flagging:** `wizard.twig` is a complete, standalone
HTML document — it does not include `common/header`/`common/column_left`/
`common/footer`, so it never shares a page with the legacy Bootstrap 3
chrome that broke when a plain `assets/dist/css/index.min.css` `<link>` was
tried on a chrome-sharing page before (Phase 15's "human session" update,
below). This makes it **the first live page in MintCart to actually enqueue
`assets/dist/css/index.min.css`** — CSS only, no `<script>` tag, since
neither implemented step needs client-side JS. Full rationale, the
`editSetting()` partial-write trap this caught, and the no-`hasPermission()`-
gate reasoning are all in `docs/developer/onboarding/setup-wizard.md`.

Verified: `php -l` clean on every touched/new PHP file; full `tests/mintcart/`
suite re-run end to end (34 files) — all pass; the updated `.twig` file's
`if`/`for`/`endif`/`endfor` tags checked balanced (same script-based
technique used for every earlier `.twig` file in this repo, `if: 20
endif: 20`, `for: 2 endfor: 2`). Same pre-existing gap as every other
controller-wiring increment: no live request test (no MySQL/OpenCart
bootstrap in this environment) — a human should confirm `mintcart/wizard`
actually renders and saves on a real install, in particular that the
`editSetting('config', $merged)` call really does preserve every other
existing `config_*` setting rather than just the four fields the Store
step writes, and that the Ready step's checklist/percentage match what
`mintcart/readiness`'s own JSON endpoint reports for the same store.

**Update (a later run): the Contacts step is done.** `WizardProgress::IMPLEMENTED_STEPS`
now includes `contacts`. `ControllerMintcartWizard::validateContactsStep()`/
`saveContactsStep()` collect up to four optional social profile URLs
(Facebook/Instagram/Twitter/YouTube — each individually optional, a blank
store-with-no-socials-yet is a completely valid finished state; a filled
field must be `filter_var(..., FILTER_VALIDATE_URL)`-valid). Saved under a
dedicated `mintcart_contacts` setting code (`mintcart_contacts_social`,
JSON-serialized `{network, href}` list) rather than merged into `config` —
social links have no standard OpenCart storage to reuse, and unlike
`config` this code has exactly one key MintCart itself owns end to end, so
there's no `editSetting()` partial-write trap to guard against here.

Written in the exact shape `MintCart\Design\FooterViewProvider::build()`'s
`$social` parameter already expects (Phase 7) — so
`ControllerCommonFooter::index()` (this run, second file touched) now reads
`mintcart_contacts` and passes real social links into the live storefront
footer, replacing the hardcoded empty array Phase 7 shipped with. This
closes Phase 7's "no storage yet" gap for `$social` specifically —
`$payments`/`$newsletter` remain unimplemented, unrelated to this change
(no Business Settings/Commerce API consumer for either yet). Full writeup:
`docs/developer/onboarding/setup-wizard.md`'s new "Contacts step" section,
`docs/developer/components/footer.md`'s updated "Social links" section,
`docs/upstream-diff.md`'s `footer.php` entry.

Verified: `php -l` clean on every touched PHP file; full `tests/mintcart/`
suite re-run end to end — all pass, no regressions;
`tests/mintcart/onboarding/wizard-progress-test.php` updated for the new
3-implemented-step sequencing (9 scenarios, was 8); `wizard.twig`'s
`{% if %}`/`{% for %}`/`{% endif %}`/`{% endfor %}` tag counts re-checked
balanced (32/32) after the new Contacts form block. Same pre-existing gap
as every earlier phase: no live MySQL/OpenCart request test in this
environment — a human should confirm the Contacts form saves/re-populates
correctly and that a filled-in social link actually renders on a real
`mint_default`-themed storefront footer.

**Update (a later run): Language & currency step.** Step 0 dedup check at the
start of this run found 11 open PRs (#52-#63); only #52 (Wizard Contacts
step) touches `wizard.php`/`wizardprogress.php`/`wizard.twig`/the wizard
language file, and its own body confirms its scope is the `contacts` step
only. `language_currency` (the third step in `WizardProgress::STEPS`, right
after `store`) was unclaimed, so this run implemented it, following the
exact shape `store` established: `WizardProgress::IMPLEMENTED_STEPS` gained
`'language_currency'`; `ControllerMintcartWizard` gained
`validateLanguageCurrencyStep()` (checks the submitted `config_language`/
`config_currency` codes actually exist via
`ModelLocalisationLanguage::getLanguageByCode()`/
`ModelLocalisationCurrency::getCurrencyByCode()`) and
`saveLanguageCurrencyStep()` (same read-existing-`config`-then-merge shape
`saveStoreStep()` uses, into the same `config` setting row —
`config_language`/`config_currency` are two more keys `Settings → Store`'s
own General tab already manages, checked against the real controller before
writing this per `CLAUDE.md` §84/§10, not a new Mint-owned duplicate).
`wizard.twig` gained the step's two `<select>` fields, populated from the
same `getLanguages()`/`getCurrencies()` calls that screen uses. Full
writeup: `docs/developer/onboarding/setup-wizard.md`'s new "Language &
currency step" section.

Note: this run branched from a stale local `origin/master` ref (fixed with
a fresh `git fetch`/`reset --hard` before starting) — worth a future run
double-checking its own branch base is actually current before relying on
"file doesn't exist" errors meaning anything other than a stale checkout.

`contacts` (PR #52) and this step were developed in parallel against
diverging bases; reconciling both onto `master` restored canonical order —
`IMPLEMENTED_STEPS` is `['welcome', 'store', 'contacts', 'language_currency']`.
This mirrors how every earlier out-of-order Wizard/Blocks increment in this
project has been reconciled.

Verified: `php -l` clean on every touched PHP file and the full `upload/` +
`tests/` tree; `wizard-progress-test.php` updated for the new implemented
step (9 scenarios, was 8 — scenario 3 split into 3/3b to cover the new
three-step resume chain, scenario 6's out-of-order-storage case updated
since `['store','welcome']` is no longer "ready" with a third pending
step, scenario 7 gained an `isImplemented('language_currency')` check);
full `tests/mintcart/` suite re-run end to end (33 files) — all pass, no
regressions. `wizard.twig`'s `{% if %}`/`{% endif %}`/`{% for %}`/
`{% endfor %}` tag counts re-balanced (27/27, 3/3) after the new step
block. Same pre-existing gap as every earlier controller-wiring increment:
no live MySQL/OpenCart admin session in this environment — a human should
confirm `mintcart/wizard?step=language_currency` renders the real
language/currency lists and that `saveLanguageCurrencyStep()` preserves
every other `config_*` setting, the same way the Store step's own caveat
already asks for its four fields.

**Not done yet, by design — natural next-run candidates:**
- The remaining six steps (`contacts`, `language_currency`, `delivery`,
  `payment`, `design`, `first_product`), each needing its own real
  form/save logic the way `store` established.
- The remaining steps (`delivery`, `payment`, `design`, `first_product`,
  `ready`), each needing its own real form/save logic the way
  `store`/`contacts`/`language_currency` established — `ready` in
  particular should integrate with Phase 14's `StoreReadiness`
  (`mintcart/readiness`, already shipped) once wired, rather than being a
  placeholder.
- Store step's logo upload (Functional Scope §2) — needs `McMediaPicker`
  (already shipped, Phase 11) wired to a real upload endpoint, which
  doesn't exist yet.
- No entry point links to `mintcart/wizard` anywhere in the admin UI yet
  (no redirect-after-install, no dashboard link) — reaching it today means
  navigating to the route directly.
- `assets/dist/js/admin.min.js` is still not enqueued anywhere (this page
  is CSS-only) — same pre-existing gap as the rest of the admin.

**Update (2026-09-19, routine run): the Delivery step is done.**
`WizardProgress::IMPLEMENTED_STEPS` gains `delivery`, inserted between
`language_currency` and `ready` (matching canonical `STEPS` order, same
"reconciled back to canonical order" precedent `contacts`/
`language_currency` themselves already set when they were added after
`ready`). `ControllerMintcartWizard`'s `delivery` step lists installed
shipping extensions (`ModelSettingExtension::getInstalled('shipping')`,
same data source and stale-extension guard `ControllerMintcartBusinessDelivery`
already established in Phase 14) with a real enabled/disabled badge and a
per-extension Enable/Disable toggle (`toggleDelivery()`, route
`mintcart/wizard.toggleDelivery`, the same merge-then-save
`shipping_<code>_status` write `ControllerMintcartBusinessDelivery::toggle()`
already uses, duplicated rather than shared per that class's own precedent
of choosing two near-duplicate controllers over a shared base class), plus
a "Manage delivery methods" link to the full `mintcart/business/delivery`
screen. No fields to validate/save on the step's own Continue submission —
enabling a method is `toggleDelivery()`'s job, not Continue's, and per
Functional Scope §2 a store can continue with zero shipping methods
enabled (the Ready step's own `StoreReadiness` checklist is what actually
flags that as outstanding). Full writeup:
`docs/developer/onboarding/setup-wizard.md`'s new "Delivery step" section.

Step 0 dedup check: `list_pull_requests` (all open, not filtered by branch
name) returned 6 open PRs at the start of this run — `#127`
(`MintCart\Update\PackageExtractor`), `#128` (`MintCart\Update\BackupExecutor`),
`#129` (admin toggle for `mintcart_dev_mode`), `#130` (Related Products
`McEntityPicker` field), `#131` (responsive image width/height + lazy
loading), `#132` (`special_price` bulk-editable field) — verified via each
PR's own file list that none touch `wizard.php`, `wizardprogress.php`,
`wizard.twig`, the wizard language file, or
`upload/admin/controller/mintcart/business/*`. No hot shared files (§0f:
Homepage Builder block-type registry, homepage block list/template,
`block.css`) are touched by any of the 6 open PRs or by this increment.

Verified: `php -l` clean across the full `upload/`/`tests/` tree.
`tests/mintcart/onboarding/wizard-progress-test.php` grows from 10 to 11
scenarios (the new `delivery` step's place in the resume sequence, its
`isImplemented()` check, and the "not yet implemented" check moved from
`delivery` to `payment`). Full `tests/mintcart/**/*-test.php` suite (81
files) re-run end to end — all passing, no regressions. `wizard.twig`'s
`{% if %}`/`{% endif %}`/`{% for %}`/`{% endfor %}` tag counts checked
balanced (46/46, 5/5) after the new step block. **Not verified:** no live
MySQL/admin session in this sandboxed environment — see
`docs/MANUAL_VERIFICATION.md`'s new Phase 13 "Wizard Delivery step" entry
for the full manual script a human should run, including the
`editSetting()`-partial-write-safety check against a shipping extension's
other settings.

**Next steps for a future session:**
- The `payment` step, a near-mechanical follow-up to this one (see the
  "not done yet" list above and the doc's own note on the exact shape).
- `design`/`first_product` steps — need their own real form/save logic,
  no existing facade to lean on the way `delivery`/`payment` can.
- Re-check `gh pr list --state open` before picking the next increment —
  this backlog moves fast.

**Update (2026-10-01, routine run): the `first_product` step is done.**
`WizardProgress::IMPLEMENTED_STEPS` gains `first_product`, inserted between
`delivery` and `ready` (matching canonical `STEPS` order — the same
"reconciled to canonical order" shape `contacts`/`language_currency`/
`delivery` themselves already set). `ControllerMintcartWizard`'s
`first_product` step collects only `product_name` + `price` (required)
plus optional `quantity`/`description`, then calls the real, unmodified
`ModelCatalogProduct::addProduct()` — every other field that model
requires gets a safe default (today's `date_available`, the store's own
`config_weight_class_id`/`config_length_class_id`, `status = 1`, stock
status resolved by name rather than a hardcoded id — see
`loadDefaultStockStatusId()`). A "Skip for now" button
(`first_product_action=skip`) bypasses validation/creation entirely and
just marks the step completed, per Functional Scope §2's optional-step
skipping (same precedent `delivery` already set). This closes
`MintCart\Onboarding\StoreReadiness`'s own `first_product` signal gap —
completing this step with a real product now flips that checklist item
from "Not yet" to "Done" on the very same Ready step this Wizard already
renders. Full writeup: `docs/developer/onboarding/setup-wizard.md`'s new
"The First product step writes a real, ordinary product" section.

Step 0 dedup check: `list_pull_requests` returned 42 open PRs (#138-#180)
at the start of this run. Exactly one, `#142` ("Setup Wizard Payment
step"), touches the same four files this increment touches
(`wizard.php`/`wizardprogress.php`/`wizard.twig`/the wizard language
file) — same accepted-risk shape this file's own `contacts`/
`language_currency` entries already documented and reconciled twice
before (both were developed in parallel against diverging bases, then
merged back to canonical order by hand). `#142`'s own diff adds the
`payment` step; this increment adds `first_product` — both are
append-only changes to the same arrays/switch statements/template `{% if
step == ... %}` blocks, which is exactly the shape prior reconciliations
in this file handled cleanly, not the kind of same-feature duplicate step
0d warns about. No Homepage Builder hot file (§0f — block-type registry,
`block.css`) is touched by either PR.

Verified: `php -l` clean across the full `upload/`/`tests/` tree.
`tests/mintcart/onboarding/wizard-progress-test.php` grows from 11 to 12
scenarios (the new `first_product` step's place in the resume sequence,
its `isImplemented()` check, and the reorder/idempotent scenarios'
expected lists updated). Full `tests/mintcart/**/*-test.php` suite (84
files) re-run end to end — all passing, no regressions. `wizard.twig`'s
`{% if %}`/`{% endif %}`/`{% for %}`/`{% endfor %}` tag counts checked
balanced (53/53, 5/5) after the new step block. **Not verified:** no live
MySQL/admin session in this sandboxed environment — see
`docs/MANUAL_VERIFICATION.md`'s new Phase 13 "Wizard First product step"
entry for the full manual script a human should run, including the
Ready step's checklist actually flipping to "Done" after a real product
is created (not merely after the step is marked completed via Skip).

**Next candidates:** reconcile `IMPLEMENTED_STEPS`' order once `#142`
(Payment step) merges — canonical order is `..., delivery, payment,
design, first_product, ready`, so a future run should move `payment`
between `delivery` and `first_product` rather than leaving it appended
wherever each PR's own diff happens to land it; the `design` step (needs
a real form/save target, likely the preset picker once it has a live
admin consumer); Store step's logo upload; or re-check `gh pr list
--state open` first, since this backlog moves fast.

## Phase 14 — Store Readiness / Business Settings

- [x] `MintCart\Onboarding\StoreReadiness` service (computed, not hardcoded,
      percentage + checklist) — `upload/system/library/mintcart/onboarding/storereadiness.php`.
      No OpenCart dependency (same shape as `CartService`/`ProductViewProvider`);
      returns `{percent, checks[], ready_for_test_order}`. `percent` is derived
      from complete/total, never a literal number. `ready_for_test_order` is a
      narrower, separate signal — `true` only when `first_product && shipping
      && payment` are all true, independent of the overall percentage (a store
      can look 100% configured and still not be able to complete a checkout).
      Checklist covers all 7 items Functional Scope §2/CLAUDE.md §22 list: logo,
      contacts, language, currency, shipping, payment, first product.
- [x] Real OpenCart signals wired — `ModelMintcartReadiness::getSignals()`
      (`upload/admin/model/mintcart/readiness.php`) reads existing OpenCart
      storage only (`config_logo`/`config_telephone`/`config_email`/
      `config_address` settings, `oc_language`/`oc_currency` `status`, the
      `extension` table + each extension's own `<type>_<code>_status` setting
      — the exact key every stock shipping/payment extension controller already
      saves under, e.g. `extension/shipping/flat.php`'s `shipping_flat_status`
      — and `catalog/product`'s own `getTotalProducts()`). No new Mint-specific
      table for any of this (CLAUDE.md §39/§6).
- [x] `ControllerMintcartReadiness` (`upload/admin/controller/mintcart/readiness.php`,
      route `mintcart/readiness`, GET, JSON) — first real consumer of the
      service (CLAUDE.md §85); fully additive, zero existing OpenCart file
      touched, zero upstream diff. Language file:
      `upload/admin/language/en-gb/mintcart/readiness.php`.
- [~] `Business → Contacts`, `Business → Delivery`, `Business → Payments` UI façades
      over standard OpenCart storage/extensions — **Delivery and Payments
      done this run** (see below); Contacts is separately in flight as an
      open PR — check `docs/IMPLEMENTATION_STATUS.md`'s current state
      before assuming it's still unclaimed.
- [ ] Dashboard widget rendering the checklist — not started on `master`
      as of this run; `mintcart/readiness` is a JSON endpoint only, no
      admin menu entry or Twig template yet. May already be in flight on
      an open PR — check before starting one.
      over standard OpenCart storage/extensions — **Contacts done**
      (see below); Delivery/Payments not started.
- [x] Dashboard widget rendering the checklist — done, see below.

**Sub-step (a previous run):** `StoreReadiness` service + `mintcart/readiness`
JSON endpoint. Full writeup: `docs/developer/onboarding/store-readiness.md`.
Verified: `php -l` clean on every new file and on the full `upload`/`tests`
tree; full `tests/mintcart/` suite (13 files, including the new
`tests/mintcart/onboarding/store-readiness-test.php`, 7 scenarios) passing.
Not verified: no live MySQL/OpenCart admin session in this environment — a
human should hit `mintcart/readiness` as a logged-in admin (the
`mintcart/readiness` permission is auto-discovered by the existing
`user_permission.php` controller-tree scan, same pattern as PR #46's
`design/homepage`) and confirm each signal flips as expected on a real
install.

**Update (this run): `Business → Delivery`/`Business → Payments` facades
are done.** `ControllerMintcartBusinessDelivery`/`...Payments`
(`upload/admin/controller/mintcart/business/{delivery,payments}.php`,
routes `mintcart/business/delivery`/`mintcart/business/payments`) list the
store's installed shipping/payment extensions
(`ModelSettingExtension::getInstalled()`, the same source
`Extensions → Shipping/Payment Methods` already use) with a one-click
enable/disable toggle over the exact `<type>_<code>_status` setting key
`ModelMintcartReadiness::getSignals()` already reads — full settings
configuration stays on each extension's own existing
`extension/shipping|payment/<code>` screen, linked as "Configure", per
`MINTCART_TECHNICAL_PLAN.md` §23's "don't build a second shipping/payment
subsystem". New "Business" menu group in `column_left.php`
(`mintcart/business/delivery`/`mintcart/business/payments` permission-gated).
Full design writeup, including why a merge-then-save toggle (not
`editSettingValue()`) and why two near-duplicate controllers instead of a
shared base class: `docs/developer/onboarding/business-delivery-payments.md`.

Step 0 dedup check found 11 other open PRs at the time (`#52`-`#63`);
none touch `column_left.php`, `admin/controller/mintcart/business/*`, or
Phase 14's Delivery/Payments scope — `#60` (if still open) covers a
*Contacts* facade only, a different file/route with no overlap.

Verified: `php -l` clean on every touched/added PHP file, re-swept across
the full `upload/`/`tests/` tree; both new `.twig` files' tag counts
checked balanced; full `tests/mintcart/**/*-test.php` suite re-run end to
end (33 files) — all passing, no regressions (no new pure logic to test —
this is OpenCart controller/Twig wiring around existing
`ModelSettingExtension`/`ModelSettingSetting` calls, same "no new unit
test" precedent the Store Readiness dashboard widget already set). Not
verified: no live MySQL/admin session in this environment — see
`docs/MANUAL_VERIFICATION.md`'s new Phase 14 entry.

**Next step:** a dashboard widget/admin menu entry rendering
`mintcart/readiness`'s checklist (check open PRs first — may already be
claimed), a `Business → Contacts` facade (ditto), reconciling the
"Business" `column_left.php` menu group once more than one of these PRs
has merged (small, expected conflict — same shape every parallel
Wizard-step/entity-picker increment in this project has already
reconciled), or wiring Phase 13's still-unbuilt `ready` Wizard step to
`StoreReadiness`.
**Update (a later run): the Store Readiness dashboard widget is done.**
`ControllerExtensionDashboardMintcartReadiness`
(`upload/admin/controller/extension/dashboard/mintcart_readiness.php`) is
a **standard OpenCart 3 dashboard extension** — the same auto-discovery
mechanism every stock widget (`recent.php`, `online.php`, `sale.php`, ...)
already uses (`admin/controller/common/dashboard.php` globs
`admin/controller/extension/dashboard/*.php`, and a store admin installs +
enables it once under Extensions -> Dashboard, exactly like any other
widget) — not OCMOD, not a new mechanism. `dashboard()` reuses the exact
same `StoreReadiness::evaluate()` + `ModelMintcartReadiness::getSignals()`
pair `ControllerMintcartReadiness`'s JSON endpoint already calls — one
computation, two consumers, per `docs/developer/onboarding/store-readiness.md`'s
own "next consumers" note. Styled with stock Bootstrap 3
`.tile`/`.progress`/`.label` classes (same as `online_info.twig`), **not**
`mc-*` markup — deliberately sidesteps the still-unsolved admin
asset-enqueue blocker (Phase 12/17's identical gap) rather than shipping
unstyled `mc-*` classes on a page that can't load their CSS. Tile footer
links to `mintcart/wizard` ("Continue setup..."). Full writeup:
`docs/developer/onboarding/store-readiness.md`'s new "Dashboard widget"
section.

Verified: `php -l` clean on the new controller/language file; the two new
`.twig` files' tag counts checked balanced. No new unit test — pure
OpenCart controller/Twig wiring around already-tested logic, no new
computation to test; full `tests/mintcart/` suite re-run end to end — all
pass, no regressions. Not verified: no live MySQL/admin session in this
environment — a human should install + enable the widget under Extensions
-> Dashboard on a real install and confirm it renders/saves correctly. See
`docs/MANUAL_VERIFICATION.md`'s new Phase 14 entry.

**Update (a later run): `Business → Contacts` UI facade is done.**
`ControllerMintcartBusinessContacts` (`upload/admin/controller/mintcart/business/contacts.php`,
route `mintcart/business/contacts`) is a standalone-editable facade over
the exact same storage `ControllerMintcartWizard`'s Store/Contacts steps
already write (`config_telephone`/`config_email`/`config_address` +
`mintcart_contacts_social`) — a shop owner can now update contact
details/social links any time from the normal admin nav (new "Business"
menu group in `column_left.php`), not only once during onboarding. New
`MintCart\Onboarding\SocialProfileLinks` (pure, unit-tested,
`tests/mintcart/onboarding/social-profile-links-test.php`) factors out the
social-link validate/normalize/re-key logic so this controller doesn't
duplicate the Wizard's inline copy verbatim — deliberately **not** wired
into `ControllerMintcartWizard` itself this run, since PR #52 ("Setup
Wizard: Contacts step") had `wizard.php` open for an unrelated change at
the time; see `docs/developer/onboarding/business-settings.md` for the
full rationale and the "Next step" note about folding the two together
once #52 lands. Uses the legacy Bootstrap 3 admin theme, same reasoning as
the Homepage Builder admin editor and the Store Readiness dashboard
widget.

Verified: `php -l` clean on every touched/added PHP file, re-swept across
the full `upload`/`tests` tree; `contacts_form.twig`'s Twig tag counts
balanced; full `tests/mintcart/**/*-test.php` suite re-run end to end (35
files, was 34) — all passing, no regressions. Not verified: no live
MySQL/admin session in this environment — see
`docs/developer/onboarding/business-settings.md`'s "Verified" section and
the new `docs/MANUAL_VERIFICATION.md` entry.

**Next step:** `Business → Delivery`/`Business → Payments` (read the
standard `extension` table for shipping/payment extensions, present a
simplified enable/configure view — no second shipping/payment subsystem),
or wiring Phase 13's still-unbuilt `ready` Wizard step to the
`StoreReadiness` computation the dashboard widget and JSON endpoint
already share.

## Phase 15 — Commerce API

- [x] `MintCart\Commerce\ProductViewProvider` — done since Phase 6.
- [x] `MintCart\Commerce\CartService` + Cart JS client
      (`add/remove/update/refresh`) + `mc:cart:updated` event contract —
      `upload/system/library/mintcart/commerce/cartservice.php` +
      `upload/catalog/controller/mintcart/cart.php`
      (`ControllerMintcartCart`, route `mintcart/cart/*`, fully additive —
      zero existing OpenCart file modified) +
      `assets/src/js/commerce/cart-client.js`. First real consumer:
      `mc-product-card`'s Add to Cart button
      (`assets/src/js/components/product-card.js`), which now calls
      `addToCart()`, shows a `createToast()` confirmation (`toast.js`
      extended with a build-on-the-fly toast factory), and redirects to
      the product page when required options/a recurring plan can't be
      answered from a listing card. Docs:
      `docs/developer/commerce/cart-service.md`,
      `docs/developer/commerce/cart-client.md`. Test:
      `tests/mintcart/commerce/cart-service-test.php` (8 scenarios).
      **Verified live against a real install** (`http://mintcart.localhost/`)
      — a first for a MintCart Commerce/controller increment, every earlier
      phase's caveat was "no MySQL, unverified end-to-end": added five
      real products via `curl`, quantities/totals recalculated correctly
      on `update`, `remove` returned the cart to empty, and three products
      with required options (`product_id` 30/35/42 in the demo catalog)
      correctly returned `{error, redirect}` and added nothing — confirmed
      via a follow-up `refresh()` that the cart's item count matched only
      the five successful adds.
- [x] `MintCart\Commerce\SearchService` + AJAX Search normalized endpoint —
      `upload/system/library/mintcart/commerce/searchservice.php` +
      `upload/catalog/controller/mintcart/search.php`
      (`ControllerMintcartSearch`, route `mintcart/search/suggest`, fully
      additive — zero existing OpenCart file modified, same pattern
      `ControllerMintcartCart` established). Reuses `product/search.php`'s
      own `model_catalog_product` `filter_name` query shape so the two
      never drift on what counts as a match; `product/search.php` itself
      (the full results page, with its own `product_cards` pipeline) is
      completely untouched — this only serves a lightweight
      typeahead/suggest JSON response, a deliberately smaller item shape
      (`id, name, url, image, price, special`) than the full `ProductView`
      contract, per `MINTCART_TECHNICAL_PLAN.md` §26. Doc:
      `docs/developer/commerce/search-service.md`. Test:
      `tests/mintcart/commerce/search-service-test.php` (10 scenarios).
      **Not yet verified live** — this environment has no MySQL server
      (same pre-existing gap noted throughout this file); a human should
      confirm `mintcart/search/suggest?search=<term>` on a real install.
      No Mint-native search-box UI component calls this endpoint yet
      (`CLAUDE.md` §85 — ships ahead of its first consumer, same as every
      other Phase 11/15 primitive) — that is the natural next increment.
- [~] `QuickOrderService` — open PR #52-#87 backlog includes one (Phase 15
      item, not yet merged as of this update — check `gh pr list --state
      open` before starting a second one).
- [~] `CheckoutService` — two increments so far: `MintCart\Commerce\CheckoutTotalsService` +
      `MintCart\Compatibility\OpenCart3\TotalsAdapter` + the
      `mintcart/checkout/totals` AJAX endpoint (recomputed order totals);
      and (this run) `MintCart\Commerce\ShippingMethodService` +
      `MintCart\Compatibility\OpenCart3\ShippingQuoteAdapter` + the
      `mintcart/checkout/shipping_methods` AJAX endpoint (normalized
      shipping method quote preview for a given address). Still no
      address/payment mutation, no order creation. See the writeups below.
- [x] `MintCart\Commerce\QuickOrderService` + Quick Order component (this
      run) — `upload/system/library/mintcart/commerce/quickorderservice.php`
      + `upload/catalog/controller/mintcart/quickorder.php`
      (`ControllerMintcartQuickorder`, route `mintcart/quickorder/submit`,
      fully additive) + `assets/src/js/components/quick-order.js`
      (`initQuickOrderActions()`, wired into `catalog.js`) +
      `assets/src/js/commerce/quick-order-client.js`. A Quick Order request
      is a lightweight lead (product/quantity/name/phone/email, no
      address/payment) recorded in a new, genuinely Mint-specific
      `mc_quick_order` table (migration
      `202609041200_create_mc_quick_order.php`) — deliberately **not**
      `checkout/order.php::addOrder()`/`oc_order`, sidestepping the exact
      `addOrder()` field-list risk PR #61 flagged as too large without live
      MySQL verification. Same required-option/recurring-plan → redirect
      fallback `ControllerMintcartCart::add()` already uses (reuses
      `CartService::validateOptions()`, not re-derived). Doc:
      `docs/developer/commerce/quick-order-service.md`. Tests:
      `tests/mintcart/commerce/quick-order-service-test.php` (10
      scenarios), `tests/mintcart/commerce/quick-order-migration-test.php`
      (schema). **Not yet verified live** — no MySQL in this environment;
      see `docs/MANUAL_VERIFICATION.md`'s new Phase 15 section. **Not
      built**: an admin "Quick Order requests" list to surface these leads
      (natural next consumer of `mc_quick_order`), and lead → order
      conversion — both deliberately deferred, see the doc's "Not done
      yet".
- [~] `CheckoutService` — totals + shipping-method-quote previews exist
      (see above); order creation (`addOrder()`) itself remains genuinely
      large/risky without live MySQL to verify its full field list against
      (same reasoning `QuickOrderService` above sidestepped by not
      touching order creation at all) — still not attempted.
- [x] Mint-native search-box UI component (`mc-search-box`) calling
      `mintcart/search/suggest` — see "Update (this run)" below.
- [ ] Modern checkout UI over existing shipping/payment extensions/totals/order model — not started.

**Update (this run): `CheckoutTotalsService` + `TotalsAdapter` — first
`CheckoutService` increment.** Step 0 dedup check against the 35 open PRs
at the time (`#52`-`#87`) found none touching Phase 15's `QuickOrderService`/
`CheckoutService` gap, and none touching `checkout/payment_method.php`/
`checkout/confirm.php`/anything under `compatibility/opencart3/` — the only
contended areas were the Setup Wizard files (`#52`/`#64`/`#84`),
`column_left.php` (`#60`/`#65`/`#66`), `category.php`/`search.php`
(`#62`/`#83`), `admin/controller/catalog/product.php` (`#63`/`#80`), and
`docs/developer/update/package-manifest.md` (`#82`/`#86`) — none of which
this increment touches.

`checkout/payment_method.php` and `checkout/confirm.php` each inlined an
identical ~20-line loop computing order totals from installed `total`
extensions. Extracted into `MintCart\Compatibility\OpenCart3\TotalsAdapter`
(`docs/developer/compatibility/totals-adapter.md`) — the repo's second
`Compatibility\OpenCart3` adapter, following the same "wait for a third
real call site" extraction threshold `ActiveTheme`/`ProductCardListRenderer`
were extracted under. The third call site is a new, fully-additive
`ControllerMintcartCheckout::totals()` (route `mintcart/checkout/totals`,
GET, JSON), whose response is normalized via the new
`MintCart\Commerce\CheckoutTotalsService`
(`docs/developer/commerce/checkout-totals-service.md`) — same
no-OpenCart-dependency, formatter-injected split `CartService::snapshot()`
already established.

**Bug caught while refactoring:** removing `confirm.php`'s totals loop
left `$order_data['total'] = $total_data['total'];` (further down the same
method) reading a now-undefined variable — a leftover alias for the exact
value the plain `$total` variable already held. Fixed by pointing it at
`$total` directly; see `docs/upstream-diff.md`'s `confirm.php` entry for
the full explanation. This was a latent risk this refactor exposed and
fixed in the same change, not a pre-existing bug being shipped separately.

Verified: `php -l` clean across the full `upload/`/`tests/` tree; full
`tests/mintcart/` suite re-run end to end (35 files, was 33, including the
two new `tests/mintcart/compatibility/totals-adapter-test.php` and
`tests/mintcart/commerce/checkout-totals-service-test.php`) — all pass, no
regressions. Same pre-existing gap as every other controller-wiring
increment: no MySQL/OpenCart bootstrap in this environment — see
`docs/MANUAL_VERIFICATION.md`'s new Phase 15 entry for what a human should
confirm on a real install (same payment methods/amounts as before the
refactor, correct `oc_order.total`, and the new endpoint's JSON shape).

**Update (2026-09-30, routine run): `ShippingMethodService` +
`ShippingQuoteAdapter` — second `CheckoutService` increment.** Step 0
dedup check found 23 open PRs (`#138`-`#160`, all `ROUTINE-STATUS: DONE`
except `#148` `IN-PROGRESS`) — none touch
`upload/catalog/controller/mintcart/checkout.php`,
`upload/catalog/controller/checkout/shipping_method.php`, or anything
under `compatibility/opencart3/`/`commerce/` named `shipping*`. The
busiest hot zone (`category.php`/`search.php`/`manufacturer.php`/
`special.php`/`product.php`, touched by 4 of the open PRs) and the
Homepage Builder block-registry/`block.css` files (untouched by any open
PR, but the known historical pile-up risk per this routine's own
instructions) were both avoided entirely by this increment's file list.

Extracted `checkout/shipping_method.php::index()`'s shipping-quote loop
into `MintCart\Compatibility\OpenCart3\ShippingQuoteAdapter`
(`docs/developer/compatibility/shipping-quote-adapter.md`) — unlike
`TotalsAdapter`, this loop is **not** duplicated anywhere else in this
codebase, so `checkout/shipping_method.php` itself is left completely
untouched (zero upstream diff, no `docs/upstream-diff.md` entry needed).
Normalized via the new `MintCart\Commerce\ShippingMethodService`
(`docs/developer/commerce/shipping-method-service.md`) into a new
`ControllerMintcartCheckout::shippingMethods()` action (route
`mintcart/checkout/shipping_methods`, GET, JSON) — a pure preview endpoint
that takes the address as request parameters and does not read or write
`$this->session->data['shipping_address']`/`['shipping_methods']`, unlike
the legacy controller. `options[].id` in the response is built as exactly
`"<method_code>.<quote_key>"`, the same string
`checkout/shipping_method.php::save()` already expects, so a future
checkout UI can post it straight through with no translation layer.

Verified: `php -l` clean on all three touched/new PHP files; two new test
files (`tests/mintcart/compatibility/opencart3/shipping-quote-adapter-test.php`,
5 scenarios; `tests/mintcart/commerce/shipping-method-service-test.php`, 4
scenarios); full `tests/mintcart/**/*-test.php` suite re-run end to end
(86 files, up from 84) — all passing, no regressions. Not verified: no
live MySQL/OpenCart session in this environment, same pre-existing gap —
see `docs/MANUAL_VERIFICATION.md`'s new Phase 15 "Shipping method quote
endpoint" section for what a human should confirm on a real install.

**Next candidates:** a `mintcart/checkout/payment_methods` preview
endpoint (the same pattern applied to `checkout/payment_method.php`'s
payment-extension loop, which already routes through `TotalsAdapter` for
totals but still inlines its own `getMethod($address, $total)` loop for
listing available payment methods); a Mint-native checkout UI Twig/JS
component that actually calls `totals()`/`shippingMethods()` (still
nothing does — see both docs' "What this is not"); `CheckoutService`'s
order-creation step itself (still deliberately not attempted, same
`addOrder()` field-list risk noted above); or re-check `gh pr list --state
open` first, since this backlog moves fast.

**Update (this run): the header's legacy cart dropdown now reacts to
`mc:cart:updated`.** `assets/src/js/components/header-cart.js`
(`initHeaderCartRefresh()`, wired into `catalog.js` only) listens on
`document` and re-fetches the *existing*, unmodified
`route=common/cart/info` endpoint to refresh `#cart`'s markup — zero PHP
touched, same "let the existing PHP/Twig produce correctly translated
HTML, don't reimplement its formatting in JS" approach the rest of this
phase followed. **Verified live**: added a product via
`mintcart/cart/add`, confirmed `common/cart/info`'s own response (what
`header-cart.js` fetches) correctly showed the updated count/total —
confirms the two endpoints share the same session cart and stay in sync.
The JS listener itself (DOM swap on the real `mc:cart:updated` event) is
a browser-only behavior curl can't exercise — a human should confirm in
a real browser that clicking Add to Cart visibly updates the header
dropdown. Doc: `docs/developer/commerce/cart-client.md`.

**Explicitly deferred, not forgotten:**
- A real Mint-native `mc-cart-drawer`/header cart component reading
  `event.detail` directly instead of doing a second fetch — today's
  `header-cart.js` is a bridge to the legacy widget, not a replacement.
- Product-page Add to Cart with a real option picker (today's redirect
  fallback sends the shopper there, but the page itself doesn't have a
  Mint-native Add to Cart yet — it renders through the legacy `default`
  theme's own product page, untouched by this increment).
- **Still open, unrelated to this increment:** `assets/dist/css/index.min.css`
  still isn't enqueued anywhere (see Phase 7's "Known gap" and this
  session's reverted Bootstrap 5 enqueue attempt in `header.twig`'s own
  comment) — the toast/button this increment ships are functionally
  correct (real AJAX, real cart mutation, correct event) but not yet
  *styled* on a live page. Verification above was functional
  (curl + JSON), not visual.

**Update (this run): `mc-search-box` — a Mint-native search-as-you-type
component calling `mintcart/search/suggest`.**
`assets/src/js/components/search-box.js` (`createSearchBox()`,
`initSearchBoxes()`) + `assets/src/css/components/search-box.css`, wired
into `catalog.js` only (storefront). Full contract, behavior and
Definition-of-Done in `docs/developer/components/search-box.md`. This was
the explicitly documented "natural next increment" both
`docs/developer/commerce/search-service.md` and this file's own Phase 15
notes above named once `SearchService`/the suggest endpoint shipped with
no UI consumer. Debounced (250ms) input, a client-side 2-character minimum
mirroring `SearchService::isQueryValid()`, `AbortController` request
cancellation (same technique `entity-picker.js` already uses), WAI-ARIA
listbox roving-tabindex keyboard navigation, a "View all results" link to
the full `product/search` page when the server's `count` exceeds the
returned items, and an `mc:search-box:results` event. Reuses `mc-price`/
`mc-price--special` for each result's price rather than a second
price-formatting look.

**Not wired into any live template yet, by design** — `mint_default`'s
header still renders the legacy jQuery-autocomplete search fragment
(`docs/developer/components/header.md` "Why widgets stay legacy
fragments"). `initSearchBoxes()` is nonetheless already called from
`catalog.js`: it only activates against a `[data-mc-search-box]` root,
which no shipped template has yet, so this is inert until a future header
increment adds that markup — the same "ship the component behind a real
Twig contract, wire the template later" approach `mc-quantity`/`mc-modal`
took in Phase 4. Deliberately did **not** attempt to touch
`mint_default/template/common/header.twig` or `test_theme`'s header
override this run — doing so risks the exact Bootstrap-3/5 CSS conflict
Phase 7's reverted enqueue attempt already hit once, and this component's
own fetch/render/keyboard logic is independently verifiable without that
risk (see below).

Verified: `npm install` (0 vulnerabilities), `npm run build` (minified)
and `npm run dev` (sourcemapped) both compile cleanly; `mc-search-box`/
`mc-search-box__item`/etc. confirmed present in the bundled
`catalog.min.js`/`index.min.css` and **absent** from `admin.min.js`
(storefront-only, spot-checked with `grep -o`, same scoping
`product-card.js` uses); `npm test` (preset-token contract checks,
unaffected) still passing; full `tests/mintcart/**/*-test.php` suite
re-run end to end (no PHP touched by this increment) — still passing, no
regressions. **Not verified:** no live browser/MySQL session in this
environment — a human should confirm keyboard navigation, the
debounce/abort behavior under fast typing, and the "View all results"
link once this component's markup lands in a real template; see
`docs/MANUAL_VERIFICATION.md`'s new Phase 15 entry.

**Next step:** wire `mc-search-box`'s markup contract into
`mint_default/template/common/header.twig` (and `test_theme`'s header
override, to prove the same Theme-API story Product Card/Header/Footer
already proved), `QuickOrderService`/`CheckoutService` (a genuinely large
increment — real order creation needs `checkout/order.php`'s full
payment/shipping/totals data shape, not something to guess at in one
run — see `upload/catalog/model/checkout/order.php::addOrder()`'s field
list before scoping that as a future increment), or Phase 14's
`Business → Delivery`/`Business → Payments` facades (check
`list_pull_requests --state open` first — PR #60 was still open with the
Business menu group / Contacts facade at the time of this run, so a
Delivery/Payments facade risks a `column_left.php` collision until it
lands).

**Update (2026-09-19, routine run): `mc-search-box` wired into both
themes' headers.** `list_pull_requests --state open` returned zero open
PRs at the start of this run, so no dedup/hot-shared-file check was
needed. Did exactly the "Next step" named above, first bullet:
`mint_default/template/mint/components/header.twig` now renders
`mc-search-box`'s documented markup contract in place of the legacy
`{{ widgets.search|raw }}` fragment; `test_theme`'s header override
renders the same component in a different placement, proving the
override-fallback story for it too. `header.php` gained one new
`text_search` key in the existing `$header_view + array(...)` merge (no
new language file work — the string already existed). `widgets.search`
itself is untouched in `HeaderViewProvider`'s contract, still computed and
available to any theme that wants the legacy fragment instead. Full
writeup: `docs/upstream-diff.md`'s new entries for `header.php` (the
`text_search` addition) and both `header.twig` files;
`docs/developer/components/search-box.md`'s "Wired into `mint_default`'s
and `test_theme`'s header" section; `docs/developer/components/header.md`'s
"Why widgets stay legacy fragments" section.

**Bug caught while verifying, fixed in the same run:**
`assets/src/css/components.css` — the Bootstrap-5-reset-free bundle
`header.php` actually enqueues on the storefront as `components.min.css`
— was missing three `@import` lines `index.css` already had
(`product-gallery.css`, `quick-order.css`, `search-box.css`), despite this
file's own docblock saying to keep the two lists identical. Without the
fix, `mc-search-box` (and, latently, the product gallery and quick-order
components) would have shipped markup with zero CSS on any live
storefront page. Fixed by adding the three missing imports in the same
order `index.css` uses. See `docs/upstream-diff.md`'s `components.css`
entry for the full writeup and the regression check
(`grep -c mc-search-box assets/dist/css/components.min.css`: 0 → 1).

Verified: `php -l` clean on `header.php`; both changed `.twig` files'
`if`/`for` tag counts re-balanced (10/10 and 3/3); full
`tests/mintcart/**/*-test.php` suite re-run end to end (84 files,
unaffected by this increment) — all passing; `npm install` (0
vulnerabilities), `npm run build` compiles cleanly; `npm test`
(preset-token contract checks, unaffected) still passing. **Not verified
live** — no MySQL/browser session in this environment; see
`docs/MANUAL_VERIFICATION.md`'s Phase 15 `mc-search-box` entry (now
updated with two more checks: the legacy fragment is actually gone from
`mint_default`, and `test_theme`'s own override works independently).

**Next candidates:** `QuickOrderService`/`CheckoutService` (still the
genuinely large item, unchanged from the note above);
`Business → Delivery`/`Business → Payments` facades (re-check
`gh pr list --state open` first, same `column_left.php` collision risk
noted above); a Mint-native Navigation component (still no second real
consumer per `CLAUDE.md` §85); or re-check `gh pr list --state open`
first, since this backlog can move fast.

In progress.

## Phase 16 — Bulk Editor

- [x] Bulk field-change validation + resumable batch chunking (pure, no DB) —
      see below
- [ ] Inline edit + multi-select bulk actions for Products (status, quantity, price,
      special, manufacturer, category, stock, SEO fields) — planner ships
      status/quantity/price/special_price/manufacturer_id/stock_status_id/
      category_ids; SEO fields deliberately excluded (see below); no admin
      UI yet
- [ ] Chunked/resumable AJAX batch jobs (no background daemon) — the
      resumable stepping primitive (`nextBatch()`) exists; the actual AJAX
      endpoint and DB-write loop calling it do not

**`MintCart\Catalog\BulkProductUpdatePlanner`** (pure, no OpenCart
dependency) validates a bulk product-field-change request (which products,
which field values) and splits it into resumable batches via `nextBatch()`,
following the same "primitive first" pattern `CategoryMoveValidator`/
`PackageManifest`/`WizardProgress` already established. Full writeup:
`docs/developer/catalog/bulk-product-update.md`.

No apply step yet, by design: `ModelCatalogProduct::editProduct()` is a
full-replace write (deletes/reinserts descriptions, attributes, options,
images, categories, etc. on every call), so correctly applying a partial
bulk edit needs a per-product load-existing-data-then-merge-then-write loop
that doesn't exist yet — real, separate follow-up work once this validation
contract exists to build it against. SEO fields are excluded from the
bulk-editable field set entirely (not just unimplemented) — `seo_url`
uniqueness is enforced per store/language against the whole table, a
different shape than the scalar fields this planner handles, and a real
collision risk across a multi-product batch; see the doc's "SEO fields
excluded" section.

Verified: `php -l` clean; new
`tests/mintcart/catalog/bulk-product-update-planner-test.php` (10
scenarios — empty product list, empty field list, unknown field name,
valid status update with ID dedup/normalization, multiple invalid field
values reported independently, nullable `special_price`, `category_ids`
validation, chunk splitting, and three `nextBatch()` steps plus one
past-the-end call); full `tests/mintcart/` suite re-run end to end (35
files, was 34) — all pass, no regressions. Same pre-existing gap as every
earlier phase: no MySQL server in this environment, so the (not yet built)
apply step will need manual verification on a real install once it exists.

**Next step:** the load-merge-`editProduct()` apply loop plus a new admin
controller route (e.g. `catalog/product.bulk`) and permission check,
following `CLAUDE.md` §8A's controller-orchestrates-service-does-the-work
split — or the admin UI (multi-select checkboxes + bulk-edit form/drawer),
which like `McSortable`/`McEntityPicker` wiring elsewhere in this file is
blocked on the still-unsolved admin asset-enqueue problem for anything
beyond a plain HTML form.

In progress.
- [~] Multi-select bulk actions for Products (status, quantity, price,
      special, manufacturer, category, stock, SEO fields) — status,
      quantity, price and manufacturer done in earlier runs,
      **stock status added this run** (see the "Update" below); the
      remaining fields (special, category, SEO) are natural follow-ups
      over the same shape.
- [ ] True inline edit (editing a value directly in the product list
      table, no separate bulk-toolbar step) — not attempted.
- [ ] Chunked/resumable AJAX batch jobs (no background daemon) — today's
      `bulkUpdate()` is a synchronous PHP loop over the selected ids, the
      same (also synchronous) shape the existing Delete/Copy actions
      already use — not a new risk this increment introduces, but no
      progress reporting for a very large selection either.

**This run:** `MintCart\Catalog\BulkProductUpdateValidator` (pure, unit-
tested — `tests/mintcart/catalog/bulk-product-update-validator-test.php`,
25 scenarios) + `ModelCatalogProduct::updateProductField()` (new,
additive, single-column partial update — deliberately **not**
`editProduct()`, which deletes and reinserts descriptions/attributes/
options/discounts/specials wholesale and would silently wipe them on a
partial call) + `ControllerCatalogProduct::bulkUpdate()`/
`validateBulkUpdate()` (route `catalog/product.bulkupdate`, reusing the
exact `selected[]` multi-select checkboxes `delete()`/`copy()` already
provide, same form-POST-then-redirect shape — no new AJAX/JSON endpoint,
no new admin asset bundle). `product_list.twig`'s toolbar gained a
field `<select>` (Status/Quantity/Price) + value `<input>` + submit
button. Full writeup, including why the new button deliberately uses
`type="submit"`+`onclick="return confirm(...)"` rather than copying the
existing Delete button's `type="button"`+manual-`.submit()` pattern (which
would have been unsafe for a button whose `formaction` differs from the
form's default `action`): `docs/developer/catalog/bulk-editor.md`.

Step 0 dedup check found 10 other open PRs (#52, #53–#57, #59, #60, #61,
#62 — Wizard Contacts, five Phase 12 entity-picker backends, the Store
Readiness dashboard widget, the Business Contacts facade, the search-box
component, and SEO Core Schema.org). None touch Phase 16 or any file this
run touches (`upload/admin/controller/catalog/product.php`,
`upload/admin/model/catalog/product.php`,
`upload/admin/view/template/catalog/product_list.twig`) — confirmed via
each PR's changed-files list, not just its title/body. The historical hot
files (Homepage Builder block-type registry, `homepage_list.twig`,
`block.css`) are untouched by this run and by all 10 open PRs.

Verified: `php -l` clean on every touched/added PHP file; full
`tests/mintcart/**/*-test.php` suite re-run end to end — all passing, no
regressions; `product_list.twig`'s Twig tag counts checked balanced
before and after the toolbar addition. **Not verified:** no live
MySQL/admin session in this environment — a human should confirm the
toolbar renders, the confirm dialog and redirect work, each field updates
only its own column, and — most importantly — that a bulk update leaves
every other relation (descriptions, options, discounts, specials)
completely untouched. See `docs/MANUAL_VERIFICATION.md`'s new Phase 16
entry and `docs/upstream-diff.md`'s new
`admin/model|controller/catalog/product.php` entry.

**Next step:** the remaining bulk-editable fields (special, manufacturer,
category, stock status, SEO), a friendlier per-field value control (a
`<select>` for Status instead of a generic text input), or true inline
edit directly in the product list table (likely blocked on the same
admin asset-enqueue problem Phase 12/17 already flagged).

**Update (2026-09-15, routine run): Manufacturer added as a fourth
bulk-editable field.** Step 0 dedup check: two open PRs at the start of
this run (#110, #111 — both Phase 10 Diagnostics wiring into storefront
`upload/catalog/controller/product/*.php`), neither touching this run's
admin-side files (`upload/admin/**`, `upload/system/library/mintcart/catalog/**`).
`MintCart\Catalog\BulkProductUpdateValidator::FIELDS` gains
`manufacturer_id` (validated as a non-negative integer, `0` = no
manufacturer, matching OpenCart's own product-edit-form convention);
`ModelCatalogProduct::updateProductField()` gains the matching column
mapping; `product_list.twig`'s toolbar `<select>` gains a "Manufacturer"
option; `en-gb/catalog/product.php` gains
`text_bulk_field_manufacturer`. Same form-POST-then-redirect shape, no
new AJAX endpoint, no new admin asset bundle. Full writeup:
`docs/developer/catalog/bulk-editor.md`, `docs/upstream-diff.md`'s
admin/product entry, `docs/MANUAL_VERIFICATION.md`'s Phase 16 section.
Verified: `php -l` clean on every file under `upload/`/`tests/`;
`tests/mintcart/catalog/bulk-product-update-validator-test.php` grows
from 27 to 34 assertions; full `tests/mintcart/` suite (77 files) re-run
end to end — all passing, no regressions; `product_list.twig`'s tag
counts re-checked balanced (14/14, 2/2). Not verified: no live
MySQL/admin session in this environment, same pre-existing gap. **Next
candidates:** category/stock-status/special-price as further
`BulkProductUpdateValidator::FIELDS` entries (same shape); or re-check
`gh pr list`/`list_pull_requests` first, since PRs #110/#111 may have
merged by then.

**Update (2026-09-17, routine run): Stock Status added as a fifth
bulk-editable field.** Step 0 dedup check: 3 open PRs at the start of
this run (#117, #118, #119 — all Phase 18 SEO wiring into storefront
`upload/catalog/controller/product/*.php`, all marked `ROUTINE-STATUS:
DONE`), none touching this run's admin-side files (`upload/admin/**`,
`upload/system/library/mintcart/catalog/**`).
`MintCart\Catalog\BulkProductUpdateValidator::FIELDS` gains
`stock_status_id` (validated as a non-negative integer; `0` is accepted
purely because it mirrors `ControllerCatalogProduct::getForm()`'s own
fallback when no value has been posted, not because it is a meaningful
merchant-chosen target — the default install's `oc_stock_status` rows
start at id `5`); `ModelCatalogProduct::updateProductField()` gains the
matching column mapping; `product_list.twig`'s toolbar `<select>` gains a
"Stock Status" option; `en-gb/catalog/product.php` gains
`text_bulk_field_stock_status`. Same form-POST-then-redirect shape, no
new AJAX endpoint, no new admin asset bundle. Full writeup:
`docs/developer/catalog/bulk-editor.md`, `docs/upstream-diff.md`'s
admin/product entry, `docs/MANUAL_VERIFICATION.md`'s Phase 16 section.
Verified: `php -l` clean on every touched file under `upload/`/`tests/`;
`tests/mintcart/catalog/bulk-product-update-validator-test.php` grows
from 34 to 41 assertions; full `tests/mintcart/` suite (77 files) re-run
end to end — all passing, no regressions; `product_list.twig`'s tag
counts re-checked balanced (14/14, 2/2). Not verified: no live
MySQL/admin session in this environment, same pre-existing gap. **Next
candidates:** category and special (`special_price`) as further
`BulkProductUpdateValidator::FIELDS` entries (same shape, note this is a
distinct class/whitelist from `MintCart\Catalog\BulkProductUpdatePlanner`,
which already lists `special_price`/`category_ids`/`stock_status_id` in
its own separate, not-yet-wired-to-a-live-endpoint field set — see the
Phase 16 section above); a friendlier per-field value control (`<select>`
for Status, `type="number"` elsewhere); or re-check
`gh pr list`/`list_pull_requests` first.

**Update (2026-09-18, routine run): `special_price` added as a sixth
bulk-editable field.** Step 0 dedup check: 5 open PRs at the start of
this run (`#127` PackageExtractor, `#128` BackupExecutor, `#129`
Diagnostics dev-mode admin toggle, `#130` Related Products
`McEntityPicker` field, `#131` responsive image width/height + lazy
loading), none touching `bulkproductupdatevalidator.php`,
`admin/model|controller/catalog/product.php`, or `product_list.twig`; no
hot shared files (§0f, Homepage Builder block registry/`block.css`)
touched either. `BulkProductUpdateValidator::FIELDS` gains
`special_price` (blank = remove the special, or a non-negative number,
mirroring `BulkProductUpdatePlanner::validateNullableNonNegativeFloat()`'s
existing semantics for the same field name).
`ModelCatalogProduct::updateProductField()` gained a dedicated branch for
it (not a `$columns` map entry, since `special_price` is an
`oc_product_special` row, not an `oc_product` column): delete existing
`oc_product_special` rows for the product, then insert exactly one row
(default customer group, `priority = 1`, no date range) only if a
non-blank value was given — a deliberate MVP simplification of the full
edit form's multi-row/date-ranged Special support, the simplest thing a
single `bulk_value` text input can express. `product_list.twig`'s toolbar
`<select>` gains a "Special Price" option;
`en-gb/catalog/product.php` gains `text_bulk_field_special`.
`category_ids` remains deliberately unwhitelisted here (needs a
multi-select control this single-text-input toolbar doesn't have).

Full writeup: `docs/developer/catalog/bulk-editor.md` (new "special_price
needed its own branch" explanation section, updated Verified/Next step),
`docs/upstream-diff.md`'s admin/product entry (new "Update" block),
`docs/MANUAL_VERIFICATION.md`'s Phase 16 section (new step 7). Verified:
`php -l` clean on every touched file under `upload/`/`tests/`;
`tests/mintcart/catalog/bulk-product-update-validator-test.php` grows
from 41 to 46 assertions; full `tests/mintcart/**/*-test.php` suite (81
files) re-run end to end — all passing, no regressions;
`product_list.twig`'s tag counts re-checked balanced (14/14, 2/2). Not
verified: no live MySQL/admin session in this environment, same
pre-existing gap — in particular, a human should confirm a bulk
special-price update replaces (not adds to) any existing
`oc_product_special` row(s) a product already had via the normal edit
form. **Next candidates:** `category_ids` as a further field (needs a
multi-select/picker UI, a bigger lift than every field so far); a
friendlier per-field value control (`<select>` for Status,
`type="number"` elsewhere); or re-check `gh pr list --state open` first.

**Update (2026-09-28, routine run): per-field-typed `bulk_value` control
built.** Step 0 dedup check: 2 open PRs at the start of this run (#138
`mc-search-box` header wiring, #139 admin Quick Order Requests list, both
`ROUTINE-STATUS: DONE`), neither touching `product_list.twig` or any
`upload/admin/**`/`upload/system/library/mintcart/catalog/**` file this
run touches; no hot shared files (§0f, Homepage Builder block
registry/`block.css`) touched by either. This was the last remaining item
on this doc's own "Next candidates" list not requiring a bigger lift
(`category_ids` still needs a real multi-select/picker control, a larger
increment). `product_list.twig`'s single generic `bulk_value` text
`<input>` is now two controls sharing that same `name` — an
Enabled/Disabled `<select>` for `status`, a `type="number"` input (whole
numbers for quantity/manufacturer_id/stock_status_id, `step="any"` for
price/special_price) for everything else — toggled by a small inline
`<script>` block on `#input-bulk-field`'s `change` event, which also
`disabled`s the non-active control so only one `bulk_value` ever reaches
the POST body. No PHP changed — `BulkProductUpdateValidator`/
`ControllerCatalogProduct::bulkUpdate()` already read `bulk_value` as a
plain string regardless of which control produced it, and still do all
the real validation. Full writeup: `docs/developer/catalog/bulk-editor.md`
(rewritten "Value input" section, updated Verified/Next step).

Verified: `product_list.twig`'s `{% if %}`/`{% for %}` tag counts
unchanged and balanced (14/14, 2/2 — no `{% %}` tag touched, only plain
HTML/inline `<script>`); all three of the file's inline `<script>` blocks
(including the new toggle) checked for JS syntax errors with a standalone
Node harness (Twig `{{ }}` substituted with a placeholder string) — all
parse cleanly. No PHP touched, so `tests/mintcart/**/*-test.php` was not
re-run (nothing it covers changed). No JS/CSS in `assets/` touched —
`npm run build`/`npm test` not re-run (this screen stays on the legacy
admin Bootstrap 3/jQuery stack, no MintCart asset bundle involved). **Not
verified live** — no MySQL/admin session in this environment, same
pre-existing gap; a human should confirm the select/number swap actually
happens on `#input-bulk-field` change, that submitting each field type
posts the right value shape, and that the previous six fields' bulk
updates still work exactly as before (see
`docs/MANUAL_VERIFICATION.md`'s Phase 16 entry). **Next candidates:**
`category_ids` as a further bulk-editable field (still needs a real
multi-select/picker control — the one remaining item this doc's Bulk
Editor "Next step" list has not yet closed); chunked/resumable AJAX batch
jobs for very large selections; true inline edit in the product list
table; or re-check `gh pr list --state open` first.

## Phase 17 — Categories drag & drop

- [x] SortableJS-based category move backend (transaction, cycle prevention,
      category_path rebuild, sort order update) — see below
- [x] Live admin UI wiring (`mc-sortable` behavior mounted on
      `category_list.twig`'s existing table, calling the `move` endpoint) —
      see "Live UI wiring (routine run)" below.

**Move/reorder backend and live UI wiring are both done.** Full writeup:
`docs/developer/catalog/category-drag-drop.md`; upstream diff entry in
`docs/upstream-diff.md`. `MintCart\Catalog\CategoryMoveValidator`
(`upload/system/library/mintcart/catalog/categorymovevalidator.php`) is a
pure, OpenCart-independent extraction of the exact cycle-prevention check
`ControllerCatalogCategory::validateForm()` already performs for the
existing add/edit form's parent picker (fed by the existing
`getCategoryPath()` closure-table lookup — no new "descendants" query
needed). `ModelCatalogCategory::rebuildCategoryPath()` is `editCategory()`'s
own inline `category_path` closure-table rebuild block, extracted
verbatim (no behavior change to `editCategory()`) so the new
`moveCategory($category_id, $parent_id, $sort_order = [])` can reuse it
inside a `START TRANSACTION`/`COMMIT`/`ROLLBACK` block (the same raw-SQL
pattern `MintCart\Core\Migration\MigrationManager` established — OpenCart
3's `\DB\MySQLi` has no transaction wrapper methods) — following Technical
Plan §30's documented flow exactly ("begin transaction, validate target,
prevent cycle, change parent, rebuild affected category_path subtree,
update sort order, commit"). `ControllerCatalogCategory::move()` is a new,
fully-additive action (route `catalog/category.move`) that orchestrates:
permission check, existence checks via the existing `getCategory()`,
cycle-prevention via the new validator, then `moveCategory()`. One new
language key (`error_category`).

**Live UI wiring deliberately not attempted this run** — same reasoning
PR #45 (Phase 12, category `McEntityPicker` backend) already documented:
wiring `McSortable` into `category_list.twig` needs MintCart's own
compiled bundle (`assets/dist/{css,js}`) enqueued on an admin page, and
nothing enqueues it on any admin screen yet (Phase 7's "Known gap",
carried forward through Phase 12). Shipping unverifiable, possibly-broken
drag markup would be worse than a tested, additive backend with the UI
left for once that enqueue problem is solved.

Verified: `php -l` clean on all four touched/added PHP files. New
`tests/mintcart/catalog/category-move-validator-test.php` (6 scenarios —
top-level move, unrelated-parent move, self-parent rejection, direct-child
cycle rejection, indirect/grandchild cycle rejection, numeric-string
`path_id` values from a real DB row shape), passing. Full `tests/mintcart/`
suite re-run end to end (13 files, was 12) — all pass, no regressions
(confirms the `rebuildCategoryPath()` extraction didn't silently change
anything the existing tests would have caught — though note none of the
existing suite actually exercises `ModelCatalogCategory` against a live
DB; see the caveat below). Same pre-existing gap as every earlier phase:
no MySQL server in this environment, so `moveCategory()`/
`rebuildCategoryPath()` and the `move()` action itself were never
exercised against a real database — see `docs/MANUAL_VERIFICATION.md`'s
new Phase 17 section for what a human should confirm, including that
`editCategory()` still behaves identically post-extraction.

**Next step (updated 2026-09-15, routine run):** the admin asset-enqueue
problem this note used to cite as the blocker was solved several runs ago
(`docs/developer/admin/asset-enqueue.md`, `enqueueMintAssets()` now has
two real consumers — Phase 12's Category/Manufacturer picker fields on
`product_form.twig`). Re-investigated this run to wire `McSortable` here
as the natural "second admin screen" consumer, and found a real,
previously-undocumented architectural mismatch instead:
`category_list.twig` is a flat, paginated table spanning every parent
(sorted by name or `sort_order` across the *entire* tree, unmodified from
stock OpenCart), while `moveCategory()`'s `order[]` contract is
sibling-scoped ("the full new sibling order under `parent_id`"). Naively
wrapping visible `<tr>`s in `data-mc-sortable`/`data-mc-sortable-item`
would either silently corrupt `sort_order` for same-parent siblings not
on the current page, or require guessing a list-UX redesign — a real
product/architecture decision (`CLAUDE.md` §20), not attempted. See
Phase 12's "Architectural fork identified, not resolved" update above for
the option set (parent-scoped tree/drill-down view; same-parent-only drag
with reject-and-snap-back; or a "Manage order for: [parent]" filter on
the existing list). A future run or a human should decide the list-UX
shape first; the backend itself needs no further work.

**Update (routine run): live UI wiring is done — the "not resolved" fork
above is resolved, but not by choosing one of its three options.** A
fourth, purely mechanical approach the earlier note didn't consider turned
out not to need a product/UX decision at all: instead of changing what the
admin is *allowed to drag* (a tree view, a same-parent filter, or
reject-and-snap-back), the save step itself splits the new visible order
back into per-parent groups and saves each group separately (skipping any
group with only one visible member), so a drag across the flat,
mixed-parent list can never mix one parent's `sort_order` numbering with
another's or reset an untouched category's `sort_order`. Full writeup:
`docs/developer/catalog/category-drag-drop.md`'s "Live UI wiring" and
"Resolves the Architectural fork..." sections.

`ControllerCatalogCategory::getList()` gained `enqueueMintAssets()`
(verbatim copy of `ControllerCatalogProduct`'s — `docs/developer/admin/
asset-enqueue.md`'s "components" bundle) and now passes `parent_id` per
row plus `$data['move']` into `category_list.twig`. The template mounts
the real `mc-sortable` **behavior** (not its CSS, which is flex-list-only
and would break a `<table>` — `data-mc-sortable`/`data-mc-sortable-handle`/
`data-mc-sortable-item`/`data-mc-action` are its documented hooks,
CLAUDE.md §14) directly on the existing Bootstrap 3 table, gated to
`sort == 'sort_order'` (dragging while sorted by Name would scramble a
visible order that has no persisted meaning). An inline `<script>` POSTs
to `catalog/category.move` immediately on `mc:sortable:reorder` — no
"Save Order" button, no page reload — reverting the row order and
announcing failure via an `aria-live="polite"` region if any group's save
fails. No category is ever re-parented from this UI.

Six new language keys (`text_drag_hint`, `text_move_up`, `text_move_down`,
`text_order_saved`, `text_order_failed`, `text_sort_by_order_first`).

**Bonus fix, found while verifying this was actually going to work:**
`assets/src/js/admin.js`/`catalog.js` called every `init*()` (including
`initSortableLists()`) at module top level with no DOM-readiness guard,
while every `header.twig` renders the enqueued bundle as a classic
`<script>` inside `<head>` — meaning it always ran *before* `<body>`
existed. Delegated-listener inits (`initAlertDismiss()`) happened to work
by accident; eager-`querySelectorAll()` inits
(`initSortableLists()`/`initModalTriggers()`/`initDrawerTriggers()`/
`initToastTriggers()`/`initTabsTriggers()`/`initProgressBars()`, and on
the storefront `initSearchBoxes()`) silently found nothing. Without this
fix, `category_list.twig`'s new drag behavior — the entire point of this
increment — would never have mounted on a real page. Both files now defer
their unchanged `init*()` sequence behind a `document.readyState`/
`DOMContentLoaded` guard. Full writeup:
`docs/developer/catalog/category-drag-drop.md`'s "Boot timing bug found
while verifying this increment" section. `assets/src/js/*` are
MintCart-owned, not OpenCart core, so no `docs/upstream-diff.md` entry.

Verified: `php -l` clean on `category.php`/`en-gb/catalog/category.php`;
`category_list.twig`'s `{% if %}`/`{% endif %}`/`{% for %}`/`{% endfor %}`
tag counts balanced (12/12, 2/2); the new inline `<script>` checked with a
standalone Node harness (Twig `{{ }}` output substituted with a
placeholder first) — parses cleanly. Full `tests/mintcart/**/*-test.php`
suite (82 files) re-run end to end — all passing, no regressions (no new
PHP class, so no new test file). `admin.js`/`catalog.js`: `node --check`
clean; `npm install && npm run build` compiles both bundles cleanly
(confirmed `DOMContentLoaded` present in both `assets/dist/js/admin.min.js`/
`catalog.min.js`); `npm test` (preset-token contract checks, unaffected)
still passes. **Not verified live** — same pre-existing no-MySQL/
no-browser-session gap as every other admin-screen increment in this
repo; see `docs/MANUAL_VERIFICATION.md`'s new "Live drag & drop UI"
entries, including a dedicated mixed-parent-page safety check.

**Next candidates:** full drag-to-re-parent (a tree/drill-down list view —
still a real, separate, larger increment, unlike the reordering solved
here); `enqueueMintAssets()` is now duplicated on two controllers
(`category.php`/`product.php`) — extracting a shared trait is a natural
low-risk cleanup once a third consumer appears (`CLAUDE.md` §85/§86, not
done here); or re-check `gh pr list --state open` first.

## Phase 18 — SEO Core

- [~] SEO URL, canonical, hreflang, meta title/description, robots controls, XML
      sitemap, Schema.org Product/Breadcrumb/Organization — **meta
      title/description** has a first increment this run (see below);
      canonical, hreflang, robots, sitemap and Schema.org each have their
      own increment sitting in open, unmerged PRs as of this run — see
      each PR's own title, not reflected in this checklist yet since they
      haven't merged
- [ ] System route URL aliases (cart, checkout, search, contact, account, etc.)
      — also has an open, unmerged PR as of this run

**Update (this run): `MintCart\Seo\MetaBuilder` — meta title/description
fallback.** Pure fix for `category.php`/`product.php`/`home.php` passing
an entity's own (frequently blank) `meta_title`/`meta_description`/
`config_meta_title`/`config_meta_description` straight to
`Document::setTitle()`/`setDescription()` with no fallback at all — a
category, product or homepage that never had its meta fields filled in
today ships with a silently empty `<title>` tag. Full writeup:
`docs/developer/seo/meta-builder.md`. No controller wiring yet — same
deliberately-deferred, mechanical follow-up `CanonicalUrlBuilder` (PR
open as of this run) already established, to keep this diff small and
reviewable. This run's step-0 duplicate-work check found 47 open PRs
covering `CanonicalUrlBuilder`, `HreflangBuilder`, `RobotsBuilder`,
`SitemapBuilder`, a Schema.org increment, and the System route URL
aliases item already claimed (none named "meta" or touching
`category.php`/`product.php`/`home.php`) — so meta title/description was
the one Phase 18 sub-piece still genuinely unclaimed.

Verified: `php -l` clean. `tests/mintcart/seo/meta-builder-test.php` (9
scenarios) — covers the basic fallback rule plus the exact blank-meta
shapes `category.php`/`product.php`/`home.php` produce today. Pure PHP,
no OpenCart dependency — no live-install verification gap applies. Full
`tests/mintcart/` suite re-run end to end (35 files, was 34) — all
passing, no regressions.

**Next step:** wire `category.php`/`product.php`/`home.php` to call
`MetaBuilder` instead of their own direct pass-through (mechanical, fixes
the blank-title/description defect — add a `docs/fixes/registry.md`
entry for it as part of that increment, per `CLAUDE.md` §13), or pick a
remaining Phase 18 sub-piece — or re-check `gh pr list --state open`
first, since this backlog is large (47+ open PRs at the time of this run)
and moving fast.

**Update (2026-09-15, routine run): `MetaBuilder` wired into `category.php`
and `home.php` (`docs/fixes/registry.md` MC-0015).** Both controllers'
direct `setTitle()`/`setDescription()` pass-through now uses `MetaBuilder`.
Only a blank title falls back to the category's `name` / store's
`config_name`; a blank description stays blank (no fourth argument). Step 0 dedup check against the four open PRs at the time
(#110 Diagnostics into search/manufacturer/special.php, #111 Diagnostics
into product.php, #112 admin bulk-editor manufacturer field, #113
`RobotsBuilder` into `header.php`) found none touching `category.php` or
`home.php` — `product.php` was left unwired this run specifically because
#111 already had it open, per this file's own "avoid the same file as an
open PR" guidance. See `docs/upstream-diff.md`'s `category.php`/`home.php`
entries and `docs/developer/seo/meta-builder.md` for the full writeup.
Verified: `php -l` clean; full `tests/mintcart/` suite (77 files) re-run
end to end, all passing, no regressions — no new test file needed since
`meta-builder-test.php` scenarios 5/7 already cover these exact shapes.
Not verified: no live MySQL/Twig session in this environment (see
`docs/MANUAL_VERIFICATION.md`'s new Phase 18 section for the manual
steps). **Next candidates:** wire `product.php` the same way once #111
merges; wire `CanonicalUrlBuilder` into `category.php` (also uncontended);
or re-check `gh pr list --state open` first, since this backlog moves
fast.

**Update (2026-09-17, routine run): `MetaBuilder` wired into `product.php`
too (`docs/fixes/registry.md` MC-0016).** Step 0 dedup check: `gh pr list
--state open` returned zero open PRs at the start of this run (also
caught and fixed a stale local `master` git ref predating commit `498a481`
— reset to `origin/master` before branching, same trap a past run already
documented hitting). `product.php`'s
`setTitle($product_info['meta_title'])`/`setDescription($product_info['meta_description'])`
pass-through — left unwired above only because PR #111 (Diagnostics into
`product.php`) was open at the time — now routes through the same
`MetaBuilder::build()` call, falling back to `$product_info['name']`. No
fallback description (same as `category.php`/`home.php`). Full writeup:
`docs/fixes/registry.md` MC-0016, `docs/developer/seo/meta-builder.md`
("Live wiring"/"What's deliberately not done yet" — the `manufacturer.php`/
`search.php` non-wiring is now explained as "not the same bug", not a
deferred gap), `docs/upstream-diff.md`'s `product.php` entry. Verified:
`php -l` clean; full `tests/mintcart/` suite (77 files) re-run end to end,
all passing, no regressions — `meta-builder-test.php` scenario 6 already
covered this exact shape, no new test file needed. Not verified: same
no-live-install gap as MC-0015. **With this, Phase 18's meta
title/description sub-piece is done** for every entity that has a real
meta field to fall back from. **Next candidates:** wire
`CanonicalUrlBuilder` into the four listing controllers (now merged per
commit `498a481` — check `docs/upstream-diff.md`/this file's Phase 18
section further below for current status); wire `SchemaOrgBuilder`'s
`breadcrumbList()` into `manufacturer.php`/`special.php` (only
`category.php`/`search.php` have it so far); `HreflangBuilder` still needs
a per-language route resolver before it can be wired anywhere; System
route URL aliases; or re-check `gh pr list --state open` first.
      sitemap, Schema.org Product/Breadcrumb/Organization — **canonical** has a
      first increment this run (see below); several sibling sub-pieces
      (hreflang, robots, sitemap, Schema.org) have their own increments sitting
      in open, unmerged PRs as of this run — see each PR's own title, not
      reflected in this checklist yet since they haven't merged
- [ ] System route URL aliases (cart, checkout, search, contact, account, etc.)
      — also has an open, unmerged PR as of this run

**Update (this run): `MintCart\Seo\CanonicalUrlBuilder` — canonical/prev/next
pagination links.** Pure extraction of the canonical/prev/next link logic
that is byte-for-byte duplicated (same linked comment, same branching)
across `catalog/controller/product/{category,manufacturer,search,special}.php`.
Full writeup: `docs/developer/seo/canonical-url-builder.md`. No controller
wiring yet — a separate, mechanical follow-up (swap each controller's inline
block for a call into this class, no behavior change) once this primitive is
reviewed, same primitive-first pattern `PackageManifest`/
`CategoryMoveValidator` already used (`CLAUDE.md` §85). This run's step-0
duplicate-work check found `MintCart\Seo\HreflangBuilder`, `RobotsBuilder`,
`SitemapBuilder` and a Schema.org increment already claimed by open PRs
(none of them named "canonical" or touched `catalog/controller/product/*`),
plus the System route URL aliases item — so canonical was the one Phase 18
sub-piece still genuinely unclaimed.

Verified: `php -l` clean. `tests/mintcart/seo/canonical-url-builder-test.php`
(8 scenarios) — scenarios 1-5 replay the exact page/limit/total/base-query
shapes the four controllers already produce today, confirming this is a
faithful extraction, not a behavior change; scenarios 6-8 cover `limit = 0`,
zero total results, and defensive input clamping. Pure PHP, no OpenCart
dependency — no live-install verification gap applies (same as
`PackageManifest`).

**Next step:** wire the four controllers to call `CanonicalUrlBuilder`
instead of their own inline block (mechanical, no behavior change — do this
once reviewed), or pick the remaining Phase 18 sub-pieces this run left
untouched (meta title/description has no `MetaBuilder` and no open PR as of
this run) — or re-check `gh pr list --state open` first, since this backlog
was large (30+ open PRs) and moving fast at the time of this run.

**Update (2026-09-15, routine run): `CanonicalUrlBuilder` live wiring —
`category.php`/`manufacturer.php`/`search.php`/`special.php` all call it
now.** This is the mechanical follow-up the "Next step" note right above
had been waiting for. Each controller's inline
`http://googlewebmastercentral.blogspot.com/2011/09/pagination-with-relnext-and-relprev.html`
block is replaced with `CanonicalUrlBuilder::build($page, $limit,
$product_total, $baseQuery)`, wrapped in the same `try`/`catch (\Throwable)`
+ `MintCart\Core\Logger` pattern the existing `SchemaOrgBuilder` call in
the same files already uses; each controller keeps building its own
non-pagination `$baseQuery` exactly as before (`'path=' . $category_id`,
`'manufacturer_id=' . ...`, search's already-fixed MC-0002 `$canonical_url`
filter set, or `''` + `secure = true` for special). Like `RobotsBuilder`'s
wiring in `common/header.php`, this runs unconditionally regardless of
active theme — canonical/prev/next links are a per-route SEO signal, not a
Mint component DOM contract.

Step 0 dedup check: `mcp__github__list_pull_requests` (all open, not
filtered by branch-name pattern) returned 6 open PRs (#110-#115) covering
Diagnostics wiring into search/manufacturer/special/product.php (#110,
#111), a Bulk Editor manufacturer_id field (#112), `RobotsBuilder` wiring
into `header.php` (#113), `MetaBuilder` wiring into `category.php`/
`home.php` (#114), and `McEntityPicker` wiring into `product_form.twig`'s
Manufacturer field (#115). None touch `CanonicalUrlBuilder` or its four
consumer controllers' canonical/prev/next blocks specifically — #114 does
touch `category.php`, but only its meta-title/description fallback near
the top of `index()`, a different concern and a different insertion point
than this change's pagination-link block further down the same method (the
existing `SchemaOrgBuilder` breadcrumb block already sits between them
without conflict). Hot shared files (§0f): none of this increment's files
are Homepage Builder block-registry files or `block.css`.

Verified: `php -l` clean on all four controllers. Full `tests/mintcart/`
suite (78 files) re-run end to end — all passing, no regressions (the
pre-existing `CanonicalUrlBuilder` unit test already covers the arithmetic
itself; this run only threads its output into the four real call sites, a
behavior-preserving refactor by construction). Not verified: no live
MySQL/browser session in this environment — see
`docs/MANUAL_VERIFICATION.md`'s new Phase 18 "`CanonicalUrlBuilder` live
wiring" entry for the full manual script a human should run before this
reaches production.

**Next step:** meta title/description (`MetaBuilder`) still needs wiring
into `product.php` specifically (per #114/#113's own notes, `category.php`/
`home.php` are already claimed there); `HreflangBuilder`/`SitemapBuilder`
still have no live consumer; or re-check `gh pr list --state open` first,
since this backlog moves fast.
- [ ] SEO URL, canonical, meta title/description, XML sitemap, Schema.org
      Product/Breadcrumb/Organization
- [x] robots controls — `MintCart\Seo\RobotsBuilder`, now live-wired into
      `ControllerCommonHeader`/`mint_default/template/common/header.twig`
      (2026-09-15 routine run) — see below
- [ ] SEO URL, canonical, meta title/description, robots controls, XML
      sitemap, Schema.org Product/Breadcrumb/Organization
- [x] System route URL aliases (cart, checkout, search, contact, account, etc.)
      — `MintCart\Seo\SystemRoutes` (the fixed whitelist of twelve routes) +
      `ControllerMintcartSeoSystemPages` (`Design → System Pages`, a
      facade over the stock `seo_url` table, not a second SEO storage) +
      a small additive `ControllerStartupSeoUrl::rewrite()` core change so
      a *generated* link also comes out as the saved alias (incoming
      request decode already worked with zero core changes). Full
      writeup: `docs/developer/seo/system-route-aliases.md`,
      `docs/upstream-diff.md`'s `startup/seo_url.php` entry. Test:
      `tests/mintcart/seo/system-routes-test.php` (8 scenarios). Known
      limitation: scoped to the main store + storefront default language
      only, no multi-store/multi-language variant yet.

**Update (this run):** started this phase with System route URL aliases —
at the time this run began, PR #62 (also open, unmerged) had already
started this same phase from a different angle (`MintCart\Seo\SchemaOrgBuilder`
+ Organization/BreadcrumbList JSON-LD), confirmed via its own diff to
touch none of the files this run touches (`seo_url.php`,
`SystemRoutes`, `mintcart/seo/system_pages.*`, `column_left.php`'s
Design menu block vs. its own `header.php`/`category.php`/`search.php`
changes) — a human will reconcile both onto `master` once merged; this
file's checklist above reflects only this run's own increment, not #62's
still-unmerged one. Rest of the phase (hreflang/robots, canonical/sitemap
centralization beyond what #62 may add, Schema.org Product once a
`mint_default` product-detail page exists) remains unclaimed.
- [~] hreflang — `MintCart\Seo\HreflangBuilder` (pure contract + tests only,
      no live wiring yet) — see below
- [x] SEO URL, canonical — already stock OpenCart (`ControllerSeoUrl`,
      `document->addLink(..., 'canonical')`, present since before MintCart;
      MC-0002 in Phase 10's Fix Registry entry fixed `search.php`'s missing
      canonical/prev/next, see below)
- [ ] hreflang, robots controls — not attempted
- [~] XML sitemap — already stock OpenCart
      (`catalog/controller/extension/feed/google_sitemap.php`, an
      installable dashboard/feed extension, same mechanism the Phase 14
      dashboard widget (PR #59) uses for its own tile) — works today via the
      standard **Extensions → Feeds** flow; not centralized into a MintCart
      "SEO" admin section yet, no new backend needed per se (`CLAUDE.md`
      §39: don't duplicate what OpenCart already has)
- [x] **Schema.org: Product, BreadcrumbList, Organization** — see below.
      Product is built + unit-tested; BreadcrumbList and Organization are
      also live-wired.
- [ ] System route URL aliases (cart, checkout, search, contact, account, etc.)
      (`MINTCART_FUNCTIONAL_SCOPE.md` §4) — not attempted

Not started on `master` yet, but multiple sub-items are in flight as open,
unmerged PRs: `MintCart\Seo\SchemaOrgBuilder` (#62), system route URL
aliases (#66), `MintCart\Seo\HreflangBuilder` (#68), `MintCart\Seo\RobotsBuilder`
(#70), and `MintCart\Seo\SitemapBuilder` (#71, this run) — see
`docs/developer/seo/sitemap.md` for the full writeup, including why
canonical and meta title/description turned out to already be covered by
stock OpenCart (nothing left to add there). A human reconciling this
backlog should check which of #62/#66/#68/#70/#71 have landed before
picking the next Phase 18 increment; none of the five touch the same
existing file (each ships a new, distinct file under
`upload/system/library/mintcart/seo/`), so they should merge cleanly in
any order. Live route/controller wiring for the sitemap (querying
product/category/manufacturer/information models) is not started and
needs a real MySQL install to verify — see `docs/developer/seo/sitemap.md`'s
"Not done yet" section for a concrete checklist.
**In progress.** `MintCart\Seo\RobotsBuilder`
(`upload/system/library/mintcart/seo/robotsbuilder.php`) decides the
per-page `<meta name="robots" content="...">` directive: `noindex, follow`
for the checkout flow and every account-area page (a fixed whitelist
checked against the real controllers in this repo), `noindex, follow` for
an empty `product/search` result, `index, follow` for everything else. No
OpenCart dependency, unit-tested in isolation
(`tests/mintcart/seo/robots-builder-test.php`, 74 assertions). Full
writeup, including why there's no live controller wiring yet: `docs/developer/seo/robots.md`.
Deliberately does not touch `ControllerCommonHeader`/`header.twig` (the
natural wiring point) since those files are already being edited by an
in-flight PR adding Schema.org JSON-LD — this increment stays
additive-only (three new files under a new `mintcart/seo/` directory, zero
existing files touched) to avoid a same-file collision, same reasoning an
earlier `HreflangBuilder` increment already used for itself. Verified:
`php -l` clean; full `tests/mintcart/**/*-test.php` suite re-run end to
end (34 files), all passing, no regressions.

At the time this increment started, the 17 open pull requests already
covered: all five remaining Phase 12 entity-picker backends, the Setup
Wizard Contacts and Language & currency steps, Business
Contacts/Delivery/Payments facades and a Store Readiness dashboard widget,
`mc-search-box`, Bulk Editor, Phase 1 Health checks, a release-packaging
script, and — within Phase 18 itself — Schema.org JSON-LD, system route
URL aliases, and hreflang (each deliberately avoiding the others' files,
same pattern this increment follows). Robots controls was the one
remaining, genuinely unclaimed Phase 18 sub-item.

**Next step:** wire `RobotsBuilder` into `ControllerCommonHeader`/
`mint_default/template/common/header.twig` once the header-chrome
contention above clears (check `gh pr list --state open` first); meta
title/description and canonical/XML-sitemap centralization remain
unclaimed too, or re-check open PRs for what else has landed.
**In progress.** `MintCart\Seo\HreflangBuilder`
(`upload/system/library/mintcart/seo/hreflangbuilder.php`) assembles a
page's `rel="alternate" hreflang="..."` link set from caller-supplied
`{hreflang, href}` pairs — validates, de-duplicates, and synthesizes an
`x-default` entry when a default language is given. No OpenCart
dependency, unit-tested in isolation (`tests/mintcart/seo/hreflang-builder-test.php`,
10 scenarios). Full writeup, including why there's no live controller
wiring yet: `docs/developer/seo/hreflang.md`. Deliberately does not touch
`ControllerCommonHeader`/`header.twig` (the natural wiring point) since
those files are already being edited by an in-flight PR adding Schema.org
JSON-LD — this increment stays additive-only (three new files, zero
existing files touched) to avoid a same-file collision. Verified: `php -l`
clean; full `tests/mintcart/**/*-test.php` suite re-run end to end, all
passing, no regressions.

**Next step:** a per-language route/URL resolver (needed before
`HreflangBuilder` can be wired anywhere — see the doc's "Why no live
wiring yet"), then wiring it + `HreflangBuilder` into
`ControllerCommonHeader`; or the still-unclaimed robots-controls item; or
re-check `gh pr list --state open` for what else has landed/opened since.
**This run: `MintCart\Seo\SchemaOrgBuilder`.** Pure, OpenCart-independent
builder (`upload/system/library/mintcart/seo/schemaorgbuilder.php`) for the
three Schema.org types the scope doc names explicitly, same shape as
`ProductViewProvider`/`SearchService`. Full design writeup:
`docs/developer/seo/schema-org.md`.

- **Organization** — live-wired into `ControllerCommonHeader::index()`,
  inside the existing `isMintAware()` gate; renders as one
  `<script type="application/ld+json">` in
  `mint_default/template/common/header.twig`'s `<head>`, on every
  Mint-aware page (header is common to all of them). Reuses the same config
  values `HeaderViewProvider`/`FooterViewProvider` already read
  (`config_name`/`config_logo`/`config_telephone`/`config_address`) — no
  new OpenCart storage.
- **BreadcrumbList** — live-wired into `ControllerProductCategory::index()`
  and `ControllerProductSearch::index()`, at the end of each method once
  `$data['breadcrumbs']` is fully built (that array grows incrementally
  through both methods); renders as a second `<script type="application/ld+json">`
  block in `category.twig`/`search.twig`, right after `{{ header }}`.
  Manufacturer/Special listing pages are not wired yet (natural next
  candidates, same pattern).
- **Product** — `SchemaOrgBuilder::product()` is implemented and
  unit-tested but has **no live consumer yet**: `mint_default` has no
  `template/product/product.twig` at all (Phase 6/8 wired the *listing*
  pages, never the product detail page itself). Wiring it needs that page
  template to exist first — out of scope for this run, see "Next step"
  below and `docs/developer/seo/schema-org.md`'s "Product: built, not
  wired" section for the full rationale.

Both live wiring points reuse the exact same `ActiveTheme::isMintAware()`
gate every other Mint-only controller addition uses (no cost/behavior
change on the legacy `default` theme or a third-party theme), and both
re-check it fresh rather than reusing `$mint_render_product_cards` (which
can be flipped to `false` mid-request by an unrelated per-product
`ProductCardListRenderer` failure — a broken product-card must not also
silently suppress a valid breadcrumb schema). A `SchemaOrgBuilder` failure
is caught and logged via `MintCart\Core\Logger`, never fatals the page —
same pattern every other `ComponentRenderer`/provider call site uses.

Verified: `php -l` clean on every touched/added PHP file, re-swept across
the full `upload`/`tests` tree; new
`tests/mintcart/seo/schema-org-builder-test.php` (16 scenarios — required-key
validation, optional-field omission, price/currency/availability shaping,
`false`-price and zero-review-count edge cases, `</script>`-breakout
prevention in `toJsonLd()`); full `tests/mintcart/**/*-test.php` suite
re-run end to end (34 files) — all passing, no regressions. Twig
`{% if %}`/`{% endif %}`/`{% for %}`/`{% endfor %}` tag counts checked
balanced on all three touched `.twig` files (same script-based technique
used for every earlier `.twig` file in this repo). No JS/CSS touched, so
`npm run build` was not re-run this session. **Not verified:** no live
MySQL/`vendor/twig/twig` session in this environment — same pre-existing
gap as every earlier phase; a human should load a real `mint_default` page
and validate both JSON-LD blocks with Google's Rich Results Test — see the
new Phase 18 entry in `docs/MANUAL_VERIFICATION.md` for the exact steps.

**In progress** — hreflang/robots, System route URL aliases, sitemap
centralization, and Manufacturer/Special breadcrumb wiring are all
unclaimed next steps; Product schema awaits its page template.

**Update (2026-09-17, routine run): `MintCart\Seo\SitemapBuilder` — first
live consumer wired (Google Sitemap extension).** Step 0 dedup check:
`list_pull_requests` (all open, no branch-name filter) returned exactly 4
open PRs at the start of this run — `#117`/`#118`/`#119` (Phase 18
SEO wiring into `product.php`/`manufacturer.php`/`special.php`) and
`#120` (Phase 16 Bulk Editor `stock_status_id` field), all marked
`ROUTINE-STATUS: DONE`. None touch `SitemapBuilder`,
`google_sitemap.php`, or any of the known hot shared files (Homepage
Builder block-type registry, `homepage_list.twig`, `block.css`,
wizard files, `mintcart/entity.php`, `column_left.php`) — confirmed via
each PR's own file list. This run picked the "Not done yet" item
`docs/developer/seo/sitemap.md` and prior "Next candidates" notes both
named — the last unwired `MintCart\Seo` primitive — but instead of the
brand-new `mintcart/seo/sitemap.xml` route those notes originally
proposed (still blocked on no-MySQL verification for a *new* flow, per
`CLAUDE.md` §10), found a lower-risk real consumer: the stock OpenCart
"Google Sitemap" extension already builds working sitemap XML by hand.
`SitemapBuilder::urlset()` gained an optional per-entry `images` list
(Google Image sitemap support, `xmlns:image` only emitted when used) and
`ControllerExtensionFeedGoogleSitemap::index()`/`getCategories()` now
build entries arrays and call the builder instead of concatenating XML
strings. Full writeup: `docs/upstream-diff.md`'s new
`google_sitemap.php` entry, `docs/developer/seo/sitemap.md`'s "Live
wiring" section, `docs/MANUAL_VERIFICATION.md`'s new Phase 18 "Google
Sitemap" section.

**Verified:** `php -l` clean on `sitemapbuilder.php` and
`google_sitemap.php`. `tests/mintcart/seo/sitemap-builder-test.php` grows
from 15 to 20 scenarios (the new `images` parameter: no-images stays
byte-identical, one image renders `xmlns:image` + full `<image:image>`
block, optional caption/title omitted when blank, blank/duplicate image
`loc` skipped/de-duplicated, `&` escaped in both `image:loc` and
`image:caption`). Full `tests/mintcart/**/*-test.php` suite (77 files)
re-run end to end — all passing, no regressions. **Not verified:** no
live MySQL/OpenCart bootstrap in this environment — a human should
enable `feed_google_sitemap_status` on a real install and diff the
feed's output before/after (see `docs/MANUAL_VERIFICATION.md`).

**Next candidates:** a first-class, always-on MintCart-owned
`mintcart/seo/sitemap.xml` route honoring `Design → System Pages`
aliases and `sitemapIndex()` pagination for large catalogs (the Google
Sitemap extension covers the common case today but stays a separate,
optional, single-file extension); `HreflangBuilder` still needs a
per-language route resolver before it can be wired anywhere; or re-check
`gh pr list --state open` first, since `#117`-`#120` may have merged by
the time a future run starts. Note: the Phase 18 checklist item "System
route URL aliases" above (marked `[ ]`) is stale — it shipped as `#66`
(`MintCart\Seo\SystemRoutes` + `Design → System Pages`) and is already
merged to `master`; left as-is here per this file's own guidance not to
rewrite the shared trailer/checklist wholesale in a single run.
**Update (2026-09-17, routine run): `SchemaOrgBuilder::product()` live
wiring — `product.php`/`product.twig`.** `mint_default/template/product/
product.twig` now exists (confirmed on disk this run), unblocking the one
remaining `SchemaOrgBuilder` method with no live consumer. Wired right
before `$mint_diagnostics->mark('view_rendered')` in `index()`, same
`ActiveTheme::isMintAware()` re-check + try/catch + `Logger` pattern as
`category.php`/`search.php`'s `BreadcrumbList` wiring. `price` is computed
fresh from `$product_info['price']`/`['special']` (not reused from the
method's `$tax_price` local, which by that point has been reassigned
inside the related-products loop to the last related product's price).
Full writeup: `docs/upstream-diff.md`'s `product.php` entry and
`docs/developer/seo/schema-org.md`'s "Product: live-wired" section.

Step 0 dedup check: `mcp__github__list_pull_requests` (all open, not
filtered by branch name) returned exactly two open PRs — #117 (`MetaBuilder`
into `product.php`, `DONE`) and #118 (`SchemaOrgBuilder::breadcrumbList()`
into `manufacturer.php`/`special.php`, `DONE`). Neither touches
`product.twig` or adds `SchemaOrgBuilder::product()` wiring; #117 only
touches `product.php`'s meta-title/description block near the top of
`index()`, a different insertion point than this change's block near the
bottom (right before `view_rendered`). Hot shared files (§0f): this
increment's files (`product.php`, `product.twig`, three doc files) are none
of the known hot files (Homepage Builder block registry, `block.css`,
`wizard.php`, `entity.php`, `column_left.php`) — no contention, and only
two other PRs were open in total, well under any collision threshold.

Verified: `php -l` clean. `product.twig`'s Twig `if`/`endif`/`for`/`endfor`
tag counts checked balanced (30/30, 11/11). Full `tests/mintcart/**/*-test.php`
suite (77 files) re-run end to end — all passing, no regressions (no new
test file needed — `SchemaOrgBuilder::product()` itself is already covered
by `tests/mintcart/seo/schema-org-builder-test.php`'s existing 16
scenarios; this run is wiring-only). **Not verified:** no live MySQL/Twig
session in this sandboxed environment — see `docs/MANUAL_VERIFICATION.md`'s
updated Phase 18 Schema.org section (steps 5-6) for the manual script a
human should run.

**Next step:** manufacturer.php/special.php BreadcrumbList wiring is
already covered by open PR #118 — do not duplicate. System route URL
aliases and robots controls are already `[x]` done (see this section's own
checklist above). Remaining unclaimed Phase 18 sub-pieces: `HreflangBuilder`
needs a per-language route resolver before it can be wired anywhere;
sitemap centralization into a MintCart "SEO" admin section (re-check
`gh pr list --state open` first before starting either).
**Update (2026-09-17, routine run): `SchemaOrgBuilder` BreadcrumbList
wired into `manufacturer.php`'s `info()` and `special.php`'s `index()`** —
the two remaining listing controllers flagged above. Same
`ActiveTheme::isMintAware()` fresh re-check, `try`/`catch (\Throwable)` +
`Logger` wrapping, and `mc_schema_breadcrumb` Twig guard as the existing
`category.php`/`search.php` wiring (`manufacturer_info.twig`/
`special.twig` get the identical `{% if mc_schema_breadcrumb %}` block
right after `{{ header }}`). Step 0 dedup check: the only open PR at the
start of this run was #117 (`MetaBuilder` into `product.php`, unrelated
file) — this increment's own body had flagged this exact item as its
recommended next step. See `docs/upstream-diff.md`'s `manufacturer.php`/
`special.php` entries and `docs/developer/seo/schema-org.md` (updated)
for the full writeup. Verified: `php -l` clean; Twig tag balance checked
on both templates; full `tests/mintcart/**/*-test.php` suite (77 files)
re-run end to end — all passing, no regressions (no new test file needed,
same reasoning `category.php`/`search.php`'s own wiring used —
`breadcrumbList()` itself is already covered by
`schema-org-builder-test.php`). Not verified: no live MySQL/Twig session
in this environment — a human should load a real manufacturer and
specials page on a `mint_default` install and confirm the `BreadcrumbList`
JSON-LD block renders (see `docs/MANUAL_VERIFICATION.md`'s Phase 18
section). **Next step:** Product schema still awaits a `mint_default`
product detail page consumer (already exists as of Phase 6/8 — worth
re-checking whether `SchemaOrgBuilder::product()` can now be wired into
`product.php`); hreflang still needs its per-language route resolver;
robots/sitemap centralization and System route URL aliases remain
unclaimed; or re-check `gh pr list --state open` first.

**Update (2026-09-30, routine run): `mintcart/seo/sitemap` — always-on
XML sitemap route, no `Extensions > Feeds > Google Sitemap` install
required.** Closes the "genuinely first-class MintCart-owned
`mintcart/seo/sitemap.xml` route" gap `MintCart\Seo\SitemapBuilder`'s own
docblock and `docs/developer/seo/sitemap.md`'s "Not done yet" section had
both named as the real remaining piece — the stock
`ControllerExtensionFeedGoogleSitemap` only works when a merchant finds
and enables that extension and its `feed_google_sitemap_status` setting,
exactly the kind of technical decision `MINTCART_PRODUCT_VISION.md` §5
says a shop owner should not have to make. New
`ControllerMintcartSeoSitemap` (`upload/catalog/controller/mintcart/seo/sitemap.php`,
route `mintcart/seo/sitemap`) reuses the exact same
`model_catalog_product`/`model_catalog_category`/
`model_catalog_manufacturer`/`model_catalog_information` enumeration and
`SitemapBuilder::urlset()` XML assembly the stock extension already uses
— same entries, same escaping, always on, no admin extension install.
Step 0 found 21 open PRs (20 `DONE`, one `IN-PROGRESS` — #148, unrelated
`product_form.twig` Option picker wiring); none touch any `MintCart\Seo\*`
file, `mintcart/seo/*`, or the Homepage Builder hot files (§0f — block-type
registry, `block.css`). Full writeup: `docs/developer/seo/sitemap.md`
(updated "Not done yet" section). Verified: `php -l` clean on the new
file and the full `upload`/`tests` tree; full `tests/mintcart/**/*-test.php`
suite re-run end to end (84 files) — all passing, no regressions (no new
test file needed — this is a live-DB controller wiring a pure,
already-tested `SitemapBuilder`, same reasoning every other Phase 18
controller-wiring increment in this section used). Not verified: no live
MySQL/Twig session in this environment — a human should confirm on a real
install that `index.php?route=mintcart/seo/sitemap` returns a well-formed
`<urlset>` XML document listing every enabled product/category/
manufacturer/information page (see `docs/MANUAL_VERIFICATION.md`'s Phase
18 section, new "MintCart sitemap route" entry). **Deliberately not done**
(unchanged from `docs/developer/seo/sitemap.md`'s own list, just now
scoped to what remains): `sitemapIndex()`-based paging once a catalog
exceeds `SitemapBuilder::MAX_URLS_PER_SITEMAP` (`urlset()` already
truncates safely, same as the stock extension does today — no
regression, just no multi-file split yet); self-registering a `Sitemap:`
line in `robots.txt` (OpenCart ships no `robots.txt` management at all
today, a separate, larger feature); per-language `hreflang` alternate
links (`HreflangBuilder` still has no consumer). **Next candidates:**
`robots.txt` management/self-registration; `sitemapIndex()` paging for
large catalogs; hreflang's per-language route resolver; System route URL
aliases (still the one remaining `[ ]` item in this section's own
checklist above); or re-check `gh pr list --state open` first, since this
backlog moves fast.

## Modern Editor (Functional Scope §24, P2/RESEARCH)

- [x] Research step: verify whether the rich-text editor needs replacing —
      resolved via `docs/adr/007-modern-editor-decision.md`. **Decision:
      keep Summernote 0.8.18** (OpenCart 3.0.5.1's stock editor, admin-only,
      already covers multilingual/image integration per the ADR's checklist
      against Functional Scope §24's own requirements). No new `Mc*` editor
      component built — no real gap identified, per `CLAUDE.md` §85. Three
      concrete revisit conditions are documented in the ADR rather than
      leaving this open-ended.

Resolved. No further action unless one of the ADR's revisit conditions is
met.

## Phase 9 (Technical Plan §42 numbering) — Productivity

*(Numbering note: Technical Plan §42 lists Bulk Editor / category drag-and-drop /
admin search under "Phase 9 — Productivity" as a plan-level grouping, while
Implementation Guide §51–§64 numbers them as Phases 16–17. This file follows the
Implementation Guide's per-feature phase numbers above; nothing is duplicated,
tracked once each.)*

## Phase 10 (Technical Plan) — SEO / Fixes / Performance

- [ ] SEO Core (tracked above as Phase 18)
- [x] `docs/fixes/registry.md` Fix Registry established, entries added only after
      reproduction on the MintCart baseline (per `CLAUDE.md` §13) — MC-0001
      (`category.php` blank language strings), MC-0002 (`search.php`
      missing canonical/prev/next links) on `master`; MC-0012
      (`ModelSettingSetting::editSetting()` not transactional, this run) —
      see below. MC-0004 through MC-0011 exist as open, unmerged PRs at the
      time of this run (`docs/fixes/registry.md`'s own numbering gap
      reflects that, same as MC-0007's own note when it was added).
      missing canonical/prev/next links), and MC-0011 (this run —
      `DB\MySQLi::query()` never recovered from a dropped connection,
      `upload/system/library/db/mysqli.php` +
      `tests/mintcart/fixes/mc-0011-mysqli-reconnect-test.php`)
      missing canonical/prev/next links), MC-0003 (`manufacturer.php` blank
      language strings) merged to `master`; MC-0004 through MC-0008 sit in
      their own still-open, unmerged PRs (see each PR's title) as of this
      run; MC-0009 (`seo_url.php` incoming decode ignores `language_id`,
      can resolve the wrong entity on a multi-language store — see
      `docs/fixes/registry.md` and `docs/upstream-diff.md` for the full
      writeup) is this run's own increment
      language strings), and MC-0008 (`Cart::__construct()` unconditional
      per-request cleanup query, this run — see below); MC-0004 through
      MC-0007 are open PRs, not yet landed on this branch
      missing canonical/prev/next links) on `master`; MC-0007 (`Cache\File`
      atomic write / concurrent-read fatal, this run) — see below. MC-0004
      through MC-0006 exist as open, unmerged PRs at the time of this run
      (`docs/fixes/registry.md`'s own numbering gap reflects that — see
      that file for detail).
      missing canonical/prev/next links), MC-0003 (`manufacturer.php` blank
      language strings) on `master`; MC-0006 (this run — `DB::connected()`
      called a method (`connected()`) no adaptor implements, all three
      implement `isConnected()`) in `upload/system/library/db.php` +
      `tests/mintcart/fixes/mc-0006-db-connected-adaptor-mismatch-test.php`.
      **Numbering note:** this run started from `master` (MC-0001–0003
      only); two other open PRs at the time (#76 "MC-0004", #78 "MC-0005")
      each independently claim the next ID from their own branch's view of
      `master` — this run deliberately used **MC-0006** to avoid a third
      collision on MC-0004, but registry entries will still need
      human/reconciliation renumbering once all three merge (whichever
      lands first keeps its number; the other two get renumbered on top of
      it, same pattern already used for other parallel-PR series in this
      file, e.g. the Homepage Builder block PRs).
      (`category.php` blank language strings), MC-0002 (`search.php` missing
      canonical/prev/next links), MC-0003 (`manufacturer.php` blank language
      strings), and MC-0005 (install-time PHP version pre-flight checks
      stale/fragile, this run — see below). MC-0004 (`tool/backup` restore
      safety-stop hardcoded byte-length) is a real entry too but lives in
      still-open PR #76 as of this run, not yet on this branch's base.
      (`category.php` blank language strings) and MC-0002 (`search.php`
      missing canonical/prev/next links, this run)
- [~] Diagnostics dev mode (query count, DB time, render time, cache status) —
      collection + `ComponentRenderer` wiring + HTML-comment surface +
      admin toggle (`System → Diagnostics`) all land on `master` as of
      2026-09-18; only real DB query counting remains open (see the Phase
      10/Phase 19 sections above and `docs/developer/core/diagnostics.md`)
- [x] Cache invalidation API — `MintCart\Core\Cache\CacheInvalidator`
      (`upload/system/library/mintcart/core/cache/cacheinvalidator.php`), a
      domain → cache-key-prefix registry (`catalog`/`localization`/
      `checkout`/`content`/`store`) on top of OpenCart's stock `\Cache`
      class, injected as a callable so it needs no `\Registry`/adapter
      bootstrap to unit-test. No live write-path caller yet (`CLAUDE.md`
      §85 — ships ahead of its first consumer, same as several other
      Phase 1/11/18 primitives in this file); a bulk product/category edit
      is the natural first one, once Phase 16/17's write paths land on
      `master`. Verified, code-inspected (not assumed) backend caveat:
      OpenCart's own `\Cache::delete($key)` only does prefix/wildcard
      matching on the `file`/`apc`/`apcu` adapters — on `mem`/`memcached`/
      `redis` it only deletes an exact key, so `delete('*')` (what stock
      `admin/controller/tool/backup.php`'s own "clear cache" step calls)
      is a silent no-op there too. Documented, not fixed this run (touching
      `tool/backup.php` would collide with an in-flight PR fixing an
      unrelated bug in that same file). Full writeup:
      `docs/developer/core/cache-invalidation.md`. Test:
      `tests/mintcart/core/cache-invalidator-test.php` (8 scenarios).
      (`category.php` blank language strings), MC-0002 (`search.php`
      missing canonical/prev/next links), MC-0003 (`manufacturer.php` blank
      language strings), and MC-0004 (`tool/backup`'s user/user_group
      restore safety-stop hardcoded a byte-length instead of deriving it
      from `DB_PREFIX`, this run — see below)
- [~] Diagnostics dev mode (query count, DB time, render time, cache status) —
      see the entry above; admin toggle added 2026-09-18
- [~] Measured performance improvements (lazy loading, responsive images, asset
      minification, cache invalidation API — no mandatory Redis)

Fix Registry mechanism is established. **This run adds MC-0013** —
`system/library/mail/smtp.php`'s `EHLO`/`HELO` commands used raw
`getenv('SERVER_NAME')`, empty outside a real web request (CLI/cron),
producing a malformed command several real SMTP servers reject outright.
Fixed via a new, pure `MintCart\Mail\HeloHostname::resolve()`
(`upload/system/library/mintcart/mail/helohostname.php`), with a 3-scenario
standalone test (`tests/mintcart/mail/helo-hostname-test.php`). Full
writeup: `docs/fixes/registry.md`'s MC-0013 entry and
`docs/upstream-diff.md`'s new `smtp.php` entry. Verified: `php -l` clean on
both touched/added PHP files; full `tests/mintcart/` suite re-run end to
end (35 files, was 34) — all pass, no regressions. No live SMTP-server
verification possible in this environment (no outbound SMTP access) — see
`docs/MANUAL_VERIFICATION.md`'s new MC-0013 entry for what a human should
confirm. The rest of this phase (diagnostics, measured performance work) is
not started — see the "Next step" trailer below for the current PR-backlog
snapshot before picking either.
Fix Registry mechanism is established. This run added **MC-0012**:
`upload/admin/model/setting/setting.php`'s `editSetting()` — the single
save path for every OpenCart settings screen and extension config form
(117 call sites) — deleted a `code` group's rows and reinserted them with
no transaction around either step; a mid-loop failure (`DB\MySQLi::query()`
throws on any SQL error, e.g. a dropped connection, MC-0011's scenario)
left that group's settings partially deleted with no rollback. Fixed with
`START TRANSACTION`/`COMMIT`/`ROLLBACK`, the same raw-SQL transaction
pattern `MintCart\Core\Migration\MigrationManager` and Phase 17's
`moveCategory()` already established. Reproduced and verified with a real
unit test against the actual `ModelSettingSetting` class driven by a
transaction-aware in-memory DB double
(`tests/mintcart/fixes/mc-0012-setting-transaction-test.php`, 4 scenarios),
confirmed non-vacuous by re-running the same test against the pre-fix
method (it reliably reproduces the exact data loss the fix removes). Full
writeup: `docs/fixes/registry.md`'s MC-0012 entry and
`docs/upstream-diff.md`'s `admin/model/setting/setting.php` entry.
Verified: `php -l` clean across the whole `upload`/`tests` tree; full
`tests/mintcart/**/*-test.php` suite re-run end to end (35 files, was 34)
— all passing, no regressions. Diagnostics dev mode and the rest of
"measured performance improvements" remain not started on `master` (open
PRs cover pieces of this phase — re-check `gh pr list --state open` before
picking up more of it).

Step 0/0f dedup check for this run: 48 open PRs (`#52`–`#98`) were listed
at the start of this run. None touch `upload/admin/model/setting/setting.php`
or any `MintCart\*` transaction-wrapping class. Several hot shared files
were identified and avoided: `wizard.php`/`wizardprogress.php`/
`wizard.twig`/the wizard language file (contended by 3 open PRs —
#52/#64/#84), `upload/catalog/controller/product/category.php` and
`search.php` (contended by 2 — #62/#83), `admin/controller/mintcart/entity.php`
and `column_left.php` (contended by 2+, per PR #83's own write-up). All
five remaining Entity Picker backends, all SEO Core sub-pieces, Business
Settings façades, Bulk Editor, Diagnostics, `HealthCheck`, Compatibility
Checker, release packaging, Update Manager's first increments, and
MC-0004 through MC-0011 were already covered by other open PRs (see each
PR's own title) — the "installer edge cases"/reliability family of the
Known Fix Research Queue (`MINTCART_FUNCTIONAL_SCOPE.md` §18) was
genuinely unclaimed and led to this fix.
Fix Registry mechanism is established and has three real entries on this
branch (MC-0001, MC-0002, MC-0011 — several open PRs at the time of this
run independently claim MC-0003 through MC-0010, unconfirmed merged yet);
the rest of this phase (diagnostics, measured performance work) is not
started here, though open PRs cover parts of it (see this run's "Update"
note below).

**Update (this run): MC-0011 fix registry entry.** `DB\MySQLi::query()`
(`upload/system/library/db/mysqli.php`) threw immediately on any mysqli
error, including errno 2006/2013 (dropped TCP connection — MySQL
`wait_timeout`, server restart), instead of reconnecting and retrying the
same query once — the "database connection / reconnect issues" Known Fix
Research Queue item (`MINTCART_FUNCTIONAL_SCOPE.md` §18), directly
relevant to MintCart's own longer-running `MigrationManager`/Update
Manager operations. Step 0 checked all 45 open PRs at the time (`#52`–
`#97`): none touch `system/library/db/mysqli.php` or any
`system/library/db*` file (PR #79's MC-0006 touches `system/library/db.php`
only — a different file, the `DB` facade, not the `MySQLi` adaptor). See
`docs/fixes/registry.md`'s MC-0011 entry and `docs/upstream-diff.md`'s new
`mysqli.php` entry for the full writeup, including the deliberate ID
choice (MC-0011, not MC-0004) to avoid colliding with PRs #76/#78/#79/#89/
#92/#93/#97's own in-flight MC-0004–MC-0010 registry entries — the
registry file itself will need human/reconciliation renumbering once all
of these merge (same numbering-collision note PR #79 already left for
MC-0004–0006). Verified: `php -l` clean; new 5-scenario test (a
`DB\TestableMySQLi` subclass overriding the now-`protected` `connect()`
with a fake connection, no live MySQL needed); full `tests/mintcart/`
suite re-run end to end (36 files, was 35) — all passing, no regressions.
**Not verified:** no live MySQL server in this environment to force a real
disconnect — see the registry entry's "Not verified" note for what a human
should confirm.
Fix Registry mechanism is established and has four real entries on this
branch (MC-0001/0002/0003/0008); diagnostics and most of "measured
performance improvements" are not started (see the "product-card image
width/height" note elsewhere in this file for the one exception).

**Update (this run, 2026-09-09): MC-0008.** `\Cart\Cart::__construct()`
(`upload/system/library/cart/cart.php`) ran an unconditional
stale-cart-cleanup `DELETE` query on *every* request (catalog and admin
alike — `Cart` is instantiated once per page load by both front
controllers' startup sequence), the "excessive session/cart writes" class
of issue `MINTCART_FUNCTIONAL_SCOPE.md` §18's Known Fix Research Queue
names explicitly. Added `MintCart\Commerce\CartGarbageCollector`
(`upload/system/library/mintcart/commerce/cartgarbagecollector.php`) — a
small, pure, OpenCart-independent probability gate (default 5% of
requests, mirroring PHP's own `session.gc_probability`/`session.gc_divisor`
pattern) — and wrapped the existing query in
`if ((new \MintCart\Commerce\CartGarbageCollector())->shouldRun()) { ... }`.
The cleanup's own logic (which rows are eligible, when) is unchanged; only
how often the check runs changes. Full write-up: `docs/fixes/registry.md`'s
MC-0008 entry and `docs/upstream-diff.md`'s new `cart.php` entry.

Step 0 dedup check for this run: `gh pr list --state open` showed 39 open
PRs (`#52`–`#91`) at the start of this run, covering nearly every other
unstarted item in this file (all remaining Entity Picker backends, three
more Wizard steps, five SEO Core sub-pieces, Bulk Editor, Diagnostics,
`HealthCheck`, `CacheInvalidator`, `UpdateChecker`/`BackupPlanner`,
`SettingsAdapter`/`ExtensionAdapter`, Compatibility Checker, the theme/
extension rollback RESEARCH item, `QuickOrderService`,
`CheckoutTotalsService`, the product detail page, mobile navigation, the
admin asset-enqueue fix, four historical-fix registry entries
(MC-0004–MC-0007), and a release packaging script — see each PR's own
title). None of them touch `Cart\Cart`, `system/library/cart/`, or any
`MintCart\Commerce\*` file (confirmed via each PR's title/scope; the
closest, `#88`'s `CheckoutTotalsService`, is a checkout-totals AJAX
endpoint, not the cart storage class) — this was picked because it's a
genuinely isolated, unclaimed, self-contained increment (one core file
plus one new pure class) that couldn't collide with any file the other 39
PRs are touching. Step 0f hot-file check: none of `system/library/cart/`,
`system/library/mintcart/commerce/`, `docs/fixes/registry.md`, or
`docs/upstream-diff.md` are contended by 2+ open PRs (registry.md/
upstream-diff.md are append-only per-entry files — MC-0004 through MC-0007
each append their own section, same pattern as this entry, so a merge
conflict there is a trivial diff-append conflict, not a semantic one).

Verified: `php -l` clean on `cart.php`, the new
`CartGarbageCollector`, and its test.
`tests/mintcart/commerce/cart-garbage-collector-test.php` (7 scenarios —
deterministic boundary conditions only, since `shouldRun()` is
probabilistic in the general case; see the registry entry for why). Full
`tests/mintcart/**/*-test.php` suite re-run end to end (35 files, was 34)
— all passing, no regressions. **Not verified:** no live MySQL in this
environment (same pre-existing gap noted throughout this file) — a human
should confirm on a real install that the cleanup query now runs on
roughly 1 in 20 requests instead of every request (e.g. temporary
`error_log` inside the `if`), and that abandoned carts still expire
correctly over time. **Next step:** re-check `gh pr list --state open` for
newly-unclaimed work before picking the next increment — this backlog is
unusually large (39 open PRs) and many are likely ready for human
review/merge.
Fix Registry mechanism is established. This run added **MC-0007**:
`upload/system/library/cache/file.php` (OpenCart's stock, default file
cache backend) had two real concurrency bugs — `set()` truncated the
target filename directly before taking any lock (so two same-second
writers for the same key could stomp each other, and a racing `get()`
could observe a torn/0-byte file), and `get()` fataled instead of
returning a clean miss when a concurrent writer deleted the matched file
between `glob()` and `fopen()`. Fixed with a temp-file-then-atomic-`rename()`
write and a hardened `get()`. Reproduced and verified with a real
multi-process concurrency test
(`tests/mintcart/fixes/mc-0007-cache-file-atomic-write-test.php`, 150
child processes via `proc_open()`), confirmed non-vacuous by re-running
the same test against the pre-fix code in isolation (it reliably
reproduces the exact fatal error the fix removes). Full writeup:
`docs/fixes/registry.md`'s MC-0007 entry and `docs/upstream-diff.md`'s
`cache/file.php` entry. Verified: `php -l` clean across the whole
`upload`/`tests` tree; full `tests/mintcart/**/*-test.php` suite re-run
end to end (including the new file) — all passing, no regressions.
Diagnostics dev mode and the rest of "measured performance improvements"
remain not started on `master` (open PRs #75/#77/#83 cover pieces of this
phase as of this run — see `docs/IMPLEMENTATION_STATUS.md`'s own "Next
step for the next routine run" trailer and re-check `gh pr list --state
open` before picking up more of this phase).

Step 0/0f dedup check for this run: 35 open PRs (`#52`–`#88`) were listed
at the start of this run. None touch `upload/system/library/cache/file.php`
or any file under `upload/system/library/mintcart/core/cache/` (that
directory is PR #77's own `CacheInvalidator`, a different, additive
class layered on top of OpenCart's stock `\Cache` facade — it does not
touch the `file` adapter itself). Several other candidates were
considered and ruled out first: `MintCart\Compatibility\OpenCart3\*`
adapters beyond `SettingsAdapter`/`ExtensionAdapter` (both #81 and #87
explicitly flag `ProductAdapter`/`CategoryAdapter`/etc. as premature
without a real consumer, per `CLAUDE.md` §85); a `CanonicalBuilder` for
Phase 18 (already ruled out by PR #71's own writeup — canonical is stock
OpenCart behavior, nothing left to add); any Setup Wizard step
(`wizard.php`/`wizard.twig` contended by 3 open PRs — #52/#64/#84); and
any Homepage Builder/admin-menu change (`column_left.php` contended by
multiple open PRs). The Known Fix Research Queue's "cache race
conditions" item (`MINTCART_FUNCTIONAL_SCOPE.md` §18) was genuinely
unclaimed and led to this file.
Fix Registry mechanism is established and has two real entries. Diagnostics
and most of "measured performance improvements" remain not started, except
for one small piece (this run, 2026-09-08): `product.image_width`/
`image_height` now flow from `MintCart\Commerce\ProductViewProvider` into
`mc-product-card`'s `<img width height>` attributes, since every listing
controller (Category, Search, Manufacturer, Special, Homepage Products
block) already resizes to a fixed theme-configured size before calling the
provider — this only threads that already-known value through, no new
OpenCart calls. `loading="lazy"` was already present on both `<img>` tags
from Phase 6. See `docs/developer/commerce/product-view-provider.md`'s
"Image dimensions" section and `docs/developer/components/product-card.md`'s
new "Image dimensions (layout-shift prevention)" section for the full
writeup. Verified: `php -l` clean on all 6 touched PHP files; 2 new
`ProductViewProvider` smoke-test scenarios (now 10) covering pass-through
and the null-default case; full `tests/mintcart/**/*-test.php` suite
re-run end to end (34 files) — all passing, no regressions; both edited
`.twig` files' `{% if %}`/`{% endif %}`/`{% for %}`/`{% endfor %}` tag
counts checked balanced. **Not verified:** no live MySQL/`vendor/twig/twig`
in this environment (same pre-existing gap noted throughout this file) — a
human should confirm the rendered `<img>` tags carry the expected
`width`/`height` on a real category/search/manufacturer/special/homepage
page. Responsive images (`srcset`), WebP/modern formats and asset
minification-beyond-esbuild's-defaults remain untouched.

Step 0 dedup check for this run: `gh`/GitHub MCP `list_pull_requests
--state open` showed 30 open PRs (`#52`–`#82`) at the start of this run,
covering nearly every other unstarted phase in this file (SEO Core's five
sub-pieces, Update Manager's first two increments, Compatibility Checker,
Diagnostics, `CacheInvalidator`, `HealthCheck`, a `SettingsAdapter`, the
admin asset-enqueue fix + a live `McEntityPicker` consumer, `QuickOrderService`,
a Mint Default product detail page, four historical-fix registry entries,
release packaging, and the remaining Phase 12/13/14 items) — none touch
`ProductViewProvider`, `product-card.twig`, or any of the five controller
call sites this run edited. Step 0f hot-file check: `wizard.php`/
`wizard.twig`, `admin/controller/mintcart/entity.php` and
`column_left.php` are each contended by 2+ open PRs per those PRs' own
write-ups — untouched by this run. `category.php`/`search.php` are each
touched by exactly one open PR (`#62`, adding Schema.org BreadcrumbList
JSON-LD near the end of each `index()` method, well after the `foreach`
loop this run edited) — confirmed via `git diff` against `#62`'s branch
that the two changes don't share a line, so this was treated as
low-risk-but-not-blocked rather than a 2+-PR hot file.
- [ ] Measured performance improvements (lazy loading, responsive images, asset
      minification — no mandatory Redis)

Fix Registry mechanism is established and has two real entries; the cache
invalidation API primitive is done (see above); diagnostics (open PR #75)
and measured performance work are not started on `master` yet.
**This run: MC-0004.** Picked from `MINTCART_FUNCTIONAL_SCOPE.md` §18's
Known Fix Research Queue ("hardcoded database prefix in backup/restore
logic") — reproduced directly against this repo (not from memory, per
`CLAUDE.md` §84): `ControllerToolBackup::import()`'s check for the
`user`/`user_group` `TRUNCATE TABLE` lines used hardcoded lengths (`24`/`30`)
that only happen to be correct for the stock 3-character `oc_` `DB_PREFIX`;
any other prefix length (which OpenCart's installer, and MintCart's own
Setup Wizard, both allow choosing) silently breaks the safety guard that
stops a restore before it overwrites those two tables. Fixed by deriving
the comparison length from the actual needle string's `strlen()` instead of
a literal. New, fully isolated regression test (no OpenCart dependency, a
first for this Fix Registry — MC-0001–MC-0003 all needed a live controller
to test properly): `tests/mintcart/fixes/mc-0004-backup-restore-prefix-test.php`
(5 scenarios). Full writeup: `docs/fixes/registry.md`'s MC-0004 entry and
`docs/upstream-diff.md`'s new `upload/admin/controller/tool/backup.php`
entry. Verified: `php -l` clean; full `tests/mintcart/**/*-test.php` suite
re-run end to end (35 files, was 34) — all passing, no regressions. Not
touched by, or touching, any of the 23 PRs open at the time of this run
(none mention `tool/backup` or restore/backup logic).

Fix Registry mechanism is established and has four real entries; the rest of
this phase (diagnostics, measured performance work) is not started.
      (`category.php` blank language strings) and MC-0002 (`search.php`
      missing canonical/prev/next links, this run)
- [~] Diagnostics dev mode (query count, DB time, render time, cache status) —
      `MintCart\Core\Diagnostics` (`upload/system/library/mintcart/core/diagnostics.php`),
      documented in `docs/developer/core/diagnostics.md`. Pure collector (no
      OpenCart dependency, same shape as `StoreReadiness`/`ProductViewProvider`):
      named checkpoints (`mark()`), counters (`increment()`/`counter()`), and
      accumulated per-bucket timings (`time()`/`duration()`), summarized via
      `summary()`. A cheap no-op when disabled — `time()` still always runs the
      wrapped work and returns its result either way, only the measurement
      itself is skipped. **Update (this run, routine): real render-time
      measurement + a dev-mode setting + a visible surface are now wired.**
      `MintCart\Design\ComponentRenderer::render()` gained an optional 4th
      constructor argument, `?Diagnostics $diagnostics = null` — the one
      method every component render (product-card, blocks, header, footer)
      already passes through regardless of caller, so instrumenting it there
      covers every current and future consumer at once, with `null` (the
      default) leaving every existing call site byte-for-byte unaffected.
      `ControllerProductCategory` (`upload/catalog/controller/product/category.php`)
      is the first real consumer: constructs `Diagnostics` gated by a new
      `mintcart_dev_mode` setting (standard OpenCart settings storage, no new
      Mint table), passes it into `ComponentRenderer`, and — only when
      enabled — appends `$diagnostics->summary()` as an HTML comment to the
      response body (Functional Scope §19's "Production UI не повинен
      показувати sensitive debug data": never written into `$data`/the
      template itself, so no theme override can surface it). Still **not**
      built: real DB query counting (still a real upstream-diff decision per
      `CLAUDE.md` §10/§75 — hooking `\DB\MySQLi` without duplicating
      OpenCart's own DB class), an admin UI toggle for `mintcart_dev_mode`
      (today it can only be set directly in `oc_setting`), and wiring the
      same pattern into Search/Manufacturer/Special (each still builds its
      own bare `ComponentRenderer`). Full writeup:
      `docs/developer/core/diagnostics.md`,
      `docs/developer/theme-api/component-renderer.md`'s new "`$diagnostics`
      (Phase 10)" section, and `docs/upstream-diff.md`'s `category.php` entry.
      Tests: `tests/mintcart/design/component-renderer-test.php` gained 3
      scenarios (14-16, 16 total) covering the no-Diagnostics-touched-on-
      empty-override and thrown-resolution-error paths and the
      no-Diagnostics-passed default, none needing `vendor/twig/twig` (same
      pre-existing gap as every other `ComponentRenderer::render()` call);
      full `tests/mintcart/` suite (65 files) re-run end to end, all passing.
      **Update (2026-09-15, routine run): Search/Manufacturer/Special wired
      too.** `ControllerProductSearch`, `ControllerProductSpecial`, and
      `ControllerProductManufacturer::info()` now each construct their own
      `mintcart_dev_mode`-gated `Diagnostics`, pass it as `ComponentRenderer`'s
      4th argument, and append the same dev-mode-gated HTML-comment summary
      before their own `setOutput()` call — identical pattern to
      `category.php`'s, no new test file needed (`component-renderer-test.php`'s
      existing scenarios are call-site-agnostic). `product.php`'s
      related-products `ComponentRenderer` call site remains the one bare
      one left (a product-detail, not listing, context — left for a
      separate increment). Full writeup: `docs/developer/core/diagnostics.md`,
      `docs/developer/theme-api/component-renderer.md`, and
      `docs/upstream-diff.md`'s `search.php`/`special.php`/`manufacturer.php`
      entries. Verified: `php -l` clean on all three touched files; full
      `tests/mintcart/**/*-test.php` suite (77 files) re-run end to end —
      all passing, no regressions. Step 0 dedup check: `gh pr list --state
      open` returned zero open PRs at the start of this run (the prior
      14-PR backlog, `#95`-`#108`, had already merged/closed), so no
      collision check was needed against other in-flight work.
      **Update (2026-09-15, routine run): `ControllerProductProduct`'s
      related-products `ComponentRenderer` call site wired too.** Same
      pattern as `category.php` — `$mint_diagnostics` constructed
      unconditionally right after `$mint_render_product_cards` is resolved
      (so it covers the whole product-page render even when the theme
      isn't Mint-aware and the related-products renderer never runs),
      passed as `ComponentRenderer`'s 4th argument, `mark('view_rendered')`
      before `load->view()`, and the same dev-mode-gated HTML-comment
      summary appended before `setOutput()`. This was the last bare
      `ComponentRenderer` call site in `upload/catalog/controller/product/`
      — Search/Manufacturer/Special were wired in the entry directly above.
      No new test file needed (`component-renderer-test.php`'s existing
      scenarios are call-site-agnostic). Full writeup:
      `docs/developer/core/diagnostics.md`,
      `docs/developer/theme-api/component-renderer.md`, and
      `docs/upstream-diff.md`'s `product.php` entry. Verified: `php -l`
      clean; full `tests/mintcart/` suite (77 files) re-run end to end —
      all passing, no regressions.
- [~] Measured performance improvements (lazy loading, responsive images, asset
      minification, cache invalidation API — no mandatory Redis) — cache
      invalidation API and asset minification (esbuild) were already done
      in earlier phases; **responsive image `width`/`height` attributes and
      lazy loading are new this run** (see below) for the category
      thumbnail, product gallery, and Homepage Builder Text/Image block —
      `mc-product-card`'s listing images and the Banner Grid/Gallery blocks
      already had this from Phase 6/10. Real DB query counting (the other
      "measured performance" sub-piece, `MintCart\Core\Diagnostics`) still
      needs the `\DB\MySQLi`-hooking upstream-diff decision noted in the
      Diagnostics entries above.

**Update (2026-09-18, routine run): responsive image `width`/`height` +
lazy loading for the category thumbnail, product gallery, and the
Text/Image Homepage Builder block.** Step 0 dedup check:
`mcp__github__list_pull_requests` (all open, not filtered by branch name)
returned exactly four open PRs at the start of this run — `#127`
(`MintCart\Update\PackageExtractor`), `#128` (`MintCart\Update\BackupExecutor`,
both Phase 11 Update Manager, contended — two open PRs already touch
`upload/system/library/mintcart/update/**`, so per the routine's own §0f
hot-file guard that whole area was treated as blocked this run), `#129`
(admin toggle for `mintcart_dev_mode`, System → Diagnostics), and `#130`
(`product_form.twig`'s Related Products field wired into `McEntityPicker`).
None touch `category.php`, `product.php`, `common/home.php`, or any
`mint_default` template — confirmed via each PR's own file list, not just
its title. Also investigated wiring the remaining Phase 12 Entity Picker
backends (Option/Attribute/Information/Customer) and the Phase 16 Bulk
Editor "category"/"special" fields this file's own prior "Next
candidates" notes had named — both turned out to need real design work
once checked against the actual code (not guessed from memory, per
`CLAUDE.md` §84): the Option field's existing autocomplete
(`catalog/option.php::autocomplete()`) returns nested `option_value`/`type`
data `McEntityPicker`'s generic `{id, name, image}` contract can't carry,
and `oc_product` has no `special_price`/category scalar column at all
(special pricing lives in `oc_product_special`, categories in
`oc_product_to_category`) — both are join-table/multi-field writes, not
the same shape as `manufacturer_id`/`stock_status_id`'s plain column
update despite this file's own earlier note calling them "same shape".
Documented here rather than attempted as a guess. Picked this
Functional Scope §19 "responsive images; width/height"/lazy-loading item
instead — genuinely unclaimed, mechanical, and precedented by
`mc-product-card.twig`'s and the Banner Grid/Gallery blocks' own existing
`width`/`height`/`loading="lazy"` pattern.

`category.php`/`product.php` now capture the same
`theme_<theme>_image_*_width`/`_height` config values their existing
`resize()` calls already used into named `$data` keys
(`thumb_width`/`thumb_height`, and per-image `thumb_width`/`thumb_height`
for `product.php`'s gallery loop) instead of only using them inline — no
resize behavior change, purely additive keys. `common/home.php`'s
`renderTextImageBlock()` had a real gap: `TextImageBlockProvider::build()`
has always computed `image_width`/`image_height` (defaulting to 800x600)
and the controller already used them to resize the image, but never
passed them into the block's own render array, so
`mint/blocks/text-image.twig` had no way to render them at all — now
fixed. `mint_default/template/product/category.twig`/`product.twig`/
`mint/blocks/text-image.twig` render explicit `width`/`height` on the
category thumbnail, product gallery (main image stays eager — the page's
LCP candidate, same convention `product-card.twig` already established
for the listing image; every thumbnail in the strip gains
`loading="lazy"`), and the Text/Image block's image (`loading="lazy"`,
matching Banner Grid/Gallery's already-existing pattern). Full writeup:
`docs/upstream-diff.md`'s `category.php`/`common/home.php`/`product.php`
entries, `docs/MANUAL_VERIFICATION.md`'s new Phase 19 section.

**Verified:** `php -l` clean across the full `upload`/`tests` tree (only
`category.php`, `product.php`, `common/home.php` touched — no existing
pure-logic class modified). Full `tests/mintcart/**/*-test.php` suite (81
files) re-run end to end — all passing, no regressions (no new test file
needed — this is config-value threading and one missing array key, not
new logic to unit-test; `TextImageBlockProvider`'s own
`image_width`/`image_height` computation is already covered by
`tests/mintcart/blocks/text-image-block-provider-test.php`). Every
touched `.twig` file's `if`/`endif`/`for`/`endfor` tag counts checked
balanced before and after (`category.twig` 13/13 + 5/5,  `product.twig`
36/36 + 11/11, `text-image.twig` 8/8). **Not verified:** no live
MySQL/Twig session in this environment — see
`docs/MANUAL_VERIFICATION.md`'s new Phase 19 section for the full manual
script a human should run.

**Next candidates:** the two Entity Picker/Bulk Editor items this run
found were bigger than previously documented (see above) — Option field
wiring needs either a second endpoint returning option-value/type data or
accepting a lesser feature (name-only, then a follow-up fetch), and
category/special bulk-edit need a join-table write path, not a
`BulkProductUpdateValidator::FIELDS` entry — both are real design
questions for a human or a future run with more room to make that call,
not mechanical follow-ups; other listing/detail templates (search,
manufacturer, special) may have their own `<img>` tags worth the same
`width`/`height`/lazy-loading treatment — worth a quick audit; or
re-check `gh pr list --state open` first, since `#127`-`#130` may have
merged by the time a future run starts, un-blocking Update Manager's
`apply()` wiring.
      **Update (2026-09-18, routine run): admin UI toggle for
      `mintcart_dev_mode`.** Every `ComponentRenderer` call site was already
      wired (above); the flag itself could still only be set by writing
      directly to `oc_setting`. Added `ControllerMintcartSettingsDiagnostics`
      (`upload/admin/controller/mintcart/settings/diagnostics.php`, route
      `mintcart/settings/diagnostics`) — one checkbox, one Save, same
      thin-facade-over-`ModelSettingSetting` shape as
      `ControllerMintcartSeoSystemPages`/`ControllerMintcartBusinessContacts`.
      Stores the flag under its own settings code group (`mintcart_dev_mode`,
      same key) rather than merging into `config`, to avoid
      `ModelSettingSetting::editSetting()`'s "delete-then-reinsert wipes the
      rest of the group" trap for a group it doesn't own. Menu entry added
      to the existing `System` group in
      `upload/admin/controller/common/column_left.php` (see
      `docs/upstream-diff.md`'s entry) — step 0 confirmed neither open PR at
      the time (#127 "PackageExtractor", #128 "BackupExecutor") touches
      `column_left.php` or any admin `mintcart/settings/*` path. Full
      writeup: `docs/developer/core/diagnostics.md`. Verified: `php -l`
      clean on all four new/changed PHP files; full `tests/mintcart/` suite
      (81 files) re-run end to end, all passing, no regressions — no new
      test file needed (a thin controller wrapping an already-tested
      setting-storage pattern, same as its sibling admin screens, none of
      which have their own controller-level test in this repo). Only real
      DB query counting remains open for this checklist item now.
- [ ] Measured performance improvements (lazy loading, responsive images, asset
      minification, cache invalidation API — no mandatory Redis)

Fix Registry mechanism is established and has four real entries on this
branch (MC-0001, MC-0002, MC-0003, MC-0006); the rest of this phase
(diagnostics, measured performance work) is not started here — PR #75
(diagnostics) and PR #77 (cache invalidation) are open and cover part of
the "measured performance" bullet, unconfirmed merged at the time of this
run.
**This run: MC-0005.** Picked from `MINTCART_FUNCTIONAL_SCOPE.md` §18's
Known Fix Research Queue ("PHP 8.x compatibility problems" / "installer
edge cases") — reproduced directly against this repo (per `CLAUDE.md`
§84): `install/cli_install.php`'s `check_requirements()` warned only below
PHP 7.3, but `system/startup.php` — required unconditionally a few lines
above in the same file, before `check_requirements()` can ever run —
already hard-exits below PHP 8.1.0, making the 7.3+ message dead,
unreachable code that was also factually wrong. Both `cli_install.php` and
`install/controller/install/step_2.php`'s companion check also used a raw
string `'<'` comparison instead of `version_compare()`, unlike ~90 other
PHP-version gates already in this codebase. Fixed: `cli_install.php`'s
threshold/message now matches `system/startup.php` exactly (8.1.0,
`version_compare()`); `step_2.php` keeps its already-correct "8.1"
threshold but now uses `version_compare()` too. New, fully isolated
regression test (no OpenCart dependency, same pattern as MC-0004's):
`tests/mintcart/fixes/mc-0005-install-php-version-check-test.php`. Full
writeup: `docs/fixes/registry.md`'s MC-0005 entry and `docs/upstream-diff.md`'s
new `cli_install.php`/`step_2.php` entries. Verified: `php -l` clean on
both modified files; full `tests/mintcart/**/*-test.php` suite re-run end
to end (35 files, was 34) — all passing, no regressions. Checked against
every PR open at the start of this run (`#52`–`#77`, see this file's final
"Next step" trailer for the full list and hot-shared-file reasoning) —
none touch `install/cli_install.php`, `install/controller/install/step_2.php`,
or `docs/fixes/registry.md`/`docs/upstream-diff.md` beyond simple
end-of-file appends, so no collision. **Not verified:** actually running
`cli_install.php` under a real PHP 7.x binary (none available in this
environment) — see the registry entry's own note on this gap.

Fix Registry mechanism is established and has five real entries (four on
this branch: MC-0001, MC-0002, MC-0003, MC-0005; MC-0004 pending in PR
#76); the rest of this phase (diagnostics, measured performance work) is
not started.
Fix Registry mechanism is established and has two real entries; the
diagnostics collector contract is shipped and unit-tested (not yet wired to
any real request); measured performance work is not started.

Verified (Diagnostics): `php -l` clean on both new PHP files, re-swept
across the full `upload/`/`tests/` tree; new
`tests/mintcart/core/diagnostics-test.php` (10 scenarios — enabled/disabled
behavior, mark ordering against an injected deterministic clock, counter/
duration independence, `time()`'s always-runs-the-work contract); full
`tests/mintcart/**/*-test.php` suite re-run end to end — all passing, no
regressions. No JS/CSS touched, so `npm run build`/`npm test` were not
re-run. No live-wiring gap to flag in `docs/MANUAL_VERIFICATION.md` — there
is no live request path yet for a human to exercise (see the doc's
Definition of Done checklist for exactly what's still open).

**Update (2026-10-01, routine run) — MC-0019 added (Fixes Registry):**
`DB\PDO::query()` had no reconnect-on-dropped-connection handling at all —
the sibling adaptor `DB\MySQLi::query()` already got this under MC-0011,
this closes the gap for `DB\PDO`. See `docs/fixes/registry.md`'s MC-0019
entry and `docs/upstream-diff.md`'s `upload/system/library/db/pdo.php`
entry for the full writeup. Step 0 dedup check: `list_pull_requests`
returned 41 open PRs at the start of this run (`#138`–`#177`); none touch
`system/library/db/*` or any Fixes Registry entry (the closest, `#174`,
claims `MC-0018` in a different file). No Homepage Builder hot files
(§0f: block-type registry, homepage block list/template, `block.css`)
touched by this increment. Verified: `php -l` clean on the modified
`pdo.php` and the new `tests/mintcart/fixes/mc-0019-pdo-reconnect-test.php`
(7 scenarios); full `tests/mintcart/**/*-test.php` suite re-run end to end
(85 files, up from 84) — all passing, no regressions. No JS/CSS touched.
**Not verified:** no live MySQL server or real `DB_DRIVER=pdo` install in
this environment — see the registry entry's "Not verified" note. **Next
candidates:** `OrderAdapter` (`MintCart\Compatibility\OpenCart3`) is the
one adapter CLAUDE.md §38's list still lacks, but has no real consumer
yet (confirmed via grep — nothing in `mintcart/*` code touches
`model_sale_order`), so per CLAUDE.md §85 it should wait for one; Demo
Data (Functional Scope §30) is unimplemented and can be built as a
standalone admin screen decoupled from the in-flight Setup Wizard Payment
step (`#142`) — a real next candidate; or re-check `gh pr list --state
open` first, since this backlog moves fast (41 open PRs at the time of
this run — see this routine's own step 0f guidance on hot shared files
before picking a Homepage Builder block-type increment).

## Phase 11 (Technical Plan) — Update / Compatibility

- [~] Update Manager (check → compatibility → backup → download → verify →
      maintenance → files → migrations → cache clear → health check → done) —
      **`MintCart\Update\PackageManifest`**
      **`check` and `backup` steps built** (this run added `backup`;
      `check` — `MintCart\Update\UpdateChecker` — is a separate, still-open
      PR, #82, not yet on `master` as of this run):
      `MintCart\Update\PackageManifest`
      (`upload/system/library/mintcart/update/packagemanifest.php`), a pure
      manifest parser/validator/compatibility-constraint checker (name,
      version, requirements, files, `removes` — added this run, see below —
      migrations, checksum); and `MintCart\Update\BackupPlanner`
      (`upload/system/library/mintcart/update/backupplanner.php`, this
      run), the `backup` step — given a manifest and which of its declared
      paths currently exist on disk, decides exactly what to snapshot and
      where, per `docs/adr/005-theme-extension-update-rollback.md`'s
      decisions #3/#6/#7. All follow the same "primitive first, wire the
      flow around it once a real caller exists" pattern `MigrationManager`
      (Phase 1) and `WizardProgress` (Phase 13) already used. See
      `docs/developer/update/package-manifest.md` for the full contract and
      what's still not done (no caller/downloader/verify/maintenance-
      mode/file-copy/migration-runner/cache-clear/health-check steps yet,
      no `mc_installed_file` ledger).
      **two increments done:** `MintCart\Update\PackageManifest`
      (`upload/system/library/mintcart/update/packagemanifest.php`), a pure
      manifest parser/validator/compatibility-constraint checker (name,
      version, requirements, files, migrations, checksum); and
      `MintCart\Update\UpdateChecker`
      (`upload/system/library/mintcart/update/updatechecker.php`), the
      flow's `check` step — given a manifest plus installed versions,
      returns `update_available`/`compatible`/`should_apply`. Both follow
      the same "primitive first, wire the flow around it once a real caller
      exists" pattern `MigrationManager` (Phase 1) and `WizardProgress`
      (Phase 13) already used. See `docs/developer/update/package-manifest.md`.
      **`MintCart\Update\PackageVerifier`** (this run) — the `verify` step:
      `verifyChecksum()` checks a downloaded archive against the manifest's
      declared `checksum`, `missingFiles()` checks an extracted package
      against the manifest's declared `files()` list. See
      `docs/developer/update/package-verifier.md`. Still not done: no
      caller/downloader/backup/maintenance-mode/file-copy/migration-runner/
      cache-clear/health-check steps.
      (Phase 13) already used. See
      `docs/developer/update/package-manifest.md` for the full contract and
      what's still not done (no caller/downloader/backup/verify/maintenance-
      mode/file-copy/migration-runner/cache-clear/health-check steps yet).
      **Update (2026-09-17, PR #125):** every step's own primitive now
      exists (`UpdateChecker`, `BackupPlanner`, `PackageDownloader`,
      `PackageVerifier`, `MaintenanceMode`, `PackageFileInstaller`, the
      Phase 1 `MigrationManager`, `CacheInvalidator`, `HealthCheck`), and
      `MintCart\Update\UpdateOrchestrator`
      (`docs/developer/update/update-orchestrator.md`) now chains
      `download → verify → maintenance → files → migrations → cache clear
      → health check` end to end. Still not done: `check`/`compatibility`/
      `backup` are not wired into the orchestrator itself (a caller is
      expected to run those first, see the new doc), no archive extractor
      exists anywhere in this codebase, no `mc_installed_file` ledger, and
      no admin controller/`mintcart/update/*` route calls any of this yet.
      **Update (2026-09-18, routine run):** `BackupPlanner::plan()` only
      ever decided *what* to snapshot — nothing in this codebase actually
      performed that copy. `MintCart\Update\BackupExecutor`
      (`upload/system/library/mintcart/update/backupexecutor.php`,
      `docs/developer/update/backup-executor.md`) is that missing
      execution half: given a `BackupPlanner::plan()` result and the live
      install root, atomically copies every still-existing planned file
      into the snapshot directory (temp-dir-then-rename, same pattern
      `PackageExtractor` established for archive extraction). Still not
      wired into `UpdateOrchestrator::apply()` (would add a `backup` step
      between `verify`/`maintenance`, changing that method's own tested
      signature — deliberately deferred, same reasoning as
      `PackageExtractor`'s own non-wiring). No restore/rollback execution,
      no admin controller/route. (Note: `MintCart\Update\PackageExtractor`
      itself, referenced above, was added on a separate branch/PR #127,
      not yet merged as of this run — this increment does not depend on
      it and does not touch any of its files.)
      **Update (2026-09-17, routine run):** ~~no archive extractor exists~~
      **done** — `MintCart\Update\PackageExtractor`
      (`upload/system/library/mintcart/update/packageextractor.php`,
      `docs/developer/update/package-extractor.md`) extracts a downloaded
      zip archive into a directory, with zip-slip protection (rejects `..`
      and absolute-path entry names before writing anything) and atomic
      temp-dir-then-rename replacement, same shape as
      `PackageDownloader`/`PackageFileInstaller`'s own atomic-write
      patterns. Deliberately not wired into `UpdateOrchestrator::apply()`
      itself yet (would change that method's own signature — a separate,
      focused increment per the new doc's "Deliberately not attempted
      here"). Still not done: that orchestrator wiring, the
      `mc_installed_file` ledger, and any admin controller/route.
      **Update (2026-09-19, routine run):** ~~`PackageExtractor`/
      `BackupPlanner`+`BackupExecutor` not wired into
      `UpdateOrchestrator::apply()`~~ **done** — both are now optional
      constructor arguments (7th/8th+9th, plus a 10th `$timestampFactory`
      for the backup snapshot's timestamp). Omitted (`null`, the default),
      `apply()`'s behavior and tested signature are byte-for-byte unchanged
      from before this run. Injected, `extract` runs between `download` and
      `verify`, and `backup` (a new `resolveExistingFiles()` helper resolves
      which manifest-declared paths currently exist under `$destRoot` and
      their sizes, then `BackupPlanner::plan()` + `BackupExecutor::backup()`)
      runs between `verify` and `maintenance` — exactly the ordering
      `docs/developer/update/backup-executor.md`'s own "Deliberately not
      attempted here" note named. Passing only one of `backupPlanner`/
      `backupExecutor` throws `\InvalidArgumentException` at construction
      (a half-configured backup is a caller bug, not a "skip this step"
      signal). Full writeup: `docs/developer/update/update-orchestrator.md`
      (rewritten "what's out of scope"/usage/Verified sections),
      `docs/developer/update/package-extractor.md` and
      `docs/developer/update/backup-executor.md` (their own "wiring into
      apply()" notes marked done). Still not done: `check`/`compatibility`
      wiring (a caller's own pre-decision, per the class's own docblock),
      the `mc_installed_file` ledger, and any admin controller/route.
      Step 0 dedup check: `mcp__github__list_pull_requests` returned zero
      open PRs at the start of this run, so no dedup/hot-shared-file check
      applied. Verified: `php -l` clean; 3 new scenarios in
      `tests/mintcart/update/update-orchestrator-test.php` (10, up from 7 —
      extraction wired in against a real zip fixture, backup wired in with
      a snapshot read back off disk to confirm pre- vs post-update content,
      and the partial-pair constructor throw) plus the 7 pre-existing
      scenarios, all passing; full `tests/mintcart/**/*-test.php` suite
      re-run end to end (83 files) — all passing, no regressions. **Not
      verified:** no live MySQL/filesystem/HTTP in this environment, same
      pre-existing gap — extraction/backup themselves ARE exercised against
      real files/real zip archives in this run's new scenarios, only the
      admin-controller/live-install pipeline around `apply()` is unverified.
- [x] Theme/extension rollback — **research/design only, no code** (RESEARCH
      item per Functional Scope §21): `docs/adr/005-theme-extension-update-rollback.md`
      answers all seven of §21's open questions (change detection, manifest
      diffing, handling package-removed files, DB migration interaction,
      retention policy, disk limits, security) as concrete design decisions.
      No `mc_installed_file` table, no `PackageManifest` `removes` field, and
      no rollback action/service exist yet — deferred until the Update
      Manager's own `files`/`backup` steps exist to hang a snapshot off of
      (`CLAUDE.md` §85: no API ahead of a real consumer).
- [ ] Theme/extension rollback (RESEARCH item per Functional Scope §21)
- [x] Compatibility Checker (`install.xml` analysis, heuristic warnings only) —
      `MintCart\Compatibility\InstallManifestParser` +
      `MintCart\Compatibility\CompatibilityChecker`
      (`upload/system/library/mintcart/compatibility/`), first consumer
      `ControllerMintcartCompatibility` (route
      `mintcart/compatibility/check`, POST, JSON). Reports modified
      files/controllers/templates, Bootstrap 3 markup assumptions (reusing
      `bootstrap3-shim.css`'s own marker list), and potential conflicts
      against `docs/upstream-diff.md`'s known-modified-file list — never
      blocks (Functional Scope §29). Full writeup:
      `docs/developer/compatibility/compatibility-checker.md`. No admin
      menu entry yet (`column_left.php` contended by 3 other open PRs at
      the time of this run — see the doc's "Why no admin menu entry yet").
- [ ] **Release packaging script** — open PR at the time of this run (see
      "Next step" below); not yet merged to this checklist.

Compatibility Checker is done for what a heuristic, non-blocking analyzer
needs (parser + checker + a real JSON consumer, fully unit-tested, zero
existing OpenCart file touched). Update Manager and rollback remain not
started. Release packaging script's status is tracked by its own PR, not
rewritten here to avoid conflicting with it.
- [ ] Compatibility Checker (`install.xml` analysis, heuristic warnings only)
- [ ] **Release packaging script (TODO, not started).** Node/npm (`npm install`
      && `npm run build`) and the PHP vendor libraries already committed
      under `upload/system/**/vendor/` are dev/release-time tooling only —
      the end user installing MintCart must never need Node or Composer.
      Need a documented/scripted step that runs the frontend build and
      packages `upload/` (with `assets/dist/` already generated and
      included) into an installable zip, the same way OpenCart itself ships
      a pre-built `upload/`. Natural home: alongside Update Manager package
      manifest work (Technical Plan §33), or a standalone `docs/release.md`
      if it's needed before Update Manager exists.

In progress. Verified (this run): `php -l` clean on every touched/added
PHP file; new `tests/mintcart/update/package-verifier-test.php` (12
scenarios) passing; full `tests/mintcart/` suite re-run end to end (35
files) — no regressions (pure addition, no existing file modified). No
OpenCart dependency, so no live-install verification gap applies to this
specific increment.
In progress.

**Update (this run, 2026-09-09): `MintCart\Update\BackupPlanner` (the
`backup` step) + `PackageManifest::removes()`.** At the start of this run
there were 30 open PRs (#52–#85, #58 absent); only #82 (`UpdateChecker`,
the `check` step) touched `upload/system/library/mintcart/update/` at all,
and its diff (checked via `pull_request_read`) adds only
`updatechecker.php` — it does not touch `packagemanifest.php`, so adding
`removes()` there and a new `backupplanner.php` file collides with nothing
in flight. PR #85 (theme/extension rollback ADR, also open) explicitly
names `PackageManifest`'s `removes` field and a future `BackupPlanner`-
shaped `backup` step as its own "next step for a future run" — this run
is exactly that follow-up, sequenced independently of `UpdateChecker`
landing first (the `backup` step only needs `PackageManifest`, already on
`master`). Verified: `php -l` clean across the full `upload/`/`tests` tree;
`tests/mintcart/update/package-manifest-test.php` (11 scenarios, now
covering `removes()`) and the new
`tests/mintcart/update/backup-planner-test.php` (7 scenarios) passing;
full `tests/mintcart/**/*-test.php` suite re-run end to end (35 files, was
34) — all passing, no regressions. No OpenCart dependency in either
touched/added file, so no live-install verification gap applies to this
specific increment (same as `PackageManifest` itself). **Next step:** the
`mc_installed_file` ledger (ADR 005 decision #1, needs the Migration
Manager once a real `files` step exists to write to it), or continuing the
flow itself (`download`/`verify`/`maintenance`/`files` steps) once a
transport/caller exists — or re-check `gh pr list --state open` for
what's newly merged/unclaimed, since this backlog is unusually large.
- [x] **Release packaging script** — `scripts/release.js` (`npm run
      release`), documented in `docs/release.md`. Builds the frontend
      (`npm run build`), stages the *committed* `upload/` tree via
      `git archive HEAD` (so everything `.gitignore` already excludes from
      a real install — `config.php`, live `.htaccess`, `install.lock`,
      `storage/`/`image/cache/` contents — is excluded automatically, no
      separate list to maintain), copies the freshly built
      `assets/dist/{css,js}` into `upload/assets/{css,js}` inside that
      staged copy (mirroring the local-dev convention `.gitignore` already
      documented for the same reason — `assets/` lives outside `upload/`,
      which is what a webserver serves), then zips the result into
      `release/mintcart-<version>.zip`. Version is read from
      `MintCart\Core\Version::VERSION`, never re-typed. This is a
      standalone script, not the full Update Manager — see
      `docs/release.md`'s "What it deliberately does not do (yet)" for the
      boundary (no version bump, no compatibility check, no
      checksum/signature — those stay Update Manager's job once that phase
      starts, and this script's staging logic is the natural first
      building block for it, not something to redo).

Update Manager and Compatibility Checker are not started. Release
packaging (the concrete TODO this file had been flagging) is done.
In progress. Verified: `php -l` clean; new
`tests/mintcart/update/package-manifest-test.php` (11 scenarios) and
`tests/mintcart/update/update-checker-test.php` (7 scenarios) passing;
full `tests/mintcart/` suite re-run end to end (35 files) — no regressions
(pure addition, no existing file modified). No OpenCart dependency, so no
live-install verification gap applies to either increment.

**Update (this run, 2026-09-09): Theme/extension rollback RESEARCH item
answered — no code, one ADR.** At the time this run started there were 31
open PRs; the visible "next step" for nearly every phase already had an
open PR against it by title (Update Manager's own `check` step, Setup
Wizard's remaining steps, all five remaining Entity Picker backends, both
Business Settings façades left, the Store Readiness dashboard widget, SEO
Core's four sub-pieces, Bulk Editor, `QuickOrderService`, the search-box
component, Diagnostics, `CacheInvalidator`, `HealthCheck`,
`SettingsAdapter`, the Compatibility Checker, three registry fixes
(MC-0004/0005/0006), the admin asset-enqueue blocker, the product-card
image-dimensions fix and the product detail page) — see `gh pr list
--state open` for the current set. This phase's rollback bullet was the one
item with real, unstarted scope and no PR anywhere near it: it's an
explicit RESEARCH item (Functional Scope §21), so the correct increment is
answering its seven open design questions, not writing untested rollback
code with no real caller yet. Full writeup:
`docs/adr/005-theme-extension-update-rollback.md`. Verified: no code
changed, so no test suite applies to this increment; the ADR's own claims
about existing code (`PackageManifest`'s current field list having no
per-file checksum or `removes` field) were checked directly against
`upload/system/library/mintcart/update/packagemanifest.php` on this branch.
**Next step:** implement the `mc_installed_file` ledger table (via the
Migration Manager) and `PackageManifest`'s optional `removes` field once
the Update Manager's `files`/`backup` steps have a real caller to attach a
snapshot to (PR #82's `UpdateChecker` — the `check` step — is a
prerequisite in the flow's own ordering); or re-run `gh pr list --state
open` for whatever has newly merged/unclaimed before picking the next
phase, since this run observed an unusually large (31) open-PR backlog.

**Update (this run): `MintCart\Update\PackageFileInstaller` (the `files`
step).** At the start of this run there were 7 open PRs (#117-#123):
four Phase 18 SEO-wiring increments, one Phase 16 Bulk Editor field, and
two other Update Manager steps in flight (#122 `MaintenanceMode`/
`maintenance`, #123 `PackageDownloader`/`download`) — neither touches
`upload/system/library/mintcart/update/packagefileinstaller.php` (a new
file) or any other file this increment changes, confirmed via each PR's
own file list. `install()` copies every `PackageManifest::files()` path
from an extracted package into the live install (atomic
temp-file-then-rename per file, pre-flight `missingFiles()` check so a
missing source file writes nothing rather than installing partway), and
deletes every `PackageManifest::removes()` path (ADR 005 decision #3,
idempotent when already absent). Returns each installed file's sha256 —
the exact input the still-unbuilt `mc_installed_file` ledger needs, not
persisted by this class itself (no table/orchestrator exists yet to call
it — `CLAUDE.md` §85). Full writeup:
`docs/developer/update/package-file-installer.md`. Verified: `php -l`
clean on the new/touched files and across the full `upload`/`tests` tree;
new `tests/mintcart/update/package-file-installer-test.php` (16 scenarios);
full `tests/mintcart/**/*-test.php` suite re-run end to end (78 files, was
77) — all passing, no regressions. Pure PHP against temp-directory
fixtures, no OpenCart dependency, so no live-install verification gap
applies to this specific increment. **Next step:** an orchestrator that
chains `download → verify → maintenance → files → migrations → cache
clear → health check` end to end (once `download`/`maintenance` land —
check `gh pr list --state open` first), which is also the real caller the
`mc_installed_file` ledger table needs before it can be added.
**Update (2026-09-17, routine run): `MintCart\Update\PackageDownloader` —
the `download` step.** Step 0 dedup check: `list_pull_requests` (all open,
not filtered by branch name) returned 6 open PRs at the start of this run
— `#117`-`#121` (Phase 18 SEO wiring + one Phase 16 Bulk Editor field) and
`#122` (`MintCart\Update\MaintenanceMode`, the `maintenance` step). None
touch `upload/system/library/mintcart/update/packagedownloader.php` (a new
file); `#122` touches `maintenancemode.php`,
`compatibility/opencart3/settingsadapter.php`'s docblock, its own test and
docs — no file overlap with this increment. This run picks the exact
remaining item `docs/developer/update/package-verifier.md`'s own "What's
deliberately not done yet" section named: "a `download` step that actually
fetches a package archive over HTTP, then hands the local path to
`verifyChecksum()`."

Built `MintCart\Update\PackageDownloader`
(`upload/system/library/mintcart/update/packagedownloader.php`): an
injectable `callable(string $url): string` transport (duck-typed, same
"no OpenCart/network dependency baked in" pattern `MigrationManager`/
`SettingsAdapter` already use for their own dependencies) fetches package
bytes, written atomically (temp file + `rename()`) to a destination path.
Rejects non-`http(s)` URLs before the transport is ever invoked. No
orchestrator wires `download → verify` together yet — same "primitive
first" gap every other Update Manager class in this file already carries.
Full writeup: `docs/developer/update/package-downloader.md`.
`docs/developer/update/package-verifier.md`'s "not done yet" note updated
to mark `download` built and point at the new doc.

Verified: `php -l` clean on both new files, and re-swept across the full
`upload`/`tests` tree. New `tests/mintcart/update/package-downloader-test.php`
(9 scenarios — verbatim byte write, exact URL passed to the transport,
missing parent directories created, no leftover `.part-*` temp file after
success, a stale existing destination overwritten on retry, a non-http(s)
URL rejected before the transport runs, a schemeless URL rejected, an
empty transport response throws with no file written, a thrown transport
exception propagates unchanged with no file written) — all passing. Full
`tests/mintcart/**/*-test.php` suite re-run end to end (78 files, up from
77) — all passing, no regressions. **Not verified:** the default
`file_get_contents()`-based transport, and any real HTTP fetch — no
outbound network access in this environment; every test scenario injects
a fake transport instead.

**Next step:** an orchestrator/caller that chains
`PackageDownloader::download()` → `PackageVerifier::verifyChecksum()` →
`MaintenanceMode::enable()` (once `#122` merges) → a `files` step (not yet
built); the `mc_installed_file` ledger once that `files` step exists; or
re-check `gh pr list --state open` first, since `#117`-`#122` may have
merged by the time a future run starts.
**Update (2026-09-17, routine run): `MintCart\Update\MaintenanceMode` —
the `maintenance` step.** Step 0 dedup check: `list_pull_requests` (all
open, not filtered by branch name) returned 5 open PRs at the start of
this run — `#117`-`#121`, all Phase 18 SEO wiring (`MetaBuilder`/
`SchemaOrgBuilder`/`CanonicalUrlBuilder`/`RobotsBuilder`/`SitemapBuilder`
into various storefront controllers) plus one Phase 16 Bulk Editor field
(`stock_status_id`), all marked `ROUTINE-STATUS: DONE`. None touch
`upload/system/library/mintcart/update/**`,
`upload/system/library/mintcart/compatibility/**`, or any Update Manager
doc — confirmed no overlap. This increment picks the exact next step
`docs/developer/update/package-verifier.md`'s own "What's deliberately not
done yet" section named ("`maintenance` mode (put the store in maintenance
before `files`/`migrations` run)").

Built `MintCart\Update\MaintenanceMode`
(`upload/system/library/mintcart/update/maintenancemode.php`): toggles
OpenCart's own `config_maintenance` setting (never a second Mint-owned
flag, `CLAUDE.md` §39) through
`MintCart\Compatibility\OpenCart3\SettingsAdapter::merge()` — this is that
adapter's first real, wired-up consumer since it shipped in Phase 1 with no
call site (its own docblock previously said "Not wired into any controller
yet"). `enable()` returns whether the store was already in maintenance
mode before the call, so a future orchestrator can decide the flow's own
end state correctly instead of unconditionally reopening a store a site
owner had already put into maintenance by hand. No orchestrator/caller
exists yet (no single entry point runs `download → verify → maintenance →
files → migrations → cache clear → health check` in sequence) — same
"primitive first" pattern every other Update Manager class in this file
already used, deliberately with no `around(callable $step)`-style wrapper
since there's no real flow yet to decide where its boundaries sit
(`CLAUDE.md` §85).

Full writeup: `docs/developer/update/maintenance-mode.md`. Also updated
`SettingsAdapter`'s own docblock and `docs/developer/compatibility/settings-adapter.md`
to point at this new first consumer instead of saying "not wired into any
controller yet," and `docs/developer/update/package-verifier.md`'s "not
done yet" note to mark maintenance mode built.

Verified: `php -l` clean on all touched/added PHP files (also re-ran
`php -l` across the full `upload`/`tests` tree — clean). New
`tests/mintcart/update/maintenance-mode-test.php` (8 scenarios — unsaved
setting defaults to disabled; `enable()` persists `config_maintenance =
"1"` and reports the prior `false` state; a second `enable()` call is a
no-op write reporting the prior `true` state; `disable()` persists `"0"`
and is likewise idempotent; both preserve every other existing `config_*`
key via `SettingsAdapter::merge()`; `isEnabled()` treats a real bool/int
the same as OpenCart's stored `"1"`/`"0"` strings), reusing the same
`FakeSettingModel` double `settings-adapter-test.php` already established.
Full `tests/mintcart/**/*-test.php` suite (78 files, was 77) run end to
end twice — all passing, no regressions. Pure PHP against an in-memory
double, no OpenCart dependency, so no live-install verification gap
applies to this class itself (same as `SettingsAdapter`/`PackageVerifier`).

**Next step:** a `download` step that actually fetches a package archive
over HTTP (`docs/developer/update/package-verifier.md`'s remaining "not
done yet" item, unblocks `verifyChecksum()` having a real caller); routing
`saveStoreStep()`/the Contacts step/the Delivery-Payments toggle actions
through `SettingsAdapter` (mechanical, no behavior change, per that class's
own "Not done yet" doc section); or the `mc_installed_file` ledger once a
`files` step exists — or re-check `gh pr list --state open` first, since
`#117`-`#121` may have merged by the time a future run starts.

**Update (2026-09-17, routine run, PR #125): `MintCart\Update\UpdateOrchestrator`
— the flow finally chained end to end.** Step 0 dedup check:
`list_pull_requests` (all open, not filtered by branch name) returned
**zero open PRs** at the start of this run. With `download` (#123),
`maintenance` (#122) and `files` (#124) all landed on `master` alongside
the pre-existing `verify`/`migrations`/`cache clear`/`health check`
primitives, every constituent step this file's own "Next step" notes had
been naming for several runs now existed — so this run built the
orchestrator itself: `MintCart\Update\UpdateOrchestrator`
(`upload/system/library/mintcart/update/updateorchestrator.php`) chains
`PackageDownloader::download()` → `PackageVerifier::verifyChecksum()`/
`missingFiles()` → `MaintenanceMode::enable()` →
`PackageFileInstaller::install()` → `MigrationManager::run()` →
`CacheInvalidator::invalidateAll()` → `HealthCheck::evaluate()`, stopping
at the first failed step and never disabling maintenance mode on a failure
path. `check`/`compatibility`/`backup` and archive extraction stay
deliberately out of scope (earlier decision points / a still-unbuilt
extractor, same gaps every constituent primitive already documented). Full
writeup: `docs/developer/update/update-orchestrator.md`; updated the
"not done yet" sections of `package-downloader.md`, `package-verifier.md`,
`package-file-installer.md` and `maintenance-mode.md` to point at it.

Verified: `php -l` clean on the new file and across the full `upload`/
`tests` tree. New `tests/mintcart/update/update-orchestrator-test.php` (7
scenarios) passing; full `tests/mintcart/**/*-test.php` suite re-run end
to end (81 files, up from 80) — all passing, no regressions. Not verified:
no live MySQL/filesystem/HTTP in this environment, same pre-existing gap
every other Update Manager primitive carries.

**Next step:** a real admin controller/route (`mintcart/update/*`) calling
`UpdateOrchestrator::apply()` — the real caller the `mc_installed_file`
ledger table needs before it can be added; deciding and building archive
extraction (still unbuilt anywhere in this codebase); or wiring
`UpdateChecker`/`BackupPlanner` as the decision point before a caller
invokes this orchestrator — or re-check `gh pr list --state open` first.

---

**Update (2026-09-19, routine run): the `mc_installed_file` ledger
(ADR 005 decision #1) is built and wired in.** `list_pull_requests`
returned zero open PRs at the start of this run. New migration
`202609191200_create_mc_installed_file.php` (InnoDB/utf8mb4, `UNIQUE KEY`
on `(package_name, file_path)`) and new `MintCart\Update\InstalledFileLedger`
(`record()`/`forPackage()`) — wired into `UpdateOrchestrator::apply()` as
a 11th, independently-optional constructor argument (same pattern as
`$cache`/`$healthCheck`), calling `record()` with the manifest's own
package name and the `files` step's own `installed[]` right after that
step succeeds. Full writeup: `docs/developer/update/installed-file-ledger.md`;
updated "not done yet" notes in `update-orchestrator.md`,
`package-file-installer.md`, `package-manifest.md`, and ADR 005's own
Consequences section. `php -l` clean on all touched files; new
`tests/mintcart/update/installed-file-ledger-test.php` (6 scenarios) plus
a new `UpdateOrchestrator` scenario (11 total, up from 10);
`tests/mintcart/core/mc-block-migrations-test.php` updated for the new
migration now discovered alongside the Homepage Builder ones. Full
`tests/mintcart/**/*-test.php` suite re-run end to end (84 files, up from
83) — all passing, no regressions. Not verified: no live MySQL in this
environment, same pre-existing gap every other Mint-owned table carries.

**Next candidates:** a real admin controller/route (`mintcart/update/*`)
that actually builds a `PackageManifest`, resolves a download URL, and
calls `apply()` on a real install (still the biggest remaining gap —
nothing calls `apply()` outside tests, even though every constituent step
including `PackageExtractor`/`BackupPlanner`+`BackupExecutor`/
`InstalledFileLedger` now exists and is wired into `apply()` itself); the
"changed since install" comparison itself (current on-disk sha256 vs. the
ledger's recorded value) once a rollback consumer exists to justify it; or
re-check `gh pr list --state open` first.

---

**Update (2026-09-29, routine run): the "changed since install"
comparison named just above is now built.** `list_pull_requests` returned
6 open PRs (#138-#144, this run's dedup check found 6 at the time — see
this run's PR description for the exact list and file-overlap check);
none touch `upload/system/library/mintcart/update/*`,
`tests/mintcart/update/*`, or `docs/developer/update/*`, so this increment
was uncontended. New `MintCart\Update\ChangedFileDetector`
(`detect()`/`hasChanges()`) — a pure primitive taking
`InstalledFileLedger::forPackage()`'s own (file_path => sha256) snapshot
shape and comparing it against a live directory's current file hashes,
same "pure, OpenCart-independent primitive first" pattern `PackageVerifier`
already established. Full writeup: `docs/developer/update/changed-file-detector.md`;
`docs/developer/update/installed-file-ledger.md`'s "not attempted here"
note updated to point at it. No OpenCart core file touched — fully
additive, no `docs/upstream-diff.md` entry needed. `php -l` clean; new
`tests/mintcart/update/changed-file-detector-test.php` (15 scenarios);
full `tests/mintcart/**/*-test.php` suite re-run end to end (85 files, up
from 84) — all passing, no regressions. No live-install verification gap
to flag in `docs/MANUAL_VERIFICATION.md` — pure PHP against temp-directory
fixtures, same reasoning `PackageVerifier`'s own doc gives for not needing
an entry there.

**Next candidates:** the real admin controller/route
(`mintcart/update/*`) remains the biggest remaining gap in this flow — it
needs a product/architecture decision on where a package manifest/download
URL comes from (flagged by earlier runs, not attempted here); wiring
`ChangedFileDetector` into that future route or a dedicated rollback
screen once it exists; ADR 005 decision #2 (manifest-diff classification)
or decision #3 (`removes()` field on `PackageManifest`) as separate future
increments; or re-check `gh pr list --state open` first.

## Phase 12 — Early Access

- [ ] 10–30 real users/developers
- [ ] Measure: time to install, drop-off points, Time to First Test Order, theme
      override count

Not started.

## 1.0

- [ ] Freeze Theme API 1.0, Blocks API 1.0, Commerce contracts, compatibility policy

Not started.

## Documentation (Functional Scope §33 / Technical Plan §40)

- [~] User docs (`docs/user/`) — **install and Setup Wizard guides done this
      run**; products/delivery/payments/design/homepage/orders/SEO basics
      guides not started (most of those features aren't built far enough
      yet to document accurately — see each one's own Phase section above).
- [x] Developer architecture overview (`docs/developer/architecture/`,
      Technical Plan §40's third `docs/` subtree) — **this run**:
      `docs/developer/architecture/overview.md`. Maps each
      `MintCart\*` namespace/layer (Compatibility, Core, Design, Commerce,
      Blocks, SEO, Update, Onboarding, Catalog helpers, admin UI
      primitives) to the classes/routes that actually exist on `master`
      today, cross-checked file-by-file rather than written from memory
      (`CLAUDE.md` §84) — e.g. confirmed `MintCart\Core\Cache\CacheInvalidator`,
      `MintCart\Onboarding\SocialProfileLinks`, and
      `mint_default/template/mint/blocks/products.twig` all exist exactly
      as named before citing them. Points to the existing per-feature docs
      under `docs/developer/{admin,blocks,catalog,commerce,compatibility,
      components,core,design,onboarding,seo,theme-api,update}/*.md` for
      depth rather than duplicating them, and explicitly does not track
      implementation status (that stays `docs/IMPLEMENTATION_STATUS.md`'s
      job, since it changes too fast for a hand-written overview to track
      without going stale). Includes one concrete top-to-bottom request
      trace (a Homepage Products block render) illustrating the
      `Theme → MintCart API → OpenCart Adapter → OpenCart` dependency
      direction from `CLAUDE.md` §90.

**This run:** `docs/user/install.md` and `docs/user/setup-wizard.md` — this
repository had **zero** files under `docs/user/` despite it being an
explicit Functional Scope §33 P1 requirement and part of Technical Plan
§40's documented `docs/` layout; no open PR (of 50 checked at step 0)
touched documentation content, so this was genuinely unclaimed and,
being pure Markdown with no code, carried zero collision risk with any
in-flight PR. `install.md` documents OpenCart 3.0.5.1's own unmodified
installer (requirements list cross-checked live against
`upload/install/controller/install/step_2.php` rather than written from
memory, per `CLAUDE.md` §84) plus the one real MintCart-specific
difference (`install/opencart.sql`'s InnoDB/utf8mb4 normalization,
already applied, no user action needed). `setup-wizard.md` documents
`mintcart/wizard` exactly as `ControllerMintcartWizard`/`WizardProgress`
behave today — Welcome and Store are real, the other seven steps are
listed as roadmap-only (matching `WizardProgress::IMPLEMENTED_STEPS`
exactly, cross-checked against the actual class rather than assumed) —
including the known gaps (no admin entry point yet, logo upload not
wired, `admin.min.js` not enqueued on this page). Both docs are
deliberately honest about what's *not* built yet rather than describing
the full roadmap as if it already worked, matching this file's own
practice for developer docs.

Verified: no code touched (Markdown only), so `php -l`/`npm run build`/the
`tests/mintcart/` suite don't apply; every concrete claim in both files
(installer requirement list, wizard step names/order, Store step's field
validation rules and character limits, the `mintcart/readiness` JSON
route) was cross-checked against the actual source file it describes
(`upload/install/controller/install/step_2.php`,
`upload/system/library/mintcart/onboarding/wizardprogress.php`,
`upload/admin/controller/mintcart/wizard.php`) rather than written from
memory.

**Next step:** a `products.md` user guide is the natural next one to add
(Store → Products already has a stable, complete admin screen in stock
OpenCart, unlike most of the Wizard's remaining steps) — or continue this
section once more Phase 13 Wizard steps/Phase 14 Business Settings façades
land and can be documented accurately. Low collision risk either way
(pure Markdown, `docs/` only) — re-check `docs/user/` isn't newly claimed
by another open PR before starting, same as any other increment.

**Update (2026-09-29, routine run):** the Developer architecture overview
named above is now done — see the checklist entry just above for the
full writeup. Step 0 checked all 17 open PRs at the start of this run
(`#138`-`#154`, all `ROUTINE-STATUS: DONE` except `#148` `IN-PROGRESS`);
none touch `docs/user/` or `docs/developer/architecture/` (confirmed via
`git diff --name-only` against every open PR's branch, not just their
titles), so this was genuinely unclaimed and, being pure Markdown with no
code, carried zero collision risk. Verified: no code touched, so
`php -l`/`npm run build`/`tests/mintcart/` don't apply; every class name,
namespace, file path and Twig path cited in `overview.md` was grepped
against the actual `upload/system/library/mintcart/**` tree before being
written down, not recalled from memory. **Next candidates:** a
`docs/user/products.md` guide (see the "Next step" note above, still
valid); `docs/adr/` entries for decisions currently only justified inline
across several per-feature docs (`CLAUDE.md` §15); or re-check
`gh pr list --state open` first, since this backlog moves fast.

---

## Next step for the next routine run

**PR backlog status (resolved 2026-08-24, human merge pass):** the
original nine-PR `claude/charming-rubin-*` backlog is fully resolved.
#14, #15, #16, #17, #18, #19, #20, #23 and #24 are all merged to `master`
(in that order, with manual conflict reconciliation each time —
documented in this file's Phase 7/9/10/11 sections and each PR's own
comments); #21 and #22 were closed as superseded (duplicate
`ProductCardListRenderer` and duplicate Presets implementations
respectively — see the Phase 6/9 write-ups above). No open PRs remain as
of this entry. Re-check `gh pr list --state open` before starting new
work rather than trusting this snapshot.

The root cause of the original nine-PR pileup — each Claude Code Remote
session being bound to its own fixed `claude/charming-rubin-*` branch,
unable to continue a sibling's branch, combined with this routine's
dedup check (step 0) originally only searching for `routine/*`-prefixed
branches — has been fixed in the routine's own prompt (see the routine
configuration itself, updated 2026-08-24): step 0 now checks every open
PR regardless of branch name, and step 3's now-impossible "create a
`routine/*` branch" instruction was removed. Future routine runs should
not reproduce this pileup.

Phases 0–6 and 8 are now complete (see their sections above). **Phase 8 —
Theme resolver has passed its critical architecture milestone**
(`CLAUDE.md` §19, `MINTCART_TECHNICAL_PLAN.md` §45): `MintCart\Design\ComponentRenderer`,
`mint_default`'s `product-card.twig`, `ControllerProductCategory` wired to
both, and now `catalog/view/theme/test_theme/` proving a second theme can
override only `product-card.twig` and inherit everything else, 0 OCMOD, 0
controller patches, 0 copied business logic. Full writeup:
`docs/adr/002-theme-api-milestone-proof.md`. One caveat carried forward: this
environment still has no MySQL/`vendor/twig/twig`, so the proof is at the
`resolve()` (file-path resolution) level, not an observed live render — a
human should still confirm `test_theme`'s override actually renders on a
real install before the milestone is considered *fully* closed end-to-end.

**Update (this run): Phase 7's Footer sub-step is now fully wired end to
end** — see the Phase 7 section above for the full writeup.
`MintCart\Design\FooterViewProvider`, `mc-footer` CSS, `mint_default`'s
`mint/components/footer.twig`, and now live `ControllerCommonFooter` wiring
(reads `config_telephone`/`config_email`/`config_address`, regroups the
controller's existing links into columns, renders via `ComponentRenderer`
into `$data['mc_footer']`, consumed by a new
`mint_default/template/common/footer.twig`) are all done and verified
(full `tests/mintcart/` suite passing, `php -l` clean on the touched PHP
files). Header/Topbar/Navigation are still not started at all.

**Update (this run): theme override proof for `footer.twig` is done.**
`catalog/view/theme/test_theme/` now overrides both `product-card.twig`
(since Phase 8's milestone) and `footer.twig` (this run), each with its own
genuinely different DOM, still declaring `"parent": null` in `theme.json`.
`ComponentRenderer` scenario 12 confirms both fallback directions for
`footer` against the real shipped repo files. Full writeup in the Phase 7
section above and `docs/developer/components/footer.md`.

**Update (this run): Phase 9 — Presets is done.** `assets/src/css/presets/clean.css`
(documentation-only, the unmodified baseline) and `assets/src/css/presets/warm.css`
(a terracotta/serif/soft-radius override scoped under `[data-mc-preset="warm"]`)
prove the token system alone can carry a radically different look with 0
Twig changes — same `product-card.twig`/`footer.twig`/`card.twig` markup as
Clean. See the Phase 9 section above and `docs/developer/theme-api/presets.md`
for the full write-up, token diff table, and "adding a new preset" recipe.
Verified via the new `tests/mintcart/design/presets-test.php` (standalone,
no PHPUnit) and by inspecting the compiled `assets/dist/css/index.min.css`
bundle for all four `[data-mc-preset=warm]` rule blocks. Same caveat as
every other design-only milestone here: no live-render environment, so a
human should still confirm the visual result on a real install.

**Update (this run): Search is wired to `product-card`, and the shared
per-product rendering step is extracted.** See the note added above, just
before the Phase 9 heading, and `docs/developer/theme-api/product-card-list-renderer.md`
for the full writeup. `MintCart\Design\ActiveTheme` +
`MintCart\Design\ProductCardListRenderer` are the two new shared classes;
`ControllerProductCategory`/`ControllerCommonFooter` were refactored
(no behavioral change) to use them instead of their own private copies;
`ControllerProductSearch` is the new live consumer, with a new
`mint_default/template/product/search.twig`.

**Coordination note for whoever picks up next:** this repo currently has
several other open, unmerged PRs in flight from other (non-routine)
sessions working the *same* `docs/IMPLEMENTATION_STATUS.md` checklist in
parallel — at the time of this run, at least one already implements Phase
7's Header component end-to-end (provider, CSS/Twig, live
`ControllerCommonHeader` wiring, `test_theme` override), and others cover
Phase 9 Presets (a second, independent implementation of what this branch
already shipped), Phase 10's Block API storage, several Phase 11 admin
primitives (`McConfirm`/`McSortable`/`McTabs`/`McProgress`), and a CI
workflow. **Before starting Header (or any of the above) fresh, check
open PRs first** (`gh pr list --state open` / repo host equivalent) —
redoing already-open, tested work wastes a run and creates avoidable merge
conflicts; a human will reconcile whichever PR(s) actually land. This is
why *this* run picked Search wiring instead of Header: Header appeared
already done elsewhere, but Category→Search product-listing reuse
(`CLAUDE.md` §8A, `MINTCART_TECHNICAL_PLAN.md` §15C/§15D) did not.

**Update (a later run, same branch/PR #14): Special is wired to
`product-card` too.** `ControllerProductSpecial::index()`
(`upload/catalog/controller/product/special.php`) now builds
`$data['product_cards']` via the same
`MintCart\Design\ActiveTheme` + `MintCart\Design\ProductCardListRenderer`
pipeline Category and Search use — third real consumer of the shared
listing render step (`CLAUDE.md` §8A, `MINTCART_TECHNICAL_PLAN.md`
§15C/§15D), no new abstraction needed, the shared class absorbed it
unchanged. Ships with a new `mint_default/template/product/special.twig`
(same `product_cards`/sorts/limits/pagination contract as
`search.twig`, minus the refine-search form — Special has no
search-criteria UI), the five `product-card` language keys appended to
`en-gb/product/special.php`, and `text_sort`/`text_limit`/`text_empty`/
`button_continue` explicitly assigned to `$data` (the same gap-avoidance
`search.php` applied). Documented in `docs/upstream-diff.md`; consumer
lists updated in `docs/developer/theme-api/product-card-list-renderer.md`
and `docs/developer/components/product-card.md`.

**Update (a later run, same branch/PR #14): the `category.php` blank-language-string
gap (item 3 of "Next run should pick" below) is fixed.** `ControllerProductCategory::index()`'s
success branch now assigns `text_refine`/`text_sort`/`text_limit`/
`text_empty`/`button_continue` to `$data` — the same five-line pattern
`search.php`/`special.php` already established — fixing a pre-existing
OpenCart 3.0.5.1 stock bug (present on both the `default` theme and
`mint_default`) where `category.twig` referenced these keys but the
controller never assigned them, so Twig's non-strict mode silently
rendered them blank. Tracked as `docs/fixes/registry.md` MC-0001 (this
run created that registry file — it didn't exist before), full writeup in
`docs/upstream-diff.md`'s `category.php` entry. `php -l` clean; full
`tests/mintcart/` suite re-run end to end (migration manager,
`ProductViewProvider` x8, `ComponentRenderer` x12, `FooterViewProvider`
x7, presets, `ProductCardListRenderer` x8) — all pass, no regressions (the
fix touches only `$data` assignments the test suite doesn't cover at the
controller level — same pre-existing gap noted throughout this file, no
live OpenCart bootstrap available in this environment).

**Resolved (human review, 2026-08-24): the PR #14/#21 renderer conflict
this paragraph originally flagged was reconciled by comparing both
implementations directly** — #14's `ProductCardListRenderer` (this branch)
was kept: it already had three consumers (Category/Search/Special) wired,
plus a shared `MintCart\Design\ActiveTheme` helper #21 lacked (#21 carried
its own private, unshared copy of the active-theme lookup in
`manufacturer.php`, reintroducing the exact duplication the shared
renderer exists to remove). PR #21 was closed as superseded on the
renderer/Category side, and its one genuinely non-overlapping contribution
— Manufacturer wiring — was ported onto this branch's actual
`ProductCardListRenderer`/`ActiveTheme` API (dropping #21's private
`mintActiveThemeDirectory()` copy in favor of the shared class). See
`upload/catalog/controller/product/manufacturer.php`'s entry in
`docs/upstream-diff.md` for the full writeup, including **MC-0003**
(`docs/fixes/registry.md`) — the same class of blank-`$data`-string bug as
MC-0001, found in `manufacturer.php` while porting this wiring.

PR #14's original Presets increment (Clean/Warm) was likewise replaced
with PR #22's implementation for the same kind of reason — see Phase 9's
section above for that writeup. PR #22 was closed as superseded.

**Update (a later run, same branch/PR #14): MC-0002 fixed — `search.php`
never emitted `canonical`/`prev`/`next` `<link>` tags at all**, unlike its
three sibling listing controllers (`category.php`/`manufacturer.php`/
`special.php`, which all have this). This was investigated as a "check
open PRs / consider a Fix Registry candidate" item (option 4 of the
previous "Next run should pick" list below) — the two higher-priority
items (renderer reconciliation, Header) were both still blocked/covered by
open non-routine PRs at the time this run started (see below), and a
Known Fix Research Queue candidate was a genuinely reproducible, safely
scoped, non-conflicting increment. `ControllerProductSearch::index()` now
builds a `$canonical_url` from only the result-defining query params
(`search`/`tag`/`description`/`category_id`/`sub_category`, deliberately
excluding display-only `sort`/`order`/`limit`) and emits `canonical` always
plus `prev`/`next` at pagination boundaries — the exact pattern already
proven in the three sibling controllers, placed at the same relative spot
(`category.php`'s). No template change, no new language strings,
`$data['products']`/`$data['product_cards']` untouched. Full writeup:
`docs/fixes/registry.md` MC-0002, `docs/upstream-diff.md`'s `search.php`
entry. `php -l` clean; full `tests/mintcart/` suite re-run end to end
(migration manager, `ProductViewProvider` x8, `ComponentRenderer` x12,
`FooterViewProvider` x7, presets, `ProductCardListRenderer` x8) — all
pass, no regressions (this fix touches only `<head>` link tags the suite
doesn't exercise at the controller level — same pre-existing
no-live-bootstrap gap noted throughout this file). A human should confirm
on a real install that a search-results page's `<head>` now contains
`<link rel="canonical">` (and `rel="prev"`/`rel="next"` where applicable).

**Update (a later run, same branch/PR #14): Phase 11 — `McEntityPicker`
shipped.** `assets/src/js/components/entity-picker.js`
(`createEntityPicker()`) + `assets/src/css/components/entity-picker.css`
— the unified entity picker's list behavior (search, pagination,
multi-select, WAI-ARIA listbox keyboard navigation) against a
caller-supplied `fetchItems` data source, Functional Scope §8's P0
requirement. No dialog chrome of its own (documented: host it inside an
existing `mc-drawer`/`mc-modal`, call `refresh()` on that dialog's
`shown` event) and not wired into any live backend/admin screen yet —
that's Phase 12's remaining scope, once a real consumer (a product/
category/etc. field on an actual admin screen) exists, per `CLAUDE.md`
§85. Picked because Phase 11's other four primitives
(`McConfirm`/`McTabs`/`McSortable`/`McProgress`) were already covered by
open non-routine PRs at the time this run started (see "Coordination
note" above), and `McDrawer`/`McModal`/`McToast` already shipped via
Phase 4 — `McEntityPicker` and `McMediaPicker` were the only two Phase 11
items with no open PR. Doc: `docs/developer/components/entity-picker.md`.
Verified: `npm install` (0 vulnerabilities, fresh install this run),
`npm run build` and `npm run dev` both compile cleanly; `grep` confirms
every `mc-entity-picker__*` class lands in the bundled CSS, and that
`entity-picker.js` is correctly **not** pulled into `admin.min.js`/
`catalog.min.js` (no importer yet, by design — same as `mcConfirm` on the
`McConfirm` PR). No PHP touched, so `tests/mintcart/` doesn't apply. No
automated component/visual test harness exists yet in this repo (same
gap noted throughout every Phase 4/6/7/9/11 entry) — verification here is
build-compiles + bundled-output grep + manual review against the
Definition of Done checklist, same as every other JS-only primitive so
far.

**Update (a later run, same branch/PR #14): Phase 11 — `McMediaPicker`
shipped.** `assets/src/js/components/media-picker.js`
(`createMediaPicker()`) + `assets/src/css/components/media-picker.css` —
the Media UX primitive (`MINTCART_FUNCTIONAL_SCOPE.md` §25, priority P1).
Sibling of `McEntityPicker`: same caller-supplied-data-source/no-dialog-
chrome/WAI-ARIA-listbox-keyboard shape, but a thumbnail **grid** instead
of a text list (items are chosen visually), plus two things media
specifically needs that a generic entity list does not — an optional
folder `<select>` populated from `fetchFolders()` (hidden entirely when
not supplied) and optional `uploadFiles(files)`/`deleteItems(items)`
callbacks whose UI (upload control, delete button) is hidden/disabled
unless the host actually supplies the callback, per `CLAUDE.md` §85 (no
affordance offered without a real backend behind it). "Recent files" and
"image metadata" (both named in Functional Scope §25) deliberately got no
dedicated concept: a host-supplied `{ id: 'recent', label: 'Recent' }`
folder entry and the optional `getMeta(item)` label callback cover them
without inventing new component surface (`CLAUDE.md` §17). Not wired into
any live backend or admin screen yet — same Phase 12/Product-Category-
Blocks-image-field deferral `McEntityPicker` documents. Doc:
`docs/developer/components/media-picker.md`. With this, **Phase 11's two
picker primitives (`McEntityPicker`, `McMediaPicker`) are both done**;
only `McConfirm`/`McTabs`/`McSortable`/`McProgress` remain, pending
whichever land from the open (non-routine) PRs referenced above.
Verified: `npm install` (0 vulnerabilities), `npm run build` and `npm run
dev` both compile cleanly; `grep` confirms every `mc-media-picker__*`
class lands in the bundled CSS, and that `createMediaPicker` is correctly
**not** pulled into `admin.min.js`/`catalog.min.js` (no importer yet, by
design — same as `McEntityPicker`). No PHP touched, so `tests/mintcart/`
doesn't apply. No automated component/visual test harness exists yet in
this repo (same gap noted throughout every Phase 4/6/7/9/11 entry) —
verification here is build-compiles + bundled-output grep + manual review
against the Definition of Done checklist, same as every other JS-only
primitive so far.

**Update (2026-08-24, merge of PR #15): Header is done too.** Merged
separately from this branch (PR #15, `claude/charming-rubin-ozhsi6`) rather
than reconciled onto it, since by merge time this branch's own Phase
6-9-11 work had already landed on `master`. See the Phase 7 section above
for the full writeup, including the `ActiveTheme` consolidation
`header.php` needed during reconciliation (it originally shipped with its
own private `mintActiveThemeDirectory()` copy, duplicating what
`footer.php`/`category.php`/`search.php`/`special.php`/`manufacturer.php`
had already consolidated onto the shared class by then).

**Next run should pick, in order of preference:**

1. ~~Reconcile the two `ProductCardListRenderer` implementations~~ —
   **done** (human review, 2026-08-24). This branch's shape (`render($product,
   $display, $context, $languageStrings, $onError)` + `isEnabled()`,
   caller-supplied logging) was kept over #21's (constructor-injected
   `Logger`/text, `hasFailed()`); #21 was closed as superseded, and its one
   non-overlapping contribution — Manufacturer wiring — was ported onto
   this shape in PR #24. All four consumers
   (Category/Search/Special/Manufacturer) now share it. See the resolved
   note above (where the "Manufacturer was deliberately NOT wired"
   paragraph used to be) and `docs/upstream-diff.md`'s `manufacturer.php`
   entry.
2. ~~Header (Phase 7)~~ — **done**, merged via PR #15 (see the update just
   above).
3. ~~The pre-existing `category.twig`/`category.php` blank-language-string
   gap~~ — **done.** See the Phase 8 update above and
   `docs/fixes/registry.md` MC-0001.
4. ~~A `docs/fixes/registry.md` Known Fix Research Queue candidate~~ —
   **done, this run.** `search.php`'s missing canonical/prev/next links,
   see MC-0002 above. Other Known Fix Research Queue candidates from
   `MINTCART_FUNCTIONAL_SCOPE.md` §18 remain uninvestigated (database
   reconnect, multilingual SEO URL issues, category path/update issues,
   cache race conditions, extra session/cart writes, installer edge cases)
   — a future run could pick one, following the same reproduce-first
   discipline as MC-0001/MC-0002. A grep for other historically-known PHP
   8.x incompatibilities (`each()`, `create_function`, curly-brace string
   offsets, `FILTER_SANITIZE_STRING`, PHP4-style constructors, mysqli
   strict-report-mode exceptions) found none present in this baseline —
   `mysqli_report(MYSQLI_REPORT_ERROR)` is already set correctly in
   `system/library/db/mysqli.php`, and no scalar-typed `= null` parameters
   exist anywhere in this codebase (so PHP 8.4's "implicitly nullable
   parameter" deprecation doesn't fire either) — so that specific
   Known-Fix-Research-Queue candidate is provisionally clear on this
   baseline, not a live gap to fix.
   **Update (a later run): one more PHP 8.x hit found and fixed —
   `catalog/model/extension/payment/opayo.php` called the deprecated
   `strftime()` (deprecated since PHP 8.1), missed by the grep above since
   that pass didn't check for `strftime`/`gmstrftime` specifically. Fixed
   as **MC-0010** (`docs/fixes/registry.md`), replaced with the
   byte-identical, non-deprecated `date('YmdHis')`; new
   `tests/mintcart/fixes/mc-0010-opayo-strftime-test.php` confirms both the
   format match and that the deprecation notice is gone. A repo-wide grep
   confirmed this was the only occurrence of `strftime`/`gmstrftime`
   anywhere under `upload/`. The other Known Fix Research Queue candidates
   listed above (database reconnect, multilingual SEO URL issues — now
   separately addressed by MC-0009 per an open PR, category path/update
   issues, cache race conditions — MC-0007, extra session/cart writes —
   MC-0008, installer edge cases — MC-0005) remain the natural next picks;
   re-check `docs/fixes/registry.md` and open PRs for the current state of
   each before starting one, since several have already landed as
   still-unmerged PRs elsewhere in this backlog.
5. With 1–4 either blocked on a human merge decision or already covered,
   consider **Phase 18's system-route-URL-alias gap**
   (`MINTCART_FUNCTIONAL_SCOPE.md` §4) — note this is data-seeding/UI work
   more naturally gated on Phase 13 (Setup Wizard) existing as a real
   consumer per `CLAUDE.md` §85, so check whether a wizard step exists
   before building a standalone seeder with no caller — or check open PRs
   again for anything newly landed/merged before picking a fresh phase.
6. ~~`McMediaPicker` (Phase 11's last unclaimed admin primitive)~~ —
   **done, this run.** See the Phase 11 update above. Both Phase 11 picker
   primitives (`McEntityPicker`, `McMediaPicker`) are now shipped; only
   `McConfirm`/`McTabs`/`McSortable`/`McProgress` remain, pending whichever
   land from open (non-routine) PRs — check those first before redoing.
7. **Enqueue MintCart's own asset bundle** (`assets/dist/{css,js}`) on a
   live Mint page — the single most important remaining piece of plumbing
   surfaced by the Header merge: no `mc-*` CSS currently has any visible
   effect on a real page (nothing links `index.min.css` anywhere). Needs a
   call-site audit of every page/widget depending on
   jQuery/`common.js`/Bootstrap 3 first (`CLAUDE.md` §10/§84) — start with
   `docs/developer/components/header.md` "Known gap" for the full
   rationale and what's already been deliberately deferred.

Re-read the relevant section above (and `docs/developer/theme-api/product-card-list-renderer.md`/
`docs/developer/components/footer.md` for the established patterns to
mirror) in full before starting, per `CLAUDE.md` §10/§84 — don't start
from this summary alone.

**Update (2026-08-24, merge of PR #20): all nine Phase 11 admin
primitives are now done** (`McDrawer`/`McModal`/`McToast`/`McConfirm`/
`McSortable`/`McTabs`/`McProgress`/`McEntityPicker`/`McMediaPicker` — see
the Phase 11 section above). With the PR backlog fully resolved (see the
"PR backlog status" note near the top of this section) and Phase 6/8's
listing pipeline, Phase 7's Header/Footer, Phase 9's Presets, and Phase 11
all complete, the natural next candidates are:

- **Phase 10 — Block Provider/Renderer/Template** (Products block first,
  reusing the already-shipped `ProductViewProvider`): now fully unblocked
  — `mc_page`/`mc_block` storage (PR #16) is on `master`.
- **Phase 12 — Entity Picker**: `McEntityPicker`'s list primitive already
  shipped; this phase is about wiring real `fetchItems` backends per
  entity type (products first) and a real admin-screen consumer — scope
  the first increment to one entity type, per `CLAUDE.md` §17/§20.
- **Enqueue MintCart's own asset bundle** on a live page (Phase 7's
  carried-forward gap — see that section above) — the single most
  important remaining piece of plumbing, since no `mc-*` CSS/JS has any
  visible effect on a real page yet.

Phase 1's remaining items (`MintCart\Compatibility\OpenCart3\*` adapters,
health checks) stay intentionally deferred — no real consumer yet. CI is
now covered (`.github/workflows/ci.yml`, merged via PR #23).

---

## Update (2026-08-24, human session — read this first)

A human session (not a routine run) did a full manual pass after the
above: merged the rest of the PR backlog (#25, #26, #27 — see Phase 15
and Phase 10 below), and — importantly for future routine runs —
**verified several previously-"unverified in this sandbox" items against
a real local install** the human has access to (`http://mintcart.localhost/`,
a configured MySQL database). **This does not change anything about a
routine's own sandbox** — a routine session still has no MySQL/live
install of its own and should keep noting "human should verify on a real
install" for anything it can't test standalone, exactly as before. The
items below are now confirmed, not still-open verification gaps:

- Phase 8's Theme resolver milestone: `test_theme` overriding
  header/footer/product-card, `mint_default` falling back to its own DOM
  correctly in both directions, `default` theme completely unaffected —
  all confirmed live via `curl` against real category/search/special/
  manufacturer pages.
- Phase 10's `mc_page`/`mc_block` migrations: run against real MySQL,
  schema confirmed exactly matching the migration source
  (InnoDB/utf8mb4/`LONGTEXT` etc.), idempotency confirmed on a second run.
- **Phase 15 (Commerce API) has a first real increment**: `MintCart\Commerce\CartService`
  + `ControllerMintcartCart` (route `mintcart/cart/{add,remove,update,refresh}`,
  fully additive, zero existing OpenCart file modified) + a Cart JS client
  (`assets/src/js/commerce/cart-client.js`, `add/remove/update/refresh` +
  `mc:cart:updated`) + `mc-product-card`'s Add to Cart button now actually
  works + the legacy header cart widget syncs itself on the same event.
  Docs: `docs/developer/commerce/cart-service.md`,
  `docs/developer/commerce/cart-client.md`. **Do not rebuild any of this**
  — check `docs/developer/commerce/` and the Phase 15 section below before
  touching cart-related code.
- **A blanket enqueue of `assets/dist/css/index.min.css` was tried and
  reverted** (see `upload/catalog/view/theme/mint_default/template/common/header.twig`'s
  own comment) — it broke legacy Bootstrap-3 widgets (navbar/dropdown,
  search overlapping cart) by loading full Bootstrap 5 CSS alongside them,
  confirmed on a real page, not theoretical. **Do not re-attempt a plain
  `<link>` enqueue.** The real fix is either (a) scoping `mc-*` CSS to not
  pull in Bootstrap 5's global reboot/base layer, or (b) migrating the
  remaining legacy header widgets (search, account, menu — cart is now
  Mint-aware) to Mint-native components first, the same way Cart just was.

**Recommended next increments** (unchanged in spirit from above, just
reconfirmed current): Phase 10's Block Provider/Renderer (Products block
first, `ProductViewProvider` already exists to reuse), Phase 12's Entity
Picker real backends, or continuing Phase 15 with `SearchService` —
note Search has no dormant markup/hook waiting for it the way Cart did
(product-card's Add to Cart button already existed from Phase 6), so it
would need both the endpoint and a new Mint-native search UI, a larger
scope than Cart was; consider scoping the first increment to the endpoint
alone if picked.

**Update (this run, no MySQL access used — see the memory-recorded rule
against touching local shared infra without asking first): Phase 10's
Block Provider/Renderer is done, Products block end-to-end.** See the
Phase 10 section above ("Sub-step 2") for the full writeup. Next
candidates: the remaining first-wave block types (Hero, Categories,
Banner Grid, Benefits, Text/Image, Brands, Testimonials, FAQ), the
Homepage drag-and-drop editor/admin UI (no way yet to create an
`mc_block` row without inserting it by hand), Phase 12's Entity Picker
real backends, or continuing Phase 15 with `SearchService`.

**Update (a later run): Phase 13 — Setup Wizard has a first increment
(Welcome + Store steps, PR #40).** All nine Homepage Block types (Hero,
Categories, Banner Grid, Benefits, FAQ, Testimonials, Brands, Text/Image,
Newsletter) were already covered by open PRs (#30-#39) at the time this run
started, so Phase 13 was picked instead — see its section above for the
full writeup, including the standalone-page/asset-enqueue design decision
and the `editSetting()` partial-write trap it avoided. Next candidates:
the remaining 7 Wizard steps (`contacts` onward), Phase 12's Entity Picker
real backends, or check `gh pr list --state open` again for what's landed
from the Phase 10 block PRs before picking a fresh phase.

**Update (this run): Phase 12's category `fetchItems` backend is done**
(`mintcart/entity/category`, see the Phase 12 section above for the full
writeup). At the time of this run, PRs #30-#44 (15 open) already covered
every remaining first-wave Homepage Builder block type and a Setup Wizard
shell, so this run picked Phase 12 — genuinely unclaimed, and the natural
next Phase 12 item per the "Coordination note"/"Recommended next
increments" above. Live wiring into `product_form.twig` was deliberately
not attempted (blocked on the admin asset-enqueue problem — see Phase 12
above); a future run could tackle that enqueue problem, add a second
entity type's backend (product/manufacturer/customer), or check whether
the 15 open block PRs have landed and pick up the Homepage drag-and-drop
editor next.

**Update (later run): the fourteen remaining first-wave block types above
each got their own open pull request** (see step-0 of that run's
duplicate-work check), so the Homepage Builder admin editor was picked
instead — see Phase 10's "Sub-step 3" above. It confirms, independently,
the same Bootstrap-3/5 admin-CSS conflict the note just above this one
already found on the storefront side (`header.twig`'s reverted blanket
enqueue) — the admin theme's shared chrome has the identical risk, never
tested live, so the new admin screen deliberately stays on the legacy
admin theme rather than repeating that experiment. Next candidates:
Phase 12's Entity Picker real backends (category's is now an open PR;
products/manufacturers/attributes/options/information/customers are not),
Phase 13's remaining Setup Wizard steps (Welcome/Store are an open PR;
Contacts through Ready are not), Phase 14 (Store Readiness/Business
Settings, not started), or resolving the Bootstrap-3/5 admin-shell
question itself (`docs/developer/blocks/homepage-admin.md`) if the project
owner has a direction.

**Update (this run): Phase 15 — `MintCart\Commerce\SearchService` + the
`mintcart/search/suggest` AJAX endpoint are done.** All of the above
candidates (every first-wave block type, the Homepage admin editor, and
Phase 12's category Entity Picker backend) were already claimed by other
open PRs at the time this run started (checked via `gh pr list --state
open`, 17 open PRs at #30-#46), so this run picked the one remaining
unclaimed candidate instead. See the Phase 15 section above and
`docs/developer/commerce/search-service.md` for the full writeup. Next
unclaimed candidates once those PRs land: a Mint-native search-box UI
component calling this endpoint, `QuickOrderService`, or re-checking open
PRs for whatever's newly unclaimed.

---

## Update (2026-08-28, routine run) — Phase 14 started

Step 0 dedup check against all 18 open PRs at the time (`#30`–`#47`):
15 are one Homepage Builder block type each (Phase 10), `#45` is the
Phase 12 category `McEntityPicker` backend, `#46` is the Phase 10
Homepage Builder admin editor, `#40` is the Phase 13 Setup Wizard shell
(Welcome + Store), and `#47` is the Phase 15 `SearchService`. None touch
Phase 14 (Store Readiness / Business Settings) — confirmed both by
`gh`/GitHub MCP search across open PR bodies for "StoreReadiness",
"Readiness", "Onboarding", and "Business Settings" (only #40/#46
mention "Readiness"/"Business Settings" in passing, as their own
*next-step* suggestions, not as work done) and by reading both PRs'
bodies in full.

Shipped this run: `MintCart\Onboarding\StoreReadiness` (pure, no
OpenCart dependency) + `ModelMintcartReadiness` (real signal gathering)
+ `ControllerMintcartReadiness` (`mintcart/readiness`, GET, JSON) — see
the Phase 14 section above and `docs/developer/onboarding/store-readiness.md`
for full detail. This is a self-contained increment (service + its first
real consumer); it does not touch any file the 18 PRs above touch.

**Next step:** Phase 14's remaining items — `Business → Contacts/Delivery/Payments`
UI façades, and a dashboard widget or admin menu entry surfacing
`mintcart/readiness`'s checklist (no admin UI renders it yet, JSON
endpoint only) — or continuing Phase 13 (remaining Wizard steps, with
`ready` wired to this new endpoint once #40 lands), or Phase 12's
remaining Entity Picker backends beyond category, or Phase 15's
`QuickOrderService`/`CheckoutService`.

**Update (this run): at the time of this run, PRs #30-#48 (19 open) already
covered every first-wave Homepage Builder block, the Homepage drag-and-drop
editor, a Setup Wizard shell, the category Entity Picker backend,
`StoreReadiness`, and `SearchService` — so this run picked Phase 17
(Categories drag & drop), genuinely unclaimed and untouched by any of them
(confirmed via each open PR's changed-files list before starting). See the
Phase 17 section above for the full writeup. Two features
(Phase 12's `product_form.twig` wiring and this phase's `category_list.twig`
wiring) are now both blocked on the same unsolved admin asset-enqueue
problem — a good candidate for a future run to tackle on its own.

**Update (reconciliation): PRs #30-#44 (Hero, Categories, Banner Grid,
Benefits, FAQ, Testimonials, Brands, Text/Image, CTA, Newsletter, Spacer,
Text, HTML, Gallery blocks) merged.** All fourteen landed with their own
Provider/Renderer/Twig/CSS, including the shared `mc-prose` rich-text
container. The full first-wave block catalog is now on `master`; only
PR #50 (manufacturer entity picker, Phase 12) remains in the backlog.

**Update (later run): PR #46 (Homepage Builder admin editor) merged, and
by then #40 (Wizard Welcome/Store) and #45 (category entity picker) had
too.** This run picked Phase 12's second entity backend (manufacturer —
see that phase's section above) after confirming, at step 0, that open
PRs #47 (Phase 15 `SearchService`), #48 (Phase 14 `StoreReadiness`) and
#49 (Phase 17 Categories drag & drop) plus the still-open block-type PRs
(#30-44) didn't overlap it. Remaining unclaimed candidates for a future
run: a third entity backend (product/attribute/option/information
page/customer), Phase 13's Contacts-through-Ready wizard steps, Phase 16
(Bulk Editor, not started), Phase 18 (SEO Core, not started), or checking
whether #47/#48/#49/the block PRs have landed.

**Update (reconciliation): PR #50 (manufacturer entity picker backend)
merged.** The whole backlog of parallel routine-run PRs (#30-#50) is now
reconciled onto this branch.

**Update (reconciliation follow-up): `mc-block--<type>` replaced with
`data-mc-block-type="<type>"` across all 15 Homepage Builder block
templates.** A per-type BEM modifier on the shared `.mc-block` wrapper
described "which type this is" (data), not a style variant of the same
component — a stretch of BEM's own definition of "modifier." Block type is
now a `data-mc-block-type` attribute (CLAUDE.md §14's `data-mc-*` hook
convention); the two types with real CSS keyed on it (Hero, Spacer) now
target `.mc-block[data-mc-block-type="..."]`. Spacer's `size` *is* a
genuine style variant, so it kept a real BEM modifier — moved onto its own
dedicated `mc-spacer`/`mc-spacer--{sm,md,lg,xl}` block instead of piggy-
backing on the type-discriminator class. Updated: all 15
`blocks/*.twig` templates, `assets/src/css/components/{block,banner-grid,gallery}.css`,
`tests/mintcart/blocks/block-renderer-test.php` (all 14 scenarios re-verified
passing), and the `docs/developer/blocks/*.md` reference docs. Historical
entries above this line describe each block as it shipped at the time
(`mc-block--<type>`) — left as-is; they're a changelog, not living
documentation.

**Update (reconciliation follow-up): `ControllerDesignHomepage::TYPES`
extended to all 15 shipped block types, replacing the Products-only
hardcoded settings form.** Full writeup: `docs/developer/blocks/homepage-admin.md`
("Block type registry" / "Content vs. settings" sections). Summary: one
declarative `fieldSchema()` (settings/content field list per type,
matching each type's own `*BlockProvider::build()`) drives
`getForm()`/`prepareBlockData()`/`validateForm()` generically instead of
15 hardcoded near-duplicate blocks; `homepage_form.twig` renders whatever
fields the current type declares. The type `<select>` reloads live via a
plain `onchange` GET reload (no JS bundle needed — the admin
asset-enqueue question above is still unresolved). Banner Grid, Benefits,
Testimonials, Brands, and Gallery (the five `items`-repeater types) get a
raw JSON textarea per items list rather than a real add/remove-row UI,
since that also needs the same unresolved JS-bundle decision — a genuinely
working escape hatch, not a placeholder, with inline help text showing
the exact JSON shape and `error_json` validation on malformed input.
Replacing it with a real repeater is follow-up work once the admin
asset-enqueue question is resolved.

New test: `tests/mintcart/design/homepage-admin-schema-test.php` (6
scenarios — flat-type round-trip, JSON-items round-trip, default
fallbacks incl. Products' `category_id: null`, a tampered `select` value
falling back to its default, `validateForm()` rejecting malformed JSON
while accepting blank, and every one of the 15 types' field schema having
a resolvable language string for every `label_key`/`help_key`). Verified:
`php -l` clean on every touched/added PHP file; full standalone test
suite re-run end to end — all passing; Twig `{% if %}`/`{% endif %}`/
`{% for %}`/`{% endfor %}` tag counts balanced in the rewritten
`homepage_form.twig`. Same pre-existing gap as every earlier phase: no
live MySQL/OpenCart admin session in this environment — a human should
work through `docs/developer/blocks/homepage-admin.md`'s "Manual
verification" steps 7-9 (added this run) on a real install.

**Update (reconciliation follow-up): drag-to-reorder added to the block
list, without `McSortable`.** The admin asset-enqueue question (no
MintCart JS bundle enqueued on this screen — see
`docs/developer/blocks/homepage-admin.md`'s "Why this screen uses the
legacy admin theme" section) still blocks using `McSortable` itself, so
this is a small self-contained inline `<script>` in `homepage_list.twig`
using the browser's native HTML5 Drag and Drop API instead: dragging a
row (`draggable="true"`, an `fa-bars` grip icon) reorders the table and
renumbers the existing `sort_order` number inputs to match; "Save Order"
submits exactly as before. The number inputs stay fully visible/editable
either way — MintCart's own non-pointer-alternative requirement for
sortable operations (`MINTCART_IMPLEMENTATION_GUIDE.md` §68) native HTML5
drag doesn't satisfy on its own. Full writeup: "Reordering without
`McSortable`" in `docs/developer/blocks/homepage-admin.md`. Verified: `node
--check` on the extracted inline script (valid syntax); Twig tag counts
re-balanced after the added grip-icon column; manual verification step 10
added.

**Update (this run, 2026-09-08): Phase 11 (Technical Plan)'s Update Manager
has a first increment — `MintCart\Update\PackageManifest`.** At the time
this run started there were 23 open PRs covering nearly every phase with
unclaimed, unstarted work in this file (Diagnostics, Compatibility
Checker, QuickOrderService, SEO Core's four sub-pieces, HealthCheck,
System route URL aliases, Business Settings' three façades, two more
Wizard steps, Bulk Editor, the search-box component, the Store Readiness
dashboard widget, and five of the six remaining Entity Picker backends —
see each PR's own title), and `column_left.php` (the admin menu file) was
already touched by ten of them, so a new admin-menu-adding increment was
avoided this run. Update Manager was the one phase with real scope and no
open PR anywhere near it — see the Phase 11 (Technical Plan) section above
and `docs/developer/update/package-manifest.md` for the full writeup.
**Next step:** the rest of the Update Manager flow (a `check` step that
fetches a real manifest and calls `PackageManifest::isCompatible()`
against `MintCart\Core\Version`, then backup/download/verify/maintenance/
files/migrations/cache-clear/health-check in later increments once
`HealthCheck`, per PR #67, has landed) — or re-check `gh pr list --state
open` for whatever the current backlog has newly merged/unclaimed before
picking the next phase.

**Update (2026-09-08): Phase 1's `SettingsAdapter` shipped.** At
the time of this run there were 28 open PRs (`#52`–`#80`). `wizard.php`/
`wizardprogress.php` (2 PRs: `#52`, `#64`), `admin/controller/mintcart/entity.php`
(5 PRs: `#53`–`#57`), and `column_left.php` (multiple PRs adding menu
groups) were all treated as blocked hot files per this file's own §0f
rule and avoided entirely. `MintCart\Compatibility\OpenCart3\SettingsAdapter`
(Phase 1's own remaining checklist item) was genuinely unclaimed and
implementable as new files only — see the Phase 1 section above and
`docs/developer/compatibility/settings-adapter.md`. **Next step:** wire
`SettingsAdapter` into `ControllerMintcartWizard::saveStoreStep()` once
`wizard.php` stops being contended by `#52`/`#64`; a second adapter
(`ProductAdapter`/`CategoryAdapter`/...) once one has a real consumer; a
`MintCart\Core\HealthCheck` surface (Phase 1's other remaining item,
already claimed by open PR `#67` as of this writing — check first); or
re-run `gh pr list --state open` for whatever has newly merged/unclaimed.

**Update (2026-09-08): `MintCart\Update\UpdateChecker` — the
`check` step.** Second Update Manager increment: given a `PackageManifest`
plus installed versions, returns `update_available`/`compatible`/
`should_apply` (`upload/system/library/mintcart/update/updatechecker.php`,
`tests/mintcart/update/update-checker-test.php`, 7 scenarios). Full
writeup in `docs/developer/update/package-manifest.md`'s new "The `check`
step" section. At the time this run started, 28 PRs were open (`#52`–`#81`)
and none touched `upload/system/library/mintcart/update/` (confirmed via
`list_pull_requests` + a manual read of PR #76, whose branch name
`claude/update-manager-package-manifest` is misleading — its actual content
is an unrelated fix, MC-0004). Verified: `php -l` clean; both
`update/*-test.php` files plus the full `tests/mintcart/` suite (35 files)
passing, no regressions. **Next step:** still no caller — the `backup`
step (reading a manifest's `files()` to snapshot what an update would
overwrite) is the next natural increment, or a `download`/`verify` step
once there's a concrete transport to fetch a manifest+package from. The
`health-check` step should still wait for `MintCart\Core\HealthCheck`
(PR #67) to land. Re-check the open-PR list before picking, as always —
the backlog is large and grows with every parallel run.

**Next step:** the admin asset-enqueue question remains the real blocker
for both a proper `McSortable`-based reorder and the five `items` JSON
textareas becoming a real add/remove-row repeater — see
`docs/developer/blocks/homepage-admin.md`'s "Why this screen uses the
legacy admin theme" section. Swapping `category_id`/`parent_id` for a
real `McEntityPicker` (Phase 12's category backend now exists) is a
separate, self-contained follow-up untouched by that question.

**Update (this run, 2026-09-11): Phase 16 (Bulk Editor) has a first
increment — `MintCart\Catalog\BulkProductUpdatePlanner`.** At the time
this run started there were 50 open PRs; none targeted Phase 16, so it was
genuinely unclaimed (see the Phase 16 section above and
`docs/developer/catalog/bulk-product-update.md` for the full writeup).
This run also found `origin/master` already carrying the Phase 11 Update
Manager `PackageManifest` commit from a prior run's direct push (not
merged via any of the 50 open PRs) — noted here in case it looks like an
unexplained jump to whoever reviews next. **Next step:** the
load-merge-`editProduct()` apply loop + admin route for Bulk Editor (see
Phase 16 above), or re-check the open PR list before picking the next
phase, since 50 PRs is a large backlog to reconcile.
**Update (this run, 2026-09-11): Phase 1's `CategoryAdapter` shipped, wired
into `ControllerCatalogCategory::move()`.** See the Phase 1 section above
for the full writeup. At the time of this run there were 54 open PRs
(`#52`–`#106`); `ProductAdapter`/`OrderAdapter` are the last two unstarted
named adapters from `MINTCART_TECHNICAL_PLAN.md` §5 — re-check `gh pr
list --state open` first before picking either, this backlog grows fast.
**Update (this run, 2026-09-11): Phase 1's adapter layer gets a fourth
adapter — `MintCart\Compatibility\OpenCart3\CustomerAdapter`.** At the
time this run started there were 54 open PRs; `git ls-remote` showed
`master` itself only as far as PR #51 (Homepage Builder block editor)
plus a directly-pushed "Phase 11: PackageManifest" commit (not via PR —
noted here as an anomaly, not something this run attempted to undo).
Checked all 54 open PR titles/head branches for overlap before starting;
confirmed via `git diff <branch> --stat` against `#81` (`SettingsAdapter`)
and the branch behind `#87` (`ExtensionAdapter`) that neither, nor any
other open PR title, covers `ProductAdapter`/`CategoryAdapter`/
`OrderAdapter`/`CustomerAdapter` — Technical Plan §5's remaining three
named adapters plus this one. Picked `CustomerAdapter`: OpenCart ships two
customer models (`admin/model/customer/customer.php`,
`catalog/model/account/customer.php`) with diverging method sets that
happen to share `getCustomer()`/`getCustomerByEmail()`; this adapter
normalizes that shared surface (see
`docs/developer/compatibility/customer-adapter.md`). New files only
(`upload/system/library/mintcart/compatibility/opencart3/customeradapter.php`,
its test, this doc) — touches no file any open PR touches, so no hot-file
collision. Like `SettingsAdapter`/`ExtensionAdapter`, not wired into any
controller yet (no checkout/order call site exists in this codebase yet).
Verified: `php -l` clean; the new standalone test
(`tests/mintcart/compatibility/opencart3/customer-adapter-test.php`)
passes end to end, including against a second, differently-shaped
duck-typed double standing in for the storefront model.

**Next step:** `ProductAdapter`/`CategoryAdapter`/`OrderAdapter` remain
the last unclaimed named adapters (check `gh pr list --state open` again
first — this backlog grows fast); or re-check whether any of the 54 open
PRs listed here have since merged, since almost every phase's remaining
checklist items in this file are already claimed by one of them and a
fresh `gh pr list` pass is the only reliable way to find real unclaimed
scope right now.
## Update (2026-09-11, routine run) — MC-0014 fix (Fixes Registry)

Step-0 dedup check against all 52 open PRs (`#52`-`#104`) found the backlog
extremely saturated: essentially every unstarted Phase 10-25 item already
had an open PR (all first-wave block types, all six Entity Picker
backends, all remaining Wizard steps, Business Settings façades, SEO
Core's five sub-pieces, Bulk Editor, the search-box component, the Store
Readiness dashboard widget, Update Manager's `UpdateChecker`/
`BackupPlanner`/`PackageVerifier`, Compatibility Checker, Diagnostics,
`CacheInvalidator`, `HealthCheck`, both remaining `MintCart\Compatibility\OpenCart3\*`
adapters, mobile header nav, `CheckoutTotalsService`, `QuickOrderService`,
product detail page template, three ADRs for RESEARCH items, the release
packaging script, user docs, and ten fixes MC-0004 through MC-0013). Every
one of these already has real, unmerged work sitting in an open PR — see
each PR's own title via `gh pr list --state open`/the GitHub MCP
equivalent before starting a fresh increment in any of those areas.

Rather than gamble on a hot shared file (per step 0f — `column_left.php`,
the Homepage Builder block registry, and the Wizard controller are all
touched by 2+ open PRs already, per the "Update (this run, 2026-09-08)"
note above and PRs `#52`/`#64`/`#84` all touching the Wizard flow), this
run picked a genuinely fresh, single-file, self-contained item instead:
**MC-0014** (`docs/fixes/registry.md`) — `klarna_account.php`/
`klarna_invoice.php` calling the PHP-8.2-deprecated
`utf8_encode()`/`utf8_decode()`, replaced with the byte-for-byte
equivalent `mb_convert_encoding()`. No open PR touches either Klarna file
(confirmed by title — none of the ten open MC-fix PRs mention Klarna,
opayo, or any payment/klarna* path) and a Fixes Registry entry is
inherently single-file/low-collision by construction, per the Fixes
Policy (`CLAUDE.md` §13).

See `docs/fixes/registry.md`'s new MC-0014 entry and
`docs/upstream-diff.md`'s new `klarna_account.php`/`klarna_invoice.php`
entry for the full writeup. Verified: `php -l` clean on both modified
files (and every other `.php` file in `upload/`/`tests/`, re-checked with
a full-repo sweep); new
`tests/mintcart/fixes/mc-0014-klarna-charset-test.php` (17 assertions,
using PHP's own still-callable-but-deprecated `utf8_encode()`/
`utf8_decode()` as the equivalence oracle); the full `tests/mintcart/`
suite (34 files) re-run end to end — all passing, no regressions. No
`npm`/frontend files touched, so the JS/CSS build wasn't re-run this
increment. Same pre-existing gap as every fix in this registry: no real
Klarna merchant sandbox account in this environment, so no live payment
request was exercised — a human should confirm a real Klarna
Invoice/Account checkout still completes on a PHP 8.2+ install.

**Next step:** re-run `gh pr list --state open` first — with 52+ PRs in
flight, most of the obvious next candidates are already claimed. If the
backlog hasn't shrunk, good unclaimed candidates that stay clear of the
hot shared files identified above are: another Known Fix Research Queue
item (MC-0015+ — search `upload/` for other stale PHP patterns not
already covered by MC-0004 through MC-0014, e.g. other deprecated
built-ins in third-party payment/shipping extensions), or a documentation-
only ADR/developer-doc increment that doesn't touch any file another PR
already owns. Once the current backlog of 50+ PRs actually lands on
`master`, a full re-audit of `docs/IMPLEMENTATION_STATUS.md` against
what's really left is overdue — this file's per-run "Next step" notes were
written against a much smaller backlog (as few as 15-23 open PRs) and are
now significantly stale about what's genuinely available to pick up.
**Update (this run, 2026-09-10):** with 50 open PRs at step 0 covering
nearly every remaining phase (fixes MC-0004–MC-0013, all four remaining
SEO Core pieces, three more Update Manager steps, five Entity Picker
backends, the admin asset-enqueue problem itself, and more — see each
PR's own title), and no untouched code area found that didn't risk
colliding with one of them, this run picked the completely unclaimed
`docs/user/` gap instead (zero files existed there) — see the new
"Documentation" section above. Next run: re-check `gh pr list --state
open` first (the backlog is large and merges happen between runs); if it's
still this saturated, another `docs/user/` guide or the developer
architecture overview are safe, low-collision picks.
**Update (this run, 2026-09-10): Update Manager's `verify` step —
`MintCart\Update\PackageVerifier`.** At the time this run started there
were 49 open PRs (see `gh pr list --state open`), covering nearly every
remaining phase, several already contending for the same shared files
(e.g. `upload/admin/controller/mintcart/entity.php` is touched by five
open entity-picker-backend PRs, `column_left.php` by many more) — those
areas were avoided this run per the routine's shared-file-collision
guard. Update Manager's `verify` step was unclaimed by any open PR and
self-contained (new files only, no shared-file contention):
`PackageVerifier::verifyChecksum()` checks a downloaded archive against
`PackageManifest`'s declared `checksum`; `PackageVerifier::missingFiles()`
checks an extracted package directory against the manifest's declared
`files()` list. Full writeup: `docs/developer/update/package-verifier.md`.
**Next step:** the remaining Update Manager stages (`check` — a real
manifest fetch + `PackageManifest::isCompatible()` call against
`MintCart\Core\Version`; `download`; `backup`; `maintenance` mode; the
actual file-copy/migration-runner/cache-clear/health-check steps) are
still open, though `check` (PR #82) and `backup` (PR #86) already have
open, unmerged PRs — re-check `gh pr list --state open` before starting
either. Given the scale of the current open-PR backlog (49 as of this
run, the large majority marked `ROUTINE-STATUS: DONE` but still awaiting
human review/merge), a human merge pass is likely more valuable right now
than further new increments — most of the "next unclaimed step" surface
in this file is already covered by an open PR.
**Update (this run, 2026-09-10): MC-0013 (SMTP `EHLO`/`HELO` hostname
fallback) added.** At step 0, ~55 open PRs were already covering nearly
every unclaimed checklist item across every phase in this file (all
remaining Entity Picker backends, all remaining SEO Core sub-pieces, both
remaining Wizard steps with real scope, all three Business Settings
façades, the Store Readiness dashboard widget, Bulk Editor, Diagnostics,
Compatibility Checker, QuickOrderService/CheckoutTotalsService, the
search-box component, mobile navigation, Update Manager's next increments,
and MC-0004 through MC-0012), and several areas (`wizard.php`/`wizard.twig`
touched by 3 open PRs, the Homepage Builder block registry/`block.css`
touched historically by many more) were already at or past the step-0f
shared-file collision threshold. `system/library/mail/smtp.php` was
untouched by any open PR and unrelated to every claimed area, so this run
picked it as a fresh, isolated Fix Registry candidate instead of any
checklist phase item — see `docs/fixes/registry.md`'s MC-0013 entry and
this file's Phase 10 (Technical Plan) section above for the full writeup.
**Next step:** re-run `gh pr list --state open` before picking anything —
given the current backlog size, most straightforward checklist items are
likely already claimed; a fresh Fix Registry candidate (per
`MINTCART_FUNCTIONAL_SCOPE.md` §18) or documentation-only work (a new ADR
for a flagged-but-undecided architectural question) is more likely to be
genuinely uncontested than another feature-phase increment. Also worth a
human's attention independent of any single run: the sheer number of open,
unmerged PRs itself — a merge/reconciliation pass (like the one documented
above for the original nine-PR backlog) would likely be more valuable than
another routine run adding to the pile.
**Update (this run, 2026-09-10): MC-0012 shipped —
`ModelSettingSetting::editSetting()` is now transactional.** See Phase 10
(Technical Plan)'s section above and `docs/fixes/registry.md` for the full
writeup. This repo had 48 open PRs (`#52`–`#98`) at the start of this run;
re-check `gh pr list --state open` for what's newly merged/unclaimed
before picking the next increment — most named phases already have an
open PR, so the remaining Known Fix Research Queue items (multilingual SEO
URL issues, category path/update issues beyond Phase 17, further
"installer edge cases") or Diagnostics dev mode are reasonable next
candidates once the current backlog clears.
**Update (this run, 2026-09-09): Phase 11's `McMediaPicker` primitive got
its real backend** (`design/media/list`/`/folders`/`/upload`/`/delete` —
see that phase's section above and `docs/developer/design/media-library.md`).
44 open PRs (`#52`-`#95`) were checked at step 0; none touch Media UX or
this run's new files, and the four contended "hot" files identified
(`mintcart/wizard.php`+`wizard.twig`+`wizardprogress.php`, 3 PRs;
`mintcart/entity.php`+`column_left.php`, 2+ PRs per PR #83's own writeup)
were left untouched. **Next step:** the same admin asset-enqueue question
blocking Homepage Builder's `McSortable`/repeater work above also blocks
mounting `mc-media-picker` itself on any real screen — resolving it
(tracked as an open question since Phase 10's admin editor) would unblock
both `McEntityPicker` and `McMediaPicker` at once. Otherwise: re-check
`gh pr list --state open` first, this backlog is unusually large.
**Update (2026-09-09, routine run): Modern Editor RESEARCH item resolved.**
Step 0 found 46 open PRs (#52–#94) at the start of this run, covering
nearly every phase (all Phase 12 entity-picker backends, most Setup Wizard
steps, all Business Settings facades, Phase 15 Commerce services, six
separate Phase 18 SEO builders, five Fixes-registry entries, and — a
second instance of the PackageManifest duplication pattern first seen with
the Homepage Builder blocks — at least four open PRs (#82, #85, #86, #94)
each independently shipping their own copy of `MintCart\Update\PackageManifest`).
Phase 11 (Technical Plan "Update/Compatibility") was treated as blocked
per the shared-file/hot-area collision guard and avoided entirely. Picked
Functional Scope §24 (Modern Editor) instead: genuinely unclaimed (no open
PR title or body mentions "editor"/"summernote"/"tinymce"), self-contained
(one new ADR file, no shared registry/controller touched), and matches the
established pattern of resolving a RESEARCH-priority item via ADR before
any code (same shape as Autosave/Rollback). See
`docs/adr/007-modern-editor-decision.md`: decision is to keep Summernote
0.8.18 for 1.0, no new editor component built. **Next candidates for a
future run:** re-run `gh pr list --state open` first (46 PRs were in
flight, likely to shift significantly by the next run) — Functional Scope
§31 (Theme live preview, P1/P2, not yet claimed by title as of this run)
is a similar low-collision RESEARCH-flavored candidate if still open; the
Phase 18 SEO builders and Phase 11 Update Manager work should stay
avoided until their PR counts drop, per the hot-file collision guard.
**Update (this run, 2026-09-09): fix MC-0009 shipped** —
`ControllerStartupSeoUrl::index()`'s incoming pretty-URL decode now
filters by `language_id` (with a language-agnostic fallback), fixing a
real correctness bug on multi-language stores where two entities in
different languages share the same SEO keyword. At the time this run
started there were 41 open PRs (`#52`-`#92`); step 0 found `#66`
(System route URL aliases) also touches `seo_url.php`, but a different
method (`rewrite()`, near the end of the file) — this run's change is in
`index()`, a different, non-overlapping location, addressing a different
problem (incoming decode's missing language filter, not outgoing system
route aliasing), confirmed by reading `#66`'s full diff before starting.
No other open PR touches `catalog/controller/startup/seo_url.php`,
`docs/fixes/registry.md`, or the MC-0004–MC-0008 fix IDs already claimed
by `#76`/`#78`/`#79`/`#89`/`#92` (this run used MC-0009, the next free ID
after those). See `docs/fixes/registry.md`'s MC-0009 entry and
`docs/upstream-diff.md`'s new `seo_url.php` (MC-0009) entry for the full
writeup. Verified: `php -l` clean on the modified file; full
`tests/mintcart/**/*-test.php` suite re-run end to end — no regressions
(no existing PHP library file touched, only the controller). **Next
step:** re-run `gh pr list --state open` first (this backlog moves fast —
40+ open PRs at the time of this run, many independently claiming nearly
every remaining unclaimed phase/checklist item); a second Known Fix
Research Queue candidate (`MINTCART_FUNCTIONAL_SCOPE.md` §18 — pagination
`?page=1`/duplicate-entry issues, PHP 8.x compatibility problems) is a
reasonable next increment of the same low-collision-risk shape, once
re-confirmed unclaimed.
**Update (this run, 2026-09-09): Phase 7's "Mobile Navigation" gap is
closed.** At the time this run started there were 37 open PRs (`#52`–`#89`)
covering nearly every phase with unclaimed work — Compatibility adapters,
Entity Picker's remaining backends, SEO Core's remaining builders, Bulk
Editor, Update Manager's later steps, several Fix Registry candidates, and
more (see each PR's own title). `admin/controller/mintcart/entity.php` (5
open PRs: `#53`–`#57`) and `wizard.php`/`wizardprogress.php` (2 open PRs)
were treated as blocked hot files and avoided; `MintCart\Compatibility\OpenCart3\*`
adapters and further Fix Registry entries were skipped even though
unclaimed by title, since `#81` already documents (per its own doc) that a
second adapter has no real consumer yet, and four separate open PRs
(`#76`/`#78`/`#79`/`#89`) are actively working the Fix Registry area this
run. `docs/IMPLEMENTATION_STATUS.md`'s own Phase 7 note (added several runs
ago) explicitly named "a dedicated mobile nav pattern, likely composing
`mc-drawer`" as deferred, unclaimed-by-title work — genuinely untouched
files (`mint/components/header.twig`, `header.css`, `drawer.js`; PR `#62`
touches `common/header.twig`/`header.php` but only inside the `<head>`
schema-JSON-LD block and a separate try/catch, not the same lines this run
edited). See the Phase 7 section above,
`docs/developer/components/header.md` "Mobile navigation", and
`docs/developer/components/drawer.md`'s new "Responsive (breakpoint)
offcanvas" section for the full writeup. Verified: `php -l` clean; `node
--check` on `drawer.js`; Twig tag counts re-balanced; `npm run build` clean
with the new CSS/JS confirmed present in the built bundles; full
`tests/mintcart/**/*-test.php` suite (34 files) re-run end to end, all
passing, no regressions. Same pre-existing no-live-browser gap as every
earlier Phase 7 entry — see `docs/upstream-diff.md`'s `common/header.php`
entry for exactly what a human should confirm on a real install. **Next
step:** re-check `gh pr list --state open` for what has newly merged or
opened before picking the next phase — Header/Footer/Navigation's remaining
sub-item is Search/Cart/Navigation getting real Mint-native components
instead of legacy widget fragments (Phase 15/Commerce API), each still
waiting on its own second real consumer per `CLAUDE.md` §85.
**Update (this run, 2026-09-09): MC-0007 shipped — `Cache\File` atomic
write / concurrent-read fatal fix.** See the Phase 10 (Technical Plan)
section above and `docs/fixes/registry.md` for the full writeup. This
repo had 35 open PRs (`#52`–`#88`) at the start of this run — re-check
`gh pr list --state open` before picking the next increment; the
remaining unattempted Known Fix Research Queue items (multilingual SEO
URL issues, category path/update issues beyond Phase 17, extra
session/cart records, remaining installer edge cases) are still
candidates, as is Diagnostics dev mode or re-checking which of the 35
open PRs have since merged.
---

**Update (2026-09-09, routine run):** Phase 15's `CheckoutTotalsService`
+ `TotalsAdapter` increment shipped (see Phase 15's own section above for
the full writeup) — genuinely unclaimed at the time against all 35 open
PRs (`#52`-`#87`). Next candidates once those PRs land: `QuickOrderService`
(open as PR #72, not yet merged), a Mint-native checkout UI consuming the
new `mintcart/checkout/totals` endpoint, or re-checking `gh pr list
--state open` for whatever's newly unclaimed.
**Update (this run, 2026-09-09): Phase 1's Compatibility layer has a first
adapter — `MintCart\Compatibility\OpenCart3\ExtensionAdapter`.** At the
time this run started there were 36 open PRs (#52–#86); a step-0 dedup
audit found five contended files/areas (the Setup Wizard files touched by
#52/#64/#84, `column_left.php` touched by #60/#65/#66, `category.php`/
`search.php` touched by #62/#83, `admin/controller/catalog/product.php`
touched by #63/#80, and `docs/developer/update/package-manifest.md`
touched by #82/#86) and confirmed the historically-hot Homepage Builder
files (block-type registry, `homepage_list.twig`, `block.css`) are
untouched by all 36. `ExtensionAdapter` extracts `ModelMintcartReadiness`'s
inline installed-vs-enabled extension check into the Compatibility layer
Technical Plan §5 calls for, with a real existing consumer
(`ModelMintcartReadiness` itself, wired) — see the Phase 1 section above
and `docs/developer/compatibility/extension-adapter.md`. Touches only
`upload/system/library/mintcart/compatibility/opencart3/extensionadapter.php`
(new) and `upload/admin/model/mintcart/readiness.php` (unchanged
behavior, implementation only) — neither file is touched by any of the 36
open PRs. Verified: `php -l` clean across `upload/`+`tests/`; full
`tests/mintcart/` suite (35 files, was 34) passing end to end, no
regressions. **Next step:** another `Compatibility\OpenCart3` adapter once
a real consumer needs one (`CLAUDE.md` §85 — do not add
`ProductAdapter`/`CategoryAdapter`/`OrderAdapter`/`CustomerAdapter`
speculatively), or re-check `gh pr list --state open` for what has
newly merged/unclaimed — Categories drag-and-drop's live `McSortable` UI
and Phase 12's `product_form.twig` wiring are both worth re-checking once
PR #80 (which claims to solve the admin asset-enqueue blocker both are
waiting on) lands.
**Update (this run, 2026-09-08): Phase 10 (Technical Plan)'s Performance
item has a first small increment — `product.image_width`/`image_height`
now flow through `ProductViewProvider` into `mc-product-card`'s `<img
width height>` attributes.** See that phase's section above for the full
writeup. 30 open PRs (`#52`–`#82`) were checked at step 0 and none overlap
this increment's five touched controllers/provider/templates. **Next
step:** responsive `srcset`, WebP/modern formats, Diagnostics dev mode, or
re-check `gh pr list --state open` — this backlog is unusually large right
now and several of those 30 PRs are marked DONE and ready for human
review/merge.
**Update (this run, 2026-09-08): the admin asset-enqueue problem referenced
by both "Next step" notes above is now solved** —
`docs/developer/admin/asset-enqueue.md` (new), see Phase 12's section
above for the full writeup. `product_form.twig`'s Category field is now
live-wired to `McEntityPicker`. The `McSortable` category-reorder and
Homepage Builder JSON-textarea-to-repeater follow-ups these two notes
describe can now reuse the same `components.min.css`/`admin.min.js`
enqueue pattern (`$this->document->addStyle()`/`addScript()` with
`HTTP_CATALOG . 'assets/dist/...'`) rather than re-solving the blocker —
neither was attempted this run (kept to one screen for a focused,
verifiable diff); both are natural next increments. Also unclaimed at
the time this run started (checked all 27 open PRs, #52–#79): Phase 12's
remaining entity-picker live-wiring (manufacturer/product backends into
their own fields), Phase 16's remaining Bulk Editor fields, Phase 18's
SEO Core (canonical/meta title-description beyond what #62/#66/#68/#70/#71
already claim), and Phase 1's `MintCart\Compatibility\OpenCart3\*` adapter
interfaces (still not started — deferred this run pending a clearer real
consumer than what exists today; see Phase 1's own checklist item).
**Update (this run, 2026-09-08): MC-0006 fix registry entry** —
`DB::connected()` (`upload/system/library/db.php`) called
`$this->adaptor->connected()`, a method none of `DB\MySQLi`/`DB\PDO`/
`DB\PgSQL` implement (all three implement `isConnected()`), so the method
fatals instead of returning a bool. Picked from
`MINTCART_FUNCTIONAL_SCOPE.md` §18's "database connection / reconnect
issues" Known Fix Research Queue item. Step 0 checked all 26 PRs open at
the time (`#52`–`#78`): none touch `system/library/db.php` or
`system/library/db/*.php`. See the Phase 10 (Technical Plan) section above
and `docs/fixes/registry.md`'s MC-0006 entry for the full writeup,
including the deliberate ID choice (MC-0006, not MC-0004) to avoid
colliding with PRs #76/#78's own in-flight registry entries. **Next
step:** the remaining Known Fix Research Queue items (multilingual SEO URL
issues, category path/update issues beyond what Phase 17 already covers,
cache race conditions, extra session/cart records, remaining installer
edge cases) are still unattempted — or re-check `gh pr list --state open`
first, since this backlog changes size on nearly every run.
**Update (this run, 2026-09-08): MC-0005 Fix Registry entry** (install-time
PHP version pre-flight checks — see Phase 10 (Technical Plan) section
above). Picked after `gh pr list --state open` showed 24 open PRs (`#52`–
`#77`) covering nearly every phase with unclaimed scope, and checking each
one's changed-files list (`gh pr diff <n> --name-only`) confirmed none
touch `install/cli_install.php`, `install/controller/install/step_2.php`,
or the fixes/upstream-diff docs beyond appends. Also confirmed this run's
detached-HEAD start commit (`MintCart\Update\PackageManifest`, "Phase 11:
add MintCart\Update\PackageManifest") had already landed directly on
`origin/master` outside the normal branch/PR flow before this session
began — flagging it here for a human to notice, not something this run
attempted to undo. Next candidates for a future run: re-check `gh pr list
--state open` for what's newly merged/unclaimed; other untried Known Fix
Research Queue items (database reconnect issues, multilingual SEO URL
issues, category path/update issues beyond Phase 17, cache race
conditions, redundant session/cart writes); or Phase 10 (Technical Plan)'s
still-unclaimed Diagnostics/performance work once PR #75/#77 land.
**Update (this run, 2026-09-08): Phase 10 (Technical Plan §36)'s "cache
invalidation API" is done** — `MintCart\Core\Cache\CacheInvalidator`, a
pure, injectable-callback domain registry over OpenCart's stock `\Cache`
class, plus a verified (code-inspected, not assumed) writeup of a real
`delete('*')` no-op gap on the `mem`/`memcached`/`redis` cache adapters
that stock OpenCart's own `tool/backup.php` "clear cache" step already
silently inherits. See the Phase 10 (Technical Plan) section above and
`docs/developer/core/cache-invalidation.md`. At the time this run started
there were 24 open PRs (#52–#76); this increment's new files
(`upload/system/library/mintcart/core/cache/`, `tests/mintcart/core/cache-invalidator-test.php`,
`docs/developer/core/cache-invalidation.md`) don't collide with any of
them, and it deliberately does not touch `tool/backup.php` (already
touched by open PR #76) despite documenting a real gap in that file.
**Next step:** wire a real caller once a bulk-write path (Phase 16 Bulk
Editor or Phase 17 category move, both currently only open PRs) lands on
`master`; or re-check `gh pr list --state open` — this backlog is
unusually large and several sessions have now flagged it as needing human
merge attention rather than more parallel increments.
**Update (this run): MC-0004 Fix Registry entry** (`tool/backup`'s
hardcoded-byte-length safety check — see Phase 10 (Technical Plan)
section above) — a self-contained, low-collision-risk increment picked
because 23 PRs were open at the time (`#52`–`#75`), covering nearly every
phase with unclaimed scope, and this repo's own admin asset-enqueue
question (above) blocks the category-drag-and-drop and Entity Picker
live-wiring candidates until a human resolves the Bootstrap-3/5 admin
shell architectural question. Next candidates: re-check `gh pr list
--state open` for what's newly merged/unclaimed, or work through the rest
of `MINTCART_FUNCTIONAL_SCOPE.md` §18's Known Fix Research Queue
(pagination `?page=1` — checked this run, does not reproduce on this
baseline's `Pagination` class; database reconnect issues; multilingual
SEO URL issues; cache race conditions; installer edge cases; PHP 8.x
compatibility problems — none attempted yet).
**Update (routine run): Phase 10 (Technical Plan) Diagnostics collector
shipped.** At the time of this run, `list_pull_requests --state open`
found 22 open PRs (`#52`-`#74`, minus `#58`) covering, among others: the
Wizard's remaining Contacts/Language&currency steps (`#52`/`#64` — both
touching `wizard.php`/`wizard.twig`/`wizardprogress.php`, already flagged
by `#65` as contended hot files, so this run avoided that area
entirely); all five Phase 12 entity-picker backends and the Business
Contacts/Delivery/Payments facades (`#53`-`#57`, `#60`, `#65` — all
touching `column_left.php`, also treated as a hot/contended file this
run); Phase 16 Bulk Editor (`#63`, already touching
`product.php`/`product_list.twig`); Phase 18's hreflang/robots/sitemap/
Schema.org/system-route-alias builders (`#68`, `#70`, `#71`, `#62`,
`#66`); Phase 1's HealthCheck (`#67`); Phase 11's Compatibility Checker
and release packaging script (`#73`, `#69`); Phase 6/8's product detail
page (`#74`). None of these touch `upload/system/library/mintcart/core/`,
`tests/mintcart/core/`, or `docs/developer/core/` — this run's only new
files — so Diagnostics was picked as genuinely unclaimed and
file-collision-free. Full writeup in the Phase 10 (Technical Plan)
section above and `docs/developer/core/diagnostics.md`. Real query/
render-time instrumentation, a dev-mode setting, and a visible surface
are all explicitly deferred next steps, not guessed at this run.
**Update (routine run): Phase 14's `Business → Delivery`/`Payments`
facades shipped** — see that phase's section above and
`docs/developer/onboarding/business-delivery-payments.md` for the full
writeup. At the time of this run 11 other PRs were open (`#52`-`#63`,
covering the Wizard Contacts/Language&currency steps, all five remaining
Phase 12 entity-picker backends, a Store Readiness dashboard widget, a
Business Contacts facade, the search-box UI component, Bulk Editor, and
SEO Core Schema.org) — none overlapped this increment's files. Also
found: `wizard.php`/`wizard.twig`/`wizardprogress.php` are contended by
two open PRs (`#52`, `#64`) and `entity.php` by five (`#53`-`#57`) — a
future run should treat both as blocked hot files (this routine's own
step 0f) until those land, rather than adding another Wizard step or
entity-picker backend on top.
**Update (2026-09-06, routine run) — Phase 6/8: Mint Default product
detail page.** Step 0 dedup check against 20 open PRs (#52-#73) found none
touching `catalog/controller/product/product.php` or any
`product/product.twig` path; `admin/controller/mintcart/entity.php` (5
open PRs), `wizard.php` (2 open PRs), and `admin/controller/common/column_left.php`
(2+ open PRs) were deliberately avoided as already hot-contended. Wired
`ControllerProductProduct`'s Related Products list to the same
`ProductCardListRenderer` pipeline Category/Search/Manufacturer/Special
already use, and added the missing `mint_default/template/product/product.twig`
page itself (gallery, options, price/stock, discounts, attributes, tabs,
related products) — see the Phase 6 section above for the full writeup.
Next candidates: a real reviews UI (list + AJAX write form) for this same
page, `QuickOrderService`/`CheckoutService` (flagged as large/risky by
prior runs without live MySQL to verify against), or re-checking
`gh pr list --state open` for what's newly unclaimed once the current
backlog lands.
---

## Update (2026-09-06, routine run) — Compatibility Checker (Phase 11/Technical Plan)

Step 0 dedup check at the time of this run: 20 open PRs (`#52`–`#72`,
`#58`/`#71` gaps aside — see the PR list itself), covering every Phase 18
SEO Core item, Phase 12's remaining entity-picker backends, the remaining
Setup Wizard steps, Business Settings facades, `HealthCheck`, Bulk
Editor, `QuickOrderService`, `mc-search-box`, and release packaging.
`wizard.php`/`wizardprogress.php`/`wizard.twig` (2 open PRs) and
`admin/controller/mintcart/entity.php` (5 open PRs) and
`admin/controller/common/column_left.php` (3 open PRs) are all
hot-contended per this file's own §0f rule — avoided entirely. Picked
Compatibility Checker instead: genuinely unclaimed by any open PR, and
implementable as new files only (no hot shared file touched). See the
Phase 11 (Technical Plan) section above and
`docs/developer/compatibility/compatibility-checker.md` for the full
writeup.

**Next step:** an upload-based admin screen + menu entry for the
Compatibility Checker (blocked on `column_left.php` decontention, same as
several other in-flight PRs); Diagnostics dev mode (Technical Plan §35,
still fully unclaimed); Update Manager itself; or re-check `gh pr list
--state open` — this repo had 20 open PRs at the time of this run.
**Update (this run, PR #72): Phase 15 — `QuickOrderService` built.** See
the Phase 15 section above for the full writeup — a Quick Order request
is recorded as a lead in a new `mc_quick_order` table, deliberately never
touching `checkout/order.php::addOrder()`. **Next step:** an admin "Quick
Order requests" list (the new table's first real consumer), or
`CheckoutService`/modern checkout UI if a future run is prepared to scope
the `addOrder()` field-list risk PR #61 already flagged.
**Update (this run, PR #71): `MintCart\Seo\SitemapBuilder` added** — the
Phase 18 checklist's last unclaimed sub-item (XML sitemap), per #66's and
#70's own "Next steps" notes. See the Phase 18 section above and
`docs/developer/seo/sitemap.md` for the full writeup. This repo currently
has 18+ open PRs in flight (#52 onward) — re-check `gh pr list --state
open` before starting new work rather than trusting any snapshot in this
file.
**Update (this run): Phase 18's robots-controls item shipped —
`MintCart\Seo\RobotsBuilder`, additive-only (new `mintcart/seo/` files),
no live wiring yet.** See that phase's section above for the full writeup
and why the 17 open PRs at the time (#52-#69) left this as the one
genuinely unclaimed Phase 18 sub-item. Next candidates once the open PRs
land: wiring `RobotsBuilder`/`HreflangBuilder`/`SchemaOrgBuilder` into
`ControllerCommonHeader`, meta title/description, or re-checking
`gh pr list --state open` for what's newly unclaimed.
**Update (this run): the release packaging script is done** (see
"Phase 11 (Technical Plan) — Update / Compatibility" above,
`scripts/release.js`/`docs/release.md`). At the time of this run, 16 open
PRs (#52-#68) already covered Phase 12's remaining entity-picker backends,
Phase 13's Wizard Contacts/Language-currency steps, Phase 14's
Contacts/Delivery/Payments facades and dashboard widget, Phase 15's
search-box component, Phase 16's Bulk Editor, and Phase 18 (SEO Core) was
already a three-way contested area (#62 Schema.org, #66 system route
aliases, #68 hreflang, each deliberately avoiding the others' files) — so
this run picked the standalone, previously-flagged release-packaging TODO
instead: genuinely unclaimed, touches zero files any open PR touches
(build/release tooling only, no PHP/Twig/JS product code). Next
candidates: `QuickOrderService`/`CheckoutService` (Phase 15 — PR #61's own
body already flagged this as large enough to scope carefully, e.g. the
service contract alone before wiring a real order-creation flow), Phase
19 (Diagnostics/Performance, not started), or re-check
`gh pr list --state open` for what's newly landed/unclaimed once the
current 16-PR backlog starts merging.
---

## Update (2026-09-04, routine run) — Phase 1's Health checks item done

Step 0 dedup check against all 14 open PRs at the time (`#52`-`#66`,
excluding `#58`/gaps which don't exist): entity-picker backends for
product/information/attribute/option/customer (Phase 12), Setup Wizard
Contacts and Language/Currency steps (Phase 13), Business Contacts/
Delivery/Payments facades and a Store Readiness dashboard widget (Phase
14), `mc-search-box` (Phase 15), Bulk Editor (Phase 16), and SEO Core
Schema.org + system route URL aliases (Phase 18). None touch Phase 1,
`MintCart\Core\HealthCheck`, `MigrationManager`, or any `mintcart/health*`
path (confirmed via each PR's body text). `QuickOrderService`/
`CheckoutService` were deliberately **not** picked despite being unclaimed:
PR #61's own body already flagged them as too large/risky to attempt
without live MySQL verification (financial-correctness risk from
`checkout/order.php::addOrder()`'s field list) — that reasoning still
holds in this sandbox, so this run picked Phase 1's still-unstarted
"Health checks" item instead, genuinely unclaimed and low-risk (pure
evaluator + thin resolver, same shape already proven safe by
`StoreReadiness`).

Shipped: `MintCart\Core\HealthCheck` + `ModelMintcartHealth` +
`ControllerMintcartHealth` (`mintcart/health`, GET, JSON) — see the Phase 1
section above and `docs/developer/core/health-check.md` for full detail,
including a latent `MigrationManager::applied()` bug this surfaced and
fixed (safe to call `pending()`/`applied()` before any `run()`, now
memoized so it doesn't add a duplicate query). This is additive/self-
contained: new files plus one small, well-tested fix to a MintCart-owned
(not OpenCart core) file nothing else in the open-PR backlog touches.

**Next step:** a Diagnostics admin screen or the Update Manager's own
health-check step (both listed as `HealthCheck`'s expected next consumers)
are natural follow-ups once a real UI exists for either; `MintCart\Compatibility\OpenCart3\*`
adapters remain deferred (see the Phase 1 section's own note on why, given
the in-flight Business Settings PRs); or check `gh pr list --state open`
again for anything newly landed before picking a fresh phase — `QuickOrderService`/
`CheckoutService` remain unclaimed but should stay deferred until this
environment (or the picking session's) has live MySQL to verify order
creation against, per PR #61's reasoning above.
**Update (this run, PR #52): Phase 13's Contacts step is done** — see that
phase's section above for the full writeup (social profile links, stored
under a new `mintcart_contacts` setting, now also wired live into
`ControllerCommonFooter`'s `$social`). Step 0 found zero open PRs at the
start of this run, so no dedup/collision check against sibling PRs was
needed. Untouched by the admin asset-enqueue blocker above (Wizard already
uses its own standalone-page workaround; `footer.php` is a storefront
controller). Next candidates: Phase 13's remaining six steps
(`language_currency` onward), Phase 12's remaining entity-picker backends
(product/attribute/option/information page/customer), Phase 14's Business
Settings UI façades or a Store Readiness dashboard widget, Phase 15's
`QuickOrderService`, or re-checking `gh pr list --state open` for what a
parallel run may have picked up first.

## Update (2026-09-02, routine run) — Phase 12 product entity-picker backend

Step 0 found exactly one other open PR (#52, "Setup Wizard: Contacts step") —
scoped entirely to `upload/admin/controller/mintcart/wizard.php` and related
Wizard files, no overlap with this run's Phase 12 work, so no shared-file
collision. This run added the **product** `fetchItems` backend
(`ControllerMintcartEntity::product()`, `MintCart\Design\ProductPickerProvider`)
— see the Phase 12 section above for the full writeup. Next candidates
unchanged from that section's own "Next step": wire a picker backend into a
live screen (blocked on the admin asset-enqueue problem), or build the
attribute/option/information-page/customer backends the same shape.

**Update (this run): Phase 12's information page `fetchItems` backend is
done** (`mintcart/entity/information`, see the Phase 12 section above for
the full writeup). At the time of this run, two other open PRs existed
(#52 — Wizard Contacts step; #53 — Phase 12's product backend), neither
overlapping this increment. Next candidates per Phase 12's own "Next step"
above: attribute/option/customer entity-picker backends, or tackling the
admin asset-enqueue problem itself now that three backends (category,
manufacturer, information) are waiting on it.

**Update (routine run): Phase 12's attribute `fetchItems` backend shipped
too** (category, manufacturer, attribute now done; product and
information page in flight via open PRs #53/#54). See the Phase 12
section above for the full writeup. Next unclaimed candidates: option or
customer entity-picker backends, or re-checking which of #52/#53/#54 have
merged.

**Update (2026-09-03, routine run) — Phase 14 Store Readiness dashboard
widget.** Note on session setup: this session's branch was accidentally
created from a stale local `master` git ref (predating commit `b28c8cf`,
"Add MintCart\Commerce\SearchService..."), which caused a first attempt at
this run to duplicate already-merged Phase 15 `SearchService` work before
the mistake was caught and the duplicate PR (#58) was closed — see that
PR's comments for the full account. After resetting to the real
`origin/master` tip, Step 0 was re-run: six other open PRs existed (#52
Wizard Contacts; #53–#57 Phase 12 entity-picker backends for
product/information/attribute/option/customer), none touching this run's
files (`admin/controller/extension/dashboard/mintcart_readiness.php` and
its Twig/language files are new paths no open PR references). See the
Phase 14 section above for the full writeup. Next candidates: Business
Settings UI façades, wiring Phase 13's `ready` step to `StoreReadiness`
(once #52 merges — it shares `wizard.php`), or once #53–#57 land, tackling
the admin asset-enqueue problem itself (now blocking Phase 12 live wiring
and Phase 17 live UI, though this run's own dashboard widget deliberately
avoided it by using stock Bootstrap 3 markup instead of `mc-*`).

**Update (a later run): Phase 18 (SEO Core) started — `MintCart\Seo\SchemaOrgBuilder`
+ live Organization/BreadcrumbList wiring.** At the time of this run, open
PRs #52/#53-57/#59/#60/#61 covered Phase 13's Contacts step, all five
remaining Phase 12 entity-picker backends, Phase 14's dashboard widget and
Business Contacts facade, and Phase 15's search-box component — #61's own
body explicitly flagged `QuickOrderService`/`CheckoutService` as too large
to attempt safely without live MySQL verification, so this run picked
Phase 18 instead, genuinely unclaimed and untouched by any of them. See the
Phase 18 section above and `docs/developer/seo/schema-org.md` for the full
writeup. Next candidates: wire `SchemaOrgBuilder::breadcrumbList()` into
Manufacturer/Special the same way Category/Search now use it; wire
`SchemaOrgBuilder::product()` once a `mint_default/template/product/product.twig`
exists (a separate, larger increment — Phase 6/8 never wired the product
detail page itself); hreflang/robots controls; System route URL aliases
(`MINTCART_FUNCTIONAL_SCOPE.md` §4); or re-check `gh pr list --state open`
for what's newly unclaimed.

---

**Update (2026-09-15, routine run): Phase 10 (Technical Plan) Diagnostics —
real render-time measurement wired for the first time.** Step 0 dedup check
against all 14 open PRs at the time (`#95`-`#108`): `#108` (Catalog
`BulkProductUpdatePlanner`), `#107` (`CategoryAdapter`), `#106`
(`CustomerAdapter`), `#105` (MC-0014 Klarna `utf8_encode`/`utf8_decode`
fix), `#104` (ADR 008 Theme Live Preview), `#103` (`docs/user/install.md`+
`setup-wizard.md`), `#102` (`PackageVerifier`), `#101` (MC-0013 SMTP HELO
fix), `#100` (`MetaBuilder`), `#99` (MC-0012 setting-transaction fix),
`#98` (MC-0011 MySQLi reconnect fix), `#97` (MC-0010 opayo `strftime()`
fix), `#96` (Media UX backend), `#95` (ADR 007 Modern Editor) — verified via
each PR's own file list (`gh pr view <n> --json files`), none touch
`upload/system/library/mintcart/design/componentrenderer.php`,
`upload/system/library/mintcart/core/diagnostics.php`, or
`upload/catalog/controller/product/category.php`. All five open Known-Fix
IDs (MC-0010 through MC-0014) confirmed against `docs/fixes/registry.md`
before ruling out a sixth fix-registry candidate this run (the next free ID
is MC-0015, reserved for a future run — this run's own scope stayed inside
Diagnostics instead of adding a fix). A search for further PHP 8.x-deprecated
patterns (`utf8_encode`/`utf8_decode`, `create_function`) beyond what
`#105`/`#97` already claim found nothing else in `upload/` — that Known
Fix Research Queue avenue is exhausted for now.

Picked `docs/developer/core/diagnostics.md`'s own long-standing "Next step"
list instead: `MintCart\Core\Diagnostics` (shipped several runs ago as a
pure, consumer-less collector, `CLAUDE.md` §85) finally has a real
consumer. `MintCart\Design\ComponentRenderer::render()` gained an optional
4th constructor argument, `?Diagnostics $diagnostics = null` — deliberately
the one method every component render (product-card, blocks, header,
footer, any `$kind`) already passes through regardless of caller, so
instrumenting there covers every current and future consumer without
touching each individual call site, and `null` (the default) leaves every
existing 2-/3-argument call site byte-for-byte unaffected. The
"intentionally-empty-override" and "component missing everywhere" paths
both short-circuit before ever touching `Diagnostics` — an empty override
or a thrown resolution error is not a real render, so it must not pollute
the `component_render` counter/duration.

`ControllerProductCategory` (`upload/catalog/controller/product/category.php`)
is the first real caller: constructs `Diagnostics` gated by a new
`mintcart_dev_mode` setting (`(bool)$this->config->get('mintcart_dev_mode')`
— standard OpenCart settings storage per `CLAUDE.md` §6, no new Mint
table, off by default since an unset setting reads falsy), passes it into
`ComponentRenderer`, and — only when enabled — appends
`$diagnostics->summary()` as an HTML comment to the response body
(Functional Scope §19 "Production UI не повинен показувати sensitive debug
data": never written into `$data`/the template itself, so no theme
override can surface it in visible markup).

Full writeup: the Phase 10 (Technical Plan) section above (Diagnostics
checklist item, now `[~]`), `docs/developer/core/diagnostics.md` (updated
DoD checklist and "Next step"), `docs/developer/theme-api/component-renderer.md`'s
new "`$diagnostics` (Phase 10)" section, and `docs/upstream-diff.md`'s
`category.php` entry (new "Update (this run — Phase 10 (Technical Plan),
Diagnostics)" block).

Still **not** built, same honesty this file's earlier Diagnostics entries
already carried: real DB query counting (still a real upstream-diff
decision per `CLAUDE.md` §10/§75 — how to hook `\DB\MySQLi` without
duplicating OpenCart's own DB class), an admin UI toggle for
`mintcart_dev_mode` (today it can only be set directly in `oc_setting`),
and wiring the same `Diagnostics` pattern into
Search/Manufacturer/Special (each still constructs a bare
`ComponentRenderer` with no `Diagnostics` — a natural, low-risk follow-up
now that the pattern exists once).

**Verified:** `php -l` clean on all three touched files
(`componentrenderer.php`, `category.php`,
`component-renderer-test.php`). `tests/mintcart/design/component-renderer-test.php`
gained 3 new scenarios (14-16, 16 total, up from 13) covering the
no-Diagnostics-touched-on-empty-override path, the
no-Diagnostics-touched-on-thrown-resolution-error path, and the
no-Diagnostics-passed default leaving behavior unaffected — none require
`vendor/twig/twig` (same pre-existing gap noted throughout this file for
every other `ComponentRenderer::render()` call; the happy-path Twig render
with `Diagnostics` attached remains, like every other `render()` call,
unexercised end-to-end in this environment). Full `tests/mintcart/` suite
(65 files) re-run end to end after the change — all passing, no
regressions.

**Next candidates:** wire the same `Diagnostics`-through-`ComponentRenderer`
pattern into `ControllerProductSearch`/`ControllerProductManufacturer`/
`ControllerProductSpecial` (each still builds a bare `ComponentRenderer`);
decide the real-DB-query-counting upstream-diff question
(`\DB\MySQLi` wrapper vs. per-call-site instrumentation) now that a real
consumer exists to justify making it; MC-0015 (the next free Fix Registry
ID) once a genuinely new, unclaimed historical-bug candidate turns up; or
re-check `gh pr list --state open` first, since this backlog moves fast —
14 open PRs at the time of this run, several already flagged in past
entries as ready for human merge review.

---

**Update (2026-09-15, routine run): Phase 10 (Technical Plan) Diagnostics —
Search/Manufacturer/Special wired.** `gh pr list --state open` returned
zero open PRs at the start of this run — the 14-PR backlog referenced
just above had already merged/closed, so no dedup/hot-file check applied.
Picked the exact "next candidates" item this file's own previous entry
named: extended the `Diagnostics`-through-`ComponentRenderer` wiring from
`category.php` to `search.php`/`special.php`/`manufacturer.php` (`info()`
only). Full writeup in the Phase 10 (Technical Plan) section above. `php -l`
clean on all three files; full `tests/mintcart/` suite (77 files) re-run
end to end, all passing. **Next candidates:** `product.php`'s bare
related-products `ComponentRenderer` call site; the real-DB-query-counting
upstream-diff decision; `manufacturer.php`'s missing `SchemaOrgBuilder`
breadcrumb wiring (unlike its three sibling controllers); MC-0015; or
re-check `gh pr list --state open` first.

**Update (2026-09-15, later routine run): Phase 10 (Technical Plan)
Diagnostics — `product.php`'s related-products call site wired.** Step 0
dedup check: `gh pr list --state open` returned exactly one open PR, `#110`
("Phase 10: wire MintCart\Core\Diagnostics into Search/Manufacturer/
Special", status DONE), which extends the same pattern to
`search.php`/`special.php`/`manufacturer.php` — confirmed via
`gh pr view 110 --json files` that it does not touch `product.php` or
`upload/catalog/controller/product/product.php`, so this run picked
`product.php`'s related-products call site instead, an item `#110`'s own
body explicitly named as deliberately left out. No hot shared files
(§0f, Homepage Builder block registry / `block.css`) are touched by this
increment. Full writeup in the Phase 10 (Technical Plan) section above.
`php -l` clean; full `tests/mintcart/` suite (77 files) re-run end to
end, all passing. **Next candidates:** now that `#110` and this increment
have both landed, `product.php`, `search.php`, `manufacturer.php`,
`special.php`, and `category.php` all carry the same `Diagnostics`
wiring — the real-DB-query-counting upstream-diff decision becomes the
natural next Phase 10 step; separately, `manufacturer.php`'s `info()`
path still has no `SchemaOrgBuilder::breadcrumbList()` wiring, unlike
`category.php`/`search.php`/`special.php`; MC-0015 remains the next free
Fix Registry ID once a genuinely new candidate turns up; or re-check
`gh pr list --state open` first.

**Update (2026-09-15, routine run): Phase 16 Bulk Editor — Manufacturer
field.** Step 0 found two open PRs (#110, #111 — Phase 10 Diagnostics,
storefront-only) with zero file overlap. Added `manufacturer_id` as a
fourth bulk-editable field, same shape as status/quantity/price. Full
writeup in the Phase 16 section above. **Next candidates:** the remaining
Phase 16 fields (special, category, stock status), Phase 10's
Search/Manufacturer/Special Diagnostics wiring (likely already covered
by #110 — re-check first), or re-check `list_pull_requests`/`gh pr list`
before picking anything new.

**Update (2026-09-15, routine run): Phase 18 (SEO Core) — `MintCart\Seo\RobotsBuilder`
live wiring.** Step 0 dedup check found 3 open PRs (`#110`, `#111` — both
Diagnostics wiring into `manufacturer.php`/`search.php`/`special.php`/
`product.php`; `#112` — admin Bulk Editor manufacturer field), none
touching `header.php` or any SEO file. `RobotsBuilder` (a pure, tested,
consumer-less contract per earlier runs) now has its first real consumer:
`ControllerCommonHeader::index()` builds `$data['mc_robots_content']` from
the current `$this->request->get['route']`, rendered by
`mint_default/template/common/header.twig` as a `<meta name="robots">` tag
— deliberately *not* gated by `isMintAware()` since it's a per-route SEO
signal, not a Mint component DOM contract. Known, documented limitation:
`product/search`'s `$hasResults` isn't threaded from `search.php` yet, so
an empty search still renders `index, follow` (see
`docs/developer/seo/robots.md` "Known limitation"). Full writeup:
`docs/upstream-diff.md`'s `header.php` entry, `docs/developer/seo/robots.md`,
`docs/MANUAL_VERIFICATION.md`'s new Phase 18 "RobotsBuilder" section.
Verified: `php -l` clean; `tests/mintcart/seo/robots-builder-test.php`
unchanged (pre-existing 74 assertions already cover the pure function);
full `tests/mintcart/**/*-test.php` suite re-run end to end (77 files) —
all passing, no regressions. Not verified: no live MySQL/Twig session in
this environment, same pre-existing gap.

**Next candidates:** thread `product/search`'s `$hasResults` from
`ControllerProductSearch` into `ControllerCommonHeader` (needs a
registry/session bridge between sibling controllers — a real, slightly
larger design question, not mechanical); wire `MetaBuilder`/
`CanonicalUrlBuilder` into `category.php`/`product.php`/`home.php`/
`manufacturer.php`/`search.php`/`special.php` (both still unwired,
duplicated inline logic — but `manufacturer.php`/`search.php`/`special.php`/
`product.php` were touched by this run's open `#110`/`#111`, so re-check
those PRs' merge status first to avoid a same-file collision; `category.php`
and `home.php` are uncontended today); hreflang live wiring (`HreflangBuilder`
still has no consumer, needs a per-language URL resolver first per its own
doc); or re-check `gh pr list --state open` first, since this backlog moves
fast.

**Update (2026-09-30, routine run): Phase 18 — `product/search`'s
`$hasResults` is now threaded into `RobotsBuilder`, closing the "next
candidate" named just above.** Step 0 found 20 open PRs, one
`IN-PROGRESS` (#148, `product_form.twig` Option entity picker wiring) and
19 `DONE`-but-unmerged; none touch `header.php`, `search.php`, or any
`MintCart\Seo\*` file, and none touch the Homepage Builder hot files
(§0f — block-type registry, `block.css`). `RobotsBuilder` gained a public
`HAS_RESULTS_REGISTRY_KEY` constant; `ControllerProductSearch::index()`
now writes the real "did this search match anything" flag to
`$this->registry` right before its existing
`$this->load->controller('common/header')` call, and
`ControllerCommonHeader::index()` reads it back (falling back to
`RobotsBuilder`'s own `true` default when unset, i.e. every route other
than `product/search`) — the same `$this->registry->set()`/`->get()`
bridge `startup/startup.php` already uses between request stages, no new
table/session/event (`CLAUDE.md` §17, no over-abstraction). Full writeup:
`docs/developer/seo/robots.md` (the former "Known limitation" section, now
resolved), `docs/upstream-diff.md`'s `header.php` and `product/search.php`
entries, `docs/MANUAL_VERIFICATION.md`'s Phase 18 section (steps 3-4 now
cover both the empty- and matching-search cases). Verified: `php -l` clean
across the full `upload`/`tests` tree; `tests/mintcart/seo/robots-builder-test.php`
gained 2 assertions for the new constant (76, up from 74); full
`tests/mintcart/**/*-test.php` suite re-run end to end (84 files) — all
passing, no regressions. Not verified: no live MySQL/Twig session in this
environment, same pre-existing gap — a human should confirm the two new
manual-verification steps on a real install. **Next candidates:** wire
`MetaBuilder`/`CanonicalUrlBuilder` into `category.php`/`home.php` (still
flagged uncontended a few runs ago — re-check current open PRs first);
hreflang live wiring (`HreflangBuilder` still has no consumer); or re-check
`gh pr list --state open` first, since this backlog moves fast.

**Update (2026-09-15, routine run):** MC-0015 filed — `MintCart\Seo\MetaBuilder`
wired into `category.php`/`home.php` (blank meta title fallback;
blank descriptions are preserved). See the Phase 18 section above for the full writeup. Step 0
checked all 4 open PRs at the time (#110-#113); `category.php`/`home.php`
were uncontended. Next: same wiring for `product.php` once #111 merges.

**Trailer note (2026-09-15, routine run):** `CanonicalUrlBuilder` now has
its first four live consumers (`category.php`/`manufacturer.php`/
`search.php`/`special.php`) — see the Phase 18 section above for the full
writeup. 6 open PRs (#110-#115) existed at the start of this run, none
overlapping this increment's files. Re-check `gh pr list --state open`
before picking the next step.

**Trailer note (2026-09-18, routine run):** `list_pull_requests` returned
exactly two open PRs at the start of this run — #127 ("Consolidate
unmerged MintCart work" — actual remaining diff is
`MintCart\Update\PackageExtractor` only) and #128
("`MintCart\Update\BackupExecutor`") — both pure Phase 11 (Update Manager)
work, neither touching `column_left.php`, any `mintcart/settings/*` path,
or the Homepage Builder hot files (§0f: block-type registry, `block.css`).
Picked a different, unclaimed Phase 10/19 item instead of Update Manager
(both open PRs' own "next candidates" point at wiring their new classes
into `UpdateOrchestrator::apply()`, which needs both to merge first — not
started here to avoid duplicating in-flight work). Added the admin UI
toggle for `mintcart_dev_mode` (`System → Diagnostics`) that
`docs/developer/core/diagnostics.md`'s "Next step" section had named as
the one remaining Diagnostics gap besides real query counting — see the
Phase 10 (Technical Plan) section above for the full writeup. `php -l`
clean; full `tests/mintcart/` suite (81 files) re-run end to end, all
passing, no regressions. **Next candidates:** real DB query counting for
`MintCart\Core\Diagnostics` (still needs the `\DB\MySQLi`-hooking
upstream-diff decision); a structured JSON diagnostics endpoint as an
alternative to the HTML-comment surface; wiring `PackageExtractor`/
`BackupExecutor` into `UpdateOrchestrator::apply()` once #127/#128 merge;
or re-check `gh pr list --state open` first.
exactly one open PR at the start of this run, #127 ("Consolidate unmerged
MintCart work"), whose actual diff against current `master`
(`gh pr view 127 --json files`) is scoped entirely to
`MintCart\Update\PackageExtractor` (new file
`upload/system/library/mintcart/update/packageextractor.php`, its test,
its own doc, and small "done" edits to `package-downloader.md`/
`package-verifier.md`/`update-orchestrator.md`) plus this same
`IMPLEMENTATION_STATUS.md` file — no overlap with any file this run
touched. This run added `MintCart\Update\BackupExecutor` — see the Phase
11 (Technical Plan) section above and
`docs/developer/update/backup-executor.md` for the full writeup: the
"performing the copy is the flow's own `files` step" gap `BackupPlanner`'s
own docblock named was never actually filled anywhere in this codebase,
so this fills it, independently of and with zero file overlap against
`#127`'s `PackageExtractor` work. No hot shared files (§0f in the routine
prompt — the Homepage Builder block-type registry/`block.css`) are
touched by this increment. `php -l` clean on both new files; the new
`tests/mintcart/update/backup-executor-test.php` (6 scenarios) passes
standalone. **Next candidates:** now that both `PackageExtractor` (once
#127 merges) and `BackupExecutor` exist as standalone primitives,
`UpdateOrchestrator::apply()`'s signature change to actually call them
(between `download`→`verify` for extraction, and `verify`→`maintenance`
for backup) is a single natural follow-up increment, per both classes'
own "deliberately not attempted here" notes; the `mc_installed_file`
ledger; an admin controller/`mintcart/update/*` route; or re-check
`gh pr list --state open` first, since this backlog moves fast.
**Trailer note (2026-09-17, routine run):** `list_pull_requests` returned
zero open PRs at the start of this run, so no dedup/hot-shared-file check
was needed. Added `MintCart\Update\PackageExtractor` — see the Phase 11
(Technical Plan) section above and `docs/developer/update/package-extractor.md`
for the full writeup (the archive-extraction gap every other `Update\*`
primitive's docblock had named). `php -l` clean; full `tests/mintcart/`
suite (82 files, up from 81) re-run end to end, all passing. **Next
candidates:** wire `PackageExtractor` into `UpdateOrchestrator::apply()`
itself (a deliberately separate increment — changes that method's
signature); the `mc_installed_file` ledger; an admin
controller/`mintcart/update/*` route; or re-check `gh pr list --state
open` first.

**Trailer note (2026-09-19, later routine run):** `PackageExtractor` and
`BackupPlanner`+`BackupExecutor` are now both wired into
`UpdateOrchestrator::apply()` as optional constructor arguments (`null`
by default, preserving the exact previously-tested behavior/signature) —
see the Phase 11 (Technical Plan) section above for the full writeup.
`list_pull_requests` returned zero open PRs at the start of this run.
**Next candidates:** an admin controller/`mintcart/update/*` route that
actually builds a `PackageManifest`, resolves a download URL, and calls
`apply()` on a real install (still nothing calls `apply()` outside tests);
the `mc_installed_file` ledger (needs a new migration); `UpdateChecker`
wiring for the `check`/`compatibility` steps `apply()` still leaves to the
caller; or re-check `gh pr list --state open` first.

**Trailer note (2026-09-19, later routine run):** the `mc_installed_file`
ledger named just above is now built — `list_pull_requests` returned zero
open PRs at the start of this run. See the Phase 11 (Technical Plan)
section above (the entry right before "Phase 12 — Early Access") for the
full writeup: new migration, new `MintCart\Update\InstalledFileLedger`,
wired into `UpdateOrchestrator::apply()` as an optional 11th constructor
argument. **Next candidates:** the admin controller/`mintcart/update/*`
route (still the only remaining gap before a real install can run
`apply()`); the "changed since install" comparison itself; or re-check
`gh pr list --state open` first.

**Trailer note (2026-09-28, routine run):** Phase 17's live UI wiring is
now done (category drag & drop, `category_list.twig` + real `mc-sortable`
behavior) — this also resolves the Phase 12 "Architectural fork
identified, not resolved" note via a per-parent-group save split rather
than a UI redesign. See the Phase 17 section for the full writeup. Step 0
checked all 3 open PRs at the time (`#138`/`#139`/`#140`, all
`ROUTINE-STATUS: DONE`, none touching `category.php`/`category_list.twig`/
any catalog/category file). **Next candidates:** full drag-to-re-parent
(tree/drill-down view, a separate larger increment); the
`enqueueMintAssets()` duplication across `category.php`/`product.php`
(trait extraction, once a third consumer appears); or re-check
`gh pr list --state open` first.
**Trailer note (2026-09-19, later routine run, PR #138):** `mc-search-box`
is now wired into both `mint_default`'s and `test_theme`'s headers (Phase
15 section above has the full writeup), plus a real bug fix in
`assets/src/css/components.css` (three missing component CSS imports
found while verifying). Zero open PRs at the start of this run. **Next
candidates:** see the Phase 15 section's own "Next candidates" above, or
re-check `gh pr list --state open` first.

---

**Trailer note (2026-10-02, routine run — MC-0021 fix, Fixes Registry/
Phase 10 (Technical Plan)).** Step 0 found 49 open PRs (`#138`–`#186`) at
the start of this run, spanning nearly every tracked phase (Entity
Picker, Setup Wizard steps, Bulk Editor fields, Update Manager primitives,
SEO wiring, Compatibility Checker UI, Reviews UI, wishlist persistence,
seven `docs/user/*` guides, and the `IMPLEMENTATION_STATUS.md`
merge-conflict-debris cleanup itself in `#154` — deliberately not touched
by this run for exactly that reason). The `Phase 10 (Technical Plan) — SEO
/ Fixes / Performance` section above (starting at its `## Phase 10
(Technical Plan)` heading) is itself the accumulated merge-conflict debris
`#154` is already fixing — not edited here to avoid colliding with that
in-flight cleanup; this note is appended standalone instead.

This run audited the one `product/*` listing controller
(`category.php`/`search.php`/`manufacturer.php` already each had a
`heading_title`-family fix under MC-0001/MC-0003/MC-0017) that had not yet
been checked for the same bug class: `special.php`. Confirmed
`ControllerProductSpecial::index()` never assigns `$data['heading_title']`
— only `$this->document->setTitle(...)` gets it — while both
`special.twig`s (`default` and `mint_default`) render a top-level
`{{ heading_title }}` page heading. Filed **MC-0021** and applied the
one-line fix (`docs/fixes/registry.md`'s new MC-0021 entry,
`docs/upstream-diff.md`'s `special.php` entry has a matching "Update"
block). No open PR title mentions `special.php`, and none of the 49 touch
it per `gh pr view <n> --name-only`'s spot checks on the ones most likely
to (none of the SEO/Diagnostics/listing PRs touch this file). Verified:
`php -l` clean on the modified file; full repo-wide lint
(`find upload tests -name '*.php'`, matching `.github/workflows/ci.yml`'s
`php-lint` job) clean; full `tests/mintcart/**/*-test.php` suite (84
files) re-run end to end exactly as `ci.yml`'s `php-tests` job does — all
passing, no regressions. Not verified: no live MySQL/Twig session in this
environment (same pre-existing gap noted throughout this file) — a human
should confirm the Special Offers page's heading is no longer blank on a
real install, on both the stock `default` theme and `mint_default`.

**Next candidates:** once `#154` merges, the `Phase 10 (Technical Plan)`
section's actual next-free Fix Registry ID should be re-derived from
`docs/fixes/registry.md` directly (now **MC-0022** — this run's own
rebase onto `master` found `#178` had already merged MC-0019 while this
PR was open, so MC-0019 is no longer free; `#174`/`#182` still hold
MC-0018/MC-0020 as open, unmerged PRs as of this note); a fresh
Known-Fix-Research-Queue candidate (`MINTCART_FUNCTIONAL_SCOPE.md` §18 —
e.g. the classic OpenCart pagination "`?page=1`" duplicate-URL issue on
the in-page sort/limit links themselves, as opposed to the `<link
rel="canonical">` tag MC-0002 already covers) would need careful
reproduction before filing, not attempted here for lack of time; or
re-check `gh pr list --state open` first, since this backlog moves fast.
