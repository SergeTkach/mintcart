# `MintCart\Commerce\SearchService`

Normalizes AJAX search results for the Theme API's Search contract
(`MINTCART_TECHNICAL_PLAN.md` §26, `MINTCART_IMPLEMENTATION_GUIDE.md` §59,
`MINTCART_FUNCTIONAL_SCOPE.md` §14 — "Єдиний normalized search
endpoint/service") — the same normalization role `CartService` plays for
the cart, applied to a search result set.

PHP: `upload/system/library/mintcart/commerce/searchservice.php`
(class `MintCart\Commerce\SearchService`)

Test: `tests/mintcart/commerce/search-service-test.php`
(`php tests/mintcart/commerce/search-service-test.php`)

Consumer: `upload/catalog/controller/mintcart/search.php`
(`ControllerMintcartSearch`, route `mintcart/search/suggest`)

## Why a new controller instead of extending `product/search.php`

`product/search.php` renders the full search **results page** — it already
has its own `product_cards` pipeline via `ProductCardListRenderer` (see
`docs/developer/theme-api/product-card-list-renderer.md`), pagination,
sort/limit options, and the refine-search form. A theme's search box
typeahead/suggest dropdown needs something different: a small, fast, JSON
response for as-you-type queries, not a full page render.

Two ways to close that gap: overload `product/search.php` with a JSON
branch, or add a new, fully-additive MintCart-owned controller. Picked the
latter, same reasoning `ControllerMintcartCart` already established
(`docs/developer/commerce/cart-service.md`): **zero upstream diff** — no
existing OpenCart file is touched, and `product/search.php` is completely
unaffected by this endpoint's existence.

## Design

`SearchService` has **no OpenCart dependency** — no `\Registry`, `\DB`,
`model_catalog_product`. Running the actual product query
(`model_catalog_product->getProducts()`/`getTotalProducts()`, the exact
same `filter_name` shape `product/search.php` uses), resizing thumbs and
formatting currency all stay `ControllerMintcartSearch`'s job upstream of
this class, exactly the split `CartService`/`ProductViewProvider` already
established.

### `normalizeQuery(string $raw): string`

Trims and collapses internal whitespace, then caps the result at 128
characters. Deliberately does not strip punctuation/HTML — that stays
OpenCart's own product-filter handling, unchanged by this class.

### `isQueryValid(string $query): bool`

A query shorter than 2 characters is treated as invalid — almost always a
stray keystroke, not a real search intent. Lets the controller skip
running a `LIKE '%x%'` product query on every keypress of a typeahead
field; an invalid query gets an empty result set back (not an error).

### `results(array $items, string $query, int $total): array`

```text
{
  query: string,   // the normalized query this result set answers
  count: int,       // total matching products (may exceed items.length)
  items: [
    { id, name, url, image, price, special }
    ...
  ]
}
```

`$items` are already-resolved (image already resized via
`model_tool_image`, price/special already formatted via `$this->currency`)
— same "presentation values already resolved upstream" contract
`ProductViewProvider::build()`/`CartService::snapshot()` use.

Deliberately a **smaller item shape than `ProductViewProvider`'s full
`ProductView` contract** — Technical Plan §26 documents `id, name, url,
image, price, special` as the AJAX Search response's own minimum, distinct
from the full product-card contract a listing page renders. This class
never grows toward the full `ProductView` shape without a real consumer
asking for more (`CLAUDE.md` §85) — a typeahead result doesn't need
badges/stock/actions/rating the way a product card does.

`count` is the total match count from `getTotalProducts()`, not
`count($items)` — the controller caps the returned list (see below), so a
caller can still show "42 results" even though only 8 are returned.

## `ControllerMintcartSearch` actions

| Route | Method | Notes |
|---|---|---|
| `mintcart/search/suggest` | GET `search` | Returns up to `SUGGEST_LIMIT` (8) matches plus the true total match count. An invalid/too-short query (`SearchService::isQueryValid()`) returns `{query, count: 0, items: []}` without running a product query. |

`SUGGEST_LIMIT` is a small, fixed constant, not caller-configurable yet —
no real consumer has asked for a different limit (`CLAUDE.md` §85); a
future Mint-native search-box component is the likely first caller to want
one.

## What this is not

- **Not the search results page.** `product/search.php` (and its
  `product_cards`/pagination/sort/limit pipeline) is untouched and remains
  the full-page search experience.
- **Not a new search backend.** Same `model_catalog_product->getProducts()`
  / `getTotalProducts()` filter calls `product/search.php` already makes
  (`filter_name` only, no tag/description/category filters — a suggest box
  searches by name, the same way a typeahead would) — this class only
  normalizes the response shape.
- **A Mint-native search-box UI now exists** —
  `docs/developer/components/search-box.md` (`mc-search-box`,
  `assets/src/js/components/search-box.js`). It does not reuse
  `McEntityPicker` itself (that component's contract is
  search/paginate/multi-select against an arbitrary entity list; a
  typeahead dropdown needs none of the pagination/selection-state
  machinery, just debounce + AbortController + keyboard nav, so composing
  it here would import more than this consumer needs, per `CLAUDE.md`
  §86) — it borrows the same debounce/AbortController/roving-tabindex
  techniques instead. Not wired into any live template yet — see that
  doc's "Not wired into any live template yet" section.

## Definition of Done checklist

- [x] Documented contract (this file)
- [x] No OpenCart dependency — unit-testable in isolation
- [x] No OCMOD, no modification to any existing OpenCart file
- [x] Smoke test covering `normalizeQuery()`, `isQueryValid()` and
      `results()`
- [ ] Live verification against a real install — this environment has no
      MySQL server (same pre-existing gap noted throughout
      `docs/IMPLEMENTATION_STATUS.md`); a human should confirm
      `mintcart/search/suggest?search=<term>` returns the expected JSON
      shape on a real catalog, and that a too-short query (`?search=a`)
      returns `{query: "a", count: 0, items: []}` without a DB query
- [ ] Mint-native search-box UI component calling this endpoint — not
      built yet, see "What this is not" above
