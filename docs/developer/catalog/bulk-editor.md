# Bulk Editor — `Catalog → Products`

`MINTCART_FUNCTIONAL_SCOPE.md` §7 ("Inline Edit / Bulk Actions"),
`MINTCART_TECHNICAL_PLAN.md` §29 ("Bulk Editor"),
`MINTCART_IMPLEMENTATION_GUIDE.md` §62.

Mass **status / quantity / price / manufacturer** update across a
multi-selected set of products, reusing the exact `selected[]` checkboxes
`Catalog → Products`' existing Delete/Copy actions already provide.
Category/stock-status/SEO fields are natural follow-ups over the same
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
(34 scenarios)

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
new and deliberately minimal: a single `UPDATE oc_product SET <one
whitelisted column> = ..., date_modified = NOW() WHERE product_id = ...`.
It never touches any other table or column. `$field` must be one of
`MintCart\Catalog\BulkProductUpdateValidator::FIELDS` — an unrecognized
field is a defensive no-op (the method has no request/response of its own
to report an error through; validation is the controller's job, before
this method is ever called).

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

The toolbar renders one generic `bulk_field` `<select>`
(Status/Quantity/Price/Manufacturer) and one generic `bulk_value` text
`<input>` — not
a per-field-typed control (e.g. a `<select>` for Status specifically).
`BulkProductUpdateValidator::validateValue()` still enforces the right
shape server-side (`0`/`1` for status, a non-negative whole number for
quantity, a non-negative number for price, a non-negative whole number —
a manufacturer ID, or `0` for none — for manufacturer) regardless of what
the generic text input lets a merchant type. A friendlier per-field control (swapping
to a `<select>` when `status` is chosen, a `type="number"` input
otherwise) needs a small amount of new JS — deliberately not added this
run to avoid introducing a new inline `<script>` block for a first
increment; a natural follow-up, not a blocker.

Manufacturer is entered as a raw `manufacturer_id` integer, the same way
the other three fields are raw values today — not the `McEntityPicker`
manufacturer backend Phase 12 already ships (`docs/developer/components/entity-picker.md`'s
"Manufacturer backend" section). Wiring that picker in here is the same
"blocked on the admin asset-enqueue problem" gap Phase 12/17/16's own
"Next step" entries already track elsewhere in
`docs/IMPLEMENTATION_STATUS.md`, not a new one this increment introduces.

## Verified

- `php -l` clean on every touched/added PHP file.
- `tests/mintcart/catalog/bulk-product-update-validator-test.php` (34
  scenarios: field whitelist, per-field value validation incl. rejection
  cases for status/quantity/price/manufacturer_id, product-id
  normalization/dedup/dropping non-positive ids) — passing.
- Full `tests/mintcart/**/*-test.php` suite re-run end to end — all
  passing, no regressions.
- `product_list.twig`'s `{% if %}`/`{% endif %}`/`{% for %}`/`{% endfor
  %}` tag counts checked balanced (14/14, 2/2) before and after the
  toolbar addition, same script-based technique used for every other
  `.twig` file in this repo.
- No JS/CSS in `assets/` touched — `npm run build` not re-run for this
  increment.

**Not verified:** no live MySQL/admin session in this environment — a
human should confirm the toolbar renders correctly, the confirm dialog
appears, the redirect preserves filters, and each of the four fields
actually updates the right column without disturbing anything else (in
particular: confirm a product's descriptions/options/discounts/specials
are unchanged after a bulk status/quantity/price/manufacturer update — the
whole point of not using `editProduct()`). See
`docs/MANUAL_VERIFICATION.md`'s Phase 16 entry.

## Next step

- Category, stock-status, and SEO fields as further
  `BulkProductUpdateValidator::FIELDS` entries, same shape.
- Chunked/resumable AJAX batch jobs (`MINTCART_TECHNICAL_PLAN.md` §29)
  for very large selections — today's synchronous PHP loop mirrors
  Delete/Copy's own existing (also synchronous) behavior, so it isn't a
  new risk this increment introduces, but it doesn't yet have progress
  reporting for a merchant bulk-updating hundreds of products at once.
- A per-field-typed value control (see "Value input" above).
- True inline edit (editing a value directly in the product list table
  without a separate bulk-toolbar step) — a larger UI change, likely
  blocked on the same admin asset-enqueue problem every other MintCart
  admin screen since Phase 10 has flagged.
