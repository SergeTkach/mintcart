/**
 * MintCart Quick Order client (MINTCART_TECHNICAL_PLAN.md §28,
 * MINTCART_IMPLEMENTATION_GUIDE.md §60) - the JS half of the Quick Order
 * API, talking to `ControllerMintcartQuickorder`
 * (`route=mintcart/quickorder/submit`,
 * `upload/catalog/controller/mintcart/quickorder.php`).
 *
 * Deliberately a pure data layer - no DOM manipulation, no toast/modal calls
 * of its own - same split `cart-client.js` already established. A missing
 * required option/recurring plan resolves to `{ redirected: true, url }`,
 * the same shape `addToCart()` returns for the same reason (a listing card
 * has no option picker); field-level validation failures resolve to
 * `{ errors: { field: message } }`; success resolves to
 * `{ success: true, message }`.
 *
 * Contract: docs/developer/commerce/quick-order-service.md
 */

/**
 * @param {string|number} productId
 * @param {{quantity?: number, name: string, telephone?: string, email?: string}} fields
 * @returns {Promise<{redirected: true, url: string}|{errors: Record<string, string>}|{success: true, message: string}>}
 */
export async function submitQuickOrder(productId, fields) {
    const body = new URLSearchParams();

    body.set('product_id', productId);
    body.set('quantity', fields.quantity ?? 1);
    body.set('name', fields.name ?? '');
    body.set('telephone', fields.telephone ?? '');
    body.set('email', fields.email ?? '');

    const response = await fetch('index.php?route=mintcart/quickorder/submit', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body,
    });

    const json = await response.json();

    if (json.redirect) {
        return { redirected: true, url: json.redirect };
    }

    return json;
}
