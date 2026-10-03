# Homepage Builder — admin editor

Phase 10 (`MINTCART_FUNCTIONAL_SCOPE.md` §10, `MINTCART_TECHNICAL_PLAN.md`
§17, `MINTCART_IMPLEMENTATION_GUIDE.md` §33): the admin screen that
manages `mc_page`/`mc_block` rows — the other half of Homepage Builder,
complementing `docs/developer/blocks/products.md`'s Provider/Renderer/
Template split (the frontend rendering side). Until this screen existed,
a block instance had to be inserted into the database by hand to ever be
visible on the storefront.

PHP:
- `upload/admin/model/design/homepage.php` — `ModelDesignHomepage`
  (CRUD for `mc_page`/`mc_block`/`mc_block_description`)
- `upload/admin/controller/design/homepage.php` — `ControllerDesignHomepage`
  (`index`/`add`/`edit`/`delete`/`saveOrder`)

Twig: `upload/admin/view/template/design/homepage_list.twig`,
`upload/admin/view/template/design/homepage_form.twig`

Language: `upload/admin/language/en-gb/design/homepage.php`

Menu: `upload/admin/controller/common/column_left.php` — a new "Homepage"
entry under `Design`, gated by `design/homepage` access permission
(auto-discovered by `user/user_permission.php`'s existing controller-tree
scan — no separate permission registration file to update).

Tests: `tests/mintcart/design/homepage-model-test.php`,
`tests/mintcart/design/homepage-admin-schema-test.php` (the generic
field-schema handling — every type's schema is well-formed, every
`label_key`/`help_key` resolves in the language file, `prepareBlockData()`
round-trips flat and JSON-items types correctly, `validateForm()` catches
malformed JSON)

## Why this screen's overall chrome still uses the legacy admin theme

This screen's page chrome (breadcrumbs, panel, buttons, alerts) is still
built on the legacy Bootstrap 3 admin conventions every stock OpenCart
admin screen already uses (`design/banner.php`/`banner_list.twig`/
`banner_form.twig` and `extension/module/latest.php`/`latest.twig` are its
closest structural templates), because the shared admin `header`/
`column_left`/`footer` wraps *every* admin screen and still only ever
loads Bootstrap 3/jQuery unconditionally — migrating that shell wholesale
to Bootstrap 5 remains a separate, larger product/architectural decision
(`CLAUDE.md` §20) this doc does not speak for.

## Reordering now uses `McSortable`

`docs/developer/admin/asset-enqueue.md` ("The fix: two bundles, pick the
right one") resolved the blocker the previous version of this section used
to flag: `assets/dist/css/components.min.css` (unlike `index.min.css`)
deliberately excludes Bootstrap 5's reset/grid/utility layer, so it's safe
to enqueue on a page that already runs Bootstrap 3 — `admin/controller/
catalog/product.php::enqueueMintAssets()` proved this first.
`ControllerDesignHomepage::enqueueMintAssets()` is this pattern's second
consumer (called from `getList()` only — the add/edit form doesn't render
any `mc-*` markup yet), queuing that same `components.min.css` plus
`assets/dist/js/admin.min.js`.

The blocks list (`homepage_list.twig`) is now a real `McSortable` list
(`docs/developer/components/sortable.md`, which named this exact screen as
its expected first consumer): `<ul class="mc-sortable" data-mc-sortable
data-mc-sortable-handle>`, one `<li class="mc-sortable__item"
data-mc-sortable-item="<block_id>">` per block, each with a drag handle,
keyboard-usable move-up/move-down buttons, and a hidden `<input
type="hidden" name="order[<block_id>]">` recomputed on every
`mc:sortable:reorder` event (a small inline `<script>`, same plain
non-module convention every admin Twig template follows). The same "Save
Order" button/`<form>`/`ControllerDesignHomepage::saveOrder()` request this
screen always had still submits those `order[<block_id>]` values exactly
as before — `saveOrder()`'s own contract (accept an `order` array, persist
it, guarded by `page_id`) did not need to change, exactly as the previous
version of this doc predicted.

The visible, directly-editable `<input type="number">` per row (this
screen's previous non-pointer reordering path) is gone — `mc-sortable`'s
own move-up/move-down buttons are the current non-pointer alternative
(`MINTCART_IMPLEMENTATION_GUIDE.md` §68), and they do not depend on
`McSortable`'s own `Sortable.create()` call succeeding (see the known
limitation right below), only on `admin.js`'s delegated `document`-level
click listener having been registered at all.

**Known interim limitation — pointer/touch drag depends on `admin.js`'s
own init-call timing.** `admin.min.js` is enqueued as a plain classic
`<script src="...">` inside `admin/view/template/common/header.twig`'s
`<head>`, with no `defer`/`async`/`type="module"` — it runs the moment the
parser reaches it, before this screen's own `<body>` markup (the `<ul
data-mc-sortable>` list included) exists in the DOM. `initSortableLists()`
(`assets/src/js/components/sortable.js`) does an eager
`document.querySelectorAll('[data-mc-sortable]')` at call time to call
`Sortable.create()` on each match; if that call happens too early, it
finds nothing, so pointer/touch drag never initializes for this screen's
list. The move-up/move-down buttons are unaffected — `initSortableLists()`
also registers a single delegated `document.addEventListener('click',
...)` for them, and attaching a listener to `document` works regardless of
whether the list's own markup exists yet, so keyboard reordering works
either way. If `assets/src/js/admin.js` does not yet defer its `boot()`
call sequence until `DOMContentLoaded` (check that file's own header
comment — this exact timing bug was independently found and is being
fixed as part of Phase 17's Categories drag-and-drop work, a separate
change from this one, since both land in the same shared `admin.js`),
pointer/touch drag on this screen will silently do nothing until that
lands; retype-free reordering via the move buttons works today regardless.

## Block type registry

`ControllerDesignHomepage::TYPES` now lists all fifteen shipped block
types — the full first-wave catalog (`MINTCART_IMPLEMENTATION_GUIDE.md`
§51: Products, Hero, Categories, Banner Grid, Benefits, FAQ, Testimonials,
Brands, Text/Image, CTA, Newsletter, Spacer, Text, HTML, Gallery). Each
now has a real, merged Provider/Renderer/Twig template.

Rather than 15 near-identical hardcoded forms, `ControllerDesignHomepage`
has one private `fieldSchema()` — a declarative list of `settings`
(structural, `mc_block.settings_json`) and `content` (localized,
`mc_block_description.content_json`) fields per type, matching each
type's own `*BlockProvider::build()` exactly (field names, defaults,
which side each lives on). `getForm()`/`prepareBlockData()`/
`validateForm()` walk that schema generically instead of switching on
`type`, and `homepage_form.twig` renders whatever fields the current
type's schema declares (text/number/select/textarea inputs) rather than a
hardcoded "Products settings" block. Adding a sixteenth type later is one
`TYPES` entry plus one `fieldSchema()` entry — no controller or template
changes needed.

**Live type switching, no JS bundle.** The `type` `<select>` has a plain
`onchange="location.href='...&type=' + this.value"` — a GET reload of the
same add/edit URL, which `getForm()` now also reads (`$this->request->
get['type']`, checked after POST but before the block's own stored type)
so the settings/content fields shown update to match the newly chosen
type immediately, still with zero JavaScript bundle dependency (the
"Why this screen uses the legacy admin theme" section above still fully
applies — this is one inline `onchange` attribute, not `McSortable`/a
built asset).

## Content vs. settings

Same storage split as before, now generic across all 15 types:

- **Content** (`block_content[<language_id>][<field>]`): one input per
  configured store language per content field the current type declares
  — `title` for most types, but e.g. `subtitle`/`cta_label` for Hero,
  `html` for HTML, nothing at all for Spacer (purely structural, no
  content fields).
- **Settings** (`settings[<field>]`): a single set of fields (not
  per-language), matching each type's own `*BlockProvider::build()`
  defaults exactly — e.g. a Products block saved with no `category_id`
  behaves identically whether that `null` came from this form or from
  `ProductsBlockProvider`'s own fallback.

`category_id`/`parent_id` are still plain numeric inputs, not an
`McEntityPicker` — Phase 12's category picker backend
(`mintcart/entity/category`) exists now, but wiring it into this specific
field is a separate, self-contained follow-up (a picker is a genuinely
different UI, not something the generic schema-driven field renderer
covers).

### The five `items`-repeater types

Banner Grid, Benefits, Testimonials, Brands, and Gallery each have a
repeatable list of items (banner slides, trust badges, quotes, logos,
photos) with their own structural fields (image path, `url`, `rating`,
...) and localized fields (`alt`, `caption`, `quote`, ...). A real
add/remove-row JS UI for that needs the same admin asset-enqueue decision
the section above already flags as unresolved (no JS bundle is loaded on
this screen at all), so each `items` field is deliberately a single raw
JSON `<textarea>` for now — a genuinely working way to configure any of
these five block types today, not a placeholder. Its `help_key` shows the
exact JSON shape expected (e.g. Benefits:
`[{"icon": "truck"}]` for settings-side structural items,
`[{"title": "Free shipping", "description": "..."}]` for the matching
content-side language tab — items are matched up by array position,
exactly like each type's own `*BlockProvider::build()` already does).

Posting malformed JSON into one of these fields fails `validateForm()`
with `error_json` rather than silently discarding it or storing a broken
value; a blank field is valid (normalizes to an empty list). Replacing
this textarea with a real repeater is flagged in
`docs/IMPLEMENTATION_STATUS.md` as follow-up work once the JS-bundle
question is resolved.

## Manual verification

No MySQL/live OpenCart request in this environment (same gap every
previous phase's admin-adjacent work carries) — a human should, on a real
install:

1. Open `Design > Homepage` (confirm the menu entry appears for a user
   group with `design/homepage` access).
2. Add a Products block (confirm the `home` `mc_page` row is created
   automatically on first visit if it doesn't exist yet).
3. Confirm the block appears on the storefront homepage
   (`docs/developer/blocks/products.md`'s manual verification step).
4. Add a second block, use the "Sort Order" inputs + "Save Order" to swap
   their order, confirm the storefront homepage reflects the new order.
5. Edit a block to Disabled, confirm it disappears from the storefront
   homepage but still appears (marked "Disabled") in the admin list.
6. Delete a block, confirm both its `mc_block` and `mc_block_description`
   rows are gone.
7. On the Add form, change the "Block Type" dropdown (e.g. Products →
   Hero) and confirm the page reloads with Hero's own fields (image,
   subtitle, button label/URL, ...) in place of Products' — no page
   refresh via the browser's own reload, just the dropdown's `onchange`.
8. Add a Benefits (or any other `items`-repeater type) block, paste a
   valid JSON array into both the settings-side and content-side "Items
   (JSON)" fields per the field's help text, save, and confirm the
   storefront homepage renders every item correctly.
9. Save that same form with deliberately broken JSON in an Items field
   (e.g. a trailing comma) and confirm the form re-shows with an
   `error_json` message and the exact text you typed still in the
   textarea, rather than silently clearing it.
10. With at least three blocks on the list, confirm the Network tab shows
    `components.min.css`/`admin.min.js` loading (200 status) on this
    screen's list page. Try dragging a block to a new position by its grip
    handle. If `assets/src/js/admin.js` already defers its init calls until
    `DOMContentLoaded` (see that file's own header comment), the drag
    should work and the list should visually reorder; if not yet (the
    "Known interim limitation" above), dragging will silently do nothing —
    either way, use a block's Move Up/Move Down buttons instead, confirm
    the list reorders, then click "Save Order" and confirm the storefront
    homepage reflects the new order.
