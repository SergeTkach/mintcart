# 016 — Retail header and card details

## Context

The home page had the soft system (ADR 015), but the header still looked like a
template: pale controls, scattered text links, no phone, no way to submit a
search. Important elements (cart, menu) must look strong, not faint.

## Decision

- **Top bar (dark):** the store phone (`contacts.phone`, from the store settings) next to
  the announcement text (`mintcart_announcement_text`). On phones the phone moves into the
  menu drawer.
- **One row on desktop:** logo, a search with a green round submit button, then
  currency, account, wishlist and compare (each with an icon), then the cart as a solid
  green pill showing the total (`mc-mini-cart__total`; icon + count on phones). The menu bar
  stays solid brand green with pill links. No rule under the header.
- **Search:** Enter in the field now opens the results page (it only worked for a focused
  suggestion before) and the button does the same.
- **Cards:** a red "-NN%" tag instead of "Sale" when the discount is known
  (`ProductViewProvider` badge `sale` gets `label: "-20%"`, from `product.discount`
  computed in `mintcart/product_cards`), the star rating under the title, a bolder price.
- **Section heads:** the Products block takes an optional "View all" link
  (`more_url` setting, `more_label` text).
- **Drawer:** phone card, currency + language pair, wishlist + compare pair, and the
  account as one full-width green button at the bottom (its menu opens upward).
- Header contract: `links.compare` added (`HeaderViewProvider::LINK_KEYS`), `text_compare`,
  `text_call` strings.

## Consequences

- The header is denser on desktop; below 992 px it keeps the compact layout (menu button,
  logo, cart, search) and everything else lives in the drawer.
- Themes that override `header.twig` keep working; the new `links.compare` and `contacts.phone`
  are optional to render.
- The category tile picture must have the tile's ratio (OpenCart's `resize()` pads other
  ratios with white); the demo crops a 4:3 picture per top category.
