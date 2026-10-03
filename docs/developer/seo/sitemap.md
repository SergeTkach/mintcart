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

## Live wiring: `ControllerExtensionFeedGoogleSitemap`

`SitemapBuilder` itself is a pure XML assembler, the same
OpenCart-independent shape as `SearchService`/`ProductViewProvider`/
`HreflangBuilder`/`RobotsBuilder`: it takes an already-resolved list of
`{loc, lastmod, changefreq, priority, images}` entries (URLs generated
upstream via `$this->url->link(...)`, the same "presentation values
already resolved upstream" contract every sibling class in this backlog
uses) and returns a spec-valid XML string. Fully unit-testable, fully
verified, zero DB/`\Registry`/`\Document` dependency.

A brand-new `mintcart/seo/sitemap.xml` route/controller was deliberately
**not** built (see "Not done yet" below) — this environment has no MySQL,
so a new controller resolving `model_catalog_product`/
`model_catalog_category`/`model_catalog_manufacturer`/
`model_catalog_information` into a paginated response from scratch
couldn't be exercised end to end here, the same reasoning
`HreflangBuilder`/`RobotsBuilder` originally documented for themselves.

Instead, this class's first real consumer is the stock OpenCart
**"Google Sitemap" extension**
(`upload/catalog/controller/extension/feed/google_sitemap.php`,
`Marketing → Marketing → Feeds`) — an existing, already-working XML
sitemap feed gated by its own `feed_google_sitemap_status` setting, that
already resolved every product/category/manufacturer/information URL via
the exact models/`$this->url->link()` calls this class's own contract
expects. That controller's `index()` used to build the
`<urlset>`/`<url>`/`<image:image>` XML by hand, string-by-string; it now
builds a plain array of entries and calls `SitemapBuilder::urlset()` to
assemble the XML, and `SitemapBuilder` itself gained an optional
per-entry `images` list (rendering `<image:image>` blocks and the
`xmlns:image` namespace only when at least one entry has images) so the
extension's Google Image sitemap support keeps working through the shared
builder. See `docs/upstream-diff.md`'s
`google_sitemap.php` entry for the full compatibility analysis — behavior
is unchanged except that `<loc>`/`<image:loc>` are now correctly escaped
(a fix; the hand-built version never escaped those two tags, only
`<image:caption>`/`<image:title>`).

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
- `images` — optional, a list of `{loc, caption?, title?}` (the Google
  Image sitemap extension). `loc` is required per image (blank entries
  skipped, duplicates de-duplicated, first occurrence wins); `caption`/
  `title` are optional and omitted entirely when blank rather than
  rendered as empty tags. The `xmlns:image` namespace attribute is only
  added to `<urlset>` when at least one entry actually has images, so
  callers with no image data get byte-identical output to before this
  parameter existed.

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

## Live wiring: `mintcart/seo/sitemap`

`ControllerMintcartSeoSitemap`
(`upload/catalog/controller/mintcart/seo/sitemap.php`, route
`mintcart/seo/sitemap`) is now the "genuinely first-class MintCart-owned
route" this section previously deferred: always available, no
`feed_google_sitemap_status` gate and no `Extensions > Feeds > Google
Sitemap` install required. It reuses the exact same
`model_catalog_product`/`model_catalog_category`/
`model_catalog_manufacturer`/`model_catalog_information` enumeration
`ControllerExtensionFeedGoogleSitemap` already does (same entries, same
recursion shape for nested categories) and the same `SitemapBuilder::urlset()`
XML assembly — a merchant with the stock extension also enabled sees the
same output from both routes, they just never disagree on what a "page"
is. Deliberately a new, fully-additive controller under `mintcart/seo/`
(matching `admin/controller/mintcart/seo/system_pages.php`'s namespacing
on the admin side) rather than a modification of the extension it
parallels — the same "new controller, not a patch" rationale
`ControllerMintcartSearch`/`ControllerMintcartCart` already established.

## Not done yet (a future run's or a human's next step)

The Google Sitemap extension (`extension/feed/google_sitemap.php`) is
unchanged and still gated behind `feed_google_sitemap_status`/its own
admin extension install — left alone deliberately, since a merchant may
already have it enabled and pointed at a search-console submission.
Neither route (the extension, or the new `mintcart/seo/sitemap` above)
applies the `Design → System Pages` URL aliases
(`MintCart\Seo\SystemRoutes`) to any entry — not a real gap in practice,
since `$this->url->link()` already applies any `seo_url` keyword
automatically when `config_seo_url` is enabled, the same mechanism every
other entry's `loc` already benefits from — nor splits into
`sitemapIndex()` pages once a catalog exceeds `MAX_URLS_PER_SITEMAP`
(`urlset()` truncates safely at that limit instead, same as the stock
extension already does — no regression, just no multi-file split yet).
Registering a `Sitemap:` line in `robots.txt` (OpenCart ships no
`robots.txt` management at all today) remains a separate, larger
follow-up, not attempted here. A human with a real install should pick
these up as their own follow-ups.

## Verified

- `php -l` clean on `sitemapbuilder.php`, its test, and
  `google_sitemap.php`.
- `tests/mintcart/seo/sitemap-builder-test.php` — 20 scenarios (empty
  input, loc-only entry, string/`\DateTimeInterface`/blank `lastmod`,
  valid/invalid `changefreq`, `priority` clamping both directions and
  mid-range formatting, blank `loc` skipped, duplicate `loc`
  de-duplicated keeping the first occurrence, XML-special-character
  escaping, `MAX_URLS_PER_SITEMAP` truncation, `sitemapIndex()`'s own
  empty/populated/blank/duplicate cases, and the new `images` parameter's
  no-images/one-image/optional-caption-title/blank-duplicate/escaping
  cases).
- Full `tests/mintcart/**/*-test.php` suite re-run end to end (77 files)
  — all pass, no regressions.
- **Not verified:** no live MySQL/OpenCart bootstrap in this environment
  — a human should confirm on a real install that the Google Sitemap
  extension's feed output is unchanged (aside from the `<loc>`/
  `<image:loc>` escaping fix) — see `docs/MANUAL_VERIFICATION.md`'s new
  Phase 18 "Google Sitemap" section.

**Update (`mintcart/seo/sitemap` live wiring):** `php -l` clean on
`upload/catalog/controller/mintcart/seo/sitemap.php`. No new test file —
this controller only enumerates rows via existing OpenCart models and
hands them to the already-tested `SitemapBuilder::urlset()`, the same
"live-DB controller wiring a pure primitive" shape every other Phase 18
controller wiring in this backlog used, with no new pure logic to unit
test. Full `tests/mintcart/**/*-test.php` suite re-run end to end (84
files) — all passing, no regressions. **Not verified:** no live
MySQL/OpenCart bootstrap in this environment — a human should confirm on
a real install that `index.php?route=mintcart/seo/sitemap` returns a
well-formed `<urlset>` XML document (see `docs/MANUAL_VERIFICATION.md`'s
Phase 18 section, "MintCart sitemap route" entry).
