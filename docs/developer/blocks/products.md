# Homepage Builder — Block Provider / Renderer / Template (Products block)

Phase 10 (`MINTCART_TECHNICAL_PLAN.md` §16-17, `MINTCART_IMPLEMENTATION_GUIDE.md`
§32-33): the first real Block Provider/Renderer/Template split, using the
Products block as the reference implementation — it reuses the
`ProductViewProvider`/`ProductCardListRenderer` pipeline already proven by
Category/Search/Special/Manufacturer (`docs/developer/theme-api/product-card-list-renderer.md`)
instead of inventing new product-shape logic.

PHP:
- `upload/system/library/mintcart/blocks/blockrenderer.php` — `MintCart\Blocks\BlockRenderer`
- `upload/system/library/mintcart/blocks/productsblockprovider.php` — `MintCart\Blocks\ProductsBlockProvider`
- `upload/catalog/model/mintcart/block.php` — `ModelMintcartBlock` (reads `mc_page`/`mc_block`/`mc_block_description`)
- `upload/catalog/controller/common/home.php` — `ControllerCommonHome` (the one real consumer)

Twig: `upload/catalog/view/theme/mint_default/template/mint/blocks/products.twig`,
`upload/catalog/view/theme/mint_default/template/common/home.twig`

Tests: `tests/mintcart/blocks/block-renderer-test.php`,
`tests/mintcart/blocks/products-block-provider-test.php`

## `BlockRenderer`

```php
$blockRenderer = new \MintCart\Blocks\BlockRenderer(DIR_TEMPLATE, $mintTheme);

$markup = $blockRenderer->render('products', [
    'title' => 'Latest Products',
    'product_cards' => $productCards, // already-rendered product-card markup
]);
```

Resolves `template/mint/blocks/<type>.twig` through the exact same active
theme → `theme.json` parent chain → Mint Default fallback
`MintCart\Design\ComponentRenderer` already implements and has tested
(`docs/developer/theme-api/component-renderer.md`) — `BlockRenderer` is a
thin wrapper that constructs a `ComponentRenderer` with
`$kind = 'blocks'` rather than duplicating that walk a second time.
`ComponentRenderer`'s constructor gained this optional 3rd `$kind`
parameter (default `'components'`, fully backward compatible — every
existing call site passes only 2 arguments) specifically to make this
reuse possible.

## `ProductsBlockProvider`

```php
$settings = (new \MintCart\Blocks\ProductsBlockProvider())->build(
    $block['settings'], // mc_block.settings_json, decoded
    $block['content']   // mc_block_description.content_json, decoded, current language
);

// $settings = ['title' => string, 'category_id' => ?int, 'limit' => int, 'sort' => string, 'order' => string]
```

Normalizes a raw block row's settings only — it does **not** query
products itself (no `\Registry`, no `\DB`, no product model), same
"no OpenCart dependency" rationale as `ProductViewProvider`/
`FooterViewProvider`. Defaults when a setting is missing or invalid:
`limit` 8, `sort` `p.date_added`, `order` `DESC`, `category_id` `null`
(no filter — queries across the whole catalog). `category_id: '0'` and a
non-positive `limit` both fall back to their defaults rather than
propagating garbage into the query a controller builds from this output.

## `ModelMintcartBlock`

Plain OpenCart model (not `MintCart\*` namespaced — same convention every
other `upload/catalog/model/**` file follows):

```php
$this->load->model('mintcart/block');

$page = $this->model_mintcart_block->getPage('home'); // null if missing/disabled
$blocks = $this->model_mintcart_block->getBlocks($page['page_id'], $languageId);
// [['block_id', 'type', 'sort_order', 'settings' => [...], 'content' => [...]], ...]
```

`getBlocks()` only returns `status = 1` rows, ordered by `sort_order`.
`settings_json`/`content_json` are decoded here so every consumer gets a
plain array, not a raw JSON string. A block with no matching
`mc_block_description` row for the requested language still comes back,
with `content` as an empty array — a missing translation degrades to a
blank title rather than hiding the block.

## `ControllerCommonHome` — the real consumer

`upload/catalog/controller/common/home.php` (an existing OpenCart core
file — this is a direct modification, see `docs/upstream-diff.md`'s entry)
gained a `buildMintBlocks()` stage:

1. Same "is the active theme Mint-API-aware" gate every other Mint
   pipeline uses (`MintCart\Design\ActiveTheme::isMintAware`) — a store on
   the legacy `default` theme, or an unrelated third-party theme, never
   queries `mc_page`/`mc_block` at all.
2. Loads the `'home'` `mc_page` row. No row (or `status = 0`) → no blocks,
   not an error — expected on a fresh install before Homepage Builder's
   admin UI exists to create one.
3. Loads its enabled blocks, in `sort_order`.
4. For each `type = 'products'` block: `ProductsBlockProvider::build()`
   normalizes its settings, `$this->model_catalog_product->getProducts()`
   queries with the resulting `sort`/`order`/`limit`/optional
   `filter_category_id` (the exact same filter keys
   `ControllerProductCategory` already uses), then each result is mapped
   into the same `product-card` input shape and rendered through
   `ProductCardListRenderer` that Category/Search/Special/Manufacturer
   already use — the per-product image/price/special/tax computation is a
   direct copy of `ControllerProductCategory::index()`'s own mapping, the
   same established per-controller duplication every listing controller
   currently carries (a shared `ProductListingService`,
   `MINTCART_TECHNICAL_PLAN.md` §15C, is still not built — no abstraction
   before enough real consumers justify it, `CLAUDE.md` §85).
5. Any other block `type` is skipped, not fatal — the other first-wave
   block types (Hero, Categories, Benefits, Text/Image, Brands,
   Testimonials, FAQ — `MINTCART_IMPLEMENTATION_GUIDE.md` §51) are
   separate, not-yet-started checklist items. `banner-grid` is now
   implemented too — see `docs/developer/blocks/banner-grid.md`.
6. Rendered block markup is passed to the view as `mc_blocks` (a plain
   list of already-rendered HTML strings, same shape as `product_cards`).

`upload/catalog/view/theme/mint_default/template/common/home.twig` (new —
`mint_default` did not previously override `common/home.twig`, it fell
through to the legacy `default` theme's copy, which knows nothing about
`mc_blocks`) loops `mc_blocks` full-width, above the legacy
`column_left`/`content_top`/`content_bottom`/`column_right`/`column_right`
layout, which is left completely intact.

A `products` block instance with no matching products (empty query
result, or every product's `product-card` render failed) renders nothing
— `renderProductsBlock()` returns `null`, `buildMintBlocks()` skips it —
never an empty `<section>`.

## What's still not done

- No admin UI to create/edit/reorder `mc_page`/`mc_block` rows yet
  (Homepage Builder's drag-and-drop editor, `MINTCART_TECHNICAL_PLAN.md`
  §17's last unchecked item) — a `'home'` page with a `'products'` block
  currently has to be inserted by hand (or a future migration/seed) to be
  visible at all.
- Hero, Categories, Benefits, Text/Image, Brands, Testimonials, FAQ block
  types have no provider/template yet (`banner-grid` is done — see
  `docs/developer/blocks/banner-grid.md`).
- No live-render verification in this sandbox (no `vendor/twig/twig`,
  same pre-existing gap as every other Twig-touching MintCart test) — a
  human should confirm on a real install: insert a `mc_page` row
  (`code = 'home'`) and an `mc_block` row (`type = 'products'`,
  `settings_json = '{}'`) and load the homepage.
