/**
 * mc-entity-picker - the unified entity picker primitive
 * (`MINTCART_FUNCTIONAL_SCOPE.md` §8, `MINTCART_TECHNICAL_PLAN.md` §20):
 * one searchable, paginated, multi-select list UI meant to replace the old
 * per-entity autocomplete pattern for products, categories, manufacturers,
 * attributes, options, information pages and customers alike.
 *
 * This module owns only the list behavior (search, pagination, selection,
 * keyboard navigation) against a caller-supplied `fetchItems` data source -
 * it does not know or care which entity type it is listing, and it does
 * not own any dialog chrome. Host it inside an existing `.mc-drawer` or
 * `.mc-modal` (Phase 4) using their own `data-mc-action="open-drawer"` /
 * `"open-modal"` triggers; call `refresh()` in response to that dialog's
 * `mc:drawer:shown` / `mc:modal:shown` event so the list loads lazily
 * instead of fetching before the picker is ever opened.
 *
 * Markup contract (host page provides this inside a drawer/modal body;
 * `data-mc-entity-picker-*` are read-only hooks this module queries -
 * CLAUDE.md §14, no styling/behavior should be keyed off `.mc-entity-picker`
 * child classes from outside this file):
 *
 *   <div class="mc-entity-picker">
 *     <input type="search" class="mc-field__control" data-mc-entity-picker-search>
 *     <ul class="mc-entity-picker__list" role="listbox" data-mc-entity-picker-list></ul>
 *     <p class="mc-entity-picker__empty" data-mc-entity-picker-empty hidden></p>
 *     <div class="mc-entity-picker__pager">
 *       <button type="button" data-mc-action="entity-picker-prev"></button>
 *       <span data-mc-entity-picker-page-info></span>
 *       <button type="button" data-mc-action="entity-picker-next"></button>
 *     </div>
 *     <div class="mc-entity-picker__create" data-mc-entity-picker-create hidden>
 *       <input type="text" class="mc-field__control" data-mc-entity-picker-create-input>
 *       <button type="button" data-mc-action="entity-picker-create"></button>
 *     </div>
 *     <div class="mc-entity-picker__footer">
 *       <span data-mc-entity-picker-selected-count></span>
 *       <button type="button" data-mc-action="entity-picker-confirm"></button>
 *     </div>
 *   </div>
 *
 * The `__create` block (`MINTCART_FUNCTIONAL_SCOPE.md` §8's "create-new
 * flow via drawer/modal") is optional markup: it only does anything when
 * the host supplies a `createItem` option below, and is force-`hidden`
 * otherwise, exactly like `__footer`'s confirm button is hidden when no
 * `onConfirm` is supplied. It is deliberately an inline row inside this
 * same panel, not a second nested drawer/modal — the picker already
 * knows how to insert/select a new row, so reusing that instead of
 * opening a second dialog on top of the first keeps the interaction (and
 * the markup) simpler (`CLAUDE.md` §86).
 *
 * Item rows are rendered by this module (their content is always data-
 * driven), as `<li role="option" aria-selected="…" data-id="…">`, styled by
 * `entity-picker.css`. Keyboard: ArrowUp/ArrowDown/Home/End move a roving
 * tabindex between rows (WAI-ARIA listbox pattern); Enter/Space toggles the
 * focused row's selection - the same interaction a mouse click gives, so
 * there is no non-keyboard-only affordance (Functional Scope §27).
 *
 * `mc:entity-picker:change` (bubbling, `detail: { selected: Item[] }`) fires
 * on `root` every time the selection changes, so a host form field can stay
 * in sync without polling `getSelected()`.
 *
 * Contract: docs/developer/components/entity-picker.md
 */

const SELECTORS = {
    search: '[data-mc-entity-picker-search]',
    list: '[data-mc-entity-picker-list]',
    empty: '[data-mc-entity-picker-empty]',
    pageInfo: '[data-mc-entity-picker-page-info]',
    selectedCount: '[data-mc-entity-picker-selected-count]',
    create: '[data-mc-entity-picker-create]',
    createInput: '[data-mc-entity-picker-create-input]',
};

export function createEntityPicker(root, {
    fetchItems,
    multiple = true,
    pageSize = 20,
    selected = [],
    debounceMs = 250,
    getId = (item) => item.id,
    getLabel = (item) => item.name,
    getImage = (item) => item.image ?? null,
    onChange = null,
    onConfirm = null,
    createItem = null,
} = {}) {
    if (typeof fetchItems !== 'function') {
        throw new TypeError('createEntityPicker() requires a fetchItems(...) data source');
    }

    const searchInput = root.querySelector(SELECTORS.search);
    const listEl = root.querySelector(SELECTORS.list);
    const emptyEl = root.querySelector(SELECTORS.empty);
    const prevButton = root.querySelector('[data-mc-action="entity-picker-prev"]');
    const nextButton = root.querySelector('[data-mc-action="entity-picker-next"]');
    const pageInfoEl = root.querySelector(SELECTORS.pageInfo);
    const selectedCountEl = root.querySelector(SELECTORS.selectedCount);
    const confirmButton = root.querySelector('[data-mc-action="entity-picker-confirm"]');
    const createSection = root.querySelector(SELECTORS.create);
    const createInput = root.querySelector(SELECTORS.createInput);
    const createButton = root.querySelector('[data-mc-action="entity-picker-create"]');

    if (!listEl) {
        throw new Error('createEntityPicker() requires a [data-mc-entity-picker-list] element in root');
    }

    const knownItems = new Map();
    const selectedIds = new Set(selected);

    let query = '';
    let page = 1;
    let total = 0;
    let debounceTimer = null;
    let abortController = null;
    let destroyed = false;

    function itemsById(ids) {
        return [...ids].map((id) => knownItems.get(id)).filter(Boolean);
    }

    function notifyChange() {
        if (typeof onChange === 'function') {
            onChange(itemsById(selectedIds));
        }

        root.dispatchEvent(new CustomEvent('mc:entity-picker:change', {
            bubbles: true,
            detail: { selected: itemsById(selectedIds) },
        }));

        if (selectedCountEl) {
            selectedCountEl.textContent = String(selectedIds.size);
        }
    }

    function setRowSelected(row, isSelected) {
        row.setAttribute('aria-selected', isSelected ? 'true' : 'false');
    }

    function toggleSelection(id) {
        if (selectedIds.has(id)) {
            selectedIds.delete(id);
        } else {
            if (!multiple) {
                selectedIds.clear();
            }

            selectedIds.add(id);
        }

        listEl.querySelectorAll('[data-id]').forEach((row) => {
            setRowSelected(row, selectedIds.has(row.dataset.id));
        });

        notifyChange();
    }

    function focusRow(row) {
        listEl.querySelectorAll('[role="option"]').forEach((el) => {
            el.tabIndex = el === row ? 0 : -1;
        });

        row?.focus();
    }

    function renderItems(items) {
        listEl.innerHTML = '';

        items.forEach((item) => {
            const id = String(getId(item));

            knownItems.set(id, item);

            const row = document.createElement('li');
            row.className = 'mc-entity-picker__item';
            row.setAttribute('role', 'option');
            row.dataset.id = id;
            row.tabIndex = -1;
            setRowSelected(row, selectedIds.has(id));

            const image = getImage(item);

            if (image) {
                const img = document.createElement('img');
                img.className = 'mc-entity-picker__item-image';
                img.src = image;
                img.alt = '';
                row.appendChild(img);
            }

            const label = document.createElement('span');
            label.className = 'mc-entity-picker__item-label';
            label.textContent = getLabel(item);
            row.appendChild(label);

            listEl.appendChild(row);
        });

        const firstRow = listEl.querySelector('[role="option"]');

        if (firstRow) {
            firstRow.tabIndex = 0;
        }

        if (emptyEl) {
            emptyEl.hidden = items.length > 0;
        }
    }

    async function load() {
        abortController?.abort();
        abortController = new AbortController();

        root.classList.add('is-loading');

        try {
            const result = await fetchItems({
                query,
                page,
                pageSize,
                signal: abortController.signal,
            });

            if (destroyed) {
                return;
            }

            total = result.total ?? result.items.length;
            renderItems(result.items);

            if (pageInfoEl) {
                const lastPage = Math.max(1, Math.ceil(total / pageSize));
                pageInfoEl.textContent = `${page} / ${lastPage}`;
            }

            if (prevButton) {
                prevButton.disabled = page <= 1;
            }

            if (nextButton) {
                nextButton.disabled = page * pageSize >= total;
            }
        } catch (error) {
            if (error?.name !== 'AbortError') {
                throw error;
            }
        } finally {
            if (!destroyed) {
                root.classList.remove('is-loading');
            }
        }
    }

    function handleListKeydown(event) {
        const row = event.target.closest('[role="option"]');

        if (!row) {
            return;
        }

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            const rows = [...listEl.querySelectorAll('[role="option"]')];
            const index = rows.indexOf(row);
            const nextIndex = event.key === 'ArrowDown'
                ? Math.min(index + 1, rows.length - 1)
                : Math.max(index - 1, 0);

            focusRow(rows[nextIndex]);
            return;
        }

        if (event.key === 'Home' || event.key === 'End') {
            event.preventDefault();
            const rows = [...listEl.querySelectorAll('[role="option"]')];
            focusRow(event.key === 'Home' ? rows[0] : rows[rows.length - 1]);
            return;
        }

        if (event.key === ' ' || event.key === 'Enter') {
            event.preventDefault();
            toggleSelection(row.dataset.id);
        }
    }

    function handleListClick(event) {
        const row = event.target.closest('[role="option"]');

        if (!row) {
            return;
        }

        focusRow(row);
        toggleSelection(row.dataset.id);
    }

    function handleSearchInput() {
        window.clearTimeout(debounceTimer);
        debounceTimer = window.setTimeout(() => {
            query = searchInput.value.trim();
            page = 1;
            load();
        }, debounceMs);
    }

    async function handleCreate() {
        if (typeof createItem !== 'function' || !createInput) {
            return;
        }

        const name = createInput.value.trim();

        if (!name) {
            createInput.focus();
            return;
        }

        if (createButton) {
            createButton.disabled = true;
        }

        try {
            const item = await createItem(name);

            if (destroyed || !item) {
                return;
            }

            const id = String(getId(item));

            knownItems.set(id, item);

            if (!multiple) {
                selectedIds.clear();
            }

            selectedIds.add(id);

            createInput.value = '';

            // Re-query by the new item's own name rather than just
            // reloading the current page: sort order (usually by name)
            // may place it outside page 1, and this is the one query that
            // is guaranteed to find it regardless (CLAUDE.md §86 - no new
            // "jump to the page containing id X" pagination feature needed
            // just for this).
            query = getLabel(item);
            page = 1;

            if (searchInput) {
                searchInput.value = query;
            }

            await load();
            notifyChange();
        } finally {
            if (!destroyed && createButton) {
                createButton.disabled = false;
            }
        }
    }

    function handleCreateKeydown(event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            handleCreate();
        }
    }

    function handleRootClick(event) {
        if (event.target.closest('[data-mc-action="entity-picker-prev"]') && page > 1) {
            page -= 1;
            load();
            return;
        }

        if (event.target.closest('[data-mc-action="entity-picker-next"]') && page * pageSize < total) {
            page += 1;
            load();
            return;
        }

        if (event.target.closest('[data-mc-action="entity-picker-confirm"]') && typeof onConfirm === 'function') {
            onConfirm(itemsById(selectedIds));
        }

        if (event.target.closest('[data-mc-action="entity-picker-create"]')) {
            handleCreate();
        }
    }

    listEl.addEventListener('click', handleListClick);
    listEl.addEventListener('keydown', handleListKeydown);
    searchInput?.addEventListener('input', handleSearchInput);
    createInput?.addEventListener('keydown', handleCreateKeydown);
    root.addEventListener('click', handleRootClick);

    if (selectedCountEl) {
        selectedCountEl.textContent = String(selectedIds.size);
    }

    if (confirmButton && typeof onConfirm !== 'function') {
        confirmButton.hidden = true;
    }

    if (createSection) {
        createSection.hidden = typeof createItem !== 'function';
    }

    return {
        refresh() {
            return load();
        },
        getSelected() {
            return itemsById(selectedIds);
        },
        setSelected(ids) {
            selectedIds.clear();
            ids.forEach((id) => selectedIds.add(String(id)));

            listEl.querySelectorAll('[data-id]').forEach((row) => {
                setRowSelected(row, selectedIds.has(row.dataset.id));
            });

            notifyChange();
        },
        clear() {
            selectedIds.clear();
            query = '';
            page = 1;

            if (searchInput) {
                searchInput.value = '';
            }

            notifyChange();
            return load();
        },
        destroy() {
            destroyed = true;
            abortController?.abort();
            window.clearTimeout(debounceTimer);
            listEl.removeEventListener('click', handleListClick);
            listEl.removeEventListener('keydown', handleListKeydown);
            searchInput?.removeEventListener('input', handleSearchInput);
            createInput?.removeEventListener('keydown', handleCreateKeydown);
            root.removeEventListener('click', handleRootClick);
        },
    };
}
