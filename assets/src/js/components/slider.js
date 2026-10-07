/**
 * mc-slider - Swiper 14 sliders (`[data-mc-slider="hero" | "logos"]`,
 * slider.css, docs/developer/components/slider.md).
 *
 * Markup uses mc-slider__* classes (wrapperClass/slideClass below) rather
 * than Swiper's own swiper-* names, so OpenCart's bundled Swiper 3 CSS -
 * still enqueued by the stock slideshow/carousel controllers for legacy
 * extensions - never styles these. Swiper's core CSS is reproduced in
 * slider.css for the same reason.
 *
 * Autoplay is skipped for prefers-reduced-motion.
 */
import Swiper from 'swiper';
import { A11y, Autoplay, Keyboard, Navigation, Pagination } from 'swiper/modules';

const reduceMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const PRESETS = {
    hero: {
        slidesPerView: 1,
        spaceBetween: 0,
        loop: true,
        autoplay: { delay: 5000, pauseOnMouseEnter: true, disableOnInteraction: false },
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

function initSlider(el) {
    const preset = PRESETS[el.dataset.mcSlider];
    const viewport = el.querySelector('.mc-slider__viewport');
    const slides = el.querySelectorAll('.mc-slider__slide');

    if (!preset || !viewport || slides.length < 2) {
        return;
    }

    const dots = el.querySelector('[data-mc-slider-dots]');
    const { autoplay, ...options } = preset;
    // Loop needs more slides than are visible at once; with too few, rewind
    // to the first slide instead so autoplay does not stall on the last one.
    const loop = options.loop && slides.length > (options.slidesPerView || 1) + 1;

    // Before Swiper measures: slider.css hides every slide but the first
    // until `is-ready`, and a hidden slide would be measured as 0 wide.
    el.classList.add('is-ready');

    new Swiper(viewport, {
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
}

export function initSliders(root = document) {
    root.querySelectorAll('[data-mc-slider]').forEach(initSlider);
}
