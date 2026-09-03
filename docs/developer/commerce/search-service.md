# `MintCart\Commerce\SearchService`

Normalizes product rows into the single AJAX Search response contract
Functional Scope §14 and `MINTCART_TECHNICAL_PLAN.md` §26 ask for — the
first real increment of Phase 15's `SearchService`
(`docs/IMPLEMENTATION_STATUS.md` Phase 15). This was deliberately deferred
until now — `MintCart\Design\HeaderViewProvider`'s own docblock already
named this exact gap ("Search... has its own real Mint API planned for
later").

PHP: `upload/system/library/mintcart/commerce/searchservice.php`
(class `MintCart\Commerce\SearchService`)

Test: `tests/mintcart/commerce/search-service-test.php`
(`php tests/mintcart/commerce/search-service-test.php`)

Consumer: `upload/catalog/controller/mintcart/search.php`
(`ControllerMintcartSearch`, route `mintcart/search`)

## Why a new controller instead of extending `product/search.php`

`product/search.php` renders the full search-results *page*: breadcrumbs,
a three-level category tree, sort/limit options, pagination, canonical/
prev/next links, and (when logged in and enabled) search-history logging.
None of that belongs in a quick-suggestions response for a search-as-
you-type box — it would be wasted work on every keystroke, and reshaping
that controller's existing `$data['products']`/`$data['product_cards']`
output into a small JSON contract would be a much larger, riskier change
than adding a new endpoint.

Picked a new, fully-additive `mintcart/search` controller instead:
**zero upstream diff** — `product/search.php` is completely untouched, and
a shopper who submits the full search form still lands there exactly as
before. Both endpoints call the *same*
`ModelCatalogProduct::getProducts()`/`getTotalProducts()` methods (with
the same `status`/`date_available`/store filtering already baked into the
model), so results stay consistent between quick suggestions and the full
results page — no second query implementation to keep in sync.

## Design

`SearchService` has **no OpenCart dependency** — no `\Registry`, `\DB`,
currency/tax/URL services. Running the product query, resizing thumbnails
and formatting price/special stay `ControllerMintcartSearch`'s job
upstream of this class, the same split `CartService`/`ProductViewProvider`
already established.

### `results(array $rows, int $total): array`

```text
{
  items: [
    { id: int, name: string, url: string, image: ?string, price: string|false, special: string|false },
    ...
  ],
  total: int
}
```

- `$rows` — already-resolved rows (thumb already resized, price/special
  already formatted or `false`). `price`/`special` default to `false`
  when the key is omitted — the same "`false`, not `null`, means don't
  show a price" convention `ProductViewProvider::build()` already uses for
  a listing card, so a theme handles both endpoints' output the same way.
- `$total` — the real, store-wide filtered match count from
  `getTotalProducts($filterData)`, not `count($rows)`. A quick-suggestions
  list always caps `$rows` well below the real match count (see below), so
  the two are expected to differ whenever more than a handful of products
  match.
- Deliberately a **smaller** shape than `ProductViewProvider`'s full
  `product`/`display`/`context` contract (no badges, stock state, rating,
  actions) — Functional Scope §14 lists the minimum fields as
  image/name/URL/price/special, and a search-suggestions dropdown has no
  use for the rest.

## `ControllerMintcartSearch::index()`

```
GET index.php?route=mintcart/search&search=…&limit=…
```

- No admin permission check — this is a public storefront route, same
  trust level as `product/search.php` itself and the existing
  `common/search.php` search-box widget.
- `search` — matches `product/search.php`'s own GET parameter name for
  consistency. A blank/missing `search` returns `{items: [], total: 0}`
  immediately, without querying the database.
- `limit` — clamped to `1`–`20` (defaulting to `10`), well below
  `product/search.php`'s own much larger page-size ceiling — a
  quick-suggestions endpoint has no legitimate use for a large page, and a
  tight cap keeps the response fast on every keystroke.
- Thumbnail size reuses `theme_*_image_cart_width`/`_height` (the same
  config keys `ControllerMintcartCart`'s cart snapshot already uses) — a
  quick-suggestions row is closer in size to a cart line than a full
  product-card grid image, so the cart's existing thumbnail size was
  reused rather than introducing a new theme config key for a single
  consumer (`CLAUDE.md` §85).
- Price/special visibility and formatting mirror
  `product/search.php`'s own listing loop exactly (customer-group price
  hiding when `config_customer_price` is set and the visitor isn't logged
  in; a special price only counted when non-null and `>= 0`) —
  deliberately not reimplemented with different rules.

## What this increment deliberately does not include

- **No storefront JS/UI** — no search-as-you-type dropdown, no wiring
  into `common/search.php`'s header search box. This run scoped to the
  endpoint alone, following the same "endpoint first, UI as a follow-up"
  path `docs/IMPLEMENTATION_STATUS.md`'s own Phase 15 recommendation
  called out for Search specifically (unlike Cart, which had a dormant
  `data-mc-action="add-to-cart"` hook already waiting on a product card —
  Search has no equivalent hook yet, so both the endpoint and a Mint-native
  UI are needed, and this run is only the first half).
- **No search-history logging** — `product/search.php`'s own
  `model_account_search->addSearch()` call (used when
  `config_customer_search` is enabled) is a full-results-page concern, not
  something a per-keystroke quick-suggestions request should trigger.
- **Not verified against a live install** — no MySQL/`vendor/twig/twig` in
  this environment, the same pre-existing gap as every earlier phase. See
  `docs/MANUAL_VERIFICATION.md`'s new Phase 15 entry.

## Definition of Done checklist

- [x] Documented contract (this file)
- [x] No OpenCart dependency — unit-testable in isolation
- [x] No OCMOD, no modification to any existing OpenCart file
- [x] Smoke test covering `results()` (5 scenarios)
- [ ] Live controller wiring — not verified against a real install yet
      (no MySQL in this environment)
- [ ] Search-as-you-type UI / header search box wiring — not attempted,
      natural next increment
