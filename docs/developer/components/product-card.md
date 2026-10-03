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
matching the markup contract below exactly.

`ControllerProductCategory` (`upload/catalog/controller/product/category.php`,
see `docs/upstream-diff.md`) was the first controller wired to it. Once
`ControllerProductSearch` needed the exact same per-product build/render/
degrade-on-failure loop, that shared shape was extracted into
`MintCart\Design\ProductCardListRenderer` (composes `ProductViewProvider` +
`ComponentRenderer`, plus `MintCart\Design\ActiveTheme` for the
`config_theme`/manifest lookup both controllers also duplicated) — see
`docs/developer/theme-api/product-card-list-renderer.md`. Category, Search,
Special and Manufacturer all call it for each product row, rendering into
`$data['product_cards']`, consumed by `mint_default/template/product/category.twig`,
`mint_default/template/product/search.twig`,
`mint_default/template/product/special.twig` and
`mint_default/template/product/manufacturer_info.twig` respectively.

**`ControllerProductProduct`** (the product *detail* page,
`upload/catalog/controller/product/product.php`) is the fifth consumer —
its own "Related Products" list, using the exact same
`ProductCardListRenderer`/`ActiveTheme` pair, rendered into
`mint_default/template/product/product.twig`. This closes a gap every
earlier phase's status notes flagged repeatedly: Category/Search/
Manufacturer/Special were wired to `mc-product-card`, but the product page
itself — the page a shopper actually buys from — never existed under
`mint_default` at all until this increment. The *main* product on that page
(gallery, options, discounts, attributes) is rendered directly from the
controller's own resolved `$data`, not through `ProductViewProvider` — that
contract is shaped for a listing-grid card, not a full product page's
richer hero section; see `docs/developer/components/product-gallery.md` for
the one genuinely new component this page needed (a main-image/thumbnail
swap `mc-product-card` has no equivalent of).

A second theme, `catalog/view/theme/test_theme/`, overrides only
`product-card.twig` with a genuinely different DOM (single badge, no
secondary-image swap, no wishlist/compare quick-actions, no quick-order
button) — see `docs/adr/002-theme-api-milestone-proof.md` for the full
milestone-proof writeup and its one open caveat (resolution is proven;
live rendering on a real install still needs a human, no MySQL/Twig vendor
in this environment).

## Markup contract

Given a `ProductViewProvider::build()` result assigned to `product` /
`display` / `context` (the exact shape documented in
`docs/developer/commerce/product-view-provider.md`), the expected Twig
template is:

```twig
<article class="mc-product-card{% if display.action_variant == 'compact' %} mc-product-card--compact{% endif %}">
    <div class="mc-product-card__media">
        <a class="mc-product-card__media-link" href="{{ product.url }}" tabindex="-1" aria-hidden="true">
            <img class="mc-product-card__image" src="{{ product.image }}"{% if product.image_srcset %} srcset="{{ product.image_srcset }}"{% endif %} alt="{{ product.image_alt }}" loading="lazy"{% if product.image_width %} width="{{ product.image_width }}"{% endif %}{% if product.image_height %} height="{{ product.image_height }}"{% endif %}>
            {% if display.show_secondary_image and product.secondary_image %}
                <img class="mc-product-card__image mc-product-card__image--secondary" src="{{ product.secondary_image }}" alt="" loading="lazy"{% if product.image_width %} width="{{ product.image_width }}"{% endif %}{% if product.image_height %} height="{{ product.image_height }}"{% endif %}>
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
            {% if product.tax %}<span class="mc-price__tax">{{ text_tax }} {{ product.tax }}</span>{% endif %}
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
            data-mc-text-added="{{ text_added_to_cart }}"
            data-mc-text-add-error="{{ text_add_to_cart_error }}"
            {{ product.actions.add_to_cart ? '' : 'disabled' }}
        >
            {{ text_add_to_cart }}
        </button>

        {% if product.actions.quick_order and display.action_variant != 'compact' %}
            <button type="button" class="mc-button mc-button--secondary mc-product-card__quick-order" data-mc-action="quick-order" data-product-id="{{ product.id }}" data-product-name="{{ product.name }}">
                {{ text_quick_order }}
            </button>
        {% endif %}
    </div>

    {% if slots is defined and slots['product.after_actions'] %}
        <div class="mc-product-card__slot mc-product-card__slot--after-actions">{{ slots['product.after_actions']|raw }}</div>
    {% endif %}
</article>
```

`text_badge`, `text_wishlist`, `text_compare`, `text_rated`, `text_tax`,
`text_add_to_cart`, `text_quick_order`, `text_added_to_cart`,
`text_add_to_cart_error` are ordinary OpenCart language strings — the same
pattern every other `catalog/language/*` file already uses, not a new
i18n mechanism. The last two feed `data-mc-text-added`/
`data-mc-text-add-error` so `product-card.js`'s toast messages are
translated without hardcoding English in JS (every listing controller's
language file — category/search/special/manufacturer — carries the same
two keys, matching how the five product-card keys before them are already
duplicated per controller).

## Extension slot: `product.after_actions`

`MINTCART_TECHNICAL_PLAN.md` §15 names `product.after_actions` as one of
the Theme API's documented extension points — a place a third-party
extension can inject markup without OCMOD-patching this template. This is
the slot mechanism's first real wiring; see
`docs/developer/theme-api/slots.md` for the full `MintCart\Design\SlotRegistry`
contract (how a listener registers, what `$context` it receives, why no
listener exists in this codebase yet).

The template only renders the wrapper when `slots['product.after_actions']`
is a non-empty string — `slots` itself may not even be set (every call site
that doesn't pass a `SlotRegistry` to `ProductCardListRenderer` never adds
the key at all, per that class's own optional-3rd-argument contract), so
`slots is defined` is checked first to avoid a Twig "key does not exist"
notice on the themes/pages that don't use it.

## Behavior hooks (`CLAUDE.md` §14)

| Hook | Where | Behavior |
|---|---|---|
| `data-mc-action="toggle-wishlist"` | `.mc-product-card__action-button` | Toggles its own `aria-pressed`, dispatches bubbling `mc:product-card:wishlist-toggle` with `{ productId, active }`. **No network call** — see below. |
| `data-mc-action="toggle-compare"` | `.mc-product-card__action-button` | Same as wishlist, dispatches `mc:product-card:compare-toggle`. |
| `data-mc-action="add-to-cart"` | `.mc-product-card__add-to-cart` | Calls `MintCart\Commerce\CartService` via `cart-client.js`'s `addToCart()`; dispatches `mc:cart:updated` on success, navigates to the product page if options/a recurring plan are required, shows a toast either way. See below. |
| `data-mc-action="quick-order"` | `.mc-product-card__quick-order` | Opens a shared `mc-modal` (`components/quick-order.js`) collecting quantity/name/phone/email, submits to `MintCart\Commerce\QuickOrderService` via `quick-order-client.js`. See below and `docs/developer/commerce/quick-order-service.md`. |

### Why wishlist/compare toggle but add-to-cart does something real now

`initProductCardActions()`'s wishlist/compare handlers only flip a button's
pressed state and emit an event — they never call a backend. That's a
complete, self-contained UI behavior (same category as `mc-alert`'s dismiss
or `mc-quantity`'s stepper); persisting membership is a future
Wishlist/Compare service's job, not built yet.

**Add-to-cart is different as of Phase 15's first Commerce API
increment**: clicking `[data-mc-action="add-to-cart"]` calls
`MintCart\Commerce\CartService` via `../commerce/cart-client.js`'s
`addToCart()` (`docs/developer/commerce/cart-client.md`) — a real AJAX
call, a real cart mutation, a real `mc:cart:updated` dispatch, confirmed
end-to-end against a live install. The button shows `is-loading`
(`aria-busy="true"`, disabled) while the request is in flight, then either:

- a `createToast()` success toast (text from the button's own
  `data-mc-text-added` attribute — translated server-side, not hardcoded
  in JS), or
- if the product needs options this grid card has no picker for (or a
  required recurring plan), navigates to the product's own page instead —
  the same "can't answer it here, send them to the page that can"
  fallback `checkout/cart.php` itself uses for the same case, or
- a `createToast()` error toast on a network failure (`data-mc-text-add-error`).

**Quick Order is wired as of this increment too**, in its own
`components/quick-order.js` module rather than inline here — a modal-based
flow (quantity/name/phone/email), not a single click handler, so it doesn't
share `initAddToCart()`'s shape. Same required-option/recurring-plan
redirect fallback as add-to-cart. Full contract:
`docs/developer/commerce/quick-order-service.md`.

**Extended for the product detail page.** The same `[data-mc-action="add-to-cart"]`
handler in `product-card.js` now also supports a button that carries
`data-quantity-input="<id>"` (reads the current value of that `mc-quantity`
input instead of a static `data-quantity`) and a `[data-mc-product-form]`
ancestor containing `[data-mc-option-id]` fields (collected into the
`options` object `addToCart()` already accepted but no caller populated
until now). A grid card never sets either attribute, so its own behavior is
completely unchanged — this is additive, not a rewrite. See
`docs/developer/components/product-gallery.md` and `docs/upstream-diff.md`'s
`product.php` entry for the product page's own markup contract
(`data-mc-product-form`/`data-mc-product-options`/`data-mc-option-id`).
**Known gap:** a multi-select checkbox-type product option (`option[<id>][]`)
only keeps the *last* checked box's value — `addToCart()`'s `options` shape
is one value per option id, matching most real catalogs (select/radio/text
options are far more common than a required checkbox group), but a true
multi-value checkbox option isn't fully supported yet. A required
`file`-type option is also not collectable via `fetch()`/`URLSearchParams`
and is rendered without a `data-mc-option-id` hook — a product depending on
one falls back to the server's own "missing required option" redirect
(back to the same page) the same way an unanswered required option always
has.

## Image dimensions (layout-shift prevention)

Both `<img>` tags render `width`/`height` from `product.image_width`/
`product.image_height` when the provider supplies them — every listing
controller already knows this size (it's the same value passed to
`model_tool_image->resize()`), so the template only threads it through
rather than deriving anything. The browser can then reserve the image's
box before it loads, avoiding a layout shift (`MINTCART_FUNCTIONAL_SCOPE.md`
§19 Performance). Guarded with `{% if %}` rather than always rendering the
attribute, since a `null` value (a caller that doesn't supply one) would
otherwise emit an invalid `width=""`. See
`docs/developer/commerce/product-view-provider.md`'s "Image dimensions"
section for the full contract.

## Image srcset (responsive images)

The main `<img>` (not the secondary/hover image — no controller builds a
2x variant for it) also renders `srcset` from `product.image_srcset` when
supplied, `"<1x url> 1x, <2x url> 2x"` — same guarded-`{% if %}` shape as
`width`/`height` above, so a `null` value (no listing controller wired it,
or a hand-built fixture) omits the attribute rather than rendering
`srcset=""`. `product.image` still fills `src` as the fallback a browser
without `srcset` support (or one that picks the 1x descriptor) uses. See
`docs/developer/commerce/product-view-provider.md`'s "Image srcset
(responsive images)" section for where the string comes from.

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

- [x] Documented contract (this file + `docs/developer/commerce/product-view-provider.md` + `docs/developer/commerce/cart-service.md`/`cart-client.md`)
- [x] `mc-*` BEM markup
- [x] JS only where actually needed (wishlist/compare toggle state; add-to-cart and quick-order both call real Commerce API services — `CartService`/`QuickOrderService` — add-to-cart confirmed against a live install, quick-order not yet live-verified, see `docs/MANUAL_VERIFICATION.md`)
- [x] Colors/spacing/fonts read from `--mc-*` tokens
- [x] Keyboard usable (real `<a>`/`<button>` elements, focus-visible outlines, no keyboard trap)
- [x] Mobile checked (flexible media aspect-ratio, wrapping actions row, no fixed widths)
- [x] No Bootstrap 3 dependency
- [x] No OCMOD required
- [x] Ships a real Mint Default `.twig` file, resolvable via `ComponentRenderer`
- [x] Live controller wiring — `ControllerProductCategory`/`ControllerProductSearch`/`ControllerProductSpecial`/`ControllerProductManufacturer`/`ControllerProductProduct` (Related Products) all render it via `ProductViewProvider` + `ComponentRenderer`
- [x] Theme override checked — `catalog/view/theme/test_theme/` overrides only `product-card.twig` with a genuinely different DOM; `ComponentRenderer` resolves it correctly in both directions (override active vs. Mint Default active), see `docs/adr/002-theme-api-milestone-proof.md`. Resolution is proven; a human still needs to confirm the override actually **renders** correctly on a real install (no MySQL/`vendor/twig/twig` in this environment — same gap as every earlier Phase 8 entry)
