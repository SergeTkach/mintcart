# Cart client

JS: `assets/src/js/commerce/cart-client.js`

The `add()`/`remove()`/`update()`/`refresh()` JS API and `mc:cart:updated`
event contract `MINTCART_TECHNICAL_PLAN.md` §25 and
`MINTCART_IMPLEMENTATION_GUIDE.md` §58 specify — every earlier component
doc (`toast.md`, `quantity.md`, `progress.md`, `header.md`, `footer.md`)
only forward-referenced this; this file is where it's actually implemented
and documented for the first time.

Talks to `ControllerMintcartCart` (`docs/developer/commerce/cart-service.md`,
route `mintcart/cart/*`) — a pure data layer, no DOM manipulation, no
toast/UI calls of its own.

## API

```javascript
import { addToCart, removeFromCart, updateCart, refreshCart } from './commerce/cart-client.js';

await addToCart(productId, quantity, options);   // options: {product_option_id: value}, omit if none
await removeFromCart(cartId);
await updateCart(cartId, quantity);
await refreshCart();
```

Each resolves to the `CartService::snapshot()` shape
(`{count, subtotal, total, items[], totals[]}`) and, on success, dispatches
a bubbling `CustomEvent('mc:cart:updated', { detail: snapshot })` on
`document`. This is the single event every cart-aware surface reacts to —
header cart, cart drawer, product-card, product page — none of them need
to know about each other or call each other directly:

```javascript
document.addEventListener('mc:cart:updated', (event) => {
    console.log(event.detail.count, event.detail.total);
});
```

### The option-required case

`addToCart()` does **not** throw and does **not** dispatch
`mc:cart:updated` when the product needs options this caller didn't
supply — nothing changed server-side (`CartService`'s doc explains why).
It resolves to `{ redirected: true, url }` instead, and the **caller**
decides what "redirect" means for its context:

```javascript
const result = await addToCart(productId, quantity);

if (result.redirected) {
    window.location.href = result.url;
    return;
}
```

`mc-product-card`'s Add to Cart button (`assets/src/js/components/product-card.js`)
is the first real caller and does exactly this — see
`docs/developer/components/product-card.md`. A future product page's own
Add to Cart, which already has options selected via a real picker, should
never hit this case.

## Current consumers

- `mc-product-card`'s `[data-mc-action="add-to-cart"]` button
  (`assets/src/js/components/product-card.js`).
- The legacy header cart widget (`assets/src/js/components/header-cart.js`,
  `initHeaderCartRefresh()`) — listens for `mc:cart:updated` and re-fetches
  the *existing*, unmodified `route=common/cart/info` endpoint to refresh
  `#cart`'s markup. Deliberately does **not** render `event.detail` itself
  client-side — the legacy widget's own PHP/Twig already produces correctly
  translated, correctly formatted HTML, so re-fetching it is simpler and
  more correct than duplicating that formatting in JS. This keeps the
  legacy widget in sync with adds/removes/updates that go through the new
  Cart client, which the widget's own jQuery auto-refresh (in stock
  `common.js`) has no way to know about since those requests don't go
  through the legacy `checkout/cart/*` routes it listens to.

## Deferred, not built yet

- A real Mint-native `mc-cart-drawer`/header cart component reading
  `event.detail` directly instead of doing a second fetch — today's
  `header-cart.js` is a bridge to the legacy widget, not a replacement for
  it.
- Product page Add to Cart with a real option picker.
