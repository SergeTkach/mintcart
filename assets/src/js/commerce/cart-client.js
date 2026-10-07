/**
 * MintCart Cart client (MINTCART_TECHNICAL_PLAN.md §25,
 * MINTCART_IMPLEMENTATION_GUIDE.md §58) - the JS half of the Cart API,
 * talking to `ControllerMintcartCart` (`route=mintcart/cart/*`,
 * `upload/catalog/controller/mintcart/cart.php`).
 *
 * `add()`/`remove()`/`update()`/`refresh()`, each dispatching a bubbling
 * `mc:cart:updated` `CustomEvent` on `document` after a successful
 * mutation - `detail` is the normalized `MintCart\Commerce\CartService`
 * snapshot (`{ count, subtotal, total, items[], totals[] }`), the single
 * event contract header cart / cart drawer / product-card / product page
 * are all meant to react to (Technical Plan §25's "same event contract").
 *
 * Deliberately a pure data layer - no DOM manipulation, no toast/UI calls
 * of its own. `add()`'s option-required case does not throw and does not
 * dispatch `mc:cart:updated` (nothing changed); it resolves to
 * `{ redirected: true, url }` so the caller decides what "redirect" means
 * for its own context (a listing page's Add to Cart button navigates
 * there; a future product-page Add to Cart, which already has options
 * selected, should never hit this case at all).
 *
 * Contract: docs/developer/commerce/cart-client.md
 */

async function postForm(route, data) {
    const body = new URLSearchParams();

    for (const [key, value] of Object.entries(data)) {
        if (Array.isArray(value)) {
            value.forEach((item) => body.append(`${key}[]`, item));
        } else {
            body.set(key, value);
        }
    }

    const response = await fetch(`index.php?route=${route}`, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body,
    });

    return response.json();
}

async function getSnapshot(route) {
    const response = await fetch(`index.php?route=${route}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
    });

    return response.json();
}

function dispatchCartUpdated(snapshot) {
    document.dispatchEvent(new CustomEvent('mc:cart:updated', { bubbles: true, detail: snapshot }));
}

/**
 * @param {string|number} productId
 * @param {number} quantity
 * @param {Record<string, string|string[]>} [options] `product_option_id` ->
 *        value (an array for a checkbox option -> `option[id][]`),
 *        same shape `checkout/cart.php`'s own `add()` accepts. Omit for a
 *        product with no options.
 * @returns {Promise<{redirected: true, url: string}|Record<string, unknown>>}
 *          The snapshot on success, or `{ redirected: true, url }` if the
 *          product needs options this caller didn't supply.
 */
export async function addToCart(productId, quantity = 1, options = {}) {
    const payload = { product_id: productId, quantity };

    for (const [optionId, value] of Object.entries(options)) {
        payload[`option[${optionId}]`] = value;
    }

    const json = await postForm('mintcart/cart/add', payload);

    if (json.redirect) {
        return { redirected: true, url: json.redirect };
    }

    dispatchCartUpdated(json);

    return json;
}

/**
 * @param {string|number} cartId
 */
export async function removeFromCart(cartId) {
    const json = await postForm('mintcart/cart/remove', { key: cartId });

    dispatchCartUpdated(json);

    return json;
}

/**
 * @param {string|number} cartId
 * @param {number} quantity
 */
export async function updateCart(cartId, quantity) {
    const json = await postForm('mintcart/cart/update', { key: cartId, quantity });

    dispatchCartUpdated(json);

    return json;
}

export async function refreshCart() {
    const json = await getSnapshot('mintcart/cart/refresh');

    dispatchCartUpdated(json);

    return json;
}
