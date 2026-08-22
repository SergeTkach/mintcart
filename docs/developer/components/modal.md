# `mc-modal`

MintCart's dialog component. Use for confirmations, short focused forms, and
quick-view style content that needs to interrupt the page — e.g. a future
Quick Order or product quick-view (`docs/MINTCART_FUNCTIONAL_SCOPE.md`
§9/§13).

CSS: `assets/src/css/components/modal.css`
JS: `assets/src/js/components/modal.js` (`initModalTriggers`)

## Why it wraps Bootstrap's `Modal`

`mc-modal` reuses Bootstrap 5's `Modal` JS for the hard accessibility work —
focus trap, `Escape`-to-close, backdrop click-to-close, background scroll
lock, restoring focus to the trigger on close — instead of reimplementing a
dialog from scratch. `MINTCART_IMPLEMENTATION_GUIDE.md` §9 explicitly allows
this for generic UI primitives ("modal ... where appropriate", "Not needed
to rewrite Bootstrap Modal own without a product reason").

Bootstrap's own `.modal`/`.modal-dialog`/`.modal-content`/`.modal-header`/
`.modal-body`/`.modal-footer`/`.modal-title` classes stay on the markup
because `Modal`'s JS selects some of them directly and its CSS (fixed
positioning, backdrop, fade transition, size variants) lives on those
classes. They are internal implementation, the same way `.container`/
`.row`/`.col-*` are layout plumbing around an `mc-*` component
(`MINTCART_IMPLEMENTATION_GUIDE.md` §6) — **`.mc-modal`/`.mc-modal__*` and
`data-mc-*` are the public contract**, not the Bootstrap classes.

## Basic usage

```html
<button
    type="button"
    class="mc-button mc-button--primary"
    data-mc-action="open-modal"
    data-mc-target="#exampleModal"
>Open dialog</button>

<div class="modal mc-modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalTitle" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title mc-modal__title" id="exampleModalTitle">Confirm</h2>
                <button type="button" class="mc-modal__close" data-mc-action="close-modal" aria-label="Close">
                    &times;
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to remove this item?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="mc-button mc-button--ghost" data-mc-action="close-modal">Cancel</button>
                <button type="button" class="mc-button mc-button--danger">Remove</button>
            </div>
        </div>
    </div>
</div>
```

- `data-mc-action="open-modal"` + `data-mc-target="<selector>"` on the
  trigger opens the matching `.mc-modal`. Any element can be a trigger — it
  does not have to be an `mc-button`.
- `data-mc-action="close-modal"` inside the modal closes its nearest
  `.mc-modal` ancestor. Add it to the header's close button, a footer
  "Cancel" button, or both.
- `tabindex="-1"` and `aria-hidden="true"` on the root are required by
  Bootstrap's `Modal` (it manages `aria-hidden`/`aria-modal` itself once
  shown) — keep them in the markup as written above.
- `aria-labelledby` on the root pointing at the title's `id` gives the
  dialog its accessible name; the heading level (`h2` here) is chosen for
  document semantics, not visual size — the visual look comes from
  `mc-modal__title` (`MINTCART_IMPLEMENTATION_GUIDE.md` §19).

## Sizes and other Bootstrap modal variants

`mc-modal` does not duplicate Bootstrap's size/layout variants — add them
directly to `.modal-dialog` as generic Bootstrap primitives
(`MINTCART_IMPLEMENTATION_GUIDE.md` §9):

```html
<div class="modal-dialog modal-sm">…</div>
<div class="modal-dialog modal-lg">…</div>
<div class="modal-dialog modal-xl">…</div>
<div class="modal-dialog modal-dialog-centered">…</div>
<div class="modal-dialog modal-dialog-scrollable">…</div>
```

## Behavior

`initModalTriggers()` is called once from both `catalog.js` and `admin.js`
and delegates on `document`, so modals inserted later (e.g. fetched into a
drawer) work without a per-instance init call — same pattern as `mc-alert`'s
dismiss behavior.

- Opening/closing goes through Bootstrap's `Modal.getOrCreateInstance()` /
  `Modal.getInstance()`, never Bootstrap's own `data-bs-toggle`/
  `data-bs-dismiss` attributes — those are intentionally not part of this
  component's markup, so `data-mc-*` stays the only public trigger surface.
- On open/close, Bootstrap's own `shown.bs.modal`/`hidden.bs.modal` events
  are relayed as bubbling `mc:modal:shown` / `mc:modal:hidden`
  `CustomEvent`s on the `.mc-modal` element, so listeners only need the
  `mc:*` contract (`MINTCART_TECHNICAL_PLAN.md` §25), not Bootstrap's event
  namespace.
- Everything else (focus trap, `Escape` to close, backdrop click to close,
  background scroll lock, returning focus to the trigger on close) is
  Bootstrap's `Modal` behavior, unmodified.

## Accessibility

- Focus management, `Escape`-to-close and returning focus to the trigger on
  close are handled by Bootstrap's `Modal` (Functional Scope §27: "dialogs
  manage focus").
- The close button is a real `<button>` with `aria-label="Close"` (its
  visible content is a glyph, not descriptive text) and is keyboard
  reachable/activatable like `mc-alert__dismiss`.
- `aria-labelledby` must point at the title's `id` so assistive tech
  announces what the dialog is for when it opens.
- `&times;` above is a placeholder for a real icon, same as `mc-alert`'s
  dismiss button — swap for `{{ mc_icon('close') }}` once the Bootstrap
  Icons SVG renderer lands (`MINTCART_IMPLEMENTATION_GUIDE.md` §21, not yet
  built — no consumer before then) and keep the `aria-label`.

## Definition of Done checklist (`MINTCART_IMPLEMENTATION_GUIDE.md` §79)

- [x] Documented contract (this file)
- [x] `mc-*` BEM markup for MintCart-owned parts (`mc-modal`,
      `mc-modal__title`, `mc-modal__close`); Bootstrap's own modal
      structure classes are kept as internal implementation, not styled or
      relied on as the public contract
- [x] JS reads `data-mc-action`/`data-mc-target`, never a CSS class or
      Bootstrap's `data-bs-*` attributes, as the trigger contract
- [x] Colors/spacing/radius/shadow read from `--mc-*` tokens, applied via
      Bootstrap's own `--bs-modal-*`/`--bs-backdrop-*` custom properties
      (no Bootstrap source recompilation)
- [x] Keyboard usable (focus trap, `Escape` to close, focus returns to
      trigger on close — all via Bootstrap's `Modal`)
- [x] Mobile checked (Bootstrap's modal layout is responsive by default;
      no fixed desktop-only sizing added)
- [x] No Bootstrap 3 dependency
- [x] No OCMOD required
- [ ] Theme override checked — deferred until the Theme resolver exists
      (Phase 8); there is no theme to override yet
