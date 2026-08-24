/**
 * Refreshes the legacy header cart widget (`#cart`, stock `default` theme's
 * `common/cart.twig`, embedded as-is inside `mint_default`'s header via the
 * `mc-legacy` wrapper - see `docs/developer/components/header.md` "Why
 * widgets stay legacy fragments") whenever `mc:cart:updated` fires
 * (`docs/developer/commerce/cart-client.md`).
 *
 * Deliberately does NOT touch any PHP: re-fetches the *existing*,
 * unmodified `route=common/cart/info` endpoint (the same one legacy
 * `catalog/view/javascript/common.js` already calls after its own
 * jQuery-driven `cart.add()`/`cart.remove()`) and swaps `#cart`'s content
 * with the fresh render. This keeps the widget correct after an add/
 * remove/update that went through the new Cart client
 * (`mc-product-card`'s Add to Cart, Phase 15) instead of the legacy
 * jQuery path - those two paths would otherwise fall out of sync, since
 * the legacy widget only auto-refreshes itself after its own AJAX calls.
 *
 * A full Mint-native header cart component (reading `mc:cart:updated`'s
 * `detail` directly instead of doing a second fetch) is a documented
 * follow-up, not this: `docs/IMPLEMENTATION_STATUS.md` Phase 15's
 * "Explicitly deferred" list.
 */

export function initHeaderCartRefresh(root = document) {
    const cart = root.querySelector('#cart');

    if (!cart) {
        return;
    }

    document.addEventListener('mc:cart:updated', async () => {
        try {
            const response = await fetch('index.php?route=common/cart/info', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            const html = await response.text();
            const fresh = new DOMParser().parseFromString(html, 'text/html').querySelector('#cart');

            if (fresh) {
                cart.innerHTML = fresh.innerHTML;
            }
        } catch {
            // Best-effort refresh - a failed re-fetch leaves the widget
            // showing its last-known state rather than breaking the page;
            // the cart itself was already mutated server-side regardless.
        }
    });
}
