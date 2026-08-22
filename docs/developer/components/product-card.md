# `mc-product-card`

MintCart's ecommerce reference component (`MINTCART_IMPLEMENTATION_GUIDE.md`
§47, Technical Plan §15D). The first component built directly against a
normalized business-data contract — `MintCart\Commerce\ProductViewProvider`'s
`product` / `display` / `context` output
(`docs/developer/commerce/product-view-provider.md`) — rather than pure
presentational markup.

CSS: `assets/src/css/components/product-card.css`
JS: `assets/src/js/components/product-card.js` (`initProductCardActions`,
imported by `catalog.js` only — this is a storefront component, not an
admin one)

Composes existing Phase 4 components instead of duplicating their CSS:
`mc-badge` (badges), `mc-price` (price/special/tax), `mc-rating` (rating),
`mc-button` (add to cart / quick order). Distinct from the generic `mc-card`
(Phase 4), which has no ecommerce fields.

## Live Twig template

`MintCart\Design\ComponentRenderer` (`docs/developer/theme-api/component-renderer.md`)
and a `mint_default` theme directory now exist, so this component has a real
`.twig` file — `catalog/view/theme/mint_default/template/mint/components/product-card.twig` —
matching the markup contract below exactly. No controller calls it yet:
wiring `ControllerProductCategory` (and Search/Manufacturer/Special) to build
a `ProductViewProvider` result and render it through `ComponentRenderer`,
plus a second test theme proving the override milestone
(`CLAUDE.md` §19), is the next increment — see
`docs/IMPLEMENTATION_STATUS.md`'s Phase 8 section.

## Markup contract

Given a `ProductViewProvider::build()` result assigned to `product` /
`display` / `context` (the exact shape documented in
`docs/developer/commerce/product-view-provider.md`), the expected Twig
template is:

```twig
<article class="mc-product-card{% if display.action_variant == 'compact' %} mc-product-card--compact{% endif %}">
    <div class="mc-product-card__media">
        <a class="mc-product-card__media-link" href="{{ product.url }}" tabindex="-1" aria-hidden="true">
            <img class="mc-product-card__image" src="{{ product.image }}" alt="{{ product.image_alt }}" loading="lazy">
            {% if display.show_secondary_image and product.secondary_image %}
                <img class="mc-product-card__image mc-product-card__image--secondary" src="{{ product.secondary_image }}" alt="" loading="lazy">
            {% endif %}
        </a>

        {% if product.badges %}
            <ul class="mc-product-card__badges">
                {% for badge in product.badges %}
                    <li><span class="mc-badge mc-badge--{{ badge.type }}">{{ badge.label ?: text_badge[badge.type] }}</span></li>
                {% endfor %}
            </ul>
        {% endif %}

        <div class="mc-product-card__quick-actions">
            <button type="button" class="mc-product-card__action-button" data-mc-action="toggle-wishlist" data-product-id="{{ product.id }}" aria-pressed="false" aria-label="{{ text_wishlist }}">
                {# swap for {{ mc_icon('heart') }} once the Bootstrap Icons SVG renderer exists (Implementation Guide §21) #}
                ♥
            </button>
            {% if product.actions.compare %}
                <button type="button" class="mc-product-card__action-button" data-mc-action="toggle-compare" data-product-id="{{ product.id }}" aria-pressed="false" aria-label="{{ text_compare }}">
                    {# swap for {{ mc_icon('compare') }} once the Bootstrap Icons SVG renderer exists #}
                    ⇄
                </button>
            {% endif %}
        </div>
    </div>

    <div class="mc-product-card__body">
        <h3 class="mc-product-card__title">
            <a href="{{ product.url }}">{{ product.name }}</a>
        </h3>

        {% if display.show_rating and product.rating %}
            <div class="mc-rating" style="--mc-rating-value: {{ (product.rating / 5 * 100)|round }}%" role="img" aria-label="{{ text_rated|format(product.rating) }}">
                <span class="mc-rating__stars" aria-hidden="true">
                    <span class="mc-rating__stars-empty">★★★★★</span>
                    <span class="mc-rating__stars-filled">★★★★★</span>
                </span>
            </div>
        {% endif %}

        {% if display.show_description and product.description %}
            <p class="mc-product-card__description">{{ product.description }}</p>
        {% endif %}

        <div class="mc-price{% if product.special %} mc-price--special{% endif %}">
            <span class="mc-price__current">{{ product.special ?: product.price }}</span>
            {% if product.special %}<span class="mc-price__original">{{ product.price }}</span>{% endif %}
            {% if product.tax %}<span class="mc-price__tax">{{ text_tax }}: {{ product.tax }}</span>{% endif %}
        </div>

        {% if not product.stock.in_stock and product.stock.label %}
            <p class="mc-product-card__stock mc-product-card__stock--out">{{ product.stock.label }}</p>
        {% endif %}
    </div>

    <div class="mc-product-card__actions">
        <button
            type="button"
            class="mc-button mc-button--primary mc-product-card__add-to-cart"
            data-mc-action="add-to-cart"
            data-product-id="{{ product.id }}"
            data-quantity="{{ product.minimum }}"
            {{ product.actions.add_to_cart ? '' : 'disabled' }}
        >
            {{ text_add_to_cart }}
        </button>

        {% if product.actions.quick_order and display.action_variant != 'compact' %}
            <button type="button" class="mc-button mc-button--secondary mc-product-card__quick-order" data-mc-action="quick-order" data-product-id="{{ product.id }}">
                {{ text_quick_order }}
            </button>
        {% endif %}
    </div>
</article>
```

`text_badge`, `text_wishlist`, `text_compare`, `text_rated`, `text_tax`,
`text_add_to_cart`, `text_quick_order` are ordinary OpenCart language
strings — the same pattern every other `catalog/language/*` file already
uses, not a new i18n mechanism.

## Behavior hooks (`CLAUDE.md` §14)

| Hook | Where | Behavior |
|---|---|---|
| `data-mc-action="toggle-wishlist"` | `.mc-product-card__action-button` | Toggles its own `aria-pressed`, dispatches bubbling `mc:product-card:wishlist-toggle` with `{ productId, active }`. **No network call** — see below. |
| `data-mc-action="toggle-compare"` | `.mc-product-card__action-button` | Same as wishlist, dispatches `mc:product-card:compare-toggle`. |
| `data-mc-action="add-to-cart"` | `.mc-product-card__add-to-cart` | **Markup + hook only, no JS shipped yet.** Reserved for the future Cart client (Phase 15, `mc:cart:updated` contract, `MINTCART_TECHNICAL_PLAN.md` §25). |
| `data-mc-action="quick-order"` | `.mc-product-card__quick-order` | **Markup + hook only, no JS shipped yet.** Reserved for the future `QuickOrderService`/component (Phase 15, `MINTCART_TECHNICAL_PLAN.md` §28). |

### Why wishlist/compare toggle but add-to-cart doesn't

`initProductCardActions()` only flips a button's pressed state and emits an
event — it never calls a backend. That's a complete, self-contained UI
behavior today (same category as `mc-alert`'s dismiss or `mc-quantity`'s
stepper). Add-to-cart and quick-order are different: making them actually do
something requires a real Cart/QuickOrder service to call, and that service
doesn't exist yet (`MintCart\Commerce\CartService`/`QuickOrderService` are
Phase 15). Shipping fake or guessed AJAX now would mean inventing an API
surface ahead of its real consumer (`CLAUDE.md` §85) — the buttons render in
the correct disabled/enabled state today (`product.actions.add_to_cart`) and
carry the hook a future Cart client attaches to, without pretending to add
anything to a cart that doesn't exist.

## Clickable title, not clickable card

Only the title link (`.mc-product-card__title a`) is a real `<a>`; its
`::after` pseudo-element stretches to cover the card body so clicking
anywhere in the body (not just the title text) navigates to the product,
without nesting a second `<a>` around the whole card (invalid HTML — the
media link and the wishlist/compare buttons would end up nested inside it).
The media block has its own separate link for the same reason. Interactive
elements (`__action-button`, `__add-to-cart`, `__quick-order`) sit in a
stacking context (`z-index: 1`) above the stretched title link so they stay
clickable on their own.

## Secondary image swap needs no JS

`.mc-product-card__image--secondary` is absolutely stacked over the primary
image and faded in via `:hover`/`:focus-within` on `.mc-product-card__media`
— a keyboard user tabbing to the media link sees the same swap a mouse
hover gives, with no JS required.

## Badges

Renders whatever `product.badges` contains (`type` + `label`, per
`ProductViewProvider`) using the existing `mc-badge` variants —
`mc-badge--sale`, `mc-badge--out-of-stock`, `mc-badge--new` map directly to
the badge `type` values the provider emits. A badge with no `label` falls
back to a caller-supplied `text_badge[type]` map (a plain per-language
string lookup, e.g. `text_badge.sale = "Sale"`), so the component never
hardcodes English text for a badge that has no explicit label.

## Variant: compact

`display.action_variant == 'compact'` (e.g. a "related products" rail) adds
`mc-product-card--compact`, which hides the description and tightens body
padding via CSS only — same DOM/contract, no separate template.

## Accessibility

- Media link and title link are both real, keyboard-reachable `<a>`
  elements; the title's `::after` full-card hit area doesn't remove the
  media link, so both remain independently focusable/navigable.
- `aria-pressed` on the wishlist/compare buttons communicates toggle state
  to assistive tech; `aria-label` (not icon-only text) names the action.
- Rating uses `mc-rating`'s existing `role="img"` + `aria-label` contract
  (see `docs/developer/components/rating.md`) — never rendered for a
  product with no rating (`product.rating` falsy).
- Disabled `add-to-cart` (`product.actions.add_to_cart == false`) uses the
  native `disabled` attribute, not a CSS-only look, so it's excluded from
  the tab order and announced as disabled.
- `:focus-visible` outlines on the icon action buttons (no focus trap
  needed — they're simple toggle buttons, not a dialog).

## Definition of Done checklist (`MINTCART_IMPLEMENTATION_GUIDE.md` §79)

- [x] Documented contract (this file + `docs/developer/commerce/product-view-provider.md`)
- [x] `mc-*` BEM markup
- [x] JS only where actually needed (wishlist/compare toggle state); add-to-cart/quick-order stay hook-only pending Phase 15
- [x] Colors/spacing/fonts read from `--mc-*` tokens
- [x] Keyboard usable (real `<a>`/`<button>` elements, focus-visible outlines, no keyboard trap)
- [x] Mobile checked (flexible media aspect-ratio, wrapping actions row, no fixed widths)
- [x] No Bootstrap 3 dependency
- [x] No OCMOD required
- [x] Ships a real Mint Default `.twig` file, resolvable via `ComponentRenderer`
- [ ] Theme override checked end-to-end — needs a second test theme (next Phase 8 increment)
- [ ] Live controller wiring (Category/Search/Manufacturer/Special actually rendering this template via `ProductViewProvider`) — next Phase 8 increment, see "Live Twig template" above
