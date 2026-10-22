/**
 * "Added to cart" confirmation: a modal with the product (picture + name) and
 * two choices - keep shopping, or go to the cart.
 *
 * Replaces the corner toast for a successful add-to-cart (a toast in the
 * bottom corner is easy to miss and gives no next step). Errors still use
 * toasts. One `mc-modal` is built on first use and reused, same approach as
 * `quick-order.js`. Closing goes through the shared
 * `[data-mc-action="close-modal"]` handler in `modal.js`.
 *
 * Called from `product-card.js` after `addToCart()` succeeds, for every
 * theme: the markup lives here, themes only restyle `.mc-modal` through
 * tokens. Texts come from the button's data attributes (translated
 * server-side): `data-mc-text-added` (title), `data-mc-text-continue`,
 * `data-mc-text-view-cart`, `data-mc-cart-url`.
 */

import { Modal } from 'bootstrap';

const MODAL_ID = 'mc-cart-added-modal';

function build() {
    const modal = document.createElement('div');

    modal.className = 'modal mc-modal fade';
    modal.id = MODAL_ID;
    modal.tabIndex = -1;
    modal.setAttribute('aria-labelledby', `${MODAL_ID}-title`);
    modal.setAttribute('aria-hidden', 'true');

    modal.innerHTML = `
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <p class="modal-title mc-modal__title" id="${MODAL_ID}-title"></p>
                    <button type="button" class="mc-modal__close" data-mc-action="close-modal" aria-label="Close">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="mc-cart-added">
                        <img class="mc-cart-added__image" alt="" width="72" height="72" hidden>
                        <p class="mc-cart-added__name"></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="mc-button mc-button--secondary" data-mc-action="close-modal"></button>
                    <a class="mc-button mc-button--primary" href="#"></a>
                </div>
            </div>
        </div>
    `;

    document.body.appendChild(modal);

    return modal;
}

/** The product picture next to the clicked button: a card image or the product page's main image. */
function findImage(button) {
    const scope = button.closest('.mc-product-card') || button.closest('[data-mc-product-form]')?.closest('main, .container') || document;
    const img = scope.querySelector('.mc-product-card__image, .mc-product-gallery__main, .mc-product-card img');

    return img?.currentSrc || img?.src || '';
}

export function showCartAdded(button) {
    const { productName, mcTextAdded, mcTextContinue, mcTextViewCart, mcCartUrl } = button.dataset;
    const modal = document.getElementById(MODAL_ID) || build();

    modal.querySelector('.mc-modal__title').textContent = mcTextAdded || 'Added to cart';
    modal.querySelector('.mc-cart-added__name').textContent = productName || '';

    const image = modal.querySelector('.mc-cart-added__image');
    const src = findImage(button);

    image.hidden = !src;

    if (src) {
        image.src = src;
    }

    modal.querySelector('.modal-footer button').textContent = mcTextContinue || 'Continue shopping';

    const link = modal.querySelector('.modal-footer a');

    link.textContent = mcTextViewCart || 'View cart';
    link.href = mcCartUrl || '#';
    link.hidden = !mcCartUrl;

    Modal.getOrCreateInstance(modal).show();
}
