# "Added to cart" modal

After a successful add-to-cart (product card in any list, or the product page,
including the sticky buy bar) the storefront opens a modal instead of a corner
toast: the product picture and name, a **Continue shopping** button and a
**View cart** button. Errors (could not add, missing option, wishlist failure)
are still toasts.

Why a modal: a toast in the bottom corner is easy to miss and offers no next
step.

## How it works

- `assets/src/js/components/cart-added-modal.js` builds one `mc-modal`
  (Bootstrap Modal underneath, `data-mc-action="close-modal"` for closing) on
  first use and reuses it. The markup lives in the script, so every theme gets
  it; themes restyle it through the `.mc-modal` tokens.
- `product-card.js` calls `showCartAdded(button)` after `addToCart()` succeeds.
- Texts come from the clicking button's data attributes, translated server-side:
  `data-mc-text-added` (title), `data-mc-text-continue`, `data-mc-text-view-cart`,
  `data-mc-cart-url`, `data-product-name`. The three card templates and
  `product.twig` set them; the strings are `text_added_to_cart`,
  `text_view_cart`, `text_continue_shopping` (`mintcart/product_card` for cards,
  `product/product` for the product page).
- The picture is read from the DOM next to the button (card image or the product
  page's main image); without one the picture is hidden.
- CSS: `.mc-cart-added*` in `assets/src/css/components/modal.css`.

## For theme authors

A custom product card must keep the `data-mc-text-continue` attribute next to
`data-mc-text-added` on its add-to-cart button, otherwise the English default
"Continue shopping" is used.

## Tests

Checked in headless Edge on the local store: a click on a card's add button
opens the modal with the product name and the cart link, and no toast appears.
There is no automated browser test yet.

## Not done

- Quick Order success still uses a toast.
- No mini cart summary (item count/total) inside the modal.
