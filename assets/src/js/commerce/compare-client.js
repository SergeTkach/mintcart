/**
 * MintCart Compare client - JS half of `ControllerMintcartCompare`
 * (`route=mintcart/compare/*`). Same shape and `mc:compare:updated` event as
 * wishlist-client.js; pure data layer.
 */

async function request(route, data) {
    const options = { headers: { 'X-Requested-With': 'XMLHttpRequest' } };

    if (data) {
        options.method = 'POST';
        options.body = new URLSearchParams(data);
    }

    const response = await fetch(`index.php?route=${route}`, options);
    const json = await response.json();

    if (!json.error) {
        document.dispatchEvent(new CustomEvent('mc:compare:updated', { bubbles: true, detail: json }));
    }

    return json;
}

export const addToCompare = (productId) => request('mintcart/compare/add', { product_id: productId });
export const removeFromCompare = (productId) => request('mintcart/compare/remove', { product_id: productId });
export const refreshCompare = () => request('mintcart/compare/refresh');
