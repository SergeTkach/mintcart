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

import GLightbox from 'glightbox';

/**
 * Full-size lightbox (GLightbox, same library the Cartello reference theme
 * used): clicking the main image opens every gallery photo - the thumbs'
 * `href`s (popup size) or just the main link's - starting at the one shown.
 * Without JS the main link still opens the full-size image directly.
 */
function openLightbox(gallery, mainLink) {
    const thumbs = [...gallery.querySelectorAll('[data-mc-action="gallery-thumb"]')];
    const alt = gallery.querySelector('.mc-product-gallery__main')?.alt || '';
    const sources = thumbs.length ? thumbs.map((thumb) => thumb.href) : [mainLink.href];
    const active = Math.max(0, thumbs.findIndex((thumb) => thumb.classList.contains('is-active')));

    const lightbox = GLightbox({
        elements: sources.map((href) => ({ href, type: 'image', alt })),
        startAt: active,
        loop: true,
        touchNavigation: true,
    });

    lightbox.on('close', () => lightbox.destroy());
    lightbox.open();
}

export function initProductGalleries(root = document) {
    root.addEventListener('click', (event) => {
        const mainLink = event.target.closest('[data-mc-action="gallery-open"]');

        if (mainLink) {
            const gallery = mainLink.closest('.mc-product-gallery');

            if (gallery) {
                event.preventDefault();
                openLightbox(gallery, mainLink);
            }

            return;
        }

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

        // Keep the no-JS fallback link pointing at the photo now shown.
        const link = gallery.querySelector('[data-mc-action="gallery-open"]');

        if (link) {
            link.href = thumb.href;
        }

        gallery.querySelectorAll('.mc-product-gallery__thumb').forEach((el) => {
            el.classList.toggle('is-active', el === thumb);
        });
    });
}
