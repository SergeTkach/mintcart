# Bulk Editor — `Catalog → Products`

`MINTCART_FUNCTIONAL_SCOPE.md` §7 ("Inline Edit / Bulk Actions"),
`MINTCART_TECHNICAL_PLAN.md` §29 ("Bulk Editor"),
`MINTCART_IMPLEMENTATION_GUIDE.md` §62.

Mass **status / quantity / price / manufacturer / stock status / special
price / category** update across a multi-selected set of products, reusing
the exact `selected[]` checkboxes `Catalog → Products`' existing
Delete/Copy actions already provide. SEO fields are a natural follow-up
over the same shape — see "Next step" below.

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
(57 scenarios)

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

`category_ids` is the other non-`oc_product`-column exception: like
`special_price`, it lives in its own table (`oc_product_to_category`), so
`updateProductField()` gives it the same delete-then-reinsert branch
`ModelCatalogProduct::editProduct()`'s own `product_category` relation
already uses (`DELETE ... WHERE product_id = ...` then one `INSERT` per
category id) — just scoped to this single relation, not a full
product-form save. Unlike `special_price`, there's no MVP simplification
here: the bulk action can express exactly the same "this product's
categories are now this exact list" as the full edit form's category
well, because `category_ids` arrives as a real list (`bulk_value[]`), not
a single-value text input.

## Why the same form-POST-then-redirect shape as Delete/Copy, not AJAX

`ControllerCatalogProduct::delete()`/`copy()` already establish the
pattern this action follows exactly: `selected[]` checkboxes inside
`#form-product`, a toolbar button with `form="form-product"`/
`formaction="..."`, POST, then a redirect back to the list preserving the
current filter/sort/page query string. `bulkUpdate()` adds a fourth
button using the identical shape — no new AJAX/JSON endpoint. The six
scalar fields above never needed MintCart's own compiled
`assets/dist/{css,js}` bundle at all (a plain `<select>`/`<input>` is
native HTML); the `category_ids` field does (its picker is
`window.MintCart.createEntityPicker`), so `getList()` now calls the same
`enqueueMintAssets()` helper `getForm()` already used for this screen's
edit-form Category field — see
`docs/developer/admin/asset-enqueue.md` for why that's safe
(`components.min.css`, not `index.min.css`, avoids the Bootstrap 3/5
conflict every other admin screen since Phase 10 had been blocked on).
This screen otherwise stays entirely on the legacy Bootstrap 3/jQuery
admin theme.

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
(Status/Quantity/Price/Manufacturer/Stock Status/Special Price/Category)
and one generic `bulk_value` text `<input>` for the six scalar fields —
not a per-field-typed control (e.g. a `<select>` for Status specifically).
`BulkProductUpdateValidator::validateValue()` still enforces the right
shape server-side (`0`/`1` for status, a non-negative whole number for
quantity, a non-negative number for price, a non-negative whole number —
an ID, or `0` for none — for manufacturer and stock status, blank or a
non-negative number for special price) regardless of what the generic
text input lets a merchant type. A friendlier per-field control for those
six fields (e.g. a `<select>` when `status` is chosen, a `type="number"`
input otherwise) is still a natural follow-up, not a blocker.

`category_ids` cannot share that generic text input at all — it is a
*list* of category IDs, not a single scalar — so choosing "Category" in
`#input-bulk-field` swaps in a dedicated `#bulk-category-field` block
instead: a `window.MintCart.createEntityPicker` (the same one
`product_form.twig`'s own Category field already uses, reusing its
`mintcart/entity/category` search/pagination endpoint unchanged) lets a
merchant search and multi-select categories, appending each confirmed pick
as a `<input type="hidden" name="bulk_value[]" form="form-product">` row
into `#bulk-category-list` (with a click-to-remove `.fa-minus-circle`
icon, the same shape `product_form.twig`'s `#product-category` well
already uses). `#input-bulk-field`'s `change` handler toggles which
control is visible *and* `disabled`s `#input-bulk-value` while
`category_ids` is chosen, so only one of `bulk_value`/`bulk_value[]` is
ever present in the POST body — `ControllerCatalogProduct::bulkUpdate()`
branches on `$field === 'category_ids'` to read the right one.
`MintCart\Catalog\BulkProductUpdateValidator::validateCategoryIds()`/
`normalizeCategoryIds()` are separate methods from `validateValue()` (kept
string-typed for the six scalar fields) rather than widening that
method's signature to accept a list too. An **empty** selection is valid
and means "remove every category from the selected products" — the same
blank-means-clear semantics `special_price` already has for its own
field.

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
- `tests/mintcart/catalog/bulk-product-update-validator-test.php` (57
  scenarios: field whitelist, per-field value validation incl. rejection
  cases for status/quantity/price/manufacturer_id/stock_status_id/
  special_price, `validateCategoryIds()`/`normalizeCategoryIds()`,
  product-id normalization/dedup/dropping non-positive ids) — passing.
- Full `tests/mintcart/**/*-test.php` suite re-run end to end (84 files)
  — all passing, no regressions.
- `product_list.twig`'s `{% if %}`/`{% endif %}`/`{% for %}`/`{% endfor
  %}` tag counts checked balanced (14/14, 2/2) before and after the
  `category_ids` addition (no `{% %}` tag touched, only plain HTML/inline
  `<script>`), same script-based technique used for every other `.twig`
  file in this repo.
- All 3 of the file's inline `<script>` blocks (including the new
  `category_ids` toggle/picker wiring) checked for JS syntax errors with a
  standalone Node harness (Twig `{{ }}` output substituted with a
  placeholder string first, since Node can't parse Twig) — all parse
  cleanly.
- No JS/CSS in `assets/` touched — `npm run build` not re-run (the new
  `category_ids` field consumes the existing compiled
  `assets/dist/{css,js}` bundle unchanged, just newly enqueued on this
  screen).

**Not verified:** no live MySQL/admin session in this environment — a
human should confirm the toolbar renders correctly, the confirm dialog
appears, the redirect preserves filters, and each field actually updates
the right column/table/relation without disturbing anything else (in
particular: confirm a product's descriptions/options/discounts are
unchanged after any bulk update; that a bulk special-price update
correctly replaces — not adds to — any existing `oc_product_special`
row(s); and that a bulk category update correctly replaces — not merges
into — each selected product's existing categories, including confirming
the entity picker actually renders once `components.min.css`/
`admin.min.js` are enqueued on this screen). See
`docs/MANUAL_VERIFICATION.md`'s Phase 16 entry.

## Next step

- SEO fields as further `BulkProductUpdateValidator::FIELDS` entries —
  `ControllerCatalogProduct::validateForm()` enforces per-store/
  per-language `seo_url` uniqueness against the whole table, a different,
  store/language-keyed shape than every field here (a single value applied
  to every selected product) and a real conflict risk across a
  multi-product batch; needs its own design, not a mechanical addition.
- Chunked/resumable AJAX batch jobs (`MINTCART_TECHNICAL_PLAN.md` §29)
  for very large selections — today's synchronous PHP loop mirrors
  Delete/Copy's own existing (also synchronous) behavior, so it isn't a
  new risk this increment introduces, but it doesn't yet have progress
  reporting for a merchant bulk-updating hundreds of products at once.
- A per-field-typed value control for the six scalar fields (see "Value
  input" above — `category_ids` already got its own dedicated control
  this run).
- True inline edit (editing a value directly in the product list table
  without a separate bulk-toolbar step) — a larger UI change.
