/**
 * mc-drawer open/close behavior.
 *
 * Behavior hooks (CLAUDE.md §14): `[data-mc-action="open-drawer"]` (reads
 * its own `data-mc-target` attribute - a CSS selector for the `.mc-drawer`
 * element to open) and `[data-mc-action="close-drawer"]` (closes the
 * `.mc-drawer` ancestor it's inside). Delegated on `document`, same
 * approach as mc-modal/mc-alert/mc-quantity - no per-instance init call
 * needed at render time.
 *
 * Drives Bootstrap 5's `Offcanvas` class directly instead of relying on
 * Bootstrap's own `data-bs-toggle`/`data-bs-dismiss` attributes, so the only
 * documented public trigger contract is `data-mc-*` (see drawer.css header
 * comment for why the underlying `.offcanvas`/`.offcanvas-start|end|...`
 * classes stay on the markup as internal implementation).
 *
 * Bootstrap's `shown.bs.offcanvas`/`hidden.bs.offcanvas` events are
 * re-dispatched as bubbling `mc:drawer:shown`/`mc:drawer:hidden`
 * `CustomEvent`s so extension code only needs to know the `mc:*` event
 * contract (`MINTCART_TECHNICAL_PLAN.md` §25), not Bootstrap's internal
 * event namespace.
 *
 * Contract: docs/developer/components/drawer.md
 */

import { Offcanvas } from 'bootstrap';

const relayedDrawers = new WeakSet();

function relayDrawerEvents(drawer) {
    if (relayedDrawers.has(drawer)) {
        return;
    }

    relayedDrawers.add(drawer);

    drawer.addEventListener('shown.bs.offcanvas', () => {
        drawer.dispatchEvent(new CustomEvent('mc:drawer:shown', { bubbles: true }));
    });

    drawer.addEventListener('hidden.bs.offcanvas', () => {
        drawer.dispatchEvent(new CustomEvent('mc:drawer:hidden', { bubbles: true }));
    });
}

export function initDrawerTriggers(root = document) {
    root.querySelectorAll('.mc-drawer').forEach(relayDrawerEvents);

    root.addEventListener('click', (event) => {
        const openTrigger = event.target.closest('[data-mc-action="open-drawer"]');

        if (openTrigger) {
            const targetSelector = openTrigger.getAttribute('data-mc-target');
            const drawer = targetSelector ? document.querySelector(targetSelector) : null;

            if (!drawer) {
                return;
            }

            relayDrawerEvents(drawer);
            Offcanvas.getOrCreateInstance(drawer).show();
            return;
        }

        const closeTrigger = event.target.closest('[data-mc-action="close-drawer"]');

        if (!closeTrigger) {
            return;
        }

        const drawer = closeTrigger.closest('.mc-drawer');

        if (drawer) {
            Offcanvas.getInstance(drawer)?.hide();
        }
    });
}
