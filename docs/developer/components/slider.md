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
  <div class="mc-slider__dots" data-mc-slider-dots>   (hero only)
```

- Presets in `assets/src/js/components/slider.js`: `hero` (1 per view,
  autoplay 5 s), `logos` (2 → 6 per view by breakpoint, autoplay 3 s).
  Loop when there are enough slides, otherwise `rewind`. No autoplay for
  `prefers-reduced-motion`. A11y + keyboard modules on.
- **Lazy bundle:** Swiper lives in `assets/dist/js/slider(.min).js`
  (~90 KB); `catalog.js` injects it only when the page has
  `[data-mc-slider]` (Implementation Guide §4).
- **Own class names:** `mc-slider__track/__slide` are passed to Swiper as
  `wrapperClass` / `slideClass`; the needed Swiper core CSS is in
  `slider.css`. The stock controllers still enqueue OpenCart's Swiper 3
  CSS/JS (kept for legacy extensions) - it targets `swiper-*` classes, so the
  two never clash.
- State: `is-ready` on the root (set before Swiper measures - until then
  only the first slide is shown), `is-active` on the current slide / dot,
  `is-disabled` on an arrow at an end.
- Tokens: `--mc-slider-arrow-size`, `--mc-slider-radius`.
