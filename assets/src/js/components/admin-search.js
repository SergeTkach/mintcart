/**
 * mc-admin-search — admin header "quick settings search"
 * (`MINTCART_FUNCTIONAL_SCOPE.md` §23, P1). Debounced fetch against
 * `mintcart/adminsearch` (`MintCart\Admin\SettingsSearchIndex`), rendered
 * as a results dropdown; Enter/click navigates straight to the matched
 * admin page.
 *
 * Markup contract (host page — `admin/view/template/common/header.twig` —
 * provides this; `data-mc-admin-search-*` are read-only hooks this module
 * queries, same convention as `entity-picker.js`):
 *
 *   <li class="mc-admin-search" data-mc-admin-search data-mc-admin-search-url="...">
 *     <input type="search" data-mc-admin-search-input>
 *     <ul class="mc-admin-search__results" data-mc-admin-search-results hidden></ul>
 *     <p class="mc-admin-search__empty" data-mc-admin-search-empty hidden></p>
 *   </li>
 *
 * `data-mc-admin-search-url` already carries this session's `user_token`
 * (`ControllerCommonHeader::index()`); this module only ever appends
 * `&q=<query>` to it. The endpoint returns `{ results: [{ label, url }] }`
 * — see `admin/controller/mintcart/adminsearch.php`.
 *
 * Contract: docs/developer/admin/settings-search.md
 */

const SELECTORS = {
    root: '[data-mc-admin-search]',
    input: '[data-mc-admin-search-input]',
    results: '[data-mc-admin-search-results]',
    empty: '[data-mc-admin-search-empty]',
};

export function initAdminSearch(context = document) {
    context.querySelectorAll(SELECTORS.root).forEach((root) => {
        const url = root.dataset.mcAdminSearchUrl;
        const input = root.querySelector(SELECTORS.input);
        const resultsEl = root.querySelector(SELECTORS.results);
        const emptyEl = root.querySelector(SELECTORS.empty);

        if (!url || !input || !resultsEl) {
            return;
        }

        let debounceTimer = null;
        let abortController = null;
        let activeIndex = -1;
        let items = [];

        function closeResults() {
            resultsEl.hidden = true;
            resultsEl.innerHTML = '';

            if (emptyEl) {
                emptyEl.hidden = true;
            }

            activeIndex = -1;
            items = [];
        }

        function renderResults(results) {
            items = results;
            activeIndex = -1;
            resultsEl.innerHTML = '';

            if (results.length === 0) {
                resultsEl.hidden = true;

                if (emptyEl) {
                    emptyEl.hidden = false;
                }

                return;
            }

            if (emptyEl) {
                emptyEl.hidden = true;
            }

            results.forEach((result) => {
                const li = document.createElement('li');
                li.setAttribute('role', 'option');

                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'mc-admin-search__result';
                button.textContent = result.label;
                button.addEventListener('click', () => {
                    window.location.href = result.url;
                });

                li.appendChild(button);
                resultsEl.appendChild(li);
            });

            resultsEl.hidden = false;
        }

        function setActive(index) {
            const buttons = resultsEl.querySelectorAll('.mc-admin-search__result');

            buttons.forEach((button, i) => {
                button.classList.toggle('is-active', i === index);
            });

            activeIndex = index;
        }

        async function search(query) {
            abortController?.abort();
            abortController = new AbortController();

            try {
                const response = await fetch(`${url}&q=${encodeURIComponent(query)}`, {
                    signal: abortController.signal,
                });

                const data = await response.json();

                renderResults(Array.isArray(data.results) ? data.results : []);
            } catch (error) {
                if (error?.name !== 'AbortError') {
                    throw error;
                }
            }
        }

        function handleInput() {
            window.clearTimeout(debounceTimer);

            const query = input.value.trim();

            if (query === '') {
                closeResults();
                return;
            }

            debounceTimer = window.setTimeout(() => {
                search(query);
            }, 250);
        }

        function handleKeydown(event) {
            if (resultsEl.hidden || items.length === 0) {
                return;
            }

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                setActive(Math.min(activeIndex + 1, items.length - 1));
                return;
            }

            if (event.key === 'ArrowUp') {
                event.preventDefault();
                setActive(Math.max(activeIndex - 1, 0));
                return;
            }

            if (event.key === 'Enter' && activeIndex >= 0) {
                event.preventDefault();
                window.location.href = items[activeIndex].url;
                return;
            }

            if (event.key === 'Escape') {
                closeResults();
            }
        }

        input.addEventListener('input', handleInput);
        input.addEventListener('keydown', handleKeydown);

        document.addEventListener('click', (event) => {
            if (!root.contains(event.target)) {
                closeResults();
            }
        });
    });
}
