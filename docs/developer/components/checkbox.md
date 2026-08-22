# `mc-checkbox`

MintCart's checkbox component. Styles the native `<input type="checkbox">`
via `accent-color` rather than hiding it behind a custom faux-control — this
keeps native keyboard/AT behavior for free and avoids inventing a checkmark
icon dependency ahead of the Bootstrap Icons SVG renderer
(`MINTCART_IMPLEMENTATION_GUIDE.md` §21, Phase 21, no consumer yet —
`CLAUDE.md` §85).

CSS: `assets/src/css/components/checkbox.css`

## Basic usage

Wrapping the input and its label text in one `<label>` gives an implicit
label association without needing a matching `id`, which matters for
dynamically generated lists (e.g. a category multi-select):

```html
<label class="mc-checkbox">
    <input type="checkbox" class="mc-checkbox__input" name="agree" value="1">
    <span class="mc-checkbox__label">I agree to the terms</span>
</label>
```

## Checked / disabled

```html
<label class="mc-checkbox">
    <input type="checkbox" class="mc-checkbox__input" name="newsletter" value="1" checked>
    <span class="mc-checkbox__label">Subscribe to the newsletter</span>
</label>

<label class="mc-checkbox">
    <input type="checkbox" class="mc-checkbox__input" name="locked" value="1" disabled>
    <span class="mc-checkbox__label">Managed by store settings</span>
</label>
```

## Group

```html
<fieldset>
    <legend>Notify me about</legend>
    <label class="mc-checkbox">
        <input type="checkbox" class="mc-checkbox__input" name="notify[]" value="orders" checked>
        <span class="mc-checkbox__label">New orders</span>
    </label>
    <label class="mc-checkbox">
        <input type="checkbox" class="mc-checkbox__input" name="notify[]" value="reviews">
        <span class="mc-checkbox__label">New reviews</span>
    </label>
</fieldset>
```

## Accessibility

- Native `<input type="checkbox">` — full keyboard (Space to toggle) and
  screen reader support out of the box.
- `:focus-visible` on the input itself shows a ring, matching `mc-button`'s
  focus treatment.
- The wrapping `<label>` makes the whole row clickable and gives the control
  its accessible name; a group of related checkboxes should sit inside a
  `<fieldset>`/`<legend>` (not part of this component — a page/form concern).
- Disabled state uses the native `disabled` attribute, so AT announces it
  correctly without extra ARIA.

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
