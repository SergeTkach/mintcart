# `mc-rating`

MintCart's read-only star rating display. Renders a product's average
rating as a 0–5 star row — the `rating` field of the future `ProductView`
contract (`MINTCART_TECHNICAL_PLAN.md` §15D). Purely presentational — no
JS, no `data-mc-*` hook.

CSS: `assets/src/css/components/rating.css`

## Basic usage

The star row is two stacked layers of the same five-star string: a gray
"empty" layer and a colored "filled" layer clipped to a percentage width.
The percentage is per-product data, so it's set via the component's own
custom property — the same documented inline-style exception `mc-badge`
uses (`MINTCART_IMPLEMENTATION_GUIDE.md` §18):

```html
<div class="mc-rating" style="--mc-rating-value: 80%" role="img" aria-label="Rated 4 out of 5">
    <span class="mc-rating__stars" aria-hidden="true">
        <span class="mc-rating__stars-empty">★★★★★</span>
        <span class="mc-rating__stars-filled">★★★★★</span>
    </span>
</div>
```

`role="img"` + `aria-label` on the wrapper give assistive tech one clean
announcement ("Rated 4 out of 5"); the star glyphs themselves are
`aria-hidden` so they aren't read out character by character.

`--mc-rating-value` is computed by whatever renders the component
(`round(rating / 5 * 100)%` from a 0–5 average) — this component does not
do the rounding/percentage math itself, it only renders the given value.

## With a review count

```html
<div class="mc-rating" style="--mc-rating-value: 80%" role="img" aria-label="Rated 4 out of 5">
    <span class="mc-rating__stars" aria-hidden="true">
        <span class="mc-rating__stars-empty">★★★★★</span>
        <span class="mc-rating__stars-filled">★★★★★</span>
    </span>
</div>
<span class="mc-rating__count">(128)</span>
```

## No rating yet

A product with no reviews should not render `mc-rating` with a fake 0%
value pretending to be a real (if empty) rating — omit the component
entirely, or have the caller render its own "No reviews yet" text instead.

## Sizes

```html
<div class="mc-rating mc-rating--sm" style="--mc-rating-value: 60%" role="img" aria-label="Rated 3 out of 5">…</div>
<div class="mc-rating mc-rating--lg" style="--mc-rating-value: 100%" role="img" aria-label="Rated 5 out of 5">…</div>
```

## Not an input

This component is display-only. An *interactive* rating picker (e.g. "rate
this product") is a different component with different accessibility
requirements (radio-group or slider semantics, keyboard step behavior) and
has no consumer yet — it is not built ahead of that real use case
(`CLAUDE.md` §85).

## Accessibility

- `role="img"` + `aria-label` on `.mc-rating` is the single source of truth
  for assistive tech; the star glyphs are `aria-hidden="true"`.
- Never convey the rating by color alone — the `aria-label` text is
  required, not optional decoration.
- No focusable elements — nothing to keyboard-navigate.

## Definition of Done checklist (`MINTCART_IMPLEMENTATION_GUIDE.md` §79)

- [x] Documented contract (this file)
- [x] `mc-*` BEM markup
- [x] No JS dependency (purely presentational)
- [x] Colors/spacing read from `--mc-*` tokens (fill percentage is the
      documented data-driven exception, §18)
- [x] Keyboard usable (not interactive; nothing to trap focus)
- [x] Mobile checked (inline-flex, font-size driven sizing, no fixed widths)
- [x] No Bootstrap 3 dependency
- [x] No OCMOD required
- [ ] Theme override checked — deferred until the Theme resolver exists
      (Phase 8); there is no theme to override yet
