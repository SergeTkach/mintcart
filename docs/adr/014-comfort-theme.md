# ADR 014 — Comfort preset and theme

## Context

The first "calm" attempt (preset `lounge`) only changed colours, and the
colours (beige, sage, brass) were unwelcome. The goal is a plain design that
is pleasant to read and use: neutral greys, comfortable rhythm, one family of
soft shapes, nothing decorative. A preset alone cannot change shapes or
markup.

## Decision

- Preset `comfort` (tokens only): white page, neutral greys, graphite action
  colour, medium-soft radii, gentle shadows, relaxed line height. It replaces
  `lounge`, which was removed.
- Theme `mint_comfort` (parent `mint_default`) changes form: its own
  `header.twig` (logo, short search, icon cart, language/currency/account) and
  `product-card.twig` (rounded image tile, price + compact button row).
  `theme-comfort.css` adds the matching CSS: `mc-header--comfort`,
  `mc-product-card--soft`, soft dropdown panels, selects with a chevron, a
  full-width graphite menu bar from `lg` up, table and reading polish.
  Page-level rules are scoped by `<html data-mc-theme="mint_comfort">`.

## Consequences

- No OCMOD, no controller changes; same data providers and contracts.
- The graphite menu bar needs `!important` against Bootstrap's transparent
  `.offcanvas-lg` from `lg` up; the rule is commented.
- Photos shot on white are blended (`multiply`) into the grey tile; the
  secondary hover image is not blended.
- Footer is still mint_default's.
