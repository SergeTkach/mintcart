# ADR 011 — GLightbox for the product photo lightbox

Status: accepted (2026-10-07)

## Context

The product page gallery could only swap the main image; there was no
full-size view. Stock OpenCart uses jQuery Magnific Popup, which new Mint
code must not depend on (CLAUDE.md §7). The Cartello reference theme solved
the same need with GLightbox.

## Decision

Use GLightbox (npm `glightbox`, pinned 3.3.1, vanilla JS, no jQuery),
bundled through esbuild into `catalog.min.js` / `index.min.css` - not a CDN
tag. Used only by `product-gallery.js` (`data-mc-action="gallery-open"`).

## Consequences

- +~20 KB JS and ~10 KB CSS (minified) in the storefront bundle on every
  page; acceptable for now, revisit with conditional loading once the
  bundle is split per page (Functional Scope §19).
- The main image link keeps pointing at the full-size photo, so it works
  without JS.
