# `mc-radio`

MintCart's radio button component. Same approach as `mc-checkbox` (see
`docs/developer/components/checkbox.md`): styles the native
`<input type="radio">` via `accent-color` instead of a custom faux-control.

CSS: `assets/src/css/components/radio.css`

## Basic usage

```html
<label class="mc-radio">
    <input type="radio" class="mc-radio__input" name="delivery" value="pickup" checked>
    <span class="mc-radio__label">Pickup</span>
</label>
<label class="mc-radio">
    <input type="radio" class="mc-radio__input" name="delivery" value="courier">
    <span class="mc-radio__label">Courier</span>
</label>
```

## Group

Radio buttons only make sense as a group sharing one `name` — wrap them in a
`<fieldset>`/`<legend>` so the group has an accessible name:

```html
<fieldset>
    <legend>Delivery method</legend>
    <label class="mc-radio">
        <input type="radio" class="mc-radio__input" name="delivery" value="pickup" checked>
        <span class="mc-radio__label">Pickup</span>
    </label>
    <label class="mc-radio">
        <input type="radio" class="mc-radio__input" name="delivery" value="courier">
        <span class="mc-radio__label">Courier</span>
    </label>
</fieldset>
```

## Disabled

```html
<label class="mc-radio">
    <input type="radio" class="mc-radio__input" name="delivery" value="courier" disabled>
    <span class="mc-radio__label">Courier (unavailable in this region)</span>
</label>
```

## Accessibility

- Native `<input type="radio">` — arrow-key navigation within a group and
  screen reader support come for free.
- `:focus-visible` on the input shows a ring, matching `mc-checkbox`/
  `mc-button`.
- Group inside `<fieldset>`/`<legend>` for an accessible group name — a
  page/form concern, not part of this component.
- Disabled uses the native `disabled` attribute.

## Definition of Done checklist (`MINTCART_IMPLEMENTATION_GUIDE.md` §79)

- [x] Documented contract (this file)
- [x] `mc-*` BEM markup
- [x] No JS dependency (native control, no `data-mc-*` hook needed)
- [x] Colors/spacing read from `--mc-*` tokens
- [x] Keyboard usable
- [x] Mobile checked (flex layout, no fixed widths)
- [x] No Bootstrap 3 dependency
- [x] No OCMOD required
- [ ] Theme override checked — deferred until the Theme resolver exists
      (Phase 8); there is no theme to override yet
