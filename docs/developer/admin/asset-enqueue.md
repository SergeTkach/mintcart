# Enqueueing MintCart's own bundle on an admin screen

`MINTCART_FUNCTIONAL_SCOPE.md` §8 (Entity Picker), §6 (drag-and-drop),
§5/§26 (modern admin UI) all assume MintCart's own compiled
`assets/dist/{css,js}` bundle is actually loaded on the admin screens that
need it. Until this increment, nothing enqueued it anywhere. Every recent
Phase 12/16/17 PR (entity-picker backends, Bulk Editor, Categories
drag-and-drop, the Homepage Builder admin editor) independently hit this
and documented it as the same standing blocker, then deliberately shipped
backend-only work rather than guess at a fix — see e.g.
`docs/developer/components/entity-picker.md`'s "Admin backends" section
before this run, or `docs/developer/catalog/bulk-editor.md`'s "Why the
same form-POST-then-redirect shape ... not AJAX".

## Root cause

`assets/dist/css/index.min.css` (Phase 2/3) bundles the *entire* Bootstrap
5 CSS reset/base/grid/utility layer
(`@import 'bootstrap/dist/css/bootstrap.css'` at the top of
`assets/src/css/index.css`). Every stock OpenCart 3 admin screen — and the
storefront's `default`-theme-wrapping pages — already runs on Bootstrap 3.
Loading the full Bootstrap 5 stylesheet on top of that redefines
`.container`/`.row`/`.col-*`/`.btn`/`.modal`/`.form-control`/etc. out from
under the existing markup. This isn't theoretical: a live check on
2026-08-24 confirmed it broke `.navbar-nav`/dropdown menus, shrank body
text, and made widgets overlap on a real page — see
`upload/catalog/view/theme/mint_default/template/common/header.twig`'s own
comment, which is where that attempt was tried and reverted.

Every subsequent admin-side increment since then correctly treated "add
`index.min.css`/`admin.min.js` to this admin screen" as unsafe and shipped
without it, rather than repeat that mistake.

## The fix: two bundles, pick the right one

**`assets/dist/css/components.min.css`** (new, this run —
`assets/src/css/components.css`) is `index.css` with only the Bootstrap 5
import removed. Every remaining import (tokens, presets, every `mc-*`
component, the beneficial `bootstrap3-shim.css`) was grepped before adding
this file and confirmed to only ever declare `--mc-*` custom properties on
`:root`/`[data-mc-preset]` or `.mc-*`-scoped selectors — nothing global,
nothing that redefines a bare element or a Bootstrap class name. So this
bundle is safe to add to a page that already runs Bootstrap 3, without
touching or reworking anything already on it.

**Caveat:** `mc-modal`, `mc-drawer`, `mc-toast`, `mc-confirm`, `mc-tabs`
and `mc-progress` all wrap a Bootstrap 5 JS primitive
(`Modal`/`Offcanvas`/`Toast`/tab) and rely on *that same* primitive's own
structural CSS (`.modal`/`.offcanvas`/`.toast`/...) — which lives in the
full `bootstrap.css` this bundle deliberately excludes — for actual
positioning, backdrop and animation. Those six components render as
unstyled/non-functional chrome from `components.min.css` alone. Every
other shipped component (`mc-button`, `mc-field`, `mc-badge`,
`mc-entity-picker`'s own list/row markup, `mc-sortable`, ...) has no such
dependency and works standalone.

**`assets/dist/js/admin.min.js`** was already safe to enqueue anywhere —
it's pure JS, no CSS side effects, and `import 'bootstrap'` (its own
Bootstrap 5 JS dependency, used by `mc-modal`/`mc-drawer`/`mc-toast`) is a
vanilla ES module with no jQuery coupling, so it doesn't collide with the
page's existing Bootstrap 3 `bootstrap.min.js`/jQuery. The blocker was
never really the JS bundle — it was that pairing it with `index.min.css`
(the only CSS bundle that existed until this run) would have pulled in the
same Bootstrap 5 conflict.

**`assets/dist/css/index.min.css`** stays the right choice for a page that
owns its *entire* asset stack and has no Bootstrap 3 to conflict with — the
one existing example is
`admin/view/template/mintcart/wizard.twig` (a fully standalone page, own
`<html>`, no admin header/footer, no Bootstrap 3 at all).

## How to enqueue it on a legacy-theme admin screen

Use OpenCart's own per-page asset mechanism — `$this->document->addStyle()`
/`addScript()` — the same one three existing admin controllers already use
(`extension/payment/paypal.php`, `extension/advertise/google.php`,
`marketplace/marketplace.php`). `admin/view/template/common/header.twig`
already loops over `styles`/`scripts` and renders whatever these calls
queue — **no template change needed**, on this file or on any admin
screen's own template, just the controller call:

```php
$this->document->addStyle(HTTP_CATALOG . 'assets/dist/css/components.min.css');
$this->document->addScript(HTTP_CATALOG . 'assets/dist/js/admin.min.js');
```

`HTTP_CATALOG` (not `HTTP_SERVER`) is required: `assets/dist/` is deployed
at the site's public webroot — sibling to both `admin/` and the storefront
— not inside `admin/`'s own directory (`.gitignore`'s
"Local-dev copy of the build output inside the OpenCart docroot" comment
documents the same layout for local development: `upload/assets/` is a
gitignored dev-time mirror of repo-root `assets/dist/`, since `upload/` is
what a local Apache/Laragon setup serves). `HTTP_CATALOG` is a standard
OpenCart admin config constant already used the same way elsewhere in this
codebase (`admin/model/tool/image.php`, several payment models) and is
exactly what `admin/controller/mintcart/wizard.php` already uses for this
same path (`$data['asset_base'] = HTTP_CATALOG;`,
`{{ asset_base }}assets/dist/css/index.min.css` in its own standalone
template).

Only call this on the specific action(s) that actually need the bundle
(e.g. the add/edit form action, not the list action too) — it's a real
extra HTTP request per page load, and most admin screens have no MintCart
component on them yet.

## First real consumer

`ControllerCatalogProduct::getForm()` (this run) — see
`docs/developer/components/entity-picker.md`'s "Admin backends" section
and `docs/upstream-diff.md`'s new
`admin/controller|view/catalog/product.php` entry for the full writeup of
what it unblocked: `product_form.twig`'s Category field can now mount a
real `McEntityPicker` against the `mintcart/entity/category` backend.

## Calling `createEntityPicker`/`createMediaPicker` from a legacy template

Admin Twig templates (`product_form.twig` included) use plain non-module
inline `<script>` blocks — the same jQuery-style convention every stock
OpenCart 3 admin template already follows — not `type="module"`. They
cannot `import` an ES module themselves. `assets/src/js/admin.js` now
exposes `createEntityPicker` on `window.MintCart.createEntityPicker` for
exactly this reason (see its own comment for the rationale). Any future
inline-script consumer follows the same shape: call
`window.MintCart.createEntityPicker(root, options)` directly, after
confirming `window.MintCart` exists (defensive — the bundle might not be
enqueued on every page, e.g. during incremental rollout to more fields/
screens). `createMediaPicker` (Phase 11, still with no real consumer) is
the natural next one to bridge the same way once a Media UX screen
(Functional Scope §25) needs it.

## What this run deliberately did not attempt

- **Swapping the storefront's own asset stack** (`mint_default`'s
  `header.twig`) — a much larger, separately-tracked problem (a full
  call-site audit of every jQuery/`common.js`/Bootstrap-3-dependent
  storefront page, per `docs/developer/components/header.md`'s "Known
  gap"). This run is admin-only.
- **Replacing** `product_form.twig`'s existing jQuery-autocomplete Category
  UI outright — kept both, additive, since this environment has no live
  MySQL/admin session to visually verify a full swap. Removing the old UI
  once a human confirms the picker works on a real install is a natural,
  low-risk follow-up.
- **A generic "enqueue on every Mint-touching admin screen" helper** (e.g.
  a shared trait/base controller method) — `enqueueMintAssets()` lives on
  `ControllerCatalogProduct` only for now, one real consumer, per
  `CLAUDE.md` §85/§86 ("no abstraction ahead of a second real consumer").
  The next admin screen that needs the bundle (Phase 17's category
  drag-and-drop reorder, Phase 11's Homepage Builder repeaters) is the
  natural trigger to extract it — most likely as a small trait, not a new
  base `Controller` subclass, once there's a second call site to compare
  against.
