# Homepage Builder — Spacer block

Phase 10 (`MINTCART_TECHNICAL_PLAN.md` §16-17, `MINTCART_IMPLEMENTATION_GUIDE.md`
§33): the **Spacer** block — the simplest block type in the Homepage
Builder's first-wave catalog. It has no localized content and makes no
OpenCart query at all; it exists only to let a homepage builder insert
vertical spacing between two other blocks, following the exact Provider/
Renderer/Template split the Products block established
(`docs/developer/blocks/products.md`).

PHP:
- `upload/system/library/mintcart/blocks/spacerblockprovider.php` — `MintCart\Blocks\SpacerBlockProvider`
- `upload/catalog/controller/common/home.php` — `ControllerCommonHome::renderSpacerBlock()`

Twig: `upload/catalog/view/theme/mint_default/template/mint/blocks/spacer.twig`

CSS: `.mc-block--spacer*` in `assets/src/css/components/block.css`

Tests: `tests/mintcart/blocks/spacer-block-provider-test.php`,
`tests/mintcart/blocks/block-renderer-test.php` (scenario 3)

## `SpacerBlockProvider`

```php
$settings = (new \MintCart\Blocks\SpacerBlockProvider())->build(
    $block['settings'] // mc_block.settings_json, decoded
);

// $settings = ['size' => 'sm'|'md'|'lg'|'xl']
```

Normalizes a single structural setting, `size`. No `content_json` is read
at all — a spacer has nothing localized to say. An unrecognized or missing
value (including a non-string, e.g. accidentally stored as a number) falls
back to the default (`md`) rather than propagating an arbitrary string into
the rendered CSS class.

## `ControllerCommonHome::renderSpacerBlock()`

`buildMintBlocks()`'s per-block dispatch (previously a single
`if ($block['type'] === 'products')`) is now an `if`/`elseif`/`else`
choosing between `renderProductsBlock()` and the new `renderSpacerBlock()`,
with any other `type` falling through to `null` (skipped, not fatal — same
behaviour as before this block existed).

`renderSpacerBlock()` makes no OpenCart query, unlike `renderProductsBlock()`
— it only normalizes the block's `settings` and renders the block template
with `size`. It always returns non-empty markup (a spacer always has a
size, defaulting to `md`), unlike the Products block which can return
`null` when the query finds nothing to show.

## Template

```twig
<div class="mc-block mc-block--spacer mc-block--spacer-{{ size }}" aria-hidden="true"></div>
```

A single empty `<div>` — `aria-hidden="true"` because a spacer is purely
visual, never meaningful content for assistive technology. Unlike
`products.twig` it has no `.container`/`.row` wrapper (a spacer has no
content to constrain, per `docs/developer/layout-conventions.md`'s "Bootstrap
grid/utilities are for constraining content" scope) and no `.mc-block`
top/bottom padding of its own — see the CSS note below.

## CSS

```css
.mc-block--spacer {
    padding-top: 0;
    padding-bottom: 0;
    height: var(--mc-block-spacer-height, var(--mc-space-8));
}

.mc-block--spacer-sm { --mc-block-spacer-height: var(--mc-space-4); }  /* 1rem */
.mc-block--spacer-md { --mc-block-spacer-height: var(--mc-space-8); }  /* 2rem, default */
.mc-block--spacer-lg { --mc-block-spacer-height: var(--mc-space-12); } /* 3rem */
.mc-block--spacer-xl { --mc-block-spacer-height: var(--mc-space-16); } /* 4rem */
```

`.mc-block`'s shared `padding-top`/`padding-bottom: var(--mc-space-8)`
(`docs/developer/blocks/products.md`'s `.mc-block` base) is explicitly
zeroed out for `.mc-block--spacer` — the modifier's `height` *is* the space
a merchant asked for; adding the base block's own padding on top of it
would make every spacer size read larger than its label suggests.
`--mc-block-spacer-height` is the component-local override point
(`MINTCART_IMPLEMENTATION_GUIDE.md` §16) a theme could redeclare per size
without touching this file.

## What's still not done

- No admin UI to create/edit/reorder `mc_page`/`mc_block` rows yet (same
  gap `docs/developer/blocks/products.md` already documents) — a `'home'`
  page with a `'spacer'` block currently has to be inserted by hand.
- Hero, Categories, Banner Grid, Benefits, Text/Image, Brands,
  Testimonials, CTA, Newsletter, FAQ have their own providers/templates in
  separate, not-yet-merged PRs as of this writing; Gallery and HTML remain
  fully unclaimed.
- No live-render verification in this sandbox (no `vendor/twig/twig`, same
  pre-existing gap every other Twig-touching MintCart change carries) — a
  human should confirm on a real install: insert an `mc_block` row
  (`type = 'spacer'`, `settings_json = '{"size":"lg"}'`) on the `'home'`
  page and load the homepage.
