/**
 * mc-product-card wishlist/compare toggle behavior.
 *
 * Behavior hooks (CLAUDE.md §14): `[data-mc-action="toggle-wishlist"]` /
 * `[data-mc-action="toggle-compare"]` inside `.mc-product-card`. Delegated
 * on `document` so cards rendered after page load (e.g. a listing loaded
 * via fetch()) work without re-wiring, same approach as mc-alert/mc-quantity.
 *
 * This only flips the button's own pressed/visual state and dispatches a
 * bubbling `CustomEvent` carrying the product id - it does not call any
 * backend. Persisting wishlist/compare membership is Commerce API territory
 * (`MintCart\Commerce\*`, Phase 15, MINTCART_TECHNICAL_PLAN.md §24/§27) which
 * doesn't exist yet; a future Wishlist/Compare service listens for
 * `mc:product-card:wishlist-toggle` / `mc:product-card:compare-toggle` on
 * `document` instead of this module reaching into a network call ahead of a
 * real consumer (CLAUDE.md §85).
 *
 * `add-to-cart` / `quick-order` buttons are intentionally NOT wired here for
 * the same reason - they stay markup + `data-mc-action` hooks for the future
 * Cart/QuickOrder client (Phase 15) to attach to.
 *
 * Contract: docs/developer/components/product-card.md
 */

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

export function initProductCardActions(root = document) {
    toggleAction(root, 'toggle-wishlist', 'mc:product-card:wishlist-toggle');
    toggleAction(root, 'toggle-compare', 'mc:product-card:compare-toggle');
}
