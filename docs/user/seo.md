# SEO basics

MintCart ships the "SEO Core" basics from
[`MINTCART_FUNCTIONAL_SCOPE.md`](../MINTCART_FUNCTIONAL_SCOPE.md) §16 /
[`MINTCART_TECHNICAL_PLAN.md`](../MINTCART_TECHNICAL_PLAN.md) §31 as a set of
small, independent pieces rather than one big "SEO module" — most of them
already run automatically, with nothing to configure.

**This guide describes what actually runs on a store today, not the full
roadmap.** One piece (hreflang) is built and tested but not wired into any
page yet; everything else below is live.

| Piece | Status | Needs any setup? |
|---|---|---|
| SEO URL (pretty URLs for products/categories/pages) | **Live** — stock OpenCart | No — already on |
| System Pages aliases (cart, checkout, search, …) | **Live** | Optional, one admin screen |
| Meta title / description fallback | **Live** | No — automatic |
| Canonical / prev / next links | **Live** | No — automatic |
| Robots meta tag (`index`/`noindex`) | **Live** | No — automatic |
| XML sitemap | **Live** | Yes — enable one extension |
| Schema.org structured data (Product, Breadcrumb, Organization) | **Live**, Mint themes only | No — automatic |
| hreflang (multi-language alternate links) | Built, not wired to any page yet | — |

## SEO URL

`Design → SEO URL` is stock OpenCart's own screen for giving any product,
category, manufacturer or information page a pretty URL instead of
`index.php?route=...&product_id=42`. MintCart doesn't replace or duplicate
this — every piece below reuses the exact same `seo_url` table.

## System Pages aliases

`Design → System Pages` is a friendlier facade over that same `seo_url`
table, scoped to a fixed list of twelve internal routes that don't belong
to any single product/category/page: `search`, `cart`, `checkout`,
`contact`, `account`, `login`, `register`, `wishlist`, `compare`,
`special`, `manufacturer` (the listing page), `sitemap`.

Instead of knowing that the cart is really `route=checkout/cart` and typing
that into the generic screen, you get one row per system page with a
single "alias" field — e.g. typing `cart` there makes `/cart` work instead
of only the default `index.php?route=checkout/cart` link. Leaving a row
blank keeps that page on its default link; no alias is required anywhere.

**Known limitation:** scoped to your store's main store and default
language only. If you run multiple stores or languages, set additional
aliases for the same routes through the generic `Design → SEO URL` screen
instead — the underlying storage is the same either way.

## Meta title / description

Every category, product and the homepage already have "Meta Tag Title" /
"Meta Tag Description" fields in their own admin edit screens. The problem
MintCart fixes: if you leave one blank (easy to do when adding products
quickly), stock OpenCart ships that page with a genuinely **empty**
`<title>` tag. MintCart now falls back to the entity's own name (product
name, category name, store name on the homepage) whenever the meta title
is blank, so no page ever ships titleless. This runs automatically on
every category, product and homepage request — there's nothing to turn on
and no new admin field; your existing meta title/description fields still
win whenever you've filled them in.

## Canonical / prev / next links

Category, manufacturer, search and special-offers listing pages already
emit a `<link rel="canonical">` (and, on paginated results, `rel="prev"`/
`rel="next"`) pointing at the clean version of that page's URL. This also
runs automatically — nothing to configure.

## Robots meta tag

Every page now emits a `<meta name="robots" content="...">` tag. Ordinary
catalog/information/home pages get `index, follow`. Your cart, the full
checkout flow, and every account-area page (login, order history, address
book, wishlist, and so on) get `noindex, follow` automatically — those
pages are either identical for every visitor or behind a login wall, so
there's no reason for a search engine to index them, but their own links
(e.g. "browse categories" from an empty cart) are still worth following.
Nothing to configure.

## XML sitemap

MintCart does not add a second sitemap generator. It improves the sitemap
stock OpenCart already ships as the **Google Sitemap** feed extension —
the entries it lists, and how it escapes special characters in each URL,
now go through the same tested code as every other MintCart SEO piece.

To get a sitemap, enable it the normal OpenCart way:

1. `Extensions → Extensions`, choose **Feeds** from the extension-type
   dropdown.
2. Install/enable **Google Sitemap**, set its status to Enabled, save.
3. Your sitemap is then available at the URL that screen shows (normally
   `index.php?route=extension/feed/google_sitemap`).

If you've set a System Pages alias for `sitemap`, that pretty URL points
at the same feed.

## Schema.org structured data

Product pages, category/search/manufacturer/special-offers listing pages
(as a `BreadcrumbList`), and every page (as `Organization`, once per
request) emit `<script type="application/ld+json">` structured data —
useful for rich results in search engines. This is automatic and needs no
settings.

**Only on Mint-aware themes.** This only renders on a theme that declares
itself Mint-aware via its own `theme.json` (`mint_default` and the
`test_theme` theme ship this today). The legacy stock `default` theme, or
any third-party theme without a `theme.json`, sees no change and no
structured data — switching to a Mint-aware theme is what turns this on,
not a separate setting.

## hreflang (not live yet)

`MintCart\Seo\HreflangBuilder` exists and is fully tested, but nothing in
the storefront calls it yet — no page currently emits
`<link rel="alternate" hreflang="...">` tags. If your store only runs one
language, this has no effect on you either way. See
`docs/developer/seo/hreflang.md` for the technical detail once it's wired
up.

## Where to look next

Each piece above has a developer-facing writeup with the exact files,
tests and known limitations in `docs/developer/seo/`:
[`system-route-aliases.md`](../developer/seo/system-route-aliases.md),
[`meta-builder.md`](../developer/seo/meta-builder.md),
[`canonical-url-builder.md`](../developer/seo/canonical-url-builder.md),
[`robots.md`](../developer/seo/robots.md),
[`sitemap.md`](../developer/seo/sitemap.md),
[`schema-org.md`](../developer/seo/schema-org.md),
[`hreflang.md`](../developer/seo/hreflang.md).
