# Categories drag & drop (Phase 17)

`MINTCART_TECHNICAL_PLAN.md` §30, `MINTCART_IMPLEMENTATION_GUIDE.md` §64:
move a category to a new parent and/or reorder it among its new siblings via
`McSortable` (Phase 11), through SortableJS. This increment ships the
transactional move backend; live drag & drop UI wiring is a follow-up (see
"Not done yet" below).

## Files

- `upload/system/library/mintcart/catalog/categorymovevalidator.php` —
  `MintCart\Catalog\CategoryMoveValidator`
- `upload/admin/model/catalog/category.php` — `rebuildCategoryPath()`
  (private, extracted from `editCategory()`) and `moveCategory()`
- `upload/admin/controller/catalog/category.php` —
  `ControllerCatalogCategory::move()`, route `catalog/category.move`
- `upload/admin/language/en-gb/catalog/category.php` — `error_category`
- Test: `tests/mintcart/catalog/category-move-validator-test.php`

## The documented flow, and where each step lives

Technical Plan §30's move operation:

```text
begin transaction
validate target
prevent cycle
change parent
rebuild affected category_path subtree
update sort order
commit
```

| Step | Where |
|---|---|
| begin transaction / commit | `ModelCatalogCategory::moveCategory()` (`START TRANSACTION`/`COMMIT`/`ROLLBACK`, the same raw-SQL pattern `MintCart\Core\Migration\MigrationManager` already uses — OpenCart 3's `\DB\MySQLi` has no `beginTransaction()`/`commit()` wrapper) |
| validate target (existence) | `ControllerCatalogCategory::move()`, via the existing `getCategory()` |
| prevent cycle | `MintCart\Catalog\CategoryMoveValidator::validate()`, fed by the existing `getCategoryPath($parent_id)` |
| change parent | `moveCategory()`'s `UPDATE category SET parent_id = ...` |
| rebuild affected category_path subtree | `moveCategory()` calls the (private) `rebuildCategoryPath()` |
| update sort order | `moveCategory()`'s `$sort_order` loop |

## `MintCart\Catalog\CategoryMoveValidator` — cycle prevention is not new logic

`ControllerCatalogCategory::validateForm()` (the existing add/edit form)
already rejects a parent choice that would create a cycle: it fetches
`getCategoryPath($parent_id)` — every ancestor of the chosen parent,
including itself, from the `category_path` closure table — and checks
whether the category being edited appears in that list (`error_parent`,
*"The parent category you have chosen is a child of the current one!"*).

`CategoryMoveValidator` extracts exactly that check into a small,
OpenCart-independent, unit-tested class so the new drag & drop `move()`
action reuses the identical rule instead of re-deriving it:

```php
$validator = new \MintCart\Catalog\CategoryMoveValidator();

$error = $validator->validate($categoryId, $newParentId, $newParentAncestorPathIds);
// $error === null means the move is valid.
```

`$newParentAncestorPathIds` is the list of `path_id` values from
`getCategoryPath($newParentId)` — pass `[]` when moving to top-level
(`parent_id = 0`, always valid). Two rejected cases: `$newParentId ===
$categoryId` (a category can't become its own parent), and `$categoryId`
appearing anywhere in the new parent's ancestor chain (direct or indirect
— a grandchild move is rejected exactly like a direct-child move, since the
full chain is checked, not just the immediate parent).

## `ModelCatalogCategory::rebuildCategoryPath()` — extracted, not rewritten

`editCategory()`'s own inline "MySQL Hierarchical Data Closure Table
Pattern" block (delete-below, fetch new parent's path, fetch what's left of
the node's current path, combine with a new `level`, `REPLACE INTO`) is
**moved verbatim** into a new private `rebuildCategoryPath($category_id,
$parent_id)` method — same SQL, same order of operations, so
`editCategory()`'s own behavior is unchanged. `moveCategory()` calls the
same method. This is why no new closure-table logic needed inventing or
testing from scratch: it is the same algorithm `addCategory()`/
`editCategory()` have relied on since the OpenCart 3.0.5.1 baseline,
applied to a lighter-weight caller.

## `ModelCatalogCategory::moveCategory()` — what it does not touch

Unlike `editCategory()`, `moveCategory($category_id, $parent_id, $sort_order
= [])` only ever writes `category.parent_id`, `category.sort_order`, and
`category_path` rows. It never rewrites `category_description`,
`category_filter`, `category_to_store`, `seo_url`, or `category_to_layout` —
a drag & drop move changes where a category sits in the tree, not its
content, so there is no reason to pay the cost (or carry the risk) of
`editCategory()`'s full description-delete-and-reinsert cycle for a pure
structural move.

`$sort_order` is the full new sibling order under `$parent_id` — an ordered
list of category_ids, the moved category included — each array position
becomes that category's `sort_order`. Pass `[]` to change only the parent,
leaving every sibling's `sort_order` untouched.

## `ControllerCatalogCategory::move()` — orchestration only

Per `CLAUDE.md` §8A ("Controller = orchestration, Reusable Service/Action =
shared logic"): permission check, `category_id`/`parent_id` existence via
the existing `getCategory()`, cycle-prevention via
`CategoryMoveValidator` + the existing `getCategoryPath()`, then
`moveCategory()`. No new business logic lives in the controller itself.

```
POST index.php?route=catalog/category.move&user_token=…
  category_id=25
  parent_id=33
  order[]=25&order[]=40&order[]=12   (the full new sibling list under parent_id 33)
```

Response: `{"success": "..."}` or `{"error": "..."}` (permission denied,
category/parent not found, or the cycle-prevention message).

## Not done yet, by design

- **No live UI.** `admin/view/template/catalog/category_list.twig` does not
  call `McSortable` (`createEntityPicker`'s admin-only sibling component,
  Phase 11) yet, and nothing POSTs to `catalog/category.move`. Wiring that
  in needs MintCart's own compiled bundle (`assets/dist/{css,js}`) enqueued
  on an admin page — which, as of this run, **nothing enqueues on any admin
  screen yet** (the same blocker `docs/developer/components/entity-picker.md`
  "Admin backends" flagged for wiring `McEntityPicker` into
  `product_form.twig`'s Category field). Shipping unverifiable, possibly-
  broken drag markup on a real admin screen would be worse than shipping a
  tested, additive backend and leaving the UI for once that enqueue problem
  is solved.
- **No live end-to-end request test.** This environment has no MySQL server,
  so `moveCategory()`/`rebuildCategoryPath()` and the `move()` action were
  never exercised against a real database — the same pre-existing gap noted
  throughout this repo. See `docs/MANUAL_VERIFICATION.md`'s Phase 17 section
  for what a human should confirm.
- Multi-store category visibility, category status, and any other
  `category_list.twig` column are untouched — this increment is scoped to
  the move/reorder write path only.

## Definition of Done

- [x] Documented contract (this file)
- [x] `MintCart\Catalog` namespace (`CLAUDE.md` §5)
- [x] Cycle-prevention logic unit-tested in isolation
- [x] No OCMOD, no duplicated `category_path` storage (extends the existing
      OpenCart core model/controller per `CLAUDE.md` §39)
- [x] Transactional (`CLAUDE.md` §12 — critical multi-step operations use
      transactions)
- [ ] Keyboard-usable, non-mouse drag alternative — deferred to the live UI
      wiring step; `McSortable` (Phase 11) already ships
      `sortable-move-up`/`sortable-move-down` buttons for this
      (`MINTCART_IMPLEMENTATION_GUIDE.md` §68), so the requirement is
      already met by the component this feature will mount once wired.
- [ ] Live-verified on a real install — blocked on this environment's
      no-MySQL gap (see above)
