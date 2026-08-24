# 004 — Design token presets scoped by `data-mc-preset`

## Context

`MINTCART_IMPLEMENTATION_GUIDE.md` §24/§51 and `MINTCART_TECHNICAL_PLAN.md`
§12 split theme customization in two: a **preset** changes design token
values only, a **full theme** may change component DOM through the Theme
API. Phase 8 proved the second half (`docs/adr/002-theme-api-milestone-proof.md`).
Phase 9's checklist asks for the first: at least two radically different
presets on the same DOM, differing in colors/fonts/radius/spacing/card
look/button look but not Twig — this is what validates that the Phase 3
token system actually carries a design, rather than just existing.

Three decisions had to be made to build that:

1. **How a preset is scoped.** The token system is native CSS custom
   properties on `:root` (ADR: Phase 3, `settings/tokens.css`), so a preset
   needs some selector that beats plain `:root` and can be switched at
   runtime for a live preview later (Functional Scope §31).
2. **How far a preset may reach.** Nothing structurally stops a CSS file
   from also restyling `.mc-product-card`. If presets are allowed to do
   that, the Preset/Theme split collapses and every preset becomes a
   partial theme that can break on a full theme's own DOM.
3. **What the baseline preset is.** MintCart's base tokens already describe
   a complete look. Whether that look is "no preset" or a named preset
   changes what a settings form can offer.

## Decision

**1. Scope presets with `:root[data-mc-preset='<code>']`.**

An attribute on the document root, not a class. `data-mc-preset` is
deliberately the one `data-mc-*` attribute used for styling rather than
behavior — Implementation Guide §14's "`data-mc-*` = behavior contract" rule
exists to stop JS from binding to component class names, which this does not
do. An attribute fits because the preset is a single global mode (a BEM
modifier class on `<html>` would be a modifier with no block), because a
future theme-settings form or live preview flips it with one `setAttribute`
and no class-list bookkeeping, and because attribute-selector specificity
(0-1-1) beats plain `:root` (0-0-1) — so a preset wins over the base tokens
no matter where it lands in the CSS import order.

**2. A preset file contains exactly one rule block, redeclaring only tokens
that `settings/tokens.css` already declares.**

Preset-local primitives are allowed, namespaced `--mc-preset-<code>-*`, as
intermediates feeding the semantic overrides; component CSS never reads them.
Forbidden: a token name the base file doesn't declare (that is new
component-facing API smuggled in through a preset), a component-local token
(`--mc-button-bg` — that would style one component out of step with the
rest), and any selector other than the preset's own block.

This is machine-checkable, and is checked: every token `warm.css` overrides
must already exist in `tokens.css`'s base `:root` block.

**3. `clean` is a real preset code whose file is an empty block.**

Clean's values *are* the base token values. `presets/clean.css` declares an
empty `:root[data-mc-preset='clean']` rather than restating ~108 declarations
that must then never drift from the originals (`CLAUDE.md` §86 — the simpler
option wins when it creates no coupling).

The first preset built against this is `warm`: terracotta/gold on cream,
serif headings, softer radius scale, roomier spacing, warm-tinted shadows —
25 semantic overrides, zero component rules.

## Consequences

- The Preset/Theme boundary is now a stated, verifiable rule rather than a
  convention. A preset that violates it fails the token-name check.
- Presets compose with full themes for free: because a preset never names a
  component, `warm` applies to a theme with entirely different component DOM,
  as long as that theme's CSS reads semantic tokens. This is the property
  that makes "preset" and "theme" orthogonal instead of two points on one
  scale.
- Every MintCart component must read semantic tokens for presets to reach it.
  Phase 4's components already do; this makes that a hard requirement going
  forward, not a stylistic preference — a component with a hardcoded color is
  now a component that silently ignores presets.
- Adding a preset costs one file, one `@import`, and one `theme.json` entry.
  Adding a *token* now costs a moment's thought about whether existing
  presets should override it.
- Nothing writes `data-mc-preset` at runtime yet. Mint Default's templates
  don't emit it and no admin UI selects it — that belongs with theme settings
  and its `settings.json` schema (Implementation Guide §31), which don't
  exist. Building the writer first would be API ahead of a consumer
  (`CLAUDE.md` §85). Until then the attribute is set by hand for inspection.
- The Phase 9 proof is at the token/build level, not an observed visual
  diff — this environment has no MySQL or Twig vendor to render a real page
  (the same gap carried since Phase 3). A human should eyeball both presets
  on a live install; tracked in `docs/MANUAL_VERIFICATION.md`.
