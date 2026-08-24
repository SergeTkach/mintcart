# `MintCart\Commerce\CartService`

Normalizes cart data for the Theme API's Cart contract
(`MINTCART_TECHNICAL_PLAN.md` §25, `MINTCART_IMPLEMENTATION_GUIDE.md` §58) —
the same normalization role `ProductViewProvider` plays for a single
product, applied to the cart as a whole.

PHP: `upload/system/library/mintcart/commerce/cartservice.php`
(class `MintCart\Commerce\CartService`)

Test: `tests/mintcart/commerce/cart-service-test.php`
(`php tests/mintcart/commerce/cart-service-test.php`)

Consumer: `upload/catalog/controller/mintcart/cart.php`
(`ControllerMintcartCart`, route `mintcart/cart/{add,remove,update,refresh}`)

## Why a new controller instead of extending `checkout/cart.php`

Stock `catalog/controller/checkout/cart.php`'s AJAX `add()`/`remove()`
actions return only a human-readable `total` string (`"3 item(s) -
$45.00"`) — no item list, no count, no structured totals. Not enough for
the Cart JS client's `mc:cart:updated` event contract, which every
listening component (header cart, cart drawer, product-card, product page)
needs real data from.

Two ways to close that gap: modify `checkout/cart.php` to enrich its JSON,
or add a new, fully-additive MintCart-owned controller. Picked the latter:
**zero upstream diff** — no existing OpenCart file is touched. Both the
legacy checkout page and the new `mintcart/cart/*` endpoints mutate the
same session `Cart\Cart` model (`$this->cart`), so they stay in sync
automatically; nothing about the stock checkout flow changes.

## Design

`CartService` has **no OpenCart dependency** — no `\Registry`, `\DB`,
`Cart\Cart`. Fetching cart rows, resizing thumbs, formatting currency and
actually mutating the cart (`$this->cart->add()`/`update()`/`remove()`)
all stay `ControllerMintcartCart`'s job upstream of this class, exactly the
split `ProductViewProvider` already established.

### `validateOptions(array $productOptions, array $submittedOptions): array`

Given a product's already-fetched option definitions
(`model_catalog_product->getProductOptions()`'s shape — a list of
`{product_option_id, required, ...}`) and what was actually submitted
(`product_option_id => value`), returns the list of required option ids
that are missing. Empty array = valid.

This mirrors — does not duplicate the storage/session side of —
`checkout/cart.php::add()`'s own required-option check; that stock method
is untouched. Two callers now share the same validation *rule* as pure
array logic instead of each re-deriving it.

### `snapshot(array $items, array $totals): array`

```text
{
  count: int,               // sum of item quantities, not line count
  subtotal: string,
  total: string,
  items: [
    { cart_id, product_id, name, url, thumb, quantity, price, total,
      option_summary: [{name, value}, ...] | null }
    ...
  ],
  totals: [{ title: string, text: string }, ...]   // Sub-Total, Tax (if
                                                     // present), Total
}
```

`$items` and `$totals` are already-resolved (thumb already resized via
`model_tool_image`, price/total/subtotal already formatted via
`$this->currency`) — same "presentation values already resolved upstream"
contract `ProductViewProvider::build()` uses for a single product.

**Deliberately not** OpenCart's full extension-totals breakdown
(shipping/coupon/handling fees, the `total_*` extension loop
`checkout/cart.php::index()` runs). `totals[]` here is a simple
subtotal(+tax)/total summary — enough for a header cart count or a "3
items — $45.00" style readout. The full checkout-parity breakdown stays
checkout's job (a future `CheckoutService`, not this one).

## `ControllerMintcartCart` actions

| Route | Method | Mutates? | Notes |
|---|---|---|---|
| `mintcart/cart/add` | POST `product_id`, `quantity`, `option[]` | yes | If required options (or a required recurring plan) are missing, returns `{error, redirect: <product url>}` **without** calling `$this->cart->add()` — a grid Add to Cart button can't offer an option picker, so this is the same "send the shopper to the full product page" fallback `checkout/cart.php` itself uses. |
| `mintcart/cart/update` | POST `key` (cart_id), `quantity` | yes | |
| `mintcart/cart/remove` | POST `key` (cart_id) | yes | |
| `mintcart/cart/refresh` | GET | no | Current snapshot, no mutation — for the JS client's `refresh()` and initial page-load sync. |

A successful `add()` also unsets `shipping_method`/`shipping_methods`/
`payment_method`/`payment_methods` from the session, the same
checkout-invalidation side effect `checkout/cart.php::add()` applies (a
changed cart can change shipping/payment eligibility).

## Definition of Done checklist

- [x] Documented contract (this file)
- [x] No OpenCart dependency — unit-testable in isolation
- [x] No OCMOD, no modification to any existing OpenCart file
- [x] Smoke test covering `validateOptions()` and `snapshot()`
- [x] Live controller wiring (`ControllerMintcartCart`) — verified against a
      real install: add/update/remove/refresh round-trip, and the
      required-option redirect case confirmed to add nothing to the cart
- [ ] Header cart dropdown reacting to `mc:cart:updated` — deferred; the
      legacy `common/cart.php` widget is untouched by this increment
- [ ] `QuickOrderService` — separate service, not built yet
