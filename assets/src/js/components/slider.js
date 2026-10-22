/**
 * mc-slider - Swiper 14 sliders (`[data-mc-slider]`, slider.css).
 * Public contract: docs/developer/components/slider.md.
 *
 * Markup uses mc-slider__* classes (wrapperClass/slideClass below) rather
 * than Swiper's own swiper-* names, so OpenCart's bundled Swiper 3 CSS -
 * still enqueued by the stock slideshow/carousel controllers for legacy
 * extensions - never styles these. Swiper's core CSS is reproduced in
 * slider.css for the same reason.
 *
 * - `data-mc-slider="hero|rich|logos"` picks a preset; any other value starts
 *   from a plain one-slide-per-view default.
 * - `data-mc-slider-options='{"slidesPerView":3}'` (JSON) is merged over
 *   the preset - Swiper parameters, documented subset in slider.md.
 * - Initialising is idempotent: a slider already set up is skipped, so
 *   `initSliders()` can be called on any root, any number of times.
 * - Each new slider dispatches `mc:slider:ready` ({ swiper }) on its root.
 * - Autoplay is skipped for prefers-reduced-motion.
 */
import Swiper from 'swiper';
import { A11y, Autoplay, Keyboard, Navigation, Pagination } from 'swiper/modules';

const reduceMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const instances = new WeakMap();

const PRESETS = {
    hero: {
        slidesPerView: 1,
        spaceBetween: 0,
        loop: true,
        autoplay: { delay: 5000, pauseOnMouseEnter: true, disableOnInteraction: false },
    },
    // Homepage Builder "Slider" block (blocks/slider.twig); the block overrides the
    // autoplay delay (or turns it off) through data-mc-slider-options.
    rich: {
        slidesPerView: 1,
        spaceBetween: 0,
        loop: true,
        autoplay: { delay: 6000, pauseOnMouseEnter: true, disableOnInteraction: false },
    },
    logos: {
        slidesPerView: 2,
        spaceBetween: 16,
        loop: true,
        autoplay: { delay: 3000, pauseOnMouseEnter: true, disableOnInteraction: false },
        breakpoints: {
            576: { slidesPerView: 3 },
            768: { slidesPerView: 4 },
            992: { slidesPerView: 5 },
            1200: { slidesPerView: 6 },
        },
    },
};

const DEFAULTS = { slidesPerView: 1, spaceBetween: 16, loop: false, autoplay: false };

function readOptions(el) {
    const raw = el.dataset.mcSliderOptions;

    if (!raw) {
        return {};
    }

    try {
        const parsed = JSON.parse(raw);
        return parsed && typeof parsed === 'object' ? parsed : {};
    } catch {
        console.warn('mc-slider: data-mc-slider-options is not valid JSON, ignored.', el);
        return {};
    }
}

function initSlider(el) {
    if (instances.has(el)) {
        return;
    }

    const viewport = el.querySelector('.mc-slider__viewport');
    const slides = el.querySelectorAll('.mc-slider__slide');

    if (!viewport || slides.length < 2) {
        return;
    }

    const { autoplay, loop: wantsLoop, ...options } = {
        ...(PRESETS[el.dataset.mcSlider] || DEFAULTS),
        ...readOptions(el),
    };
    const dots = el.querySelector('[data-mc-slider-dots]');
    // Loop needs more slides than are visible at once; with too few, rewind
    // to the first slide instead so autoplay does not stall on the last one.
    const loop = Boolean(wantsLoop) && slides.length > (options.slidesPerView || 1) + 1;

    // Before Swiper measures: slider.css hides every slide but the first
    // until `is-ready`, and a hidden slide would be measured as 0 wide.
    el.classList.add('is-ready');

    const swiper = new Swiper(viewport, {
        ...options,
        modules: [A11y, Autoplay, Keyboard, Navigation, Pagination],
        wrapperClass: 'mc-slider__track',
        slideClass: 'mc-slider__slide',
        slideActiveClass: 'is-active',
        loop,
        rewind: !loop,
        autoplay: reduceMotion() ? false : autoplay,
        keyboard: { enabled: true, onlyInViewport: true },
        a11y: { enabled: true },
        navigation: {
            prevEl: el.querySelector('[data-mc-slider-prev]'),
            nextEl: el.querySelector('[data-mc-slider-next]'),
            disabledClass: 'is-disabled',
        },
        pagination: dots
            ? {
                el: dots,
                clickable: true,
                bulletClass: 'mc-slider__dot',
                bulletActiveClass: 'is-active',
            }
            : false,
    });

    instances.set(el, swiper);
    el.dispatchEvent(new CustomEvent('mc:slider:ready', { bubbles: true, detail: { swiper } }));
}

/**
 * Sets up every `[data-mc-slider]` in `root` (and `root` itself) that is not
 * set up yet.
 */
export function initSliders(root = document) {
    if (root.nodeType === 1 && root.matches('[data-mc-slider]')) {
        initSlider(root);
    }

    root.querySelectorAll('[data-mc-slider]').forEach(initSlider);
}

/** The Swiper instance behind a slider root, or null. */
export function getSlider(el) {
    return instances.get(el) || null;
}
