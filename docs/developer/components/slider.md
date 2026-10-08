# Slider — `mc-slider` (Swiper 14)

mint_default overrides the stock `extension/module/slideshow.twig` and
`carousel.twig`; data unchanged (`banners[]`: `title`, `link`, `image`).

```html
<div class="mc-slider mc-slider--hero|--logos" data-mc-slider="hero|logos">
  <div class="mc-slider__viewport">
    <div class="mc-slider__track">
      <div class="mc-slider__slide"><a class="mc-slider__link"><img class="mc-slider__image">
  <button class="mc-slider__arrow mc-slider__arrow--prev" data-mc-slider-prev>
  <button class="mc-slider__arrow mc-slider__arrow--next" data-mc-slider-next>
  <div class="mc-slider__dots" data-mc-slider-dots>   (optional)
```

Only `.mc-slider[data-mc-slider]`, `.mc-slider__viewport`,
`.mc-slider__track` and two or more `.mc-slider__slide` are required;
arrows and dots are optional.

- Presets in `assets/src/js/components/slider.js`: `hero` (1 per view,
  autoplay 5 s), `logos` (2 → 6 per view by breakpoint, autoplay 3 s). Any
  other `data-mc-slider` value starts from a plain default (1 per view,
  16 px gap, no loop, no autoplay). Loop only when there are enough slides,
  otherwise `rewind`. No autoplay for `prefers-reduced-motion`. A11y +
  keyboard modules always on.
- **Own class names:** `mc-slider__track/__slide` are passed to Swiper as
  `wrapperClass` / `slideClass`; the needed Swiper core CSS is in
  `slider.css`. The stock controllers still enqueue OpenCart's Swiper 3
  CSS/JS (kept for legacy extensions) - it targets `swiper-*` classes, so the
  two never clash.
- State: `is-ready` on the root (set before Swiper measures - until then
  only the first slide is shown), `is-active` on the current slide / dot,
  `is-disabled` on an arrow at an end.
- Tokens: `--mc-slider-arrow-size`, `--mc-slider-radius`.

## Protocol (themes, blocks, third-party extensions)

**1. Markup is the request.** Output the HTML above anywhere on a storefront
page of a Mint-aware theme. Nothing to enqueue, no `addScript()`, no init
call: `catalog.js` sees `[data-mc-slider]`, loads
`assets/dist/js/slider(.min).js` once (about 90 KB, Swiper 14) and sets the
slider up. Pages without a slider never load Swiper (Implementation
Guide §4).

**2. Late HTML works too.** A `MutationObserver` in `catalog.js` watches the
document: a slider inserted by AJAX, a quick-view modal, tabs, "load more"
and so on is set up as soon as it is attached - no call needed. To rebuild,
remove the element and insert new markup.

**3. Options.** `data-mc-slider="<preset>"` plus optional JSON in
`data-mc-slider-options`, merged over the preset (shallow: a given
`breakpoints` replaces the preset's one). Supported keys (Swiper params):

| Key | Example |
|---|---|
| `slidesPerView` | `3`, `"auto"` |
| `spaceBetween` | `8` (px) |
| `loop` | `true` (falls back to rewind when there are too few slides) |
| `autoplay` | `false` or `{"delay": 4000}` |
| `speed` | `400` (ms) |
| `centeredSlides` | `true` |
| `breakpoints` | `{"768": {"slidesPerView": 4}}` |

Invalid JSON is ignored with a console warning (the preset still applies).
Class names, navigation and pagination belong to the component and cannot
be overridden through options.

```html
<div class="mc-slider" data-mc-slider="products"
     data-mc-slider-options='{"slidesPerView": 2, "breakpoints": {"992": {"slidesPerView": 4}}}'>
  ...
</div>
```

**4. JS API** (exists once the bundle has loaded - listen for the event
instead of polling):

- event `mc:slider:ready` on the slider root (bubbles), `detail.swiper` is
  the Swiper instance - attach your own Swiper listeners there;
- `window.MintCart.sliders.init(root?)` - set up sliders under `root`
  (idempotent; normally unnecessary thanks to the observer);
- `window.MintCart.sliders.get(element)` - the Swiper instance or `null`.

**5. Idempotent.** A slider is set up once; repeated `init()` calls or
re-observed nodes never create a second Swiper.

**Limits.** `catalog.min.js` must stay a classic `<script>` (its
`document.currentScript` locates `slider.min.js`); as an ES module it would
need `import.meta.url`. The bundle request starts after DOMContentLoaded -
until it runs only the first slide is visible (no layout jump).

Verified 2026-10-07 in headless Edge: no bundle on a page without sliders;
a slider appended after load pulls the bundle and honours its options; a
second appended slider is set up without a second request; repeated
`init()` keeps the same instance.

See also `docs/developer/extensions/storefront-js.md` (JS protocol for
extensions).
