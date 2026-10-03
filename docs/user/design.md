# Design

MintCart's promise for design is "Ready to Look Good" and "Design Friendly"
(`MINTCART_PRODUCT_VISION.md` §6.3/§6.4): you shouldn't have to buy or
hand-code a theme to get a decent-looking store, and a designer shouldn't
have to patch OpenCart to build you a different one.

**This guide describes what's actually built today, not the full Theme API
roadmap.** Several pieces (Design → Styles, live preview, a theme picker)
are still open work — see `docs/IMPLEMENTATION_STATUS.md`'s Phase 8/9
sections for exactly what each routine run has shipped so far. If you want
the full intended scope, see `MINTCART_FUNCTIONAL_SCOPE.md` §9.

## What exists today

### Mint Default theme

MintCart ships with one theme, `mint_default`
(`upload/catalog/view/theme/mint_default/`), active out of the box. It
provides MintCart-owned storefront components — header, footer, product
card, product gallery, search results — styled with MintCart's own design
tokens rather than stock OpenCart's `default` theme markup.

### Design → Homepage

`Design → Homepage` in the admin menu (`design/homepage`) is MintCart's
Homepage Builder (`MINTCART_FUNCTIONAL_SCOPE.md` §10) — add, remove,
reorder and configure homepage blocks (Hero, Products, Categories, Banner
Grid, Benefits, and others). This is covered in its own guide; see
`docs/user/homepage.md` if present, or `docs/IMPLEMENTATION_STATUS.md`'s
Phase 10 (Blocks) section for current block coverage.

### Design → Layout

`Design → Layout` (`design/layout`) is stock OpenCart's own Layouts/Positions
screen — unchanged. It still works for anyone who wants to place legacy
OpenCart modules into template positions. `MINTCART_PRODUCT_VISION.md` §8
and `CLAUDE.md` §77 both intend this screen to eventually move under
`Advanced` rather than sit next to Homepage/Styles in everyday use, but that
navigation change hasn't happened yet — nothing is being removed, it's just
not the first place a day-to-day user needs to look.

### Changing a theme's look without touching Twig: presets

A **preset** is a set of design-token values (colors, typography, radius,
spacing, shadows) — not a different page layout
(`MINTCART_IMPLEMENTATION_GUIDE.md` §24, `docs/developer/theme-api/presets.md`).
Mint Default ships two:

| Preset | What it looks like |
| ------ | ------------------- |
| `clean` | MintCart's own neutral default — mint-green primary, system sans, tight radii |
| `warm`  | Terracotta/gold on cream, serif headings, rounder radii, roomier spacing |

**Known gap:** there is currently no admin screen to pick a preset — no
`Design → Styles` menu entry exists yet, and nothing in the shipped storefront
sets the `data-mc-preset` attribute presets rely on. The CSS for both presets
is built, tested, and ships in `assets/dist/css/`, but switching your live
store to `warm` today means manually adding `data-mc-preset="warm"` to the
`<html>` tag in a theme override — not something most store owners should
need to do by hand. A guided preset picker is tracked, unbuilt, work (see
`docs/IMPLEMENTATION_STATUS.md`'s Phase 9 section for current status).

### Changing a component's markup: themes and the Theme API

This is the designer/developer side of "Design Friendly"
(`MINTCART_PRODUCT_VISION.md` §6.4): a theme can override a single
MintCart component — `product-card.twig`, `header.twig`, `footer.twig` —
and get everything else from Mint Default automatically, with zero OCMOD,
zero controller patches, and zero copied business logic
(`CLAUDE.md` §19, `MINTCART_TECHNICAL_PLAN.md` §45). `catalog/view/theme/test_theme/`
in this repository is a real, working proof of this, not just a design
document — see `docs/adr/002-theme-api-milestone-proof.md`.

Building a custom theme this way is developer work, not an admin-screen
task. If you're a freelancer, designer, or developer building a store for a
client, start at `docs/developer/theme-api/component-renderer.md` and
`docs/developer/components/` rather than this guide.

## What you can't do yet

- Install, preview, or switch themes from the admin UI — there is no theme
  marketplace, theme manager screen, or live preview (`MINTCART_FUNCTIONAL_SCOPE.md`
  §31 live preview, §9 Theme System admin UI — both still roadmap).
- Pick a preset without editing a template file by hand (see "Known gap"
  above).
- Fill in a declarative theme settings form (colors, fonts, layout options) —
  `MINTCART_IMPLEMENTATION_GUIDE.md` §31's `settings.json` schema mechanism
  isn't built yet; `mint_default` has no `settings.json` today.

## Where to look next

- Homepage content: `docs/user/homepage.md` (if present) or
  `docs/IMPLEMENTATION_STATUS.md`'s Phase 10 section.
- Building or overriding a theme: `docs/developer/theme-api/component-renderer.md`,
  `docs/developer/theme-api/presets.md`, `docs/developer/components/`.
- Current build status for everything in this guide:
  `docs/IMPLEMENTATION_STATUS.md`'s Phase 8 (Theme resolver) and Phase 9
  (Presets) sections.
