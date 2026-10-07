/**
 * MintCart Checkout client - the JS half of the Mint single-page checkout,
 * talking to `ControllerMintcartCheckout` (`route=mintcart/checkout/*`,
 * `upload/catalog/controller/mintcart/checkout.php`).
 *
 * Pure data layer, same shape as cart-client.js: no DOM work of its own.
 * Every call resolves to the endpoint's JSON. After a successful
 * `updateCheckout()` it dispatches `mc:checkout:updated` on `document`
 * (`detail` = the state snapshot) so other components - or an extension's
 * script - can react to recalculated delivery/payment/totals without
 * patching checkout.js.
 *
 * Contract: docs/developer/commerce/checkout-service.md
 */

const HEADERS = { 'X-Requested-With': 'XMLHttpRequest' };

async function post(route, formData, signal) {
    const response = await fetch(`index.php?route=${route}`, {
        method: 'POST',
        headers: HEADERS,
        body: new URLSearchParams(formData),
        signal,
    });

    return response.json();
}

export async function fetchCheckoutState() {
    const response = await fetch('index.php?route=mintcart/checkout/state', { headers: HEADERS });

    return response.json();
}

/**
 * Persists the (possibly incomplete) form and returns the recalculated
 * state. Pass an AbortSignal so a newer edit can cancel a stale request.
 *
 * @param {FormData} formData
 * @param {AbortSignal} [signal]
 */
export async function updateCheckout(formData, signal) {
    const json = await post('mintcart/checkout/update', formData, signal);

    if (!json.redirect) {
        document.dispatchEvent(new CustomEvent('mc:checkout:updated', { bubbles: true, detail: json }));
    }

    return json;
}

/**
 * Validates and creates the order. Resolves to `{ errors, state }`,
 * `{ redirect }` or `{ payment_html }` (the chosen payment extension's own
 * confirm markup).
 *
 * @param {FormData} formData
 */
export async function confirmCheckout(formData) {
    return post('mintcart/checkout/confirm', formData);
}
