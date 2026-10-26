/**
 * mc-add-rows behavior: a "+" that adds one more empty row to a list, so a
 * form can collect several values at once and save them with its own Save
 * button (Setup Wizard -> Language & currency: add several currencies or
 * languages). Rows are cloned from the <template> inside the root, so the
 * server renders the row markup and this file only wires the buttons.
 *
 *   <div data-mc-add-rows data-mc-add-rows-form="my-form" data-mc-add-rows-target="select-id">
 *     <div data-mc-add-rows-list></div>
 *     <template data-mc-add-rows-template>...row with [data-mc-add-rows-row] and [data-mc-add-rows-remove]...</template>
 *     <button type="button" data-mc-add-rows-add>+</button>
 *     <button type="submit" form="my-form" data-mc-add-rows-save hidden>Save</button>
 *   </div>
 *
 * Save is sent with fetch() to the form's action; the server answers
 * `{rows: {<position>: {ok, value, label, message}}, notice: {type, text}}`.
 * A saved row disappears and its value joins the target <select> (the last
 * saved one is selected); a failed row stays, with its message under it.
 * The page is never reloaded, so nothing typed is lost. The Save button
 * stays hidden until at least one row exists.
 */

import { mcConfirm } from './confirm.js';

function syncSave(root) {
    const save = root.querySelector('[data-mc-add-rows-save]');

    if (save) {
        save.hidden = root.querySelector('[data-mc-add-rows-list]').children.length === 0;
    }
}

function setRowError(row, message) {
    row.querySelector('[data-mc-add-rows-error]')?.remove();

    const input = row.querySelector('input');

    if (!message) {
        input?.removeAttribute('aria-invalid');
        return;
    }

    input?.setAttribute('aria-invalid', 'true');

    const error = document.createElement('p');
    error.className = 'mc-field__error w-100 mb-0';
    error.setAttribute('data-mc-add-rows-error', '');
    error.setAttribute('role', 'alert');
    error.textContent = message;
    row.append(error);
}

function showNotice(root, notice) {
    const box = root.closest('form, section, main')?.parentElement?.querySelector('[data-mc-wizard-notice]')
        ?? document.querySelector('[data-mc-wizard-notice]');

    if (!box || !notice) {
        return;
    }

    box.replaceChildren();

    const alert = document.createElement('div');
    alert.className = `mc-alert mc-alert--${notice.type} mb-4`;
    alert.setAttribute('role', 'status');

    const body = document.createElement('div');
    body.className = 'mc-alert__body';

    const message = document.createElement('p');
    message.className = 'mc-alert__message';
    message.textContent = notice.text;

    body.append(message);
    alert.append(body);
    box.append(alert);
}

async function save(host, form) {
    const button = host.querySelector('[data-mc-add-rows-save]');
    const rows = [...host.querySelectorAll('[data-mc-add-rows-list] [data-mc-add-rows-row]')];

    button.disabled = true;
    button.classList.add('is-loading');

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams(new FormData(form)),
            credentials: 'same-origin',
        });
        const result = await response.json();
        const target = document.getElementById(host.dataset.mcAddRowsTarget);
        let lastSaved = null;

        rows.forEach((row, index) => {
            const outcome = result.rows[index];

            if (!outcome) {
                setRowError(row, host.dataset.mcAddRowsEmpty);
                return;
            }

            if (outcome.ok) {
                if (target && ![...target.options].some((option) => option.value === outcome.value)) {
                    target.add(new Option(outcome.label, outcome.value));
                }

                lastSaved = outcome.value;
                row.remove();
            } else {
                setRowError(row, outcome.message);
            }
        });

        if (target && lastSaved) {
            target.value = lastSaved;
        }

        showNotice(host, result.notice);
    } catch (error) {
        showNotice(host, { type: 'warning', text: error.message });
    } finally {
        button.disabled = false;
        button.classList.remove('is-loading');
        syncSave(host);
    }
}

export function initAddRows(root = document) {
    // A connected language / currency "x": ask first, then submit its own form
    // (the button carries the code; `requestSubmit(button)` sends it as the submitter).
    root.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-mc-confirm-remove]');

        if (!button || !button.form) {
            return;
        }

        event.preventDefault();

        const confirmed = await mcConfirm({
            title: button.dataset.mcConfirmTitle,
            message: button.dataset.mcConfirmMessage,
            confirmLabel: button.dataset.mcConfirmAccept,
            cancelLabel: button.dataset.mcConfirmCancel,
            variant: 'danger',
        });

        if (confirmed) {
            button.form.requestSubmit(button);
        }
    });

    root.addEventListener('click', (event) => {
        const add = event.target.closest('[data-mc-add-rows-add]');
        const remove = event.target.closest('[data-mc-add-rows-remove]');
        const host = (add || remove)?.closest('[data-mc-add-rows]');

        if (!host) {
            return;
        }

        const list = host.querySelector('[data-mc-add-rows-list]');

        if (add) {
            const template = host.querySelector('template[data-mc-add-rows-template]');
            list.append(template.content.cloneNode(true));
            list.lastElementChild.querySelector('input, select')?.focus();
        } else {
            remove.closest('[data-mc-add-rows-row]').remove();
        }

        syncSave(host);
    });

    root.addEventListener('submit', (event) => {
        const host = document.querySelector(`[data-mc-add-rows][data-mc-add-rows-form="${event.target.id}"]`);

        if (host) {
            event.preventDefault();
            save(host, event.target);
        }
    });
}
