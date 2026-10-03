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
 * Compare only flips the button's own pressed/visual state and dispatches a
 * bubbling `CustomEvent` carrying the product id - it does not call any
 * backend. Persisting compare membership is Commerce API territory
 * (`MintCart\Commerce\*`, Phase 15, MINTCART_TECHNICAL_PLAN.md §24/§27)
 * which doesn't exist yet; a future Compare service would listen for
 * `mc:product-card:compare-toggle` on `document` instead of this module
 * reaching into a network call ahead of a real consumer (CLAUDE.md §85).
 *
 * `toggle-wishlist` calls `MintCart\Commerce\WishlistService` via
 * `../commerce/wishlist-client.js`'s `addToWishlist()`/`removeFromWishlist()`
 * - membership now actually persists (logged-in customer: DB via
 * `ModelAccountWishlist`; guest: session), same "real backend, not just a
 * visual toggle" upgrade `add-to-cart` already got. See
 * `docs/developer/commerce/wishlist-service.md`.
 *
 * `add-to-cart` calls `MintCart\Commerce\CartService` via
 * `../commerce/cart-client.js`'s `addToCart()` - the first real Phase 15
 * Commerce API consumer. A card needing options it can't offer a picker for
 * (no option UI on a grid card) gets redirected to its full product page
 * instead, same fallback `checkout/cart.php` itself uses when options are
 * missing; the product page itself has no such fallback to redirect to, so
 * it always passes any collected `option[<id>]` values along. `quick-order`
 * is wired separately, in its own `./quick-order.js` module
 * (`initQuickOrderActions()`) - a modal-based flow, not a single click
 * handler, so it doesn't belong in this file.
 *
 * Contract: docs/developer/components/product-card.md
 */

import { addToCart } from '../commerce/cart-client.js';
import { addToWishlist, removeFromWishlist } from '../commerce/wishlist-client.js';
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

/**
 * Optimistically flips the button's `aria-pressed` and dispatches
 * `mc:product-card:wishlist-toggle` exactly like the old UI-only handler
 * did, then persists the change via `wishlist-client.js`. A failed request
 * reverts the button's visual state and shows an error toast - the
 * dispatched event is not un-dispatched, since any listener reacting to it
 * (none exist yet) would need to handle a failed mutation the same way this
 * module does.
 */
function initWishlistToggle(root) {
    root.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-mc-action="toggle-wishlist"]');

        if (!button || button.disabled) {
            return;
        }

        const card = button.closest('.mc-product-card');
        const productId = button.dataset.productId ?? null;
        const wasPressed = button.getAttribute('aria-pressed') === 'true';
        const nextActive = !wasPressed;
        const errorText = button.dataset.mcTextWishlistError || 'Could not update your wishlist';

        button.setAttribute('aria-pressed', String(nextActive));
        button.disabled = true;

        (card || button).dispatchEvent(
            new CustomEvent('mc:product-card:wishlist-toggle', {
                bubbles: true,
                detail: { productId, active: nextActive },
            })
        );

        try {
            const result = nextActive ? await addToWishlist(productId) : await removeFromWishlist(productId);

            if (result.error) {
                throw new Error(result.error);
            }
        } catch {
            button.setAttribute('aria-pressed', String(wasPressed));
            showToast(createToast({ title: errorText, message: errorText, variant: 'danger' }));
        } finally {
            button.disabled = false;
        }
    });
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
    initWishlistToggle(root);
    toggleAction(root, 'toggle-compare', 'mc:product-card:compare-toggle');
    initAddToCart(root);
}
