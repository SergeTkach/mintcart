# `mc-header`

MintCart's storefront header component (`MINTCART_IMPLEMENTATION_GUIDE.md`
§48, Technical Plan §14). First Phase 7 component (Footer is a separate,
parallel increment — see `docs/IMPLEMENTATION_STATUS.md`).

CSS: `assets/src/css/components/header.css`
No JS of its own — the account menu reuses Bootstrap 5's dropdown primitive
(`data-bs-toggle="dropdown"`), and mobile navigation (below) composes the
existing `mc-drawer` component (`assets/src/js/components/drawer.js`,
already wired into `catalog.js`) rather than adding a new script — the same
allowance already used by `mc-modal`/`mc-drawer` for Bootstrap's
Modal/Offcanvas JS (`MINTCART_IMPLEMENTATION_GUIDE.md` §9).

## Provider

`MintCart\Design\HeaderViewProvider`
(`upload/system/library/mintcart/design/headerviewprovider.php`) is the
single place the `header` shape gets assembled — branding, account state,
contacts, named navigation links and legacy widget fragments — mirroring
`MintCart\Design\FooterViewProvider`'s role for `footer`.

```php
$header = (new \MintCart\Design\HeaderViewProvider())->build(
    $branding, // ['name' => ..., 'logo' => ..., 'home' => ...]
    $account,  // ['logged' => bool, 'first_name' => ..., 'wishlist_count' => ...]
    $contacts, // ['phone' => ...] — OpenCart's own config_telephone
    $links,    // named URLs: account/login/register/order/transaction/
               // download/logout/wishlist/shopping_cart/checkout/contact
    $widgets   // pre-rendered HTML: menu/search/cart/language/currency
);
```

`branding`/`account`/`contacts` source from OpenCart's existing
`config_name`/`config_logo`/`config_telephone` settings and the visitor's
session/customer state — never duplicated into a Mint-owned table
(`MINTCART_IMPLEMENTATION_GUIDE.md` §39, `CLAUDE.md` §39).

## Why widgets stay legacy fragments

`links`/`widgets` only accept documented keys (`HeaderViewProvider::LINK_KEYS`
/ `WIDGET_KEYS`) — an unrecognized key is silently dropped rather than
exposed to a theme as an undocumented contract field, and every documented
key is always present in the result (`null` when not supplied) so a theme's
`header.twig` never has to guard against a missing array key.

`widgets` holds five pre-rendered HTML fragments straight from OpenCart's
own `common/menu`, `common/search`, `common/cart`, `common/language`,
`common/currency` sub-controllers — not a normalized data shape. Each of
these already has, or is planned to get, its own real Mint-native API:

- **Search** → **done (2026-09-19).** `mint_default`'s and `test_theme`'s
  `header.twig` no longer read `widgets.search` at all — they render
  `mc-search-box` (`docs/developer/components/search-box.md`) directly,
  talking to the `mintcart/search/suggest` AJAX Search endpoint
  (`MINTCART_TECHNICAL_PLAN.md` §26, Functional Scope §14). The
  `widgets.search` key itself is untouched in `HeaderViewProvider`'s
  contract — `header.php` still computes it and it is still exposed for
  any third-party theme that wants the legacy fragment instead.
- **Cart** → Cart JS client / `mc:cart:updated` event contract
  (`MINTCART_TECHNICAL_PLAN.md` §25, Functional Scope §15); still a legacy
  fragment (bridged by `header-cart.js`, see
  `docs/developer/commerce/cart-client.md`) — no live template renders a
  Mint-native cart widget yet, only the legacy dropdown refreshed via the
  real event.
- **Navigation** (`menu`) → no second real consumer yet for a Mint-native
  category-menu-walking component (`CLAUDE.md` §85)

Cart and Navigation are still Phase 15 (Commerce API)/deferred work.
Rather than half-build a normalized shape ahead of a real consumer,
`Header` still embeds those two widgets as-is inside its own layout shell,
following the documented compatibility pattern for legacy fragments
(`MINTCART_IMPLEMENTATION_GUIDE.md` §36 — wrap in `mc-legacy`, never break
the fragment's own IDs/classes/`data-*`). This still gives a theme full
control over the *surrounding* DOM (topbar layout, logo placement, account
menu markup, where each widget slots in) without any OCMOD — the actual
Theme API property this component exists to prove — even while Cart/
Navigation's own internals stay OpenCart-native for now. When they get
real Mint contracts in a later phase, `Header`'s own shape does not need
to change — only what fills `widgets.cart`/`widgets.menu` does, the same
way Search's slot just changed from a legacy fragment to a real component
without `HeaderViewProvider`'s contract shape changing at all.

## Live Twig template

`catalog/view/theme/mint_default/template/mint/components/header.twig` is
Mint Default's real Header component, resolvable via the existing
`MintCart\Design\ComponentRenderer` (`docs/developer/theme-api/component-renderer.md`).

**Live controller wiring is done.** `catalog/controller/common/header.php`
(`ControllerCommonHeader::index()`) builds a `HeaderViewProvider` result and
renders it via `ComponentRenderer::render('header', ...)` into
`$data['mc_header']`, gated by the same "only when the active theme ships a
`theme.json` manifest" check `ControllerCommonFooter`/
`ControllerProductCategory` use. Every field `ControllerCommonHeader`
already computed (`$data['name']`, `$data['logged']`, `$data['account']`,
...) is left completely untouched — this only regroups it, it does not
change what it resolves to. A failed/missing `header.twig` is caught and
logged via `MintCart\Core\Logger` rather than fataling every page on the
site (header is common to all of them), the same "log and disable for this
request" pattern `ControllerCommonFooter`/`ControllerProductCategory`
already use.

`catalog/view/theme/mint_default/template/common/header.twig` is the
whole-page wrapper (doctype/head/body-open through `{{ mc_header }}`) that
OpenCart's own `event/theme.php` three-tier fallback resolves the
`common/header` route to for any Mint-aware theme without its own copy
(same mechanism `docs/adr/003-page-template-fallback-fix.md` fixed for
`product/category.twig`).

### Known gap (partially resolved): MintCart's own asset bundle

The wrapper's `<head>` still deliberately keeps the *exact same* legacy
asset stack `default/template/common/header.twig` loads — Bootstrap 3,
jQuery 3.7.1, Font Awesome, `catalog/view/javascript/common.js` — rather
than replacing it. `common.js` (and several other OpenCart core scripts —
cart AJAX, form validation on account/checkout pages) depend on jQuery
being loaded globally; *replacing* that stack safely still needs a full
call-site audit of every page/widget that assumes it, per `CLAUDE.md`
§10/§84 ("never modify an existing OpenCart flow based only on memory...
determine backward-compatibility risk... only then implement"). That
remains a separate, larger, not-yet-attempted increment.

What a later run did resolve: `ControllerCommonHeader::index()` now
*additionally* enqueues `assets/dist/css/components.min.css` +
`assets/dist/js/catalog.min.js` (not `index.min.css` — see
`docs/developer/admin/asset-enqueue.md`'s "Storefront:
`ControllerCommonHeader`" section for the full writeup, including why
those two specific files and not the ones this note used to name). Before
that, `mc-*` component CSS (`mc-header`, `mc-product-card`, ...) had no
effect on a live page at all, and no `data-mc-action` hook (`add-to-cart`,
`quick-order`, ...) had any JS listening for it — nothing linked any
`assets/dist/` file anywhere on the storefront. A real visual/functional
verification of any Mint component on a live install is still outstanding
(no MySQL/browser session in this environment), but the link itself is no
longer the blocker.

## Contract

```twig
{# branding, account, contacts, links, widgets - HeaderViewProvider::build() output #}
<header class="mc-header">
    <div class="mc-header__topbar">
        {# contacts.phone, widgets.language, widgets.currency, account menu, links.wishlist #}
    </div>
    <div class="mc-header__main">
        {# mobile nav toggle, branding.logo/name -> links.home, mc-search-box, widgets.cart #}
    </div>
    {% if widgets.menu %}
        <nav class="offcanvas-lg offcanvas-start mc-drawer mc-header__nav" id="mcHeaderNav">
            {# offcanvas-header (mobile only) + widgets.menu in .offcanvas-body #}
        </nav>
    {% endif %}
</header>
```

`text_account`, `text_register`, `text_login`, `text_order`,
`text_transaction`, `text_download`, `text_logout`, `text_search` are
ordinary OpenCart language strings already present in
`catalog/language/en-gb/common/header.php` — no new strings were needed
(`text_search` is used as both the `mc-search-box` input's `placeholder`
and `aria-label`, see `docs/developer/components/search-box.md`).
`text_wishlist` is passed through **already `sprintf`-formatted** with the
wishlist count (matching `ControllerCommonHeader`'s pre-existing
`sprintf($this->language->get('text_wishlist'), ...)` call) — `Header`
does not re-format it from `account.wishlist_count`, which stays available
in the contract for a theme that wants a different presentation (e.g. a
badge counter instead of inline text). `text_menu`/`text_close` **are** two
new strings, added to the same file for the mobile navigation panel below
(`docs/upstream-diff.md`'s `catalog/language/en-gb/common/header.php`
entry).

## Mobile navigation

`mc-header__nav` (the `<nav>` wrapping `widgets.menu`) carries Bootstrap
5's responsive off-canvas classes — `.offcanvas-lg.offcanvas-start` (never together with plain `.offcanvas`, which hides it at every width)
— plus `.mc-drawer` (`docs/developer/components/drawer.md`). At the `lg`
breakpoint (992px) and up, Bootstrap's own CSS neutralizes the off-canvas
positioning/backdrop/transform and the nav renders as a normal static
block, unchanged from before this landed. Below `lg`, it becomes the same
slide-in panel `mc-drawer` uses everywhere else, opened by a new
`.mc-header__nav-toggle` hamburger button (visible only below `lg`, via
`d-lg-none`) through the existing `data-mc-action="open-drawer"` /
`data-mc-target="#mcHeaderNav"` contract — no new JS component, no change
to `initDrawerTriggers()`'s call sites (already called once from
`catalog.js`).

This is `mc-drawer`'s first real trigger-button consumer in this repo, so
`assets/src/js/components/drawer.js` gained one small addition:
`syncTriggerAriaExpanded()` keeps `aria-expanded` on any
`[data-mc-action="open-drawer"]` trigger referencing the drawer via
`data-mc-target="#<drawer id>"` in sync with the panel's actual
shown/hidden state (Bootstrap's dropdown/collapse primitives do this for
their own `data-bs-toggle` triggers automatically; `mc-drawer` deliberately
never uses that attribute, so nothing else did this before). See
`docs/developer/components/drawer.md` for the full behavior — this is a
generic drawer improvement, not header-specific, and benefits any future
`data-mc-action="open-drawer"` trigger (e.g. a future cart-drawer button)
the same way.

## Bootstrap grid/utilities, not a parallel one

`.container`/`d-flex`/`flex-wrap`/`gap-*` provide the responsive layout
around `mc-header__*` elements (`docs/developer/layout-conventions.md`) —
this component never reimplements grid/flex math of its own.

## Accessibility

- The account menu toggle is a real `<button>` with `aria-expanded`
  (Bootstrap's dropdown JS keeps it in sync), not a bare `<a href="#">`.
- Contact `tel:` links are real anchors, keyboard-reachable like any other
  link — no click-only JS behavior.
- `.mc-header__account-menu-link:focus-visible` gets the same
  visible-outline treatment as every other interactive MintCart component.
- Legacy widgets (`widgets.menu`/`widgets.search`/`widgets.cart`/
  `widgets.language`/`widgets.currency`) keep whatever accessibility
  behavior OpenCart's own sub-controller templates already provide — the
  `mc-legacy` wrapper only adds layout spacing, never touches the
  fragment's own markup (`MINTCART_IMPLEMENTATION_GUIDE.md` §36).
- The mobile navigation toggle is a real `<button>` with `aria-controls`,
  a translated `aria-label` (`text_menu`), and `aria-expanded` kept in sync
  with the panel's actual open/closed state (see "Mobile navigation"
  above). Opening it hands off to Bootstrap's `Offcanvas` for focus
  trapping, `Escape`-to-close and backdrop click-to-close — same
  `mc-drawer` behavior documented in `docs/developer/components/drawer.md`.

## Definition of Done checklist (`MINTCART_IMPLEMENTATION_GUIDE.md` §79)

- [x] Documented contract (this file)
- [x] `mc-*` BEM markup
- [x] No JS dependency of its own (account dropdown reuses Bootstrap 5's
      existing Modal/Offcanvas-style allowance for `data-bs-toggle`)
- [x] Colors/spacing/fonts read from `--mc-*` tokens
- [x] Keyboard usable (real `<a>`/`<button>` elements, Bootstrap dropdown
      focus/`Escape` behavior)
- [x] Mobile checked (topbar/main rows use `flex-wrap`, no fixed widths;
      navigation gets a dedicated off-canvas/hamburger pattern below `lg` —
      see "Mobile navigation" above)
- [x] No Bootstrap 3 dependency in the component's own CSS
- [x] No OCMOD required
- [x] Ships a real Mint Default `.twig` file, resolvable via
      `ComponentRenderer` (verified: `if`/`for` tag nesting is balanced,
      same script-based check used for `product-card.twig`/`footer.twig`)
- [x] Live controller wiring — `ControllerCommonHeader::index()`, see "Live
      Twig template" above
- [x] Theme override checked — `test_theme` now overrides `header.twig`
      with a genuinely different DOM (single stacked bar, no account
      dropdown, no topbar/search widgets), still `"parent": null` in its
      `theme.json`; `component-renderer-test.php` scenario 11 proves both
      fallback directions against the real shipped files
- [x] MintCart's own asset bundle gets enqueued on a Mint-aware storefront
      theme — `components.min.css`/`catalog.min.js`, see "Known gap
      (partially resolved)" above
- [ ] **Not fully verified end-to-end**: this environment has no MySQL
      server/`vendor/twig/twig`, so no live rendered page was observed —
      same pre-existing gap as every earlier Phase 6/7/8 component. A human
      should confirm a real `mint_default`-configured install's header
      renders correctly, a `test_theme`-configured install shows the
      distinct header DOM, and that the now-enqueued bundle actually loads
      (network tab, 200 response) and makes `mc-product-card`'s Add to
      Cart/Quick Order/wishlist buttons respond to a click, before this is
      production-ready.

## Sticky header (2026-10-07)

`mc-header--sticky` + `data-mc-sticky-header` (on by default in
mint_default; drop both for a static header). The topbar scrolls away; the
main row and navigation stay (`top: -var(--mc-header-topbar-height)`).
`assets/src/js/components/sticky-header.js` keeps
`--mc-header-topbar-height` in sync (ResizeObserver) and toggles `is-stuck`
(shadow) after the topbar has scrolled off. While the mobile nav drawer is
open the header is lifted to `--mc-z-drawer` so the drawer stays above
Bootstrap's body-level backdrop.

Below `lg` the main row is compact: menu toggle, logo, cart in one row,
search underneath. The mini cart shows an item-count bubble on the icon
below 576px, where the summary text is visually hidden (still the
accessible label).
