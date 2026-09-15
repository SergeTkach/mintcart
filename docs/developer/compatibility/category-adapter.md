# OpenCart 3 Compatibility Adapter — `CategoryAdapter`

`MintCart\Compatibility\OpenCart3\CategoryAdapter`
(`upload/system/library/mintcart/compatibility/opencart3/categoryadapter.php`)

`MINTCART_TECHNICAL_PLAN.md` §5 (`MintCart\Compatibility\OpenCart3\*`
adapter layer, Phase 1 foundation), `MINTCART_IMPLEMENTATION_GUIDE.md` §38.

## What this is

The fifth `MintCart\Compatibility\OpenCart3\*` adapter (after
`SettingsAdapter`, `ExtensionAdapter` and `CustomerAdapter` — all open PRs
at the time of writing). Wraps OpenCart 3's category lookup
(`getCategory()`) and ancestor-chain lookup (`getCategoryPath()`) so Mint
code doesn't re-derive the same "empty array means not found" and
"flatten these rows into a `path_id` list" logic at every call site.

Unlike `SettingsAdapter`/`ExtensionAdapter`/`CustomerAdapter`, this one
**is wired into a real, already-existing controller in this same
increment** (`ControllerCatalogCategory::move()`) rather than shipping
unwired — see "Why this one is wired in immediately" below.

## Why this exists — duplication that was already on `master`

OpenCart ships two separate category model classes:

- `admin/model/catalog/category.php` — used by admin screens, including
  the Phase 17 category drag & drop `move()` action
  (`docs/developer/catalog/category-drag-drop.md`) and the category
  Entity Picker backend.
- `catalog/model/catalog/category.php` — used by the storefront.

Both expose `getCategory(int $category_id): array` with the same shape (a
DB row, or an empty array when nothing matches — OpenCart 3's
`\DB\MySQLi` result always returns `array()` for `->row` on a no-match
query, never `null`/`false`). Only the admin model additionally exposes
`getCategoryPath(int $category_id): array`, the `category_path`
closure-table ancestor-chain lookup.

`ControllerCatalogCategory::move()` already had, inline, exactly the
duplication this adapter removes:

```php
if (!$category_id || !$this->model_catalog_category->getCategory($category_id)) {
    $json['error'] = $this->language->get('error_category');
} elseif ($parent_id && !$this->model_catalog_category->getCategory($parent_id)) {
    $json['error'] = $this->language->get('error_category');
}
// ...
$ancestor_path_ids = array();
if ($parent_id) {
    foreach ($this->model_catalog_category->getCategoryPath($parent_id) as $result) {
        $ancestor_path_ids[] = $result['path_id'];
    }
}
```

This is the same "extract a real, already-duplicated inline pattern into
the Compatibility layer" shape `ExtensionAdapter` used for
`ModelMintcartReadiness::hasEnabledExtension()` — not a hypothetical
future need invented ahead of a consumer (`CLAUDE.md` §85).

## API

```php
$adapter = new \MintCart\Compatibility\OpenCart3\CategoryAdapter($this->model_catalog_category);

$adapter->find(5);                  // array|null — the category row, or null if not found
$adapter->exists(5);                // bool
$adapter->getAncestorPathIds(2);    // list<int> — every path_id from getCategoryPath(2), including itself
```

The constructor is duck-typed, not type-hinted against either concrete
OpenCart model class — there is no `class ModelCatalogCategory` to
`use`/autoload outside a booted OpenCart request, the same reasoning
`SettingsAdapter`/`ExtensionAdapter`/`CustomerAdapter` already document.
Any object exposing `getCategory(int): array` works for `find()`/
`exists()`; `getAncestorPathIds()` additionally needs
`getCategoryPath(int): array`, which only the **admin** model declares —
calling it against a storefront-shaped double throws a normal PHP
`Error`, the same as calling any other undeclared method. No fallback is
added for that case: inventing one would hide a real caller mistake
(passing the wrong model) instead of surfacing it.

## Why this one is wired in immediately

`SettingsAdapter`/`ExtensionAdapter`/`CustomerAdapter` each either had no
real call site yet, or had one living in a file already contended by
other open PRs at the time. Neither applies here:

- The one real call site (`ControllerCatalogCategory::move()`) already
  exists on `master` (Phase 17, merged).
- `upload/admin/controller/catalog/category.php` is not touched by any
  other currently open pull request (checked each open PR's title against
  this file/feature area before starting; none target category move/drag
  & drop).

So, unlike the other three adapters, wiring it in now removes real
duplication today instead of leaving it as a documented follow-up.

## Behavior change

None. `find()`/`exists()` preserve the exact same "empty row means not
found" semantics `getCategory()` already had;
`getAncestorPathIds($parent_id)` returns the exact same list
`$ancestor_path_ids` was built into before (same order, same values,
`(int)`-cast the same way the original loop implicitly relied on via
`CategoryMoveValidator`'s own `(int) $pathId` comparison). The `if
($parent_id)` guard moved from wrapping the loop to a ternary around the
adapter call — same net effect: `parent_id = 0` (top-level) still yields
`[]` without calling `getAncestorPathIds()` at all.

## Testing

`tests/mintcart/compatibility/opencart3/category-adapter-test.php` — a
standalone smoke test (no PHPUnit/Composer/MySQL available in this
environment, same pre-existing gap as every other test in this repo).
Covers `find()`/`exists()` for a known and unknown id, `getAncestorPathIds()`
flattening a multi-level chain in order, an empty list when the model has
no path rows, and `path_id` values arriving as numeric strings (a raw,
un-cast DB row) still normalizing to real ints — `CategoryMoveValidator`'s
own cycle check relies on strict (`===`) int comparison, so a silent
string/int mismatch here would have reintroduced a bug this adapter
exists to prevent, not just relocate.

Run:

```
php tests/mintcart/compatibility/opencart3/category-adapter-test.php
```

## Not done yet

- No `add`/`edit`/`delete`/`getCategories()` wrapping — only the two
  methods `move()` actually needed are exposed; per `CLAUDE.md` §85,
  adding the rest ahead of a real consumer would be inventing API. The
  category Entity Picker backend (`docs/developer/theme-api/entity-picker.md`
  if/when it exists) is the likely next real consumer for a `search()`-
  style method, once it needs one.
- `ProductAdapter` and `OrderAdapter` remain the last two unstarted named
  adapters from `MINTCART_TECHNICAL_PLAN.md` §5.
