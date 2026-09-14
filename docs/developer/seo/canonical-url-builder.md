# SEO Core — Canonical URL Builder

`MintCart\Seo\CanonicalUrlBuilder`
(`upload/system/library/mintcart/seo/canonicalurlbuilder.php`)

## What this is

The "canonical" line item from `MINTCART_FUNCTIONAL_SCOPE.md` §16 "SEO
Core" (alongside hreflang, robots, sitemap and the three Schema.org
types other in-flight increments cover — see "Relationship to other SEO
Core pieces" below).

`catalog/controller/product/category.php`,
`catalog/controller/product/manufacturer.php`,
`catalog/controller/product/search.php` and
`catalog/controller/product/special.php` each carry an **identical**
inline block, right down to the same linked comment
(`http://googlewebmastercentral.blogspot.com/2011/09/pagination-with-relnext-and-relprev.html`),
that decides:

- page 1 gets a bare canonical link; page 2+ gets `&page=N` appended;
- a `prev` link is only emitted from page 2 onward, and is itself bare
  when it points back to page 1 (no `&page=1` ever appears in a URL);
- a `next` link is only emitted when `ceil($total / $limit) > $page`.

`CanonicalUrlBuilder` is a pure, OpenCart-independent extraction of that
shared decision — the same "primitive first, wire the controllers in a
later increment" pattern `MintCart\Update\PackageManifest` and
`MintCart\Catalog\CategoryMoveValidator` already established
(`CLAUDE.md` §85). It is deliberately narrow: it never calls
`Url::link()`, never knows a route name, and never builds the
non-pagination part of a query string (`path=5`, `manufacturer_id=20`,
a search filter set) — that stays each controller's own job, since it's
genuinely different per listing type. The only thing that was actually
byte-for-byte duplicated across all four controllers is the page-number
arithmetic this class now owns.

## Contract

```php
$builder = new \MintCart\Seo\CanonicalUrlBuilder();

$links = $builder->build($page, $limit, $total, $baseQuery);
// ['canonical' => string, 'prev' => ?string, 'next' => ?string]
```

- `$page`, `$limit`, `$total` — the same values every listing controller
  already computes today.
- `$baseQuery` — the query string suffix for the *current* listing with
  no `page` parameter at all (e.g. `'path=5'`, `'manufacturer_id=20'`, or
  `''` for `product/special`, which has no identifying parameter).
- Return shape: `canonical` is always a string (never `null`); `prev` and
  `next` are `null` when that link should not be emitted at all — a
  caller checks for `null` rather than checking page bounds itself a
  second time.

A caller wires this in as, for example:

```php
$links = $builder->build($page, $limit, $product_total, 'path=' . $category_info['category_id']);

$this->document->addLink($this->url->link('product/category', $links['canonical']), 'canonical');

if ($links['prev'] !== null) {
    $this->document->addLink($this->url->link('product/category', $links['prev']), 'prev');
}

if ($links['next'] !== null) {
    $this->document->addLink($this->url->link('product/category', $links['next']), 'next');
}
```

## Why joining with `&page=N` is always safe

OpenCart's `Url::link()` (`upload/system/library/url.php`) builds its
query string as `'&' . ltrim($args, '&')` — it always supplies its own
leading `&` and strips any the caller's `$args` already had. That's why
`special.php`'s existing code can get away with concatenating
`'' . '&page=' . $page` (producing a string that itself starts with
`&`) for its prev link, while `category.php` concatenates
`'path=5' . '&page=2'` (no leading `&` needed since `path=5` isn't
empty) — both reach `Url::link()` and come out correct either way. This
class relies on that same guarantee: it always joins with a plain
`'&page=' . $page`, regardless of whether `$baseQuery` is empty.

## What's deliberately not done yet

- **No controller wiring.** None of `category.php`/`manufacturer.php`/
  `search.php`/`special.php` call this class yet — each still has its
  own inline copy of the logic, unchanged and un-behavior-affected by
  this increment. Swapping the four inline blocks for calls into this
  class is a genuinely separate, mechanical follow-up (replace, don't
  redesign) once this primitive itself has been reviewed; deferred
  rather than bundled into this increment to keep the diff reviewable
  and to avoid touching four live storefront controllers in the same
  change that introduces the new class.
- **No `common/home.php` / `product/product.php` coverage.** Neither
  emits a `prev`/`next` pair today (`product/product.php` only sets a
  bare canonical with no pagination concept; `common/home.php` has no
  canonical/pagination at all) — nothing to extract there.
- **No meta title/description, no `MetaBuilder`.** Out of scope for this
  increment; a separate SEO Core line item.
- **No query-parameter allowlisting/stripping** (e.g. dropping tracking
  parameters like `utm_*` from `$baseQuery` before computing canonical).
  Every existing controller already builds `$baseQuery` from only the
  parameters it cares about — by the time it reaches this class,
  `$baseQuery` is already "clean." Adding a stripping responsibility
  here would duplicate work the controllers already do correctly.

## Relationship to other SEO Core pieces

Functional Scope §16 lists SEO URLs, system route URLs, canonical,
hreflang, meta title, meta description, robots controls, sitemap and
three Schema.org types under one "SEO Core" umbrella. Each is being
built as its own small, independently reviewable `MintCart\Seo\*` (or
sibling-namespace) class rather than one large "SEO service" — consistent
with `CLAUDE.md` §86 ("one clear service" over a large multi-responsibility
class). This class covers canonical/prev/next pagination links only.

## Verified

`php -l` clean. `tests/mintcart/seo/canonical-url-builder-test.php` (8
scenarios) — scenarios 1-5 replay the exact page/limit/total/base-query
combinations `category.php`'s 40-products-at-20-per-page example and
`special.php`'s empty-base-query shape already produce today, asserting
this class's output matches the existing controllers' behavior exactly
(a refactor-ready extraction, not a behavior change); scenarios 6-8 cover
`limit = 0` ("show all"), zero total results, and defensive clamping of
out-of-range input. Pure PHP, no OpenCart dependency, so no live-install
verification gap applies here (same as `PackageManifest`) — there is
nothing to run against a real install yet since no controller calls this
class.
