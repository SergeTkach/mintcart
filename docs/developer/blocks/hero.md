# Homepage Builder — Hero block

Phase 10's second first-wave block type (`MINTCART_IMPLEMENTATION_GUIDE.md`
§51), following the exact Provider/Renderer/Template split
`docs/developer/blocks/products.md` established. Unlike Products, Hero needs
no product query — it's a settings-only banner (title, subtitle, optional
CTA, optional background image), so its controller-side piece is much
smaller.

PHP:
- `upload/system/library/mintcart/blocks/heroblockprovider.php` — `MintCart\Blocks\HeroBlockProvider`
- `upload/catalog/controller/common/home.php` — `ControllerCommonHome::renderHeroBlock()`

Twig: `upload/catalog/view/theme/mint_default/template/mint/blocks/hero.twig`

CSS: `.mc-block--hero` in `assets/src/css/components/block.css`

Test: `tests/mintcart/blocks/hero-block-provider-test.php`

## `HeroBlockProvider`

```php
$settings = (new \MintCart\Blocks\HeroBlockProvider())->build(
    $block['settings'], // mc_block.settings_json, decoded: image, image_width, image_height, cta_url
    $block['content']   // mc_block_description.content_json, decoded, current language: title, subtitle, cta_label
);

// $settings = [
//     'title' => string, 'subtitle' => string, 'cta_label' => string,
//     'cta_url' => string, 'image' => ?string,
//     'image_width' => int, 'image_height' => int,
// ]
```

Same "no OpenCart dependency" shape as `ProductsBlockProvider` — it only
normalizes the block row's already-decoded settings/content, it does not
touch the filesystem or resize anything. Defaults: `image_width` 1920,
`image_height` 600 (a widescreen banner, matching a typical full-bleed hero),
every text field `''`, `image` `null` when unset or an empty string. A
non-positive `image_width`/`image_height` falls back to its default, same
"don't propagate garbage" rule `ProductsBlockProvider` applies to `limit`.

## `ControllerCommonHome::renderHeroBlock()` — the real consumer

Added alongside the existing `renderProductsBlock()`, dispatched from the
same `buildMintBlocks()` loop by `$block['type']`:

1. `HeroBlockProvider::build()` normalizes the row's settings/content.
2. If an `image` is set, resolves it through OpenCart's `tool/image` model —
   guarded with `is_file(DIR_IMAGE . $settings['image'])` first, mirroring
   the stock Banner module's own pattern
   (`catalog/controller/extension/module/banner.php`) for a configured image
   path that no longer exists on disk. No image setting → no `is_file`
   call, no `tool/image` model load at all (avoids the load for the common
   text-only hero case).
3. Renders `blocks/hero.twig` via the same `BlockRenderer` `renderProductsBlock()`
   already constructs — no separate renderer needed, `BlockRenderer` is
   generic per block type.
4. Returns `null` (renders nothing) when the block has no title, no
   subtitle, and no resolvable image — an empty hero row degrades silently,
   same as an empty Products query.

## `blocks/hero.twig`

```twig
{% if title or subtitle or image %}
<section class="mc-block mc-block--hero"{% if image %} style="--mc-block-hero-image: url('{{ image }}');"{% endif %}>
    ...
</section>
{% endif %}
```

The background image is set as an inline `--mc-block-hero-image` custom
property, not a `background` shorthand baked into the template —
`MINTCART_IMPLEMENTATION_GUIDE.md` §18's documented exception for
data-driven inline styles (the image is per-instance content, not a theme
style). `.mc-block--hero`'s own CSS composes that variable with a
`--mc-block-hero-overlay` token into a `linear-gradient()` + `background-image`
pair, so a theme override can restyle the overlay/text color without ever
touching how the image URL itself is passed in. The CTA renders only when
**both** `cta_label` and `cta_url` are present — a URL with no label (or
vice versa) is treated as not configured rather than rendering a button
with missing text or a dead link.

## What's still not done

- No admin UI to create/edit a `hero`-type `mc_block` row — same
  "Homepage Builder's drag-and-drop editor doesn't exist yet" gap
  `docs/developer/blocks/products.md` already documents; a row has to be
  inserted by hand to be visible.
- The remaining first-wave block types (Categories, Banner Grid, Benefits,
  Text/Image, Brands, Testimonials, FAQ) have no provider/template yet.
- No live-render verification in this sandbox (no `vendor/twig/twig`, same
  pre-existing gap as every other Twig-touching MintCart increment) — a
  human should confirm on a real install: insert a `mc_page` row
  (`code = 'home'`) and an `mc_block` row (`type = 'hero'`,
  `settings_json = '{"cta_url":"index.php?route=product/special"}'`,
  a `mc_block_description` row with `title`/`subtitle`/`cta_label`) and load
  the homepage. Also confirm a hero with a real `image` setting (an actual
  file under `DIR_IMAGE`) renders the background correctly.
