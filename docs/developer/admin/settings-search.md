# Admin quick settings search

`MINTCART_FUNCTIONAL_SCOPE.md` §23, P1 ("Швидкий пошук налаштувань"):

> Користувач повинен мати можливість знайти: currency, logo, delivery,
> SEO URL, email, tax, checkout — без знання структури меню. Пошук
> повинен вести не просто на сторінку, а за можливості на конкретну
> секцію/field.

"за можливості" (if possible) marks field-level targeting as a stretch
goal, not a requirement — this increment's scope is page-level navigation
only, matching every one of that section's own example terms.

## Pieces

- `MintCart\Admin\SettingsSearchIndex`
  (`upload/system/library/mintcart/admin/settingssearchindex.php`) — a
  pure PHP class (no `\Registry`/`\DB`, same reasoning as
  `MintCart\Catalog\BulkProductUpdateValidator`) holding a static catalog
  of `{label, route, keywords}` entries and a `search(string $query, int
  $limit = 8): array` method (case-insensitive substring match against
  label/keywords, exact match ranks highest, then prefix, then
  substring). Every `route` is a real admin route already linked from
  `admin/controller/common/column_left.php`'s own menu — grepped before
  writing the catalog, never invented.
- `ControllerMintcartAdminsearch`
  (`upload/admin/controller/mintcart/adminsearch.php`, route
  `mintcart/adminsearch`, GET `q`) — resolves each `SettingsSearchIndex`
  match's `route` into a real URL via `$this->url->link()` and returns
  `{"results": [{"label": "...", "url": "..."}]}`. Gated by OpenCart's
  own route-based ACL (`hasPermission('access', 'mintcart/adminsearch')`),
  same shape as `ControllerMintcartEntity` and every other `mintcart/*`
  admin controller — a fresh install's user groups do not have this
  listed yet; an admin must grant it under Users → User Groups →
  Permissions before the search box returns anything.
- `ControllerCommonHeader::index()` — the one controller every logged-in
  admin page renders — now calls `enqueueMintAssets()` (the same
  `components.min.css`/`admin.min.js` pair `docs/developer/admin/asset-enqueue.md`
  documents; see "Why `components`, not `index`" there) and builds
  `$data['mc_admin_search_url']` (`mintcart/adminsearch` with this
  session's `user_token` already appended).
- `admin/view/template/common/header.twig` — a new `<li class="mc-admin-search" data-mc-admin-search data-mc-admin-search-url="{{ mc_admin_search_url }}">`
  in the top-right nav, inside the existing `{% if logged %}` block (no
  new `{% %}` tag — the existing conditional already gates every other
  logged-in-only element in this file).
- `assets/src/js/components/admin-search.js` (`initAdminSearch()`, wired
  into `admin.js` the same declarative way as `initAlertDismiss()`/
  `initSortableLists()`/etc.) — debounced (`250ms`) `fetch()` against
  `mc_admin_search_url + '&q=' + query`, renders a results dropdown,
  Enter/click navigates via `window.location.href`. ArrowUp/ArrowDown move
  a roving "active" result, Escape closes, a click outside the component
  closes it.
- `assets/src/css/components/admin-search.css` (`mc-admin-search` BEM,
  Implementation Guide §12) — imported into both `index.css` and
  `components.css` at the same relative position (right after
  `entity-picker.css`), per `components.css`'s own "keep this list's
  imports identical to index.css" rule.

## Why a new controller, not reusing `mintcart/entity`

`ControllerMintcartEntity` (Phase 12, Entity Picker) is a *data-entity*
search — products/categories/manufacturers/etc., each backed by a real
OpenCart model/table, paginated, multi-select. This is a *navigation*
search over a small static catalog of admin screens, single-select,
single page of results, no pagination. Different data shape (`{label,
url}` vs `{id, name, image}`), different UI (a plain dropdown, not
`mc-entity-picker`'s list/pager/footer chrome) — forcing one shape onto
the other would be a worse fit for both, not a reuse.

## Why `ControllerCommonHeader`, not a shared layout controller

OpenCart's admin has no separate "layout" controller to hook into —
`common/header` and `common/footer` are independently rendered sub-views
every admin controller's own template includes directly (`{{ header }}`/
`{{ footer }}`), and `common/header` is the one both already run on every
logged-in page and already own the top-right nav markup the search box
belongs next to. No new controller/event needed to reach "every admin
page" — this one already is that.

## `MintCart\Admin` — a new namespace

No class existed yet under `MintCart\Admin` before this increment (Entity
Picker backends live under `MintCart\Design`, since they are Theme-API-style
data providers; this is cross-cutting admin UX catalog data instead,
which doesn't fit `Design`/`Core`/`Catalog`/`Commerce`). `MINTCART_TECHNICAL_PLAN.md`
§18 groups "Mint Admin" primitives (`McDrawer`/`McModal`/`McEntityPicker`/
etc.) as their own area; `MintCart\Admin` is this increment's first PHP
class under that same conceptual grouping.

## Verified

- `php -l` clean on every new/modified PHP file.
- `tests/mintcart/admin/settings-search-index-test.php` (new, pure PHP,
  no OpenCart dependency) — blank/zero-limit query returns nothing, every
  one of Functional Scope §23's own example terms (`currency`/`logo`/
  `delivery`/`email`/`tax`/`checkout`, plus `seo`/`url` for "SEO URL")
  resolves to at least one result, an exact label match ranks first,
  case-insensitivity, a keyword-only match (`vat` matching Tax
  Classes/Tax Rates without appearing in either label), the limit is
  respected, every catalog entry has a non-empty route and at least one
  keyword, and every route is unique.
- `npm run build` — both CSS bundles contain `mc-admin-search`; the JS
  bundle contains `initAdminSearch`/`data-mc-admin-search`.
- `admin/view/template/common/header.twig`'s `{% if %}`/`{% endif %}`/
  `{% for %}`/`{% endfor %}` tag counts checked balanced (3/3, 4/4) before
  and after — no `{% %}` tag touched, only a new `<li>` with `{{ }}`
  output tags inside the existing `{% if logged %}` block.
- Full `tests/mintcart/**/*-test.php` suite re-run end to end — all
  passing, no regressions.

**Not verified:** no live MySQL/admin session in this environment — a
human should confirm (see `docs/MANUAL_VERIFICATION.md`'s new Phase 9
Productivity entry): the search box renders in the admin header on every
logged-in page; a fresh install's default user group has no access to
`mintcart/adminsearch` until explicitly granted (same as every other
`mintcart/*` route); after granting it, typing "currency"/"delivery"/
"seo"/"checkout" returns real, clickable results that navigate to the
right admin page; and that `ControllerCommonHeader`'s own MC-0018 fix (see
`docs/fixes/registry.md`) means the admin logo and user-profile dropdown
menu text are no longer blank.

## Next step

- Field-level targeting (`MINTCART_FUNCTIONAL_SCOPE.md` §23's "за
  можливості" stretch goal) — needs a per-screen anchor/section design
  (e.g. a `#tab-general`-style fragment per entry), not a mechanical
  addition over today's whole-page routes.
- More `SettingsSearchIndex` catalog entries as new admin screens ship
  (e.g. `mintcart/wizard`, `mintcart/compatibility` once those PRs land on
  `master`).
- A keyboard shortcut to focus the search box (e.g. `/` — common
  "quick search" convention) — not attempted this run, no real UX
  validation yet that it wouldn't collide with a form field already
  focused.

## Jump to a field (2026-10)

A catalog entry may carry `field` (the id of an input on the target page). The result's URL then ends
with `#mc-field=<id>`; on that page `initFieldJump()` (`assets/src/js/components/admin-search.js`, started from
`admin.js`) opens the tab holding the input, scrolls it to the middle, focuses it and flashes its row
(`.mc-field-flash`). Entries so far, all on `setting/setting` (the default store's settings): store name, email,
telephone, address, logo, favicon, default currency, default language, meta title. A field entry
outranks a page-level entry that matches equally well, and a label hit no longer hides a better keyword hit.

Keywords are already in English, Ukrainian and Russian; there is no Ukrainian admin language pack (the admin
only ships `en-gb`), so the result labels are English.

Not checked in a browser (the admin needs a login): the test covers the index and the ranking
(`tests/mintcart/admin/settings-search-index-test.php`); the jump script is a few lines of DOM code.
