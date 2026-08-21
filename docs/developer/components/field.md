# `mc-field`

MintCart's form field wrapper. Gives text/email/password/number/etc.
`<input>`, `<textarea>` and `<select>` a single shared look
(`.mc-field__control`) plus consistent label/help/error markup, instead of
each form styling its controls differently.

CSS: `assets/src/css/components/field.css`

## Basic usage

```html
<div class="mc-field">
    <label class="mc-field__label" for="store-name">Store name</label>
    <input type="text" id="store-name" name="store_name" class="mc-field__control">
</div>
```

## Textarea

```html
<div class="mc-field">
    <label class="mc-field__label" for="store-description">Description</label>
    <textarea id="store-description" name="description" class="mc-field__control"></textarea>
</div>
```

## Select

```html
<div class="mc-field">
    <label class="mc-field__label" for="store-country">Country</label>
    <select id="store-country" name="country" class="mc-field__control">
        <option value="ua">Ukraine</option>
        <option value="pl">Poland</option>
    </select>
</div>
```

The dropdown chevron is an inline SVG data URI placeholder — swap it for
`{{ mc_icon('chevron-down') }}`-driven markup once the Bootstrap Icons SVG
renderer lands (`MINTCART_IMPLEMENTATION_GUIDE.md` §21, Phase 21; see
`field.css`'s header comment).

## Help text

```html
<div class="mc-field">
    <label class="mc-field__label" for="store-email">Email</label>
    <input type="email" id="store-email" name="email" class="mc-field__control" aria-describedby="store-email-help">
    <p class="mc-field__help" id="store-email-help">Used for order notifications.</p>
</div>
```

## Error state

`has-error` is a transient runtime state (`CLAUDE.md` §13), not a BEM
modifier — set it when validation fails. Pair it with `aria-invalid` and
`aria-describedby` on the control so assistive tech gets the same signal the
CSS shows:

```html
<div class="mc-field has-error">
    <label class="mc-field__label" for="store-email">Email</label>
    <input
        type="email"
        id="store-email"
        name="email"
        class="mc-field__control"
        aria-invalid="true"
        aria-describedby="store-email-error"
    >
    <p class="mc-field__error" id="store-email-error" role="alert">Enter a valid email address.</p>
</div>
```

## Disabled

```html
<div class="mc-field">
    <label class="mc-field__label" for="store-code">Store code</label>
    <input type="text" id="store-code" name="code" class="mc-field__control" value="mint-01" disabled>
</div>
```

## Accessibility

- `<label for>`/`id` association is required — `.mc-field__label` is not a
  clickable-by-proximity visual only.
- Error messages use `aria-invalid` + `aria-describedby` on the control, and
  `role="alert"` on `.mc-field__error` so screen readers announce them
  immediately, matching `mc-alert`'s `role="alert"` convention.
- Focus is shown via `:focus-visible` with a visible ring + border color
  change — never color alone.
- `.mc-field__control:disabled` uses the native `disabled` attribute so
  keyboard/AT behavior is correct for free; `aria-disabled="true"` is
  supported for non-native cases the same way `mc-button` handles it.

## Definition of Done checklist (`MINTCART_IMPLEMENTATION_GUIDE.md` §79)

- [x] Documented contract (this file)
- [x] `mc-*` BEM markup
- [x] No JS dependency (pure CSS; no `data-mc-*` hook needed)
- [x] Colors/spacing/fonts read from `--mc-*` tokens
- [x] Keyboard usable
- [x] Mobile checked (block layout, no fixed widths)
- [x] No Bootstrap 3 dependency
- [x] No OCMOD required
- [ ] Theme override checked — deferred until the Theme resolver exists
      (Phase 8); there is no theme to override yet
