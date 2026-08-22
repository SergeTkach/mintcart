# `mc-quantity`

MintCart's quantity stepper. Decrement/increment buttons around a numeric
`<input>`, used wherever a shopper picks a quantity — cart line items, the
product page, Quick Order (`docs/MINTCART_FUNCTIONAL_SCOPE.md` §11/§13).

CSS: `assets/src/css/components/quantity.css`
JS: `assets/src/js/components/quantity.js` (`initQuantityStepper`)

## Basic usage

```html
<div class="mc-quantity">
    <button
        type="button"
        class="mc-quantity__button"
        data-mc-action="quantity-decrement"
        aria-label="Decrease quantity"
    >−</button>
    <input
        type="number"
        class="mc-quantity__input"
        name="quantity"
        value="1"
        min="1"
        max="99"
        step="1"
        inputmode="numeric"
        aria-label="Quantity"
    >
    <button
        type="button"
        class="mc-quantity__button"
        data-mc-action="quantity-increment"
        aria-label="Increase quantity"
    >+</button>
</div>
```

`min`/`max`/`step` are read straight from the native input attributes — the
component does not invent a parallel data contract for bounds. Omit `min`/
`max` for an unbounded stepper.

## Behavior

`initQuantityStepper()` is called once from both `catalog.js` and
`admin.js` and delegates on `document`, so steppers inserted later (e.g. a
cart drawer re-render via `fetch()`) work without a per-instance init call —
same pattern as `mc-alert`'s dismiss behavior.

- Clicking `[data-mc-action="quantity-decrement"]` /
  `[data-mc-action="quantity-increment"]` steps the input's value by `step`,
  clamped to `[min, max]`.
- After any change (button click or manual typing/paste into the input),
  the decrement/increment buttons are disabled once the value reaches
  `min`/`max` respectively, and re-enabled when it moves back inside range.
- A button click dispatches a native `change` event on the input (so any
  existing `change` listener — e.g. a future cart line-item updater — fires
  normally) and an `mc:quantity:change` custom event
  (`{ detail: { value } }`, bubbling) on `.mc-quantity` itself, following
  the `mc:cart:updated`-style event contract convention
  (`MINTCART_TECHNICAL_PLAN.md` §25).
- Typing a value outside `[min, max]` directly into the input is not
  clamped by this component (the browser/native `<input type="number">`
  validity state and any consuming form's own validation handle that) —
  only the button-driven step is clamped here.

## Sizes

```html
<div class="mc-quantity mc-quantity--sm">…</div>
```

## Disabled

Disable the whole stepper by disabling the input; the buttons follow its
disabled state automatically once `initQuantityStepper()` has run (or once
either button/input is clicked or the input changes):

```html
<div class="mc-quantity">
    <button type="button" class="mc-quantity__button" data-mc-action="quantity-decrement" aria-label="Decrease quantity" disabled>−</button>
    <input type="number" class="mc-quantity__input" value="1" min="1" disabled aria-label="Quantity">
    <button type="button" class="mc-quantity__button" data-mc-action="quantity-increment" aria-label="Increase quantity" disabled>+</button>
</div>
```

## Error state

`has-error` is a transient runtime state (`CLAUDE.md` §13), not a BEM
modifier:

```html
<div class="mc-quantity has-error">…</div>
```

## Accessibility

- Both buttons need an explicit `aria-label` ("Decrease/Increase quantity")
  since their visible content is a glyph, not descriptive text.
- The input needs its own accessible name — either `aria-label="Quantity"`
  or a `<label for>` in the surrounding form; don't rely on the buttons'
  labels to convey what the input is.
- Buttons are real `<button type="button">` elements, so they're keyboard
  reachable and activatable with Enter/Space for free; no custom key
  handling was added.
- Disabled state uses the native `disabled` attribute on both the input and
  the buttons it drives, so assistive tech and keyboard tab order are
  correct without extra ARIA.
- Native spinner arrows are hidden (`appearance: textfield` /
  `::-webkit-*-spin-button`) because the component's own buttons replace
  them — the native `<input type="number">` keyboard behavior (arrow
  keys, scroll) still works even with the visual spinner hidden.

## Definition of Done checklist (`MINTCART_IMPLEMENTATION_GUIDE.md` §79)

- [x] Documented contract (this file)
- [x] `mc-*` BEM markup
- [x] JS behavior only via `data-mc-action`, never a CSS class selector
- [x] Colors/spacing/fonts read from `--mc-*` tokens
- [x] Keyboard usable
- [x] Mobile checked (fixed comfortable tap-target width, no viewport-relative sizing)
- [x] No Bootstrap 3 dependency
- [x] No OCMOD required
- [ ] Theme override checked — deferred until the Theme resolver exists
      (Phase 8); there is no theme to override yet
