# `mc-progress`

MintCart's progress bar primitive. Use it wherever a long-running operation
needs to show how far along it is — a chunked bulk-edit batch job
(`MINTCART_FUNCTIONAL_SCOPE.md` §7/§29, "chunked; resumable; progress;
failed batch reporting"), an Update Manager stage
(`MINTCART_TECHNICAL_PLAN.md` §33: check → backup → download → verify →
migrations → ...), or a Setup Wizard step indicator
(`MINTCART_TECHNICAL_PLAN.md` §21). `MINTCART_TECHNICAL_PLAN.md` §18 /
`MINTCART_IMPLEMENTATION_GUIDE.md` §52 list `McProgress` among the Mint
Admin primitives.

CSS: `assets/src/css/components/progress.css`
JS: `assets/src/js/components/progress.js` (`initProgressBars`, `setProgress`)

## Why it doesn't wrap a Bootstrap JS class

`mc-modal`/`mc-drawer`/`mc-toast` wrap Bootstrap 5's `Modal`/`Offcanvas`/
`Toast` JS classes (`MINTCART_IMPLEMENTATION_GUIDE.md` §9). Bootstrap's
progress bar has no JS class to wrap — `.progress`/`.progress-bar` are
CSS-only. `mc-progress` still follows the same "no Bootstrap source
recompilation" rule as those components: it retints Bootstrap's own
`--bs-progress-*` custom properties from MintCart's `--mc-*` tokens
(`MINTCART_IMPLEMENTATION_GUIDE.md` §11), and keeps `.progress`/
`.progress-bar` as required internal implementation classes (Bootstrap's CSS
targets them directly) while `.mc-progress`/`.mc-progress__bar` are the
public contract.

What `mc-progress` *does* own is a small JS API for the one thing static
markup can't express on its own: the same bar's value changing over time as
an operation runs.

## Basic usage

Bootstrap 5 places `role="progressbar"` and the `aria-value*` triad on the
*outer* `.progress` element, not the inner `.progress-bar` — `mc-progress`
follows that placement exactly, so `.mc-progress` and `.progress` are the
same node:

```html
<div
    class="progress mc-progress"
    role="progressbar"
    aria-label="Importing products"
    aria-valuenow="42"
    aria-valuemin="0"
    aria-valuemax="100"
>
    <div class="progress-bar mc-progress__bar" style="width: 42%"></div>
</div>
```

- `aria-valuenow`/`aria-valuemin`/`aria-valuemax` are the **single source of
  truth** for the bar's fill. The inline `style="width: ..."` on
  `.mc-progress__bar` is a no-JS/pre-hydration fallback only — always set it
  to the same percentage as `aria-valuenow` so the bar is visually correct
  even before `initProgressBars()` runs (progressive enhancement, same
  reasoning `mc-quantity` uses for its native `min`/`max`/`step` attributes).
  Once JS runs, `initProgressBars()` re-derives the width from the
  `aria-value*` attributes, so a Twig template only ever has to compute one
  correct percentage, not keep two numbers in sync by hand.
- `aria-label` (or `aria-labelledby`, if a visible label element already
  exists elsewhere) is required — Bootstrap's progress bar carries no
  visible text of its own, so without one a screen reader announces a bare
  percentage with no context.
- Percentage text ("42%") is not part of this component's contract. Render
  it as ordinary markup next to `.mc-progress` if the design calls for it
  (e.g. a `<span>` a caller updates alongside `setProgress()`) — adding a
  dedicated `.mc-progress__value` element would duplicate what `aria-label`/
  `aria-valuenow` already convey to assistive tech, per CLAUDE.md §17 (no
  abstraction without a concrete need).

## Updating progress from JS

For a long-running operation, call the exported `setProgress()` after each
chunk/stage completes:

```javascript
import { setProgress } from './components/progress.js';

const bar = document.querySelector('#import-progress');

setProgress(bar, 42);
setProgress(bar, 68, { label: 'Importing products (68 of 100)' });
setProgress(bar, 100); // triggers mc:progress:complete, see Events below
```

`setProgress(progress, value, options)`:

- `progress` — the `.mc-progress` element (same node as `.progress`).
- `value` — the new `aria-valuenow`, clamped to the bar's own
  `aria-valuemin`/`aria-valuemax` (defaulting to `0`/`100` if either is
  absent).
- `options.label` (optional) — if given, also updates `aria-label`, so a
  batch job can report both progress and what's currently happening
  ("Importing products (68 of 100)") in one call instead of managing the
  attribute separately.

## Indeterminate progress

For an operation with no known duration (e.g. an Update Manager "Verifying
backup" stage), omit `aria-valuenow` entirely and add the `--indeterminate`
modifier:

```html
<div class="progress mc-progress mc-progress--indeterminate" role="progressbar" aria-label="Verifying backup">
    <div class="progress-bar mc-progress__bar"></div>
</div>
```

Per WAI-ARIA, a `progressbar` with no `aria-valuenow` is correctly announced
as "busy"/indeterminate by assistive tech — do not set `aria-valuenow` to a
placeholder value. `initProgressBars()` skips any `.mc-progress` without an
`aria-valuenow` attribute, so an indeterminate bar's width is driven purely
by the `--indeterminate` CSS animation, never by JS.

## Variants

```html
<div class="progress mc-progress mc-progress--success" role="progressbar" aria-label="…" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100">…</div>
<div class="progress mc-progress mc-progress--danger" role="progressbar" aria-label="…" aria-valuenow="30" aria-valuemin="0" aria-valuemax="100">…</div>
<div class="progress mc-progress mc-progress--warning" role="progressbar" aria-label="…" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100">…</div>
<div class="progress mc-progress mc-progress--info" role="progressbar" aria-label="…" aria-valuenow="10" aria-valuemin="0" aria-valuemax="100">…</div>
```

Variants recolor `.mc-progress__bar` only — pick one to signal outcome (e.g.
`--danger` for a batch job reporting failed rows,
`MINTCART_FUNCTIONAL_SCOPE.md` §29's "failed batch reporting"), independent
of whatever value the bar currently shows.

## Behavior

`initProgressBars()` is called once from `admin.js` on load (Phase 11 lists
`McProgress` as a Mint Admin primitive — Bulk Editor and Update Manager, its
first expected consumers, are both admin-only screens; `catalog.js` does not
import it) and derives every already-rendered `.mc-progress[aria-valuenow]`
element's bar width from its `aria-value*` attributes. It does not dispatch
any events — this is a one-time sync of pre-rendered markup, the same
"apply state, don't announce it" approach `mc-quantity`'s initial button
sync uses.

`setProgress()` is the imperative path a batch-job/Update Manager/wizard
flow calls to report new progress:

- Recomputes and applies the bar's width and `aria-valuenow` the same way
  `initProgressBars()` does.
- Dispatches a bubbling `mc:progress:change` `CustomEvent`
  (`detail: { value, min, max }`) on every call, so a listener anywhere in
  the DOM can react without needing a reference to the specific bar element
  (`MINTCART_TECHNICAL_PLAN.md` §25's `mc:*` event-contract pattern, used
  for `mc:cart:updated`/`mc:quantity:change`/`mc:toast:shown` today).
- The first time a call reaches `value >= max`, also adds the `is-complete`
  state class (`CLAUDE.md` §13 — transient state, not a BEM modifier) and
  dispatches a bubbling `mc:progress:complete` event with no `detail`. This
  only fires once per completion — calling `setProgress()` again with the
  bar already at its max does not re-dispatch it.

## Styling notes

`.mc-progress` retints Bootstrap's progress bar purely through its own
documented `--bs-progress-*` custom properties (height, background,
radius, bar color/background, transition), reading from `--mc-*` tokens —
no Bootstrap source recompilation.

`--bs-progress-bar-transition`'s width animation, and the `--indeterminate`
modifier's sliding animation, are both disabled under
`prefers-reduced-motion: reduce` (`MINTCART_FUNCTIONAL_SCOPE.md` §27) — the
indeterminate bar falls back to a static half-filled bar rather than
animating.

## Accessibility

- `role="progressbar"` plus `aria-valuenow`/`aria-valuemin`/`aria-valuemax`
  are Bootstrap's own documented placement, on the outer `.mc-progress`
  element — keep them there, not on `.mc-progress__bar`.
- `aria-label` (or `aria-labelledby`) is required on every instance —
  neither Bootstrap nor this component renders any visible text by default.
- Indeterminate progress must omit `aria-valuenow` rather than set it to `0`
  or any other placeholder — see "Indeterminate progress" above.
- The width-change and indeterminate-slide animations respect
  `prefers-reduced-motion` — see "Styling notes" above.

## Definition of Done checklist (`MINTCART_IMPLEMENTATION_GUIDE.md` §79)

- [x] Documented contract (this file)
- [x] `mc-*` BEM markup for MintCart-owned parts (`mc-progress`,
      `mc-progress__bar`); Bootstrap's own `.progress`/`.progress-bar`
      classes are kept as internal implementation, not the public contract
- [x] JS (`setProgress`) is the only mutation path for value updates —
      callers never hand-write `aria-valuenow`/width after initial render
- [x] Colors/spacing/radius read from `--mc-*` tokens, applied via
      Bootstrap's own `--bs-progress-*` custom properties (no Bootstrap
      source recompilation)
- [x] Keyboard usable — a progress bar is a status display, not an
      interactive control, so there is nothing to focus or operate by
      keyboard (WAI-ARIA `progressbar` role is read-only)
- [x] Mobile checked (Bootstrap's progress bar layout is responsive by
      default; no fixed desktop-only sizing added)
- [x] Reduced motion respected (see Styling notes)
- [x] No Bootstrap 3 dependency
- [x] No OCMOD required
- [ ] Theme override checked — no live consumer/page renders `.mc-progress`
      yet (Bulk Editor and Update Manager, its likely first callers, are not
      built — `CLAUDE.md` §85), so there is nothing to check theme override
      behavior against yet.
