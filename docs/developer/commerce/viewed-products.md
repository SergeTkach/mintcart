# `mc-viewed-products` / `mintcart/viewed/products`

"Recently viewed" storefront strip (`MINTCART_FUNCTIONAL_SCOPE.md` §11
"viewed products", Commerce Essentials, priority **P0**). Unlike
`SearchService`/`mc-search-box`, this feature has no dedicated pure service
class — the server side is a single small, fully-additive controller, and
the client side does both the tracking and the rendering.

PHP: `upload/catalog/controller/mintcart/viewed.php`
(`ControllerMintcartViewed`, route `mintcart/viewed/products`)

JS: `assets/src/js/components/viewed-products.js`
(`recordViewedProduct()`, `renderViewedProducts()`, `initViewedProducts()`)

CSS: `assets/src/css/components/viewed-products.css`

Consumer: `mint_default/template/product/product.twig` (the only template
that renders `[data-mc-viewed-products]` today)

## Why a new controller instead of a service class

`ControllerMintcartSearch`/`SearchService` split their logic into a pure,
unit-testable class because the response shape (`{query, count, items}`)
and validation rules (`normalizeQuery()`/`isQueryValid()`) are non-trivial
enough to test in isolation. This endpoint's only real logic is "loop over
at most `MAX_IDS` client-supplied ids, call the existing
`model_catalog_product->getProduct()` for each, skip ones that come back
empty" — the same few lines `ControllerMintcartSearch::suggest()` itself
already writes inline for its own per-result formatting, with no separate
class extracted there either. Extracting a class here with no second
consumer would be an abstraction ahead of any real need (`CLAUDE.md` §85/§86).

## `ControllerMintcartViewed` actions

| Route | Method | Notes |
|---|---|---|
| `mintcart/viewed/products` | GET `product_id[]=1&product_id[]=2...` | Resolves each id via `model_catalog_product->getProduct()` (enforces the same `status`/`date_available`/`store_id` visibility rule every product page already applies), skipping any that don't resolve. Caps input at `MAX_IDS` (12) — a manipulated query string can't force more than that many lookups per request. |

Response shape:

```text
{
  items: [
    { id, name, url, image, price, special }
    ...
  ]
}
```

Same `id, name, url, image, price, special` item shape
`mintcart/search/suggest` already established (Technical Plan §26) — a
"recently viewed" link-through strip needs nothing from the full
`ProductView` contract (no badges/stock/rating/actions), so this endpoint
does not build one (`CLAUDE.md` §85). Items are returned in the same order
the caller's `product_id[]` list was given — the caller (this endpoint has
none but its own JS client) decides ordering, typically most-recently-viewed
first.

A disabled, deleted, or out-of-store-scope product silently drops out of
the response — there is no error entry for a missing id, since a shopper's
recently-viewed history naturally accumulates products that later become
unavailable, and the strip should just quietly shrink rather than show a
broken entry.

## `viewed-products.js`

### Recording (`recordViewedProduct(root = document)`)

Reads `[data-mc-product-form] [data-mc-action="add-to-cart"][data-product-id]`
— the same hook already rendered on the current page's own add-to-cart
button for the add-to-cart flow itself (`product.twig`,
`data-mc-product-form` wrapper) — scoped to that wrapper specifically so a
related-product card's own add-to-cart button (also `data-product-id`, also
present on the same page) is never mistaken for the page's own product.
**No PHP/Twig change was needed to expose the id** — it was already there.

The resolved id is pushed to the front of a `localStorage` array under
`mc:viewed:product_ids`, deduplicated (a re-visit moves the id back to the
front rather than adding a second entry) and capped at `MAX_HISTORY` (12,
matching the server's `MAX_IDS`). Every `localStorage` read/write is
wrapped in `try`/`catch` — a private-browsing quota error or disabled
storage degrades to "no history recorded/found", never a page error.

### Rendering (`renderViewedProducts(root)`)

For a given `[data-mc-viewed-products]` root: reads the history, removes
the id named in that root's own `data-mc-viewed-products-exclude` attribute
(the current page's own product, so a shopper never sees "recently viewed:
the page they're already on"), and caps the remainder at `MAX_DISPLAY` (8).
If nothing remains, the root stays `hidden` and no request is made. If the
resulting list also comes back with zero live items (e.g. everything in
range was disabled/deleted), the root is hidden again — same empty-state
approach `mc-search-box` uses for its own results panel.

Rendered items reuse `mc-product-card__media`/`__body`/`__title`/`mc-price`
in full (`MINTCART_IMPLEMENTATION_GUIDE.md` §9 — don't duplicate an
existing look) with **no** `__actions`/quick-actions block: this item shape
carries no `actions`/`minimum`/`badges`/`stock` data to drive
add-to-cart/wishlist/compare/quick-order, so the strip is a link-through
card, not a full interactive `mc-product-card`.

Fires `mc:viewed-products:rendered` (bubbling, `detail: { items }`) on
`root` after a successful render, so a host page can react without
re-querying `localStorage`.

## Markup contract

```html
<div class="mc-viewed-products" data-mc-viewed-products data-mc-viewed-products-exclude="{{ product_id }}" hidden>
    <h2 class="h4 mb-3">{{ text_viewed }}</h2>
    <div class="row g-4" data-mc-viewed-products-list></div>
</div>
```

`data-mc-viewed-products-exclude` is required — without it, the current
page's own product could appear in its own "recently viewed" strip.
`initViewedProducts()` (called once from `catalog.js`) both records the
current page's product (if any) and renders every
`[data-mc-viewed-products]` root found — a page can call
`recordViewedProduct()`/`renderViewedProducts()` directly if it needs
different timing.

## What this is not

- **Not a page-view analytics feature.** Only ever records the product id
  already rendered on the add-to-cart button — no view timestamps, no
  server-side log, nothing beyond a capped client-side id list.
- **Not the full `ProductView` contract.** Same smaller item shape
  `mintcart/search/suggest` uses, for the same reason (`CLAUDE.md` §85) —
  no real consumer needs badges/stock/rating/actions on this strip yet.
- **Not wired into category/cart pages yet.** Today's increment only wires
  the render side into `product/product.php`'s own template — a future
  increment could add the same markup contract to category/cart pages with
  zero JS/PHP changes (the component already renders against any
  `[data-mc-viewed-products]` root in the document).

## Definition of Done checklist

- [x] Documented contract (this file)
- [x] `mc-*` BEM markup (reuses `mc-product-card`'s existing elements, adds
      only `mc-viewed-products`/`mc-viewed-products__item`)
- [x] JS reads `data-mc-*` hooks, never a CSS class, as the trigger/query
      contract
- [x] No OCMOD, no modification to any existing OpenCart file beyond the
      purely additive `product/product.php`/`product.twig` pair (see
      `docs/upstream-diff.md`'s `product/product.php` entry, "Addendum"
      section)
- [x] Reuses `mc-product-card`/`mc-price` rather than a second card look
- [x] `localStorage` access is defensive (`try`/`catch`) — private-browsing
      quota errors degrade to no history, never a page error
- [x] Mobile checked — same Bootstrap `row`/`col-6 col-lg-3` grid the
      Related Products section already uses
- [x] No Bootstrap 3 dependency
- [ ] Theme override checked — `test_theme` does not override
      `product.twig`/`product-card.twig` for this section yet; inherits the
      `mint_default` markup unchanged
- [ ] Live data verified end to end — this environment has no MySQL server
      (same pre-existing gap noted throughout `docs/IMPLEMENTATION_STATUS.md`);
      see `docs/MANUAL_VERIFICATION.md`'s new Phase 15 entry

## Verified

- `php -l` clean on all touched/added PHP files.
- `npm install` (0 vulnerabilities), `npm run build` (minified) compile
  cleanly.
- `mc-viewed-products` present in the bundled `catalog.min.js`/
  `index.min.css`/`components.min.css` and **absent** from `admin.min.js`
  (storefront-only, `grep -c`, same scoping `search-box.js` uses).
- `npm test` (preset-token contract checks, unaffected) — still passing.
- Full `tests/mintcart/**/*-test.php` suite re-run end to end (84 files) —
  unaffected, still passing.

**Not verified:** no live browser/MySQL session in this environment — see
`docs/MANUAL_VERIFICATION.md`'s new Phase 15 entry for the full manual
checklist.

## Picture size (2026-10)

`mintcart/viewed/products` returns the card picture size (`theme_*_image_product_*`) plus a 1x/2x `srcset`
(`srcset`, `width`, `height` per item), not the 47 px cart thumbnail it used before. The strip title is a
styled label (`mc-product-section__title`), not a decorative `h2`.
