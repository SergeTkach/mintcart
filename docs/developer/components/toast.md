# `mc-toast`

MintCart's transient notification component. Use for short-lived feedback
after an action — "Added to cart", "Changes saved", "Item removed" — the
kind of message that should not interrupt what the user is doing the way
`mc-modal` does. `MINTCART_TECHNICAL_PLAN.md` §15 lists a toast + header
cart update as one of the supported Cart UX patterns after Add to Cart.

CSS: `assets/src/css/components/toast.css`
JS: `assets/src/js/components/toast.js` (`initToastTriggers`, `showToast`)

## Why it wraps Bootstrap's `Toast`

`mc-toast` reuses Bootstrap 5's `Toast` JS for showing/hiding and the
autohide timer (which pauses while the toast has mouse or keyboard focus)
instead of reimplementing that from scratch — the same allowance
`mc-modal`/`mc-drawer` already use for `Modal`/`Offcanvas`
(`MINTCART_IMPLEMENTATION_GUIDE.md` §9).

Unlike `Modal`/`Offcanvas`, Bootstrap's `Toast` **does not manage focus** —
it never traps focus or moves it on show. That is the correct behavior for
a toast: a transient notification must not steal keyboard focus away from
whatever the user is doing (`MINTCART_FUNCTIONAL_SCOPE.md` §27). Screen
reader announcement instead comes from `role`/`aria-live`/`aria-atomic` on
the markup itself, which you must include — Bootstrap's JS does not add
these for you (see Accessibility below).

Bootstrap's own `.toast`/`.toast-container`/`.toast-header`/`.toast-body`
classes stay on the markup because `Toast`'s JS/CSS (show/hide state,
autohide timer, fade transition) lives on those classes — they are internal
implementation, the same way `.modal`/`.modal-dialog` are for `mc-modal`.
**`.mc-toast`/`.mc-toast__*` and `data-mc-*` are the public contract**, not
the Bootstrap classes.

## Basic usage

```html
<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div
        id="cartToast"
        class="toast mc-toast mc-toast--success"
        role="status"
        aria-live="polite"
        aria-atomic="true"
    >
        <div class="toast-header">
            <strong class="mc-toast__title">Added to cart</strong>
            <button type="button" class="mc-toast__close" data-mc-action="close-toast" aria-label="Close">
                &times;
            </button>
        </div>
        <div class="toast-body">
            <p class="mc-toast__message">Mint T-Shirt (x1) was added to your cart.</p>
        </div>
    </div>
</div>
```

- `.toast-container` plus Bootstrap's own `position-fixed`/`bottom-0`/
  `end-0`/`p-3` utilities position the stack in a screen corner — this is
  generic Bootstrap layout, not an `mc-*` concern
  (`MINTCART_IMPLEMENTATION_GUIDE.md` §6/§9).
- `data-mc-action="close-toast"` on the header's close button hides its
  nearest `.mc-toast` ancestor.
- There is no `data-mc-action="open-toast"` in this example because a toast
  is normally shown at the moment of an event (see "Showing a toast"
  below), not from a static always-present trigger button. A declarative
  trigger is still supported for cases where a fixed control should reveal
  an already-rendered toast — see "Declarative trigger" below.

## Showing a toast

Most real callers create or already have a toast element in the DOM and
want to show it at a specific moment (e.g. right after a successful "add to
cart" request) — call the exported `showToast()`:

```javascript
import { showToast } from './components/toast.js';

showToast(document.querySelector('#cartToast'));
```

This is the integration point Phase 15's Cart client uses: after
dispatching `mc:cart:updated`, call `showToast()` on the corresponding
toast element instead of duplicating `Toast.getOrCreateInstance(...).show()`
call sites.

### Building a toast on the fly

Most real callers don't have a pre-rendered toast element to reveal — the
message is only known at the moment of the event (e.g. "Added to cart"
after an AJAX add). `createToast()` builds one, matching the exact markup
shape shown above (`.toast-header`/`.toast-body`,
`mc-toast__title`/`__message`/`__close`, `role`/`aria-live` picked per
variant the same way the static examples do), appends it to a lazily
created `.toast-container`, and removes it from the DOM once it finishes
hiding:

```javascript
import { createToast, showToast } from './components/toast.js';

showToast(createToast({
    title: 'Added to cart',
    message: 'Added to cart',
    variant: 'success', // 'success' | 'danger' | 'warning' | 'info', default 'info'
}));
```

`mc-product-card`'s Add to Cart button (`assets/src/js/components/product-card.js`)
is the first real caller — see `docs/developer/components/product-card.md`
and `docs/developer/commerce/cart-client.md`.

### Declarative trigger

For a static trigger already in the markup (e.g. a demo/test button), use
the same `data-mc-action`/`data-mc-target` pattern as `mc-modal`/`mc-drawer`:

```html
<button
    type="button"
    class="mc-button mc-button--secondary"
    data-mc-action="open-toast"
    data-mc-target="#cartToast"
>Show notification</button>
```

## Variants

```html
<div class="toast mc-toast mc-toast--success" role="status" aria-live="polite" aria-atomic="true">…</div>
<div class="toast mc-toast mc-toast--danger" role="alert" aria-live="assertive" aria-atomic="true">…</div>
<div class="toast mc-toast mc-toast--warning" role="alert" aria-live="assertive" aria-atomic="true">…</div>
<div class="toast mc-toast mc-toast--info" role="status" aria-live="polite" aria-atomic="true">…</div>
```

Variants recolor `.toast-header` only (the same header/body split
Bootstrap's own toast markup uses) — pick `role`/`aria-live` per how urgent
the message actually is, independent of the visual variant, same as
`mc-alert`.

## Autohide and delay

`data-bs-autohide` and `data-bs-delay` are Bootstrap's own `Toast`
configuration attributes, read directly by its constructor — keep using
them as-is rather than reinventing an `mc-*` equivalent
(`MINTCART_IMPLEMENTATION_GUIDE.md` §9, "Sizes and other Bootstrap ...
variants" applies to configuration attributes the same way it applies to
size classes):

```html
<div class="toast mc-toast" data-bs-autohide="false">…</div>
<div class="toast mc-toast" data-bs-delay="8000">…</div>
```

Autohide pauses automatically while the toast has mouse-over or keyboard
focus (Bootstrap's own behavior) and resumes once the pointer/focus leaves.

## Behavior

`initToastTriggers()` is called once from both `catalog.js` and `admin.js`
and delegates `close-toast` clicks on `document`, so toasts inserted later
work without a per-instance init call — same pattern as `mc-modal`/
`mc-drawer`.

- Showing goes through `showToast()` (which calls Bootstrap's
  `Toast.getOrCreateInstance(el).show()`), whether triggered via
  `data-mc-action="open-toast"` or called directly from other JS.
  Dismissing goes through Bootstrap's `Toast.getInstance(el)?.hide()`, never
  Bootstrap's own `data-bs-dismiss` attribute — `data-mc-*` stays the only
  public trigger surface, same rule as `mc-modal`/`mc-drawer`.
- On show/hide, Bootstrap's own `shown.bs.toast`/`hidden.bs.toast` events
  are relayed as bubbling `mc:toast:shown`/`mc:toast:hidden`
  `CustomEvent`s on the `.mc-toast` element, so listeners only need the
  `mc:*` contract (`MINTCART_TECHNICAL_PLAN.md` §25), not Bootstrap's event
  namespace.
- Bootstrap's `Toast` does not trap or move focus on show, and does not
  restore focus anywhere on hide — there is nothing to restore, since focus
  never left the page's active element.

## Styling notes

`.mc-toast` retints Bootstrap's toast purely through its own documented
`--bs-toast-*` custom properties, reading from MintCart's `--mc-*` tokens —
no Bootstrap source recompilation (`MINTCART_IMPLEMENTATION_GUIDE.md` §11).

`.toast-container` is a plain wrapping `<div>` your markup provides
(Bootstrap does not append it dynamically the way it appends
`.modal-backdrop`/`.offcanvas-backdrop`), so it can never be a descendant of
`.mc-toast`. `toast.css` retints its `--bs-toast-zindex` copy directly on
the `.toast-container` class itself so a page with several stacked toasts
shares MintCart's `--mc-z-toast` tier instead of Bootstrap's unrelated
default (1090) — the same reasoning `mc-drawer.css` used for
`.offcanvas-backdrop`, applied to a different kind of "sits outside the
component root" element.

## Accessibility

- `role="status"` + `aria-live="polite"` for non-urgent feedback (e.g.
  "Added to cart"), `role="alert"` + `aria-live="assertive"` for anything
  the user needs to know about immediately (e.g. "Could not add to cart") —
  the same per-message judgment call `mc-alert` documents, not tied to the
  visual variant. `aria-atomic="true"` ensures assistive tech reads the
  whole message on each update, not just the changed part.
- These three attributes are required on every `.mc-toast` instance —
  unlike `mc-modal`/`mc-drawer`, Bootstrap's `Toast` JS does not add
  `role`/`aria-*` for you, so leaving them off means screen reader users
  never learn the toast appeared.
- Bootstrap's `Toast` deliberately does not trap or move focus (Functional
  Scope §27's "dialogs manage focus" does not apply here — a toast is not a
  dialog). Sighted keyboard users can still `Tab` to the close button while
  the toast is visible if they choose to.
- The close button is a real `<button>` with `aria-label="Close"` (its
  visible content is a glyph, not descriptive text), keyboard reachable and
  activatable like `mc-modal__close`/`mc-drawer__close`.
- `&times;` above is a placeholder for a real icon, same as
  `mc-modal`/`mc-drawer` — swap for `{{ mc_icon('close') }}` once the
  Bootstrap Icons SVG renderer lands (`MINTCART_IMPLEMENTATION_GUIDE.md`
  §21, not yet built — no consumer before then) and keep the `aria-label`.

## Definition of Done checklist (`MINTCART_IMPLEMENTATION_GUIDE.md` §79)

- [x] Documented contract (this file)
- [x] `mc-*` BEM markup for MintCart-owned parts (`mc-toast`,
      `mc-toast__title`, `mc-toast__message`, `mc-toast__close`); Bootstrap's
      own toast structure classes are kept as internal implementation, not
      styled or relied on as the public contract
- [x] JS reads `data-mc-action`/`data-mc-target`, never a CSS class or
      Bootstrap's `data-bs-dismiss` attribute, as the trigger contract
      (`data-bs-autohide`/`data-bs-delay` remain generic Bootstrap
      configuration attributes, not a trigger surface)
- [x] Colors/spacing/radius/shadow read from `--mc-*` tokens, applied via
      Bootstrap's own `--bs-toast-*` custom properties (no Bootstrap source
      recompilation)
- [x] Keyboard usable (close button is a focusable, `:focus-visible`
      styled `<button>`); deliberately does not trap focus, per
      Accessibility above
- [x] Mobile checked (Bootstrap's toast layout is responsive by default; no
      fixed desktop-only sizing added)
- [x] No Bootstrap 3 dependency
- [x] No OCMOD required
- [ ] Theme override checked — deferred until the Theme resolver exists
      (Phase 8); there is no theme to override yet

## Action link (2026-10-07)

`createToast({ title, message, variant, action })` — optional
`action: { text, href }` appends one `a.mc-toast__action` under the message
(text set via `textContent`). Used by add-to-cart: title "Added to cart",
message = product name, action "View cart" → `checkout/cart`.
