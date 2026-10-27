/**
 * Row editor of the Homepage block form (`design/homepage_form.twig`): the lists of
 * slides, banners, benefits, testimonials, brands, photos and FAQ questions.
 *
 * Markup contract (see the twig macro `item_row`):
 *   [data-mc-items]                  one list (a <fieldset>; data-list = its name, data-max = 0 or a cap)
 *     [data-mc-items-list]           the rows' container - drag & drop (SortableJS) on its children
 *       [data-mc-items-row]          one row: [data-mc-items-handle], [data-mc-items-remove],
 *                                    [data-mc-items-more] (shows the
 *                                    [data-mc-items-extra] fields)
 *       [data-mc-items-lang-pane=ID] the texts of one language inside a row
 *     [data-mc-items-lang=ID]        a language tab above the list (switches the pane in every row)
 *     [data-mc-items-add]            clones <template data-mc-items-template> (`__UID__` = a fresh id)
 *     [data-mc-items-empty]          "nothing here yet" hint
 *   [data-mc-show-setting] / [data-mc-show-value]  show the list only while `settings[<name>]` has the value
 *
 * Field names carry a row index (`settings[items][3][image]`); rows are renumbered in their
 * DOM order right before the form is submitted, so the server gets 0..n-1 in the visual order.
 * The picture fields use OpenCart's own file manager (`a[data-toggle="image"]`, common.js) -
 * nothing to do here except keep their ids unique.
 */

import Sortable from 'sortablejs';

function rows(list) {
    return Array.from(list.querySelectorAll(':scope > [data-mc-items-list] > [data-mc-items-row]'));
}

function refresh(root) {
    const count = rows(root).length;
    const max = Number(root.dataset.max) || 0;

    root.querySelector('[data-mc-items-empty]').hidden = count > 0;
    root.querySelector('[data-mc-items-add]').hidden = max > 0 && count >= max;
}

function activeLanguage(root) {
    const active = root.querySelector('[data-mc-items-lang].is-active, .active > [data-mc-items-lang]');

    return active ? active.dataset.mcItemsLang : null;
}

function showLanguage(root, languageId) {
    if (languageId === null) {
        return;
    }

    root.querySelectorAll('[data-mc-items-lang]').forEach((tab) => {
        const on = tab.dataset.mcItemsLang === String(languageId);

        tab.classList.toggle('is-active', on);
        tab.parentElement.classList.toggle('active', on);
    });

    root.querySelectorAll('[data-mc-items-lang-pane]').forEach((pane) => {
        pane.hidden = pane.dataset.mcItemsLangPane !== String(languageId);
    });
}

function renumber(root) {
    const name = root.dataset.list;
    const pattern = new RegExp('\\[' + name + '\\]\\[[^\\]]+\\]', 'g');

    rows(root).forEach((row, index) => {
        row.querySelectorAll('[name]').forEach((field) => {
            field.name = field.name.replace(pattern, '[' + name + '][' + index + ']');
        });
    });
}

function initList(root, form) {
    const list = root.querySelector('[data-mc-items-list]');
    const template = root.querySelector('[data-mc-items-template]');
    let uid = rows(root).length;

    Sortable.create(list, {
        handle: '[data-mc-items-handle]',
        animation: 150,
        ghostClass: 'is-dragging',
        onEnd: () => refresh(root),
    });

    root.addEventListener('click', (event) => {
        const target = event.target;

        if (!(target instanceof Element)) {
            return;
        }

        const tab = target.closest('[data-mc-items-lang]');

        if (tab && root.contains(tab)) {
            event.preventDefault();
            showLanguage(root, tab.dataset.mcItemsLang);

            return;
        }

        if (target.closest('[data-mc-items-add]')) {
            const holder = document.createElement('div');

            holder.innerHTML = template.innerHTML.split('__UID__').join('n' + uid++);
            list.appendChild(holder.firstElementChild);
            showLanguage(root, activeLanguage(root));
            refresh(root);

            return;
        }

        const row = target.closest('[data-mc-items-row]');

        if (!row || !root.contains(row)) {
            return;
        }

        if (target.closest('[data-mc-items-remove]')) {
            row.remove();
            refresh(root);
        } else if (target.closest('[data-mc-items-more]')) {
            row.querySelectorAll('[data-mc-items-extra]').forEach((field) => {
                field.hidden = !field.hidden;
            });
        }
    });

    form.addEventListener('submit', () => renumber(root));

    refresh(root);
}

function initShowWhen(root, form) {
    const setting = root.dataset.mcShowSetting;

    if (!setting) {
        return;
    }

    const control = form.querySelector('[name="settings[' + setting + ']"]');

    if (!control) {
        return;
    }

    // An accordion: the header (legend) is always there, so the visitor sees the section exists;
    // it opens by itself when the setting asks for it, and can be toggled by hand.
    const legend = root.querySelector(':scope > legend');
    const apply = () => {
        const open = control.value === root.dataset.mcShowValue;

        root.classList.toggle('is-collapsed', !open);
        legend?.setAttribute('aria-expanded', String(open));
    };

    if (legend) {
        legend.tabIndex = 0;
        legend.setAttribute('role', 'button');

        const toggle = () => {
            const collapsed = root.classList.toggle('is-collapsed');

            legend.setAttribute('aria-expanded', String(!collapsed));
        };

        legend.addEventListener('click', toggle);
        legend.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                toggle();
            }
        });
    }

    control.addEventListener('change', apply);
    apply();
}

export function initHomepageItems() {
    const form = document.querySelector('[data-mc-items-form]');

    if (!form) {
        return;
    }

    form.querySelectorAll('[data-mc-items]').forEach((root) => {
        initList(root, form);
        initShowWhen(root, form);
    });
}
