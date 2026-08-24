# `mc-tabs`

MintCart's tabbed panel component (`docs/MINTCART_IMPLEMENTATION_GUIDE.md`
§52, Phase 11 Mint Admin primitive). Use for splitting a long entity form
into sections without leaving the page — e.g. a future Product edit screen's
General/Data/Links/Attributes/Options/SEO sections
(`MINTCART_FUNCTIONAL_SCOPE.md` §7 bulk/inline product editing implies a
form with more fields than fit comfortably as one flat form).

CSS: `assets/src/css/components/tabs.css`
JS: `assets/src/js/components/tabs.js` (`initTabsTriggers`)

## Why it wraps Bootstrap's `Tab`

`mc-tabs` reuses Bootstrap 5's `Tab` JS - active-pane switching, fade
transition, and (per Bootstrap's own docs) arrow-key/Home/End keyboard
navigation across the tablist - instead of reimplementing tab behavior from
scratch. `MINTCART_IMPLEMENTATION_GUIDE.md` §9 explicitly allows this for
generic UI primitives, and `MINTCART_FUNCTIONAL_SCOPE.md` §27 requires
keyboard navigation MintCart would otherwise have to build by hand.

Bootstrap's own `.nav`/`.nav-tabs`/`.nav-link`/`.tab-content`/`.tab-pane`/
`.active`/`.fade`/`.show` classes stay on the markup because `Tab`'s JS
toggles some of them directly and its CSS (fade transition, active-state
layout) lives on those classes. They are internal implementation, the same
way `.modal-dialog` is internal plumbing for `mc-modal` -
**`.mc-tabs`/`.mc-tabs__*` and `data-mc-*` are the public contract**, not
the Bootstrap classes.

## Basic usage

```html
<div class="mc-tabs">
    <div class="mc-tabs__list nav nav-tabs" role="tablist">
        <button
            class="mc-tabs__tab nav-link active"
            type="button"
            role="tab"
            id="tab-general"
            data-mc-action="show-tab"
            data-bs-target="#pane-general"
            aria-controls="pane-general"
            aria-selected="true"
        >General</button>
        <button
            class="mc-tabs__tab nav-link"
            type="button"
            role="tab"
            id="tab-data"
            data-mc-action="show-tab"
            data-bs-target="#pane-data"
            aria-controls="pane-data"
            aria-selected="false"
            tabindex="-1"
        >Data</button>
    </div>

    <div class="mc-tabs__panels tab-content">
        <div class="mc-tabs__panel tab-pane fade show active" id="pane-general" role="tabpanel" aria-labelledby="tab-general" tabindex="0">
            ...
        </div>
        <div class="mc-tabs__panel tab-pane fade" id="pane-data" role="tabpanel" aria-labelledby="tab-data" tabindex="0">
            ...
        </div>
    </div>
</div>
```

- `data-mc-action="show-tab"` on a trigger activates its pane.
  Bootstrap's own `data-bs-target="<pane selector>"` must stay on the same
  trigger - `Tab`'s JS reads that attribute directly to find the pane, the
  same way `mc-modal` keeps `.modal-dialog` as required internal wiring.
- `role="tablist"` on the trigger list, `role="tab"` on each trigger, and
  `role="tabpanel"` on each pane are required ARIA roles Bootstrap's `Tab`
  expects and manages `aria-selected`/`tabindex` against as tabs switch.
- Exactly one trigger should start with `active`/`aria-selected="true"` and
  one pane with `show active`; every other trigger needs `tabindex="-1"` so
  only the active tab is in the default tab order (arrow keys move focus
  between the rest, per Bootstrap's `Tab` keyboard handling).
- `aria-controls` on each trigger and `aria-labelledby` on each pane cross-
  reference each other so assistive tech announces the relationship.

## Behavior

`initTabsTriggers()` is called once from `admin.js` and delegates on
`document`, so tabs inserted later work without a per-instance init call -
same pattern as `mc-modal`.

- Activating a tab goes through Bootstrap's `Tab.getOrCreateInstance()`,
  never Bootstrap's own `data-bs-toggle="tab"` attribute - that attribute is
  intentionally not part of this component's markup, so `data-mc-*` stays
  the only public trigger surface.
- On activation, Bootstrap's own `shown.bs.tab`/`hidden.bs.tab` events are
  relayed as bubbling `mc:tabs:shown`/`mc:tabs:hidden` `CustomEvent`s on the
  `.mc-tabs` root (`detail.pane` is the activated/deactivated pane's
  selector), so listeners only need the `mc:*` contract
  (`MINTCART_TECHNICAL_PLAN.md` §25), not Bootstrap's event namespace.
- Everything else (fade transition, arrow-key/Home/End navigation between
  triggers, `aria-selected`/`tabindex` bookkeeping) is Bootstrap's `Tab`
  behavior, unmodified.

## Not wired into `catalog.js`

Phase 11 (`MINTCART_IMPLEMENTATION_GUIDE.md` §52) lists `McTabs` as a Mint
**Admin** primitive, not a Phase 4 shared component - `admin.js` imports
`initTabsTriggers()`, `catalog.js` does not. A storefront use case (e.g.
product page description/specification/reviews tabs) would be a real,
separate reason to also import it there once such a page exists
(`CLAUDE.md` §85 - no speculative consumer).

## Accessibility

- Keyboard navigation (arrow keys/Home/End across the tablist, Enter/Space
  to activate) is handled by Bootstrap's `Tab`
  (`MINTCART_FUNCTIONAL_SCOPE.md` §27).
- Only the active trigger is reachable via normal `Tab` key navigation
  (`tabindex="-1"` on inactive triggers); arrow keys move focus within the
  tablist, matching the WAI-ARIA Tabs pattern Bootstrap's `Tab` implements.
- `role="tablist"`/`role="tab"`/`role="tabpanel"` plus `aria-selected`/
  `aria-controls`/`aria-labelledby` give assistive tech the full tab/panel
  relationship.

## Definition of Done checklist (`MINTCART_IMPLEMENTATION_GUIDE.md` §79)

- [x] Documented contract (this file)
- [x] `mc-*` BEM markup for MintCart-owned parts (`mc-tabs`,
      `mc-tabs__list`, `mc-tabs__tab`, `mc-tabs__panels`, `mc-tabs__panel`);
      Bootstrap's own nav/tab-pane structure classes are kept as internal
      implementation, not styled or relied on as the public contract
- [x] JS reads `data-mc-action`, never a CSS class, as the trigger contract
      (`data-bs-target` stays as required Bootstrap `Tab` internal wiring,
      same as `mc-modal`'s `.modal-dialog`)
- [x] Colors/spacing/radius/motion read from `--mc-*` tokens, applied via
      Bootstrap's own `--bs-nav-link-*`/`--bs-nav-tabs-*` custom properties
      (no Bootstrap source recompilation)
- [x] Keyboard usable (arrow-key/Home/End tablist navigation, Enter/Space to
      activate - all via Bootstrap's `Tab`)
- [x] Mobile checked (Bootstrap's nav-tabs layout wraps by default; no
      fixed desktop-only sizing added)
- [x] No Bootstrap 3 dependency
- [x] No OCMOD required
- [ ] Theme override checked - no live consumer/page renders `mc-tabs`
      markup yet to verify a theme override against (`CLAUDE.md` §85); the
      Theme resolver mechanism itself was already proven in Phase 8 and
      applies uniformly to every component template
