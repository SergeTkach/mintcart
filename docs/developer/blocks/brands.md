# Homepage Builder — Brands block

Phase 10 (`MINTCART_TECHNICAL_PLAN.md` §16-17, `MINTCART_IMPLEMENTATION_GUIDE.md`
§51): the **Brands** block, a wrapping strip of brand/manufacturer logo
links, following the exact Provider/Renderer/Template split the Products
block established (see `docs/developer/blocks/products.md` for the pattern
this mirrors).

PHP:
- `upload/system/library/mintcart/blocks/brandsblockprovider.php` — `MintCart\Blocks\BrandsBlockProvider`
- `upload/catalog/controller/common/home.php` — `ControllerCommonHome::renderBrandsBlock()`

Twig: `upload/catalog/view/theme/mint_default/template/mint/blocks/brands.twig`

CSS: `.mc-brands`/`.mc-brand`/`.mc-brand__logo` in `assets/src/css/components/block.css`
(colocated — single consumer so far, `CLAUDE.md` §17: no dedicated file
before a real second consumer).

Tests: `tests/mintcart/blocks/brands-block-provider-test.php`,
Scenario 3 in `tests/mintcart/blocks/block-renderer-test.php`.

## `BrandsBlockProvider`

```php
$settings = (new \MintCart\Blocks\BrandsBlockProvider())->build(
    $block['settings'], // mc_block.settings_json, decoded
    $block['content']   // mc_block_description.content_json, decoded, current language
);

// $settings = [
//     'title' => string,
//     'items' => [
//         ['image' => string, 'url' => string, 'image_width' => int, 'image_height' => int, 'alt' => string],
//         ...
//     ],
// ]
```

Same structural/localized split as `BannerGridBlockProvider`: each item's
`image`/`url`/`image_width`/`image_height` live in `settings_json`
(non-localized), each item's `alt` lives in `content_json` (localized),
matched up by array position — a brand logo item has no stable ID of its
own, same as a Banner Grid item.

Deliberately simpler than `BannerGridBlockProvider`:

- **No `columns` setting.** A Brands block renders as a wrapping logo
  strip (`.mc-brands` is a `flex-wrap` row, see `brands.twig`), not a
  fixed-column grid — the number of logos on a row is left to the
  browser, not constrained to 2-4 columns.
- **No `caption`.** A brand logo is identified by its image/`alt` text
  alone, unlike a Banner Grid item which is a clickable marketing tile
  that typically needs a caption.

Defaults when a setting is missing or invalid: `image_width` 160,
`image_height` 80 (a typical logo-strip size, smaller than Banner Grid's
600×400 marketing-tile default), `url` `''` (renders a non-link `<span>`
wrapper instead of an `<a>` — see the template below), `alt` `''`. An item
with no `image` is dropped during normalization rather than rendered as
an empty `<img>`, same rule `BannerGridBlockProvider` follows.

Deliberately has no OpenCart dependency (no `\Registry`, no `\DB`, no
image model) — same rationale as every other block provider — resizing
each item's `image` via `tool/image` stays the controller's job.

## `ControllerCommonHome::renderBrandsBlock()`

Mirrors `renderBannerGridBlock()`'s shape exactly:

1. `BrandsBlockProvider::build()` normalizes the block row's
   settings/content.
2. Each item's `image` is resolved via
   `$this->model_tool_image->resize($item['image'], $item['image_width'], $item['image_height'])`
   — the same `is_file`-guarded call the stock Banner module and every
   other image-bearing block use. An item whose image file no longer
   exists on disk is dropped rather than rendering a broken `<img>`.
3. If every item's image failed to resolve, `null` is returned — the
   block renders nothing rather than an empty `<section>`.
4. Otherwise `blocks/brands.twig` is rendered through `BlockRenderer`
   with `title` and the resolved `items`.

`buildMintBlocks()`'s dispatch gained an
`elseif ($block['type'] === 'brands')` branch alongside the existing
`'products'` one — see that method's docblock for the full list of
still-not-implemented first-wave block types.

## `brands.twig`

```twig
{% if items %}
<section class="mc-block" data-mc-block-type="brands">
    <div class="container">
        {% if title %}
            <h2 class="mc-block__title">{{ title }}</h2>
        {% endif %}

        <div class="mc-brands">
            {% for item in items %}
                {% if item.url %}
                    <a class="mc-brand" href="{{ item.url }}">
                        <img class="mc-brand__logo" src="{{ item.image }}" alt="{{ item.alt }}" width="{{ item.image_width }}" height="{{ item.image_height }}" loading="lazy">
                    </a>
                {% else %}
                    <span class="mc-brand">
                        <img class="mc-brand__logo" src="{{ item.image }}" alt="{{ item.alt }}" width="{{ item.image_width }}" height="{{ item.image_height }}" loading="lazy">
                    </span>
                {% endif %}
            {% endfor %}
        </div>
    </div>
</section>
{% endif %}
```

An item with no `url` renders inside a plain `<span>` instead of an `<a>`
— a brand block entry doesn't have to link anywhere (e.g. a partner logo
with no landing page yet).

`.mc-brand__logo` renders desaturated (`filter: grayscale(1)`, reduced
opacity) at rest and restores full color on hover/focus — a common
"logo wall" treatment; a theme override can drop this via its own CSS
without touching the Twig markup, since it's pure presentation.

## What's still not done

- No admin UI to create/edit/reorder `mc_page`/`mc_block` rows yet — a
  `'home'` page with a `'brands'` block currently has to be inserted by
  hand to be visible, same gap every other block type carries.
- **Text/Image** is the one remaining unclaimed first-wave block type
  from `MINTCART_IMPLEMENTATION_GUIDE.md` §51 as of this writing — it was
  deliberately skipped in this increment because it likely needs the
  not-yet-built `mc-prose` rich-text rendering
  (`MINTCART_IMPLEMENTATION_GUIDE.md` §20), which has no other consumer
  yet either. Check open PRs first — a Text/Image block or `mc-prose`
  itself may already be in flight.
- No live-render verification in this sandbox (no `vendor/twig/twig`,
  same pre-existing gap as every other Twig-touching MintCart test) — a
  human should confirm on a real install: insert an `mc_block` row
  (`type = 'brands'`) with a couple of items (with and without a `url`)
  on a real install, confirm the homepage renders a logo strip, and that
  a missing logo file degrades gracefully (item dropped, not a broken
  image icon).
