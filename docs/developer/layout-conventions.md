# Layout conventions

How MintCart builds page structure and responsive layout, and where that
stops and an `mc-*` component's own CSS begins.

Phase: `MINTCART_IMPLEMENTATION_GUIDE.md` §46 (Phase 5), tracked as
"Phase 2 — Frontend foundation" in `MINTCART_TECHNICAL_PLAN.md` §42.

## The rule

> Bootstrap 5's grid and utility classes are MintCart's only layout system.
> `mc-*` is for MintCart-owned semantic components and specific ecommerce
> layouts — never a parallel grid or utility framework.

This is stated in three places and is not optional:
`CLAUDE.md` §6, `MINTCART_IMPLEMENTATION_GUIDE.md` §7/§46,
`MINTCART_TECHNICAL_PLAN.md` §9.

Use Bootstrap 5's own classes for structure, spacing and responsiveness:

```text
.container / .container-fluid / .container-{sm,md,lg,xl,xxl}
.row
.col-*, .col-{sm,md,lg,xl,xxl}-*
.g-*, .gx-*, .gy-*        (grid gutters)
.d-flex, .d-grid, .d-none, .d-block, ...
.flex-*, .align-items-*, .justify-content-*
.m-*, .mt-*, .mb-*, .p-*, .px-*, .py-*, ...   (spacing utilities)
```

Do **not** create MintCart equivalents of these (no `mc-grid`, `mc-row`,
`mc-col`, `mc-stack`, `mc-cluster`, `mc-container`). If a Bootstrap utility
already solves the layout need, use it directly in the page/theme markup —
it does not need an `mc-*` wrapper class to be "allowed".

## Where `mc-*` starts

`mc-*` BEM classes are for:

- MintCart-owned ecommerce components (`mc-product-card`, `mc-price`,
  `mc-cart-drawer`, and the Phase 4 primitives already shipped —
  `mc-button`, `mc-field`, `mc-badge`, `mc-card`, `mc-modal`, `mc-drawer`,
  `mc-toast`, etc. — see `docs/developer/components/`);
- layout that is specific to an ecommerce concept Bootstrap has no opinion
  on (e.g. a future product gallery's thumbnail rail, a cart line-item
  layout) — still built with Bootstrap's flex/grid utilities *inside* the
  `mc-*` component where that's simplest, not by hand-rolling new grid
  math.

The pattern used throughout every Phase 4 component doc is: Bootstrap
primitives wrap the component, `mc-*` classes style the component itself.
For example (`docs/developer/components/modal.md`):

```html
<div class="modal mc-modal fade" id="exampleModal" ...>
    <div class="modal-dialog">
        <div class="modal-content">
            ...
        </div>
    </div>
</div>
```

or a product grid combining the Bootstrap grid with an MintCart component
(`CLAUDE.md` §6):

```html
<div class="container">
    <div class="row g-4">
        <div class="col-6 col-lg-3">
            <article class="mc-product-card">
                <div class="mc-product-card__media">...</div>
            </article>
        </div>
    </div>
</div>
```

`.container`/`.row`/`.col-*` are layout plumbing, not part of any `mc-*`
component's public contract — a Theme API consumer of `mc-product-card`
should never need to know or depend on which grid classes wrap it.

## Bootstrap components as generic primitives

Bootstrap's own interactive components (`Modal`, `Offcanvas`, `Toast`,
`Collapse`, `Dropdown`, tooltip/popover) may be used directly for generic
UI behavior — this is what `mc-modal`, `mc-drawer` and `mc-toast` already
do, wrapping Bootstrap's JS instead of reimplementing focus
management/backdrop/autohide from scratch (`MINTCART_IMPLEMENTATION_GUIDE.md`
§9). Their own classes (`.modal-dialog`, `.offcanvas-body`,
`.toast-header`, ...) stay in the markup as required internal
implementation. What must never happen is the reverse: making a Bootstrap
component class like `.card`, `.btn` or `.alert` *itself* the public Theme
API contract for an ecommerce component (`CLAUDE.md` §6,
`MINTCART_IMPLEMENTATION_GUIDE.md` §9) — that contract is always the
`mc-*` class.

## Verified: no Bootstrap 3 assumptions, no import-order conflicts

This phase's other requirement is confirming nothing shipped so far
assumes Bootstrap 3 or fights Bootstrap 5's own grid/utility classes.
Checked directly against the current tree:

- `assets/src/css/compatibility/bootstrap3-shim.css` (Phase 2) only
  reproduces legacy Bootstrap 3 leaf classes actually seen in OpenCart 3 /
  its extension ecosystem (`.pull-right`, `.pull-left`, `.hidden-xs`,
  `.visible-xs`, `.btn-default`, `.panel`/`.panel-heading`/`.panel-body`,
  `.input-group-btn`). None of these are Bootstrap 5 grid or container
  class names, so the shim cannot shadow or conflict with
  `.container`/`.row`/`.col-*`/`.g-*`.
- `grep -rnE '\.(container|row|col-|g-[0-9]|d-flex|d-grid)\b'` across
  `assets/src/css/components/` and `assets/src/css/settings/` returns only
  one hit: a comment in `settings/tokens.css` (`--mc-content-max-width`)
  explicitly noting that token is *not* a replacement for
  `.container`/`.container-*` — no component CSS file selects or
  overrides a Bootstrap grid/utility class name.
- `assets/src/css/index.css`'s import order (Bootstrap 5 → tokens →
  components → compatibility, per `MINTCART_IMPLEMENTATION_GUIDE.md` §11)
  loads the shim *after* every component, so a shim rule can only ever
  extend legacy markup, never get silently overridden by a later
  component import.
- No Twig/theme exists yet to render a real page against (that's Phase 8 —
  `ComponentRenderer`/theme resolver), so there is no live storefront page
  to browser-test a full responsive layout on at this stage. The
  verification available now — and the one this phase asks for — is this
  static check: the CSS shipped so far does not assume or require
  Bootstrap 3, and nothing in it conflicts with plain Bootstrap 5
  grid/utility usage. A live responsive-layout check against a real theme
  belongs to Phase 8, once there is a page to check.

## Definition of Done for this phase

- [x] Bootstrap 5 grid/utility usage rules documented (this file)
- [x] `mc-*` vs. Bootstrap boundary documented with examples
- [x] Confirmed no component CSS overrides Bootstrap grid/utility class
      names
- [x] Confirmed `bootstrap3-shim.css` only touches legacy leaf classes,
      never grid/container classes
- [x] Confirmed CSS import order doesn't let compatibility/legacy rules
      shadow component or grid/utility CSS
