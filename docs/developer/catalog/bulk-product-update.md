# Bulk product update planning (Phase 16)

`MINTCART_TECHNICAL_PLAN.md` §29, `MINTCART_IMPLEMENTATION_GUIDE.md` §62-63,
`MINTCART_FUNCTIONAL_SCOPE.md` §7: inline edit / bulk actions for Products
(status, quantity, price, special, manufacturer, category, stock state),
applied via chunked, resumable AJAX batch jobs with no background daemon.

This increment ships the validation + chunking contract
(`MintCart\Catalog\BulkProductUpdatePlanner`) that a future admin
controller/endpoint calls before and during a bulk edit. It does not yet
write anything to the database - see "Not done yet" below.

## Files

- `upload/system/library/mintcart/catalog/bulkproductupdateplanner.php` -
  `MintCart\Catalog\BulkProductUpdatePlanner`
- Test: `tests/mintcart/catalog/bulk-product-update-planner-test.php`
  (10 scenarios)

## Why "plan", not "apply", in this increment

`ModelCatalogProduct::editProduct($product_id, $data)`
(`upload/admin/model/catalog/product.php`) is a full-replace write: every
call deletes and re-inserts `product_description`, `product_attribute`,
`product_option`, `product_option_value`, `product_recurring`,
`product_discount`, `product_special`, `product_image`,
`product_to_download`, `product_to_category`, `product_filter`, and more -
it expects the *entire* product payload, not a partial "just change the
price" diff. Handing it a bulk-edit request naively would silently wipe out
every product's attributes, options, images, and category assignments that
weren't part of the bulk edit.

A correct bulk-apply step therefore has to, per product: load the existing
full data (the same shape `getProduct()` + its many `getProductXxx()`
sibling methods already assemble for the single-product edit form), merge
in only the requested field changes, then call `editProduct()` with the
merged result. That merge-and-write loop - plus the actual admin
controller route, permission check, and AJAX batch endpoint that drives it
per Technical Plan §29's `check → compatibility → ...`-style flow ("100
items → request → progress → next batch", resumable, no background
daemon) - is real, separate work. This increment ships the piece it needs
to be built against first: a pure, unit-tested contract for *which*
products and *which* field values are even valid to apply, and how the
work is split into resumable batches.

## `BulkProductUpdatePlanner::plan()`

```php
$planner = new \MintCart\Catalog\BulkProductUpdatePlanner();

$plan = $planner->plan(
    $productIds,            // list<int|string>, e.g. checked checkboxes from the product list
    [
        'status' => 1,
        'price' => 19.99,
        'category_ids' => [20, 25],
    ],
    100                      // chunk size, optional (default 100)
);

// $plan['errors']      — list<string>, empty when valid
// $plan['product_ids'] — normalized (deduped, cast to int, non-positive entries dropped)
// $plan['fields']      — sanitized field => value, only the keys that validated
// $plan['total']       — count($plan['product_ids'])
// $plan['chunks']      — precomputed preview: list<list<int>>, e.g. for " this will update
//                         240 products in 3 batches" on a confirmation screen
```

Bulk-editable fields (`FIELD_VALIDATORS`), matching Functional Scope §7's
list minus SEO fields (see below):

| Field | Rule |
|---|---|
| `status` | `0` or `1` |
| `quantity` | integer ≥ 0 |
| `price` | number ≥ 0 |
| `special_price` | number ≥ 0, or `null` to remove the special price |
| `manufacturer_id` | integer ≥ 0 (`0` = no manufacturer) |
| `stock_status_id` | integer ≥ 1 |
| `category_ids` | list of integers ≥ 1 (the full new category set - matches `editProduct()`'s own delete-and-reinsert `product_to_category` semantics, i.e. a *replace*, not an append) |

Every other key in the `$fields` array is rejected as "not a bulk-editable
field" rather than silently ignored, so a typo in an admin form's field
name surfaces immediately instead of quietly doing nothing.

**SEO fields excluded from this increment.** Functional Scope §7 lists SEO
fields "де безпечно" ("where safe"). `ControllerCatalogProduct::validateForm()`
enforces `seo_url` keyword uniqueness per store *and* language against the
whole `seo_url` table - a fundamentally different, store/language-keyed
shape than every other field here (one scalar value applied identically to
every selected product), and a real collision risk the moment two products
in the same batch would end up with the same keyword. Left for a dedicated
follow-up rather than bolted on to this generic scalar-field validator.

## `BulkProductUpdatePlanner::nextBatch()` — the resumable stepping primitive

`plan()`'s `chunks` array is a preview, not something a processing loop
should rely on staying in memory across separate stateless AJAX requests.
The actual batch loop calls `nextBatch()` once per request instead:

```php
// Request 1 (offset defaults to 0 client-side):
$batch = $planner->nextBatch($productIds, 0, 100);
// -> process $batch['product_ids'], report progress, store $batch['next_offset']
//    (e.g. in a hidden form field or query param) for the next request

// Request 2:
$batch = $planner->nextBatch($productIds, $batch['next_offset'], 100);
// ... repeat until $batch['done'] === true
```

This is the same "100 items → request → progress → next batch" shape
Technical Plan §29 and Implementation Guide §63 describe, and mirrors how
`MintCart\Onboarding\WizardProgress` already treats resumability as
"the caller remembers a small cursor, not a live server-side job object" -
no background daemon, no session-bound job state that could get lost.
Calling `nextBatch()` again past the end (e.g. a retried request) returns
an empty batch with `done: true` rather than erroring, so a client retry
after a dropped response is safe.

## Not done yet, by design

- **No DB writes.** Nothing in this class touches `\DB` or any OpenCart
  model. The load-merge-`editProduct()` loop described above is unbuilt.
- **No admin controller/route.** No `catalog/product.bulk` (or similar)
  action exists yet, and no permission check has been wired - that belongs
  in the controller that will call `plan()`/`nextBatch()`.
- **No admin UI.** The product list's multi-select checkboxes and bulk-edit
  form/drawer are unbuilt. Per-row inline edit (a second, complementary UX
  from Functional Scope §7) is also unbuilt. Both would need MintCart's own
  compiled bundle (`assets/dist/{css,js}`) enqueued on an admin page -
  the same "nothing enqueues it on any admin screen yet" gap
  `docs/developer/catalog/category-drag-drop.md` and
  `docs/developer/components/entity-picker.md` already flag for
  `McSortable`/`McEntityPicker` wiring. Whichever run solves that enqueue
  problem unblocks all three at once.
- **SEO fields** - see above; excluded from `FIELD_VALIDATORS` entirely for
  now, not just unimplemented.
- **No live end-to-end test.** This environment has no MySQL server, so
  even once the apply step exists it can only be smoke-tested the same way
  every other phase's DB-touching code has been: unit tests against the
  pure logic, manual verification steps for a human on a real install.

## Definition of Done

- [x] Documented contract (this file)
- [x] `MintCart\Catalog` namespace (`CLAUDE.md` §5)
- [x] Pure, unit-tested in isolation (no OpenCart/`\DB` dependency)
- [x] Chunked + resumable, no background daemon (Technical Plan §29)
- [ ] Apply step (`editProduct()` merge-and-write loop) - follow-up
- [ ] Admin controller/route + permission check - follow-up
- [ ] Admin UI (multi-select + bulk-edit form, or inline edit) - follow-up,
      blocked on the admin asset-enqueue problem for anything beyond a
      plain HTML form
- [ ] Live-verified on a real install - blocked on this environment's
      no-MySQL gap (see above)
