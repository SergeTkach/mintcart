# Homepage Builder — Text/Image block

Phase 10 (`MINTCART_TECHNICAL_PLAN.md` §16-17, `MINTCART_IMPLEMENTATION_GUIDE.md`
§33/§51): the **Text/Image** block, a pure content block (no OpenCart query at
all), following the exact Provider/Renderer/Template split the Products block
established (`docs/developer/blocks/products.md`).

Covers both the "Text" and "Text + Image" catalog entries
(`MINTCART_IMPLEMENTATION_GUIDE.md` §33) as one block type: the `image`
setting is optional, so a block with none renders as plain text, exactly
like a standalone "Text" block would — a second, near-identical provider
would only duplicate this one's title/body handling
(`CLAUDE.md` §17 — reuse by responsibility, not a class per catalog label).

PHP:
- `upload/system/library/mintcart/blocks/textimageblockprovider.php` — `MintCart\Blocks\TextImageBlockProvider`
- `upload/catalog/controller/common/home.php` — `ControllerCommonHome::renderTextImageBlock()`

Twig: `upload/catalog/view/theme/mint_default/template/mint/blocks/text-image.twig`

CSS: `.mc-block__text-image-media` (`assets/src/css/components/block.css`,
colocated — single consumer so far, `CLAUDE.md` §17); `.mc-prose`
(`assets/src/css/elements/prose.css` — new file, see below).

Tests: `tests/mintcart/blocks/text-image-block-provider-test.php`,
Scenario 3 of `tests/mintcart/blocks/block-renderer-test.php`.

## `TextImageBlockProvider`

```php
$settings = (new \MintCart\Blocks\TextImageBlockProvider())->build(
    $block['settings'], // mc_block.settings_json, decoded
    $block['content']   // mc_block_description.content_json, decoded, current language
);

// $settings = [
//     'title' => string, 'body' => string, 'image' => ?string,
//     'image_alt' => string, 'image_width' => int, 'image_height' => int,
//     'image_position' => 'left'|'right',
// ]
```

Structural (`settings_json`): `image`, `image_width`, `image_height`,
`image_position`. Localized (`content_json`): `title`, `body`, `image_alt`.
No OpenCart dependency at all — same "given already-decoded settings/content,
only shape what the caller has" rationale as `ProductsBlockProvider`/
`HeroBlockProvider`. Defaults when a setting is missing or invalid:
`image_width` 800, `image_height` 600, `image_position` `right`; an
unrecognized `image_position` (anything but `left`/`right`, case-insensitive)
falls back to `right` rather than propagating garbage into the template's
`order-md-first` check.

`body` is rich text (e.g. a future CKEditor-based admin field) rendered raw
inside `.mc-prose` — same convention OpenCart's own
`product/category.twig` already uses for `description` (Twig autoescape is
disabled repo-wide, `upload/system/library/template/twig.php`), so no `|raw`
filter is needed or used.

## `ControllerCommonHome::renderTextImageBlock()`

Resolves `image` via `$this->model_tool_image->resize()`, `is_file()`-guarded
exactly like `renderHeroBlock()` — a missing file degrades to no image
(never a broken `<img>`) rather than dropping the whole block, since the
block's title/body can still be worth showing. Returns `null` (block skipped
entirely) only when there is no title, no body, *and* no resolvable image —
nothing at all to render. `buildMintBlocks()`'s dispatch gained an
`elseif ($block['type'] === 'text-image')` branch alongside the existing
`'products'` one.

## `text-image.twig`

A responsive two-column layout (Bootstrap's `.row`/`.col-*`, per
`docs/developer/layout-conventions.md`) — text in one column, an optional
image in the other. Column order is fixed in the template source (text
first); `image_position: 'left'` is achieved with Bootstrap's
`.order-md-first` utility on the image column rather than a second markup
branch, so there is exactly one place that decides the layout on desktop and
plain vertical stacking below `md`. With no `image` setting, the text column
alone spans the full width (`col-12`, no `col-md-6`) — a genuine "Text" block,
not a "Text + Image" block with an invisible empty column.

## `mc-prose` (`assets/src/css/elements/prose.css`)

The first file in the CSS architecture's `elements/` layer
(`MINTCART_IMPLEMENTATION_GUIDE.md` §11/§20) — headings, paragraphs, lists,
links, images and tables inside `.mc-prose` get baseline typographic styling
using the same `--mc-*` tokens every other component uses, scoped to that
class so it never leaks into unrelated markup. This block's `body` is its
first real consumer; the OpenCart core `product`/`category`/`information`
`description` fields are documented future consumers once their templates
are touched, but this PR does not wire them (no abstraction ahead of a real
consumer, `CLAUDE.md` §85).

## What's still not done

- No admin UI to create/edit/reorder `mc_page`/`mc_block` rows yet — a
  `'home'` page with a `'text-image'` block currently has to be inserted by
  hand to be visible.
- Categories, Banner Grid, Benefits, Brands, Testimonials, FAQ block types
  (each open as separate PRs as of this writing) still need to land and be
  reconciled into `buildMintBlocks()`'s dispatch chain.
- CTA, Newsletter, Gallery, HTML, Spacer (`MINTCART_IMPLEMENTATION_GUIDE.md`
  §33's full block catalog, beyond the "first-wave" set
  `docs/IMPLEMENTATION_STATUS.md` has been tracking) have no provider yet.
- No live-render verification in this sandbox (no `vendor/twig/twig`, same
  pre-existing gap as every other Twig-touching MintCart test) — a human
  should insert an `mc_block` row (`type='text-image'`) with and without an
  `image` setting on a real install and confirm the homepage renders both a
  plain-text block and a two-column block with the image on each side.
