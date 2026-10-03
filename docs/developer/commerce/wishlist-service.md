# `MintCart\Commerce\WishlistService`

Persists wishlist membership — "wishlist" (`MINTCART_FUNCTIONAL_SCOPE.md`
§11, Commerce Essentials, P0), `MINTCART_TECHNICAL_PLAN.md` §24 (Commerce
Layer), `MINTCART_IMPLEMENTATION_GUIDE.md` §47 (Product Card lists
"wishlist" among its required actions).

PHP: `upload/system/library/mintcart/commerce/wishlistservice.php`
(class `MintCart\Commerce\WishlistService`)

Test: `tests/mintcart/commerce/wishlist-service-test.php`
(`php tests/mintcart/commerce/wishlist-service-test.php`)

Consumer: `upload/catalog/controller/mintcart/wishlist.php`
(`ControllerMintcartWishlist`, routes `mintcart/wishlist/add|remove|refresh`) +
`assets/src/js/commerce/wishlist-client.js`
(`addToWishlist()`/`removeFromWishlist()`/`refreshWishlist()`) +
`assets/src/js/components/product-card.js` (`initWishlistToggle()`)

## Why this exists

`mc-product-card`'s `[data-mc-action="toggle-wishlist"]` button has existed
since Phase 6/7 but, per its own docblock, only flipped its own
`aria-pressed` state and dispatched a bubbling `mc:product-card:wishlist-toggle`
event — "persisting wishlist/compare membership is Commerce API territory...
a future Wishlist/Compare service, not built yet" (CLAUDE.md §85: the
consumer — the event dispatch — already existed, so building the service
now has a real, already-wired caller). This PR builds that service for
**wishlist**; **compare** is left exactly as it was (still UI-only, still
dispatching `mc:product-card:compare-toggle` with no backend) — no
Compare-specific storage/UX requirement has been identified yet, and
building one speculatively would violate CLAUDE.md §85/§17.

## Why this wraps OpenCart's existing wishlist storage instead of a new table

OpenCart 3 already has everything membership needs: `oc_customer_wishlist`
(logged-in customers, via `ModelAccountWishlist`) and
`session->data['wishlist']` (guests) — the exact same split stock
`ControllerAccountWishList::add()` already implements. CLAUDE.md §39/§6
("don't duplicate OpenCart DB... Mint tables only for Mint-specific
functionality") rules out a new `mc_wishlist` table here. `WishlistService`
itself has **no OpenCart dependency** (no `\Registry`, `\DB`,
`ModelAccountWishlist`) — reading/writing the actual list stays
`ControllerMintcartWishlist`'s job, the same split `CartService`/
`ControllerMintcartCart` already established.

## `WishlistService`

### `normalizeIds(array $ids): array<int>`

De-duplicates, casts to int, drops non-positive/non-numeric leftovers. Used
on both a DB result's `product_id` column and a guest session array (which
can accumulate duplicates over multiple `add()` calls, same as the stock
controller already tolerates via its own `array_unique()`).

### `withProduct(array $ids, $productId, bool $add): array<int>`

Pure array logic: the new membership list after adding/removing one
product. `$add = true` appends (idempotent — adding an already-present id
is a no-op after normalization); `$add = false` removes it if present. The
caller persists the result (an `INSERT`/`DELETE` for a logged-in customer
via `ModelAccountWishlist`, or writing it back to
`$this->session->data['wishlist']` for a guest).

### `snapshot(array $ids, $productId = null): array`

```text
{
  count: int,               // normalized unique id count
  product_ids: list<int>,   // normalized
  active: bool|null         // whether $productId is in the list; null if $productId omitted
}
```

Same "presentation values already resolved upstream" contract
`CartService::snapshot()` uses.

## `ControllerMintcartWishlist`

Fully additive — no existing OpenCart file modified.

- `route=mintcart/wishlist/add` — POST `product_id`. Product must exist
  (`model_catalog_product->getProduct()`) → else `{error: text}`. Logged-in:
  `ModelAccountWishlist::addWishlist()`. Guest:
  `$this->session->data['wishlist']` is replaced with
  `WishlistService::withProduct(..., true)`'s result. Responds with
  `snapshot()` for the given product id.
- `route=mintcart/wishlist/remove` — POST `product_id`. Same split, `add =
  false`. Unlike `add()`, does not re-check product existence (removing a
  since-deleted product id is harmless either storage path).
- `route=mintcart/wishlist/refresh` — GET, no mutation. Responds with
  `snapshot()` for the full list, `active: null` (no single product in
  mind).

## JS: `wishlist-client.js` + `product-card.js`

`addToWishlist(productId)`/`removeFromWishlist(productId)`/
`refreshWishlist()` are a pure data layer (same shape as `cart-client.js`),
each dispatching a bubbling `mc:wishlist:updated` `CustomEvent` on
`document` with the snapshot as `detail` on success. No listener consumes
this event yet (a header wishlist-count badge is a natural next consumer —
see "Not done yet").

`initWishlistToggle()` (replacing the old generic `toggleAction()` call for
`toggle-wishlist` specifically — `toggle-compare` still uses the
unmodified, UI-only `toggleAction()`) optimistically flips the button's
`aria-pressed` and dispatches `mc:product-card:wishlist-toggle` exactly as
before (so any existing listener keeps working unchanged), then calls
`addToWishlist()`/`removeFromWishlist()`. On failure, the button's visual
state is reverted and a `createToast()` error toast is shown, reading its
message from the button's own `data-mc-text-wishlist-error` attribute
(translated server-side per listing controller's own language file, the
same convention `data-mc-text-add-error` already uses) — falls back to a
hardcoded English string if the attribute is absent (e.g. `test_theme`'s
product card, which has no wishlist button at all, never hits this code
path).

`text_wishlist_error` was added to each of the five listing controllers'
own language files (`product/category.php`, `product/search.php`,
`product/manufacturer.php`, `product/special.php`, `product/product.php`)
and wired into their existing `ProductCardListRenderer::render()`
`$languageStrings` argument (or, for `product/product.php`'s own main-
product button, `$data`) — the exact same per-controller pattern
`text_add_to_cart_error` already established.

## Not done yet

- **Compare** — still UI-only, no backend. See "Why this exists" above.
- **No live wishlist-count badge in the header** —
  `MintCart\Design\HeaderViewProvider` already carries `wishlist_count`
  (sourced from OpenCart's own header controller today), but nothing
  listens for `mc:wishlist:updated` to refresh it client-side after a
  toggle without a full page reload. Natural next consumer of this event,
  same role `header-cart.js` plays for `mc:cart:updated` — deliberately not
  built here to keep this increment to the persistence layer itself.
- **No "already wishlisted" initial state on page load** — a product
  already in the wishlist renders with `aria-pressed="false"` until toggled
  once in the current page view; `ProductViewProvider`'s `display`/`actions`
  contract has no `is_wishlisted` field yet. Would need `refresh()`'s
  `product_ids` (or a new `ProductViewProvider` field) threaded through the
  initial page render — a separate, larger increment.
- **No create-flow tie-in with the stock `account/wishlist` page** — the
  stock page/email templates are untouched and keep working via
  `ModelAccountWishlist` exactly as before; this only adds a second,
  AJAX-friendly entry point to the same storage.
- **Not yet verified live** — this environment has no MySQL server/browser
  (same pre-existing gap noted throughout `IMPLEMENTATION_STATUS.md`); a
  human should click the wishlist button on a real install (both logged-in
  and guest) per `docs/MANUAL_VERIFICATION.md`.

## Definition of Done checklist

- [x] Documented contract (this file)
- [x] `WishlistService` has no OpenCart dependency — unit-testable in
      isolation
- [x] No OCMOD, no modification to any existing OpenCart core file (the
      five listing-controller language-string additions are MintCart's own
      additive language keys, same pattern `text_add_to_cart_error` used —
      not a core behavior change)
- [x] Smoke test covering `normalizeIds()`/`withProduct()`/`snapshot()`
      (12 scenarios)
- [x] `mc-` BEM / `data-mc-*` hooks unchanged — no new CSS class, reuses
      the existing `.mc-product-card__action-button` markup
- [ ] Live controller + browser wiring verified against a real install —
      not possible in this environment (see "Not done yet")
- [ ] Header wishlist-count live update — not built yet (see above)
- [ ] Compare service — not built yet, deliberately (see above)
