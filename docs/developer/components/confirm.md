# `mc-confirm`

MintCart's confirmation dialog — the `window.confirm()` replacement for
destructive or critical actions ("Delete this product?", "Cancel this
order?"). `MINTCART_FUNCTIONAL_SCOPE.md` §5 lists "Toast / Confirm / Alert
primitives" together as P0; `mc-toast` and `mc-alert` shipped in Phase 4,
this is the remaining one (Phase 11 admin primitives).

CSS: `assets/src/css/components/confirm.css`
JS: `assets/src/js/components/confirm.js` (`mcConfirm`)

## Why it has no static markup

Every other `mc-*` dialog/panel component (`mc-modal`, `mc-drawer`,
`mc-toast`) is markup you write once in a page's Twig, with JS only
handling open/close. `mc-confirm` is different: a confirmation's title,
message, and button labels are always specific to the action being
confirmed, decided at the moment the action is attempted — there is nothing
generic to put in a template. So `mcConfirm()` builds one reusable
`.mc-confirm` dialog element on first call, appends it to `document.body`,
fills in the options you pass, shows it, and returns a `Promise<boolean>`.

## Why it wraps Bootstrap's `Modal`

Same allowance as `mc-modal`/`mc-drawer`/`mc-toast`
(`MINTCART_IMPLEMENTATION_GUIDE.md` §9): Bootstrap 5's `Modal` JS already
does focus trap, `Escape`-to-close, backdrop click-to-close and background
scroll lock correctly, so `mc-confirm` reuses it rather than reimplementing
a dialog. Bootstrap's own `.modal`/`.modal-dialog`/`.modal-content`/
`.modal-header`/`.modal-body`/`.modal-footer` classes stay on the
dynamically-built markup as internal implementation — **`.mc-confirm`/
`.mc-confirm__*` is the public contract**, not the Bootstrap classes or the
element's structure.

`role="alertdialog"` (rather than `Modal`'s implied `role="dialog"`) tells
assistive tech this dialog demands an immediate yes/no decision, matching
native `window.confirm()` semantics.

## Usage

```javascript
import { mcConfirm } from './components/confirm.js';

async function deleteProduct(productId) {
    const confirmed = await mcConfirm({
        title: 'Delete this product?',
        message: 'This cannot be undone.',
        confirmLabel: 'Delete',
        cancelLabel: 'Cancel',
        variant: 'danger',
    });

    if (!confirmed) {
        return;
    }

    // proceed with the destructive request
}
```

`mcConfirm(options)` options, all optional:

| Option         | Default          | Notes                                                          |
| -------------- | ---------------- | ---------------------------------------------------------------|
| `title`        | `'Are you sure?'`| Dialog heading (`.mc-confirm__title`)                          |
| `message`      | `''`             | Supporting text (`.mc-confirm__message`)                       |
| `confirmLabel` | `'Confirm'`      | Accept button text                                              |
| `cancelLabel`  | `'Cancel'`       | Cancel button text                                              |
| `variant`      | `'primary'`      | Any `mc-button` modifier (`primary`/`secondary`/`outline`/`ghost`/`danger`) applied to the accept button — use `'danger'` for destructive actions |

The returned Promise resolves `true` only when the accept button is
clicked. Every other way the dialog closes — cancel button, backdrop
click, `Escape` — resolves `false`, the same "assume no" default
`window.confirm()` gives on dismissal.

Only one `mcConfirm()` call can be pending at a time. Calling it again
while an earlier call's dialog is still open resolves the earlier call
`false` before showing the new one, since a user can only be looking at one
prompt.

## No declarative trigger

Unlike `mc-modal`/`mc-drawer`/`mc-toast`, there is no
`data-mc-action="open-confirm"` markup pattern — a confirm prompt's content
is decided by the calling code (which product, which order, which
message), not by a static attribute on a button. Call `mcConfirm()`
directly from the click handler (or form-submit handler) that would
otherwise perform the destructive action immediately.

## Not wired into `catalog.js`/`admin.js` yet

`mcConfirm` is exported for direct import, not auto-initialized from either
entry point — it has no boot-time listener to register (its dialog element
is built lazily on first call), and no MintCart screen calls it yet (Bulk
Editor's mass-delete confirmation, Phase 16, is the first likely consumer).
Per `CLAUDE.md` §85, the import is added to whichever entry point needs it
once that consumer exists, not ahead of time.

## Definition of Done checklist (`MINTCART_IMPLEMENTATION_GUIDE.md` §79)

- [x] Documented contract (this file)
- [x] `mc-*` BEM markup for MintCart-owned parts (`mc-confirm`,
      `mc-confirm__title`, `mc-confirm__message`, `mc-confirm__actions`);
      Bootstrap's own modal structure classes are kept as internal
      implementation, not styled or relied on as the public contract
- [x] JS reads `data-mc-action` for its two buttons, never a CSS class or
      Bootstrap's `data-bs-*` attributes, as the trigger contract
- [x] Colors/spacing/radius/shadow read from `--mc-*` tokens, applied via
      Bootstrap's own `--bs-modal-*` custom properties (no Bootstrap source
      recompilation); the accept button reuses `mc-button`'s existing
      variant classes rather than a new confirm-specific button style
- [x] Keyboard usable — `Modal`'s focus trap and `Escape`-to-close apply
      unchanged; `Escape` resolves the Promise `false`
- [x] Mobile checked (Bootstrap's modal layout is responsive by default; a
      `max-width` cap keeps the dialog readable on wide viewports without
      adding fixed desktop-only sizing)
- [x] No Bootstrap 3 dependency
- [x] No OCMOD required
- [ ] Theme override checked — deferred; this is an admin primitive with no
      admin screen/theme override mechanism yet (Phase 11/Phase 6 Admin UX
      not started)
