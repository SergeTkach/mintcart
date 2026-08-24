/**
 * mc-product-card wishlist/compare toggle + add-to-cart behavior.
 *
 * Behavior hooks (CLAUDE.md §14): `[data-mc-action="toggle-wishlist"]` /
 * `[data-mc-action="toggle-compare"]` / `[data-mc-action="add-to-cart"]`
 * inside `.mc-product-card`. Delegated on `document` so cards rendered
 * after page load (e.g. a listing loaded via fetch()) work without
 * re-wiring, same approach as mc-alert/mc-quantity.
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
 * Commerce API consumer. A product needing options this card can't offer a
 * picker for (no option UI on a grid card) gets redirected to its full
 * product page instead, same fallback `checkout/cart.php` itself uses when
 * options are missing. `quick-order` stays intentionally NOT wired here -
 * separate `QuickOrderService`, not built yet.
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

function initAddToCart(root) {
    root.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-mc-action="add-to-cart"]');

        if (!button || button.disabled) {
            return;
        }

        const productId = button.dataset.productId;
        const quantity = Number(button.dataset.quantity) || 1;
        const addedText = button.dataset.mcTextAdded || 'Added to cart';
        const errorText = button.dataset.mcTextAddError || 'Could not add this product to your cart';

        button.disabled = true;
        button.classList.add('is-loading');
        button.setAttribute('aria-busy', 'true');

        try {
            const result = await addToCart(productId, quantity);

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
