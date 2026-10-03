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

**Consumer: `ControllerCommonHeader::index()`** (`upload/catalog/controller/common/header.php`),
live-wired 2026-09-15 — see "Live wiring" below. Before that, this was a
shipped, tested, documented contract with no consumer yet, the same "ship a
tested contract before its consumer" precedent already set in this codebase
(`CLAUDE.md` §85), e.g. `HreflangBuilder` before a per-language URL
resolver existed, or `SchemaOrgBuilder::product()` before `mint_default`
had a product detail page template.

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

## Live wiring

`ControllerCommonHeader::index()` (`upload/catalog/controller/common/header.php`)
now builds `$data['mc_robots_content']` via `metaContent()`, resolving the
current route from `$this->request->get['route']` (the same route string
`startup/router.php`/`content_top.php`/every route-aware controller in this
repo already reads), and `mint_default/template/common/header.twig` renders
it as `<meta name="robots" content="{{ mc_robots_content }}">` right after
the existing `keywords` meta tag. Full wiring rationale:
`docs/upstream-diff.md`'s `header.php` entry
("Update (2026-09-15, routine run — Phase 18, SEO Core)").

Unlike `mc_header`/`mc_schema_organization`, this is **not** gated by
`isMintAware()` — a robots directive is a per-route SEO signal, not a Mint
component DOM contract, so it is computed on every request regardless of
active theme (a theme/template that never reads
`$data['mc_robots_content']`, including the legacy `default` theme, simply
never sees the extra key).

### `product/search` `$hasResults` — resolved via a registry bridge

Previously, `$hasResults` (the second parameter) was left at its default,
`true`, in this wiring: `header.php` renders as a sibling sub-controller of
`ControllerProductSearch`, not that controller itself, so it had no way to
know whether the current search actually matched anything.

This is now resolved with a plain `\Registry` value —
`RobotsBuilder::HAS_RESULTS_REGISTRY_KEY` (`mc_seo_search_has_results`) —
the same request-scoped `$this->registry->set()`/`->get()` bridge
OpenCart's own `startup/startup.php` already uses to hand `session`/
`customer`/`cart` from one stage of the request to the next (no new table,
session key, or event). `ControllerProductSearch::index()` computes
`$mint_search_has_results` (`true` for a bare `product/search` request with
no `search`/`tag` param — the plain search form, not a "no results" page —
otherwise `$product_total > 0`) and calls
`$this->registry->set(RobotsBuilder::HAS_RESULTS_REGISTRY_KEY, $mint_search_has_results)`
immediately before `$this->load->controller('common/header')`.
`ControllerCommonHeader::index()` reads the same key back with
`$this->registry->get(...)`, falling back to `RobotsBuilder`'s own default
(`true`) when the key is unset — i.e. every route other than
`product/search`, which never writes it. A `product/search` page with zero
matches now renders `noindex, follow`; every other route is unaffected.

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
- [x] Live controller/template wiring (`ControllerCommonHeader` +
      `mint_default/template/common/header.twig`) — see "Live wiring" above
- [x] `product/search` `$hasResults` threaded from `ControllerProductSearch`
      — see "`product/search` `$hasResults` — resolved via a registry
      bridge" above
- [ ] Live verification against a real install — this environment has no
      MySQL server (same pre-existing gap noted throughout
      `docs/IMPLEMENTATION_STATUS.md`)

## Verified

- `php -l` clean on every touched/added PHP file, re-swept across the full
  `upload/`/`tests/` tree, including `header.php`/`search.php` after this
  run's registry-bridge wiring.
- `tests/mintcart/seo/robots-builder-test.php` (76 assertions, up from 74 —
  this run added a defensive shape check for the new
  `HAS_RESULTS_REGISTRY_KEY` constant; `directives()`/`metaContent()`'s own
  logic is unchanged).
- Full `tests/mintcart/**/*-test.php` suite re-run end to end (84 files) —
  all passing, no regressions.

**Not verified:** no live MySQL/`vendor/twig/twig` session in this
environment — a human should confirm that `/checkout/cart`/`/account/*`
pages render `<meta name="robots" content="noindex, follow">`, ordinary
catalog pages render `content="index, follow"`, and — the new part this
run adds — a `product/search` query with zero matches renders
`content="noindex, follow"` while one with matches still renders
`content="index, follow"` (see `docs/MANUAL_VERIFICATION.md`'s Phase 18
section).
