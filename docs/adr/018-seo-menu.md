# ADR 018 - SEO is its own admin section

## Context

`MINTCART_PRODUCT_VISION.md` §8 puts SEO under Marketing (Marketing -> Promotions, Banners, SEO). In practice
SEO ended up in two places: the stock "SEO URL" and MintCart's "System Pages" under Design, the new
"Meta Tags" under Marketing. SEO Core (Functional Scope §16) is a set of screens of its own (meta tag
templates, page addresses, later sitemap / robots / redirects), not a promotion.

## Decision

A top-level admin menu **SEO** with: Meta Tags (`mintcart/seo/meta`), System Pages
(`mintcart/seo/system_pages`) and All URLs (stock `design/seo_url`). It is part of the core, not an
extension: nothing to install, it works from the first start.

Routes, permissions and storage do not change - only where the menu links to them. The vision's
navigation is the target; this is a deliberate deviation for SEO, to be folded into the full admin
navigation restructure.

## Consequences

- One place for everything SEO; Design keeps only look-and-feel screens.
- Sitemap, robots and redirects get a home when they get screens.
- Bookmarks and permissions of the old entries keep working.
