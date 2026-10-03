# Bulk Editor — `Catalog → Products`

`MINTCART_FUNCTIONAL_SCOPE.md` §7 ("Inline Edit / Bulk Actions"),
`MINTCART_TECHNICAL_PLAN.md` §29 ("Bulk Editor"),
`MINTCART_IMPLEMENTATION_GUIDE.md` §62.

Mass **status / quantity / price / manufacturer / stock status / special
price** update across a multi-selected set of products, reusing the exact
`selected[]` checkboxes `Catalog → Products`' existing Delete/Copy actions
already provide. Category/SEO fields are natural follow-ups over the same
shape — see "Next step" below.

PHP:

- `upload/system/library/mintcart/catalog/bulkproductupdatevalidator.php`
  (`MintCart\Catalog\BulkProductUpdateValidator`)
- `upload/admin/model/catalog/product.php`
  (`ModelCatalogProduct::updateProductField()`, new method)
- `upload/admin/controller/catalog/product.php`
  (`ControllerCatalogProduct::bulkUpdate()`/`validateBulkUpdate()`, new
  methods)

View: `upload/admin/view/template/catalog/product_list.twig` (toolbar
addition)

Language: `upload/admin/language/en-gb/catalog/product.php` (new
`text_bulk_*`/`error_bulk_*` keys)

Test: `tests/mintcart/catalog/bulk-product-update-validator-test.php`
(46 scenarios)

## Why a new model method, not `editProduct()`

`ModelCatalogProduct::editProduct($product_id, $data)` is a full,
destructive rewrite: besides the `oc_product` row itself, it **deletes and
reinserts** `product_description`, `product_to_store`, `product_attribute`,
`product_option`/`product_option_value`, `product_recurring`,
`product_discount`, `product_special`, and more — every call assumes
`$data` carries the product's *entire* current state, the same shape
`ControllerCatalogProduct::getForm()`'s edit form posts. Calling it with
only `{status: 1}` (without re-supplying every other field and every
existing relation) would silently wipe descriptions, options, discounts
and specials for every selected product — a catastrophic side effect for
what a merchant expects to be a narrow, safe action.

`ModelCatalogProduct::updateProductField($product_id, $field, $value)` is
new and deliberately minimal: for `status`/`quantity`/`price`/
`manufacturer_id`/`stock_status_id` it runs a single `UPDATE oc_product
SET <one whitelisted column> = ..., date_modified = NOW() WHERE
product_id = ...` and never touches any other table or column. `$field`
must be one of `MintCart\Catalog\BulkProductUpdateValidator::FIELDS` — an
unrecognized field is a defensive no-op (the method has no request/
response of its own to report an error through; validation is the
controller's job, before this method is ever called).

`special_price` is the one exception to "single column" above — OpenCart
stores a product's special price in a separate `oc_product_special` row
(`product_id`, `customer_group_id`, `priority`, `price`, `date_start`,
`date_end`), not a column on `oc_product` itself. `updateProductField()`
handles it with its own branch: delete every existing
`oc_product_special` row for the product, then — only if `$value` is
non-blank — insert exactly one row at the store's default customer group
(`config_customer_group_id`), `priority = 1`, `date_start`/`date_end` both
`'0000-00-00'` (i.e. always-on, no date range). This is a **deliberate
MVP simplification** of what the full product-edit form's Special tab
supports (multiple rows, one per customer group, each with its own
optional date range) — a bulk action driven by one `bulk_value` text
input has no way to express more than one flat number, so it sets the
simplest thing that reliably means "this product is now on special at
this price for everyone" rather than attempting to merge into whatever
per-customer-group/date-ranged rows a product already had. A blank
`bulk_value` removes the special entirely (no row), matching
`MintCart\Catalog\BulkProductUpdatePlanner::validateNullableNonNegativeFloat()`'s
existing `null` = "remove" semantics for the same field.

## Why the same form-POST-then-redirect shape as Delete/Copy, not AJAX

`ControllerCatalogProduct::delete()`/`copy()` already establish the
pattern this action follows exactly: `selected[]` checkboxes inside
`#form-product`, a toolbar button with `form="form-product"`/
`formaction="..."`, POST, then a redirect back to the list preserving the
current filter/sort/page query string. `bulkUpdate()` adds a fourth
button using the identical shape — no new AJAX/JSON endpoint, and
critically, **no new admin asset bundle** (MintCart's own compiled
`assets/dist/{css,js}` still isn't enqueued on any admin page — the same
blocker `docs/developer/blocks/homepage-admin.md` and
`docs/developer/catalog/category-drag-drop.md` already document for their
own admin screens). This screen stays entirely on the legacy Bootstrap
3/jQuery admin theme, same reasoning every other MintCart admin addition
since Phase 10 has given.

## Why `type="submit"` + `onclick="return confirm(...)"`, not `type="button"` + manual `.submit()`

The existing Delete button is `type="button"` with an inline
`onclick="confirm(...) ? $('#form-product').submit() : false;"`, relying
on a separate `<script>` block (`$('button[form=\'form-product\']').on(
'click', ...)`, labelled "IE and Edge fix!") to set `#form-product`'s
`action` attribute to the clicked button's `formaction` **before** the
form actually submits. In practice this only works for Delete because its
`formaction` happens to equal the form's own default `action="{{ delete
}}"` — a content-attribute `onclick` handler registers (and therefore
fires) before a same-element `<script>`-block `.on('click', ...)`
handler added later in the document, so `$('#form-product').submit()` can
run *before* the action-fixing handler does. Copying that pattern
verbatim for a *new* button whose `formaction` differs from the form's
default action would risk submitting to the wrong route (in this case,
the product list's default action is `{{ delete }}` — a bulk-update click
could silently **delete** every selected product instead of updating it).

Using `type="submit"` instead (the same shape the existing Copy button
already uses) sidesteps this entirely: native HTML5 `formaction` handling
applies the button's own target URL as part of the browser's built-in
submit action, independent of JS timing. `onclick="return confirm(...);"`
on a submit button is the standard way to gate a native submit behind a
confirmation — returning `false` cancels the default action (the confirm
dialog appears, and only on OK does the browser proceed to submit using
this button's own `formaction`).

## Value input

The toolbar renders one `bulk_field` `<select>`
(Status/Quantity/Price/Manufacturer/Stock Status/Special Price) and a
**per-field-typed** `bulk_value` control: an Enabled/Disabled `<select>`
(`#input-bulk-value-status`, options `1`/`0`, the same `text_enabled`/
`text_disabled` global language keys the existing Status filter `<select>`
above it already uses) when `status` is chosen, or a `type="number"`
`<input>` (`#input-bulk-value-number`, `min="0"`, `step="1"` for
quantity/manufacturer_id/stock_status_id, `step="any"` for price/
special_price) for every other field. Both controls share
`name="bulk_value"`; a small inline `<script>` block toggles which one is
`disabled` on `#input-bulk-field`'s `change` event — a disabled form
control is excluded from the browser's own POST body, so
`ControllerCatalogProduct::bulkUpdate()` still only ever receives a single
`bulk_value`, unchanged from before. `BulkProductUpdateValidator::validateValue()`
still does the real server-side enforcement (`0`/`1` for status, a
non-negative whole number for quantity, a non-negative number for price, a
non-negative whole number — an ID, or `0` for none — for manufacturer and
stock status, blank or a non-negative number for special price); the
per-field control is a UX improvement on top of that, not a replacement
for it — a merchant can still type an out-of-range number into a
`type="number"` input, and the server rejects it exactly as before.

Manufacturer and stock status are entered as raw `manufacturer_id`/
`stock_status_id` integers, the same way the other three fields are raw
values today — not the `McEntityPicker` manufacturer backend Phase 12
already ships (`docs/developer/components/entity-picker.md`'s
"Manufacturer backend" section), and not the `oc_stock_status`-backed
`<select>` the standard product edit form (`product_form.twig`) already
renders. Wiring either in here is the same "blocked on the admin
asset-enqueue problem" gap Phase 12/17/16's own "Next step" entries
already track elsewhere in `docs/IMPLEMENTATION_STATUS.md`, not a new one
this increment introduces. `stock_status_id`'s `0` value has no dedicated
"none" meaning the way `manufacturer_id`'s does in the OpenCart UI (the
default install's `oc_stock_status` rows start at id `5`) — it is allowed
purely because it mirrors `ControllerCatalogProduct::getForm()`'s own
`$data['stock_status_id'] = 0;` fallback when no value has been posted
yet (`upload/admin/controller/catalog/product.php`), not because `0` is a
meaningful bulk-update target a merchant would intentionally choose.

## Verified

- `php -l` clean on every touched/added PHP file.
- `tests/mintcart/catalog/bulk-product-update-validator-test.php` (46
  scenarios: field whitelist, per-field value validation incl. rejection
  cases for status/quantity/price/manufacturer_id/stock_status_id/
  special_price, product-id normalization/dedup/dropping non-positive
  ids) — passing.
- Full `tests/mintcart/**/*-test.php` suite re-run end to end (81 files)
  — all passing, no regressions.
- `product_list.twig`'s `{% if %}`/`{% endif %}`/`{% for %}`/`{% endfor
  %}` tag counts checked balanced (14/14, 2/2) before and after the
  toolbar addition, same script-based technique used for every other
  `.twig` file in this repo; unchanged by the per-field-value-control
  increment (no `{% %}` tags touched, only plain HTML/inline `<script>`).
- The three inline `<script>` blocks in `product_list.twig` (including the
  new bulk-field toggle) checked for JS syntax errors with a standalone
  Node harness (Twig `{{ }}` output tags substituted with a placeholder
  string first, since Node can't parse Twig) — all three parse cleanly.
- No JS/CSS in `assets/` touched — `npm run build` not re-run for this
  increment (this screen stays on the legacy admin Bootstrap 3/jQuery
  stack, no MintCart-owned asset bundle involved).

**Not verified:** no live MySQL/admin session in this environment — a
human should confirm the toolbar renders correctly, the confirm dialog
appears, the redirect preserves filters, and each of the six fields
actually updates the right column/table without disturbing anything else
(in particular: confirm a product's descriptions/options/discounts are
unchanged after any bulk update, and that a bulk special-price update
correctly replaces — not adds to — any existing `oc_product_special`
row(s), including one a merchant had previously set with a date range or
non-default customer group via the normal edit form). See
`docs/MANUAL_VERIFICATION.md`'s Phase 16 entry.

## Next step

- `category_ids` and SEO fields as further
  `BulkProductUpdateValidator::FIELDS` entries — `category_ids` needs a
  multi-select/picker control the current single `bulk_value` text input
  can't provide (see "Why a new model method, not `editProduct()`" above
  for why `special_price` needed its own branch, the same underlying
  constraint); `category_ids` is already validated as its own field set
  by `MintCart\Catalog\BulkProductUpdatePlanner`, not yet wired to a live
  endpoint.
- Chunked/resumable AJAX batch jobs (`MINTCART_TECHNICAL_PLAN.md` §29)
  for very large selections — today's synchronous PHP loop mirrors
  Delete/Copy's own existing (also synchronous) behavior, so it isn't a
  new risk this increment introduces, but it doesn't yet have progress
  reporting for a merchant bulk-updating hundreds of products at once.
- True inline edit (editing a value directly in the product list table
  without a separate bulk-toolbar step) — a larger UI change, likely
  blocked on the same admin asset-enqueue problem every other MintCart
  admin screen since Phase 10 has flagged.

**Done (per-field-typed value control):** see "Value input" above — no
longer a "Next step" item.
