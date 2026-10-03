# `mc-cookie-consent`

MintCart's storefront cookie notice (`MINTCART_FUNCTIONAL_SCOPE.md` §11
"Commerce Essentials", P0) — a small acknowledgement bar, not a granular
cookie-category consent-management platform. Building the latter would be
exactly the kind of scope creep `CLAUDE.md` §16 rules out for MintCart 1.0
("large visual page builder" / "100+ widgets" over-building, applied here
to a consent UI); a store that needs per-category opt-in later can build on
top of the `mc:cookie-consent:*` event contract below without this
component changing shape.

CSS: `assets/src/css/components/cookie-consent.css`
JS: `assets/src/js/components/cookie-consent.js` (`initCookieConsent`,
`createCookieConsentBanner`)

## Why this is the first component that self-injects its own markup

Every other MintCart component so far (`mc-modal`, `mc-toast`,
`mc-search-box`, ...) enhances markup a Twig template already renders —
per `CLAUDE.md` §85, no `Mc*` JS component is built ahead of a real
template consumer. Cookie consent has no such consumer yet: there is no
MintCart Settings facade for admin-editable, translatable banner text
(the same "own isolated setting group" shape
`ControllerMintcartSettingsDiagnostics`/`ControllerMintcartDesignStyles`
already use for a single setting), so building one just for this component
would be the API-ahead-of-consumer mistake `CLAUDE.md` §85 warns against —
and no controller/template file was touched to avoid colliding with the
several other open, in-flight PRs already editing
`header.php`/`header.twig`/`column_left.php` at the time this shipped (see
`docs/IMPLEMENTATION_STATUS.md`'s Phase 11 (Functional Scope §11) entry
for the specific PR numbers checked).

Instead `initCookieConsent()` builds the banner itself, from a hardcoded
English default, and appends it to `document.body` — a real, working
consumer today with zero template changes, because `catalog.js` already
loads unconditionally on every Mint-aware page since Phase 7's asset-enqueue
work (`docs/developer/admin/asset-enqueue.md`). The component is written so
a *future* server-rendered version (once a real Settings facade exists for
admin-editable, per-language text) needs no JS change at all — see
"Two ways this markup can reach the page" in the module's own docblock.
`initCookieConsent()` detects an already-present
`[data-mc-cookie-consent]` element and reuses it instead of injecting a
second one, and the `accept-cookie-consent` click handler is delegated on
`document` (the same pattern `mc-alert`/`mc-modal`/`mc-drawer` already use),
so it works identically whether the markup came from this module or from a
future Twig partial.

## Markup contract

```html
<div class="mc-cookie-consent" role="region" aria-label="Cookie notice" data-mc-cookie-consent>
    <p class="mc-cookie-consent__message">
        We use cookies to make this store work and to improve your experience.
        By continuing to browse, you agree to our use of cookies.
    </p>
    <div class="mc-cookie-consent__actions">
        <a class="mc-cookie-consent__link" href="/index.php?route=information/information&information_id=…">
            Learn more
        </a>
        <button type="button" class="mc-button mc-button--primary mc-button--sm" data-mc-action="accept-cookie-consent">
            Accept
        </button>
    </div>
</div>
```

- `data-mc-cookie-consent` marks the banner root — `initCookieConsent()`
  looks for this attribute to decide whether a banner already exists.
- `data-mc-action="accept-cookie-consent"` is the only behavior hook
  (`CLAUDE.md` §14) — never `.mc-cookie-consent` itself or a bare
  `<button>` selector.
- `.mc-cookie-consent__link` is optional — `createCookieConsentBanner()`
  only renders it when a `policyUrl` option is supplied (see below); a
  future server-rendered version can omit it the same way if the store has
  no linked policy page.
- `mc-button`/`mc-button--primary`/`mc-button--sm` are reused as-is
  (`MINTCART_IMPLEMENTATION_GUIDE.md` §17/§86 — no separate
  `mc-cookie-consent__accept` button style duplicating what `mc-button`
  already provides).

## Behavior

`initCookieConsent()`, called once from `catalog.js` (never `admin.js` —
this is a storefront-only concern):

1. Checks `localStorage` (`mc-cookie-consent` key, value `"accepted"`) for
   prior consent.
2. **Already consented:** removes any `[data-mc-cookie-consent]` element
   already on the page (covers the future server-rendered case for a
   returning visitor) and returns — nothing shown.
3. **Not yet consented:** reuses an existing `[data-mc-cookie-consent]`
   element if the page already rendered one, otherwise builds the default
   English banner via `createCookieConsentBanner()` and appends it to
   `document.body`. Dispatches bubbling `mc:cookie-consent:shown`.
4. Clicking `[data-mc-action="accept-cookie-consent"]` (delegated on
   `document`, so it works for either origin above) writes
   `mc-cookie-consent = "accepted"` to `localStorage`, dispatches bubbling
   `mc:cookie-consent:accepted` on the `.mc-cookie-consent` root, then
   removes the banner from the DOM.

`createCookieConsentBanner(options)` accepts `{ message, acceptLabel,
policyUrl, policyLabel }` — all optional, all falling back to the English
defaults above. No caller passes these yet (no admin UI exists to source
them from); the parameter exists so a future Settings-driven caller can
override text without a `cookie-consent.js` change, per `CLAUDE.md` §85 (an
option the type already needs, not a speculative extra one).

### `localStorage` and Private Browsing

This is the first use of `localStorage` anywhere in this codebase. Every
read and write is wrapped in `try`/`catch`: Safari's Private Browsing mode
throws on `localStorage.setItem()` rather than silently no-opping, and some
stores/embedders disable storage entirely. A store in that state still gets
a fully working, dismissible banner — consent just isn't remembered across
reloads, a graceful degradation rather than a broken page.

## Styling notes

Fixed to the bottom of the viewport (`position: fixed; inset-inline: 0;
bottom: 0;`), `--mc-z-fixed` (shared with Bootstrap's own fixed-position
tier, appropriate since this is a non-modal bar, not an overlay needing
`--mc-z-modal`/`--mc-z-drawer`). Uses `--mc-surface-inverse`/
`--mc-text-inverse` (the same dark-surface tokens `mc-badge`'s
`out-of-stock` variant and nothing else currently reads) so the bar reads
as a distinct system-level notice rather than another content card. The
slide-in entrance animation is removed under `prefers-reduced-motion:
reduce` (`MINTCART_FUNCTIONAL_SCOPE.md` §27).

No Bootstrap 3 dependency, no Bootstrap 5 JS dependency (unlike
`mc-toast`/`mc-modal`, this renders correctly in `components.css`'s
reduced bundle — see `docs/developer/admin/asset-enqueue.md`).

## Accessibility

- `role="region"` + `aria-label="Cookie notice"` gives assistive tech a
  named landmark for the bar, without the assertive interruption of
  `role="alert"` — a cookie notice is informational, not urgent
  (`MINTCART_FUNCTIONAL_SCOPE.md` §27).
- The Accept action is a real `<button>`, reachable by `Tab` like any other
  page control — no focus trap (this is not a modal dialog; blocking
  keyboard navigation of the rest of the page until it's dismissed would
  itself be an accessibility regression).
- Deliberately does not auto-hide/auto-dismiss on a timer — a visitor who
  needs more time to read or operate the Accept button must never lose that
  option to a countdown (`MINTCART_FUNCTIONAL_SCOPE.md` §27's "reduced
  motion"/non-timed-interaction spirit, same reasoning `mc-toast`
  documents for why autohide is opt-in there, applied here as "never" since
  this bar gates consent, not a dismissible tip).

## Not done yet

- No admin-configurable message/link text — hardcoded English default only
  (see "Why this is the first component that self-injects its own markup"
  above). A real Settings facade is the natural next increment once this
  component needs one.
- No per-language strings — same reason; this is JS-literal text, not a
  `catalog/language/en-gb/*` key, until a controller renders it.
- No cookie-category granularity (analytics vs. marketing vs. essential) —
  deliberately out of scope for MintCart 1.0, see the intro above.

## Definition of Done checklist (`MINTCART_IMPLEMENTATION_GUIDE.md` §79)

- [x] Documented contract (this file)
- [x] `mc-*` BEM markup (`mc-cookie-consent`, `mc-cookie-consent__message`,
      `mc-cookie-consent__actions`, `mc-cookie-consent__link`); the Accept
      button reuses `mc-button` rather than inventing its own class
- [x] JS reads `data-mc-action`/`data-mc-cookie-consent`, never a CSS class,
      as the behavior/identity hooks
- [x] Colors/spacing/radius/shadow/motion/z-index all read from `--mc-*`
      tokens
- [x] Keyboard usable (no focus trap by design, see Accessibility)
- [x] Mobile checked (flex-wrap layout, no fixed desktop-only sizing)
- [x] Theme override checked — n/a, this component renders no Twig
      template to override (JS-built markup only); a future
      server-rendered version would go through the normal
      `ComponentRenderer` fallback like any other component
- [x] No OCMOD required
- [x] Component does not depend on Bootstrap 3; works in both the full
      `index.css` bundle and the reduced `components.css` bundle (no
      Bootstrap 5 JS primitive dependency)

## Verified

`npm install` (0 vulnerabilities), `npm run build` and `npm run dev` both
compile cleanly. `grep` confirms `mc-cookie-consent`/the init call land in
both `assets/dist/css/index.min.css` and `assets/dist/css/components.min.css`,
and in `assets/dist/js/catalog.min.js` but **not**
`assets/dist/js/admin.min.js` (storefront-only, by design). `npm test`
(preset-token contract checks, unaffected by this change) still passing.
No PHP touched, so `tests/mintcart/**/*-test.php` doesn't apply — the full
suite (84 files) was still re-run end to end as a regression check, all
passing.

**Not verified:** no live browser session in this environment — a human
should confirm the banner appears on first visit to a real `mint_default`
install, that clicking Accept both hides it and persists across a reload,
and that Private Browsing (or storage disabled) still shows a working,
dismissible banner (see "`localStorage` and Private Browsing" above).
