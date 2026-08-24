/**
 * mc-media-picker - the Media UX primitive (`MINTCART_FUNCTIONAL_SCOPE.md`
 * §25, priority P1): a searchable, foldered, paginated media grid with
 * thumbnails, optional upload and safe delete, meant to replace ad-hoc
 * image pickers on Product/Category/Blocks admin screens.
 *
 * This is the sibling of `entity-picker.js` (Phase 11) for image/media
 * selection specifically: same shape (caller-supplied data source, no
 * dialog chrome of its own, WAI-ARIA listbox keyboard pattern), but a
 * thumbnail grid instead of a text list, plus folder filtering and
 * optional upload/delete affordances gated on whether the host actually
 * wired a backend for them (`CLAUDE.md` §85 - no feature offered without a
 * real consumer behind it).
 *
 * Host it inside an existing `.mc-drawer` or `.mc-modal` (Phase 4) using
 * their own triggers; call `refresh()` in response to that dialog's
 * `mc:drawer:shown` / `mc:modal:shown` event.
 *
 * Markup contract (host page provides this inside a drawer/modal body;
 * `data-mc-media-picker-*` are read-only hooks this module queries -
 * CLAUDE.md §14, no styling/behavior should be keyed off `.mc-media-picker`
 * child classes from outside this file):
 *
 *   <div class="mc-media-picker">
 *     <div class="mc-media-picker__toolbar">
 *       <input type="search" class="mc-field__control" data-mc-media-picker-search>
 *       <select class="mc-field__control" data-mc-media-picker-folder></select>
 *       <label class="mc-button mc-button--secondary">
 *         Upload
 *         <input type="file" multiple hidden data-mc-media-picker-upload>
 *       </label>
 *     </div>
 *     <div class="mc-media-picker__grid" role="listbox" data-mc-media-picker-grid></div>
 *     <p class="mc-media-picker__empty" data-mc-media-picker-empty hidden></p>
 *     <div class="mc-media-picker__pager">
 *       <button type="button" data-mc-action="media-picker-prev"></button>
 *       <span data-mc-media-picker-page-info></span>
 *       <button type="button" data-mc-action="media-picker-next"></button>
 *     </div>
 *     <div class="mc-media-picker__footer">
 *       <span data-mc-media-picker-selected-count></span>
 *       <button type="button" data-mc-action="media-picker-delete"></button>
 *       <button type="button" data-mc-action="media-picker-confirm"></button>
 *     </div>
 *   </div>
 *
 * Grid cells are rendered by this module as
 * `<button type="button" role="option" aria-selected="…" data-id="…">`,
 * styled by `media-picker.css`. Keyboard: ArrowLeft/ArrowRight/ArrowUp/
 * ArrowDown/Home/End move a roving tabindex between cells (WAI-ARIA
 * listbox pattern, treated as a single wrapped row since the grid's real
 * column count depends on container width); Enter/Space toggles the
 * focused cell's selection - the same interaction a mouse click gives, so
 * there is no non-keyboard-only affordance (Functional Scope §27).
 *
 * `mc:media-picker:change` (bubbling, `detail: { selected: Item[] }`) fires
 * on `root` every time the selection changes.
 *
 * Contract: docs/developer/components/media-picker.md
 */

const SELECTORS = {
    search: '[data-mc-media-picker-search]',
    folder: '[data-mc-media-picker-folder]',
    upload: '[data-mc-media-picker-upload]',
    grid: '[data-mc-media-picker-grid]',
    empty: '[data-mc-media-picker-empty]',
    pageInfo: '[data-mc-media-picker-page-info]',
    selectedCount: '[data-mc-media-picker-selected-count]',
};

export function createMediaPicker(root, {
    fetchItems,
    fetchFolders = null,
    uploadFiles = null,
    deleteItems = null,
    multiple = true,
    pageSize = 24,
    selected = [],
    debounceMs = 250,
    getId = (item) => item.id,
    getLabel = (item) => item.name,
    getThumbnail = (item) => item.thumbnail ?? item.image ?? null,
    getMeta = (item) => item.meta ?? null,
    onChange = null,
    onConfirm = null,
} = {}) {
    if (typeof fetchItems !== 'function') {
        throw new TypeError('createMediaPicker() requires a fetchItems(...) data source');
    }

    const searchInput = root.querySelector(SELECTORS.search);
    const folderSelect = root.querySelector(SELECTORS.folder);
    const uploadInput = root.querySelector(SELECTORS.upload);
    const gridEl = root.querySelector(SELECTORS.grid);
    const emptyEl = root.querySelector(SELECTORS.empty);
    const prevButton = root.querySelector('[data-mc-action="media-picker-prev"]');
    const nextButton = root.querySelector('[data-mc-action="media-picker-next"]');
    const pageInfoEl = root.querySelector(SELECTORS.pageInfo);
    const selectedCountEl = root.querySelector(SELECTORS.selectedCount);
    const confirmButton = root.querySelector('[data-mc-action="media-picker-confirm"]');
    const deleteButton = root.querySelector('[data-mc-action="media-picker-delete"]');

    if (!gridEl) {
        throw new Error('createMediaPicker() requires a [data-mc-media-picker-grid] element in root');
    }

    const knownItems = new Map();
    const selectedIds = new Set(selected);

    let query = '';
    let folder = '';
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

        root.dispatchEvent(new CustomEvent('mc:media-picker:change', {
            bubbles: true,
            detail: { selected: itemsById(selectedIds) },
        }));

        if (selectedCountEl) {
            selectedCountEl.textContent = String(selectedIds.size);
        }

        if (deleteButton) {
            deleteButton.disabled = selectedIds.size === 0;
        }
    }

    function setCellSelected(cell, isSelected) {
        cell.setAttribute('aria-selected', isSelected ? 'true' : 'false');
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

        gridEl.querySelectorAll('[data-id]').forEach((cell) => {
            setCellSelected(cell, selectedIds.has(cell.dataset.id));
        });

        notifyChange();
    }

    function focusCell(cell) {
        gridEl.querySelectorAll('[role="option"]').forEach((el) => {
            el.tabIndex = el === cell ? 0 : -1;
        });

        cell?.focus();
    }

    function renderItems(items) {
        gridEl.innerHTML = '';

        items.forEach((item) => {
            const id = String(getId(item));

            knownItems.set(id, item);

            const cell = document.createElement('button');
            cell.type = 'button';
            cell.className = 'mc-media-picker__item';
            cell.setAttribute('role', 'option');
            cell.dataset.id = id;
            cell.tabIndex = -1;
            setCellSelected(cell, selectedIds.has(id));

            const thumbnail = getThumbnail(item);

            if (thumbnail) {
                const img = document.createElement('img');
                img.className = 'mc-media-picker__item-thumbnail';
                img.src = thumbnail;
                img.alt = '';
                img.loading = 'lazy';
                cell.appendChild(img);
            }

            const label = document.createElement('span');
            label.className = 'mc-media-picker__item-label';
            label.textContent = getLabel(item);
            cell.appendChild(label);

            const meta = getMeta(item);

            if (meta) {
                const metaEl = document.createElement('span');
                metaEl.className = 'mc-media-picker__item-meta';
                metaEl.textContent = meta;
                cell.appendChild(metaEl);
            }

            gridEl.appendChild(cell);
        });

        const firstCell = gridEl.querySelector('[role="option"]');

        if (firstCell) {
            firstCell.tabIndex = 0;
        }

        if (emptyEl) {
            emptyEl.hidden = items.length > 0;
        }
    }

    async function loadFolders() {
        if (!folderSelect || typeof fetchFolders !== 'function') {
            return;
        }

        const folders = await fetchFolders();
        const currentValue = folderSelect.value;

        folderSelect.innerHTML = '';

        const allOption = document.createElement('option');
        allOption.value = '';
        allOption.textContent = 'All folders';
        folderSelect.appendChild(allOption);

        folders.forEach((entry) => {
            const option = document.createElement('option');
            option.value = String(entry.id);
            option.textContent = entry.label;
            folderSelect.appendChild(option);
        });

        folderSelect.value = currentValue;
    }

    async function load() {
        abortController?.abort();
        abortController = new AbortController();

        root.classList.add('is-loading');

        try {
            const result = await fetchItems({
                query,
                folder,
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

    function handleGridKeydown(event) {
        const cell = event.target.closest('[role="option"]');

        if (!cell) {
            return;
        }

        if (['ArrowDown', 'ArrowUp', 'ArrowLeft', 'ArrowRight'].includes(event.key)) {
            event.preventDefault();
            const cells = [...gridEl.querySelectorAll('[role="option"]')];
            const index = cells.indexOf(cell);
            const forward = event.key === 'ArrowDown' || event.key === 'ArrowRight';
            const nextIndex = forward
                ? Math.min(index + 1, cells.length - 1)
                : Math.max(index - 1, 0);

            focusCell(cells[nextIndex]);
            return;
        }

        if (event.key === 'Home' || event.key === 'End') {
            event.preventDefault();
            const cells = [...gridEl.querySelectorAll('[role="option"]')];
            focusCell(event.key === 'Home' ? cells[0] : cells[cells.length - 1]);
            return;
        }

        if (event.key === ' ' || event.key === 'Enter') {
            event.preventDefault();
            toggleSelection(cell.dataset.id);
        }
    }

    function handleGridClick(event) {
        const cell = event.target.closest('[role="option"]');

        if (!cell) {
            return;
        }

        focusCell(cell);
        toggleSelection(cell.dataset.id);
    }

    function handleSearchInput() {
        window.clearTimeout(debounceTimer);
        debounceTimer = window.setTimeout(() => {
            query = searchInput.value.trim();
            page = 1;
            load();
        }, debounceMs);
    }

    function handleFolderChange() {
        folder = folderSelect.value;
        page = 1;
        load();
    }

    async function handleUploadChange() {
        const files = [...(uploadInput.files ?? [])];

        if (files.length === 0 || typeof uploadFiles !== 'function') {
            return;
        }

        root.classList.add('is-loading');

        try {
            await uploadFiles(files);
        } finally {
            uploadInput.value = '';

            if (!destroyed) {
                root.classList.remove('is-loading');
                await load();
            }
        }
    }

    function handleRootClick(event) {
        if (event.target.closest('[data-mc-action="media-picker-prev"]') && page > 1) {
            page -= 1;
            load();
            return;
        }

        if (event.target.closest('[data-mc-action="media-picker-next"]') && page * pageSize < total) {
            page += 1;
            load();
            return;
        }

        if (event.target.closest('[data-mc-action="media-picker-confirm"]') && typeof onConfirm === 'function') {
            onConfirm(itemsById(selectedIds));
            return;
        }

        if (event.target.closest('[data-mc-action="media-picker-delete"]') && typeof deleteItems === 'function' && selectedIds.size > 0) {
            const toDelete = itemsById(selectedIds);

            Promise.resolve(deleteItems(toDelete)).then(() => {
                if (destroyed) {
                    return;
                }

                selectedIds.clear();
                notifyChange();
                return load();
            });
        }
    }

    gridEl.addEventListener('click', handleGridClick);
    gridEl.addEventListener('keydown', handleGridKeydown);
    searchInput?.addEventListener('input', handleSearchInput);
    folderSelect?.addEventListener('change', handleFolderChange);
    uploadInput?.addEventListener('change', handleUploadChange);
    root.addEventListener('click', handleRootClick);

    if (selectedCountEl) {
        selectedCountEl.textContent = String(selectedIds.size);
    }

    if (confirmButton && typeof onConfirm !== 'function') {
        confirmButton.hidden = true;
    }

    if (uploadInput && typeof uploadFiles !== 'function') {
        uploadInput.closest('label')?.setAttribute('hidden', '');
    }

    if (deleteButton) {
        if (typeof deleteItems !== 'function') {
            deleteButton.hidden = true;
        } else {
            deleteButton.disabled = selectedIds.size === 0;
        }
    }

    if (folderSelect && typeof fetchFolders !== 'function') {
        folderSelect.hidden = true;
    }

    loadFolders();

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

            gridEl.querySelectorAll('[data-id]').forEach((cell) => {
                setCellSelected(cell, selectedIds.has(cell.dataset.id));
            });

            notifyChange();
        },
        clear() {
            selectedIds.clear();
            query = '';
            folder = '';
            page = 1;

            if (searchInput) {
                searchInput.value = '';
            }

            if (folderSelect) {
                folderSelect.value = '';
            }

            notifyChange();
            return load();
        },
        destroy() {
            destroyed = true;
            abortController?.abort();
            window.clearTimeout(debounceTimer);
            gridEl.removeEventListener('click', handleGridClick);
            gridEl.removeEventListener('keydown', handleGridKeydown);
            searchInput?.removeEventListener('input', handleSearchInput);
            folderSelect?.removeEventListener('change', handleFolderChange);
            uploadInput?.removeEventListener('change', handleUploadChange);
            root.removeEventListener('click', handleRootClick);
        },
    };
}
