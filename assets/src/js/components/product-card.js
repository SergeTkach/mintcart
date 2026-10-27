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
 * A successful `add-to-cart` opens the "Added to cart" modal (`cart-added-modal.js`);
 * errors stay toasts.
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
import { addToCompare, removeFromCompare, refreshCompare } from '../commerce/compare-client.js';
import { refreshWishlist } from '../commerce/wishlist-client.js';
import { showActionNote } from './action-note.js';
import { createToast, showToast } from './toast.js';
import { showCartAdded } from './cart-added-modal.js';

function initCompareToggle(root) {
    root.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-mc-action="toggle-compare"]');

        if (!button || button.disabled) {
            return;
        }

        const card = button.closest('.mc-product-card');
        const productId = button.dataset.productId ?? null;
        const wasPressed = button.getAttribute('aria-pressed') === 'true';
        const errorText = button.dataset.mcTextCompareError || 'Could not update comparison';

        button.setAttribute('aria-pressed', String(!wasPressed));
        button.disabled = true;

        (card || button).dispatchEvent(
            new CustomEvent('mc:product-card:compare-toggle', {
                bubbles: true,
                detail: { productId, active: !wasPressed },
            })
        );

        try {
            const result = wasPressed ? await removeFromCompare(productId) : await addToCompare(productId);

            if (result.error) {
                throw new Error(result.error);
            }

            showActionNote(button, { message: result.message, linkText: result.view_text, linkUrl: result.view_url });
        } catch {
            button.setAttribute('aria-pressed', String(wasPressed));
            showToast(createToast({ title: errorText, message: errorText, variant: 'danger' }));
        } finally {
            button.disabled = false;
        }
    });
}

/**
 * Cards are rendered with aria-pressed="false"; the server knows what is in
 * the wishlist / comparison. Mark the pressed ones on load and whenever the
 * lists change (also for cards added to the page later).
 */
function syncPressed(action, ids) {
    const set = new Set((ids || []).map(String));

    document.querySelectorAll(`[data-mc-action="${action}"]`).forEach((button) => {
        button.setAttribute('aria-pressed', String(set.has(String(button.dataset.productId))));
    });
}

function initPressedSync() {
    document.addEventListener('mc:wishlist:updated', (event) => syncPressed('toggle-wishlist', event.detail.product_ids));
    document.addEventListener('mc:compare:updated', (event) => syncPressed('toggle-compare', event.detail.product_ids));

    if (document.querySelector('[data-mc-action="toggle-wishlist"]')) {
        refreshWishlist().catch(() => {});
    }

    if (document.querySelector('[data-mc-action="toggle-compare"]')) {
        refreshCompare().catch(() => {});
    }
}

/**
 * A quantity typed into a linked `<input>` (the product page's `mc-quantity`
 * stepper) takes priority over the button's own static `data-quantity` -
 * cards have no such input and keep using the static attribute, this only
 * changes behavior where `data-quantity-input` is actually present.
 */
export function readQuantity(button) {
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
export function readOptions(button) {
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

        const optionId = field.dataset.mcOptionId;

        // A checkbox option can have several values (`option[id][]`).
        if (field.type === 'checkbox') {
            (options[optionId] ||= []).push(field.value);
            return;
        }

        options[optionId] = field.value;
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

            showActionNote(button, { message: result.message, linkText: result.view_text, linkUrl: result.view_url });
        } catch {
            button.setAttribute('aria-pressed', String(wasPressed));
            showToast(createToast({ title: errorText, message: errorText, variant: 'danger' }));
        } finally {
            button.disabled = false;
        }
    });
}

/**
 * Flags each missing required option's `.mc-field` with `has-error` /
 * `aria-invalid` and focuses the first one; clears flags on the others.
 * A flag is also cleared as soon as that option is changed.
 */
function markMissingOptions(form, missingIds) {
    const missing = new Set(missingIds);
    let first = null;

    form.querySelectorAll('[data-mc-product-options] [data-mc-option-id]').forEach((field) => {
        const wrapper = field.closest('.mc-field');
        const isMissing = missing.has(field.dataset.mcOptionId);

        if (wrapper) {
            wrapper.classList.toggle('has-error', isMissing);
        }

        if (field.type !== 'hidden') {
            field.toggleAttribute('aria-invalid', isMissing);
        }

        if (isMissing && !first) {
            first = field.type === 'hidden' ? wrapper?.querySelector('[data-mc-option-upload]') : field;
        }
    });

    // Centre it explicitly: plain focus() scrolls the minimum, which can
    // leave the field under the sticky header (or not scroll at all when
    // triggered from the sticky buy bar).
    if (first) {
        (first.closest('.mc-field') || first).scrollIntoView({ block: 'center', behavior: 'smooth' });
        first.focus({ preventScroll: true });
    }
}

function initOptionErrorReset(root) {
    root.addEventListener('change', (event) => {
        const field = event.target.closest('[data-mc-product-options] [data-mc-option-id], [data-mc-option-upload]');
        const wrapper = field?.closest('.mc-field.has-error');

        if (wrapper) {
            wrapper.classList.remove('has-error');
            wrapper.querySelectorAll('[aria-invalid]').forEach((el) => el.removeAttribute('aria-invalid'));
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
                // On the product page the options are right here - say what is
                // missing instead of "redirecting" to the same page. A grid card
                // has no option UI, so it still goes to the product page.
                const form = button.closest('[data-mc-product-form]');

                if (form) {
                    markMissingOptions(form, result.missingOptions);
                    showToast(createToast({ title: errorText, message: result.error || errorText, variant: 'danger' }));
                } else {
                    window.location.href = result.url;
                }

                return;
            }

            const form = button.closest('[data-mc-product-form]');

            if (form) {
                markMissingOptions(form, []);
            }

            // A confirmation with a next step (keep shopping / go to the cart), not a corner toast.
            showCartAdded(button);
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
    initCompareToggle(root);
    initPressedSync();
    initAddToCart(root);
    initOptionErrorReset(root);
}
