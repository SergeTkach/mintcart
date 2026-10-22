/**
 * Sticky buy controls on the product page. On phones: the bottom bar
 * (`[data-mc-buy-bar]`, buy-bar.css), shown while the main add-to-cart button
 * is scrolled above the viewport. On desktop: the buy box beside the lower
 * sections (`[data-mc-buy-box]`, always sticky, CSS only). Both buttons carry
 * `data-mc-action="buy-bar-add"` and click the main button, so options,
 * quantity, the confirmation and missing-option marking are handled once, by
 * product-card.js.
 */
export function initBuyBar(root = document) {
    const bar = root.querySelector('[data-mc-buy-bar]');
    const main = root.querySelector('[data-mc-product-form] [data-mc-action="add-to-cart"]');

    if (!main) {
        return;
    }

    root.addEventListener('click', (event) => {
        if (event.target.closest('[data-mc-action="buy-bar-add"]')) {
            main.click();
        }
    });

    if (!bar) {
        return;
    }

    // Plain scroll/resize check rather than an IntersectionObserver: it is
    // deterministic, and only one thing matters - the button has gone above
    // the viewport (not merely below the fold on first load). Browsers
    // already deliver scroll events at most once per frame, and the check
    // is a single getBoundingClientRect().
    const update = () => {
        bar.hidden = main.getBoundingClientRect().bottom > 0;
    };

    window.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);
    update();
}
