# `mc-media-picker`

MintCart's Media UX primitive — a searchable, foldered, paginated
thumbnail grid, with optional upload and safe delete, meant to replace
ad-hoc image pickers scattered across Product/Category/Blocks admin
screens (`MINTCART_FUNCTIONAL_SCOPE.md` §25, priority **P1**).

This is the Phase 11 "admin primitive" half of the picker: grid/selection
behavior against a caller-supplied data source. It does not know which
media library backend it talks to, and does not own any dialog chrome.
Wiring a real `fetchItems`/`fetchFolders`/`uploadFiles`/`deleteItems`
backend and mounting the picker inside an actual Product/Category/Blocks
image field is later work, once that real consumer exists
(`CLAUDE.md` §85) — the same deferral `mc-entity-picker` documents for
Phase 12.

## Relationship to `mc-entity-picker`

`media-picker.js` is the sibling of `entity-picker.js` (same run, same
Phase 11 batch): same shape — a caller-supplied async data source, no
dialog chrome of its own, WAI-ARIA listbox roving-tabindex keyboard
navigation, `mc:*:change` event on selection — but a thumbnail **grid**
instead of a text **list**, because media items are chosen visually
(Functional Scope §25's "thumbnails" requirement), plus two features
media specifically needs that a generic entity list does not:

- **Folders** — an optional `<select>` populated from `fetchFolders()`,
  filtered as part of every fetch. Hidden entirely when `fetchFolders` is
  not supplied, per `CLAUDE.md` §85 (the picker doesn't offer a filter
  affordance it can't actually query).
- **Upload / safe delete** — optional `uploadFiles(files)` /
  `deleteItems(items)` callbacks. Their UI (the upload `<label>`/`<input
  type="file">`, the delete button) is hidden/disabled unless the host
  actually supplies the callback — the component never assumes a backend
  exists.

"Recent files" (Functional Scope §25) is not a separate mode: a host
backend that defaults its unfiltered/first-page sort to most-recently-
uploaded, or supplies a `{ id: 'recent', label: 'Recent' }` folder entry
alongside real folders, gets the same UX without a dedicated concept in
this component (`CLAUDE.md` §17 — no abstraction without a real need).
"Image metadata" (Functional Scope §25) is exposed as an optional
`getMeta(item)` line under the filename (e.g. dimensions/file size),
supplied by the host from whatever the backend returns — this component
does not compute or know image metadata itself.

## Markup contract

```html
<div class="mc-media-picker">
    <div class="mc-media-picker__toolbar">
        <input
            type="search"
            class="mc-field__control"
            data-mc-media-picker-search
            aria-label="Search media"
        >

        <select class="mc-field__control" data-mc-media-picker-folder aria-label="Folder"></select>

        <label class="mc-button mc-button--secondary">
            Upload
            <input type="file" multiple hidden data-mc-media-picker-upload>
        </label>
    </div>

    <div
        class="mc-media-picker__grid"
        role="listbox"
        aria-multiselectable="true"
        aria-label="Media"
        data-mc-media-picker-grid
    ></div>

    <p class="mc-media-picker__empty" data-mc-media-picker-empty hidden>
        No media found.
    </p>

    <div class="mc-media-picker__pager">
        <button type="button" class="mc-button mc-button--secondary" data-mc-action="media-picker-prev">
            Previous
        </button>
        <span class="mc-media-picker__page-info" data-mc-media-picker-page-info></span>
        <button type="button" class="mc-button mc-button--secondary" data-mc-action="media-picker-next">
            Next
        </button>
    </div>

    <div class="mc-media-picker__footer">
        <span class="mc-media-picker__selected-count" data-mc-media-picker-selected-count></span>
        <button type="button" class="mc-button mc-button--danger" data-mc-action="media-picker-delete">
            Delete selected
        </button>
        <button type="button" class="mc-button mc-button--primary" data-mc-action="media-picker-confirm">
            Use selected
        </button>
    </div>
</div>
```

Grid cells (`<button type="button" role="option" aria-selected="…"
data-id="…">`) are rendered entirely by `media-picker.js` — their content
is always data-driven, so there is no static cell markup for a host page
to write (the same reasoning `mc-entity-picker`'s row markup follows).

## Usage

```js
import { createMediaPicker } from './components/media-picker.js';
import { Offcanvas } from 'bootstrap';

const drawerEl = document.querySelector('#product-image-picker');
const picker = createMediaPicker(drawerEl.querySelector('.mc-media-picker'), {
    async fetchItems({ query, folder, page, pageSize, signal }) {
        const params = new URLSearchParams({ query, folder, page, pageSize });
        const response = await fetch(`index.php?route=design/media/list&user_token=${userToken}&${params}`, { signal });
        const data = await response.json();

        return { items: data.items, total: data.total };
    },
    async fetchFolders() {
        const response = await fetch(`index.php?route=design/media/folders&user_token=${userToken}`);
        const data = await response.json();

        return data.folders; // [{ id, label }, ...]
    },
    async uploadFiles(files) {
        const form = new FormData();
        files.forEach((file) => form.append('file[]', file));

        await fetch(`index.php?route=design/media/upload&user_token=${userToken}`, { method: 'POST', body: form });
    },
    async deleteItems(items) {
        await fetch(`index.php?route=design/media/delete&user_token=${userToken}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ 'paths[]': items.map((item) => item.id) }),
        });
    },
    multiple: false,
    getId: (file) => file.id,
    getLabel: (file) => file.name,
    getThumbnail: (file) => file.thumbnail,
    getMeta: (file) => `${file.width}×${file.height}`,
    onConfirm(selected) {
        // assign selected[0].url to the host product-image field, then close
        Offcanvas.getInstance(drawerEl)?.hide();
    },
});

drawerEl.addEventListener('mc:drawer:shown', () => picker.refresh());
```

The routes are slash-separated (`design/media/list`, not `design/media.list`)
— see `docs/developer/design/media-library.md` for why the dot form never
actually worked, and the full backend contract now shipped behind these
four routes (`ModelDesignMedia`/`ControllerDesignMedia`).

## Behavior

- **Search** — the `input` event on `[data-mc-media-picker-search]` is
  debounced (250ms default, `debounceMs` option), resets to page 1, and
  re-fetches.
- **Folders** — changing `[data-mc-media-picker-folder]` resets to page 1
  and re-fetches with the selected folder id. Hidden entirely when
  `fetchFolders` is not supplied.
- **Upload** — selecting files in `[data-mc-media-picker-upload]` calls
  `uploadFiles(files)` with the raw `File[]`, then reloads the current
  page on success so newly uploaded items appear. The upload control is
  hidden entirely when `uploadFiles` is not supplied.
- **Pagination** — `data-mc-action="media-picker-prev"` /
  `"media-picker-next"`, disabled at the first/last page from the
  `total`/`pageSize` the data source reports. In-flight requests are
  cancelled via `AbortController` when a newer search/folder/page request
  starts (`MINTCART_IMPLEMENTATION_GUIDE.md` §3's `AbortController`
  allowance).
- **Selection** — click or Enter/Space on a cell toggles it. `multiple:
  false` makes selecting a cell clear any previous selection
  (single-select, the common case for an image field). Selection is
  tracked by id across pages/searches, and `getSelected()` always returns
  full item objects reconstructed from every page fetched so far.
- **Delete** — `data-mc-action="media-picker-delete"` calls
  `deleteItems(selectedItems)`, then clears the selection and reloads the
  current page. Disabled while nothing is selected; hidden entirely when
  `deleteItems` is not supplied — this is what makes delete "safe" at the
  component level: it is never offered without a host that explicitly
  opted in, and always operates on an explicit selection rather than a
  single ambiguous target.
- **Keyboard navigation** — ArrowUp/ArrowDown/ArrowLeft/ArrowRight/Home/End
  move a roving `tabindex` between grid cells (the WAI-ARIA listbox
  pattern, all four arrow keys treated as forward/backward through the
  same list since the grid's real column count depends on container
  width, not a value this component controls); Enter/Space toggles the
  focused cell. This is the same interaction a mouse click gives — no
  feature is mouse/touch-only (Functional Scope §27).
- **Events** — `mc:media-picker:change` (bubbling,
  `detail: { selected: Item[] }`) fires on `root` whenever selection
  changes, whether from a click, keyboard, or a programmatic
  `setSelected()` call.

## Returned instance API

- `refresh()` — re-run the current query/folder/page fetch (call on
  dialog open)
- `getSelected()` — full item objects for every currently selected id
- `setSelected(ids)` — set the initial selection (e.g. editing an
  existing product image)
- `clear()` — reset search text, folder, page and selection, then reload
- `destroy()` — remove all listeners (call if the host drawer/modal is
  removed from the DOM)

## Wired into `admin.js` — first live consumer

`createMediaPicker` is bridged onto `window.MintCart.createMediaPicker`
in `assets/src/js/admin.js` (same pattern as `createEntityPicker` —
`CLAUDE.md` §14/§85, legacy admin Twig templates use plain inline
`<script>` blocks, not `type="module"`, so they cannot `import` this ES
module directly).

`ModelDesignMedia`/`ControllerDesignMedia` (`design/media/list`,
`/folders`, `/upload`, `/delete`) implement exactly the
`fetchItems`/`fetchFolders`/`uploadFiles`/`deleteItems` shape the usage
example above calls — browsing/uploading/deleting inside OpenCart's own
`image/catalog/` directory. See `docs/developer/design/media-library.md`
for the full endpoint contract.

**First real consumer:** `design/homepage_form.twig`'s Hero/Text+Image
block types' `image` settings field (Functional Scope §25) —
`ControllerDesignHomepage::enqueueMintAssets()` queues
`components.min.css`/`admin.min.js` on the add/edit form (same
Bootstrap-5-free bundle `ControllerCatalogProduct::getForm()` already
established, `docs/developer/admin/asset-enqueue.md`), and the field
hosts a plain `hidden`-attribute-toggled `mc-media-picker` next to the
existing text input (no `mc-drawer`/`mc-modal` — those need Bootstrap 5
JS/CSS this bundle deliberately excludes, same shape
`order_form.twig`'s Customer field `mc-entity-picker` already
established). Confirming a selection writes the picked item's
catalog-relative path into the paired text input; the text input itself
stays editable so a merchant who already knows the path can still
type/paste it directly. Known, documented limitation:
`ModelDesignMedia::getFiles()` lists subdirectories inline in the same
grid as images (`type: 'directory'`) — this consumer does not let a
directory cell be "entered" from the grid itself (only the top-level
folder `<select>` navigates), and confirming a directory selection
applies no path, per `homepage_form.twig`'s own inline script comment.
Product/Category image fields and a proper Banner Grid/Gallery/Brands/
Testimonials `items` repeater (Technical Plan §15C/§16, still a single
raw-JSON textarea today) remain unwired.

## Definition of Done checklist (`MINTCART_IMPLEMENTATION_GUIDE.md` §79)

- [x] Documented contract (this file)
- [x] `mc-*` BEM markup (`mc-media-picker`, `mc-media-picker__toolbar`,
      `__grid`, `__item`, `__item-thumbnail`, `__item-label`,
      `__item-meta`, `__empty`, `__pager`, `__page-info`, `__footer`,
      `__selected-count`)
- [x] JS reads `data-mc-media-picker-*`/`data-mc-action` hooks, never a
      CSS class, as the trigger/query contract
- [x] Colors/spacing/radius read from `--mc-*` tokens
- [x] Keyboard usable — WAI-ARIA listbox roving-tabindex pattern
      (arrow keys/Home/End/Enter/Space), `:focus-visible` outline
- [x] Selection state uses `aria-selected` (semantic attribute) rather
      than a JS-added modifier class, per CLAUDE.md §13
- [x] Mobile checked — the grid uses `repeat(auto-fill, minmax(...))` so
      column count follows container width with no fixed desktop-only
      widths; the toolbar wraps at narrow widths
- [x] No Bootstrap 3 dependency
- [x] No OCMOD required
- [x] Theme override checked — N/A: this is an admin-only primitive, not
      a storefront component, so there is no theme override surface to
      check (same as `McEntityPicker`/`McSortable`/other Phase 11
      primitives)
- [x] Live data source — `ModelDesignMedia`/`ControllerDesignMedia` +
      `design/homepage_form.twig`'s Hero/Text+Image `image` field (see
      above)
