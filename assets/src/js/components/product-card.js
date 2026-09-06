/**
 * mc-product-card wishlist/compare toggle + add-to-cart behavior. Also the
 * add-to-cart behavior for the product detail page
 * (`mint_default/template/product/product.twig`) - same
 * `[data-mc-action="add-to-cart"]` hook, so a picked option/typed quantity
 * on that page is collected the same way a card's static
 * `data-quantity`/no-options case already was, see `readQuantity()`/
 * `readOptions()` below.
 *
 * Behavior hooks (CLAUDE.md §14): `[data-mc-action="toggle-wishlist"]` /
 * `[data-mc-action="toggle-compare"]` / `[data-mc-action="add-to-cart"]`
 * inside `.mc-product-card` or `[data-mc-product-form]`. Delegated on
 * `document` so cards/forms rendered after page load (e.g. a listing loaded
 * via fetch()) work without re-wiring, same approach as mc-alert/mc-quantity.
 *
 * Wishlist/compare only flip the button's own pressed/visual state and
 * dispatch a bubbling `CustomEvent` carrying the product id - they do not
 * call any backend. Persisting wishlist/compare membership is Commerce API
 * territory (`MintCart\Commerce\*`, Phase 15, MINTCART_TECHNICAL_PLAN.md
 * §24/§27) which doesn't exist yet for those two; a future Wishlist/Compare
 * service listens for `mc:product-card:wishlist-toggle` /
 * `mc:product-card:compare-toggle` on `document` instead of this module
 * reaching into a network call ahead of a real consumer (CLAUDE.md §85).
 *
 * `add-to-cart` calls `MintCart\Commerce\CartService` via
 * `../commerce/cart-client.js`'s `addToCart()` - the first real Phase 15
 * Commerce API consumer. A card needing options it can't offer a picker for
 * (no option UI on a grid card) gets redirected to its full product page
 * instead, same fallback `checkout/cart.php` itself uses when options are
 * missing; the product page itself has no such fallback to redirect to, so
 * it always passes any collected `option[<id>]` values along. `quick-order`
 * stays intentionally NOT wired here - separate `QuickOrderService`, not
 * built yet.
 *
 * Contract: docs/developer/components/product-card.md
 */

import { addToCart } from '../commerce/cart-client.js';
import { createToast, showToast } from './toast.js';

function toggleAction(root, actionName, eventName) {
    root.addEventListener('click', (event) => {
        const button = event.target.closest(`[data-mc-action="${actionName}"]`);

        if (!button) {
            return;
        }

        const card = button.closest('.mc-product-card');
        const pressed = button.getAttribute('aria-pressed') === 'true';

        button.setAttribute('aria-pressed', String(!pressed));

        (card || button).dispatchEvent(
            new CustomEvent(eventName, {
                bubbles: true,
                detail: {
                    productId: button.dataset.productId ?? null,
                    active: !pressed,
                },
            })
        );
    });
}

/**
 * A quantity typed into a linked `<input>` (the product page's `mc-quantity`
 * stepper) takes priority over the button's own static `data-quantity` -
 * cards have no such input and keep using the static attribute, this only
 * changes behavior where `data-quantity-input` is actually present.
 */
function readQuantity(button) {
    const inputId = button.dataset.quantityInput;
    const input = inputId ? document.getElementById(inputId) : null;

    if (input) {
        return Number(input.value) || 1;
    }

    return Number(button.dataset.quantity) || 1;
}

/**
 * Product options this card can't offer a picker for don't apply here -
 * `.mc-product-card` never renders `[data-mc-product-options]`. The product
 * page (`mint_default/template/product/product.twig`) does: each option
 * field carries `data-mc-option-id`, matching `option[<id>]`/`option[<id>][]`
 * naming so the same markup still works if JS never runs. A later checkbox
 * value overwrites an earlier one for the same option id - multi-select
 * checkbox options aren't fully supported by `addToCart()`'s single-value
 * `option` shape yet (`docs/developer/commerce/cart-client.md`).
 */
function readOptions(button) {
    const options = {};
    const container = button.closest('[data-mc-product-form]')?.querySelector('[data-mc-product-options]');

    if (!container) {
        return options;
    }

    container.querySelectorAll('[data-mc-option-id]').forEach((field) => {
        if ((field.type === 'checkbox' || field.type === 'radio') && !field.checked) {
            return;
        }

        if (field.type === 'file' || field.value === '') {
            return;
        }

        options[field.dataset.optionId] = field.value;
    });

    return options;
}

function initAddToCart(root) {
    root.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-mc-action="add-to-cart"]');

        if (!button || button.disabled) {
            return;
        }

        const productId = button.dataset.productId;
        const quantity = readQuantity(button);
        const options = readOptions(button);
        const addedText = button.dataset.mcTextAdded || 'Added to cart';
        const errorText = button.dataset.mcTextAddError || 'Could not add this product to your cart';

        button.disabled = true;
        button.classList.add('is-loading');
        button.setAttribute('aria-busy', 'true');

        try {
            const result = await addToCart(productId, quantity, options);

            if (result.redirected) {
                window.location.href = result.url;
                return;
            }

            showToast(createToast({ title: addedText, message: addedText, variant: 'success' }));
        } catch {
            showToast(createToast({ title: errorText, message: errorText, variant: 'danger' }));
        } finally {
            button.disabled = false;
            button.classList.remove('is-loading');
            button.removeAttribute('aria-busy');
        }
    });
}

export function initProductCardActions(root = document) {
    toggleAction(root, 'toggle-wishlist', 'mc:product-card:wishlist-toggle');
    toggleAction(root, 'toggle-compare', 'mc:product-card:compare-toggle');
    initAddToCart(root);
}
