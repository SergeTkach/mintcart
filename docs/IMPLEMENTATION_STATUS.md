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

- [ ] Create MintCart PHP namespace/bootstrap (`MintCart\`)
- [ ] Service container / service registry bridge into OpenCart's existing registry
- [ ] OpenCart adapter interfaces (`MintCart\Compatibility\OpenCart3\*`)
- [ ] Migration manager (`mc_migration` table, ordered/logged/apply-once migrations)
- [ ] Version information / basic logging / health checks
- [ ] CI (if in scope for this stage)

Not started. **This is the next step** for the following routine run.

## Phase 2 — Frontend foundation

- [ ] `package.json` + `package-lock.json`
- [ ] esbuild config
- [ ] `assets/src/` / `assets/dist/` structure
- [ ] Pin and install Bootstrap 5.3.8, Swiper 14.1.x, SortableJS 1.15.7,
      Bootstrap Icons 1.13.x via npm (no CDN)
- [ ] `catalog.js`, `admin.js`, `index.css` entry points
- [ ] `css/compatibility/bootstrap3-shim.css` for legacy OpenCart 3 / Bootstrap 3
      markup (do not load Bootstrap 3 and 5 globally together)

Not started.

## Phase 3 — Design System (tokens)

- [ ] Primitive / semantic / component-local CSS custom property tiers
- [ ] Token categories: color, typography, spacing, radius, border, shadow, motion,
      z-index
- [ ] Neutral MintCart default tokens (no Livenza/Cartello branding carried over)

Not started.

## Phase 4 — Core UI components

- [ ] `mc-button`, `mc-field`, `mc-select`, `mc-checkbox`, `mc-radio`, `mc-card`,
      `mc-badge`, `mc-price`, `mc-rating`, `mc-alert`, `mc-breadcrumb`,
      `mc-pagination`, `mc-quantity`, `mc-modal`, `mc-drawer`, `mc-toast`
- [ ] Each component: Twig contract, CSS, JS (if needed), a11y states, doc example

Not started.

## Phase 5 — Layout conventions

- [ ] Document Bootstrap 5 grid/utility usage rules (no parallel grid framework)
- [ ] Verify responsive storefront layout achievable without Bootstrap 3

Not started.

## Phase 6 — Product Card (first reference component)

- [ ] `ProductViewProvider` contract (id, name, url, image, secondary_image, price,
      special, tax, rating, stock, badges, options_summary, actions)
- [ ] `mc-product-card` Twig + CSS + JS
- [ ] Wishlist / compare / add-to-cart / quick-order support

Not started.

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

Start **Phase 1 — Foundation**: create the `MintCart\` PHP namespace/bootstrap and
the migration manager (`mc_migration` table + migration runner), per `CLAUDE.md` §21
steps 8–9 and `MINTCART_TECHNICAL_PLAN.md` §4/§7. Recommended scope for one increment:
namespace bootstrap + migration manager + the first migration (creating
`mc_migration` itself). Defer the OpenCart adapter interfaces and frontend pipeline
(Phase 2) to subsequent increments if the first one is already substantial.
