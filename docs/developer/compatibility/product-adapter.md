# OpenCart 3 Compatibility Adapter — `ProductAdapter`

`MintCart\Compatibility\OpenCart3\ProductAdapter`
(`upload/system/library/mintcart/compatibility/opencart3/productadapter.php`)

`MINTCART_TECHNICAL_PLAN.md` §5 (`MintCart\Compatibility\OpenCart3\*`
adapter layer, Phase 1 foundation), `MINTCART_IMPLEMENTATION_GUIDE.md` §38.

## What this is

The last-but-one `MintCart\Compatibility\OpenCart3\*` adapter from the
named list in `MINTCART_TECHNICAL_PLAN.md` §5 (after `SettingsAdapter`,
`ExtensionAdapter`, `CustomerAdapter`, `CategoryAdapter`, `TotalsAdapter`).
`OrderAdapter` remains the only unstarted one.

Wraps OpenCart 3's product lookup (`getProduct()`) so Mint code doesn't
re-derive the same "empty array means not found" check at every call
site — the same shape `CategoryAdapter::find()`/`exists()` already
documents for categories.

Wired into two real, already-existing controllers in this same increment
(`ControllerMintcartCart::add()`, `ControllerMintcartQuickorder::submit()`)
rather than shipping unwired.

## Why this exists — duplication that was already on `master`

Both OpenCart product models — `catalog/model/catalog/product.php`
(storefront) and `admin/model/catalog/product.php` (admin) — expose
`getProduct(int $product_id): array` with the same shape: a DB row, or an
empty array when nothing matches (OpenCart 3's `\DB\MySQLi` result always
returns `array()` for `->row` on a no-match query, never `null`/`false`).

Two MintCart-owned controllers (`upload/catalog/controller/mintcart/cart.php`,
`upload/catalog/controller/mintcart/quickorder.php`) each had, inline, the
exact same existence check:

```php
$product_info = $this->model_catalog_product->getProduct($product_id);

if (!$product_info) {
    $this->respond(array('error' => $this->language->get('error_product')));
    return;
}
```

The same "extract a real, already-duplicated inline pattern into the
Compatibility layer" shape `CategoryAdapter`/`ExtensionAdapter` already
used — not a hypothetical future need invented ahead of a consumer
(`CLAUDE.md` §85).

## API

```php
$adapter = new \MintCart\Compatibility\OpenCart3\ProductAdapter($this->model_catalog_product);

$adapter->find(42);      // array|null — the product row, or null if not found
$adapter->exists(42);    // bool
```

The constructor is duck-typed, not type-hinted against either concrete
OpenCart model class — there is no `class ModelCatalogProduct` to
`use`/autoload outside a booted OpenCart request, the same reasoning
`CategoryAdapter`/`SettingsAdapter`/`ExtensionAdapter`/`CustomerAdapter`
already document. Any object exposing `getProduct(int): array` works, on
either the storefront or admin side.

## Behavior change

None. `find()`/`exists()` preserve the exact same "empty row means not
found" semantics `getProduct()` already had. Both call sites' `if
(!$product_info) { respond error_product }` branch is unchanged in
behavior — only the lookup line itself was replaced.

## Wired into

- `ControllerMintcartCart::add()` (`upload/catalog/controller/mintcart/cart.php`)
- `ControllerMintcartQuickorder::submit()` (`upload/catalog/controller/mintcart/quickorder.php`)

Both are fully MintCart-owned, fully-additive controllers — no existing
OpenCart core file is touched by this change, so no `docs/upstream-diff.md`
entry is needed.

## Testing

`tests/mintcart/compatibility/opencart3/product-adapter-test.php` — a
standalone smoke test (no PHPUnit/Composer/MySQL available in this
environment, same pre-existing gap as every other test in this repo).
Covers `find()`/`exists()` for a known id, an unknown id, id `0`, and a
model holding multiple products (confirming `find()` returns the correct
row, not just the first one).

Run:

```
php tests/mintcart/compatibility/opencart3/product-adapter-test.php
```

## Not done yet

- No `getProducts()`/listing/search wrapping — only `getProduct()`, the
  one method both real call sites actually needed, is exposed. Per
  `CLAUDE.md` §85, adding more ahead of a real consumer would be
  inventing API. `MintCart\Commerce\ProductViewProvider` (Phase 15) is a
  plausible future consumer for a listing-shaped method, once it needs
  one that isn't already served by `MintCart\Catalog\ProductListingService`.
- `OrderAdapter` remains the last unstarted named adapter from
  `MINTCART_TECHNICAL_PLAN.md` §5.
