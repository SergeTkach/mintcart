# Homepage Builder — Testimonials block

Phase 10 (`MINTCART_TECHNICAL_PLAN.md` §16-17, `MINTCART_IMPLEMENTATION_GUIDE.md`
§32-33, §51): the **Testimonials** block, a pure content block (a grid of
quote/author/optional-avatar/optional-rating cards), following the exact
Provider/Renderer/Template split the Products block established
(`docs/developer/blocks/products.md`).

PHP:
- `upload/system/library/mintcart/blocks/testimonialsblockprovider.php` — `MintCart\Blocks\TestimonialsBlockProvider`
- `upload/catalog/controller/common/home.php` — `ControllerCommonHome::renderTestimonialsBlock()`

Twig: `upload/catalog/view/theme/mint_default/template/mint/blocks/testimonials.twig`

CSS: `.mc-testimonial`/`__quote`/`__author`/`__avatar`/`__author-name`/
`__author-role`, colocated in `assets/src/css/components/block.css`
(single consumer so far, `CLAUDE.md` §17: no dedicated file before a real
second consumer).

Tests: `tests/mintcart/blocks/testimonials-block-provider-test.php`,
`tests/mintcart/blocks/block-renderer-test.php` (Scenario 3).

## `TestimonialsBlockProvider`

```php
$settings = (new \MintCart\Blocks\TestimonialsBlockProvider())->build(
    $block['settings'], // mc_block.settings_json, decoded
    $block['content']   // mc_block_description.content_json, decoded, current language
);

// $settings = [
//     'title' => string,
//     'items' => [
//         ['quote' => string, 'author_name' => string, 'author_role' => string,
//          'avatar' => ?string, 'avatar_width' => int, 'avatar_height' => int,
//          'rating' => ?int],
//         ...
//     ],
// ]
```

A testimonial item's structural (non-localized) data — `avatar` path/
`avatar_width`/`avatar_height`/`rating` — lives in `settings_json`;
localized `quote`/`author_name`/`author_role` live in `content_json`. The
two lists are matched by array position, same convention
`BannerGridBlockProvider`/`BenefitsBlockProvider` use for their own items
— there is no per-item ID to join on, since the Homepage Builder admin UI
to manage these rows doesn't exist yet.

An item with a blank `quote` or blank `author_name` is dropped during
normalization rather than rendered as an empty card. `rating` is only
kept when it parses to an integer between 1 and 5 inclusive; anything
else (missing, non-numeric, out of range) normalizes to `null`, which
`testimonials.twig` treats as "no rating shown" for that item.
`avatar`/`avatar_width`/`avatar_height` are independent of `rating` —
a testimonial can have any combination of avatar and rating, including
neither.

Deliberately has no OpenCart dependency (no `\Registry`, no `\DB`, no
image model) — resizing an item's optional `avatar` is the controller's
job, same split `ProductsBlockProvider`/`BannerGridBlockProvider` use.

## `ControllerCommonHome::renderTestimonialsBlock()`

`buildMintBlocks()`'s per-block dispatch gained an
`elseif ($block['type'] === 'testimonials')` branch alongside the
existing `'products'` one. For each `type = 'testimonials'` block:

1. `TestimonialsBlockProvider::build()` normalizes settings/content into
   `title` + `items[]`.
2. No items survived normalization (e.g. every item had a blank quote or
   author) → return `null`, the same "render nothing rather than an empty
   `<section>`" convention every block type uses.
3. Each item's optional `avatar` is resized via
   `$this->model_tool_image->resize()`, guarded with
   `is_file(DIR_IMAGE . $item['avatar'])` — the same pattern the stock
   Banner module and `BannerGridBlockProvider`'s consumer use. A missing
   or deleted file degrades that one item to no avatar (`null`), not a
   broken `<img>` and not dropping the whole testimonial — unlike Banner
   Grid, where the image *is* the content, here the quote/author remain
   fully renderable without a photo.
4. Rendered via `BlockRenderer::render('testimonials', [...])`.

## `testimonials.twig`

A responsive `row g-4` grid (1 column on mobile, 2 on tablet, 3 on
desktop) of `<figure class="mc-testimonial">` cards. Each card:

- An optional star rating, reusing the exact same `mc-rating` markup
  (`--mc-rating-value` custom property + `mc-rating__stars-empty`/
  `-filled` overlay) `product-card.twig` already uses for product
  ratings — no new rating component was introduced.
- A `<blockquote class="mc-testimonial__quote">`.
- A `<figcaption class="mc-testimonial__author">` with an optional
  `<img class="mc-testimonial__avatar">` (rendered only when the
  controller resolved a real file; `alt=""` since the adjacent author
  name already conveys the same information) and the author's name/role.

## What's still not done

- No admin UI to create/edit/reorder `mc_page`/`mc_block` rows yet — a
  `'testimonials'` block instance currently has to be inserted by hand.
- Text/Image, Brands block types have no provider/template yet.
- No live-render verification in this sandbox (no `vendor/twig/twig`,
  same pre-existing gap as every other Twig-touching MintCart test) — a
  human should confirm on a real install: insert an `mc_block` row
  (`type = 'testimonials'`) with a couple of items (with and without an
  `avatar`/`rating`) on the `'home'` page and confirm the homepage
  renders the grid, the star rating overlay looks correct, and a missing
  avatar file degrades gracefully rather than showing a broken image.

## Gitignore fix noted alongside this block

While adding these files, `git add` silently reported them as ignored:
the pre-existing `upload/**/test*` glob in `.gitignore` (intended for
ad-hoc runtime/scratch test data dropped into a live install) matches
any file whose name merely starts with "test" — including
`testimonialsblockprovider.php` and `testimonials.twig`. This is the same
false-positive class `upload/catalog/view/theme/test_theme/` already hit
once (see that commit's negation, just above the new one this PR adds).
Fixed with two more targeted `!` negations rather than loosening the
broad `test*` rule for anything else — consistent with the existing
precedent.
