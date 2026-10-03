/**
 * MintCart admin JavaScript entry point.
 *
 * Bundled with esbuild into assets/dist/js/admin.min.js (production) or
 * assets/dist/js/admin.js (dev, with sourcemap). See package.json scripts.
 *
 * New MintCart admin screens use vanilla JS (see CLAUDE.md §7 and
 * docs/MINTCART_IMPLEMENTATION_GUIDE.md §3). Legacy OpenCart admin screens
 * keep using jQuery independently of this bundle.
 *
 * Boot timing (Phase 17, found while wiring category_list.twig's drag &
 * drop): `enqueueMintAssets()` (docs/developer/admin/asset-enqueue.md)
 * queues this bundle via `$this->document->addScript()`, and every admin
 * `header.twig` renders `{% for script in scripts %}` inside `<head>`, as
 * a plain classic `<script src="...">` with no `defer`/`async`/
 * `type="module"`. A classic script like that runs the moment the parser
 * reaches it - before `<body>` (and this page's own markup) exists in the
 * DOM at all. `initAlertDismiss()`'s pure event-delegation (a single
 * `document.addEventListener('click', ...)`, needing no element to exist
 * yet) happened to work anyway, which is why this went unnoticed, but
 * every init*() below that does an eager `root.querySelectorAll(...)` at
 * call time to wire up *already-existing* elements
 * (`initSortableLists()` - `docs/developer/components/sortable.md` "1.
 * Initializes a SortableJS instance on every [data-mc-sortable] container
 * found ... at call time" - plus `initModalTriggers()`/
 * `initDrawerTriggers()`/`initToastTriggers()`/`initTabsTriggers()`'s
 * relay-listener setup and `initProgressBars()`) found nothing and
 * silently did nothing. `boot()` below defers the same, unmodified call
 * sequence until the DOM is actually ready, the standard fix for a
 * classic script loaded in `<head>` (`document.readyState === 'loading'`
 * covers the `<head>` case; the `else` branch keeps this file safe to
 * also load after `DOMContentLoaded` has already fired, e.g. injected
 * dynamically). See `docs/developer/catalog/category-drag-drop.md`'s
 * "Boot timing bug found while verifying this increment" section for the
 * full writeup (this file and `catalog.js` are MintCart-owned, not
 * OpenCart core, so this isn't a `docs/upstream-diff.md` entry).
 */

import 'bootstrap';

import { initAlertDismiss } from './components/alert.js';
import { initQuantityStepper } from './components/quantity.js';
import { initModalTriggers } from './components/modal.js';
import { initDrawerTriggers } from './components/drawer.js';
import { initToastTriggers } from './components/toast.js';
import { initSortableLists } from './components/sortable.js';
import { initTabsTriggers } from './components/tabs.js';
import { initProgressBars } from './components/progress.js';
import { createEntityPicker } from './components/entity-picker.js';
import { createMediaPicker } from './components/media-picker.js';
import { initAdminSearch } from './components/admin-search.js';

// `mc-modal`'s, `mc-drawer`'s and `mc-toast`'s CSS/JS (Phase 4, shared with
// catalog.js) already cover the admin dialog/panel/notification use cases;
// a future McModal/McDrawer/McToast JS class (if a real consumer needs an
// imperative open()/close() API beyond data-mc-action triggers) would wrap
// these same components, not replace them. `mc-sortable` (McSortable) is
// admin-only (Functional Scope §6's drag-and-drop scenarios are all admin
// screens), so it is wired here and not in catalog.js. `mc-tabs` (McTabs)
// is likewise admin-only (entity edit forms - Product:
// General/Data/Links/Attributes/... - are its first expected consumer), so
// it too is wired here and not in catalog.js.
//
// mc-entity-picker (McEntityPicker) - `createEntityPicker(root, options)`
// still needs a caller-supplied `fetchItems` data source and options
// (getId/getLabel/onConfirm/...) specific to whichever entity type/field is
// hosting it, so it isn't auto-initialized here the way initXxx() above are
// - each consumer calls it directly with its own options, same as before.
// What changed with product_form.twig's Category field (Phase 12's first
// live consumer, docs/developer/components/entity-picker.md "Admin
// backends"): admin Twig templates use plain non-module inline <script>
// blocks (jQuery-style), not `type="module"`, so they cannot `import` this
// ES module themselves. Exposing the factory on `window.MintCart` is the
// minimal bridge - no per-page bundle, no new build entry, and every other
// admin.js behavior stays exactly as declarative (data-mc-*) as before.
window.MintCart = window.MintCart || {};
window.MintCart.createEntityPicker = createEntityPicker;
//
// mc-media-picker (McMediaPicker) shipped the same way in
// `./components/media-picker.js` as `createMediaPicker(root, options)`:
// bridged here for the identical reason - plain inline `<script>` blocks on
// legacy admin Twig templates cannot `import` an ES module directly. First
// live consumer: `design/homepage_form.twig`'s Hero/Text+Image `image`
// settings fields (Functional Scope §25), wired against the
// `design/media/list`/`/folders`/`/upload`/`/delete` backend
// (`ControllerDesignMedia`/`ModelDesignMedia`).
window.MintCart.createMediaPicker = createMediaPicker;
//
// mc-confirm (McConfirm) shipped in `./components/confirm.js` as an
// exported `mcConfirm()` function, not imported here: it has no boot-time
// listener to register (it builds its dialog element lazily on first call,
// see confirm.js), and no admin screen calls it yet - a future screen that
// needs a confirm prompt (e.g. Bulk Editor mass delete, Phase 16) imports
// `mcConfirm` directly. Bundling it here with no caller would just be dead
// code (CLAUDE.md §85: no wiring ahead of a real consumer).
function boot() {
    initAlertDismiss();
    initQuantityStepper();
    initModalTriggers();
    initDrawerTriggers();
    initToastTriggers();
    initSortableLists();
    initTabsTriggers();
    initProgressBars();
    initAdminSearch();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}

// `setProgress()` (assets/src/js/components/progress.js) has no
// declarative trigger to wire here - it's an imperative API called by a
// future batch-job/Update Manager flow (Phase 16/Technical Plan §33), not a
// page-load behavior. Exported for that future caller to import directly.
