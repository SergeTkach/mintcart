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
 *
 * Boot timing: same fix and same reason as `admin.js`'s own "Boot timing"
 * comment - `ControllerCommonHeader::index()`
 * (`docs/developer/admin/asset-enqueue.md` "Storefront:
 * ControllerCommonHeader") enqueues this bundle via
 * `$this->document->addScript()`, and `mint_default`'s `header.twig`
 * renders `{% for script in scripts %}` inside `<head>` as a plain
 * classic `<script src="...">`, which runs before `<body>` - and this
 * page's own markup - exists. `initSearchBoxes()`
 * (`root.querySelectorAll('[data-mc-search-box]')` at call time) is the
 * one call below with the same eager-query shape `admin.js`'s
 * `initSortableLists()` has; `boot()` defers the whole sequence the same
 * way.
 */

import 'bootstrap';

import { initBootstrap3Compat } from './compatibility/bootstrap3-jquery.js';

import { initAlertDismiss } from './components/alert.js';
import { initQuantityStepper } from './components/quantity.js';
import { initModalTriggers } from './components/modal.js';
import { initDrawerTriggers } from './components/drawer.js';
import { initToastTriggers } from './components/toast.js';
import { initProductCardActions } from './components/product-card.js';
import { initHeaderCounts } from './components/header-counts.js';
import { initHeaderCartRefresh } from './components/header-cart.js';
import { initProductGalleries } from './components/product-gallery.js';
import { initQuickOrderActions } from './components/quick-order.js';
import { initSearchBoxes } from './components/search-box.js';
import { initCookieConsent } from './components/cookie-consent.js';
import { initViewedProducts } from './components/viewed-products.js';
import { initReviews } from './components/reviews.js';
import { initCheckout } from './components/checkout.js';
import { initOptionUploads } from './components/option-upload.js';
import { initStickyHeader } from './components/sticky-header.js';
import { initHeaderUtility } from './components/header-utility.js';
import { initCartPage } from './components/cart-page.js';
import { initBuyBar } from './components/buy-bar.js';
import { initSectionNav } from './components/section-nav.js';
import { initProductPrice } from './components/product-price.js';
import { initPhoneMask } from './components/phone-mask.js';

// Swiper-based sliders live in their own bundle (slider(.min).js next to
// this file), fetched only when a `[data-mc-slider]` is on the page - at
// load, or inserted later by any script (AJAX, quick view, an extension).
// Contract: docs/developer/components/slider.md "Protocol".
// Resolved at load time, while document.currentScript still points at
// this classic <script>.
const sliderSrc = document.currentScript?.src.replace(/catalog(\.min)?\.js(\?.*)?$/, (match, min) => `slider${min || ''}.js`);
let sliderBundleRequested = false;

function hasSlider(node) {
    return node.nodeType === 1
        && (node.matches('[data-mc-slider]') || node.querySelector('[data-mc-slider]') !== null);
}

function loadSliders(root = document.documentElement) {
    if (!sliderSrc || !hasSlider(root)) {
        return;
    }

    // Bundle already here: set up just the new ones (idempotent).
    if (window.MintCart?.sliders) {
        window.MintCart.sliders.init(root);
        return;
    }

    // Requested but not executed yet: it scans the whole document on load.
    if (sliderBundleRequested) {
        return;
    }

    sliderBundleRequested = true;

    const script = document.createElement('script');
    script.src = sliderSrc;
    document.head.appendChild(script);
}

function watchSliders() {
    loadSliders();

    if (typeof MutationObserver !== 'function') {
        return;
    }

    new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            mutation.addedNodes.forEach((node) => loadSliders(node));
        }
    }).observe(document.body, { childList: true, subtree: true });
}

// MintCart-owned catalog behavior modules are added here as components ship
// (Phase 4 onward).
function boot() {
    // Legacy default/ templates + extensions on Bootstrap 5 (ADR 010). First,
    // and after Bootstrap's own DOMContentLoaded jQuery plugin registration.
    initBootstrap3Compat();
    initAlertDismiss();
    initQuantityStepper();
    initModalTriggers();
    initDrawerTriggers();
    initToastTriggers();
    initProductCardActions();
    initHeaderCounts();
    initHeaderCartRefresh();
    initProductGalleries();
    initQuickOrderActions();
    initSearchBoxes();
    initCookieConsent();
    initViewedProducts();
    initReviews();
    initCheckout();
    initOptionUploads();
    initStickyHeader();
    initHeaderUtility();
    initCartPage();
    initBuyBar();
    initSectionNav();
    initProductPrice();
    initPhoneMask();
    watchSliders();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}
