/**
 * MintCart storefront (catalog) JavaScript entry point.
 *
 * Bundled with esbuild into assets/dist/js/catalog.min.js (production) or
 * assets/dist/js/catalog.js (dev, with sourcemap). See package.json scripts.
 *
 * Rules (see CLAUDE.md §3/§7 and docs/MINTCART_IMPLEMENTATION_GUIDE.md §3-4):
 * - Vanilla JS / ES modules only. No jQuery in MintCart-owned code.
 * - Behavior hooks read from `data-mc-*` attributes, never from CSS classes.
 * - Bootstrap 5 JS is imported here so themes get a single bundled entry
 *   point instead of loading it from a CDN or a second <script> tag.
 */

import 'bootstrap';

import { initAlertDismiss } from './components/alert.js';
import { initQuantityStepper } from './components/quantity.js';
import { initModalTriggers } from './components/modal.js';
import { initDrawerTriggers } from './components/drawer.js';
import { initToastTriggers } from './components/toast.js';
import { initProductCardActions } from './components/product-card.js';
import { initHeaderCartRefresh } from './components/header-cart.js';
import { initQuickOrderActions } from './components/quick-order.js';

// MintCart-owned catalog behavior modules are added here as components ship
// (Phase 4 onward).
initAlertDismiss();
initQuantityStepper();
initModalTriggers();
initDrawerTriggers();
initToastTriggers();
initProductCardActions();
initHeaderCartRefresh();
initQuickOrderActions();
