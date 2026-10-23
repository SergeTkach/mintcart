/**
 * mc-select-search behavior: a searchable dropdown on top of a plain
 * `<select data-mc-select-search>` (the Select2 pattern, without Select2).
 *
 * The `<select>` stays the single source of truth - it is what the form
 * posts and what no-JS visitors see - so this file never replaces it. It only
 * intercepts the native popup: a click, Enter, Space or Arrow-Down on such a
 * select opens one shared panel with a search box and the (filtered) option
 * list instead. Because it works by event delegation and reads the options
 * when the panel opens, selects cloned from a <template> or given new
 * options later (Setup Wizard's "+" rows and its AJAX save) need no extra
 * wiring.
 *
 * Options with an empty value are placeholders and are not listed.
 * Keyboard: type to filter, Arrow Up/Down to move, Enter to choose, Escape
 * to close and return focus to the select.
 */

let panel = null;
let current = null;

function buildPanel() {
    const root = document.createElement('div');
    root.className = 'mc-select-search';
    root.hidden = true;

    const input = document.createElement('input');
    input.type = 'search';
    input.className = 'mc-select-search__input';
    input.autocomplete = 'off';
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-expanded', 'true');
    input.setAttribute('aria-controls', 'mc-select-search-list');

    const list = document.createElement('ul');
    list.className = 'mc-select-search__list';
    list.id = 'mc-select-search-list';
    list.setAttribute('role', 'listbox');

    root.append(input, list);
    document.body.append(root);

    input.addEventListener('input', render);
    input.addEventListener('keydown', onKey);
    list.addEventListener('mousedown', (event) => event.preventDefault());
    list.addEventListener('click', (event) => {
        const item = event.target.closest('[data-value]');

        if (item) {
            choose(item.dataset.value);
        }
    });

    return root;
}

function items() {
    return [...panel.querySelectorAll('.mc-select-search__option')];
}

function setActive(item) {
    items().forEach((other) => other.classList.toggle('is-active', other === item));

    if (item) {
        panel.querySelector('input').setAttribute('aria-activedescendant', item.id);
        item.scrollIntoView({ block: 'nearest' });
    }
}

function render() {
    const query = panel.querySelector('input').value.trim().toLowerCase();
    const list = panel.querySelector('ul');
    list.replaceChildren();

    [...current.options].forEach((option, index) => {
        if (option.value === '' || (query && !option.text.toLowerCase().includes(query))) {
            return;
        }

        const item = document.createElement('li');
        item.className = 'mc-select-search__option';
        item.id = `mc-select-search-option-${index}`;
        item.dataset.value = option.value;
        item.setAttribute('role', 'option');
        item.setAttribute('aria-selected', String(option.value === current.value));
        item.textContent = option.text;
        list.append(item);
    });

    if (!list.children.length) {
        const empty = document.createElement('li');
        empty.className = 'mc-select-search__empty';
        empty.textContent = current.dataset.mcSelectSearchEmpty || '-';
        list.append(empty);
    }

    setActive(list.querySelector('[aria-selected="true"]') || list.querySelector('.mc-select-search__option'));
}

function open(select) {
    panel = panel || buildPanel();
    current = select;

    const box = select.getBoundingClientRect();

    panel.style.minWidth = `${box.width}px`;
    panel.style.left = `${box.left + window.scrollX}px`;
    panel.style.top = `${box.bottom + window.scrollY + 4}px`;
    panel.hidden = false;
    select.setAttribute('aria-expanded', 'true');

    const input = panel.querySelector('input');
    input.value = '';
    input.placeholder = select.dataset.mcSelectSearchPlaceholder || '';
    render();
    input.focus();
}

function close(refocus = true) {
    if (!panel || panel.hidden) {
        return;
    }

    panel.hidden = true;
    current.setAttribute('aria-expanded', 'false');

    if (refocus) {
        current.focus();
    }

    current = null;
}

function choose(value) {
    current.value = value;
    current.dispatchEvent(new Event('change', { bubbles: true }));
    close();
}

function onKey(event) {
    const list = items();
    const index = list.findIndex((item) => item.classList.contains('is-active'));

    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        const step = event.key === 'ArrowDown' ? 1 : -1;
        setActive(list[Math.min(Math.max(index + step, 0), list.length - 1)]);
    } else if (event.key === 'Enter') {
        event.preventDefault();

        if (list[index]) {
            choose(list[index].dataset.value);
        }
    } else if (event.key === 'Escape') {
        event.preventDefault();
        close();
    } else if (event.key === 'Tab') {
        close(false);
    }
}

export function initSelectSearch(root = document) {
    root.addEventListener('mousedown', (event) => {
        const select = event.target.closest('select[data-mc-select-search]');

        if (select && !select.disabled) {
            event.preventDefault();
            open(select);
        } else if (panel && !panel.hidden && !panel.contains(event.target)) {
            close(false);
        }
    });

    root.addEventListener('keydown', (event) => {
        const select = event.target.closest?.('select[data-mc-select-search]');

        if (select && ['Enter', ' ', 'ArrowDown'].includes(event.key)) {
            event.preventDefault();
            open(select);
        }
    });
}
