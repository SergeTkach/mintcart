# `mc-card`

MintCart's generic content/media card — a layout primitive for a content
teaser, an info block, a plain listing item, etc. This is **not** the
product card: the ecommerce-specific `mc-product-card` (price, badges,
add-to-cart, wishlist/compare) gets its own contract once
`ProductViewProvider` exists (Phase 6, Technical Plan §15D, Implementation
Guide §47). `mc-card` is pure CSS — no JS.

CSS: `assets/src/css/components/card.css`

## Basic usage

```html
<article class="mc-card">
    <div class="mc-card__media">
        <img src="/image/catalog/example.jpg" alt="">
    </div>

    <div class="mc-card__body">
        <h3 class="mc-card__title">Card title</h3>
        <p class="mc-card__text">
            Supporting text for the card, kept short.
        </p>

        <div class="mc-card__actions">
            <a href="/information/example" class="mc-button mc-button--primary mc-button--sm">
                Read more
            </a>
        </div>
    </div>
</article>
```

`.mc-card__media` is optional — omit it for a text-only card. `img` fills
the media area via `object-fit: cover` inside a fixed `16 / 9` aspect
ratio, so the card's height stays predictable in a grid regardless of the
source image's own aspect ratio.

## Footer

```html
<article class="mc-card">
    <div class="mc-card__body">
        <h3 class="mc-card__title">Card title</h3>
        <p class="mc-card__text">Supporting text.</p>
    </div>

    <div class="mc-card__footer">Updated 3 days ago</div>
</article>
```

`.mc-card__footer` is a separate section outside `.mc-card__body` (its own
top border and padding), for metadata that shouldn't compete with
`.mc-card__actions` for the "push to bottom" `margin-top: auto` behavior.

## Modifiers

`.mc-card--flush` removes `.mc-card__body`'s padding, for a card whose
inner content supplies its own spacing (e.g. a body that is itself a list
with its own item padding):

```html
<article class="mc-card mc-card--flush">
    <div class="mc-card__body">
        <ul class="list-group list-group-flush">
            <li class="list-group-item">Item one</li>
            <li class="list-group-item">Item two</li>
        </ul>
    </div>
</article>
```

`.mc-card--hoverable` adds a hover affordance (shadow + lift) for a card
that is itself one clickable/actionable unit. It only supplies the visual
affordance — the consumer is responsible for the actual interaction (e.g.
wrapping the card in an `<a>`, or wiring a `data-mc-action` on it per
`CLAUDE.md` §14):

```html
<a href="/product/42" class="mc-card mc-card--hoverable text-decoration-none">
    <div class="mc-card__media">
        <img src="/image/catalog/example.jpg" alt="">
    </div>
    <div class="mc-card__body">
        <h3 class="mc-card__title">Card title</h3>
    </div>
</a>
```

## Grid layout

`mc-card` has no opinion on how many columns it sits in — combine it with
Bootstrap's grid (`CLAUDE.md` §6/§8):

```html
<div class="row g-4">
    <div class="col-6 col-lg-4">
        <article class="mc-card">...</article>
    </div>
    <div class="col-6 col-lg-4">
        <article class="mc-card">...</article>
    </div>
</div>
```

## Accessibility

- Purely structural/presentational — no interactive state of its own to
  manage. Any interactivity (a link wrapping the card, a button inside it)
  keeps its own native keyboard/focus behavior; `mc-card` does not
  intercept it.
- `.mc-card--hoverable`'s lift transform is disabled under
  `prefers-reduced-motion: reduce`; the shadow-change affordance is kept
  since it isn't motion.
- Media images need their own `alt` text supplied by the consumer — this
  component has no say over that (it's content, not presentation).

## Definition of Done checklist (`MINTCART_IMPLEMENTATION_GUIDE.md` §79)

- [x] Documented contract (this file)
- [x] `mc-*` BEM markup
- [x] No JS dependency on CSS classes (no JS at all — pure layout/presentation)
- [x] Colors/spacing/fonts read from `--mc-*` tokens
- [x] Keyboard usable (no custom interactive behavior to trap focus)
- [x] Mobile checked (flex layout, no fixed widths; grid placement is the
      consumer's choice via Bootstrap columns)
- [x] No Bootstrap 3 dependency; Bootstrap 5 grid/utilities may be combined
      alongside it (e.g. `row g-4` / `col-lg-4` wrapping `.mc-card`)
- [x] No OCMOD required
- [ ] Theme override checked — deferred until the Theme resolver exists
      (Phase 8); there is no theme to override yet
