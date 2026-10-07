# Mini cart — `mc-mini-cart`

`mint_default/template/common/cart.twig` overrides the stock header cart
widget. Data is unchanged: stock `ControllerCommonCart` (`products[]` with
`cart_id`, `thumb`, `name`, `option[]`, `recurring`, `quantity`, `total`,
`href`; `vouchers[]`; `totals[]`; `text_items`, `cart`, `checkout`, ...).
No controller change.

```html
<div id="cart" class="mc-mini-cart dropdown">
  <button class="mc-mini-cart__toggle" data-bs-toggle="dropdown" data-bs-display="static">
    <svg class="mc-mini-cart__icon"> <span class="mc-mini-cart__summary" id="cart-total">
  <div class="mc-mini-cart__panel dropdown-menu dropdown-menu-end">
    <ul class="mc-mini-cart__items"><li class="mc-mini-cart__item">
      a.mc-mini-cart__thumb  .mc-mini-cart__info (__name __meta __line > __qty __price)
      button.mc-mini-cart__remove[data-mc-action="remove-cart-item"][data-cart-id]
    <dl class="mc-mini-cart__totals"><div class="mc-mini-cart__total [--grand]"><dt><dd>
    <div class="mc-mini-cart__actions"> two mc-button links (cart, checkout)
    <p class="mc-mini-cart__empty">
```

- `#cart` / `#cart-total` ids are kept: `header-cart.js` re-fetches
  `common/cart/info` on `mc:cart:updated` and swaps `#cart`'s content.
- Remove: `header-cart.js` → `removeFromCart()` (Mint cart client) →
  `mc:cart:updated` → refresh. The panel stays open if it was open.
- Tokens: `--mc-mini-cart-width`, `--mc-mini-cart-thumb`.
- Not covered: removing a gift voucher from the panel (no button); legacy
  `common.js` `cart.add()` refreshes `#cart > ul`, which this markup no
  longer has - Mint pages use the Mint cart client, which is unaffected.
