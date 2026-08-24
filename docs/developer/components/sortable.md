# `mc-sortable`

MintCart's drag-and-drop reordering list component. Use anywhere order is a
natural part of the admin task (`MINTCART_FUNCTIONAL_SCOPE.md` §6): Homepage
Blocks, Categories, Menu, Banner items, Benefits, FAQ, Gallery, Footer
columns.

CSS: `assets/src/css/components/sortable.css`
JS: `assets/src/js/components/sortable.js` (`initSortableLists`)

Admin-only: wired into `admin.js`, not `catalog.js` — every scenario listed
above is an admin screen.

## Why it wraps SortableJS

`mc-sortable` reuses SortableJS 1.15.7 (already an npm dependency since
Phase 2) for the pointer/touch drag mechanics — the same allowance
`MINTCART_IMPLEMENTATION_GUIDE.md` §9 gives `mc-modal`/`mc-drawer`/
`mc-toast` for wrapping Bootstrap's `Modal`/`Offcanvas`/`Toast` rather than
reimplementing dialog behavior from scratch.
`MINTCART_IMPLEMENTATION_GUIDE.md` §4 explicitly names SortableJS for this
job and explicitly forbids jQuery UI Sortable.

## Why there are also move-up/move-down buttons

`MINTCART_IMPLEMENTATION_GUIDE.md` §68 and `MINTCART_FUNCTIONAL_SCOPE.md`
§27 both require **"non-mouse alternative for important sortable
actions"** — pointer/touch drag is not sufficient on its own. Every
`mc-sortable__item` should render its own
`data-mc-action="sortable-move-up"` / `"sortable-move-down"` buttons so the
list is fully reorderable by keyboard/assistive tech, independent of
SortableJS's own drag simulation.

## Basic usage

```html
<ul class="mc-sortable" data-mc-sortable data-mc-sortable-handle>
    <li class="mc-sortable__item" data-mc-sortable-item="12">
        <button
            type="button"
            class="mc-sortable__handle"
            data-mc-action="sortable-handle"
            aria-label="Drag to reorder"
        >⠿</button>

        <span class="mc-sortable__label">Hero</span>

        <div class="mc-sortable__controls">
            <button type="button" class="mc-sortable__move" data-mc-action="sortable-move-up" aria-label="Move up">↑</button>
            <button type="button" class="mc-sortable__move" data-mc-action="sortable-move-down" aria-label="Move down">↓</button>
        </div>
    </li>

    <li class="mc-sortable__item" data-mc-sortable-item="7">
        <button
            type="button"
            class="mc-sortable__handle"
            data-mc-action="sortable-handle"
            aria-label="Drag to reorder"
        >⠿</button>

        <span class="mc-sortable__label">Products</span>

        <div class="mc-sortable__controls">
            <button type="button" class="mc-sortable__move" data-mc-action="sortable-move-up" aria-label="Move up">↑</button>
            <button type="button" class="mc-sortable__move" data-mc-action="sortable-move-down" aria-label="Move down">↓</button>
        </div>
    </li>
</ul>
```

- `data-mc-sortable` on the container marks it for `initSortableLists()` to
  pick up.
- `data-mc-sortable-handle` (boolean attribute, presence only) on the
  container restricts dragging to elements with
  `data-mc-action="sortable-handle"` inside each item. Omit it to make the
  whole item a drag surface instead.
- `data-mc-sortable-item="<id>"` on each direct child of the container is
  the id reported in the reorder event — typically the row's database
  primary key (e.g. `block_id`).
- `⠿`/`↑`/`↓` above are glyph placeholders, same convention as `mc-alert`'s
  `&times;` dismiss glyph — swap for `{{ mc_icon('drag-handle') }}` /
  `{{ mc_icon('chevron-up') }}` / `{{ mc_icon('chevron-down') }}` once the
  Bootstrap Icons SVG renderer lands (`MINTCART_IMPLEMENTATION_GUIDE.md`
  §21, not yet built), and keep the `aria-label`s.

## Behavior

`initSortableLists()` is called once from `admin.js` and:

1. Initializes a SortableJS instance on every `[data-mc-sortable]`
   container found under `root` (default `document`) at call time.
2. Delegates a `click` listener on `root` for
   `[data-mc-action="sortable-move-up"]` / `"sortable-move-down"` so items
   rendered after boot (e.g. fetched into a drawer) stay keyboard-usable
   without a per-instance init call — same delegation pattern
   `mc-alert`/`mc-modal`/`mc-toast` already use.

After **any** reorder — a completed drag (SortableJS `onEnd`) or a
move-up/move-down click — a bubbling `mc:sortable:reorder` `CustomEvent`
fires on the container:

```js
container.addEventListener('mc:sortable:reorder', (event) => {
    console.log(event.detail.order); // ['7', '12', ...] - new top-to-bottom order
});
```

`detail.order` is the list of `data-mc-sortable-item` values in the new
DOM order. A consumer (e.g. a future Homepage Builder admin screen
persisting block order, Phase 10, or Categories drag-and-drop, Phase 17)
needs only this one event to know when and how to save — it does not need
to distinguish a drag from a keyboard move.

Move-up/move-down buttons are re-disabled at the list boundaries
(first item's "move up", last item's "move down") after every reorder,
including on initial load.

## Accessibility

- Full keyboard path via `sortable-move-up`/`sortable-move-down` buttons —
  required by `MINTCART_IMPLEMENTATION_GUIDE.md` §68 ("non-mouse
  alternative for important sortable actions"), not optional. Focus stays
  on the clicked button after its item moves (`Node.insertBefore` repositions
  the existing element instead of removing/recreating it, so no
  re-focusing logic is needed).
- Handle/move buttons are real `<button>` elements with `aria-label`s
  describing the action (their visible content is a glyph, not descriptive
  text), matching `mc-alert__dismiss`/`mc-modal__close`'s existing pattern.
- SortableJS's own pointer/touch drag remains available for sighted
  mouse/touch users; it is additive, not the only path.

## Definition of Done checklist (`MINTCART_IMPLEMENTATION_GUIDE.md` §79)

- [x] Documented contract (this file)
- [x] `mc-*` BEM markup (`mc-sortable`, `mc-sortable__item`,
      `mc-sortable__handle`, `mc-sortable__label`, `mc-sortable__controls`,
      `mc-sortable__move`)
- [x] JS reads `data-mc-sortable`/`data-mc-sortable-handle`/
      `data-mc-sortable-item`/`data-mc-action`, never a CSS class, as the
      hook contract
- [x] Colors/spacing/radius/shadow read from `--mc-*` tokens
- [x] Keyboard usable (move-up/move-down buttons are a complete alternative
      to pointer drag, not a partial one)
- [x] Mobile checked (SortableJS handles touch natively; buttons work
      identically on mobile)
- [x] No Bootstrap 3 dependency
- [x] No OCMOD required
- [ ] Theme override checked — not applicable; this is an admin-only
      component, outside the storefront Theme API's scope
      (`MINTCART_TECHNICAL_PLAN.md` §11 draws Theme API around storefront
      components; `mc-sortable` has no live admin-screen consumer yet, see
      "No live consumer yet" below)

## No live consumer yet

No admin screen currently renders `[data-mc-sortable]` markup — the same
"component ships ahead of its first live consumer" pattern already
established for every Phase 4 component and `mc-confirm`
(`CLAUDE.md` §85: an API/component is built once there is a real use case
in view, and Functional Scope §6 names all eight scenarios above as real
future consumers; the component itself doesn't need to wait for the first
of those screens to be built before it can be reviewed and shipped). The
most likely first caller is the Homepage Builder's block list (Phase 10)
or Categories drag-and-drop (Phase 17).
