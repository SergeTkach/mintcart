# `mc-alert`

MintCart's inline notice/message component. Use for form validation summaries,
save confirmations, and similar status messages.

CSS: `assets/src/css/components/alert.css`
JS (optional dismiss behavior): `assets/src/js/components/alert.js`

## Basic usage

```html
<div class="mc-alert mc-alert--info" role="status">
    <div class="mc-alert__body">
        <p class="mc-alert__message">Your changes have been saved.</p>
    </div>
</div>
```

Use `role="status"` for non-urgent updates and `role="alert"` for errors that
need immediate attention — this determines whether screen readers announce it
right away or on the next pause, independent of the visual `--danger`/`--info`
variant.

## Variants

```html
<div class="mc-alert mc-alert--success" role="status">…</div>
<div class="mc-alert mc-alert--danger" role="alert">…</div>
<div class="mc-alert mc-alert--warning" role="alert">…</div>
<div class="mc-alert mc-alert--info" role="status">…</div>
```

## With a title

```html
<div class="mc-alert mc-alert--danger" role="alert">
    <div class="mc-alert__body">
        <strong class="mc-alert__title">Order could not be placed.</strong>
        <p class="mc-alert__message">Check the highlighted fields and try again.</p>
    </div>
</div>
```

## Dismissible

The dismiss button is a behavior hook (`data-mc-action="dismiss-alert"`), not
a CSS-class-driven one (`CLAUDE.md` §14). `initAlertDismiss()` is already
called from both `assets/src/js/catalog.js` and `assets/src/js/admin.js`, so
no extra JS wiring is needed at the call site — just include the button:

```html
<div class="mc-alert mc-alert--warning" role="alert">
    <div class="mc-alert__body">
        <p class="mc-alert__message">Your session will expire soon.</p>
    </div>
    <button type="button" class="mc-alert__dismiss" data-mc-action="dismiss-alert" aria-label="Dismiss">
        &times;
    </button>
</div>
```

Dismissing removes the alert from the DOM and dispatches a bubbling
`mc:alert:dismiss` `CustomEvent` on it first, so a listener higher up the tree
can react (e.g. clear related form-error state) before the node is gone.

`aria-label="Dismiss"` above is a placeholder for a real icon; once the
Bootstrap Icons SVG renderer lands (`MINTCART_IMPLEMENTATION_GUIDE.md` §21,
not yet built — no consumer before this), swap the `&times;` glyph for
`{{ mc_icon('close') }}` and keep the `aria-label`.

## Accessibility

- `role="status"` / `role="alert"` on the container drives screen-reader
  announcement timing — pick per message, not per visual variant.
- The dismiss control is a real `<button>` with a visible/`aria-label`
  accessible name, keyboard-focusable, and uses `:focus-visible` like
  `mc-button`.
- Color is never the only signal: pair the variant with the message text
  itself (and `role="alert"` for anything the user must act on).

## Definition of Done checklist (`MINTCART_IMPLEMENTATION_GUIDE.md` §79)

- [x] Documented contract (this file)
- [x] `mc-*` BEM markup
- [x] JS reads `data-mc-action`, never `.mc-alert__dismiss` as a hook
- [x] Colors/spacing/fonts read from `--mc-*` tokens (variant tints via
      `color-mix()` on existing primitives — see `alert.css` header comment)
- [x] Keyboard usable (dismiss button)
- [x] Mobile checked (flex layout, no fixed widths)
- [x] No Bootstrap 3 dependency
- [x] No OCMOD required
- [ ] Theme override checked — deferred until the Theme resolver exists
      (Phase 8); there is no theme to override yet
