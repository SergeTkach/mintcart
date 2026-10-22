# Sticky buy controls on the product page

Two views of the same idea, so the Add to Cart button is always within reach
after the visitor scrolls past the main one.

| Screen | Control | Behaviour |
|---|---|---|
| under 992 px (phones, tablets) | `mc-buy-bar` | fixed to the bottom; appears once the main Add to Cart button has scrolled out of view |
| 992 px and up (desktop) | `mc-buy-box` | a small card (picture, name, price, button) that sticks on the right beside the lower sections (description, specs, reviews, related), under the sticky section nav |

A full-width bottom bar on a wide screen is mostly empty space, so from 992 px
the bar is hidden and the box is used instead (the pattern of the helvion theme).

## Markup

`product.twig` renders the bar at the end of the page and the box inside
`.mc-product-body` next to `.mc-product-body__main` (the lower sections). Both
need a price (`{% if price %}`). Both buttons carry
`data-mc-action="buy-bar-add"`, and every `[data-mc-price-current]` element is
updated when an option changes the price (`product-price.js`).

## Behaviour

`assets/src/js/components/buy-bar.js`: one delegated click handler clicks the
real Add to Cart button, so options, quantity, the "Added to cart" modal and
missing-option marking all stay in `product-card.js`. The scroll check only
drives the phone bar. The box is pure CSS (`position: sticky`, top =
`--mc-header-sticky-offset` + `--mc-section-nav-height` + a gap).

CSS: `assets/src/css/components/buy-bar.css`.

## Tests

Checked in headless Edge at 1280 px: the box is at the right of the lower
sections while scrolling, the bottom bar is `display: none`. Phone layout was
not re-checked in this change.
