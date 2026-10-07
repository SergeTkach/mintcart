/**
 * Lazy storefront entry for mc-slider (Swiper 14 is ~90 KB minified).
 * Built to assets/dist/js/slider(.min).js and injected by catalog.js only
 * once the page has a `[data-mc-slider]` - at load or added later
 * (Implementation Guide §4: Swiper is not loaded globally).
 *
 * Registers the public API `window.MintCart.sliders` and then sets up every
 * slider already in the document. Sliders added afterwards are picked up by
 * catalog.js's observer, which calls this API.
 * Contract: docs/developer/components/slider.md
 */
import { getSlider, initSliders } from './components/slider.js';

window.MintCart = window.MintCart || {};
window.MintCart.sliders = { init: initSliders, get: getSlider };

initSliders(document);
