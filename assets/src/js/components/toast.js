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

const ALERT_VARIANTS = new Set(['danger', 'warning']);

/**
 * Builds a `.mc-toast` element on the fly and appends it to a (lazily
 * created) `.toast-container`, for a caller with a message to show but no
 * pre-existing toast markup to reveal - the "future Phase 15 Cart client"
 * case this file's own header comment already anticipated. Matches
 * docs/developer/components/toast.md's documented markup exactly
 * (`.toast-header`/`.toast-body`, `mc-toast__title`/`__message`/`__close`,
 * `role`/`aria-live` chosen per variant the same way the static examples
 * there do). Auto-removes the element from the DOM once it finishes
 * hiding, so repeated calls don't leak nodes.
 *
 * @param {{ title: string, message: string, variant?: 'success'|'danger'|'warning'|'info', action?: { text: string, href: string } }} options
 *        `action` adds one link under the message (e.g. "View cart").
 * @returns {HTMLElement} the created `.mc-toast` element - not yet shown,
 *          pass it to `showToast()`.
 */
export function createToast({ title, message, variant = 'info', action = null }) {
    let container = document.querySelector('.toast-container');

    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        document.body.appendChild(container);
    }

    const isAlert = ALERT_VARIANTS.has(variant);

    const toast = document.createElement('div');
    toast.className = `toast mc-toast mc-toast--${variant}`;
    toast.setAttribute('role', isAlert ? 'alert' : 'status');
    toast.setAttribute('aria-live', isAlert ? 'assertive' : 'polite');
    toast.setAttribute('aria-atomic', 'true');

    toast.innerHTML = `
        <div class="toast-header">
            <strong class="mc-toast__title"></strong>
            <button type="button" class="mc-toast__close" data-mc-action="close-toast" aria-label="Close">&times;</button>
        </div>
        <div class="toast-body">
            <p class="mc-toast__message"></p>
        </div>
    `;

    toast.querySelector('.mc-toast__title').textContent = title;
    toast.querySelector('.mc-toast__message').textContent = message;

    if (action && action.text && action.href) {
        const link = document.createElement('a');
        link.className = 'mc-toast__action';
        link.href = action.href;
        link.textContent = action.text;
        toast.querySelector('.toast-body').appendChild(link);
    }

    container.appendChild(toast);

    toast.addEventListener('hidden.bs.toast', () => toast.remove(), { once: true });

    return toast;
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
