# ADR 012 — Storefront JavaScript loading model (Mint vs extensions)

Status: accepted (2026-10-07)

## Context

Mint-aware storefronts run Bootstrap 5 and ship shared libraries (Swiper 14,
GLightbox) through the esbuild bundle. Swiper was split into a lazy bundle
(`slider.min.js`) loaded on demand. The question: how does a third-party
extension get its own scripts onto the page, and how does it use a library
MintCart already ships - without each extension loading its own copy, and
without a new framework?

## Decision

Two lanes, nothing else:

1. **An extension's own JS/CSS - unchanged OpenCart way.**
   `$this->document->addScript()` / `addStyle()` in its controller.
   mint_default's header prints that queue. HTML an extension injects later
   (AJAX) brings or triggers its own script itself - Mint does not manage
   third-party script loading.

2. **Libraries MintCart integrates (Bootstrap 5, Swiper, GLightbox, ...) -
   only MintCart loads them.** An extension asks for them through the
   documented component markup (e.g. `.mc-slider[data-mc-slider]`), never
   by loading another copy. Mint decides how each library is delivered:
   - small / used on most pages -> in `catalog.min.js` (Bootstrap 5 JS,
     GLightbox today);
   - heavy / used on few pages -> separate lazy bundle loaded when its
     marker appears in the DOM, including HTML inserted later
     (MutationObserver) - Swiper / `slider.min.js` is the reference case.
   Raw library access is given per component through its ready event
   (`mc:slider:ready` -> `detail.swiper`) and a small `window.MintCart.*`
   handle, not through a global library loader.

A generic `MintCart.load('library')` API is **not** built until a second
real consumer needs it (CLAUDE.md §17, Guide §85). The next lazy library
copies the slider pattern; if that happens twice, extract the loader.

## Consequences

- Extensions keep working the OpenCart way; nothing new to learn for their
  own code.
- No duplicate Bootstrap/Swiper copies from extensions on Mint themes; one
  version, one stylesheet, one set of events.
- Each Mint component that wraps a library must document markup, options,
  ready event and late-HTML behaviour (`docs/developer/components/slider.md`
  is the template).
- Extensions needing a library Mint does not ship still bring it via
  `addScript()` and own the consequences.

Details for extension authors: `docs/developer/extensions/storefront-js.md`.
