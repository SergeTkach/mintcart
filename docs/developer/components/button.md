# `mc-button`

MintCart's button component. Works on `<button>` and `<a>` alike — `.mc-button`
is a presentation contract only; it never doubles as a JS behavior hook
(`CLAUDE.md` §14). If a button triggers behavior, wire it with `data-mc-action`
separately, e.g.:

```html
<button type="button" class="mc-button mc-button--primary" data-mc-action="add-to-cart">
    Add to cart
</button>
```

CSS: `assets/src/css/components/button.css`

## Variants

```html
<button type="button" class="mc-button mc-button--primary">Primary</button>
<button type="button" class="mc-button mc-button--secondary">Secondary</button>
<button type="button" class="mc-button mc-button--outline">Outline</button>
<button type="button" class="mc-button mc-button--ghost">Ghost</button>
<button type="button" class="mc-button mc-button--danger">Danger</button>
```

`.mc-button--primary` is the implicit default — writing it explicitly on
`.mc-button` is optional but recommended for call-site clarity.

## Sizes

```html
<button type="button" class="mc-button mc-button--primary mc-button--sm">Small</button>
<button type="button" class="mc-button mc-button--primary">Default</button>
<button type="button" class="mc-button mc-button--primary mc-button--lg">Large</button>
```

## As a link

```html
<a href="/category/42" class="mc-button mc-button--secondary">View category</a>
```

## Disabled

```html
<button type="button" class="mc-button mc-button--primary" disabled>
    Disabled
</button>
```

A non-`<button>` element (e.g. `<a>`) has no native `disabled` attribute —
use `aria-disabled="true"` instead, which the CSS also targets:

```html
<a href="#" class="mc-button mc-button--secondary" aria-disabled="true" tabindex="-1">
    Unavailable
</a>
```

## Loading state

`is-loading` is a transient runtime state (`CLAUDE.md` §13), not a BEM
modifier — toggle it from JS when an action starts/finishes. Pair it with
`aria-busy` and `disabled` so assistive tech and keyboard users see the same
state the CSS shows:

```html
<button type="button" class="mc-button mc-button--primary is-loading" aria-busy="true" disabled>
    Processing…
</button>
```

## Accessibility

- Keyboard-focusable by default (`<button>`/`<a href>`); focus is visible via
  `:focus-visible` only, so mouse/touch activation doesn't show a ring.
- Disabled/loading states are conveyed through native attributes
  (`disabled`, `aria-disabled`, `aria-busy`), not color alone.
- The loading spinner respects `prefers-reduced-motion` by slowing down
  rather than disappearing — it is essential motion (it is the only signal
  that the action is in progress), so removing it entirely would leave
  reduced-motion users with no feedback.

## Definition of Done checklist (`MINTCART_IMPLEMENTATION_GUIDE.md` §79)

- [x] Documented contract (this file)
- [x] `mc-*` BEM markup
- [x] No JS dependency on CSS classes (only the optional `data-mc-action` hook,
      wired separately per call site)
- [x] Colors/spacing/fonts read from `--mc-*` tokens
- [x] Keyboard usable
- [x] Mobile checked (flex layout, no fixed widths)
- [x] No Bootstrap 3 dependency; Bootstrap 5 utilities may be combined
      alongside it (e.g. `mc-button mc-button--primary w-100`)
- [x] No OCMOD required
- [ ] Theme override checked — deferred until the Theme resolver exists
      (Phase 8); there is no theme to override yet
