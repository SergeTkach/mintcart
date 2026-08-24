# `mc-header`

MintCart's storefront header component (`MINTCART_IMPLEMENTATION_GUIDE.md`
§48, Technical Plan §14). First Phase 7 component (Footer is a separate,
parallel increment — see `docs/IMPLEMENTATION_STATUS.md`).

CSS: `assets/src/css/components/header.css`
No JS — purely presentational. The account menu reuses Bootstrap 5's
dropdown primitive (`data-bs-toggle="dropdown"`), the same allowance
already used by `mc-modal`/`mc-drawer` for Bootstrap's Modal/Offcanvas JS
(`MINTCART_IMPLEMENTATION_GUIDE.md` §9).

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
these already has its own real Mint-native API planned for later:

- **Search** → AJAX Search normalized endpoint (`MINTCART_TECHNICAL_PLAN.md`
  §26, Functional Scope §14)
- **Cart** → Cart JS client / `mc:cart:updated` event contract
  (`MINTCART_TECHNICAL_PLAN.md` §25, Functional Scope §15)
- **Navigation** (`menu`) → no second real consumer yet for a Mint-native
  category-menu-walking component (`CLAUDE.md` §85)

Both Search and Cart are explicitly Phase 15 (Commerce API) work, not this
phase's. Rather than half-build a normalized shape ahead of those real
consumers, `Header` embeds the five widgets as-is inside its own layout
shell, following the documented compatibility pattern for legacy fragments
(`MINTCART_IMPLEMENTATION_GUIDE.md` §36 — wrap in `mc-legacy`, never break
the fragment's own IDs/classes/`data-*`). This still gives a theme full
control over the *surrounding* DOM (topbar layout, logo placement, account
menu markup, where each widget slots in) without any OCMOD — the actual
Theme API property this component exists to prove — even while the five
widgets' own internals stay OpenCart-native for now. When Search/Cart/
Navigation get real Mint contracts in a later phase, `Header`'s own shape
does not need to change — only what fills `widgets.search`/`widgets.cart`/
`widgets.menu` does.

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

### Known gap: MintCart's own asset bundle is not enqueued yet

The wrapper's `<head>` deliberately keeps the *exact same* asset stack
`default/template/common/header.twig` loads today — Bootstrap 3, jQuery
3.7.1, Font Awesome, `catalog/view/javascript/common.js` — rather than
switching to `assets/dist/{css,js}/index.min.css`/`catalog.min.js`.
`common.js` (and several other OpenCart core scripts — cart AJAX, form
validation on account/checkout pages) depend on jQuery being loaded
globally; swapping the asset stack safely needs a full call-site audit of
every page/widget that assumes it, per `CLAUDE.md` §10/§84 ("never modify
an existing OpenCart flow based only on memory... determine
backward-compatibility risk... only then implement"). That is a separate,
larger increment than this Header component, tracked as a follow-up in
`docs/IMPLEMENTATION_STATUS.md`. In the meantime, `mc-*` component CSS
(`mc-header`, `mc-product-card`, ...) has no effect on a live page yet —
nothing links `assets/dist/css/index.min.css` anywhere — a real visual
verification of any Mint component still needs that link added first.

## Contract

```twig
{# branding, account, contacts, links, widgets - HeaderViewProvider::build() output #}
<header class="mc-header">
    <div class="mc-header__topbar">
        {# contacts.phone, widgets.language, widgets.currency, account menu, links.wishlist #}
    </div>
    <div class="mc-header__main">
        {# branding.logo/name -> links.home, widgets.search, widgets.cart #}
    </div>
    {% if widgets.menu %}<nav class="mc-header__nav mc-legacy">{{ widgets.menu|raw }}</nav>{% endif %}
</header>
```

`text_account`, `text_register`, `text_login`, `text_order`,
`text_transaction`, `text_download`, `text_logout` are ordinary OpenCart
language strings already present in
`catalog/language/en-gb/common/header.php` — no new strings were needed.
`text_wishlist` is passed through **already `sprintf`-formatted** with the
wishlist count (matching `ControllerCommonHeader`'s pre-existing
`sprintf($this->language->get('text_wishlist'), ...)` call) — `Header`
does not re-format it from `account.wishlist_count`, which stays available
in the contract for a theme that wants a different presentation (e.g. a
badge counter instead of inline text).

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

## Definition of Done checklist (`MINTCART_IMPLEMENTATION_GUIDE.md` §79)

- [x] Documented contract (this file)
- [x] `mc-*` BEM markup
- [x] No JS dependency of its own (account dropdown reuses Bootstrap 5's
      existing Modal/Offcanvas-style allowance for `data-bs-toggle`)
- [x] Colors/spacing/fonts read from `--mc-*` tokens
- [x] Keyboard usable (real `<a>`/`<button>` elements, Bootstrap dropdown
      focus/`Escape` behavior)
- [x] Mobile checked (topbar/main rows use `flex-wrap`, no fixed widths)
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
- [ ] **Not fully verified end-to-end**: this environment has no MySQL
      server/`vendor/twig/twig`, so no live rendered page was observed —
      same pre-existing gap as every earlier Phase 6/7/8 component. A human
      should confirm a real `mint_default`-configured install's header
      renders correctly, a `test_theme`-configured install shows the
      distinct header DOM, and that MintCart's own asset bundle gets
      enqueued (see "Known gap" above) before this is production-ready.
