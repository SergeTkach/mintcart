# SEO -> Redirects

One page, one address. Everything is a 301 (moved permanently) from a duplicate address to the page's SEO URL.

## What redirects (`startup/mc_seo_redirect`)

Runs after `startup/seo_url` and `startup/mc_language_url` (listed in `system/config/catalog.php`, see `docs/upstream-diff.md`), so
the request is decoded and `$this->url->link()` knows the keyword and the language folder. Only **GET page navigations**: not POST,
AJAX / fetch (`X-Requested-With`, `Sec-Fetch-Mode` other than `navigate`), nor routes under `api/`, `extension/` (payment callbacks),
`common/`, `startup/`, `event/`, `error/`, `tool/`.

| Requested | Redirected to | Setting |
|---|---|---|
| `index.php?route=product/product&product_id=5` | `/white-mug` | `dirty` |
| `/kitchen/white-mug`, `...&path=12` (a product has one address) | `/white-mug` | `dirty` |
| `index.php?route=checkout/cart` | `/cart` (the system page alias) | `dirty` |
| `/white-mug/` | `/white-mug` | `slash` |
| `/mugs?page=1` | `/mugs` | `page1` |
| a keyword no page has any more | the page, from `mc_redirect` (below) | always |

The decision is `MintCart\Seo\RedirectPlanner::plan()` (pure, tested): the canonical URL comes from the link generator; a URL that
still has `route=` in it (no keyword) is never redirected. Other query parameters (`utm_*`, `sort`, ...) are kept in the target.
Settings: `mintcart_seo_redirects` (JSON `{dirty, slash, page1}`, all on until switched off in the admin).

## Old keywords (`mc_redirect`)

When a product, category, information page or brand is saved with a changed SEO URL keyword, the event `admin/model/catalog/
<product|category|information|manufacturer>/edit<...>/before` -> `mintcart/seo/redirects/track` writes a row: `old_path` (the old
keyword, lower case) -> `route` + `args` of the page, resolved to the page's current address when the redirect fires (so a later change
of the keyword does not break the chain). A keyword a page gets again is removed from the table. A request for an unknown keyword
(`error/not_found` after decoding) is looked up here: `store_id`, `language_id` (0 = any language), `old_path`; a hit counts `hits`.

Manual redirects: old path -> `target_url` (a path or a full address), 301 or 302.

Not done: redirects on deleting a page (the address then answers 404), a 404 log, redirect rules with patterns.
