# MintCart — Implementation Status

Tracks progress against the phases defined in `MINTCART_TECHNICAL_PLAN.md` §42 and the
starting sequence in `CLAUDE.md` §21. Update this file at the end of every work
increment (see `CLAUDE.md` §14/§20).

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

## Phase 1 — Foundation

- [x] Create MintCart PHP namespace/bootstrap (`MintCart\`) — `MintCart\Core\Bootstrap`,
      autoloaded via OpenCart's existing `library()` autoloader
      (`upload/system/library/mintcart/core/bootstrap.php`); see `docs/adr/001-mintcart-core-foundation.md`
- [x] Service container / service registry bridge into OpenCart's existing registry —
      `MintCart\Core\Bootstrap` wraps `\Registry`, no parallel DI container
- [ ] OpenCart adapter interfaces (`MintCart\Compatibility\OpenCart3\*`)
- [x] Migration manager (`mc_migration` table, ordered/logged/apply-once migrations) —
      `MintCart\Core\Migration\MigrationManager` +
      `MintCart\Core\Migration\MigrationInterface`; naming convention and file shape
      documented in `upload/system/mintcart/migrations/README.md`; no MintCart-owned
      table exists yet so the migrations directory is currently empty; covered by
      `tests/mintcart/core/migration-manager-test.php` (in-memory DB double — no
      MySQL server available in this environment; real-DB verification is still
      needed before this is exercised against a live install)
- [x] Version information — `MintCart\Core\Version`
- [x] Basic logging — `MintCart\Core\Logger` (wraps OpenCart's `\Log`, writes to a
      dedicated `mintcart.log`)
- [ ] Health checks
- [ ] CI (if in scope for this stage)

**In progress.** Remaining for a future run: `MintCart\Compatibility\OpenCart3\*`
adapters (no consumer needs one yet — first candidate is likely `SettingsAdapter`
when Business Settings/Setup Wizard starts, per `CLAUDE.md` §85 "API appears when
there's a real consumer"), a health-check surface, and CI. `Bootstrap::boot()` has
no automatic call site yet by design (see ADR 001) — the first real caller wires it.

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
- [ ] `mc-select` (dedicated component beyond `mc-field__control`'s shared
      select styling — not attempted; see "Next step" below)
- [x] Each shipped component so far: CSS contract (no Twig yet — no theme/
      renderer exists until Phase 8, per CLAUDE.md §85), JS only where
      actually needed (`mc-alert`'s dismiss, `mc-quantity`'s stepper,
      `mc-modal`'s open/close triggers), a11y states, doc example with a
      Definition-of-Done checklist

**Phase 4 is complete** except for the intentionally-deferred `mc-select`
(15 of 16 components done: `mc-button`, `mc-alert`, `mc-field`,
`mc-checkbox`, `mc-radio`, `mc-badge`, `mc-price`, `mc-quantity`,
`mc-rating`, `mc-breadcrumb`, `mc-pagination`, `mc-card`, `mc-modal`,
`mc-drawer`, `mc-toast`). Verified with `npm install` (0 vulnerabilities —
this run's environment needed a fresh install, all packages resolved from
the committed lockfile with no version drift), `npm run build` (minified)
and `npm run dev` (sourcemapped), both compiling cleanly — the bundled CSS
contains `.mc-toast`/`.mc-toast__title`/`.mc-toast__message`/
`.mc-toast__close`/`.mc-toast--success|danger|warning|info` and a second
`toast-container{--bs-toast-zindex: var(--mc-z-toast)}` rule overriding
Bootstrap's own default, and the bundled JS contains the
`open-toast`/`close-toast`/`mc:toast:shown`/`mc:toast:hidden` strings in
both `catalog.min.js` and `admin.min.js` (spot-checked with `grep -o`
against `assets/dist/{css,js}/*`). No PHP files were touched in this
increment, so the Phase 1 PHP `tests/mintcart/` suite is not applicable. No
automated component/visual test harness exists yet in this repo — this
increment's verification is build-compiles + bundled-output grep + manual
code review only, same gap noted in previous Phase 4 runs (not new).

**Next step:** `mc-select` remains intentionally deferred — no real
consumer beyond what `mc-field__control`'s shared `<select>` styling
already covers (searchable/multi-select would be the trigger to revisit
it, per CLAUDE.md §85). With `mc-toast` landed, the next run should move to
**Phase 5 — Layout conventions**.

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
      Actual controller wiring (Category/Search/Manufacturer/Special calling
      this instead of their inline array-building) is intentionally deferred
      to Phase 8 (Theme resolver) — see "Next step" below.
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

Verified: `php -l` on `productviewprovider.php` (clean, previous run); the
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

## Phase 7 — Header / Footer / Navigation

- [ ] Header, Topbar, Logo, Search, Account, Cart, Navigation, Mobile Navigation
      component contracts
- [ ] Footer, Footer Columns, Contacts, Social links, Payments, Newsletter
- [ ] Centralized business data (no per-theme contact duplication)

Not started.

## Phase 8 — Theme resolver

- [ ] `MintCart\Design\ComponentRenderer` (active theme override → parent/base
      theme → Mint Default fallback)
- [ ] Prove: a test theme overriding only `product-card.twig` gets everything else
      from Mint Default automatically, 0 OCMOD

Not started. **Critical architecture milestone** — see `CLAUDE.md` §19 / Technical
Plan §45. Must pass before broad feature build-out.

## Phase 9 — Presets

- [ ] At least two radically different presets (e.g. Clean, Warm) on the same DOM
- [ ] Differ in colors/fonts/radius/spacing/card look/button look only, not Twig

Not started.

## Phase 10 — Homepage Blocks

- [ ] Block API (`mc_page`, `mc_page_description`, `mc_block`, `mc_block_description`)
- [ ] Block Provider / Renderer / Template separation
- [ ] First blocks: Hero, Products, Categories, Banner Grid, Benefits, Text/Image,
      Brands, Testimonials, FAQ (initial set per Implementation Guide §51)
- [ ] Homepage drag-and-drop editor (SortableJS)

Not started.

## Phase 11 — Mint Admin primitives

- [ ] `McDrawer`, `McModal`, `McToast`, `McConfirm`, `McTabs`, `McSortable`,
      `McMediaPicker`, `McEntityPicker`, `McProgress` (vanilla JS)

Not started.

## Phase 12 — Entity Picker

- [ ] Unified picker for products, categories, manufacturers, attributes, options,
      information pages, customers (search, filters, pagination, images,
      multi-select, keyboard nav, selected state)

Not started.

## Phase 13 — Setup Wizard

- [ ] Welcome → Store → Contacts → Language/Currency → Delivery → Payment → Design →
      First Product → Ready
- [ ] Progress saving, resume, skip optional steps, writes to standard OpenCart
      settings storage

Not started.

## Phase 14 — Store Readiness / Business Settings

- [ ] `MintCart\Onboarding\StoreReadiness` service (computed, not hardcoded,
      percentage + checklist)
- [ ] `Business → Contacts`, `Business → Delivery`, `Business → Payments` UI façades
      over standard OpenCart storage/extensions

Not started.

## Phase 15 — Commerce API

- [ ] `MintCart\Commerce\CartService`, `SearchService`, `QuickOrderService`,
      `CheckoutService`, `ProductViewProvider`
- [ ] Cart JS client (`add/remove/update/refresh`) + `mc:cart:updated` event contract
- [ ] AJAX Search normalized endpoint
- [ ] Quick Order component
- [ ] Modern checkout UI over existing shipping/payment extensions/totals/order model

Not started.

## Phase 16 — Bulk Editor

- [ ] Inline edit + multi-select bulk actions for Products (status, quantity, price,
      special, manufacturer, category, stock, SEO fields)
- [ ] Chunked/resumable AJAX batch jobs (no background daemon)

Not started.

## Phase 17 — Categories drag & drop

- [ ] SortableJS-based category move (transaction, cycle prevention, category_path
      rebuild, sort order update)

Not started.

## Phase 18 — SEO Core

- [ ] SEO URL, canonical, hreflang, meta title/description, robots controls, XML
      sitemap, Schema.org Product/Breadcrumb/Organization
- [ ] System route URL aliases (cart, checkout, search, contact, account, etc.)

Not started.

## Phase 9 (Technical Plan §42 numbering) — Productivity

*(Numbering note: Technical Plan §42 lists Bulk Editor / category drag-and-drop /
admin search under "Phase 9 — Productivity" as a plan-level grouping, while
Implementation Guide §51–§64 numbers them as Phases 16–17. This file follows the
Implementation Guide's per-feature phase numbers above; nothing is duplicated,
tracked once each.)*

## Phase 10 (Technical Plan) — SEO / Fixes / Performance

- [ ] SEO Core (tracked above as Phase 18)
- [ ] `docs/fixes/registry.md` Fix Registry established, entries added only after
      reproduction on the MintCart baseline (per `CLAUDE.md` §13)
- [ ] Diagnostics dev mode (query count, DB time, render time, cache status)
- [ ] Measured performance improvements (lazy loading, responsive images, asset
      minification, cache invalidation API — no mandatory Redis)

Not started.

## Phase 11 (Technical Plan) — Update / Compatibility

- [ ] Update Manager (check → compatibility → backup → download → verify →
      maintenance → files → migrations → cache clear → health check → done)
- [ ] Theme/extension rollback (RESEARCH item per Functional Scope §21)
- [ ] Compatibility Checker (`install.xml` analysis, heuristic warnings only)

Not started.

## Phase 12 — Early Access

- [ ] 10–30 real users/developers
- [ ] Measure: time to install, drop-off points, Time to First Test Order, theme
      override count

Not started.

## 1.0

- [ ] Freeze Theme API 1.0, Blocks API 1.0, Commerce contracts, compatibility policy

Not started.

---

## Next step for the next routine run

**`mc-button`, `mc-alert`, `mc-field`, `mc-checkbox`, `mc-radio`, `mc-badge`,
`mc-price`, `mc-quantity`, `mc-rating`, `mc-breadcrumb`, `mc-pagination`,
`mc-card`, `mc-modal`, `mc-drawer` and `mc-toast` are done — Phase 4 is
complete** except for the intentionally-deferred `mc-select`
(`mc-field.css`'s `select.mc-field__control` already covers the common case
of a `<select>` inside a form field; only revisit a dedicated `mc-select`
component if a real consumer needs something it can't do, e.g. a custom
searchable/multi-select widget — per CLAUDE.md §85, no API ahead of a real
consumer).

**Phase 5 — Layout conventions is done.** `docs/developer/layout-conventions.md`
documents the Bootstrap-5-grid-is-the-only-layout-system rule with examples,
and the "no Bootstrap 3 assumptions / no import-order conflicts" check was
done statically against the current CSS tree (see the Phase 5 section above
for exactly what was grepped/confirmed) — there is still no Twig/theme to
render a real page against, so a live browser check stays deferred to
Phase 8.

**Phase 6 — Product Card is complete.** Both sub-steps are done:
`ProductViewProvider` (prior run) and `mc-product-card` CSS/JS + documented
Twig contract (this run) — see the Phase 6 section above for what was
verified. `docs/developer/components/product-card.md` is the template
contract; there is still no live `.twig` file or controller wiring (that's
Phase 8, once the Theme resolver exists to actually resolve one).

The next run should start **Phase 8 — Theme resolver**
(`MINTCART_TECHNICAL_PLAN.md` §11, `MINTCART_IMPLEMENTATION_GUIDE.md` §28–29,
`CLAUDE.md` §19 "First architecture milestone"), the **critical architecture
milestone** — everything built so far (Phase 4/5/6 CSS/JS/PHP) has no way to
actually render on a page until this exists:

```text
- [ ] `MintCart\Design\ComponentRenderer` (active theme override → parent/base
      theme → Mint Default fallback)
- [ ] Prove: a test theme overriding only `product-card.twig` gets everything
      else from Mint Default automatically, 0 OCMOD
```

This is a large, foundational increment — bigger than Phase 6. Before
writing code:

- Re-read `CLAUDE.md` §19 and `MINTCART_TECHNICAL_PLAN.md` §11/§45 for
  exactly what "pass" means: **Mint Default** theme + **a second test
  theme**, both using the same providers/Commerce services/component
  contract, differing only in DOM/tokens/style, with 0 OCMOD/controller
  patches/copied business logic. If this milestone can't be made to work,
  stop and fix the Theme API before continuing — do not proceed to Phase 9+.
- This is the first phase that needs an actual **MintCart theme directory**
  to exist (`MINTCART_IMPLEMENTATION_GUIDE.md` §26/§30 — `theme.json`,
  `mint_default`), which nothing before Phase 6 needed. Read how OpenCart 3
  resolves `$this->load->view()`/theme paths today
  (`system/engine/loader.php`, `system/library/template/*`, the `default`
  theme's directory layout) before designing where `ComponentRenderer`'s
  override lookup sits relative to that — per `CLAUDE.md` §10/§84, inspect
  the actual mechanism rather than assuming it from memory.
- Concretely, this phase likely needs, in order: (1) a minimal `mint_default`
  theme directory with a `theme.json` manifest and a first real
  `product-card.twig` rendering the contract documented in
  `docs/developer/components/product-card.md`; (2) `ComponentRenderer`'s
  override-lookup logic (active theme → parent → Mint Default fallback);
  (3) wiring `ControllerProductCategory` (the simplest existing listing
  controller — see `upload/catalog/controller/product/category.php`) to call
  `ProductViewProvider` + `ComponentRenderer` instead of its current inline
  array-building and `product/category.twig` product loop; (4) a second
  minimal test theme that overrides only `product-card.twig`, proving (2)'s
  fallback actually works end-to-end.
- Given the size, treat this as multiple sub-steps and checkpoint after each
  (CLAUDE.md §4a) — e.g. land `ComponentRenderer` + `mint_default`'s
  `product-card.twig` as one run, the controller wiring as a second, the
  second test theme + milestone proof as a third, rather than attempting all
  of it in one pass. This is exactly the kind of run where hitting the usage
  limit mid-way is most costly (partially-wired controller, broken page) —
  prefer stopping at a clean boundary (e.g. `ComponentRenderer` alone, not
  yet called by any controller) over a half-wired one.

Phase 1's remaining items (`MintCart\Compatibility\OpenCart3\*` adapters,
health checks, CI) stay intentionally deferred — no real consumer yet.
