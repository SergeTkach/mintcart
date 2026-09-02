# Homepage Builder — Banner Grid block

Phase 10 (`MINTCART_TECHNICAL_PLAN.md` §16-17, `MINTCART_IMPLEMENTATION_GUIDE.md`
§51): the second first-wave block type with a query/provider implementation
(after Products), following the exact Provider/Renderer/Template split
`docs/developer/blocks/products.md` established.

PHP:
- `upload/system/library/mintcart/blocks/bannergridblockprovider.php` — `MintCart\Blocks\BannerGridBlockProvider`
- `upload/catalog/controller/common/home.php` — `ControllerCommonHome::renderBannerGridBlock()`

Twig: `upload/catalog/view/theme/mint_default/template/mint/blocks/banner-grid.twig`

CSS: `assets/src/css/components/banner-grid.css` (`.mc-banner-grid__item`/`__image`/`__caption`)

Tests: `tests/mintcart/blocks/banner-grid-block-provider-test.php`

## Data model

Unlike the Products block (which queries the catalog), a Banner Grid's
content is entirely the block's own settings — a list of items, each an
image + link. Every item is matched between `settings_json` and
`content_json` **by array position**, since an item has no stable ID of its
own — the whole list is replaced wholesale whenever the (not-yet-built)
admin UI saves the block:

```jsonc
// mc_block.settings_json — structural, not localized
{
  "columns": 3,               // 2, 3 or 4 — anything else falls back to 3
  "items": [
    { "image": "catalog/banner/summer.jpg", "url": "index.php?route=product/category&path=20", "image_width": 600, "image_height": 400 }
  ]
}

// mc_block_description.content_json — localized, current language
{
  "title": "Featured collections",
  "items": [
    { "alt": "Summer collection", "caption": "Up to 50% off" }
  ]
}
```

## `BannerGridBlockProvider`

```php
$settings = (new \MintCart\Blocks\BannerGridBlockProvider())->build(
    $block['settings'], // mc_block.settings_json, decoded
    $block['content']   // mc_block_description.content_json, decoded, current language
);

// $settings = [
//     'title' => string,
//     'columns' => int,     // 2-4, default 3
//     'col_width' => int,   // 12/columns — precomputed for the Bootstrap grid, avoids Twig arithmetic
//     'items' => [
//         ['image' => string, 'url' => string, 'image_width' => ?int, 'image_height' => ?int, 'alt' => string, 'caption' => string],
//         ...
//     ],
// ]
```

No OpenCart dependency — same "pure normalization" shape as
`ProductsBlockProvider`. An item with no `image` is dropped, since there is
nothing to render. `columns` outside `{2, 3, 4}` falls back to the default
(3) rather than producing a `col_width` that doesn't divide evenly into
Bootstrap's 12-column grid.

## `ControllerCommonHome::renderBannerGridBlock()`

For each `type = 'banner-grid'` block:

1. `BannerGridBlockProvider::build()` normalizes the row's settings/content.
2. Each item's `image` is resolved through `$this->model_tool_image->resize()`
   — the same `is_file(DIR_IMAGE . ...)`-guarded call the stock Banner
   module (`ModelDesignBanner`) and the Hero block both use. An item whose
   image file no longer exists on disk is silently dropped, not rendered
   as a broken `<img>`. Falls back to 600×400 when an item didn't set its
   own `image_width`/`image_height`.
3. If every item was dropped, the block renders nothing (`null`) —
   `buildMintBlocks()` skips it, same as an empty Products query.
4. Otherwise `BlockRenderer::render('banner-grid', [...])` resolves
   `template/mint/blocks/banner-grid.twig` through the active theme →
   parent chain → Mint Default fallback, same as every other block type.

No `ProductViewProvider`/`ProductCardListRenderer` involved — a banner
item's data all comes from the block's own settings, not the catalog.

## What's still not done

- No admin UI to create/edit/reorder `mc_block` rows yet (same gap as the
  Products block) — a `banner-grid` block currently has to be inserted by
  hand (or a future migration/seed).
- No live-render verification in this sandbox (no `vendor/twig/twig`, same
  pre-existing gap as every other Twig-touching MintCart test) — a human
  should confirm on a real install: insert an `mc_block` row
  (`type = 'banner-grid'`, `settings_json` with at least one item pointing
  at a real file under `DIR_IMAGE`) and load the homepage.
