/**
 * Sticky buy bar on the product page (`[data-mc-buy-bar]`, buy-bar.css).
 * Shown while the main add-to-cart button is scrolled above the viewport;
 * its button clicks that main button, so options, quantity, toasts and
 * missing-option marking are handled once, by product-card.js.
 */
export function initBuyBar(root = document) {
    const bar = root.querySelector('[data-mc-buy-bar]');
    const main = root.querySelector('[data-mc-product-form] [data-mc-action="add-to-cart"]');

    if (!bar || !main) {
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

    bar.addEventListener('click', (event) => {
        if (event.target.closest('[data-mc-action="buy-bar-add"]')) {
            main.click();
        }
    });
}
