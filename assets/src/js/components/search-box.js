/**
 * mc-search-box - AJAX search-as-you-type dropdown
 * (`MINTCART_FUNCTIONAL_SCOPE.md` §14, `MINTCART_TECHNICAL_PLAN.md` §26,
 * `MINTCART_IMPLEMENTATION_GUIDE.md` §59), the "natural next increment"
 * `docs/developer/commerce/search-service.md` flagged once
 * `MintCart\Commerce\SearchService` + `mintcart/search/suggest` existed
 * with no UI consumer yet.
 *
 * Talks directly to `mintcart/search/suggest` (no `../commerce/` data-layer
 * module of its own, unlike `cart-client.js`) - the response is already the
 * exact shape this component renders (`{ query, count, items[] }`,
 * `docs/developer/commerce/search-service.md`), so there is no
 * normalization step a shared client would add.
 *
 * Markup contract (`data-mc-search-box-*` are read-only hooks this module
 * queries - CLAUDE.md §14, no styling/behavior should be keyed off
 * `.mc-search-box` child classes from outside this file):
 *
 *   <div class="mc-search-box" data-mc-search-box>
 *     <input type="search" class="mc-search-box__input mc-field__control"
 *            name="search" data-mc-search-box-input>
 *     <div class="mc-search-box__results" data-mc-search-box-results hidden>
 *       <ul class="mc-search-box__list" role="listbox" data-mc-search-box-list></ul>
 *       <p class="mc-search-box__empty" data-mc-search-box-empty hidden></p>
 *       <a class="mc-search-box__view-all" data-mc-search-box-view-all hidden></a>
 *     </div>
 *   </div>
 *
 * Item rows (`<li role="option" data-mc-search-box-url="…">`) are always
 * rendered by this module - there is no static row markup for a host page
 * to write, same reasoning `entity-picker.js` documents for its own list.
 *
 * Behavior:
 * - Debounced (250ms default) `input` on `[data-mc-search-box-input]`.
 *   Queries shorter than 2 characters never reach the network - mirrors
 *   `SearchService::isQueryValid()` client-side so a stray keystroke
 *   doesn't fire a request the server would just bounce empty anyway.
 * - In-flight requests are cancelled via `AbortController` when a newer
 *   keystroke/query starts, so a slow first response can never overwrite a
 *   later one (`MINTCART_IMPLEMENTATION_GUIDE.md` §3's `AbortController`
 *   allowance) - same technique `entity-picker.js` already uses.
 * - Keyboard: ArrowDown from the input moves into the first result;
 *   ArrowUp/ArrowDown move a roving `tabindex` between results (WAI-ARIA
 *   listbox pattern, same as `entity-picker.js`); Enter on a focused result
 *   navigates to it; Escape closes the dropdown and returns focus to the
 *   input - no feature is mouse/touch-only (Functional Scope §27).
 * - Clicking/focusing outside the root closes the dropdown without
 *   clearing the input's typed text.
 * - `mc:search-box:results` (bubbling, `detail: { query, count, items }`)
 *   fires on `root` after every successful fetch, win or empty, so a host
 *   page can react (e.g. a result counter) without re-querying the network.
 *
 * Not auto-wired into any live template yet - `mint_default`'s header
 * still renders the legacy jQuery-autocomplete search fragment
 * (`docs/developer/components/header.md` "Why widgets stay legacy
 * fragments"). `initSearchBoxes()` is still wired into `catalog.js` now
 * (progressive enhancement: it only ever activates on a
 * `[data-mc-search-box]` root, and none exists in any shipped template
 * yet) so a future header increment can drop the markup contract above
 * into `mint_default/template/common/header.twig` with zero JS changes -
 * the same "ship ahead of the template, behind a real Twig contract"
 * approach `mc-quantity`/`mc-modal` already took in Phase 4.
 *
 * Contract: docs/developer/components/search-box.md
 */

const SELECTORS = {
    input: '[data-mc-search-box-input]',
    results: '[data-mc-search-box-results]',
    list: '[data-mc-search-box-list]',
    empty: '[data-mc-search-box-empty]',
    viewAll: '[data-mc-search-box-view-all]',
};

const MIN_QUERY_LENGTH = 2;

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

export function createSearchBox(root, {
    action = 'mintcart/search/suggest',
    searchRoute = 'product/search',
    debounceMs = 250,
    onResults = null,
} = {}) {
    const input = root.querySelector(SELECTORS.input);
    const resultsEl = root.querySelector(SELECTORS.results);
    const listEl = root.querySelector(SELECTORS.list);
    const emptyEl = root.querySelector(SELECTORS.empty);
    const viewAllEl = root.querySelector(SELECTORS.viewAll);

    if (!input || !resultsEl || !listEl) {
        throw new Error('createSearchBox() requires [data-mc-search-box-input/-results/-list] elements in root');
    }

    let query = '';
    let debounceTimer = null;
    let abortController = null;
    let destroyed = false;

    function open() {
        resultsEl.hidden = false;
        root.classList.add('is-open');
    }

    function close() {
        resultsEl.hidden = true;
        root.classList.remove('is-open');
    }

    function rows() {
        return [...listEl.querySelectorAll('[role="option"]')];
    }

    function focusRow(row) {
        rows().forEach((el) => {
            el.tabIndex = el === row ? 0 : -1;
        });

        row?.focus();
    }

    function renderItems(items, count) {
        listEl.innerHTML = '';

        items.forEach((item) => {
            const row = document.createElement('li');
            row.className = 'mc-search-box__item';
            row.setAttribute('role', 'option');
            row.tabIndex = -1;
            row.dataset.mcSearchBoxUrl = item.url;

            const imageHtml = item.image
                ? `<img class="mc-search-box__item-image" src="${escapeHtml(item.image)}" alt="">`
                : '';

            const priceHtml = item.special
                ? `<span class="mc-price mc-price--special mc-search-box__item-price">
                    <span class="mc-price__current">${escapeHtml(item.special)}</span>
                    <span class="mc-price__original">${escapeHtml(item.price)}</span>
                </span>`
                : `<span class="mc-price mc-search-box__item-price">
                    <span class="mc-price__current">${escapeHtml(item.price)}</span>
                </span>`;

            row.innerHTML = `
                ${imageHtml}
                <span class="mc-search-box__item-body">
                    <span class="mc-search-box__item-name">${escapeHtml(item.name)}</span>
                    ${priceHtml}
                </span>
            `;

            listEl.appendChild(row);
        });

        const firstRow = listEl.querySelector('[role="option"]');

        if (firstRow) {
            firstRow.tabIndex = 0;
        }

        if (emptyEl) {
            emptyEl.hidden = items.length > 0;
        }

        if (viewAllEl) {
            if (count > items.length) {
                viewAllEl.hidden = false;
                viewAllEl.href = `index.php?route=${searchRoute}&search=${encodeURIComponent(query)}`;
            } else {
                viewAllEl.hidden = true;
            }
        }
    }

    async function load() {
        if (query.length < MIN_QUERY_LENGTH) {
            abortController?.abort();
            close();
            return;
        }

        abortController?.abort();
        abortController = new AbortController();

        root.classList.add('is-loading');

        try {
            const response = await fetch(
                `index.php?route=${action}&search=${encodeURIComponent(query)}`,
                { headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: abortController.signal }
            );

            const result = await response.json();

            if (destroyed) {
                return;
            }

            renderItems(result.items ?? [], result.count ?? 0);
            open();

            if (typeof onResults === 'function') {
                onResults(result);
            }

            root.dispatchEvent(new CustomEvent('mc:search-box:results', {
                bubbles: true,
                detail: { query, count: result.count ?? 0, items: result.items ?? [] },
            }));
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

    function handleInput() {
        window.clearTimeout(debounceTimer);
        debounceTimer = window.setTimeout(() => {
            query = input.value.trim();
            load();
        }, debounceMs);
    }

    function handleInputKeydown(event) {
        if (event.key === 'ArrowDown' && !resultsEl.hidden) {
            const first = rows()[0];

            if (first) {
                event.preventDefault();
                focusRow(first);
            }

            return;
        }

        // Enter in the field goes to the full results page (a focused suggestion has its own handler).
        if (event.key === 'Enter' && input.value.trim() !== '') {
            event.preventDefault();
            window.location.href = `index.php?route=${searchRoute}&search=${encodeURIComponent(input.value.trim())}`;
            return;
        }

        if (event.key === 'Escape') {
            close();
            input.focus();
        }
    }

    function handleListKeydown(event) {
        const row = event.target.closest('[role="option"]');

        if (!row) {
            return;
        }

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            const list = rows();
            const index = list.indexOf(row);

            if (event.key === 'ArrowUp' && index === 0) {
                focusRow(null);
                input.focus();
                return;
            }

            const nextIndex = event.key === 'ArrowDown'
                ? Math.min(index + 1, list.length - 1)
                : Math.max(index - 1, 0);

            focusRow(list[nextIndex]);
            return;
        }

        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            window.location.href = row.dataset.mcSearchBoxUrl;
            return;
        }

        if (event.key === 'Escape') {
            close();
            input.focus();
        }
    }

    function handleListClick(event) {
        const row = event.target.closest('[role="option"]');

        if (row) {
            window.location.href = row.dataset.mcSearchBoxUrl;
        }
    }

    function handleFocusOut(event) {
        if (!root.contains(event.relatedTarget)) {
            close();
        }
    }

    // The submit button (themes that render one: `[data-mc-search-box-submit]`).
    root.querySelector('[data-mc-search-box-submit]')?.addEventListener('click', () => {
        if (input.value.trim() === '') {
            input.focus();
            return;
        }

        window.location.href = `index.php?route=${searchRoute}&search=${encodeURIComponent(input.value.trim())}`;
    });

    input.addEventListener('input', handleInput);
    input.addEventListener('keydown', handleInputKeydown);
    listEl.addEventListener('click', handleListClick);
    listEl.addEventListener('keydown', handleListKeydown);
    root.addEventListener('focusout', handleFocusOut);

    return {
        refresh() {
            return load();
        },
        close,
        destroy() {
            destroyed = true;
            abortController?.abort();
            window.clearTimeout(debounceTimer);
            input.removeEventListener('input', handleInput);
            input.removeEventListener('keydown', handleInputKeydown);
            listEl.removeEventListener('click', handleListClick);
            listEl.removeEventListener('keydown', handleListKeydown);
            root.removeEventListener('focusout', handleFocusOut);
        },
    };
}

export function initSearchBoxes(root = document) {
    root.querySelectorAll('[data-mc-search-box]').forEach((el) => {
        if (el.mcSearchBox) {
            return;
        }

        el.mcSearchBox = createSearchBox(el);
    });
}
