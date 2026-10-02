# Developer architecture overview

`MINTCART_TECHNICAL_PLAN.md` §40 lists `docs/developer/architecture/` as
one of three `docs/` subtrees (alongside `docs/user/` and `docs/adr/`).
Until this file, it was empty — every layer had its own accurate,
kept-current per-feature doc under `docs/developer/{core,compatibility,
commerce,design,blocks,seo,update,...}/`, but nothing tied them together
for someone opening this repository for the first time. `CLAUDE.md` §90
("Final principle") is the closest thing that existed before this file,
and it's still the right one-paragraph summary — this document expands it
into a map of *where each layer actually lives on disk* and *which
existing doc to read next* for each one.

This is a map, not a duplicate spec. It does not repeat what
`MINTCART_TECHNICAL_PLAN.md` or the per-feature docs already say
correctly, and it does not track implementation status — that's
`docs/IMPLEMENTATION_STATUS.md`'s job, and it changes too often (a new PR
most days) for a hand-written overview to keep up without going stale.
Where this doc says a layer "exists," it means the classes/routes below
are real, on `master`, today — not that the whole layer is finished.

## The dependency direction (`CLAUDE.md` §90 / §9, `MINTCART_TECHNICAL_PLAN.md` §1)

```text
OpenCart
   ↓
MintCart Compatibility / Adapters
   ↓
MintCart Core APIs
   ↓
Admin / Commerce / Design / SEO / Update
   ↓
Components / Blocks
   ↓
Themes / Presets / Extensions
```

Dependency only ever points down this list. A Theme calls a MintCart
component/provider contract; it never reaches past that into an OpenCart
controller, and it is never patched into place with OCMOD or a core PHP
change (`MINTCART_IMPLEMENTATION_GUIDE.md` §90's own diagram states the
same rule from the Theme's point of view: `Theme → MintCart API →
OpenCart Adapter → OpenCart`, never `Theme → arbitrary OpenCart controller
→ OCMOD → core patch`). Every layer below is described in that order.

## Where the layers actually live

All MintCart PHP lives under the `MintCart\` namespace
(`upload/system/library/mintcart/`), loaded through OpenCart's own
autoloader — there is no separate MintCart framework or bootstrap process
outside OpenCart's normal request lifecycle (`MintCart\Core\Bootstrap`
hooks into it, it does not replace it).

### Compatibility / Adapter layer

`upload/system/library/mintcart/compatibility/`

Isolates OpenCart-3-specific calls so the layers above never call an
OpenCart model directly for data OpenCart already owns.

```text
MintCart\Compatibility\OpenCart3\SettingsAdapter
MintCart\Compatibility\OpenCart3\CategoryAdapter
MintCart\Compatibility\OpenCart3\CustomerAdapter
MintCart\Compatibility\OpenCart3\ExtensionAdapter
MintCart\Compatibility\OpenCart3\TotalsAdapter
MintCart\Compatibility\CompatibilityChecker
MintCart\Compatibility\InstallManifestParser
```

Docs: `docs/developer/compatibility/*.md`, one per adapter. This is also
the layer a future `OpenCart4Adapter` would join without touching
anything above it (`MINTCART_TECHNICAL_PLAN.md` §5) — not attempted in
MintCart 1.0.

### Core

`upload/system/library/mintcart/core/`

```text
MintCart\Core\Bootstrap
MintCart\Core\Version
MintCart\Core\Logger
MintCart\Core\HealthCheck
MintCart\Core\Diagnostics
MintCart\Core\Migration\MigrationManager
MintCart\Core\Cache\CacheInvalidator
```

Docs: `docs/developer/core/*.md`. `MigrationManager` is why every
Mint-specific table (`mc_migration`, `mc_page`, `mc_block`,
`mc_quick_order`, `mc_installed_file`, ...) is created by an ordered,
apply-once, logged PHP migration under `upload/system/library/mintcart/
core/migration/` rather than ad-hoc SQL in an installer — see
`CLAUDE.md` §12/§40 and `MINTCART_TECHNICAL_PLAN.md` §7.

### Design (Theme API)

`upload/system/library/mintcart/design/`

```text
MintCart\Design\ComponentRenderer
MintCart\Design\ActiveTheme
MintCart\Design\ProductCardListRenderer
MintCart\Design\HeaderViewProvider
MintCart\Design\FooterViewProvider
MintCart\Design\CategoryPickerProvider / ManufacturerPickerProvider /
    ProductPickerProvider / AttributePickerProvider / OptionPickerProvider /
    InformationPickerProvider / CustomerPickerProvider
```

`ComponentRenderer` is the resolver behind `MINTCART_TECHNICAL_PLAN.md`
§11's three-step lookup (active theme override → parent/base theme →
Mint Default fallback) — the mechanism that lets a theme override
`product-card.twig` with zero OCMOD and zero controller patches. Docs:
`docs/developer/theme-api/*.md`. The `*PickerProvider` classes are the
normalization layer behind `McEntityPicker` (Phase 12) — each one turns a
raw OpenCart model result into the `{items: [...], total}` shape the
picker's `fetchItems` contract expects; see
`docs/developer/components/entity-picker.md`.

### Commerce

`upload/system/library/mintcart/commerce/`

```text
MintCart\Commerce\ProductViewProvider
MintCart\Commerce\CartService
MintCart\Commerce\SearchService
MintCart\Commerce\QuickOrderService
MintCart\Commerce\CheckoutTotalsService
MintCart\Commerce\CartGarbageCollector
```

Docs: `docs/developer/commerce/*.md`. `ProductViewProvider` is the single
normalizer `MINTCART_TECHNICAL_PLAN.md` §15D requires — Category, Search,
Manufacturer, Special and the Homepage Products block all build their
listings through it, so one `product-card.twig` override applies
everywhere a product is listed (see `docs/developer/commerce/
product-view-provider.md` and `docs/developer/theme-api/
product-card-list-renderer.md` for how `ProductCardListRenderer` glues
that provider to `ComponentRenderer` for every listing controller).

### Blocks (Homepage Builder)

`upload/system/library/mintcart/blocks/`

```text
MintCart\Blocks\BlockRenderer
MintCart\Blocks\HeroBlockProvider / ProductsBlockProvider /
    CategoriesBlockProvider / BannerGridBlockProvider / ...
```

One Provider class per block type (Hero, Products, Categories, Banner
Grid, Benefits, Brands, Testimonials, FAQ, Text, Text+Image, CTA,
Newsletter, HTML, Gallery, Spacer — `MINTCART_FUNCTIONAL_SCOPE.md` §10),
each returning normalized data for `BlockRenderer` to hand to a
theme-overridable Twig template, storage in `mc_page`/`mc_block`(+
`_description`) per `MINTCART_TECHNICAL_PLAN.md` §16. Docs:
`docs/developer/blocks/*.md`.

### SEO

`upload/system/library/mintcart/seo/`

```text
MintCart\Seo\MetaBuilder
MintCart\Seo\CanonicalUrlBuilder
MintCart\Seo\HreflangBuilder
MintCart\Seo\RobotsBuilder
MintCart\Seo\SchemaOrgBuilder
MintCart\Seo\SitemapBuilder
MintCart\Seo\SystemRoutes
```

Each is a pure, OpenCart-independent builder wired into one or more
storefront controllers as a live consumer — see `docs/developer/seo/*.md`
for which controller calls which builder today, and
`docs/IMPLEMENTATION_STATUS.md`'s Phase 18 section for what's still
unwired.

### Update Manager

`upload/system/library/mintcart/update/`

```text
MintCart\Update\UpdateOrchestrator
MintCart\Update\UpdateChecker
MintCart\Update\PackageManifest / PackageDownloader / PackageVerifier /
    PackageExtractor / PackageFileInstaller
MintCart\Update\BackupPlanner / BackupExecutor
MintCart\Update\InstalledFileLedger
MintCart\Update\MaintenanceMode
```

Each stage of `MINTCART_TECHNICAL_PLAN.md` §33's
`check → compatibility → backup → download → verify → maintenance →
files → migrations → cache clear → health check → done` flow is its own
class, composed by `UpdateOrchestrator::apply()`. Docs:
`docs/developer/update/*.md`, one per stage class.

### Onboarding / Business layer

`upload/system/library/mintcart/onboarding/`

```text
MintCart\Onboarding\WizardProgress
MintCart\Onboarding\StoreReadiness
MintCart\Onboarding\SocialProfileLinks
```

Backs the Setup Wizard (`admin/controller/mintcart/wizard.php`) and Store
Readiness dashboard/JSON endpoint. Docs: `docs/developer/onboarding/*.md`.

### Catalog helpers

`upload/system/library/mintcart/catalog/`

```text
MintCart\Catalog\BulkProductUpdateValidator / BulkProductUpdatePlanner
MintCart\Catalog\CategoryMoveValidator
```

Pure, OpenCart-independent validators/planners behind the admin Bulk
Editor (`Catalog → Products`) and Categories drag-and-drop reorder. Docs:
`docs/developer/catalog/*.md`.

### Admin UI primitives

Not a PHP namespace — a set of vanilla-JS ES module factories under
`assets/src/js/components/` (`createEntityPicker`, `createMediaPicker`,
`createSortable`, `createTabs`, `createProgress`, `createConfirm`, plus
the shared `mc-modal`/`mc-drawer`/`mc-toast` factories from Phase 4),
bridged into legacy non-module admin `<script>` blocks via
`window.MintCart.*` — see `docs/developer/admin/asset-enqueue.md` for why
that bridge exists and how a new admin screen enqueues the bundle safely
(`components.min.css` + `admin.min.js`, never the full `index.min.css` on
a page that still runs Bootstrap 3/jQuery). Docs:
`docs/developer/components/*.md`.

## A concrete request, top to bottom

`Design → Homepage` renders a Products block on the storefront:

```text
mc_block row (settings_json: category_id, limit, sort)
        ↓
MintCart\Blocks\ProductsBlockProvider
        ↓ (needs product data)
MintCart\Commerce\ProductViewProvider
        ↓ (needs OpenCart product rows)
ModelCatalogProduct (stock OpenCart, called through the block/commerce
        layer, never duplicated)
        ↓
MintCart\Blocks\BlockRenderer
        ↓
MintCart\Design\ComponentRenderer (theme override → Mint Default fallback)
        ↓
mint/blocks/products.twig (or a test theme's own override)
```

No OCMOD, no controller patch, no theme-specific copy of the product
query — this is the exact chain `CLAUDE.md` §19/§89's "critical
architecture milestone" checks: **Mint Default + a second test theme**
must be able to render this with different DOM/tokens/visual style while
sharing every PHP class in the chain above `ComponentRenderer`.

## Reading further

- **Why** MintCart exists, product philosophy: `MINTCART_PRODUCT_VISION.md`.
- **What** must not be lost, P0–P2 scope: `MINTCART_FUNCTIONAL_SCOPE.md`.
- **How it's structured**, phase sequencing: `MINTCART_TECHNICAL_PLAN.md`.
- **Exact coding rules** (namespaces, BEM, JS policy, Definition of Done):
  `MINTCART_IMPLEMENTATION_GUIDE.md`.
- **What's actually built vs. not, right now**:
  `docs/IMPLEMENTATION_STATUS.md` — the living source of truth this
  overview deliberately does not duplicate.
- **Every direct OpenCart core file change and why**: `docs/upstream-diff.md`.
- **Applied historical/compatibility bugfixes**: `docs/fixes/registry.md`.
- **Per-feature developer docs**: `docs/developer/{admin,blocks,catalog,
  commerce,compatibility,components,core,design,onboarding,seo,
  theme-api,update}/*.md` — one doc per class/component, each with its own
  "Verified" and "Next step" section.
