# Homepage Builder — Gallery block

Phase 10 (`MINTCART_TECHNICAL_PLAN.md` §16-17, `MINTCART_IMPLEMENTATION_GUIDE.md`
§51): the Gallery block, a plain photo grid, following the exact
Provider/Renderer/Template split `docs/developer/blocks/products.md`
established.

PHP:
- `upload/system/library/mintcart/blocks/galleryblockprovider.php` — `MintCart\Blocks\GalleryBlockProvider`
- `upload/catalog/controller/common/home.php` — `ControllerCommonHome::renderGalleryBlock()`

Twig: `upload/catalog/view/theme/mint_default/template/mint/blocks/gallery.twig`

CSS: `assets/src/css/components/gallery.css` (`.mc-gallery__item`/`__image`/`__caption`)

Tests: `tests/mintcart/blocks/gallery-block-provider-test.php`,
plus a 3rd scenario in `tests/mintcart/blocks/block-renderer-test.php`
confirming the real `gallery.twig` resolves.

## Data model

Same as Banner Grid's — a Gallery block's content is entirely the block's
own settings, a list of items matched between `settings_json` and
`content_json` **by array position**, since an item has no stable ID of its
own. Unlike Banner Grid, an item has **no `url`** — a Gallery is a plain
photo grid (store photos, lifestyle imagery), not a set of banner links:

```jsonc
// mc_block.settings_json — structural, not localized
{
  "columns": 4,               // 2, 3, 4 or 6 — anything else falls back to 4
  "items": [
    { "image": "catalog/gallery/storefront.jpg", "image_width": 800, "image_height": 600 }
  ]
}

// mc_block_description.content_json — localized, current language
{
  "title": "Behind the scenes",
  "items": [
    { "alt": "Our storefront", "caption": "Downtown flagship" }
  ]
}
```

## `GalleryBlockProvider`

```php
$settings = (new \MintCart\Blocks\GalleryBlockProvider())->build(
    $block['settings'], // mc_block.settings_json, decoded
    $block['content']   // mc_block_description.content_json, decoded, current language
);

// $settings = [
//     'title' => string,
//     'columns' => int,     // 2, 3, 4 or 6 — default 4
//     'col_width' => int,   // 12/columns — precomputed for the Bootstrap grid, avoids Twig arithmetic
//     'items' => [
//         ['image' => string, 'image_width' => ?int, 'image_height' => ?int, 'alt' => string, 'caption' => string],
//         ...
//     ],
// ]
```

No OpenCart dependency — same "pure normalization" shape as
`ProductsBlockProvider`/`BannerGridBlockProvider`. An item with no `image`
is dropped, since there is nothing to render. `columns` outside
`{2, 3, 4, 6}` falls back to the default (4) rather than producing a
`col_width` that doesn't divide evenly into Bootstrap's 12-column grid —
the same constraint `BannerGridBlockProvider` uses, extended with `6` since
a photo grid commonly wants more, smaller columns than a banner grid does.

## `ControllerCommonHome::renderGalleryBlock()`

For each `type = 'gallery'` block:

1. `GalleryBlockProvider::build()` normalizes the row's settings/content.
2. Each item's `image` is resolved through `$this->model_tool_image->resize()`
   — the same `is_file(DIR_IMAGE . ...)`-guarded call every other
   image-referencing block uses. An item whose image file no longer exists
   on disk is silently dropped, not rendered as a broken `<img>`. Falls
   back to 600×600 (a gallery photo, unlike a banner, has no natural
   wide/landscape default) when an item didn't set its own
   `image_width`/`image_height`.
3. If every item was dropped, the block renders nothing (`null`) —
   `buildMintBlocks()` skips it, same as an empty Products query.
4. Otherwise `BlockRenderer::render('gallery', [...])` resolves
   `template/mint/blocks/gallery.twig` through the active theme → parent
   chain → Mint Default fallback, same as every other block type.

No `ProductViewProvider`/`ProductCardListRenderer` involved — an item's
data all comes from the block's own settings, not the catalog.

## Template

`blocks/gallery.twig` renders each item as a non-interactive `<figure>`
(not the `<a>` Banner Grid uses) with an `<img>` and an optional
`<figcaption>` below it — a gallery photo isn't a link. No JS, no lightbox
— a future increment can add a lightbox/zoom interaction as a separate,
documented enhancement once a real product need for it exists
(`CLAUDE.md` §85: no abstraction before a real consumer).

## What's still not done

- No admin UI to create/edit/reorder `mc_block` rows yet (same gap as
  every other block type) — a `gallery` block currently has to be
  inserted by hand (or a future migration/seed).
- No lightbox/zoom interaction — a static grid only.
- No live-render verification in this sandbox (no `vendor/twig/twig`, same
  pre-existing gap as every other Twig-touching MintCart test) — a human
  should confirm on a real install: insert an `mc_block` row
  (`type = 'gallery'`, `settings_json` with at least one item pointing at
  a real file under `DIR_IMAGE`) and load the homepage.
