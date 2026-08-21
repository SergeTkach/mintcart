/**
 * mc-alert dismiss behavior.
 *
 * Reads the `data-mc-action="dismiss-alert"` behavior hook, never the
 * `.mc-alert__close` presentation class (docs/MINTCART_IMPLEMENTATION_GUIDE.md
 * §14). Removes the closest `.mc-alert` from the DOM on click.
 *
 * A single delegated listener on `document` so alerts inserted after page
 * load (e.g. via fetch) are dismissible without re-binding.
 */

document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-mc-action="dismiss-alert"]');

    if (!trigger) {
        return;
    }

    const alert = trigger.closest('.mc-alert');

    if (alert) {
        alert.remove();
    }
});
