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
- [x] CI — `.github/workflows/ci.yml`: `php-lint` (`php -l` over every `.php`
      under `upload/` and `tests/`), `php-tests` (runs every
      `tests/mintcart/**/*-test.php` smoke test, fails the job on any nonzero
      exit), `frontend-build` (`npm ci && npm run build`). Runs on push/PR
      against `master`.

**In progress.** Remaining for a future run: `MintCart\Compatibility\OpenCart3\*`
adapters (no consumer needs one yet — first candidate is likely `SettingsAdapter`
when Business Settings/Setup Wizard starts, per `CLAUDE.md` §85 "API appears when
there's a real consumer") and a health-check surface. `Bootstrap::boot()` has
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
      Actual controller wiring was deferred to Phase 8 (Theme resolver) and
      has since landed for Category, Search, Special and Manufacturer — all
      four now via the shared `MintCart\Design\ProductCardListRenderer`.
      Phase 6's listing-controller wiring is complete.
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
      Navigation" specifically is **not** addressed — `mc-header` has no
      responsive off-canvas/hamburger behavior yet, it relies on
      `flex-wrap` only; a dedicated mobile nav pattern (likely composing
      `mc-drawer`, Phase 4) is deferred to a future run.
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
DOM with 0 OCMOD). Two things remain, tracked as follow-ups rather than
blocking Phase 7 itself:
- **Mobile Navigation** — no dedicated pattern yet (see above).
- **MintCart's own compiled asset bundle (`assets/dist/{css,js}`) is still
  not enqueued on any live page** — `mint_default/template/common/header.twig`
  deliberately keeps the exact same Bootstrap 3/jQuery/Font Awesome/
  `common.js` stack `default`'s header.twig loads, because swapping it
  safely needs a full call-site audit of every page/widget that depends on
  jQuery/`common.js` (cart AJAX, form validation, account/checkout pages)
  per `CLAUDE.md` §10/§84 — out of scope for a single component increment.
  Practical effect: **no `mc-*` component CSS has any visible effect on a
  real page yet**, since nothing links `index.min.css`. This is the single
  most important next piece of plumbing before any component's live
  rendering can be visually verified — see
  `docs/developer/components/header.md` "Known gap" for detail. A natural
  next-run candidate.

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

## Phase 10 — Homepage Blocks

- [x] Block API storage (`mc_page`, `mc_page_description`, `mc_block`,
      `mc_block_description`) — see "Sub-step 1" below
- [x] Block Provider / Renderer / Template separation — see "Sub-step 2" below
- [~] First blocks: Products (see "Sub-step 2") and Banner Grid (see
      "Sub-step 3" below) done; Benefits, Text/Image, Brands, Testimonials,
      FAQ not started (initial set per Implementation Guide §51). Hero and
      Categories were in progress in open PRs #30/#31 as of this run — not
      yet merged into master, so not reflected as done here; check open PRs
      before starting either to avoid duplicating that work.
- [ ] Homepage drag-and-drop editor (SortableJS)

In progress. PR #16 (storage), this run (Provider/Renderer + Products block).

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

**Next step:** the remaining first-wave block types (Hero, Categories,
Benefits, Text/Image, Brands, Testimonials, FAQ) each need their own
Provider + `blocks/<type>.twig` following the same shape Products/Banner
Grid established, or the Homepage drag-and-drop editor (admin UI to
actually create/reorder `mc_page`/`mc_block` rows — until that exists, a
block instance has to be inserted by hand to be visible). Check open PRs
first (Hero/Categories were in progress in #30/#31 as of this run) before
starting a block type someone else may already be building.

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
      Not wired into any live backend or admin screen yet — same Phase 12
      deferral as `McEntityPicker`. Doc:
      `docs/developer/components/media-picker.md`.

**All nine Phase 11 primitives are now done:** `McDrawer`/`McModal`/
`McToast` (via Phase 4), `McConfirm`, `McSortable`, `McTabs`, `McProgress`,
`McEntityPicker` and `McMediaPicker`.

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

## Phase 12 — Entity Picker

- [x] List primitive shipped (Phase 11 — `McEntityPicker`, see above):
      search, pagination, images, multi-select, keyboard nav, selected
      state, all against a caller-supplied data source.
- [ ] Filters (beyond search text) — not attempted; no real per-entity
      filter requirement identified yet.
- [ ] Real `fetchItems` backends per entity type (products, categories,
      manufacturers, attributes, options, information pages, customers)
      — none exist; each needs a normalized search/autocomplete endpoint.
- [ ] Live wiring into an actual admin screen/field.
- [ ] Create-new flow via drawer/modal — not attempted.

List behavior is done (Phase 11); everything that turns it into a real,
backend-connected picker on an actual screen is not started.

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
- [ ] `SearchService`, `QuickOrderService`, `CheckoutService` — not started.
- [ ] AJAX Search normalized endpoint — not started.
- [ ] Quick Order component — not started; `mc-product-card`'s
      `data-mc-action="quick-order"` stays hook-only.
- [ ] Modern checkout UI over existing shipping/payment extensions/totals/order model — not started.

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

In progress.

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
- [x] `docs/fixes/registry.md` Fix Registry established, entries added only after
      reproduction on the MintCart baseline (per `CLAUDE.md` §13) — MC-0001
      (`category.php` blank language strings) and MC-0002 (`search.php`
      missing canonical/prev/next links, this run)
- [ ] Diagnostics dev mode (query count, DB time, render time, cache status)
- [ ] Measured performance improvements (lazy loading, responsive images, asset
      minification, cache invalidation API — no mandatory Redis)

Fix Registry mechanism is established and has two real entries; the rest of
this phase (diagnostics, measured performance work) is not started.

## Phase 11 (Technical Plan) — Update / Compatibility

- [ ] Update Manager (check → compatibility → backup → download → verify →
      maintenance → files → migrations → cache clear → health check → done)
- [ ] Theme/extension rollback (RESEARCH item per Functional Scope §21)
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
