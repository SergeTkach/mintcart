# Homepage Builder — Categories block

Phase 10 (`MINTCART_TECHNICAL_PLAN.md` §16-17, `MINTCART_IMPLEMENTATION_GUIDE.md`
§32-33, §51): the second first-wave block type after Products/Hero, following
the same Provider/Renderer/Template split. It reuses OpenCart's own
`ModelCatalogCategory::getCategories()` — the same model `common/menu` and
`extension/module/category` already query — instead of inventing new
category-listing logic.

PHP:
- `upload/system/library/mintcart/blocks/categoriesblockprovider.php` — `MintCart\Blocks\CategoriesBlockProvider`
- `upload/catalog/controller/common/home.php` — `ControllerCommonHome::renderCategoriesBlock()`

Twig: `upload/catalog/view/theme/mint_default/template/mint/blocks/categories.twig`

CSS: `.mc-category-card` in `assets/src/css/components/block.css` (single
consumer so far — see that file's docblock for why it isn't its own
`components/*.css` file, per `CLAUDE.md` §85).

Tests: `tests/mintcart/blocks/categories-block-provider-test.php`,
`tests/mintcart/blocks/block-renderer-test.php` (Scenario 3).

## `CategoriesBlockProvider`

```php
$settings = (new \MintCart\Blocks\CategoriesBlockProvider())->build(
    $block['settings'], // mc_block.settings_json, decoded
    $block['content']   // mc_block_description.content_json, decoded, current language
);

// $settings = ['title' => string, 'parent_id' => int, 'limit' => int]
```

Normalizes a raw block row's settings only — it does **not** query
categories itself (no `\Registry`, no `\DB`, no category model), same
"no OpenCart dependency" rationale as `ProductsBlockProvider`. Defaults:
`limit` 6, `parent_id` 0 (top-level categories). `parent_id: '-3'` and a
non-positive `limit` both fall back to their defaults rather than
propagating garbage into the query a controller builds from this output.

Unlike `ProductsBlockProvider`, there is no `sort`/`order` setting —
`ModelCatalogCategory::getCategories()` has a fixed `ORDER BY
c.sort_order, LCASE(cd.name)`, the same order every other core category
listing (menu, category module) already relies on, so the provider has
nothing to normalize there.

## `ControllerCommonHome::renderCategoriesBlock()` — the real consumer

`buildMintBlocks()`'s per-block dispatch (`upload/catalog/controller/common/home.php`)
was reshaped from a single `if('products')` into `if`/`elseif`/`else` so
adding a block type is one more branch, not a duplicated append-check:

```php
if ($block['type'] === 'products') {
    $markup = $this->renderProductsBlock($block, $block_renderer, $mint_theme);
} elseif ($block['type'] === 'categories') {
    $markup = $this->renderCategoriesBlock($block, $block_renderer);
} else {
    $markup = null;
}
```

`renderCategoriesBlock()`:

1. `CategoriesBlockProvider::build()` normalizes the block's settings.
2. `$this->model_catalog_category->getCategories($settings['parent_id'])`
   queries children of that parent (0 = top-level), then the result is
   truncated to `$settings['limit']` with `array_slice()` — the model
   itself has no `LIMIT` parameter, same as `common/menu`'s own usage.
3. Each category's link path follows the exact convention
   `common/menu.php`/`extension/module/category.php` already use: just
   the category's own `category_id` at the top level, or
   `<parent_id>_<category_id>` one level down — not a new URL scheme.
4. Each category's image is resized via `tool/image` using the existing
   `theme_<theme>_image_category_width/height` config keys (the same
   ones `ControllerProductCategory` uses for its own category banner). No
   image → empty string, matching `ControllerProductCategory::index()`'s
   own `$data['thumb'] = ''` fallback (categories are commonly image-less,
   unlike products, so there is no placeholder image substitution here).
5. Renders `blocks/categories.twig` through `BlockRenderer`.

A `categories` block instance with no matching categories for the
configured `parent_id` renders nothing — `renderCategoriesBlock()` returns
`null`, `buildMintBlocks()` skips it — never an empty `<section>`. Same
behaviour as the Products block.

## What's still not done

- No admin UI to create/edit/reorder `mc_page`/`mc_block` rows yet — a
  `'home'` page with a `'categories'` block has to be inserted by hand
  (`INSERT INTO mc_block (page_id, type, sort_order, status, settings_json) VALUES (<page_id>, 'categories', <n>, 1, '{}')`)
  to be visible on a real install.
- No live MySQL/`vendor/twig/twig` in this environment, so no live
  end-to-end request was exercised — a human should insert a `'home'`
  `mc_page` row and a `'categories'` `mc_block` row on a real install and
  confirm the homepage renders a Categories grid, both with and without a
  `parent_id` setting.
- Remaining first-wave block types per `MINTCART_IMPLEMENTATION_GUIDE.md`
  §51: Banner Grid, Benefits, Text/Image, Brands, Testimonials, FAQ.
