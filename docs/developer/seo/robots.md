# `MintCart\Seo\RobotsBuilder`

Decides the per-page `<meta name="robots" content="...">` directive
(`MINTCART_FUNCTIONAL_SCOPE.md` §16 "robots controls" under **SEO Core**,
`MINTCART_TECHNICAL_PLAN.md` §31 "Metadata"). No robots signal of any kind
exists anywhere in this repository before this class, other than
`information/information.php`'s own narrow `X-Robots-Tag: noindex` HTTP
header for a handful of "bottom position" information pages — see
"Relationship to `information.php`'s `X-Robots-Tag`" below.

PHP: `upload/system/library/mintcart/seo/robotsbuilder.php`
(class `MintCart\Seo\RobotsBuilder`)

Test: `tests/mintcart/seo/robots-builder-test.php`
(`php tests/mintcart/seo/robots-builder-test.php`, 74 assertions)

**Consumer: none yet.** This is a shipped, tested, documented contract with
no live controller/template wiring — see "Why no live wiring yet" below.
Same "ship a tested contract before its consumer" precedent already set in
this codebase (`CLAUDE.md` §85), e.g. `HreflangBuilder` before a
per-language URL resolver existed, or `SchemaOrgBuilder::product()` before
`mint_default` had a product detail page template.

## Design

No OpenCart dependency — no `\Registry`, `\DB`, `\Document` — same
"resolved values in, normalized contract out" shape as
`MintCart\Seo\HreflangBuilder`/`SchemaOrgBuilder` and
`MintCart\Commerce\SearchService`. A caller resolves the current route
(and, for search, whether it actually matched anything) upstream; this
class only decides the index/follow directive and formats it.

### `directives(string $route, bool $hasResults = true): array{index: bool, follow: bool}`

- Every route in `RobotsBuilder::NOINDEX_ROUTES` (checkout flow, every
  account-area page) returns `['index' => false, 'follow' => true]`.
- `product/search` with `$hasResults = false` also returns `noindex,
  follow` — an empty search-results page is thin, duplicate-shaped content
  (the same "no results" markup for every failed query), but its own links
  (e.g. "browse categories instead") are still worth a crawler following,
  so this is never `nofollow`.
- Every other route (catalog/category/manufacturer/special/information/home,
  and `product/search` with results) is `index, follow`.

### `metaContent(string $route, bool $hasResults = true): string`

Formats `directives()`'s result as the exact string a
`<meta name="robots" content="...">` tag's `content` attribute should
carry, e.g. `noindex, follow`.

## `NOINDEX_ROUTES`

Every value was checked against the actual controller in this repo
(`CLAUDE.md` §84 — never assumed from memory): the full `checkout/*` flow
(`cart`, `checkout`, `success`, `failure`, `guest`, `register`, `confirm`)
and every primary `account/*` page (`account`, `login`, `register`,
`forgotten`, `logout`, `success`, `edit`, `password`, `address` +
`add`/`edit`, `wishlist`, `order` + `info`, `return` + `add`/`info`,
`download`, `reward`, `transaction`, `voucher`, `newsletter`, `recurring` +
`info`, `affiliate`, `tracking`). Every visitor's cart/checkout/account
pages are functionally identical (or behind a login wall), so indexing
them only creates duplicate-content/thin-content noise — and for checkout
in particular, could leak an in-progress order's URL into search results.

Not necessarily exhaustive of every sub-action each controller exposes
(e.g. `account/address.add`/`.edit` share their parent's `account/address`
entry) — this is the set of *primary*, directly-linked routes a crawler
would actually reach, not every possible query-string permutation.

## Relationship to `information.php`'s `X-Robots-Tag`

`upload/catalog/controller/information/information.php` already sends its
own `X-Robots-Tag: noindex` HTTP header for information pages configured
with a "bottom" layout position — a narrow, pre-existing, unrelated
mechanism this class deliberately does not touch, duplicate, or route
through. `RobotsBuilder` is scoped to the fixed checkout/account route
whitelist above; a per-information-page robots decision is a separate
concern this class does not attempt to absorb (`CLAUDE.md` §85 — no API
ahead of a real, identified consumer for that case).

## Why no live wiring yet

The natural live-wiring point is `ControllerCommonHeader::index()`
(`upload/catalog/controller/common/header.php`) — a robots meta tag, like
Organization JSON-LD and (once built) hreflang links, belongs in `<head>`
on every page. That exact file is currently being edited by an in-flight
pull request adding Schema.org JSON-LD (`docs/IMPLEMENTATION_STATUS.md`'s
Phase 18 "Schema.org" entry) — deliberately not touched by this increment
to avoid two open changes editing the same lines of the same shared
page-chrome file at once, the same reasoning `HreflangBuilder` already
documented for itself. `header.php` also has no reliable way today to know
the *current request's* route (it renders as a sub-template included by
every page controller, not the page controller itself) or whether a search
actually matched anything — both need to be resolved and passed in by
whichever controller assembles `$data` for a page, which is a separate,
slightly larger wiring task once the header-chrome contention above
clears.

## What this is not

- **Not a `robots.txt` file manager.** OpenCart ships a static
  `upload/robots.txt` file already; this class is about the per-page
  `<meta name="robots">` tag, not the site-wide crawl-directive file.
- **Not wired into any controller or template yet.** No page emits a
  robots meta tag today; this ships the tested contract those call sites
  will build on.
- **Not a `nofollow` mechanism for individual links.** This class only
  ever decides a whole page's directive, not link-level `rel="nofollow"`.

## Definition of Done checklist

- [x] Documented contract (this file)
- [x] No OpenCart dependency — unit-testable in isolation
- [x] No OCMOD, no modification to any existing OpenCart file (this
      increment adds only new files)
- [x] Smoke test covering the ordinary-route default, every
      `NOINDEX_ROUTES` route, `product/search` with and without results,
      and `$hasResults` being ignored outside `product/search`
- [ ] Live controller/template wiring (`ControllerCommonHeader` +
      `mint_default/template/common/header.twig`) — deliberately deferred,
      see "Why no live wiring yet"
- [ ] Live verification against a real install — this environment has no
      MySQL server (same pre-existing gap noted throughout
      `docs/IMPLEMENTATION_STATUS.md`)

## Verified

- `php -l` clean on every touched/added PHP file, re-swept across the full
  `upload/`/`tests/` tree.
- `tests/mintcart/seo/robots-builder-test.php` (74 assertions: ordinary
  route default, every `checkout/*`/`account/*` `NOINDEX_ROUTES` entry,
  `metaContent()` formatting, `product/search` with/without results,
  `$hasResults` ignored elsewhere, `NOINDEX_ROUTES` uniqueness and shape).
- Full `tests/mintcart/**/*-test.php` suite re-run end to end (34 files,
  was 33) — all passing, no regressions.

**Not verified:** no live MySQL/`vendor/twig/twig` session in this
environment — a human should confirm, once this is wired into
`ControllerCommonHeader`, that `/checkout/cart`/`/account/*` pages render
`<meta name="robots" content="noindex, follow">` and ordinary catalog pages
render `content="index, follow"`.
