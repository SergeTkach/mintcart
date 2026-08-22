# `mc-drawer`

MintCart's slide-in panel component. Use for the cart drawer and mobile
navigation — both mentioned explicitly as Cart UX/navigation options
(`docs/MINTCART_TECHNICAL_PLAN.md` §15, `docs/MINTCART_FUNCTIONAL_SCOPE.md`
§15) — and any other content that should slide in from a screen edge rather
than interrupt the page centrally like `mc-modal`.

CSS: `assets/src/css/components/drawer.css`
JS: `assets/src/js/components/drawer.js` (`initDrawerTriggers`)

## Why it wraps Bootstrap's `Offcanvas`

`mc-drawer` reuses Bootstrap 5's `Offcanvas` JS for the hard accessibility
work — focus trap, `Escape`-to-close, backdrop click-to-close, background
scroll lock, restoring focus to the trigger on close — instead of
reimplementing a slide-in panel from scratch. This is the same allowance
`mc-modal` already uses for `Modal`: `MINTCART_IMPLEMENTATION_GUIDE.md` §9
explicitly permits reusing generic Bootstrap interaction primitives ("Not
needed to rewrite Bootstrap Modal own without a product reason" — the same
reasoning applies to `Offcanvas`, Bootstrap's other built-in dialog-like
primitive).

Bootstrap's own `.offcanvas`/`.offcanvas-start|end|top|bottom`/
`.offcanvas-header`/`.offcanvas-body`/`.offcanvas-title` classes stay on the
markup because `Offcanvas`'s JS selects/animates on them directly and its
CSS (fixed positioning, backdrop, slide transition, placement variants)
lives on those classes. They are internal implementation, the same way
`.container`/`.row`/`.col-*` are layout plumbing around an `mc-*` component
(`MINTCART_IMPLEMENTATION_GUIDE.md` §6) — **`.mc-drawer`/`.mc-drawer__*` and
`data-mc-*` are the public contract**, not the Bootstrap classes.

## Basic usage

```html
<button
    type="button"
    class="mc-button mc-button--primary"
    data-mc-action="open-drawer"
    data-mc-target="#cartDrawer"
>Open cart</button>

<div class="offcanvas offcanvas-end mc-drawer" tabindex="-1" id="cartDrawer" aria-labelledby="cartDrawerTitle">
    <div class="offcanvas-header">
        <h2 class="offcanvas-title mc-drawer__title" id="cartDrawerTitle">Your cart</h2>
        <button type="button" class="mc-drawer__close" data-mc-action="close-drawer" aria-label="Close">
            &times;
        </button>
    </div>
    <div class="offcanvas-body">
        <p>Cart contents go here.</p>
    </div>
</div>
```

- `data-mc-action="open-drawer"` + `data-mc-target="<selector>"` on the
  trigger opens the matching `.mc-drawer`. Any element can be a trigger — it
  does not have to be an `mc-button`.
- `data-mc-action="close-drawer"` inside the drawer closes its nearest
  `.mc-drawer` ancestor. Add it to the header's close button and/or any
  in-body "Cancel"/"Continue shopping" action.
- `tabindex="-1"` on the root is required by Bootstrap's `Offcanvas` (it
  manages `aria-modal`/`role="dialog"` itself once shown) — keep it in the
  markup as written above.
- `aria-labelledby` on the root pointing at the title's `id` gives the panel
  its accessible name; the heading level (`h2` here) is chosen for document
  semantics, not visual size — the visual look comes from
  `mc-drawer__title` (`MINTCART_IMPLEMENTATION_GUIDE.md` §19).

## Placement

Placement is a generic Bootstrap offcanvas variant, not a MintCart concern —
add it directly to the root element next to `.offcanvas`
(`MINTCART_IMPLEMENTATION_GUIDE.md` §9):

```html
<div class="offcanvas offcanvas-start mc-drawer">…</div>  <!-- left edge, e.g. mobile navigation -->
<div class="offcanvas offcanvas-end mc-drawer">…</div>    <!-- right edge, e.g. cart drawer -->
<div class="offcanvas offcanvas-top mc-drawer">…</div>
<div class="offcanvas offcanvas-bottom mc-drawer">…</div>
```

## Behavior

`initDrawerTriggers()` is called once from both `catalog.js` and `admin.js`
and delegates on `document`, so drawers inserted later work without a
per-instance init call — same pattern as `mc-modal`.

- Opening/closing goes through Bootstrap's `Offcanvas.getOrCreateInstance()`
  / `Offcanvas.getInstance()`, never Bootstrap's own `data-bs-toggle`/
  `data-bs-dismiss` attributes — those are intentionally not part of this
  component's markup, so `data-mc-*` stays the only public trigger surface.
- On open/close, Bootstrap's own `shown.bs.offcanvas`/`hidden.bs.offcanvas`
  events are relayed as bubbling `mc:drawer:shown` / `mc:drawer:hidden`
  `CustomEvent`s on the `.mc-drawer` element, so listeners only need the
  `mc:*` contract (`MINTCART_TECHNICAL_PLAN.md` §25), not Bootstrap's event
  namespace.
- Everything else (focus trap, `Escape` to close, backdrop click to close,
  background scroll lock, returning focus to the trigger on close) is
  Bootstrap's `Offcanvas` behavior, unmodified.

## Styling notes

`.mc-drawer` retints Bootstrap's offcanvas purely through its own
documented `--bs-offcanvas-*` custom properties, reading from MintCart's
`--mc-*` tokens — no Bootstrap source recompilation
(`MINTCART_IMPLEMENTATION_GUIDE.md` §11).

Unlike `.modal-backdrop`, Bootstrap's `.offcanvas-backdrop` does not expose
its z-index as a custom property, so it can't be retinted the same way
`mc-modal.css` retints `--bs-backdrop-zindex`. `drawer.css` overrides
`.offcanvas-backdrop`'s z-index directly (`calc(var(--mc-z-drawer) - 1)`),
one below `--mc-z-drawer` (the drawer panel's own z-index), so the panel and
its backdrop never sit at an identical stacking value.

## Accessibility

- Focus management, `Escape`-to-close and returning focus to the trigger on
  close are handled by Bootstrap's `Offcanvas` (Functional Scope §27:
  "dialogs manage focus").
- The close button is a real `<button>` with `aria-label="Close"` (its
  visible content is a glyph, not descriptive text) and is keyboard
  reachable/activatable like `mc-modal__close`.
- `aria-labelledby` must point at the title's `id` so assistive tech
  announces what the drawer is for when it opens.
- `&times;` above is a placeholder for a real icon, same as `mc-modal`'s
  close button — swap for `{{ mc_icon('close') }}` once the Bootstrap Icons
  SVG renderer lands (`MINTCART_IMPLEMENTATION_GUIDE.md` §21, not yet built
  — no consumer before then) and keep the `aria-label`.

## Definition of Done checklist (`MINTCART_IMPLEMENTATION_GUIDE.md` §79)

- [x] Documented contract (this file)
- [x] `mc-*` BEM markup for MintCart-owned parts (`mc-drawer`,
      `mc-drawer__title`, `mc-drawer__close`); Bootstrap's own offcanvas
      structure classes are kept as internal implementation, not styled or
      relied on as the public contract
- [x] JS reads `data-mc-action`/`data-mc-target`, never a CSS class or
      Bootstrap's `data-bs-*` attributes, as the trigger contract
- [x] Colors/spacing/border/shadow/motion read from `--mc-*` tokens, applied
      via Bootstrap's own `--bs-offcanvas-*` custom properties (no Bootstrap
      source recompilation)
- [x] Keyboard usable (focus trap, `Escape` to close, focus returns to
      trigger on close — all via Bootstrap's `Offcanvas`)
- [x] Mobile checked (Bootstrap's offcanvas layout is responsive by
      default; no fixed desktop-only sizing added beyond the documented
      `--bs-offcanvas-width` retint)
- [x] No Bootstrap 3 dependency
- [x] No OCMOD required
- [ ] Theme override checked — deferred until the Theme resolver exists
      (Phase 8); there is no theme to override yet
