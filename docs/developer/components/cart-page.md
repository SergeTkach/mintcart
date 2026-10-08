# Cart page — `mc-cart-page`

`mint_default/template/checkout/cart.twig` overrides the stock cart page.
Data unchanged: stock `ControllerCheckoutCart::index()` (`products[]`,
`vouchers[]`, `totals[]`, `modules[]`, `weight`, `action`, `checkout`,
`continue`, alerts). No controller change.

- Items: `ul.mc-cart-page__items > li.mc-cart-page__item` (thumb, info with
  model/options/reward/recurring/unit price, controls: `mc-quantity`,
  line total, remove).
- Quantity: the stock form posts `quantity[cart_id]` to `checkout/cart/edit`
  (works without JS; a `<noscript>` Update button). `cart-page.js`
  submits it 600 ms after the last change.
- Remove: `data-mc-action="remove-cart-item"` → Mint `removeFromCart()` →
  reload. Gift vouchers keep stock `voucher.remove()`.
- Coupon / shipping estimate / voucher blocks are extension output
  (`modules`) and keep their markup (Bootstrap 3 panels via the shim).
- Summary: `aside.mc-cart-page__summary` (sticky on desktop), totals `dl`,
  Checkout button, Continue shopping link.
- Empty cart is the stock `error/not_found` page (not overridden).
