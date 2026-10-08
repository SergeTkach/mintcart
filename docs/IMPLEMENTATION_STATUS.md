# MintCart — Implementation Status

Rewritten 2026-10-08 from the code, not from old notes. The full history
of earlier runs (decisions, per-run details, verification notes) is in
[`implementation-log.md`](implementation-log.md).

Legend: `[x]` done and wired into a real screen/page · `[~]` partly done
(what is missing is named) · `[ ]` not started. "Live-checked" = clicked
through on a running store; everything else is covered by unit tests,
headless-browser probes or both.

Branch state: `phase-15-mint-checkout` → PR #190 (open). Follow-up work on
`phase-16-polish`.

## Foundation

- [x] Phase 0 — Audit (`docs/audit.md`, upstream baseline OpenCart 3.0.5.1)
- [x] Phase 1 — Foundation: `MintCart\` namespace, version, logger, health
      checks, diagnostics, migration manager, OpenCart 3 adapters
      (settings, product, category, order, customer, extension, totals,
      shipping quote, payment methods, checkout order)
  - [x] Installers run MintCart migrations (2026-10-08; before, nothing did)
  - [~] Fine-grained stage events: `MintCart\Core\Stage` exists, one live
        consumer (manufacturer page); no catalog of event names yet
  - [ ] Cache invalidation API: `CacheInvalidator` exists, no consumer
- [x] Phase 2 — Frontend foundation: npm + esbuild, Bootstrap 5.3.8,
      Swiper 14 (lazy bundle), SortableJS, Bootstrap Icons, BS3 CSS + JS
      shims, OpenCart 3 page-skeleton CSS (ADR 010, 012)
- [x] Phase 3 — Design tokens (`--mc-*`, primitive/semantic/component)

## Design system & Theme API

- [x] Phase 4 — Core UI components: button, field, select, checkbox, radio,
      card, badge, price, rating, alert, breadcrumb, pagination, quantity,
      modal, drawer, toast, confirm, tabs, progress, sortable
- [x] Phase 5 — Layout conventions (Bootstrap grid + `mc-*` BEM)
- [x] Phase 6 — Product Card + `ProductViewProvider`; one product-card
      pipeline (`mintcart/product_cards`) for all 11 product lists
- [x] Phase 7 — Header / Footer / Navigation (view providers, sticky header,
      mini-cart, mobile nav)
- [x] Phase 8 — Theme resolver: `ComponentRenderer`, `test_theme` overrides
      product-card / header / footer with 0 OCMOD (ADR 002)
- [x] Phase 9 — Presets: Clean, Warm (ADR 004); Design → Styles
  - [~] Slots: `SlotRegistry` exists, not rendered by any template yet
  - [ ] Theme live preview (ADR 008 decided, not built)
  - [ ] `settings.json`-driven theme settings form

## Blocks & admin

- [x] Phase 10 — Homepage Blocks: 15 block providers + templates,
      Design → Homepage with drag & drop
- [x] Phase 11 — Mint Admin primitives (drawer, modal, toast, confirm, tabs,
      sortable, progress)
- [~] Phase 12 — Entity Picker: picker + 7 entity backends, live in several
      admin forms; create-new-in-drawer not done
  - [~] Media Picker: one live consumer
- [~] Admin settings search: 31 screens, en + uk/ru synonyms; page-level
      only (no jump to a field), labels English only
- [~] Admin navigation by business language: a Business menu (Contacts /
      Delivery / Payments) is added to the stock menu; the full Store /
      Design / Marketing / Business / Settings / Advanced restructure is not
      done

## Onboarding

- [x] Phase 13 — Setup Wizard: all 9 steps (incl. Design presets and logo
      upload with SVG validation), progress/resume/skip, autostart from the
      dashboard, onboarding layout. **Not live-checked in the admin yet.**
  - [x] Admin permissions for all `mintcart/*` screens granted by migration
        (2026-10-08; before, a fresh install got "Permission denied")
- [x] Phase 14 — Store Readiness (service + dashboard widget + wizard step),
      Business → Contacts / Delivery / Payments

## Commerce

- [x] Cart service + JS client (`mc:cart:updated`), cart page, mini-cart
- [x] AJAX search endpoint + search box
- [x] Quick Order (service, endpoint, storefront component, admin list)
- [x] Wishlist, viewed products, cookie consent, product badges (automatic)
- [~] Mint single-page checkout (ADR 009): guest + logged-in, delivery /
      payment / totals recalculation, coupon / gift certificate, Terms
      dialog, duplicate-order fix. **One real order placed by the user.**
      Missing: address book, registration in checkout, custom fields,
      captcha, separate billing address, reward points. Auto-confirm for
      COD / free checkout added, not live-checked
- [x] Product page: gallery + lightbox, option pills, stock, sticky buy bar,
      section nav, live total price (`mintcart/price`, cart rules)
- [ ] Delivery / payment / returns info on the product page (needs a
      decision on where the text comes from)
- [ ] Top info bar; configurable (manual) badges

## Productivity

- [~] Phase 16 — Bulk Editor: multi-select bulk update of status / quantity /
      price etc. in Store → Products; no true inline edit, no chunked jobs
- [x] Phase 17 — Category drag & drop (SortableJS, cycle/path validation)

## SEO / Fixes / Performance

- [x] Meta builder, canonical, robots, Schema.org (Product, Breadcrumb,
      Organization), XML sitemap
- [~] hreflang: builder + one consumer; not verified multi-language
- [~] System page URLs (cart, checkout, search...): admin screen +
      `SystemRoutes`; default aliases on install not verified
- [x] Fix Registry (`docs/fixes/registry.md`) with reproduction tests
- [~] Diagnostics dev mode (query count, timings); measured performance
      work not started

## Update / Compatibility / Release

- [~] Update Manager: checker, verifier, backup, maintenance, file install,
      ledger, orchestrator, admin screen; never run against a real package
- [~] Rollback: changed-file detection + ledger; no rollback action
- [x] Compatibility Checker (install.xml analysis, admin screen)
- [~] Release packaging: `npm run release` (`scripts/release.js`); asset
      path bug fixed 2026-10-08; needs `zip` on PATH, never run end to end
- [ ] Fresh install from `upload/` → wizard → first order, end to end
- [ ] Smoke tests per Implementation Guide §78 (browser)

## Early Access / 1.0

- [ ] Phase 12 (Technical Plan) — Early Access with 10–30 users
- [ ] 1.0 — freeze Theme API, Blocks API, Commerce contracts

## Tests

`php` over `tests/mintcart/**/*-test.php`: 105 files, 103 pass. The 2
failures are environment-specific and predate this branch
(`design/media-model-test.php` path separator on Windows,
`fixes/mc-0020-pgsql-reconnect-test.php` needs pgsql).

## Shortest path to a first release

1. Live click-through: Setup Wizard in the admin; checkout with an active
   coupon (`docs/MANUAL_VERIFICATION.md`).
2. Merge PR #190.
3. Run `npm run release`, install the zip on a clean DB, go install →
   wizard → product → order.
4. Decide the product-page delivery/payment/returns text source and build it.
5. Admin navigation restructured in business language.
6. Early Access.
