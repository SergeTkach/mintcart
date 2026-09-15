/**
 * mc-product-gallery - main image / thumbnail swap for the product detail
 * page (`mint_default/template/product/product.twig`).
 *
 * Behavior hook (CLAUDE.md §14): clicking `[data-mc-action="gallery-thumb"]`
 * swaps `.mc-product-gallery__main`'s `src`/`alt` to the values on the
 * clicked thumbnail's own `data-mc-gallery-src`/`data-mc-gallery-alt`
 * attributes - no second data source, the thumbnail *is* the data. Delegated
 * on `document`, same pattern as `mc-quantity`/`mc-alert`.
 *
 * Progressive enhancement: every thumbnail is a real `<a href="{{ popup }}">`
 * pointing at the full-size image (see the template), so a visitor gets a
 * working full-size image link even before/without this script running -
 * this only intercepts the click to swap the main image in place instead.
 *
 * Contract: docs/developer/components/product-gallery.md
 */

export function initProductGalleries(root = document) {
    root.addEventListener('click', (event) => {
        const thumb = event.target.closest('[data-mc-action="gallery-thumb"]');

        if (!thumb) {
            return;
        }

        const gallery = thumb.closest('.mc-product-gallery');
        const main = gallery?.querySelector('.mc-product-gallery__main');

        if (!gallery || !main) {
            return;
        }

        event.preventDefault();

        main.src = thumb.dataset.mcGallerySrc;
        main.alt = thumb.dataset.mcGalleryAlt || '';

        gallery.querySelectorAll('.mc-product-gallery__thumb').forEach((el) => {
            el.classList.toggle('is-active', el === thumb);
        });
    });
}
