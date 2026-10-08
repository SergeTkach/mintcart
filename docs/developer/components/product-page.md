# Product page (PDP) — mint_default

Pieces added 2026-10-07 on top of `product/product.twig`:

- **`mc-stock`** — stock text with a coloured dot; `mc-stock--in` /
  `mc-stock--out` from `mc_in_stock` (`product.php`, `quantity > 0`).
- **`mc-choice-group`** on radio / checkbox option fieldsets — options as
  pills; the real input stays focusable (visually hidden over the pill),
  `:has(:checked)` marks the choice, `.mc-field.has-error` paints it red.
- **`mc-price--lg`** — headline price size (replaces an inline style).
- **`mc-product-page__options-title`** — "Available Options" label (styled
  text, not an `h2`, Guide §19.1).
- **`mc-buy-bar`** (`buy-bar.css`, `buy-bar.js`) — sticky bottom bar (thumb,
  name, price, Add to Cart) shown while the main add-to-cart button is above
  the viewport. Its button clicks the main one, so options, quantity, toast
  and missing-option handling live only in `product-card.js`. A missing
  option is scrolled to the centre (clear of the sticky header) and focused.
  Rendered only when the product has a price.

Changed 2026-10-08:

- **`mc-section-nav`** (see `section-nav.md`) replaces the `mc-tabs`
  Description / Specification / Reviews block. Content is stacked as
  `mc-product-section` blocks (label = `mc-product-section__title`, styled
  text, not a heading); the sticky nav jumps between them and highlights
  the one in view. Reviews render only when `review_status` is on (as in
  the stock template). Related Products became a section too.
- The rating under the title links to the Reviews section and shows the
  "Reviews (N)" label.
- Live total price on quantity / option change (main price + buy bar) —
  `docs/developer/commerce/product-price.md`.

Not done yet: delivery / payment / returns info block (needs a decision on
where that content comes from), Quick Order missing-option handling.
