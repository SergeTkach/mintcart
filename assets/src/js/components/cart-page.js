/**
 * Shopping cart page (`[data-mc-cart-page]`, mint_default checkout/cart.twig).
 *
 * - A changed quantity submits the stock cart form (checkout/cart/edit),
 *   which recalculates totals and the extension blocks server-side.
 * - Remove goes through the Mint cart client, then the page reloads so
 *   totals/modules match.
 */
import { removeFromCart } from '../commerce/cart-client.js';

export function initCartPage(root = document) {
    const page = root.querySelector('[data-mc-cart-page]');

    if (!page) {
        return;
    }

    const form = page.querySelector('[data-mc-cart-form]');
    let timer = 0;

    page.addEventListener('change', (event) => {
        if (!form || !event.target.matches('.mc-quantity__input')) {
            return;
        }

        // Stepper clicks fire one change each - wait for the last one.
        clearTimeout(timer);
        timer = setTimeout(() => {
            event.target.closest('.mc-cart-page__item')?.classList.add('is-loading');
            form.requestSubmit();
        }, 600);
    });

    page.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-mc-action="remove-cart-item"]');

        if (!button || button.disabled) {
            return;
        }

        button.disabled = true;
        button.closest('.mc-cart-page__item')?.classList.add('is-loading');

        try {
            await removeFromCart(button.dataset.cartId);
            window.location.reload();
        } catch {
            button.disabled = false;
            button.closest('.mc-cart-page__item')?.classList.remove('is-loading');
        }
    });
}
