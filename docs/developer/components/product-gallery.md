# `mc-product-gallery`

Main image + thumbnail strip for the product detail page
(`mint_default/template/product/product.twig`, Phase 6/8's identified gap —
Category/Search/Manufacturer/Special listing pages were wired to
`mc-product-card`, but the product page itself never existed until this
increment).

CSS: `assets/src/css/components/product-gallery.css`
JS: `assets/src/js/components/product-gallery.js` (`initProductGalleries`)

Distinct from `mc-gallery` (the Homepage Builder Gallery block's static,
non-interactive image grid, `docs/developer/blocks/gallery.md`) — that
component has no "main image" concept to swap, this one does.

## Basic usage

```html
<div class="mc-product-gallery">
    <a href="/image/catalog/product-popup.jpg" class="mc-product-gallery__main-link">
        <img class="mc-product-gallery__main" src="/image/catalog/product-thumb.jpg" alt="Product name">
    </a>

    <ul class="mc-product-gallery__thumbs">
        <li>
            <a
                class="mc-product-gallery__thumb is-active"
                href="/image/catalog/product-popup.jpg"
                data-mc-action="gallery-thumb"
                data-mc-gallery-src="/image/catalog/product-thumb.jpg"
                data-mc-gallery-alt="Product name"
            >
                <img class="mc-product-gallery__thumb-image" src="/image/catalog/product-thumb.jpg" alt="">
            </a>
        </li>
        <li>
            <a
                class="mc-product-gallery__thumb"
                href="/image/catalog/product-2-popup.jpg"
                data-mc-action="gallery-thumb"
                data-mc-gallery-src="/image/catalog/product-2-thumb.jpg"
                data-mc-gallery-alt="Product name"
            >
                <img class="mc-product-gallery__thumb-image" src="/image/catalog/product-2-thumb.jpg" alt="">
            </a>
        </li>
    </ul>
</div>
```

- Every thumbnail is a real `<a href="...">` pointing at the image's own
  full-size ("popup") URL — this is progressive enhancement: a visitor gets
  a working full-size image link even before/without `initProductGalleries()`
  running, matching the same "not JS-only" spirit `mc-search-box`'s "View all
  results" link follows.
- `data-mc-gallery-src`/`data-mc-gallery-alt` on the thumbnail are the data
  this component's own JS reads to swap `.mc-product-gallery__main` — never
  the thumbnail's own `src`, which is a smaller resized thumbnail image, not
  the resolution the main slot should show.
- The first `<li>` re-lists the product's own default thumbnail/popup pair,
  so clicking back to it after viewing another image works the same way any
  other thumbnail does.

## Behavior

`initProductGalleries()` is called once from `catalog.js` (storefront-only —
gallery viewing is not an admin concept) and delegates on `document`, same
pattern as `mc-quantity`/`mc-alert`.

- Clicking `[data-mc-action="gallery-thumb"]` prevents the link's default
  navigation and instead swaps the enclosing `.mc-product-gallery`'s
  `.mc-product-gallery__main` `src`/`alt` to the clicked thumbnail's own
  `data-mc-gallery-src`/`data-mc-gallery-alt`, and moves the `is-active`
  state to that thumbnail.
- No network call, no lightbox/zoom — a future increment can add either as a
  separate, documented enhancement once a real product need for it exists
  (`CLAUDE.md` §85).

## Accessibility

- Every thumbnail is a real, keyboard-focusable `<a>` — Enter/Space (native
  link activation) both work without any extra key handling.
- The main image's `alt` always tracks the currently-shown image's own
  description (the product name for the default image, initially — a
  per-additional-image alt/caption isn't in OpenCart's own product image
  data model, so this defers to the product name).

## Definition of Done checklist (`MINTCART_IMPLEMENTATION_GUIDE.md` §79)

- [x] Documented contract (this file)
- [x] `mc-*` BEM markup
- [x] JS behavior only via `data-mc-action`, never a CSS class selector
- [x] Colors/spacing/radius read from `--mc-*` tokens
- [x] Keyboard usable (real `<a>` elements, no custom key handling needed)
- [x] Mobile checked (thumbnails wrap, main image uses `aspect-ratio` + `object-fit: contain`)
- [x] No Bootstrap 3 dependency
- [x] No OCMOD required
- [x] Theme override checked — `test_theme` doesn't override `product.twig`
      or this component, so it falls back to `mint_default`'s version via
      the same Theme resolver chain Phase 8 already proved (ADR 002/003)
