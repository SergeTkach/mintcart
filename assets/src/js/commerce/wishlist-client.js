/**
 * MintCart Wishlist client (MINTCART_FUNCTIONAL_SCOPE.md §11,
 * MINTCART_TECHNICAL_PLAN.md §24) - the JS half of the Wishlist API,
 * talking to `ControllerMintcartWishlist` (`route=mintcart/wishlist/*`,
 * `upload/catalog/controller/mintcart/wishlist.php`).
 *
 * `addToWishlist()`/`removeFromWishlist()`/`refreshWishlist()`, each
 * dispatching a bubbling `mc:wishlist:updated` `CustomEvent` on `document`
 * after a successful call - `detail` is the normalized
 * `MintCart\Commerce\WishlistService` snapshot
 * (`{ count, product_ids, active }`), same event-contract shape
 * `cart-client.js` already established for `mc:cart:updated`.
 *
 * Deliberately a pure data layer - no DOM manipulation, no toast calls of
 * its own, same split every other `commerce/*-client.js` module uses.
 *
 * Contract: docs/developer/commerce/wishlist-service.md
 */

async function postForm(route, data) {
    const body = new URLSearchParams();

    for (const [key, value] of Object.entries(data)) {
        body.set(key, value);
    }

    const response = await fetch(`index.php?route=${route}`, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body,
    });

    return response.json();
}

function dispatchWishlistUpdated(snapshot) {
    document.dispatchEvent(new CustomEvent('mc:wishlist:updated', { bubbles: true, detail: snapshot }));
}

/**
 * @param {string|number} productId
 * @returns {Promise<{count: number, product_ids: number[], active: boolean|null}|{error: string}>}
 */
export async function addToWishlist(productId) {
    const json = await postForm('mintcart/wishlist/add', { product_id: productId });

    if (!json.error) {
        dispatchWishlistUpdated(json);
    }

    return json;
}

/**
 * @param {string|number} productId
 */
export async function removeFromWishlist(productId) {
    const json = await postForm('mintcart/wishlist/remove', { product_id: productId });

    if (!json.error) {
        dispatchWishlistUpdated(json);
    }

    return json;
}

export async function refreshWishlist() {
    const response = await fetch('index.php?route=mintcart/wishlist/refresh', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
    });

    const json = await response.json();

    dispatchWishlistUpdated(json);

    return json;
}
