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

// MintCart admin primitives (McDrawer, McToast, McConfirm, McTabs,
// McSortable, McMediaPicker, McEntityPicker, McProgress - Phase 11) are
// added here once implemented. `mc-modal`'s CSS/JS (Phase 4, shared with
// catalog.js) already covers the admin dialog use case; a future McModal
// JS class (if a real consumer needs an imperative open()/close() API
// beyond data-mc-action triggers) would wrap this same component, not
// replace it.
initAlertDismiss();
initQuantityStepper();
initModalTriggers();
