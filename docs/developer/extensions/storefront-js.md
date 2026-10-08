# Storefront JavaScript for extensions

How a third-party OpenCart extension (or a theme) works with MintCart's
storefront JS on a Mint-aware theme (`mint_default` and its children).
Decision record: ADR 012.

## 1. Your own JS / CSS - the OpenCart way

`$this->document->addScript($url)` / `addStyle($url)` in your controller,
before `common/header` renders (module controllers in layout positions run
before it, so this works there too). mint_default's header prints that
queue in `<head>`.

- jQuery 3.7.1 is still loaded for legacy code.
- Bootstrap 3 is **not** loaded - Bootstrap 5 is. BS3 markup,
  `$(...).button('loading'|'reset')`, `data-toggle` / `data-dismiss`,
  `.in` keep working through the compatibility shim (ADR 010).
- HTML you insert later (AJAX) must bring or trigger its own script; Mint
  does not manage third-party scripts.

## 2. Libraries MintCart ships - ask through component markup

Bootstrap 5, Swiper 14 and GLightbox are loaded by MintCart only. Do not
load another copy. Use the component markup; behaviour comes from
`data-mc-*` hooks (CSS classes are presentation only):

| Want | Markup (contract doc in `docs/developer/components/`) |
|---|---|
| Slider / carousel (Swiper) | `.mc-slider[data-mc-slider]` + optional `data-mc-slider-options` (`slider.md`) |
| Add to cart | `button[data-mc-action="add-to-cart"][data-product-id]` + optional `data-quantity`, `data-product-name`, `data-mc-cart-url`, `data-mc-text-view-cart` (`product-card.md`) |
| Wishlist / compare | `[data-mc-action="toggle-wishlist"]`, `[data-mc-action="toggle-compare"]` with `data-product-id` |
| Quick order | `[data-mc-action="quick-order"][data-product-id]` |
| Quantity stepper | `.mc-quantity` with `data-mc-action="quantity-decrement|quantity-increment"` (`quantity.md`) |
| Modal / drawer / toast / tabs | `data-mc-action="open-modal" | "open-drawer" | "open-toast" | "show-tab"` (`modal.md`, `drawer.md`, `toast.md`, `tabs.md`) |

Need the library object itself (e.g. Swiper events)? Use the component's
ready event: `mc:slider:ready` gives `detail.swiper`.

### HTML added after page load

- **`data-mc-action` clicks** are delegated on `document` - they work for
  markup inserted at any time.
- **Sliders** are detected automatically (MutationObserver) and the Swiper
  bundle is fetched on first need - see `slider.md` "Protocol".
- **Bootstrap 3 attributes** in inserted HTML are upgraded automatically.
- **Set up at page load only** (insert-later not supported yet): search
  box, reviews form, viewed products, quantity buttons' disabled-state sync,
  and the `mc:modal|drawer|tabs|toast:*` event relays. Their click
  behaviour still works for late markup.

## 3. Events

All are `CustomEvent`s that bubble; listen on `document` or the element.
`detail` shapes are in each component's doc.

| Event | Fired on / when |
|---|---|
| `mc:cart:updated` | document, after any Mint cart change (detail: cart snapshot) |
| `mc:wishlist:updated` | document, after a wishlist change |
| `mc:product-card:wishlist-toggle` | the wishlist button |
| `mc:quantity:change` | `.mc-quantity` (detail: `{ value }`) |
| `mc:checkout:updated` | checkout root, after recalculation |
| `mc:search-box:results` | search box |
| `mc:viewed-products:rendered` | viewed-products block |
| `mc:slider:ready` | slider root, once per slider (detail: `{ swiper }`) |
| `mc:modal:shown` / `hidden`, `mc:drawer:shown` / `hidden`, `mc:tabs:shown` / `hidden`, `mc:toast:shown` / `hidden` | component root |
| `mc:alert:dismiss` | alert |
| `mc:cookie-consent:shown` / `accepted` | banner |
| `mc:progress:change` / `complete`, `mc:sortable:reorder`, `mc:entity-picker:change`, `mc:media-picker:change` | admin components |

## 4. Global JS API

Only what is listed here is public; everything else in the bundles is
internal and may change.

- `window.MintCart.sliders.init(root?)`, `window.MintCart.sliders.get(el)`
  - present once the slider bundle has loaded (wait for `mc:slider:ready`).

No global cart API yet: use the add-to-cart markup, or POST to
`index.php?route=mintcart/cart/add` (`product_id`, `quantity`,
`option[id]`); the JSON reply carries plain URLs (`&`, not `&amp;`).

## 5. Don't

- Don't load your own Bootstrap, Swiper or GLightbox on Mint themes.
- Don't target `mc-*` CSS classes from JS - use `data-mc-*` hooks.
- Don't patch Mint templates via OCMOD - use theme overrides or slots.
