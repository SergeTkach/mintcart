# mc-alert

CSS: `assets/src/css/components/alert.css`. Optional dismiss behavior:
`assets/src/js/components/alert.js` (bundled into both `catalog.js` and
`admin.js`).

There is no Twig rendering pipeline yet (that starts with Phase 6 Product
Card / Phase 8 Theme resolver), so this component is plain markup for now.

## Markup contract

```html
<div class="mc-alert" role="status">
    <div class="mc-alert__body">Your changes were saved.</div>
</div>
```

Variants (default is success - no modifier class needed):

```html
<div class="mc-alert mc-alert--danger" role="alert">
    <div class="mc-alert__body">Could not save changes.</div>
</div>

<div class="mc-alert mc-alert--warning" role="status">
    <div class="mc-alert__body">This product is low on stock.</div>
</div>

<div class="mc-alert mc-alert--info" role="status">
    <div class="mc-alert__body">Delivery estimates may vary by region.</div>
</div>
```

## Dismissible alert

Add a close button with the `dismiss-alert` behavior hook. The bundled JS
module listens for it on `document` (delegated, so alerts inserted later via
`fetch` are dismissible without extra wiring) and removes the closest
`.mc-alert`:

```html
<div class="mc-alert mc-alert--danger" role="alert">
    <div class="mc-alert__body">Could not save changes.</div>
    <button
        type="button"
        class="mc-alert__close"
        data-mc-action="dismiss-alert"
        aria-label="Dismiss"
    >
        &times;
    </button>
</div>
```

The close button is found by the `data-mc-action` hook, never by the
`.mc-alert__close` class (`docs/MINTCART_IMPLEMENTATION_GUIDE.md` §14) - the
class is presentation only.

## Accessibility

- Use `role="alert"` for errors/interruptions that should be announced
  immediately (e.g. `mc-alert--danger` on a failed form submit), `role="status"`
  for non-urgent updates.
- The close button needs an `aria-label` (or visible text) since `&times;`
  alone is not announced meaningfully.
- Text color stays `--mc-text` (not a tinted color) against the tinted
  background so contrast holds regardless of variant.

## Tokens consumed

`--mc-success`, `--mc-danger`, `--mc-warning`, `--mc-info`, `--mc-surface`,
`--mc-text`, spacing/radius/font/motion tokens. Background/border tints are
computed with `color-mix()` from the variant's accent token - no new
hardcoded or primitive tint colors were added for this single consumer.
