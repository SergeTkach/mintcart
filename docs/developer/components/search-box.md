# `mc-search-box`

MintCart's AJAX search-as-you-type dropdown (`MINTCART_FUNCTIONAL_SCOPE.md`
§14 "AJAX Search", `MINTCART_TECHNICAL_PLAN.md` §26,
`MINTCART_IMPLEMENTATION_GUIDE.md` §59, priority **P0**). This is the UI
half `docs/developer/commerce/search-service.md` named as "the natural next
increment" once `MintCart\Commerce\SearchService` and its
`mintcart/search/suggest` endpoint shipped with no consumer yet.

JS: `assets/src/js/components/search-box.js` (`createSearchBox()`,
`initSearchBoxes()`)

CSS: `assets/src/css/components/search-box.css`

## Why it talks to the endpoint directly, no `../commerce/` data-layer module

`cart-client.js` exists as a thin data layer because several DOM consumers
(product-card's Add to Cart button, the legacy header cart widget) all need
the same normalized cart snapshot after their own independent mutation.
`mintcart/search/suggest`'s response (`docs/developer/commerce/search-service.md`:
`{ query, count, items[] }`) is already exactly the shape this component
renders, and there is currently exactly one consumer (this component) — so
a separate client module would be an abstraction with no second caller yet
(`CLAUDE.md` §85/§86).

## Markup contract

```html
<div class="mc-search-box" data-mc-search-box>
    <input
        type="search"
        name="search"
        class="mc-search-box__input mc-field__control"
        data-mc-search-box-input
        aria-label="Search products"
    >

    <div class="mc-search-box__results" data-mc-search-box-results hidden>
        <ul class="mc-search-box__list" role="listbox" data-mc-search-box-list></ul>

        <p class="mc-search-box__empty" data-mc-search-box-empty hidden>
            No results.
        </p>

        <a class="mc-search-box__view-all" data-mc-search-box-view-all hidden>
            View all results
        </a>
    </div>
</div>
```

Result rows (`<li role="option" data-mc-search-box-url="…">`, each
containing an image, name and `mc-price`) are rendered entirely by
`search-box.js` — there is no static row markup for a host page to write,
the same reasoning `docs/developer/components/entity-picker.md` gives for
its own list.

Composes `mc-price`/`mc-price--special` (Phase 4) for each result's price
instead of a second price-formatting look
(`MINTCART_IMPLEMENTATION_GUIDE.md` §9).

## Usage

`initSearchBoxes()` auto-wires every `[data-mc-search-box]` element found
in the document against the default `mintcart/search/suggest` action —
already called from `catalog.js`, so a future template only needs to
render the markup contract above, no JS changes. `createSearchBox()` is
also exported directly for a caller that needs a non-default `action`/
`searchRoute` or the returned instance's `refresh()`/`close()`/`destroy()`.

```js
import { createSearchBox } from './components/search-box.js';

const box = createSearchBox(document.querySelector('.mc-search-box'), {
    action: 'mintcart/search/suggest', // default
    searchRoute: 'product/search',     // default - the full results page
});

document.addEventListener('mc:search-box:results', (event) => {
    console.log(event.detail.query, event.detail.count, event.detail.items);
});
```

## Behavior

- **Debounced fetch** — 250ms default (`debounceMs` option) after the last
  keystroke on `[data-mc-search-box-input]`.
- **Client-side minimum length** — a query shorter than 2 characters never
  reaches the network, mirroring `SearchService::isQueryValid()`'s own rule
  server-side; the dropdown simply closes instead.
- **Request cancellation** — an in-flight fetch is aborted via
  `AbortController` the moment a newer query starts, so a slow first
  response can never overwrite a later one (same technique
  `entity-picker.js` already uses, `MINTCART_IMPLEMENTATION_GUIDE.md` §3's
  `AbortController` allowance).
- **Keyboard navigation** — ArrowDown from the input moves into the first
  result; ArrowUp/ArrowDown then move a roving `tabindex` between results
  (WAI-ARIA listbox pattern); Enter/Space navigates to the focused result;
  Escape closes the dropdown and returns focus to the input. No feature is
  mouse/touch-only (Functional Scope §27).
- **Outside click/focus** closes the dropdown without clearing the typed
  query — `focusout` checks `event.relatedTarget` against `root`.
- **"View all results"** appears only when the server's `count` exceeds the
  number of items actually returned (the endpoint caps at 8 —
  `docs/developer/commerce/search-service.md`'s `SUGGEST_LIMIT`), linking
  to the full `product/search` results page with the same query — the
  suggest endpoint's response never replaces that page
  (`docs/developer/commerce/search-service.md` "What this is not").
- **Events** — `mc:search-box:results` (bubbling,
  `detail: { query, count, items }`) fires on `root` after every successful
  fetch, empty or not.

## Not wired into any live template yet

`mint_default`'s header still renders the legacy jQuery-autocomplete search
fragment (`docs/developer/components/header.md` "Why widgets stay legacy
fragments") — same reason Navigation/Cart(partially)/Account still do.
`initSearchBoxes()` is nonetheless already called from `catalog.js` now:
it only ever activates against a `[data-mc-search-box]` root, and none
exists in any shipped template yet, so this is inert until a future header
increment drops the markup contract above into
`mint_default/template/common/header.twig` — the same "ship the component
behind a real Twig contract, wire the template later" approach `mc-quantity`/
`mc-modal` already took in Phase 4, and `mc-product-card`/`CartService` took
in Phase 6/15 before `ComponentRenderer` existed to render them.

Replacing the legacy fragment outright (rather than composing it) also
needs the same admin/storefront asset-enqueue decision every other
Commerce/Theme-API JS increment since Phase 7 has flagged as the real
blocker for *visual* verification (`docs/developer/components/header.md`
"Known gap") — this component's fetch/render/keyboard logic is real and
independently testable (see "Not verified" below), but nothing will look
styled on a live page until that decision is made.

## Definition of Done checklist (`MINTCART_IMPLEMENTATION_GUIDE.md` §79)

- [x] Documented contract (this file)
- [x] `mc-*` BEM markup (`mc-search-box`, `__input`, `__results`, `__list`,
      `__item`, `__item-image`, `__item-body`, `__item-name`,
      `__item-price`, `__empty`, `__view-all`)
- [x] JS reads `data-mc-search-box-*` hooks, never a CSS class, as the
      trigger/query contract
- [x] Colors/spacing/radius/shadow/z-index read from `--mc-*` tokens
- [x] Keyboard usable — WAI-ARIA listbox roving-tabindex pattern
      (ArrowDown/ArrowUp/Enter/Space/Escape), `:focus-visible` outline
- [x] Reuses `mc-price` rather than a second price look
- [x] Mobile checked — `inset-inline: 0` dropdown, no fixed desktop-only
      widths
- [x] No Bootstrap 3 dependency
- [x] No OCMOD required
- [ ] Theme override checked — deferred; no live template renders this
      component's markup yet (see "Not wired into any live template yet")
- [ ] Live data verified end to end — this environment has no MySQL server
      (same pre-existing gap noted throughout `docs/IMPLEMENTATION_STATUS.md`);
      a human should confirm the dropdown renders/navigates correctly
      against a real catalog once a template renders the markup contract

## Verified

- `npm install` (0 vulnerabilities), `npm run build` (minified) and
  `npm run dev` (sourcemapped) both compile cleanly.
- `mc-search-box`/`mc-search-box__item`/`mc-search-box__item-price`/etc. are
  present in the bundled `catalog.min.js`/`index.min.css`, and **absent**
  from `admin.min.js` (spot-checked with `grep -o`) — storefront-only, same
  scoping `product-card.js` uses.
- `npm test` (preset-token contract checks, unaffected by this change) —
  still passing.
- Full `tests/mintcart/**/*-test.php` suite re-run end to end — unaffected
  (no PHP touched by this increment), still passing.

**Not verified:** no live browser/MySQL session in this environment — a
human should confirm keyboard navigation, the debounce/abort behavior under
fast typing, and the "View all results" link once this component's markup
lands in a real template. See `docs/MANUAL_VERIFICATION.md`'s new Phase 15
entry.
