/**
 * Keeps the header's wishlist / compare counts in step with the lists
 * (`mc:wishlist:updated` / `mc:compare:updated`, see commerce/*-client.js).
 * The wishlist label comes from the language file as "Wish List (3)", so only
 * its number is swapped; the compare label is "<label> (N)", N hidden at 0.
 */
export function initHeaderCounts() {
    document.addEventListener('mc:wishlist:updated', (event) => {
        document.querySelectorAll('.mc-header__wishlist').forEach((link) => {
            link.textContent = link.textContent.replace(/\(\d+\)/, `(${event.detail.count})`);
        });
    });

    document.addEventListener('mc:compare:updated', (event) => {
        const count = event.detail.count;

        document.querySelectorAll('.mc-header__compare').forEach((link) => {
            const label = link.dataset.mcLabel || link.textContent.replace(/\s*\(\d+\)\s*$/, '');

            link.textContent = count > 0 ? `${label} (${count})` : label;
        });
    });
}
