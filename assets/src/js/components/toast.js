/**
 * mc-toast open/close behavior.
 *
 * Behavior hooks (CLAUDE.md §14): `[data-mc-action="open-toast"]` (reads its
 * own `data-mc-target` attribute - a CSS selector for the `.mc-toast`
 * element to show) and `[data-mc-action="close-toast"]` (hides the
 * `.mc-toast` ancestor it's inside). Delegated on `document`, same approach
 * as mc-modal/mc-drawer - no per-instance init call needed for markup
 * present at page load.
 *
 * Drives Bootstrap 5's `Toast` class directly instead of relying on
 * Bootstrap's own `data-bs-dismiss` attribute, so the only documented
 * public trigger contract is `data-mc-*` (there is no Bootstrap
 * `data-bs-toggle` equivalent for toasts to begin with - Bootstrap's own
 * docs show them triggered imperatively).
 *
 * `showToast()` is exported for future callers that create/show a toast
 * programmatically (e.g. a Phase 15 Cart client dispatching
 * `mc:cart:updated` and wanting to surface a "Added to cart" toast) rather
 * than from a static `data-mc-action="open-toast"` trigger already in the
 * markup.
 *
 * Bootstrap's `shown.bs.toast`/`hidden.bs.toast` events are re-dispatched as
 * bubbling `mc:toast:shown`/`mc:toast:hidden` `CustomEvent`s so extension
 * code only needs the `mc:*` event contract (`MINTCART_TECHNICAL_PLAN.md`
 * §25), not Bootstrap's internal event namespace.
 *
 * Contract: docs/developer/components/toast.md
 */

import { Toast } from 'bootstrap';

const relayedToasts = new WeakSet();

function relayToastEvents(toast) {
    if (relayedToasts.has(toast)) {
        return;
    }

    relayedToasts.add(toast);

    toast.addEventListener('shown.bs.toast', () => {
        toast.dispatchEvent(new CustomEvent('mc:toast:shown', { bubbles: true }));
    });

    toast.addEventListener('hidden.bs.toast', () => {
        toast.dispatchEvent(new CustomEvent('mc:toast:hidden', { bubbles: true }));
    });
}

export function showToast(toast) {
    if (!toast) {
        return;
    }

    relayToastEvents(toast);
    Toast.getOrCreateInstance(toast).show();
}

export function initToastTriggers(root = document) {
    root.querySelectorAll('.mc-toast').forEach(relayToastEvents);

    root.addEventListener('click', (event) => {
        const openTrigger = event.target.closest('[data-mc-action="open-toast"]');

        if (openTrigger) {
            const targetSelector = openTrigger.getAttribute('data-mc-target');
            const toast = targetSelector ? document.querySelector(targetSelector) : null;

            showToast(toast);
            return;
        }

        const closeTrigger = event.target.closest('[data-mc-action="close-toast"]');

        if (!closeTrigger) {
            return;
        }

        const toast = closeTrigger.closest('.mc-toast');

        if (toast) {
            Toast.getInstance(toast)?.hide();
        }
    });
}
