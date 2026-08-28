# Homepage Builder — HTML block

Phase 10 (`MINTCART_TECHNICAL_PLAN.md` §16-17,
`MINTCART_IMPLEMENTATION_GUIDE.md` §33's first-wave block list). The
simplest Block Provider/Renderer/Template implementation so far — no
product query, no OpenCart model dependency beyond the already-loaded
`mc_block` row.

For `BlockRenderer`/`ModelMintcartBlock`/`ControllerCommonHome`'s shared
mechanics (theme fallback resolution, `mc_page`/`mc_block` storage, the
per-block dispatch in `buildMintBlocks()`), see
`docs/developer/blocks/products.md` — this doc only covers what's
different about the HTML block itself.

PHP:
- `upload/system/library/mintcart/blocks/htmlblockprovider.php` —
  `MintCart\Blocks\HtmlBlockProvider`
- `upload/catalog/controller/common/home.php` —
  `ControllerCommonHome::renderHtmlBlock()`

Twig: `upload/catalog/view/theme/mint_default/template/mint/blocks/html.twig`

Tests: `tests/mintcart/blocks/html-block-provider-test.php`,
`tests/mintcart/blocks/block-renderer-test.php` (scenario 3, real-repo
`html.twig` resolution)

## `HtmlBlockProvider`

```php
$settings = (new \MintCart\Blocks\HtmlBlockProvider())->build(
    $block['settings'], // mc_block.settings_json, decoded — unused, kept only for shape parity
    $block['content']   // mc_block_description.content_json, decoded, current language
);

// $settings = ['html' => string]
```

Unlike `ProductsBlockProvider` (`category_id`/`limit`/`sort`/`order`) or
`TextBlockProvider` (`title`/`text_align`), the HTML block has **no
structural `settings_json` fields at all** — its only field is the raw
markup itself, which is per-language admin-authored content, so it lives
in `content_json` rather than `settings_json`. `build()` still accepts a
`$settings` parameter so every Block Provider shares one
`build(settings, content)` shape, even though this one ignores it.

A missing or non-string `html` value normalizes to `''`, never `null` or
an array — the same "always a safe, typed value" rule every other
provider in this codebase follows.

## Why `|raw` is safe here

`html.twig` renders the value with Twig's `|raw` filter — deliberately
unescaped. This block type exists specifically to let a store owner or
admin drop arbitrary markup into the homepage (embed codes, a one-off
promotional banner, third-party widget snippets, ...), the same
trusted-admin-content model OpenCart's own "HTML content"-style
modules use. `mc_block` rows are only ever written by someone with admin
backend access — there is no public-facing block editor yet (Homepage
Builder's drag-and-drop admin UI, `MINTCART_TECHNICAL_PLAN.md` §17's last
unchecked item, is still not started; today a row has to be inserted by
hand). This is not a place for unescaped end-user/customer input, and
must not become one once the admin editor exists — that editor authors
content on behalf of the store owner, not a storefront visitor.

## `ControllerCommonHome::renderHtmlBlock()`

```php
private function renderHtmlBlock(array $block, \MintCart\Blocks\BlockRenderer $block_renderer) {
    $settings = (new \MintCart\Blocks\HtmlBlockProvider())->build($block['settings'], $block['content']);

    if ($settings['html'] === '') {
        return null;
    }

    return $block_renderer->render('html', array('html' => $settings['html']));
}
```

An empty `html` value returns `null` (not an empty `<section>`) — the
same "render nothing rather than an empty wrapper" rule
`renderProductsBlock()` follows for a query with no results. `html.twig`
itself also guards with `{% if html %}`, so the two checks are
belt-and-braces, not a single point of failure.

`buildMintBlocks()`'s per-block dispatch now recognizes `type = 'html'`
alongside `type = 'products'`; any other type is still skipped, not
fatal — the remaining first-wave block types (Hero, Categories, Banner
Grid, Benefits, Text/Image, Brands, Testimonials, FAQ, Text, CTA,
Newsletter, Spacer, Gallery) are separate checklist items, several
already in flight as open pull requests (see
`docs/IMPLEMENTATION_STATUS.md`).

## What's still not done

- No admin UI to create/edit/reorder `mc_block` rows — same gap every
  other block type has (Homepage Builder's drag-and-drop editor).
- No live-render verification in this sandbox (no `vendor/twig/twig`
  installed here) — a human should confirm on a real install: insert an
  `mc_block` row (`type = 'html'`, `content_json = '{"html":"<p>Hello</p>"}'`)
  on the `'home'` page and load the homepage.
- Gallery is the only remaining first-wave block type with no open PR at
  the time this was written.
