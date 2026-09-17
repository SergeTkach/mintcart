# `MintCart\Seo\SchemaOrgBuilder`

Builds Schema.org JSON-LD structured data for the three types
`MINTCART_FUNCTIONAL_SCOPE.md` §16 names explicitly under **SEO Core**:
Product, Breadcrumb, Organization (`MINTCART_TECHNICAL_PLAN.md` §31 groups
these under "Structured Data").

PHP: `upload/system/library/mintcart/seo/schemaorgbuilder.php`
(class `MintCart\Seo\SchemaOrgBuilder`)

Test: `tests/mintcart/seo/schema-org-builder-test.php`
(`php tests/mintcart/seo/schema-org-builder-test.php`, 16 scenarios)

Consumers: `upload/catalog/controller/common/header.php` (Organization),
`upload/catalog/controller/product/category.php`,
`upload/catalog/controller/product/search.php`,
`upload/catalog/controller/product/manufacturer.php` (`info()`) and
`upload/catalog/controller/product/special.php` (BreadcrumbList) — the
four listing pages that build `$data['breadcrumbs']` — and
`upload/catalog/controller/product/product.php` (Product) — see "Product:
live-wired" below.

## Design

No OpenCart dependency — no `\Registry`, `\DB`, currency/tax/url services —
same shape as `ProductViewProvider`/`SearchService`/`CartService`. Callers
resolve raw values (a numeric price, an ISO 4217 currency code, an absolute
URL) upstream; this class only assembles the JSON-LD array structure and
encodes it for safe embedding in a `<script type="application/ld+json">`
tag via `toJsonLd()`.

Three builder methods, one per schema type:

- `organization(array $store): array` — requires `name`/`url`; optional
  `logo`/`telephone`/`address`. `telephone` becomes a `ContactPoint` with
  `contactType: "customer service"`; `address` becomes a `PostalAddress`
  with a single `streetAddress` field (OpenCart's `config_address` is a
  free-text block, not structured street/city/postcode/country fields, so
  there is nothing more specific to map without inventing data OpenCart
  doesn't actually have).
- `breadcrumbList(array $items): array` — takes the exact `[{text, href}]`
  shape every listing controller already builds for `$data['breadcrumbs']`
  (OpenCart's own key names, not renamed here so a controller can pass that
  array straight through with zero reshaping). Items missing `text`/`href`
  are skipped; `position` stays contiguous (1, 2, 3, ...) across a skip.
- `product(array $product): array` — requires `name`/`url`; optional
  `image`/`description`/`sku`/`brand`/`price`/`currency`/`in_stock`/
  `rating_value`/`rating_count`. `offers` is only added when `price` is a
  real, non-`false`, non-`null` value (OpenCart's own "price hidden from
  this customer group" convention is `false`, matching
  `ProductViewProvider`'s `price` field) — `priceCurrency` defaults to
  `USD` when the caller doesn't pass one. `aggregateRating` is only added
  when both `rating_value` and `rating_count` are truthy (a product with a
  computed average but zero actual reviews must not claim an
  `AggregateRating` — that would be inaccurate structured data, not just an
  empty field).

`toJsonLd(array $schema): string` encodes with
`JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG`.
`JSON_HEX_TAG` is the security-relevant flag: it hex-escapes `<`/`>` inside
string values, so a store name or product description containing literal
`</script>` can never break out of the `<script type="application/ld+json">`
wrapper — the same class of risk the Setup Wizard's Contacts step already
guarded against for `javascript:` URLs (`docs/upstream-diff.md`'s
`wizard.php` entry).

## Why controllers build the JSON-LD, not `ComponentRenderer`

Unlike `product-card`/`header`/`footer`, structured data has no visual DOM
and nothing for a theme to override — it is metadata a search engine reads,
not markup a customer sees or a designer restyles. Routing it through
`ComponentRenderer`'s active-theme/parent/`mint_default` resolution chain
would add theme-override machinery with no real use case (`CLAUDE.md` §85):
no theme would ever want a *different* `BreadcrumbList` shape than another,
only different visible breadcrumb styling, which `mc-breadcrumb` (Phase 4)
already owns. So each consuming controller builds the schema directly with
`SchemaOrgBuilder` and hands the encoded JSON string to its own template —
same division of labor `MintCart\Catalog\CategoryMoveValidator` or
`MintCart\Onboarding\StoreReadiness` use for their own single-purpose
consumers.

## Gated the same way as every other Mint-only controller addition

Each of the three wiring points is gated by
`MintCart\Design\ActiveTheme::isMintAware(DIR_TEMPLATE, $mint_theme)` — the
same check `product-card`/`header`/`footer` wiring already uses. Neither the
legacy `default` theme nor an unrelated third-party theme has a `theme.json`
manifest, and neither of their templates reads the new `$data['mc_schema_*']`
keys, so stores on either kind of theme pay no extra cost and see no
behavior change. A failure inside `SchemaOrgBuilder` (e.g. a required key
missing) is caught and logged via `MintCart\Core\Logger`, never allowed to
fatal the page — same "log and disable for this request" pattern
`ComponentRenderer` failures already use in `header.php`/`footer.php`.

**`category.php`/`search.php`/`manufacturer.php`/`special.php` specifically
re-check `isMintAware()` fresh**
rather than reusing `$mint_render_product_cards` — that variable can be
flipped to `false` mid-request by `ProductCardListRenderer::isEnabled()`
after an unrelated per-product render failure (a broken `product-card.twig`
must not also silently suppress a perfectly valid breadcrumb schema).

## Organization: site-wide, not per-component

`header.php` builds `mc_schema_organization` once per request (Organization
schema is a single, page-independent fact about the store, not something
that varies by which page loaded), reusing the exact same config values
`HeaderViewProvider`/`FooterViewProvider` already read
(`config_name`/`config_logo`/`config_telephone`) plus `config_address` — no
new OpenCart storage, no new Mint-owned table. Rendered in
`mint_default/template/common/header.twig`'s `<head>`, inside the existing
`{% if mc_schema_organization %}` guard so a caught build failure (above)
renders nothing rather than an empty `<script>` tag.

## BreadcrumbList: reuses each controller's existing `$data['breadcrumbs']`

`category.php`, `search.php`, `manufacturer.php` (`info()`) and
`special.php` all already build `$data['breadcrumbs']` for their own
visible breadcrumb nav (`mc-breadcrumb`, Phase 4) — the schema is built
from that same array, once every entry has been appended (breadcrumb
arrays grow incrementally through those methods, so building the schema
too early would silently drop later entries). Rendered as a second
`<script type="application/ld+json">` block right after `{{ header }}` in
`category.twig`/`search.twig`/`manufacturer_info.twig`/`special.twig`.

## Product: live-wired

`product()` is now wired into `catalog/controller/product/product.php`,
right before `$mint_diagnostics->mark('view_rendered')`, gated by the same
`ActiveTheme::isMintAware(DIR_TEMPLATE, $mint_theme)` check re-checked
directly (not `$mint_render_product_cards`, for the same
flipped-mid-request reason `category.php`/`search.php`'s BreadcrumbList
wiring already documents above). `name`/`url`/`image`/`description`/`sku`/
`brand` all reuse values the controller already resolved for the page
itself (`$data['share']`/`$data['popup']`/a `strip_tags()`'d copy of
`$data['description']`/`$product_info['sku']`/`$product_info['manufacturer']`)
— no new OpenCart calls. `price` is computed fresh from
`$product_info['price']`/`['special']` rather than reusing the method's
`$tax_price` local, which by that point in `index()` has been reassigned
inside the related-products `foreach` loop to the *last related product's*
price, not the main product's; it is `false` when `config_customer_price`
hides pricing from a logged-out customer, matching this builder's own
"`false` means hidden" convention. `in_stock` is `$product_info['quantity']
> 0`; `rating_value`/`rating_count` are the product's raw `rating`/
`reviews` counts (the builder itself only emits `aggregateRating` when both
are truthy, so a product with zero reviews correctly gets none). Rendered
as a second `<script type="application/ld+json">` block right after
`{{ header }}` in `product.twig`, same position `category.twig` uses for
its own `mc_schema_breadcrumb` block. `product.php` does not (yet) build a
per-item `$data['breadcrumbs']` trail worth a `BreadcrumbList` of its own
distinct from `category.php`'s — that remains a separate, unclaimed future
increment, not part of this one.

## Definition of Done

- [x] Pure, OpenCart-independent contract, unit-tested in isolation
- [x] `toJsonLd()` is safe to embed directly in a `<script>` tag
      (`JSON_HEX_TAG` — no manual escaping needed by a caller)
- [x] Live-wired for Organization (header, all Mint-aware pages),
      BreadcrumbList (category, search, manufacturer, special) and Product
      (product detail page)
- [ ] Validated against a real page with Google's Rich Results Test /
      schema.org validator (`docs/MANUAL_VERIFICATION.md`, Phase 18 entry —
      this environment has no MySQL/`vendor/twig/twig` to render a real
      page)
