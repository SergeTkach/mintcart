/**
 * mc-add-rows behavior: a "+" that adds one more empty row to a list, so a
 * form can collect several values at once and save them with its own Save
 * button (Setup Wizard -> Language & currency: add several currencies or
 * languages). Rows are cloned from the <template> inside the root, so the
 * server renders the row markup and this file only wires the buttons.
 *
 *   <div data-mc-add-rows>
 *     <div data-mc-add-rows-list></div>
 *     <template data-mc-add-rows-template>...row with [data-mc-add-rows-remove]...</template>
 *     <button type="button" data-mc-add-rows-add>+</button>
 *     <button type="submit" data-mc-add-rows-save hidden>Save</button>
 *   </div>
 *
 * The Save button stays hidden until at least one row exists.
 */

function syncSave(root) {
    const save = root.querySelector('[data-mc-add-rows-save]');

    if (save) {
        save.hidden = root.querySelector('[data-mc-add-rows-list]').children.length === 0;
    }
}

export function initAddRows(root = document) {
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
}
