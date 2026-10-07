/**
 * Lazy storefront entry for mc-slider (Swiper 14 is ~90 KB minified).
 * Built to assets/dist/js/slider(.min).js and injected by catalog.js only on
 * pages that contain `[data-mc-slider]` (Implementation Guide §4: Swiper is
 * not loaded globally).
 */
import { initSliders } from './components/slider.js';

initSliders();
