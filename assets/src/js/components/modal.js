/**
 * mc-modal open/close behavior.
 *
 * Behavior hooks (CLAUDE.md §14): `[data-mc-action="open-modal"]` (reads its
 * own `data-mc-target` attribute - a CSS selector for the `.mc-modal`
 * element to open) and `[data-mc-action="close-modal"]` (closes the
 * `.mc-modal` ancestor it's inside). Delegated on `document`, same approach
 * as mc-alert/mc-quantity - no per-instance init call needed at render time.
 *
 * Drives Bootstrap 5's `Modal` class directly instead of relying on
 * Bootstrap's own `data-bs-toggle`/`data-bs-dismiss` attributes, so the only
 * documented public trigger contract is `data-mc-*` (see modal.css header
 * comment for why the underlying `.modal`/`.modal-dialog`/... classes stay
 * on the markup as internal implementation).
 *
 * Bootstrap's `shown.bs.modal`/`hidden.bs.modal` events are re-dispatched as
 * bubbling `mc:modal:shown`/`mc:modal:hidden` `CustomEvent`s so extension
 * code only needs to know the `mc:*` event contract
 * (`MINTCART_TECHNICAL_PLAN.md` §25), not Bootstrap's internal event
 * namespace.
 *
 * Contract: docs/developer/components/modal.md
 */

import { Modal } from 'bootstrap';

const relayedModals = new WeakSet();

function relayModalEvents(modal) {
    if (relayedModals.has(modal)) {
        return;
    }

    relayedModals.add(modal);

    modal.addEventListener('shown.bs.modal', () => {
        modal.dispatchEvent(new CustomEvent('mc:modal:shown', { bubbles: true }));
    });

    modal.addEventListener('hidden.bs.modal', () => {
        modal.dispatchEvent(new CustomEvent('mc:modal:hidden', { bubbles: true }));
    });
}

export function initModalTriggers(root = document) {
    root.querySelectorAll('.mc-modal').forEach(relayModalEvents);

    root.addEventListener('click', (event) => {
        const openTrigger = event.target.closest('[data-mc-action="open-modal"]');

        if (openTrigger) {
            const targetSelector = openTrigger.getAttribute('data-mc-target');
            const modal = targetSelector ? document.querySelector(targetSelector) : null;

            if (!modal) {
                return;
            }

            relayModalEvents(modal);
            Modal.getOrCreateInstance(modal).show();
            return;
        }

        const closeTrigger = event.target.closest('[data-mc-action="close-modal"]');

        if (!closeTrigger) {
            return;
        }

        const modal = closeTrigger.closest('.mc-modal');

        if (modal) {
            Modal.getInstance(modal)?.hide();
        }
    });
}
