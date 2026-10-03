# Design presets

A **preset** changes how MintCart looks without changing what it renders
(`MINTCART_IMPLEMENTATION_GUIDE.md` §24, `MINTCART_TECHNICAL_PLAN.md` §12).
It is the lighter of the Theme API's two customization mechanisms:

```text
Preset      -> design token values only. Same DOM, same Twig, same contracts.
Full Theme  -> may replace component DOM, via ComponentRenderer overrides
               (see theme-api/component-renderer.md).
```

Presets shipped with Mint Default:

| Preset  | File                                | Look |
| ------- | ----------------------------------- | ---- |
| `clean` | `assets/src/css/presets/clean.css`  | MintCart's own neutral default — mint-green primary, system sans, tight radii |
| `warm`  | `assets/src/css/presets/warm.css`   | Terracotta/gold on cream, serif headings, rounder radii, roomier spacing, warm-tinted shadows |

## Activation contract

A preset is selected by an attribute on the document root:

```html
<html data-mc-preset="warm">
```

No attribute, or `data-mc-preset="clean"`, yields the base look — those are
the same thing by design (see "Why `clean.css` is empty" below).

`data-mc-preset` is the one `data-mc-*` attribute that is a *styling* hook
rather than a behavior hook. `MINTCART_IMPLEMENTATION_GUIDE.md` §14's rule
("CSS class = presentation contract, `data-mc-*` = behavior contract") is
about not coupling JS to component class names; it does not apply here, and
an attribute is the right shape for this because:

- it is a single global mode, not a component variant, so a BEM modifier
  class (`mc-theme--warm`) on `<html>` would be a modifier without a block;
- it is exactly what a future theme-settings form or live preview
  (Functional Scope §31) can flip with one `setAttribute` call, with no
  class-list bookkeeping;
- attribute-selector specificity (`:root[data-mc-preset='warm']`, 0-1-1)
  always beats plain `:root` (0-0-1), so a preset wins over the base tokens
  regardless of CSS import order.

## What a preset may and may not touch

A preset file contains exactly one rule block:

```css
:root[data-mc-preset='<code>'] {
    /* semantic-tier --mc-* tokens only */
}
```

**May** redeclare any token already declared in
`assets/src/css/settings/tokens.css` — semantic colors (`--mc-primary`,
`--mc-surface`, `--mc-text`, ...), typography, spacing, radius, border,
shadow, motion, z-index.

**May** declare preset-local primitives, namespaced `--mc-preset-<code>-*`
(e.g. `--mc-preset-warm-terracotta-600`), purely as intermediates that feed
the semantic overrides above. Component CSS never reads these — the same
primitive/semantic separation `tokens.css` itself uses.

**Must not**:

- introduce a token name that `tokens.css` does not already declare — that
  would be new component-facing API arriving through a preset, which no
  component reads and no other preset would define;
- set a component-local token directly (`--mc-button-bg`,
  `--mc-badge-bg`, ...). Those are each component's own tier and are already
  derived from semantic tokens; overriding one from a preset would style one
  component out of step with the rest;
- contain any selector other than its own `:root[data-mc-preset='<code>']`
  block — no component rules, no layout rules, no `@media` blocks beyond
  what `tokens.css` already handles.

The last two rules are what keep the promise "presets differ in
colors/fonts/radius/spacing/card look/button look only, not Twig". The
card and button *look* changes on a preset switch precisely because
`mc-card`/`mc-button` read semantic tokens — the preset never mentions them.

## Why `clean.css` is empty

Clean's values are `tokens.css`'s values. `clean.css` declares an empty
`:root[data-mc-preset='clean']` block rather than restating all ~108 token
declarations a second time.

This is deliberate (`CLAUDE.md` §86 — the simpler solution wins when it
creates no coupling). The alternative — a full copy of every token value —
would mean every future token change has to be made in two files that must
not drift, to produce a result identical to making it in one. What the file
buys as-is: `clean` is a real, enumerable preset code that a theme manifest
can list and a settings form can offer, and the "no preset selected" and
"Clean selected" states are provably the same rather than accidentally the
same.

## Declaring presets in a theme

`theme.json` lists the preset codes a theme supports
(`MINTCART_IMPLEMENTATION_GUIDE.md` §30):

```json
{
    "name": "Mint Default",
    "code": "mint_default",
    "version": "1.0.0",
    "mint_api": "^1.0",
    "parent": null,
    "presets": ["clean", "warm"]
}
```

A full theme with its own component DOM can list these same presets, since
a preset makes no assumption about DOM — only that components read semantic
tokens.

## Adding a preset

1. Create `assets/src/css/presets/<code>.css` with a single
   `:root[data-mc-preset='<code>']` block, following the rules above.
2. Add the `@import` to `assets/src/css/index.css` (after `settings/tokens.css`).
3. Add `<code>` to the `presets` array in each theme's `theme.json` that
   supports it.
4. Run `npm run build`.

## Wired: `Design → Styles` (2026-09-29)

`ControllerMintcartDesignStyles` (`upload/admin/controller/mintcart/design/styles.php`,
route `mintcart/design/styles`, menu entry under `Design`) is the first
slice of the future Theme Settings screen (`MINTCART_IMPLEMENTATION_GUIDE.md`
§31) — deliberately just the one setting this phase needed to prove the
mechanism, not a `settings.json`-driven schema for every future theme
option (`CLAUDE.md` §85: no API ahead of a real consumer).

- **Choice list:** read live from the *active* theme's own `theme.json`
  `presets` array (`ActiveTheme::directory()` + `DIR_CATALOG` on the admin
  side), never hardcoded — a theme with a different preset set, or none at
  all (`test_theme` ships `"presets": []`, the legacy `default` theme has
  no manifest at all), gets a correct empty state instead of a broken form.
- **Storage:** one setting, `mintcart_design_preset` (code group
  `mintcart_design`), written via the stock `setting/setting` model — same
  "own isolated code group, no merge needed" shape
  `ControllerMintcartSettingsDiagnostics` already uses for
  `mintcart_dev_mode`.
- **Storefront wiring:** `ControllerCommonHeader::index()` reads the
  setting and re-validates it against the active theme's own declared
  presets a second time (a value saved while one theme was active, then
  left stale after switching to a theme with a different preset list, must
  never reach the page as a `data-mc-preset` no loaded CSS defines a
  `:root` block for) before passing `$data['mc_preset']` through.
  `mint_default/template/common/header.twig`'s `<html>` tag renders
  `data-mc-preset="{{ mc_preset }}"` only when non-blank — an unset/blank
  choice (the default) omits the attribute entirely, which Phase 9 already
  established is visually identical to an explicit `clean` (`clean.css` is
  an empty token block). Not gated behind the `isMintAware()`/
  `$mint_render_header` check `mc_header` uses: this is a plain CSS
  attribute selector any theme's own `<html>` tag may choose to read, not a
  Mint component DOM contract, so a legacy/third-party theme pays nothing
  by simply never referencing `$data['mc_preset']` in its own header
  template (`test_theme` has no `common/header.twig` override at all yet,
  so it is unaffected either way).

Full write-ups: `docs/upstream-diff.md`'s `header.php` and
`common/column_left.php` entries.

**Not done yet:** `test_theme` never renders a `data-mc-preset` value
today (it has no `common/header.twig` of its own — a pre-existing gap, see
that theme's own limitations, not new to this increment); a live-preview
mechanism (ADR 008, still documentation-only); and the wider
`settings.json`-driven Theme Settings schema for options beyond "which
preset" (a separate, larger increment once a real theme needs one).

To see a preset without the admin form, the attribute can still be set by
hand in the browser (`document.documentElement.dataset.mcPreset = 'warm'`)
on any page loading the bundled `index.min.css`.

## Definition of Done checklist (`MINTCART_IMPLEMENTATION_GUIDE.md` §79)

- [x] Documented contract (this file)
- [x] Presets touch token values only — no component CSS, no Twig, no
      component-local tokens (script-verified, see
      `docs/IMPLEMENTATION_STATUS.md` Phase 9)
- [x] No JS dependency
- [x] No Bootstrap 3 dependency
- [x] No OCMOD required
- [x] Works on any theme's DOM, including a full theme's own components
- [ ] Live visual check across the two presets — needs a running install with
      a rendered page; this environment has no MySQL/Twig vendor (same
      pre-existing gap as Phases 3–8), tracked in `docs/MANUAL_VERIFICATION.md`
