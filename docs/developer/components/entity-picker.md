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
- [~] Live data source — the `category` backend is real (see "Admin
      backends" below); no live *screen* wires `createEntityPicker()` to
      it yet, so the example above is still illustrative pending that.

## Admin backends

Phase 12's remaining scope is "a real `fetchItems` backend per entity
type, then wire it into a live admin screen." The **category** backend is
the first:

```
GET index.php?route=mintcart/entity/category&user_token=…&filter_name=…&page=…&limit=…
```

`upload/admin/controller/mintcart/entity.php` (`ControllerMintcartEntity::category()`)
wraps the existing `ModelCatalogCategory::getCategories()` call — it never
queries the database directly — and normalizes the rows via
`MintCart\Design\CategoryPickerProvider`
(`upload/system/library/mintcart/design/categorypickerprovider.php`, no
OpenCart dependency, unit-tested in
`tests/mintcart/design/category-picker-provider-test.php`) into exactly
the `{items: [{id, name, image}], total}` shape `createEntityPicker()`'s
`fetchItems` expects out of the box (no custom `getId`/`getLabel`/`getImage`
needed for this one — `image` is always `null`, since
`getCategories()`'s query has no image column to read).

**Why a new route instead of reusing `catalog/category/autocomplete`:**
that action already has five other admin-template consumers
(`product_form.twig`'s manufacturer/category fields, `category_form.twig`'s
parent picker, `coupon_form.twig`, and two extension templates), returning
a plain `[{category_id, name}]` array with no pagination. Reshaping it to
`{items, total}` would be a breaking change to all five
(`CLAUDE.md` §10) — safer to add a fully-additive route that wraps the
same underlying model call, the same pattern
`upload/catalog/controller/mintcart/cart.php` already established for
`CartService` (a new, additive controller rather than modifying
`checkout/cart.php`).

**Why paging happens in PHP, not SQL:** `ModelCatalogCategory::getCategories()`
does support `start`/`limit` at the SQL level, but the model has no
*filtered* total-count method to pair with it — only
`getTotalCategories()` (unfiltered, store-wide) and
`getTotalCategoriesByLayoutId()` (filtered by layout, not name). Adding a
new filtered-count method to `ModelCatalogCategory` would be a genuine
OpenCart-core-file modification for a single consumer; `CategoryPickerProvider`
instead takes the full filtered (but unpaged) row set and slices/counts it
in PHP. Category lists are small enough (dozens to low hundreds, even for
a large catalog) that this isn't a real cost today — if a store's category
count ever makes this worth revisiting, adding a filtered count method to
the model (mirroring `getTotalCategoriesByLayoutId()`'s shape) is the
natural fix, once a second `*PickerProvider` needs the same thing.

**Permission required before this endpoint works:** like every other
MintCart admin route, `mintcart/entity` is gated by OpenCart's own
route-based ACL (`admin/controller/startup/permission.php`). A fresh
install's user groups do not have `mintcart/entity` access listed yet —
this is completely normal for any *new* admin controller, not a MintCart
gap: OpenCart discovers routes by scanning `admin/controller/**` and lists
them in **Users → User Groups → Permissions** for an admin to grant. Grant
`mintcart/entity` **Access** to a user group before testing this endpoint
or wiring a screen to it.

**Not done yet, by design:**
- No live admin screen calls `createEntityPicker()` against this endpoint.
  The identified first real consumer is `product_form.twig`'s **Category**
  multi-select (`upload/admin/view/template/catalog/product_form.twig`,
  the `#input-category`/`#product-category` well + its jQuery UI
  `autocomplete()` block) — it already has the right UI shape (search +
  multi-select + removable tags), so swapping it for a `McEntityPicker`
  mount inside an `mc-drawer` is closer to a drop-in replacement than a
  new pattern, and it wouldn't need any change to `product_form.php`'s
  save logic (the picker would keep emitting the same
  `product_category[]` hidden inputs the form already reads on submit).
- **Deliberately not attempted this run:** actually wiring it. Doing so
  needs MintCart's own compiled bundle (`assets/dist/{css,js}`) loaded on
  an admin page, and *nothing enqueues it there yet* — the one prior
  attempt to enqueue the shared `index.min.css` bundle on a storefront
  page was reverted after it broke legacy Bootstrap 3 widgets by loading
  Bootstrap 5's CSS reset/base layer alongside them (see
  `docs/developer/components/header.md` "Known gap" and
  `upload/catalog/view/theme/mint_default/template/common/header.twig`'s
  own comment) — admin pages are equally Bootstrap-3-based, so the same
  conflict risk applies, and this environment still has no
  MySQL/`vendor/twig/twig` to verify a fix visually. Wiring this consumer
  live should wait for that enqueue problem to be solved first (or scope
  a narrow, verified fix for it as part of the same increment) rather than
  shipping markup that would silently render unstyled/broken on a real
  install.
- Filters beyond search text, and the create-new-flow (add a category
  inline from the picker) — no requirement identified yet for this first
  consumer.
- The other entity types (products, manufacturers, attributes, options,
  information pages, customers) each need their own backend the same
  shape as this one.
