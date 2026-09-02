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

## Why this screen uses the legacy admin theme, not `mc-*`/Bootstrap 5

MintCart's Phase 11 admin primitives (`McSortable` in particular — see
`docs/developer/components/sortable.md`, which names "Homepage Builder's
block list" as its expected first consumer) were built with this exact
screen in mind, but this run does **not** use them here.

The reason: `assets/dist/css/index.min.css` and `assets/dist/js/admin.min.js`
(the built bundle every `mc-*` component/`McSortable` ships in) have never
been enqueued into the admin theme's shared `header`/`column_left`/
`footer` chrome, anywhere in this repository. That chrome wraps *every*
admin screen, not just this one — enqueuing MintCart's Bootstrap 5 CSS
bundle here would load it globally alongside the legacy admin theme's
Bootstrap 3 CSS (`view/stylesheet/bootstrap.css`, loaded unconditionally
in `admin/view/template/common/header.twig`) on every other admin screen
too, which `CLAUDE.md` §10 explicitly forbids ("Do not globally load
Bootstrap 3 and Bootstrap 5 together" — `.btn`/`.container`/`.modal`/
`.dropdown`/`.alert` all collide between the two versions).

Resolving that — migrate the whole admin shell to Bootstrap 5, scope
MintCart's own screens some other CSS-isolated way, or keep new
MintCart-owned admin screens on the legacy admin theme's conventions
until a dedicated "Mint Admin Shell" phase replaces `header`/
`column_left`/`footer` wholesale — is a real product/architectural
decision (`CLAUDE.md` §20), not something this increment guesses at. It
is flagged for the project owner in this change's pull request
description and in `docs/IMPLEMENTATION_STATUS.md`'s Phase 10 entry.

Until that's decided, this screen is built on the exact same legacy
Bootstrap 3 admin conventions every stock OpenCart admin screen already
uses (`design/banner.php`/`banner_list.twig`/`banner_form.twig` and
`extension/module/latest.php`/`latest.twig` are its closest structural
templates) — including how it solves reordering:

## Reordering without `McSortable`

The blocks list (`homepage_list.twig`) shows one `<input type="number"
name="order[<block_id>]">` per row (the exact same pattern
`design/banner_form.twig` already uses for `banner_image` rows'
`sort_order` fields) — the base mechanism, always present, always
directly editable. A "Save Order" button (`formaction="{{ save_order }}"`,
sharing the same `<form>` the bulk-select checkboxes and "Delete" button
already use, via HTML5's `formaction` attribute) submits every row's
value in one request to `ControllerDesignHomepage::saveOrder()`, which
persists them via `ModelDesignHomepage::updateSortOrder()`.

`updateSortOrder()` guards each `UPDATE` with `block_id AND page_id`, so a
crafted `order[<block_id>]` for a block on a different page (there is
only ever one page — `home` — today, but the guard costs nothing and
matches `mc_block`'s already page-scoped shape) is silently ignored
rather than reassigning that block's order.

**Drag-to-reorder is layered on top**, as a small inline `<script>` in
`homepage_list.twig` using the browser's native HTML5 Drag and Drop API —
deliberately not `McSortable`: no MintCart JS bundle is enqueued on this
screen at all yet (same asset-enqueue question as above), so pulling in
`assets/src/js/components/sortable.js` here isn't available without
first resolving that. Dragging a row (`draggable="true"` on each `<tr>`,
a `fa-bars` grip icon as the visual affordance) reorders the table in the
DOM and renumbers the existing number inputs to match; "Save Order"
submits exactly as before either way. Native HTML5 drag has no built-in
keyboard path, so the always-visible, always-editable number inputs are
this screen's non-pointer alternative (`MINTCART_IMPLEMENTATION_GUIDE.md`
§68: a sortable operation needs one) — a keyboard-only admin reorders by
retyping numbers, no drag gesture required.

Once the Bootstrap-5-admin-shell decision above is resolved, replacing
both the number-input column and this inline script with
`data-mc-sortable`/`data-mc-sortable-item` markup and a listener for
`McSortable`'s `mc:sortable:reorder` event (POSTing `detail.order` to
`saveOrder()` instead of relying on a full-page form submit) is a
self-contained follow-up — `saveOrder()`'s contract (accept an `order`
array, persist it, guarded by `page_id`)
does not need to change for that upgrade.

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
10. With at least three blocks on the list, drag a row to a new position
    (grip icon on the left) and confirm the "Sort Order" numbers renumber
    to match without a page reload, then click "Save Order" and confirm
    the storefront homepage reflects the new order. Also try reordering
    by editing the number inputs directly (no drag) to confirm that path
    still works unchanged.
