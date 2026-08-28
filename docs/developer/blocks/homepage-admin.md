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

Tests: `tests/mintcart/design/homepage-model-test.php`

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
`sort_order` fields) instead of a drag handle. A "Save Order" button
(`formaction="{{ save_order }}"`, sharing the same `<form>` the bulk-select
checkboxes and "Delete" button already use, via HTML5's `formaction`
attribute — no JavaScript involved) submits every row's value in one
request to `ControllerDesignHomepage::saveOrder()`, which persists them
via `ModelDesignHomepage::updateSortOrder()`.

`updateSortOrder()` guards each `UPDATE` with `block_id AND page_id`, so a
crafted `order[<block_id>]` for a block on a different page (there is
only ever one page — `home` — today, but the guard costs nothing and
matches `mc_block`'s already page-scoped shape) is silently ignored
rather than reassigning that block's order.

Once the Bootstrap-5-admin-shell decision above is resolved, replacing
this number-input column with `data-mc-sortable`/`data-mc-sortable-item`
markup and a small listener for `McSortable`'s `mc:sortable:reorder`
event (POSTing `detail.order` to `saveOrder()` instead of relying on a
full-page form submit) is a self-contained follow-up — `saveOrder()`'s
contract (accept an `order` array, persist it, guarded by `page_id`)
does not need to change for that upgrade.

## Block type registry

`ControllerDesignHomepage::TYPES` currently lists only `products` — the
one block type with a real, merged Provider/Renderer/Twig template
(`docs/developer/blocks/products.md`). The other first-wave block types
documented in `MINTCART_IMPLEMENTATION_GUIDE.md` §33 (Hero, Categories,
Banner Grid, Benefits, Text, Text/Image, Brands, Testimonials, CTA,
Newsletter, FAQ, Gallery, HTML, Spacer) each had their own open,
in-progress pull request as of this run — this screen deliberately does
not add a typed settings form for any of them ahead of knowing each
merged provider's real settings shape, to avoid guessing at (and
conflicting with) that in-flight work.

`mc_block` storage stays type-agnostic (`type` + `settings_json`, per
`MINTCART_TECHNICAL_PLAN.md` §16), so once one of those PRs lands, adding
it here is: one more `TYPES` array entry, plus that type's own settings
fields in `ControllerDesignHomepage::getForm()`/`prepareBlockData()` and
`homepage_form.twig`'s "Products settings" `<fieldset>` (which itself
would need to become type-conditional at that point rather than
hardcoded to Products' four settings — `category_id`/`limit`/`sort`/
`order` — as it is today).

## Content vs. settings

Mirrors the storage split `mc_block_description.content_json` (localized)
vs. `mc_block.settings_json` (structural, non-localized) already
documented in `docs/developer/blocks/products.md`:

- **Content** (`block_content[<language_id>][title]` in the form): one
  `Title` input per configured store language, using the exact
  `entity_description[<language_id>][<field>]` naming/per-language-tab
  pattern `catalog/category_form.twig` already establishes.
- **Settings** (`settings[category_id]`/`settings[limit]`/`settings[sort]`/
  `settings[order]`): a single set of fields (not per-language), matching
  `ProductsBlockProvider::build()`'s normalized defaults exactly, so a
  block saved with e.g. no `category_id` behaves identically whether that
  default came from the admin form or from `ProductsBlockProvider`'s own
  fallback.

`category_id` is a plain numeric input, not an `McEntityPicker`/category
autocomplete — Phase 12's category picker backend
(`mintcart/entity/category`) was an in-progress pull request as of this
run; wiring a second, independent category-selection UI here would risk
duplicating that work. Swapping this field for the real picker once that
PR lands is a self-contained follow-up.

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
