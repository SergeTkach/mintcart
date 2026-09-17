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

## Not auto-initialized — exposed on `window.MintCart` for legacy templates

`createEntityPicker` still isn't auto-initialized the way `initModalTriggers()`
and friends are — it needs a caller-supplied `fetchItems` data source and
options (`getId`/`getLabel`/`onConfirm`/...) specific to whichever field is
hosting it, so each consumer calls it directly with its own options.

What changed once a real consumer existed (`product_form.twig`'s Category
field, see "Admin backends" below): `assets/src/js/admin.js` now imports it
and assigns `window.MintCart.createEntityPicker = createEntityPicker`.
Admin Twig templates use plain non-module inline `<script>` blocks
(jQuery-style), not `type="module"`, so they can't `import` this ES module
themselves — the `window.MintCart` bridge is how they reach it. See
`docs/developer/admin/asset-enqueue.md` for the full writeup, including why
the bundle wasn't enqueued anywhere until this run.

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

**Live-wired (this run):** `product_form.twig`'s **Category** field
(`upload/admin/view/template/catalog/product_form.twig`) now mounts a real
`McEntityPicker` against this endpoint, via
`window.MintCart.createEntityPicker` — see
`docs/developer/admin/asset-enqueue.md` for the enqueue side and
`docs/upstream-diff.md`'s `product.php`/`product_form.twig` entry for the
full diff writeup. Kept **additive** alongside the existing jQuery
`autocomplete()` block (not a replacement — this environment has no live
MySQL/admin session to visually verify a full swap), and *not* hosted in
an `mc-drawer` — `mc-drawer` needs Bootstrap 5's own `.offcanvas`
structural CSS, which the `components.min.css` bundle this page loads
deliberately excludes (see that doc's caveat). Instead it's a plain
`hidden`-toggled panel, shown/hidden with vanilla JS. Both mechanisms
write the identical `#product-category{id}`/`product_category[]`
hidden-input shape, so `product.php`'s save logic needed no change.
Removing the old autocomplete once a human confirms the picker works on a
real install is a natural, low-risk follow-up — not attempted this run for
the same no-live-verification reason.

**Still not done, by design:**
- Filters beyond search text, and the create-new-flow (add a category
  inline from the picker) — no requirement identified yet for this
  consumer.
- Every other entity type is now done — **manufacturer**, **product**,
  **information pages**, **attributes**, **options** and **customers**,
  see below.

## Manufacturer backend

**Integration conventions (2026-09-16 review):** the product form uses
`data-mc-manufacturer-*` and `data-mc-action="browse-manufacturers"` hooks.
Existing field IDs/names remain compatible with OpenCart labels, autocomplete
and save handling. The panel ID is retained for `aria-controls`, not JS lookup.
The embedded field's spacing uses `--mc-space-2` through
`.mc-entity-picker-field`; its text button needs no Font Awesome icon
(Implementation Guide §§14–18, 21). `.mc-entity-picker[hidden]` explicitly
overrides the component's flex display. The trigger tracks `aria-expanded`
and receives focus after confirmation (Implementation Guide §§13, 68).

```
GET index.php?route=mintcart/entity/manufacturer&user_token=…&filter_name=…&page=…&limit=…
```

Same shape as the category backend above:
`ControllerMintcartEntity::manufacturer()` wraps
`ModelCatalogManufacturer::getManufacturers()` (never queries the database
directly) and normalizes rows via
`MintCart\Design\ManufacturerPickerProvider`
(`upload/system/library/mintcart/design/manufacturerpickerprovider.php`,
no OpenCart dependency, unit-tested in
`tests/mintcart/design/manufacturer-picker-provider-test.php`) into the
same `{items: [{id, name, image}], total}` shape. Same reasons for a new
route instead of reshaping `catalog/manufacturer.php`'s own
`autocomplete()` action (an existing `product_form.twig` consumer with no
pagination), and for paging in PHP rather than SQL
(`ModelCatalogManufacturer` has `getTotalManufacturers()`, unfiltered
store-wide, but no *filtered* total-count method to pair with
`getManufacturers()`'s own `start`/`limit` support).

**Difference from the category backend: `image` is a real thumbnail, not
always `null`.** `oc_manufacturer` has an `image` column (unlike
`oc_category`'s query, which selects none) — `ControllerMintcartEntity`
resolves each row's raw filename into a URL via `model_tool_image->resize()`
(the same helper `manufacturer_form.twig` already uses for its own image
preview) before handing rows to the provider, or `null` when the
manufacturer has no image file on disk. This makes `manufacturer` the
first entity backend to actually exercise `McEntityPicker`'s thumbnail
rendering — `image` was untestable end-to-end via category alone.

**Filter matching differs slightly from category's, inherited from the
underlying models, not introduced here:** `ModelCatalogCategory::getCategories()`
matches `filter_name` as a substring (`LIKE '%…%'`, so it matches
anywhere in the name); `ModelCatalogManufacturer::getManufacturers()`
matches it as a prefix (`LIKE '…%'`, so it only matches from the start of
the name). Both endpoints just pass `filter_name` straight through to
their respective existing model method — this is pre-existing OpenCart
behavior, not a MintCart inconsistency to fix here.

Same permission requirement as category (`mintcart/entity` **Access**
must be granted to a user group first).

**Live-wired (this run):** `product_form.twig`'s **Manufacturer** field
now mounts a real `McEntityPicker` against this endpoint, via
`window.MintCart.createEntityPicker` — the same enqueue
(`docs/developer/admin/asset-enqueue.md`) the Category field already
uses, since `getForm()`'s existing `enqueueMintAssets()` call covers the
whole form, not just the Category field. Kept **additive** alongside the
existing jQuery UI `autocomplete()` field (not a replacement, for the same
no-live-verification reason the Category picker gives). The one real
difference from Category's wiring: this endpoint is **single-select**
(`multiple: false` — a product has exactly one `manufacturer_id`), so
confirming a selection *overwrites*
`#input-manufacturer`/`input[name="manufacturer_id"]` directly, instead of
appending into a multi-select well — there is no "selected count" footer
element for this picker, since 0-or-1 is not worth counting. Both
mechanisms write the identical `input-manufacturer`/`manufacturer_id`
fields, so `product.php`'s save logic needed no change. Removing the old
autocomplete field is the same natural, low-risk, not-yet-attempted
follow-up as Category's.

## Product backend

```
GET index.php?route=mintcart/entity/product&user_token=…&filter_name=…&page=…&limit=…
```

`ControllerMintcartEntity::product()` wraps the existing
`ModelCatalogProduct::getProducts()`/`getTotalProducts()` calls — it never
queries the database directly — and normalizes rows via
`MintCart\Design\ProductPickerProvider`
(`upload/system/library/mintcart/design/productpickerprovider.php`, no
OpenCart dependency, unit-tested in
`tests/mintcart/design/product-picker-provider-test.php`) into the same
`{items: [{id, name, image}], total}` shape. Same reason as category/
manufacturer for a new route rather than reshaping an existing
`autocomplete()` action — `catalog/product.php`'s own `autocomplete()` is
already the busiest of the three (ten existing admin-template consumers,
including `product_form.twig`'s own Related Products field, `order_form.twig`,
`return_form.twig`/`return_list.twig`, `coupon_form.twig`, and several
more), returning a plain `[{product_id, name, model, image}]` array with no
pagination — reshaping it would be an even larger breaking change than
category's five consumers.

**Difference from category/manufacturer: paging happens in SQL, not PHP.**
Unlike `ModelCatalogCategory`/`ModelCatalogManufacturer`,
`ModelCatalogProduct::getTotalProducts($data)` accepts the *same*
`filter_name` the list query does and returns a real filtered count, not
just an unfiltered store-wide total — so `getTotalProductsByLayoutId()`-style
core changes were not needed here, unlike the gap the category backend
flagged. The controller passes `start`/`limit` straight through to
`getProducts()` and calls `getTotalProducts()` with the same filter, so
`ProductPickerProvider::build()` takes `(array $rows, int $total)` rather
than `(array $rows, int $page, int $limit)` — `$rows` is already the
current page, `$total` is the real filtered count. This is the exact
"once a second `PickerProvider` needs a filtered count" case the category
backend's write-up above flagged as the natural fix — product catalogs are
the entity type most likely to be large enough for PHP-side slicing of the
full result set (category/manufacturer's approach) to actually matter.

**Image resolution:** same pattern as manufacturer — `oc_product.image`
is a filename, resolved to a real thumbnail URL via
`model_tool_image->resize()` (the same helper `product_form.twig` already
uses for its own image preview), or `null` when the product has no image
file on disk.

**Filter matching:** `ModelCatalogProduct::getProducts()`/`getTotalProducts()`
match `filter_name` as a prefix (`LIKE '…%'`), same as manufacturer, unlike
category's substring match — again pre-existing OpenCart behavior, not
introduced here.

Same permission requirement (`mintcart/entity` **Access**) and the same
"not done yet, by design" live-wiring gap as category/manufacturer: no
admin screen calls this endpoint yet (MintCart's asset bundle still isn't
enqueued on any admin page — see above). The identified first real
consumer is `product_form.twig`'s **Related Products** field
(`upload/admin/view/template/catalog/product_form.twig`, the
`#input-related`/`related` multi-select block using its own jQuery UI
`autocomplete()` against `catalog/product.autocomplete`) — already
multi-select with removable tags, the same shape match reasoning
category/manufacturer's identified consumers follow.

## Information page backend

```
GET index.php?route=mintcart/entity/information&user_token=…&filter_name=…&page=…&limit=…
```

Fourth entity backend, same overall shape:
`ControllerMintcartEntity::information()` wraps
`ModelCatalogInformation::getInformations()` (never queries the database
directly) and normalizes rows via `MintCart\Design\InformationPickerProvider`
(`upload/system/library/mintcart/design/informationpickerprovider.php`, no
OpenCart dependency, unit-tested in
`tests/mintcart/design/information-picker-provider-test.php`) into the same
`{items: [{id, name, image}], total}` shape. `image` is always `null` —
information pages have no image concept in OpenCart, the same reason
category's is always `null`.

**Difference from category/manufacturer: filtering also happens in PHP, not
just paging.** `ModelCatalogInformation::getInformations()`'s `$data`-driven
query path only understands `sort`/`order`/`start`/`limit` — there is no
`filter_name` support to pass through at all (unlike `getCategories()`/
`getManufacturers()`, which at least support filtering in SQL even without a
matching filtered-count method). Adding one to `ModelCatalogInformation` for
a single new consumer would be a genuine OpenCart-core-file modification
(`CLAUDE.md` §10), so the controller instead calls `getInformations()` with
no arguments (all rows, via the model's own cache) and
`InformationPickerProvider` does both the `filter_name` substring match
(case-insensitive, matches anywhere in the title — the more permissive of
category's and manufacturer's two matching styles) and the paging itself, in
PHP. Information page counts are small (a handful to a few dozen even for a
large store), so this isn't a real cost today — same reasoning that already
justified PHP-side paging alone for category and manufacturer.

Same permission requirement (`mintcart/entity` **Access** must be granted to
a user group first) and the same "not done yet, by design" live-wiring gap:
no admin screen calls this endpoint yet, blocked on the same admin
asset-enqueue problem as category and manufacturer (see above). There is no
single obvious first consumer the way `product_form.twig`'s Category/
Manufacturer fields are for the other two — information pages are picked in
several places (`information_form.twig`'s own layout/link settings and any
future "related pages" style field), so the first real consumer should be
identified when one of those screens is actually being migrated to
`McEntityPicker`, not guessed at here.

## Attribute backend

```
GET index.php?route=mintcart/entity/attribute&user_token=…&filter_name=…&page=…&limit=…
```

Same shape as the category backend:
`ControllerMintcartEntity::attribute()` wraps
`ModelCatalogAttribute::getAttributes()` (never queries the database
directly) and normalizes rows via `MintCart\Design\AttributePickerProvider`
(`upload/system/library/mintcart/design/attributepickerprovider.php`, no
OpenCart dependency, unit-tested in
`tests/mintcart/design/attribute-picker-provider-test.php`) into the same
`{items: [{id, name, image}], total}` shape. `image` is always `null` —
`oc_attribute`/`oc_attribute_description` have no image column, the same
situation as category and information pages.

**Why a new route instead of reusing an existing autocomplete action:**
`catalog/product.php` has no attribute autocomplete of its own today
(product attribute values are entered via a dropdown of *already-loaded*
attributes on `product_form.twig`'s Attribute tab, not a search field), so
there is no existing consumer this route could break — unlike category/
manufacturer, this is a genuinely new capability, not a reshape of one.
Still added as a `mintcart/entity/*` action rather than a new method on
`ModelCatalogAttribute` itself, for the same separation-of-concerns reason
(`CategoryPickerProvider`'s section above): the model returns raw OpenCart
rows, the picker-shape normalization is MintCart's own concern.

**Why paging happens in PHP, not SQL:** `ModelCatalogAttribute::getAttributes()`
supports `start`/`limit` at the SQL level, but the model only has
`getTotalAttributes()` (unfiltered, store-wide) and
`getTotalAttributesByAttributeGroupId()` (filtered by attribute group, not
by name) — no *filtered-by-name* total-count method to pair with a
SQL-level `LIMIT`. Same category-established pattern: take the full
filtered (but unpaged) row set and slice/count it in PHP. Attribute counts
are small (dozens even for a large catalog's full attribute set), so this
isn't a real cost today.

**Filter matching is a prefix match, not substring** — inherited from
`ModelCatalogAttribute::getAttributes()`'s own `LIKE '…%'` (matches from
the start of the name only), the same as manufacturer, not category's
`LIKE '%…%'` substring match. Pre-existing OpenCart behavior, not
introduced here.

Same permission requirement and the same "not done yet, by design"
live-wiring gap as category/manufacturer above. No obvious first live
consumer has been identified yet for this one — unlike category/
manufacturer, there is no existing single-select attribute autocomplete
field on `product_form.twig` to swap out; a future consumer would be a
*new* UI affordance (e.g. searching attributes by name when adding one to
a product), not a drop-in replacement of something that already exists.

## Option backend

```
GET index.php?route=mintcart/entity/option&user_token=…&filter_name=…&page=…&limit=…
```

Same shape as category/manufacturer: `ControllerMintcartEntity::option()`
wraps `ModelCatalogOption::getOptions()` (never queries the database
directly) and normalizes rows via `MintCart\Design\OptionPickerProvider`
(`upload/system/library/mintcart/design/optionpickerprovider.php`, no
OpenCart dependency, unit-tested in
`tests/mintcart/design/option-picker-provider-test.php`) into the same
`{items: [{id, name, image}], total}` shape. Same reason for a new route
instead of reshaping `catalog/option.php`'s own `autocomplete()` action
(an existing `product_form.twig` consumer with no pagination), and for
paging in PHP rather than SQL (`ModelCatalogOption` has
`getTotalOptions()`, unfiltered store-wide, but no *filtered*
total-count method to pair with `getOptions()`'s own `start`/`limit`
support).

`image` is always `null` — `oc_option` has no image column of its own
(individual `oc_option_value` rows can carry one, but an *option* — the
group, e.g. "Size" — cannot), same reasoning as category.

`filter_name` prefix-matches (`LIKE '…%'`), same as manufacturer, not
category's substring match — inherited unchanged from
`ModelCatalogOption::getOptions()`.

Same permission requirement (`mintcart/entity` **Access**) and the same
"not done yet, by design" live-wiring gap as category/manufacturer: no
admin screen calls this endpoint yet, for the identical reason
(MintCart's asset bundle still isn't enqueued on any admin page — see
above). `product_form.twig`'s **Option** tab (`#input-option`, the
jQuery UI `autocomplete()` block using `catalog/option/autocomplete`) is
this backend's identified first real consumer — already
search-as-you-type, so a future `McEntityPicker` swap is a shape match,
not a new pattern.

## Customer backend

```
GET index.php?route=mintcart/entity/customer&user_token=…&filter_name=…&page=…&limit=…
```

`ControllerMintcartEntity::customer()` wraps `ModelCustomerCustomer::getCustomers()`
(never queries the database directly) and normalizes rows via the new
`MintCart\Design\CustomerPickerProvider`
(`upload/system/library/mintcart/design/customerpickerprovider.php`, no
OpenCart dependency, unit-tested in
`tests/mintcart/design/customer-picker-provider-test.php`) into the same
`{items: [{id, name, image}], total}` shape.

**Deliberate design difference from category/manufacturer: paging happens
at the SQL level, not in PHP.** Unlike `ModelCatalogCategory`/
`ModelCatalogManufacturer`, `ModelCustomerCustomer::getTotalCustomers($data)`
accepts the same `filter_name` the list query does and returns a real
*filtered* count — so `getCustomers()`'s own `start`/`limit` SQL support is
used directly, and `CustomerPickerProvider::build(array $rows, int $total)`
takes an already-paged row set plus the real filtered total, instead of
`build(array $rows, int $page, int $limit)` slicing a full result set in
PHP. This is the same shape `ProductPickerProvider` uses for the same
reason — customer tables, like product catalogs, are realistically large
enough (potentially thousands of rows) for PHP-side slicing of the *entire*
filtered set to be a real cost, unlike category/manufacturer/attribute
lists which stay small.

`name` is `CONCAT(firstname, ' ', lastname)`, matched by `filter_name` as a
substring (`LIKE '%…%'`) — inherited unchanged from
`ModelCustomerCustomer::getCustomers()`'s existing behavior, same matching
style as category (not manufacturer's prefix match).

`image` is always `null` — customers have no image concept in OpenCart.

Live wiring **not** attempted — same reason category/manufacturer left it
undone: MintCart's asset bundle still isn't enqueued on any admin page. No
single obvious first live consumer has been identified yet for this one
either (there is no existing customer-autocomplete field on any admin
template to replace, unlike category/manufacturer/product) — documented
here rather than guessed at, per `CLAUDE.md` §85.

Same permission requirement as every other backend above (`mintcart/entity`
**Access** must be granted to a user group first).
