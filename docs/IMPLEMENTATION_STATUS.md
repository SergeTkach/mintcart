# MintCart — Implementation Status

Rewritten 2026-10-08 from the code, not from old notes. The full history
of earlier runs (decisions, per-run details, verification notes) is in
[`implementation-log.md`](implementation-log.md).

Legend: `[x]` done and wired into a real screen/page · `[~]` partly done
(what is missing is named) · `[ ]` not started. "Live-checked" = clicked
through on a running store; everything else is covered by unit tests,
headless-browser probes or both.

Branch state: PR #190 merged to master 2026-10-08. Work continues on
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
  - [~] Comfort preset + `mint_comfort` theme (ADR 014): neutral greys, soft
        shapes, own header/card, graphite menu bar; live-checked on desktop
        (home, product, information page); not mobile, no comfort footer
  - [~] Gallery preset + `mint_gallery` theme with tile product card (ADR 013);
        header + tile card live-checked on the local store (desktop, home +
        category, headless screenshots; not mobile, product page or
        checkout); no gallery footer yet
  - [~] Slots: `SlotRegistry` exists, not rendered by any template yet
  - [ ] Theme live preview (ADR 008 decided, not built)
  - [ ] `settings.json`-driven theme settings form

## Blocks & admin

- [x] Phase 10 — Homepage Blocks: 15 block providers + templates,
      Design → Homepage with drag & drop
- [x] Design → Homepage block form: slides, promo cards, banners, benefits, testimonials, brands, photos
      and FAQ are edited as rows (drag & drop, add / remove, up / down buttons, language tabs, pictures
      through OpenCart's file manager) instead of JSON textareas (2026-10-25). Server round-trip checked
      on the demo blocks; the file-manager pick and drag in the browser not yet clicked through
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
- [~] Delivery / payment / returns on the product page: built from enabled
      extensions + Return Terms page (2026-10-08); hand-written text not
      supported yet (needs a decision on where it is edited)
- [x] Top info bar (Business → Contacts, one text for all languages;
      not live-checked)
- [x] Configurable (manual) badges: Marketing → Product Badges, `mc_product_badge`
      (`docs/developer/commerce/product-badges.md`); cards only, not the product page
- [~] Demo data: `scripts/demo/build_demo.py` replaces the stock OpenCart catalog
      with a small home-goods store on free Pexels photos (credits in
      `upload/image/catalog/demo/shop/CREDITS.txt`): 8 root categories with
      subcategories, 48 products with a photo each, specifications, specials,
      5 brands, reviews, badges, and the home page blocks (slider, benefits,
      categories, new arrivals, about, reviews, FAQ, CTA). Applied to the local DB by
      hand; not wired into the Setup Wizard's optional demo step yet.
      `scripts/demo/build_local_electronics.py` is a local-only variant with an
      electronics catalogue (third-party photos, git-ignored; never ship it)
- [x] Three-level category tree (`MintCart\Catalog\CategoryTree`) behind the
      menu and the category module; `mint_comfort` shows it in the menu bar,
      the bordered-free category list and the mobile drawer

## Productivity

- [~] Phase 16 — Bulk Editor: multi-select bulk update of status / quantity /
      price etc. in Store → Products; no true inline edit, no chunked jobs
- [x] Phase 17 — Category drag & drop (SortableJS, cycle/path validation)

## SEO / Fixes / Performance

- [x] SEO -> Redirects: 301 from dirty addresses, category paths in a product URL, trailing slash and `?page=1` to the one SEO URL;
      an old keyword redirects to the page after the keyword is changed (written by events on the product / category / page /
      brand save); manual redirects; information pages got a canonical link. Checked with curl and unit tests
- [x] SEO menu section: Meta Tags (title / description templates per page type and language, live preview),
      System Pages, All URLs (ADR 018); URL Generator (templates, transliteration, auto-fill of the SEO URL fields in the
      product / category / page / brand forms, batch for items without a URL). Browser-checked only through the server
      endpoints and screenshots; the auto-fill JS in the forms is not clicked through yet
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
- [x] Homepage Builder Slider block (split / full / cards layouts, rich slides)
      on the `mc-slider` engine; Customer reviews page + review-backed Testimonials;
      manual product badges (set + per-product assignment); Quick Order on the
      product page; "Added to cart" modal; sticky buy box
- [x] Home page rhythm (ADR 015) and retail header (ADR 016): tinted bands, soft
      shapes, dark top bar with phone, one-row header, discount tags, ratings
- [x] Demo data: `npm run demo:home` (Pexels photos) / `demo:electronics` (local only)
- [~] Fresh install from `upload/` → wizard → first order, end to end: the release zip
      installs on a clean DB and the storefront/admin/wizard open (2026-10-23); fixed on
      the way: `.gitignore` dropped `system/storage/*` (placeholders, Twig vendor, Test
      classes), admin header lost its assets, install defaults (Mint Comfort theme,
      MintCart logo, own home-goods demo catalogue via `install/mintcart-demo.sql`),
      MintCart admin logo/footer. Still to do: wizard steps 5-9 click-through and a
      real test order on the clean install
- [x] Storage outside the web root from the first install (`StorageLocation`, installers
      web + CLI) so OpenCart's security notice never appears; System > Maintenance >
      Storage moves it later (2026-10-23)
- [x] Setup Wizard: shipping methods set up inline (cost, tax class, zone) and toggled
      over AJAX; steps passed but not set up get an amber `!`, unmet readiness rows link
      back to their step; admin menu pointer (red) shows where each setting lives
      (`menu-hint.js`, `mc_hint` / `mc_point`); first product has optional per-language
      text; MintCart favicon on the wizard and the admin
- [x] Setup Wizard Language & currency: currency picked from a bundled ISO list with live
      rates (ECB, then open.er-api.com), `uk-ua` / `ru-ru` packs, Select2-style
      searchable selects, "+" rows saved over AJAX (`add-rows.js`, `select-search.js`)
- [ ] Smoke tests per Implementation Guide §78 (browser)

## Early Access / 1.0

- [ ] Phase 12 (Technical Plan) — Early Access with 10–30 users
- [ ] 1.0 — freeze Theme API, Blocks API, Commerce contracts

## Tests

`php` over `tests/mintcart/**/*-test.php`: 109 files, 107 pass. The 2
failures are environment-specific and predate this branch
(`design/media-model-test.php` path separator on Windows,
`fixes/mc-0020-pgsql-reconnect-test.php` needs pgsql).

## Planned work (agreed 2026-10-22)

Checkout is done interactively with the user, one piece at a time, in this
order (each builds on the previous; billing address last-but-one because it
splits `payment_address` / `shipping_address` in `mintcart/checkout.php`):

1. [ ] Address book for logged-in customers (pick saved address / new)
2. [ ] Registration in checkout (guest "create account" + password)
3. [ ] Custom fields (from OpenCart custom_field model)
4. [ ] Captcha (stock OpenCart captcha extensions)
5. [ ] Separate billing address
6. [ ] Reward points (next to coupon / gift certificate)

Smaller items, taken autonomously in this order:

1. [x] Configurable (manual) product badges
2. [x] Default system-page URL aliases on install (migration 202610221300)
3. [x] Render Theme API slots in templates (`Slots::shared()`, docs/developer/theme-api/slots.md)
4. [x] Language folder in the URL (`/uk/...`, default language at the root) + hreflang and a link-based
   language switcher (ADR 017, docs/developer/seo/language-urls.md). Written, unit-tested for the URL logic,
   not yet clicked through on a two-language store; sitemap per language and redirects for already indexed
   stores are not done
5. [~] Admin settings search: jump to a field (done, not browser-checked); Ukrainian labels need an admin uk-ua pack
6. [ ] Entity Picker: create-new in a drawer

Done 2026-10-25 (browser check pending): Business > Delivery / Payments become the one place to
manage methods (an "Add a method" button that installs an available extension,
inline settings instead of the pencil to another page), Extensions stays under
Advanced; the wizard's Delivery and Payment steps use the same screen.

Shipping carriers (added 2026-10-23, after the first release):

1. [x] Nova Poshta (`mc_novaposhta`, live API, no sync tables) and Ukrposhta (`mc_ukrposhta`, own
   directory in `mc_ukrposhta_city` / `mc_ukrposhta_branch`, region-by-region admin sync): city and
   point form comes from the extension itself through the checkout hook
   (`docs/developer/commerce/shipping-method-forms.md`), no shared city field. Written without API
   keys: client parsing is unit-tested, the live API calls and the checkout form still need a
   click-through. Original plan was:
   ported from the Cartello project
   (`C:\laragon\www\themes\cartello`: `ocnp_novaposhta`, `st_ukrposhta`): adapt
   them to the MintCart design and to the MintCart checkout. Cartello's versions are
   built around the old checkout, so first check how they behave in ours (city and
   warehouse pickers, price calculation, API key stored and shown inline in the
   wizard's delivery card like the core methods). No OCMOD, no controller patches
2. [ ] US postal carriers (2-3, e.g. USPS, UPS, FedEx) and UK (e.g. Royal Mail,
   Parcelforce): OpenCart already ships some of them; check what works on the
   MintCart checkout, then give them inline settings in the wizard
3. [ ] DHL
4. [ ] Inline settings for payment methods in the wizard (same mechanism as
   shipping: `system/mintcart/data/method-fields.json`; the Business screens and
   `ModelMintcartMethods` already take any type)

Design > Styles rebuilt 2026-10-25 (docs/developer/theme-api/styles-screen.md): look tiles with real swatches,
main / accent colour, corners, borders, page width, button style and shape, heading / body fonts, live preview. Written,
unit-tested, storefront output checked; the admin screen itself not yet clicked through.

Heavy items not in this batch: first end-to-end release (needs a clean DB),
admin navigation restructure, theme live preview + `settings.json` form,
full Bulk Editor, rollback action.

## Shortest path to a first release

1. Live click-through: Setup Wizard in the admin; checkout with an active
   coupon (`docs/MANUAL_VERIFICATION.md`).
2. Run `npm run release`, install the zip on a clean DB, go install →
   wizard → product → order.
3. Decide whether the product-page delivery/returns block also needs
   hand-written text (the automatic version is in).
4. Admin navigation restructured in business language.
5. Early Access.
