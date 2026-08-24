/**
 * mc-confirm - a `window.confirm()` replacement for destructive/critical
 * actions (Functional Scope §5 P0: "Toast / Confirm / Alert primitives" -
 * `mc-alert`/`mc-toast` shipped in Phase 4, this is the remaining one).
 *
 * Unlike `mc-modal`/`mc-drawer`/`mc-toast`, there is no static markup for a
 * caller to reference - a confirm dialog's title/message/button labels are
 * always specific to the action being confirmed (e.g. "Delete this
 * product?"), decided at the call site. So `mcConfirm()` builds a single
 * reusable `.mc-confirm` dialog element on first use, appends it to
 * `document.body`, fills in the given options, and returns a Promise that
 * resolves `true` (confirm button, or Enter with the confirm button
 * focused) or `false` (cancel button, backdrop click, Escape, or the
 * dialog closing any other way).
 *
 * Reuses Bootstrap 5's `Modal` JS for show/hide, focus trap, ESC-to-close
 * and backdrop click-to-close, the same allowance already used by
 * `mc-modal`/`mc-drawer`/`mc-toast` (`MINTCART_IMPLEMENTATION_GUIDE.md`
 * §9). `role="alertdialog"` (not `role="dialog"`, which is what
 * Bootstrap's own `.modal` markup implies) signals to assistive tech that
 * this dialog demands an immediate decision, matching native
 * `window.confirm()` semantics.
 *
 * Only one confirm dialog can be pending at a time - calling `mcConfirm()`
 * again while a previous call's dialog is still open resolves the earlier
 * call `false` (treated as cancelled) before showing the new one. This
 * matches how a real user can only be looking at one confirmation prompt
 * at once.
 *
 * Contract: docs/developer/components/confirm.md
 */

import { Modal } from 'bootstrap';

let dialogEl = null;
let settlePending = null;

function buildDialog() {
    const el = document.createElement('div');

    el.className = 'modal mc-confirm';
    el.tabIndex = -1;
    el.setAttribute('role', 'alertdialog');
    el.setAttribute('aria-modal', 'true');
    el.setAttribute('aria-labelledby', 'mcConfirmTitle');
    el.setAttribute('aria-describedby', 'mcConfirmMessage');
    el.innerHTML = `
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="mc-confirm__title" id="mcConfirmTitle"></h2>
                </div>
                <div class="modal-body">
                    <p class="mc-confirm__message" id="mcConfirmMessage"></p>
                </div>
                <div class="modal-footer mc-confirm__actions">
                    <button type="button" class="mc-button mc-button--secondary" data-mc-action="confirm-cancel"></button>
                    <button type="button" class="mc-button mc-button--primary" data-mc-action="confirm-accept"></button>
                </div>
            </div>
        </div>
    `;

    el.addEventListener('click', (event) => {
        if (event.target.closest('[data-mc-action="confirm-accept"]')) {
            settle(true);
        } else if (event.target.closest('[data-mc-action="confirm-cancel"]')) {
            settle(false);
        }
    });

    // Covers every other way the dialog can close: Escape, backdrop click,
    // or a future caller hiding it directly via the Bootstrap Modal API.
    el.addEventListener('hidden.bs.modal', () => settle(false));

    document.body.appendChild(el);

    return el;
}

function ensureDialog() {
    if (!dialogEl) {
        dialogEl = buildDialog();
    }

    return dialogEl;
}

function settle(result) {
    if (!settlePending) {
        return;
    }

    const resolve = settlePending;
    settlePending = null;
    resolve(result);
    Modal.getInstance(dialogEl)?.hide();
}

export function mcConfirm({
    title = 'Are you sure?',
    message = '',
    confirmLabel = 'Confirm',
    cancelLabel = 'Cancel',
    variant = 'primary',
} = {}) {
    const dialog = ensureDialog();

    // A previous, still-pending call loses its dialog to this one - treat
    // it as cancelled rather than leaving its Promise unresolved forever.
    settle(false);

    return new Promise((resolve) => {
        settlePending = resolve;

        dialog.querySelector('.mc-confirm__title').textContent = title;
        dialog.querySelector('.mc-confirm__message').textContent = message;
        dialog.querySelector('[data-mc-action="confirm-cancel"]').textContent = cancelLabel;

        const acceptButton = dialog.querySelector('[data-mc-action="confirm-accept"]');
        acceptButton.textContent = confirmLabel;
        acceptButton.className = `mc-button mc-button--${variant}`;

        Modal.getOrCreateInstance(dialog).show();
    });
}
