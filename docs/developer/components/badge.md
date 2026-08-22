# `mc-badge`

MintCart's badge/sticker component. Small labeled marker used on product
listings and cards for things like "New", "-20%" or "Out of stock"
(`docs/MINTCART_FUNCTIONAL_SCOPE.md` §11 "configurable badges/stickers").
Purely presentational — no JS, no `data-mc-*` hook.

CSS: `assets/src/css/components/badge.css`

## Variants

```html
<span class="mc-badge">Featured</span>
<span class="mc-badge mc-badge--new">New</span>
<span class="mc-badge mc-badge--sale">-20%</span>
<span class="mc-badge mc-badge--success">In stock</span>
<span class="mc-badge mc-badge--out-of-stock">Out of stock</span>
```

The base `.mc-badge` (no modifier) is a neutral dark badge — use it for
merchant-defined labels that don't map to one of the semantic variants
above.

## Custom color

A merchant-picked sticker color is data, not a design decision, so it is
set via the component's own custom property rather than a new modifier
class — this is the documented inline-style exception
(`MINTCART_IMPLEMENTATION_GUIDE.md` §18):

```html
<span class="mc-badge" style="--mc-badge-bg:#e83232">Limited</span>
```

`--mc-badge-color` can be set alongside it if the custom background needs a
non-white label color for contrast.

## Accessibility

- Text-based, so it's readable by screen readers without extra ARIA — avoid
  icon-only badges without an accessible label.
- Not focusable/interactive by design; if a badge ever needs to be
  clickable (e.g. links to the sale collection), wrap it in a `<a>`/`<button>`
  rather than adding click behavior to `.mc-badge` itself.

## Definition of Done checklist (`MINTCART_IMPLEMENTATION_GUIDE.md` §79)

- [x] Documented contract (this file)
- [x] `mc-*` BEM markup
- [x] No JS dependency (purely presentational)
- [x] Colors/spacing read from `--mc-*` tokens (custom color is the
      documented data-driven exception, §18)
- [x] Keyboard usable (not interactive; nothing to trap focus)
- [x] Mobile checked (inline-flex, no fixed widths)
- [x] No Bootstrap 3 dependency
- [x] No OCMOD required
- [ ] Theme override checked — deferred until the Theme resolver exists
      (Phase 8); there is no theme to override yet
