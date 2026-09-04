# XML Sitemap

PHP: `upload/system/library/mintcart/seo/sitemapbuilder.php`
Test: `tests/mintcart/seo/sitemap-builder-test.php`

`MintCart\Seo\SitemapBuilder` is the last still-unclaimed item of Phase 18
(SEO Core, `MINTCART_FUNCTIONAL_SCOPE.md` §16 / `MINTCART_TECHNICAL_PLAN.md`
§31's "SEO URL, canonical, hreflang, meta title/description, robots
controls, XML sitemap, Schema.org Product/Breadcrumb/Organization" bullet).
The rest of that bullet was already covered before this class existed:

- **canonical** — stock OpenCart already calls
  `$this->document->addLink(..., 'canonical')` in
  `product.php`/`category.php`/`manufacturer.php`/`search.php`/
  `special.php`/`common/home.php`; MC-0002
  (`docs/fixes/registry.md`) additionally fixed `search.php`'s missing
  `canonical`/`prev`/`next` links. Nothing left to add here.
- **meta title/description** — stock OpenCart already calls
  `$this->document->setTitle()`/`setDescription()` from each entity's own
  admin-editable `meta_title`/`meta_description` fields (see e.g.
  `product.php`'s `setDescription($product_info['meta_description'])`).
  Nothing left to add here either.
- **hreflang**, **robots controls**, **Schema.org** — `MintCart\Seo\HreflangBuilder`,
  `MintCart\Seo\RobotsBuilder`, `MintCart\Seo\SchemaOrgBuilder` (sibling
  classes in this same `MintCart\Seo` namespace, shipped by other PRs in
  this backlog).
- **SEO URL** (system route aliases) — `MintCart\Seo\SystemRoutes` +
  `Design → System Pages` (another sibling PR in this backlog).

That leaves **XML sitemap** as the one sub-item with no MintCart
implementation anywhere in the repo yet.

## Why this ships as a pure builder only, with no live route

Every other `MintCart\Seo` class in this backlog (`HreflangBuilder`,
`RobotsBuilder`) already established the same pattern for the same
reason, documented in their own `docs/developer/seo/*.md` files: this
environment has no MySQL, so a live controller/route that actually
queries `model_catalog_product`/`model_catalog_category`/
`model_catalog_manufacturer`/`model_catalog_information` and paginates the
result into a real `mintcart/seo/sitemap.xml` response can't be exercised
end to end here — landing it untested would violate `CLAUDE.md` §10's
"never modify an existing OpenCart flow based only on memory" spirit
applied to a *new* flow, and there is also no
`MintCart\Compatibility\OpenCart3\ProductAdapter`/`CategoryAdapter` yet
(`MINTCART_TECHNICAL_PLAN.md` §5) to build that query against without
reaching into OpenCart internals directly from a brand-new controller.

`SitemapBuilder` itself has none of that risk — it is a pure XML
assembler, the same OpenCart-independent shape as `SearchService`/
`ProductViewProvider`/`HreflangBuilder`/`RobotsBuilder`: it takes an
already-resolved list of `{loc, lastmod, changefreq, priority}` entries
(URLs generated upstream via `$this->url->link(...)`, the same
"presentation values already resolved upstream" contract every sibling
class in this backlog uses) and returns a spec-valid XML string. Fully
unit-testable, fully verified, zero DB/`\Registry`/`\Document` dependency.

## API

```php
use MintCart\Seo\SitemapBuilder;

$builder = new SitemapBuilder();

$xml = $builder->urlset([
    ['loc' => 'https://example.com/', 'changefreq' => 'daily', 'priority' => 1.0],
    ['loc' => 'https://example.com/product/42', 'lastmod' => '2026-09-01', 'priority' => 0.8],
]);
```

- `loc` — required, absolute URL. Blank entries are skipped; duplicate
  `loc` values are de-duplicated (first occurrence wins), so callers do
  not need to de-duplicate their own product/category/manufacturer URL
  lists before calling this.
- `lastmod` — optional, either a pre-formatted string (e.g. a `Y-m-d` or
  full W3C datetime the caller already has) or a `\DateTimeInterface`
  (formatted via `format('c')`, ISO 8601/ATOM). A blank string is treated
  as absent, not rendered as an empty tag.
- `changefreq` — optional, one of `always`/`hourly`/`daily`/`weekly`/
  `monthly`/`yearly`/`never` per the protocol. Any other value is silently
  dropped rather than emitted as invalid XML content — a caller passing a
  typo'd value gets a slightly less complete sitemap, not a broken one.
- `priority` — optional, clamped to `[0.0, 1.0]` and formatted to one
  decimal place.

`urlset()` always returns a well-formed `<urlset>` document, even for
empty or all-invalid input (an empty sitemap is valid per the protocol) —
callers never need to special-case "no URLs yet".

### `MAX_URLS_PER_SITEMAP` and `sitemapIndex()`

The sitemaps.org protocol caps a single sitemap file at 50,000 URLs (or
50MB uncompressed) — a real constraint a growing product catalog will
eventually hit, not a hypothetical one, so `urlset()` truncates to
`SitemapBuilder::MAX_URLS_PER_SITEMAP` (50,000) rather than emitting an
oversized, spec-invalid file. `sitemapIndex()` is the protocol's paired
answer for that case — a `<sitemapindex>` document referencing multiple
`urlset()` files by URL, with the same blank/duplicate `loc` handling:

```php
$xml = $builder->sitemapIndex([
    ['loc' => 'https://example.com/sitemap-products.xml', 'lastmod' => '2026-09-01'],
    ['loc' => 'https://example.com/sitemap-categories.xml'],
]);
```

## Not done yet (a future run's or a human's next step)

A live `mintcart/seo/sitemap.xml` route/controller that:

1. Enumerates enabled products/categories/manufacturers/information pages
   via the existing OpenCart models (`model_catalog_product`, etc.) — the
   same models `ProductViewProvider`/`ProductListingService` already read
   from, so no new query patterns need inventing.
2. Resolves each to an absolute URL via `$this->url->link(...)` (respecting
   the `Design → System Pages` aliases where applicable) and a sensible
   `lastmod` (each entity's `date_modified`/`date_added` column already
   exists in OpenCart's schema).
3. Splits into `urlset()`/`sitemapIndex()` pages once the catalog exceeds
   `MAX_URLS_PER_SITEMAP`.
4. Sends the result with an `application/xml` content type and registers
   the route in `robots.txt` (not yet a MintCart concern — OpenCart ships
   no `robots.txt` management at all; out of scope for this increment).

This needs a real product/category/manufacturer/information dataset and a
live OpenCart bootstrap to verify correctly (pagination boundaries,
multi-store/multi-language URL resolution, `status`/`date_available`
filtering) — exactly the kind of controller-wiring work this environment
cannot safely verify without MySQL, per the same caveat every other
DB-touching increment in this backlog has documented for itself. A human
with a real install should pick this up as its own follow-up, using this
file's numbered list as the starting checklist.

## Verified

- `php -l` clean on `sitemapbuilder.php` and its test.
- `tests/mintcart/seo/sitemap-builder-test.php` — 15 scenarios (empty
  input, loc-only entry, string/`\DateTimeInterface`/blank `lastmod`,
  valid/invalid `changefreq`, `priority` clamping both directions and
  mid-range formatting, blank `loc` skipped, duplicate `loc`
  de-duplicated keeping the first occurrence, XML-special-character
  escaping, `MAX_URLS_PER_SITEMAP` truncation, and `sitemapIndex()`'s own
  empty/populated/blank/duplicate cases).
- Full `tests/mintcart/**/*-test.php` suite re-run end to end (35 files,
  was 34) — all pass, no regressions.
