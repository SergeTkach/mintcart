# `mc-price`

MintCart's price display component. Renders the normalized price/special/tax
fields that `ProductViewProvider` (`docs/MINTCART_TECHNICAL_PLAN.md` §15D)
will supply once Phase 6's Product Card exists. Purely presentational — no
JS, no `data-mc-*` hook.

CSS: `assets/src/css/components/price.css`

## Regular price

```html
<div class="mc-price">
    <span class="mc-price__current">$49.00</span>
</div>
```

## Special price

`mc-price--special` is a BEM modifier, not a transient `is-*`/`has-*`
state — whether a product currently has a special price is product data,
not a runtime UI state (`CLAUDE.md` §13 draws this same distinction: a
modifier for a data-driven look, `is-*`/`has-*` only for interaction state
like open/loading):

```html
<div class="mc-price mc-price--special">
    <span class="mc-price__current">$39.00</span>
    <span class="mc-price__original">$49.00</span>
</div>
```

## With tax note

```html
<div class="mc-price">
    <span class="mc-price__current">$49.00</span>
    <span class="mc-price__tax">Ex Tax: $40.83</span>
</div>
```

All three parts can combine (special price + tax note) — `mc-price__tax`
always takes its own line via `flex-basis: 100%` regardless of how many
price elements precede it.

## Accessibility

- Plain text content, read naturally by screen readers in document order —
  the crossed-out `.mc-price__original` reads as ordinary text (the
  strikethrough is decorative, not conveyed as a semantic deletion); if a
  page needs to announce "was $49.00" explicitly for AT users, wrap it with
  visually-hidden text at the call site rather than changing this component.
- Not interactive — nothing to keyboard-focus.

## Definition of Done checklist (`MINTCART_IMPLEMENTATION_GUIDE.md` §79)

- [x] Documented contract (this file)
- [x] `mc-*` BEM markup
- [x] No JS dependency (purely presentational)
- [x] Colors/spacing/fonts read from `--mc-*` tokens
- [x] Keyboard usable (not interactive; nothing to trap focus)
- [x] Mobile checked (flex-wrap, no fixed widths)
- [x] No Bootstrap 3 dependency
- [x] No OCMOD required
- [ ] Theme override checked — deferred until the Theme resolver exists
      (Phase 8); there is no theme to override yet
