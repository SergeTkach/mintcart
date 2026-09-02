# Homepage Builder — CTA block

Phase 10 (`MINTCART_TECHNICAL_PLAN.md` §16-17, `MINTCART_IMPLEMENTATION_GUIDE.md`
§33): the **CTA** (call-to-action) block — title, supporting text, and an
optional button — the simplest first-wave block type after Benefits/FAQ:
pure content, no OpenCart query/model involved at all, following the exact
Provider/Renderer/Template split the Products block established
(`docs/developer/blocks/products.md`).

PHP:
- `upload/system/library/mintcart/blocks/ctablockprovider.php` — `MintCart\Blocks\CtaBlockProvider`
- `upload/catalog/controller/common/home.php` — `ControllerCommonHome::renderCtaBlock()`

Twig: `upload/catalog/view/theme/mint_default/template/mint/blocks/cta.twig`

Tests: `tests/mintcart/blocks/cta-block-provider-test.php`,
`tests/mintcart/blocks/block-renderer-test.php` (Scenario 3)

## `CtaBlockProvider`

```php
$settings = (new \MintCart\Blocks\CtaBlockProvider())->build(
    $block['settings'], // mc_block.settings_json, decoded — currently unused
    $block['content']   // mc_block_description.content_json, decoded, current language
);

// $settings = ['title' => string, 'text' => string, 'cta_label' => string, 'cta_url' => string]
```

Everything a CTA block needs is localized content — `title`, `text`,
`cta_label`, `cta_url` — so `settings_json` is accepted only for signature
consistency with every other block provider and is not read. No OpenCart
dependency (no `\Registry`, no `\DB`, no model), same rationale as
`ProductsBlockProvider`/`ProductViewProvider`: there is nothing to query for
this block type.

A button needs **both** `cta_label` and `cta_url` to be meaningful — a label
with no URL (or a URL with no label) normalizes to `''`/`''` ("no button")
rather than rendering a dead or unlabeled link. `title`/`text` are
independent of the button and pass through as-is (missing → `''`).

## `ControllerCommonHome::renderCtaBlock()` — the real consumer

`buildMintBlocks()`'s per-block dispatch gained an
`elseif ($block['type'] === 'cta')` branch alongside the existing
`'products'` one. `renderCtaBlock()` just normalizes via
`CtaBlockProvider::build()` and renders through the existing
`MintCart\Blocks\BlockRenderer` — no query, no image resize, no model load,
the simplest render method of the block types implemented so far (mirrors
what the Benefits/FAQ block types' own PRs describe for the same reason: a
pure-content block has nothing to fetch).

`blocks/cta.twig` renders nothing (`{% if title or text or (cta_label and
cta_url) %}`) when title, text, and button are all empty — same "return
null/empty, never an empty `<section>`" contract every other block type
follows.

## What's still not done

- No admin UI to create/edit/reorder `mc_page`/`mc_block` rows yet
  (Homepage Builder's drag-and-drop editor) — a `'home'` page with a `'cta'`
  block currently has to be inserted by hand to be visible.
- Newsletter, Gallery, HTML, Spacer block types (`MINTCART_IMPLEMENTATION_GUIDE.md`
  §33's remaining catalog entries) have no provider/template yet, as of this
  increment. Hero, Categories, Banner Grid, Benefits, Text/Image, Brands,
  Testimonials, FAQ each have their own open, not-yet-merged PR — check
  open PRs before starting any of those.
- No live-render verification in this sandbox (no `vendor/twig/twig`, same
  pre-existing gap as every other Twig-touching MintCart test) — a human
  should confirm on a real install: insert a `mc_page` row (`code = 'home'`)
  and an `mc_block` row (`type = 'cta'`, `content_json` with `title`/`text`/
  `cta_label`/`cta_url`) and load the homepage; also confirm a block with
  only a `title` (no button) still renders correctly.
