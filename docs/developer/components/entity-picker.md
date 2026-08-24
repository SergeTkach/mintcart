# `mc-entity-picker`

MintCart's unified entity picker primitive — a single searchable,
paginated, multi-select list component meant to replace the old
per-entity autocomplete pattern for products, categories, manufacturers,
attributes, options, information pages and customers alike
(`MINTCART_FUNCTIONAL_SCOPE.md` §8, `MINTCART_TECHNICAL_PLAN.md` §20,
priority **P0**).

This is the Phase 11 "admin primitive" half of the picker: the list
behavior — search, pagination, keyboard-navigable selection — against a
caller-supplied data source. It does not know which entity type it is
listing, does not call any specific backend endpoint, and does not own
any dialog chrome. Phase 12 ("Entity Picker") is where a real admin
screen supplies a `fetchItems` source per entity type (products,
categories, …) and renders this inside an actual product/category/etc.
field.

## Why one implementation, not one per entity type

`CLAUDE.md` §17 and `MINTCART_TECHNICAL_PLAN.md` §20 both call for a
single Entity Picker, not a bespoke autocomplete per screen. Everything
entity-specific (the search endpoint, how an item's label/image/id are
read) is supplied via options (`fetchItems`, `getId`, `getLabel`,
`getImage`), so the same `createEntityPicker()` call serves a product
picker and a customer picker alike — no per-entity subclassing.

## Why it owns no dialog chrome

`mc-modal` and `mc-drawer` (Phase 4) already solve "show/hide a panel,
trap focus, close on Escape/backdrop click" — reimplementing that inside
the picker would duplicate them (`MINTCART_IMPLEMENTATION_GUIDE.md` §9).
Host the picker's markup inside an existing `.mc-drawer` or `.mc-modal`
body and call `picker.refresh()` in response to that dialog's own
`mc:drawer:shown` / `mc:modal:shown` event, so the first fetch happens
when the picker actually becomes visible, not on page load.

## Markup contract

```html
<div class="mc-entity-picker">
    <input
        type="search"
        class="mc-field__control"
        data-mc-entity-picker-search
        aria-label="Search products"
    >

    <ul
        class="mc-entity-picker__list"
        role="listbox"
        aria-multiselectable="true"
        aria-label="Products"
        data-mc-entity-picker-list
    ></ul>

    <p class="mc-entity-picker__empty" data-mc-entity-picker-empty hidden>
        No results.
    </p>

    <div class="mc-entity-picker__pager">
        <button type="button" class="mc-button mc-button--secondary" data-mc-action="entity-picker-prev">
            Previous
        </button>
        <span class="mc-entity-picker__page-info" data-mc-entity-picker-page-info></span>
        <button type="button" class="mc-button mc-button--secondary" data-mc-action="entity-picker-next">
            Next
        </button>
    </div>

    <div class="mc-entity-picker__footer">
        <span class="mc-entity-picker__selected-count" data-mc-entity-picker-selected-count></span>
        <button type="button" class="mc-button mc-button--primary" data-mc-action="entity-picker-confirm">
            Add selected
        </button>
    </div>
</div>
```

Item rows (`<li role="option" aria-selected="…" data-id="…">`) are
rendered entirely by `entity-picker.js` — their content is always
data-driven, the same reasoning `mc-confirm`'s dialog markup follows —
so there is no static row markup for a host page to write.

## Usage

```js
import { createEntityPicker } from './components/entity-picker.js';
import { Offcanvas } from 'bootstrap';

const drawerEl = document.querySelector('#product-picker');
const picker = createEntityPicker(drawerEl.querySelector('.mc-entity-picker'), {
    async fetchItems({ query, page, pageSize, signal }) {
        const params = new URLSearchParams({ filter_name: query, page, limit: pageSize });
        const response = await fetch(`index.php?route=catalog/product.autocomplete&${params}`, { signal });
        const data = await response.json();

        return { items: data.products, total: data.total };
    },
    multiple: true,
    getId: (product) => product.product_id,
    getLabel: (product) => product.name,
    getImage: (product) => product.image,
    onConfirm(selected) {
        // insert `selected` into the host form, then close the drawer
        Offcanvas.getInstance(drawerEl)?.hide();
    },
});

drawerEl.addEventListener('mc:drawer:shown', () => picker.refresh());
drawerEl.addEventListener('mc:entity-picker:change', (event) => {
    console.log('selected:', event.detail.selected);
});
```

## Behavior

- **Search** — the `input` event on `[data-mc-entity-picker-search]` is
  debounced (250ms default, `debounceMs` option), resets to page 1, and
  re-fetches.
- **Pagination** — `data-mc-action="entity-picker-prev"` /
  `"entity-picker-next"`, disabled at the first/last page from the
  `total`/`pageSize` the data source reports. In-flight requests are
  cancelled via `AbortController` when a newer search/page request
  starts, so a slow first page can never overwrite a later one
  (`MINTCART_IMPLEMENTATION_GUIDE.md` §3's `AbortController` allowance).
- **Selection** — click or Enter/Space on a row toggles it.
  `multiple: false` makes selecting a row clear any previous selection
  (single-select/radio behavior). Selection is tracked by id across
  pages/searches — paging away and back preserves what was already
  chosen, and `getSelected()` always returns full item objects (not just
  ids), reconstructed from every page fetched so far.
- **Keyboard navigation** — ArrowUp/ArrowDown/Home/End move a roving
  `tabindex` between rows (the WAI-ARIA listbox pattern); Enter/Space
  toggles the focused row. This is the same interaction a mouse click
  gives — no feature is mouse/touch-only (Functional Scope §27).
- **Events** — `mc:entity-picker:change` (bubbling,
  `detail: { selected: Item[] }`) fires on `root` whenever selection
  changes, whether from a click, keyboard, or a programmatic
  `setSelected()` call.

## Returned instance API

- `refresh()` — re-run the current query/page fetch (call on dialog open)
- `getSelected()` — full item objects for every currently selected id
- `setSelected(ids)` — set the initial selection (e.g. editing an
  existing product-to-category association)
- `clear()` — reset search text, page and selection, then reload
- `destroy()` — remove all listeners (call if the host drawer/modal is
  removed from the DOM)

## Not wired into `catalog.js`/`admin.js` yet

`createEntityPicker` is exported for direct import, not auto-initialized
from either entry point — it needs a caller-supplied `fetchItems` data
source and a root element that doesn't exist until a real Phase 12 admin
screen renders one. Per `CLAUDE.md` §85, the import is added to whichever
screen needs it once that consumer exists, not ahead of time.

## Definition of Done checklist (`MINTCART_IMPLEMENTATION_GUIDE.md` §79)

- [x] Documented contract (this file)
- [x] `mc-*` BEM markup (`mc-entity-picker`, `mc-entity-picker__list`,
      `__item`, `__item-image`, `__item-label`, `__empty`, `__pager`,
      `__page-info`, `__footer`, `__selected-count`)
- [x] JS reads `data-mc-entity-picker-*`/`data-mc-action` hooks, never a
      CSS class, as the trigger/query contract
- [x] Colors/spacing/radius read from `--mc-*` tokens
- [x] Keyboard usable — WAI-ARIA listbox roving-tabindex pattern
      (ArrowUp/ArrowDown/Home/End/Enter/Space), `:focus-visible` outline
- [x] Selection state uses `aria-selected` (semantic attribute) rather
      than a JS-added modifier class, per CLAUDE.md §13
- [x] Mobile checked — the list/pager/footer are a single flex column
      with no fixed desktop-only widths; the host drawer/modal supplies
      any viewport-specific sizing
- [x] No Bootstrap 3 dependency
- [x] No OCMOD required
- [ ] Theme override checked — deferred; this is an admin primitive with
      no admin screen/theme override mechanism yet (Phase 6 Admin UX
      / Phase 12 not started)
- [ ] Live data source — deferred to Phase 12; today's `fetchItems`
      contract is exercised only by the usage example above, not a real
      endpoint
