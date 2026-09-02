# Homepage Builder — Benefits block

Phase 10 (`MINTCART_TECHNICAL_PLAN.md` §16-17, `MINTCART_IMPLEMENTATION_GUIDE.md`
§32-33): the Benefits block, a pure content block — a row of trust-badge
style items ("Free shipping", "Secure payment", "24/7 support", ...),
following the exact Provider/Renderer/Template split the Products block
established (`docs/developer/blocks/products.md`).

PHP:
- `upload/system/library/mintcart/blocks/benefitsblockprovider.php` — `MintCart\Blocks\BenefitsBlockProvider`
- `upload/catalog/controller/common/home.php` — `ControllerCommonHome::renderBenefitsBlock()`

Twig: `upload/catalog/view/theme/mint_default/template/mint/blocks/benefits.twig`

CSS: `.mc-benefit`/`__icon`/`__title`/`__description` in
`assets/src/css/components/block.css` (colocated with `.mc-block` — single
consumer so far, `CLAUDE.md` §17).

Tests: `tests/mintcart/blocks/benefits-block-provider-test.php`, plus a
third scenario added to `tests/mintcart/blocks/block-renderer-test.php`
confirming the real `benefits.twig` resolves.

## Why Benefits has no query, no image resize, no OpenCart model

Unlike Products (queries products), Hero/Banner Grid (resize an optional
image), or Categories (queries `ModelCatalogCategory`), Benefits is pure
localized text plus a structural icon key — there is nothing to fetch.
`BenefitsBlockProvider` has the same "no OpenCart dependency" shape as
`ProductsBlockProvider`, and `renderBenefitsBlock()` in the controller is
correspondingly the simplest of the block render methods: normalize, then
render.

## `BenefitsBlockProvider`

```php
$settings = (new \MintCart\Blocks\BenefitsBlockProvider())->build(
    $block['settings'], // mc_block.settings_json, decoded — items[].icon
    $block['content']   // mc_block_description.content_json, decoded, current language — title, items[].title/description
);

// $settings = [
//     'title' => string,
//     'items' => [['icon' => string, 'title' => string, 'description' => string], ...],
// ]
```

`items[].icon` is structural (non-localized) and lives in `settings_json`;
`items[].title`/`items[].description` are localized and live in
`content_json` — matched up by array position, the same settings/content
item split every other multi-item block provider in this codebase uses
(e.g. Banner Grid's items). Example stored rows:

```json
// mc_block.settings_json
{"items": [{"icon": "truck"}, {"icon": "shield-check"}, {"icon": "headset"}]}

// mc_block_description.content_json (per language)
{
  "title": "Why shop with us",
  "items": [
    {"title": "Free shipping", "description": "On all orders over $50"},
    {"title": "Secure payment", "description": ""},
    {"title": "24/7 support", "description": "We're here to help"}
  ]
}
```

Rules `build()` enforces:
- An item whose content-side `title` is blank/missing is skipped entirely
  — a settings-only stray entry (e.g. an icon with no matching content
  item) never renders an empty card.
- `icon` falls back to `'check'` when the matched settings item has no
  `icon` string set.
- `description` defaults to an empty string (the template hides the
  `<p>` entirely when it's empty).
- Items are matched by position across `settings['items']` and
  `content['items']` independently of each array's own length — the
  longer of the two determines how many positions are considered.

## `ControllerCommonHome::renderBenefitsBlock()`

Added as a new dispatch branch in `buildMintBlocks()`'s per-block-type
`if`/`elseif` chain (alongside the existing `'products'` branch):

```php
if ($block['type'] === 'products') {
    $markup = $this->renderProductsBlock($block, $block_renderer, $mint_theme);
} elseif ($block['type'] === 'benefits') {
    $markup = $this->renderBenefitsBlock($block, $block_renderer);
} else {
    $markup = null;
}
```

`renderBenefitsBlock()` itself:

```php
private function renderBenefitsBlock(array $block, \MintCart\Blocks\BlockRenderer $block_renderer) {
    $settings = (new \MintCart\Blocks\BenefitsBlockProvider())->build($block['settings'], $block['content']);

    if (!$settings['items']) {
        return null;
    }

    return $block_renderer->render('benefits', $settings);
}
```

A block with no items (e.g. a freshly-created row with no content filled
in yet) renders nothing — never an empty `<section>` — matching
`renderProductsBlock()`'s existing "skip empty blocks" contract.

## Icon rendering — placeholder, not final

`benefits.twig` renders each item's `icon` key as a `data-mc-icon`
attribute on an empty `<span>`, with a comment marking where
`{{ mc_icon(item.icon) }}` belongs once the Bootstrap Icons SVG renderer
(`MINTCART_IMPLEMENTATION_GUIDE.md` §21) exists — the exact same
placeholder pattern `mint_default/template/mint/components/footer.twig`
already uses for its social links. No Twig function or icon sprite exists
in this repo yet; this block does not attempt to invent one.

## What's still not done

- No admin UI to create/edit/reorder `mc_page`/`mc_block` rows yet — a
  `'home'` page with a `'benefits'` block currently has to be inserted by
  hand to be visible at all (same gap every other block type has).
- The remaining first-wave block types (Hero, Categories, Banner Grid,
  Text/Image, Brands, Testimonials, FAQ —
  `MINTCART_IMPLEMENTATION_GUIDE.md` §51) have no provider/template yet.
- `mc_icon()` itself does not exist yet — every icon-bearing MintCart
  component (this block, `footer.twig`'s social links) is carrying the
  same placeholder pending that renderer.
- No live-render verification in this sandbox (no `vendor/twig/twig`,
  same pre-existing gap as every other Twig-touching MintCart test) — a
  human should confirm on a real install: insert a `mc_block` row
  (`type = 'benefits'`) with a few items and load the homepage.
