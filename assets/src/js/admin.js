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
import { initProgressBars } from './components/progress.js';

// Remaining MintCart admin primitives (McConfirm, McTabs, McSortable,
// McMediaPicker, McEntityPicker - Phase 11) are added here once
// implemented. `mc-modal`'s, `mc-drawer`'s and `mc-toast`'s CSS/JS
// (Phase 4, shared with catalog.js) already cover the admin
// dialog/panel/notification use cases; a future McModal/McDrawer/McToast JS
// class (if a real consumer needs an imperative open()/close() API beyond
// data-mc-action triggers) would wrap these same components, not replace
// them.
initAlertDismiss();
initQuantityStepper();
initModalTriggers();
initDrawerTriggers();
initToastTriggers();
initProgressBars();

// `setProgress()` (assets/src/js/components/progress.js) has no
// declarative trigger to wire here - it's an imperative API called by a
// future batch-job/Update Manager flow (Phase 16/Technical Plan §33), not a
// page-load behavior. Exported for that future caller to import directly.
