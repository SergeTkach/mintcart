# Categories drag & drop (Phase 17)

`MINTCART_TECHNICAL_PLAN.md` §30, `MINTCART_IMPLEMENTATION_GUIDE.md` §64:
move a category to a new parent and/or reorder it among its new siblings via
`McSortable` (Phase 11), through SortableJS. The transactional move backend
and the live drag & drop UI wiring (`category_list.twig`) are both done —
see "Live UI wiring" below for what the UI covers and deliberately does
not.

## Files

- `upload/system/library/mintcart/catalog/categorymovevalidator.php` —
  `MintCart\Catalog\CategoryMoveValidator`
- `upload/admin/model/catalog/category.php` — `rebuildCategoryPath()`
  (private, extracted from `editCategory()`) and `moveCategory()`
- `upload/admin/controller/catalog/category.php` —
  `ControllerCatalogCategory::move()`, route `catalog/category/move`
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
POST index.php?route=catalog/category/move&user_token=…
  category_id=25
  parent_id=33
  order[]=25&order[]=40&order[]=12   (the full new sibling list under parent_id 33)
```

Response: `{"success": "..."}` or `{"error": "..."}` (permission denied,
category/parent not found, or the cycle-prevention message).

## Live UI wiring

`ControllerCatalogCategory::getList()` now calls a new
`enqueueMintAssets()` (verbatim copy of
`ControllerCatalogProduct::enqueueMintAssets()` —
`docs/developer/admin/asset-enqueue.md`'s "components" bundle, not
"index", to avoid the Bootstrap 3/5 conflict; not shared via a trait yet,
per `CLAUDE.md` §85/§86 — two call sites isn't a strong enough signal to
extract one, a natural follow-up once a third appears) and passes
`$data['move']` (the `catalog/category/move` URL) and `parent_id` per row
into `category_list.twig`.

The template mounts the **real** `mc-sortable` behavior
(`assets/src/js/components/sortable.js`, auto-initialized by
`admin.min.js` — `docs/developer/components/sortable.md`) directly on the
existing Bootstrap 3 `<table>`/`<tbody>`/`<tr>` markup: `data-mc-sortable`/
`data-mc-sortable-handle`/`data-mc-sortable-item`/`data-mc-action` are
`mc-sortable`'s documented **behavior** hooks (`CLAUDE.md` §14), and don't
require its `mc-sortable`/`mc-sortable__item` **CSS** classes, which are
flex-list-only and would break table-row layout. This page's own existing
Bootstrap 3 table/button classes supply the visuals; the JS behavior
(SortableJS drag + keyboard move-up/move-down buttons +
`mc:sortable:reorder` event) is unchanged from any other `mc-sortable`
consumer.

An inline (non-module, matching every other admin Twig template's
`<script>` convention) listener on the `mc:sortable:reorder` event POSTs
the new order straight to `catalog/category/move` via `fetch` —
immediately, no "Save Order" button/page reload, unlike the Homepage
Builder's block list (which deliberately stayed at a lighter, non-`mc-
sortable` HTML5-drag-plus-manual-save implementation for a different
reason — no bundle was enqueued on that screen at the time; see that
template's own comment). On failure (network error or `{"error": ...}`),
the row order is restored client-side and the reason is announced via a
visually-hidden `aria-live="polite"` region — no page reload either way.

**Resolves the "Architectural fork identified, not resolved (Phase 17)"
note in `docs/IMPLEMENTATION_STATUS.md`'s Phase 12 section** — not by
picking one of its three brainstormed options (a parent-scoped
drill-down/tree view, same-parent-only drag with reject-and-snap-back, or
a "Manage order for: [parent]" filter), but with a fourth, purely
mechanical one that note didn't consider: **decompose the new visible
order back into per-parent groups before saving, instead of changing what
the admin is allowed to drag.**

`category_list.twig` is a flat, paginated table spanning every parent
(stock OpenCart 3 behavior, unmodified — sorted by name or `sort_order`
across the *entire* tree, not grouped by parent), while
`moveCategory()`'s `order[]` contract is sibling-scoped ("the full new
sibling order under `parent_id`", and it blindly applies `sort_order` to
every id given regardless of that id's real parent — see
`moveCategory()`'s "what it does not touch" section above). Naively
sending the *whole* visible page's new order in one request — one flat
list mixing however many different parents happen to share this page —
would let two categories under different parents that simply ended up
adjacent after a drag receive interleaved `sort_order` values relative to
each other's *actual*, off-page siblings, silently scrambling menu/listing
order the admin never saw or intended to touch. That was the real risk
the "not resolved" note flagged, not a missing feature.

The reorder handler in `category_list.twig` avoids it without any UI
redesign: on `mc:sortable:reorder`, it groups the new visible `order[]`
by each row's own `data-mc-category-parent` (read once at page load — no
category is ever re-parented from this screen; `moveCategory()`'s
`parent_id` write always re-targets the same value it already had, a
harmless no-op subtree rebuild), **preserving each group's relative
order** exactly as it appears in the new full sequence, and fires one
`move()` request per group — never mixing one parent's numbering with
another's. A group with only one visible member is **skipped entirely**:
with nothing else from that parent on the page to compare it against,
saving it anyway would reset that one category's `sort_order` to `0` even
though the admin never touched its real siblings (most of which simply
aren't on this page). This means an admin is free to drag *any* row
anywhere on the page — including across what looks like a different
parent's rows — with no rejected drops or snap-back surprises; the save
step is what stays safe, not the drag gesture.

Full drag-to-*re-parent* (moving a category onto a different branch of
the tree) is a separate, materially bigger UI — a nested tree/drill-down
view, one of this note's original three options — and is explicitly left
for a future increment; nothing above attempts it.

**Gated to `sort == 'sort_order'`.** The drag handle, move-up/move-down
buttons, and `data-mc-sortable*` attributes only render when the list is
sorted by the Sort Order column (click the "Sort Order" header first).
When sorted by Name, a plain hint (`text_sort_by_order_first`) points the
admin at that column instead — reordering rows while they're displayed
alphabetically by name would visually scramble that order with no clear
meaning, so this increment does not attempt it.

## Boot timing bug found while verifying this increment

Wiring `mc-sortable`'s real drag behavior into a live admin page (the
first time any `[data-mc-sortable]` markup has actually shipped on a
page that also enqueues `admin.min.js`) surfaced a real, pre-existing,
cross-cutting bug: `enqueueMintAssets()` queues this bundle via
`$this->document->addStyle()`/`addScript()`
(`docs/developer/admin/asset-enqueue.md`), and every admin/storefront
`header.twig` renders `{% for script in scripts %}` **inside `<head>`**
as a plain classic `<script src="...">` — no `defer`, `async`, or
`type="module"`. A classic script like that executes the instant the
HTML parser reaches it, **before `<body>` (and this page's own table)
exists in the DOM at all.**

`assets/src/js/admin.js`/`catalog.js` called every `init*()` at their
module's top level, with no readiness guard. `initAlertDismiss()`'s pure
event-delegation (one `document.addEventListener('click', ...)`, needing
no element to exist yet) happened to work anyway despite this, which is
why the bug went unnoticed — but `initSortableLists()`
(`docs/developer/components/sortable.md`: "Initializes a SortableJS
instance on every `[data-mc-sortable]` container found ... **at call
time**") does an eager `document.querySelectorAll(...)` that would have
found nothing, silently leaving `category_list.twig`'s drag behavior
never mounted. The same shape affects `initModalTriggers()`/
`initDrawerTriggers()`/`initToastTriggers()`/`initTabsTriggers()`'s
relay-listener setup, `initProgressBars()`, and — on the storefront —
`initSearchBoxes()` (the just-wired-in-#138 `mc-search-box`, same eager
`querySelectorAll` shape), whichever of those happen to already be on a
page when its bundle loads.

**Fix (this run):** both `admin.js` and `catalog.js` now wrap their
existing, unmodified `init*()` call sequence in a `boot()` function
deferred with the standard `document.readyState === 'loading'` /
`DOMContentLoaded` guard. Behavior for every existing (delegation-based)
consumer is unchanged; every eager-query consumer listed above now
actually finds its elements. `assets/src/js/admin.js`/`catalog.js` are
MintCart-owned files, not OpenCart core, so this fix has no
`docs/upstream-diff.md` entry — it isn't a modification to stock
OpenCart.

**Verified:** `node --check` on both files; `npm install && npm run build`
compiles both bundles cleanly (`assets/dist/js/admin.min.js`/
`catalog.min.js`, confirmed to contain `DOMContentLoaded`); `npm test`
(preset-token contract checks, unaffected by this change) still passes.
**Not verified live** — same pre-existing no-browser-session gap as
everything else in this repo; a human should confirm on a real install
that (before this fix) a fresh page load genuinely failed to mount
drag/relay/progress behavior and that (after) it does — see
`docs/MANUAL_VERIFICATION.md`'s Phase 17 entries, which already exercise
this path via the Categories drag & drop UI itself.

## Not done yet, by design

- **Re-parenting via drag** — see the "Resolves the Architectural fork..."
  section above; needs a tree-structured list view, a materially larger
  increment.
- **Not atomic across parent groups.** When a single drag/keyboard move
  produces more than one save-worthy parent group (rare — only when the
  admin's one gesture happened to change the relative order of rows from
  two or more different parents at once), each group is saved with its
  own independent `move()` request (`Promise.all`). If one succeeds and
  another fails, the successful one's write is not rolled back even
  though the UI reverts to the pre-drag order and reports failure — a
  human should re-drag to retry rather than assume nothing was saved.
- **No live end-to-end request test.** This environment has no MySQL server,
  so `moveCategory()`/`rebuildCategoryPath()` and the `move()` action were
  never exercised against a real database — the same pre-existing gap noted
  throughout this repo. See `docs/MANUAL_VERIFICATION.md`'s Phase 17 section
  for what a human should confirm.
- Multi-store category visibility, category status, and any other
  `category_list.twig` column are untouched — this increment is scoped to
  the move/reorder write path only.
- `enqueueMintAssets()` is duplicated verbatim on `ControllerCatalogCategory`
  and `ControllerCatalogProduct` — extracting a shared trait is a natural,
  low-risk cleanup once a third admin screen needs the same bundle,
  deliberately not done here (`CLAUDE.md` §85/§86).

## Definition of Done

- [x] Documented contract (this file)
- [x] `MintCart\Catalog` namespace (`CLAUDE.md` §5)
- [x] Cycle-prevention logic unit-tested in isolation
- [x] No OCMOD, no duplicated `category_path` storage (extends the existing
      OpenCart core model/controller per `CLAUDE.md` §39)
- [x] Transactional (`CLAUDE.md` §12 — critical multi-step operations use
      transactions)
- [x] Keyboard-usable, non-mouse drag alternative — `mc-sortable`'s
      `sortable-move-up`/`sortable-move-down` buttons
      (`MINTCART_IMPLEMENTATION_GUIDE.md` §68), rendered per row alongside
      the drag handle.
- [x] Live UI wired (`category_list.twig` + `ControllerCatalogCategory`)
- [ ] Live-verified on a real install — blocked on this environment's
      no-MySQL gap (see above)
