# `mc-select`

MintCart's standalone select component. Applied directly to a `<select>`
used on its own — outside a labeled `.mc-field` wrapper — such as a
sort-order or per-page-limit control next to a product listing
(`MINTCART_TECHNICAL_PLAN.md` §15E's `SortOptionsBuilder`/`LimitOptionsBuilder`).
For a select that's part of a labeled form (with a `.mc-field__label`,
help text or error message), use `mc-field`'s `select.mc-field__control`
instead — the two are not meant to be combined on the same element.

CSS: `assets/src/css/components/select.css`

## Basic usage

```html
<select class="mc-select" name="sort" aria-label="Sort by">
    <option value="name-asc">Name (A-Z)</option>
    <option value="price-asc">Price (low to high)</option>
    <option value="price-desc">Price (high to low)</option>
</select>
```

A standalone select without a visible `<label>` needs an accessible name —
`aria-label` (as above) or `aria-labelledby` pointing at nearby visible text.

The dropdown chevron is an inline SVG data URI placeholder, same as
`mc-field__control`'s — swap it for `{{ mc_icon('chevron-down') }}`-driven
markup once the Bootstrap Icons SVG renderer lands (`MINTCART_IMPLEMENTATION_GUIDE.md`
§21, Phase 21; see `select.css`'s header comment).

## Sizes

```html
<select class="mc-select mc-select--sm">...</select>
<select class="mc-select">...</select>
<select class="mc-select mc-select--lg">...</select>
```

Matches `mc-button--sm`/`mc-button--lg` naming and scale, for a select sitting
next to sized buttons (e.g. a listing toolbar).

## Multiple

```html
<select class="mc-select" name="tags[]" multiple aria-label="Tags">
    <option value="new">New</option>
    <option value="sale">Sale</option>
</select>
```

`[multiple]` renders as a native listbox rather than a closed one-line
dropdown, so the chevron and right padding are removed automatically and a
minimum height is applied instead.

## Error state

`has-error` is a transient runtime state (`CLAUDE.md` §13), not a BEM
modifier. Pair it with `aria-invalid` and `aria-describedby` on the element,
same convention as `mc-field`:

```html
<select
    class="mc-select has-error"
    name="country"
    aria-invalid="true"
    aria-describedby="country-error"
>
    <option value="">Choose a country</option>
</select>
<p class="mc-field__error" id="country-error" role="alert">Country is required.</p>
```

## Disabled

```html
<select class="mc-select" disabled>
    <option>Sold out</option>
</select>
```

## Accessibility

- Needs an accessible name — `aria-label`/`aria-labelledby` when used
  standalone (no visible `<label>`), or a real `<label for>` if wrapped in
  markup that provides one.
- Focus is shown via `:focus-visible` with a visible ring + border color
  change, matching `mc-field__control`/`mc-button`.
- `:disabled` uses the native attribute so keyboard/AT behavior is correct
  for free; `aria-disabled="true"` is supported the same way `mc-button`
  handles it.
- Error messages use `aria-invalid` + `aria-describedby`, with `role="alert"`
  on the message element, matching `mc-field`'s convention.

## Definition of Done checklist (`MINTCART_IMPLEMENTATION_GUIDE.md` §79)

- [x] Documented contract (this file)
- [x] `mc-*` BEM markup
- [x] No JS dependency (pure CSS; no `data-mc-*` hook needed)
- [x] Colors/spacing/fonts read from `--mc-*` tokens
- [x] Keyboard usable
- [x] Mobile checked (inline-block, `max-width: 100%`, no fixed widths)
- [x] No Bootstrap 3 dependency
- [x] No OCMOD required
- [ ] Theme override checked — deferred until the Theme resolver exists
      (Phase 8); there is no theme to override yet
