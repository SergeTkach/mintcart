# Presets

Phase 9 (`MINTCART_TECHNICAL_PLAN.md` §12/§42, `MINTCART_IMPLEMENTATION_GUIDE.md`
§24/§50, `MINTCART_FUNCTIONAL_SCOPE.md` §9): a **preset** changes only design
tokens and settings values. It never changes component DOM.

This is the other half of the Theme API, alongside `MintCart\Design\ComponentRenderer`
(`docs/developer/theme-api/component-renderer.md`):

```text
Preset  -> tokens/settings only, same Twig
Theme   -> can also override component DOM (via ComponentRenderer)
```

CSS: `assets/src/css/presets/clean.css`, `assets/src/css/presets/warm.css`
Bundled from: `assets/src/css/index.css`

## Mechanism

Each preset file scopes its overrides under an attribute selector on the
document root:

```css
:root[data-mc-preset='warm'] {
    --mc-primary: #c2410c;
    /* ... */
}
```

Whichever preset is active is expressed by setting `data-mc-preset` on
`<html>` (e.g. `<html data-mc-preset="warm">`). `CLAUDE.md` §5 frames
`data-mc-*` as "JavaScript behavior hooks"; a preset attribute is read by
CSS, not by a `data-mc-action` click handler, but it is the same kind of
root-level configuration attribute Bootstrap itself already uses
(`data-bs-theme`) and MintCart's `data-mc-*` namespace deliberately mirrors
Bootstrap's `data-bs-*` (`CLAUDE.md` §5). A future preset picker will still
set it from JS, so it is a legitimate `data-mc-*` attribute either way —
just not a `data-mc-action` one.

No property is redefined on
a bare `:root` — only on `:root[data-mc-preset="..."]` — so a preset file
has zero effect unless that attribute is present. This is why import order
in `assets/src/css/index.css` doesn't matter: an attribute-scoped selector
always outranks a bare `:root`/class rule on specificity, regardless of
where it appears in the bundle.

**No live picker sets `data-mc-preset` yet.** Choosing a preset is a Setup
Wizard / Theme Settings concern (`MINTCART_TECHNICAL_PLAN.md` §21, §31 —
Phase 13, Phase 31 in `docs/IMPLEMENTATION_STATUS.md`), neither of which is
built. This phase's job was only to prove the token system itself can carry
a genuinely different look with 0 Twig changes; wiring `data-mc-preset` from
a real admin setting is later, standard-OpenCart-storage work per
`CLAUDE.md` §39/§5A, not a new problem.

## What a preset may override

Per the Implementation Guide, a preset may redefine:

- semantic color tokens (`--mc-primary`, `--mc-surface`, `--mc-text`, ...)
- typography tokens (`--mc-font-family-*`)
- spacing tokens (`--mc-space-*`)
- radius/border/shadow tokens (`--mc-radius-*`, `--mc-shadow-*`)
- a component's own local tokens directly (e.g. `--mc-product-card-radius`,
  `--mc-button-radius`) for a distinctive "card look"/"button look" beyond
  what redefining the semantic tokens alone produces

A preset must **not**:

- add or remove markup, classes, or `data-mc-*` hooks (that's a theme
  component override, not a preset — see `component-renderer.md`)
- redefine the functional state colors (`--mc-success`/`--mc-danger`/
  `--mc-warning`/`--mc-info`). Those carry meaning (stock status, form
  errors, ...), not brand identity — a preset re-tuning them for aesthetics
  risks a contrast/usability regression a preset has no business making.

## The two shipped presets

### Clean (`presets/clean.css`)

MintCart's neutral baseline, already fully defined by `settings/tokens.css`
— crisp small radii, a flat bordered card, white surfaces, the default
mint/gray palette. The file contains no overrides; it exists so
`data-mc-preset` has two explicit, listed values instead of "unset = the
default" (`mint_default/theme.json`'s `presets` array lists both).

### Warm (`presets/warm.css`)

Deliberately different, to prove the token system alone carries a distinct
identity:

| Token(s)                                   | Clean                    | Warm                              |
| ------------------------------------------ | ------------------------ | ---------------------------------- |
| `--mc-primary`                             | mint green (`#059669`)   | terracotta (`#c2410c`)             |
| `--mc-surface` / `--mc-surface-alt`        | white / near-white gray  | warm off-white / warm sand         |
| `--mc-text` / `--mc-muted` / `--mc-border` | near-black gray          | warm brown / taupe / tan           |
| `--mc-font-family-heading`                 | system sans (base stack) | `Georgia, "Times New Roman", serif` |
| `--mc-radius-sm/md/lg/xl`                  | 0.25–0.75rem (crisp)     | 0.5–1.75rem (soft)                 |
| `--mc-shadow-sm/md/lg`                     | neutral black shadow     | warm-tinted, more diffused         |
| `--mc-space-4/6`                           | 1rem / 1.5rem            | 1.125rem / 1.75rem (roomier)       |
| `.mc-product-card`/`.mc-card` local tokens | flat bordered card       | borderless, larger radius/shadow/padding |
| `.mc-button` local tokens                  | rounded rectangle        | pill-shaped, wider padding         |

State colors (`--mc-success`/`--mc-danger`/`--mc-warning`/`--mc-info`) are
unchanged, per the rule above.

## Adding a new preset

1. Create `assets/src/css/presets/<name>.css`, scoping every rule under
   `:root[data-mc-preset='<name>']` (or a descendant of it for
   component-local overrides) — never a bare `:root`.
2. Add the `@import './presets/<name>.css';` line to `assets/src/css/index.css`.
3. List `<name>` in the theme's `theme.json` `presets` array
   (`MINTCART_IMPLEMENTATION_GUIDE.md` §30).
4. Update the table above (or the equivalent for the new theme) documenting
   what changed and why.

## Verifying this without a live install

This environment still has no MySQL / running storefront (the same gap
noted for the Theme API milestone, `docs/adr/002-theme-api-milestone-proof.md`),
so this phase's proof is at the compiled-CSS level rather than an observed
browser render:

```sh
npm install && npm run build:css
grep -n "data-mc-preset" assets/dist/css/index.min.css
```

confirms all four `[data-mc-preset=warm]` rule blocks (root tokens,
product-card/card, button) land in the bundle with the values from the
table above. A human should still confirm the visual result on a real
install by setting `<html data-mc-preset="warm">` by hand in the rendered
page and reloading, before Phase 9 is considered fully closed end-to-end —
same caveat pattern as the Theme API and Footer milestones before it.
