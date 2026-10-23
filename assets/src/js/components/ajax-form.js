/**
 * mc-ajax-form behavior: a `<form data-mc-ajax-form>` is sent with fetch()
 * instead of navigating, so nothing on the page is lost or reloaded.
 *
 * The server answers JSON:
 *   { ok: bool, message?: string, errors?: { <field key>: string }, enabled?: bool }
 *
 *  - `message` goes into `[data-mc-ajax-form-status]` inside the form;
 *  - `errors` go into `[data-mc-ajax-form-error="<field key>"]` (the field's
 *    key is the name inside `field[...]`);
 *  - `enabled` (the Setup Wizard's enable/disable toggle) updates the
 *    surrounding `[data-mc-method]` card: its on-state, badge and button
 *    label (texts come from the card's data-text-on/-off, data-label-enable/-disable).
 *
 * First consumer: the Setup Wizard's Delivery and Payment steps.
 */

const HEADERS = { 'X-Requested-With': 'XMLHttpRequest' };

function applyToggle(form, enabled) {
    const card = form.closest('[data-mc-method]');

    if (!card) {
        return;
    }

    card.classList.toggle('is-on', enabled);

    const badge = card.querySelector('[data-mc-method-badge]');

    if (badge) {
        badge.textContent = enabled ? card.dataset.textOn : card.dataset.textOff;
        badge.classList.toggle('mc-badge--success', enabled);
    }

    const button = form.querySelector('button[type="submit"]');

    if (button) {
        button.textContent = enabled ? card.dataset.labelDisable : card.dataset.labelEnable;
        button.classList.toggle('mc-button--ghost', enabled);
        button.classList.toggle('mc-button--secondary', !enabled);
    }

    const list = card.closest('[data-mc-methods]');
    const warning = list?.parentElement.querySelector('[data-mc-methods-warning]');

    if (warning) {
        warning.hidden = list.querySelector('[data-mc-method].is-on') !== null;
    }
}

async function send(form) {
    const status = form.querySelector('[data-mc-ajax-form-status]');
    const button = form.querySelector('button[type="submit"]');

    form.querySelectorAll('[data-mc-ajax-form-error]').forEach((error) => {
        error.hidden = true;
        error.textContent = '';
    });

    button.disabled = true;

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: HEADERS,
            body: new URLSearchParams(new FormData(form)),
            credentials: 'same-origin',
        });
        const result = await response.json();

        Object.entries(result.errors || {}).forEach(([key, message]) => {
            const error = form.querySelector(`[data-mc-ajax-form-error="${key}"]`);

            if (error) {
                error.textContent = message;
                error.hidden = false;
            }
        });

        if (status) {
            status.textContent = result.message || '';
            status.classList.toggle('has-error', !result.ok);
        }

        if (typeof result.enabled === 'boolean') {
            applyToggle(form, result.enabled);
        }
    } catch (error) {
        if (status) {
            status.textContent = error.message;
            status.classList.add('has-error');
        }
    } finally {
        button.disabled = false;
    }
}

export function initAjaxForms(root = document) {
    root.addEventListener('submit', (event) => {
        const form = event.target.closest?.('form[data-mc-ajax-form]');

        if (form) {
            event.preventDefault();
            send(form);
        }
    });
}
