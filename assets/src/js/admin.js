/**
 * MintCart admin JavaScript entry point.
 *
 * Bundled with esbuild into assets/dist/js/admin.min.js (production) or
 * assets/dist/js/admin.js (dev, with sourcemap). See package.json scripts.
 *
 * New MintCart admin screens use vanilla JS (see CLAUDE.md §7 and
 * docs/MINTCART_IMPLEMENTATION_GUIDE.md §3). Legacy OpenCart admin screens
 * keep using jQuery independently of this bundle.
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
// mc-entity-picker (McEntityPicker) shipped in `./components/entity-picker.js`
// as an exported `createEntityPicker(root, options)` factory, not imported
// here: it needs a caller-supplied `fetchItems` data source and a root
// element that doesn't exist until a real admin screen (Phase 12 - product/
// category/manufacturer/attribute/option/information-page/customer pickers)
// renders one and calls it directly - no wiring ahead of a real consumer
// (CLAUDE.md §85).
//
// mc-media-picker (McMediaPicker) shipped the same way in
// `./components/media-picker.js` as `createMediaPicker(root, options)`: it
// needs a caller-supplied `fetchItems`/`fetchFolders`/`uploadFiles`/
// `deleteItems` data source and a real Media UX consumer (Functional Scope
// §25 - Product/Category/Blocks image fields), neither of which exist yet.
//
// mc-confirm (McConfirm) shipped in `./components/confirm.js` as an
// exported `mcConfirm()` function, not imported here: it has no boot-time
// listener to register (it builds its dialog element lazily on first call,
// see confirm.js), and no admin screen calls it yet - a future screen that
// needs a confirm prompt (e.g. Bulk Editor mass delete, Phase 16) imports
// `mcConfirm` directly. Bundling it here with no caller would just be dead
// code (CLAUDE.md §85: no wiring ahead of a real consumer).
initAlertDismiss();
initQuantityStepper();
initModalTriggers();
initDrawerTriggers();
initToastTriggers();
initSortableLists();
initTabsTriggers();
initProgressBars();

// `setProgress()` (assets/src/js/components/progress.js) has no
// declarative trigger to wire here - it's an imperative API called by a
// future batch-job/Update Manager flow (Phase 16/Technical Plan §33), not a
// page-load behavior. Exported for that future caller to import directly.
