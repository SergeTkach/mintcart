# ADR 010 — mint_default storefront switches from Bootstrap 3 to Bootstrap 5

Status: accepted (2026-10-07), in progress — `docs/developer/storefront-bootstrap5-migration.md`

## Context

Bootstrap 5.3.8 is MintCart's frontend foundation (CLAUDE.md §4), but
`mint_default` still loaded the legacy Bootstrap 3 stack and only added
`components.min.css` (Mint CSS without Bootstrap) on top. Mint templates are
written for Bootstrap 5, so they rendered with BS3's `html{font-size:10px}`
and without BS5 utilities. Loading both Bootstraps together (tried
2026-08-24) breaks legacy pages and is forbidden by §10.

## Decision

On Mint-aware storefront themes Bootstrap 3 CSS/JS is no longer loaded.
`index.min.css` (Bootstrap 5 + Mint) and `catalog.min.js` (Bootstrap 5 JS +
Mint) are the base. jQuery, Font Awesome and `common.js` stay for legacy
code. Legacy markup and jQuery calls inherited from `default/` templates and
third-party extensions are covered by:

- `compatibility/bootstrap3-shim.css` — the BS3 classes actually used;
- `compatibility/bootstrap3-jquery.js` — `$.fn.button('loading'|'reset')`
  and `data-toggle` → `data-bs-toggle`.

Admin is out of scope and stays on Bootstrap 3 for now.

## Consequences

- Mint templates render as designed; `rem` tokens get the browser default.
- Legacy `default/` templates and extensions render through the shim, so
  some visual drift is expected and fixed in the shim, never in their Twig.
- Non-Mint themes (no `theme.json`) are untouched.
