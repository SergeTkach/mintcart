# Homepage Builder — FAQ block

Phase 10 (`MINTCART_TECHNICAL_PLAN.md` §16-17, `MINTCART_IMPLEMENTATION_GUIDE.md`
§32-33): the **FAQ** block, a pure content block (a list of question/answer
pairs), following the exact Provider/Renderer/Template split
`docs/developer/blocks/products.md` established.

PHP:
- `upload/system/library/mintcart/blocks/faqblockprovider.php` — `MintCart\Blocks\FaqBlockProvider`
- `upload/catalog/controller/common/home.php` — `ControllerCommonHome::renderFaqBlock()`

Twig: `upload/catalog/view/theme/mint_default/template/mint/blocks/faq.twig`

CSS: `.mc-faq`/`__item`/`__question`/`__answer`, colocated in
`assets/src/css/components/block.css` (single consumer so far — `CLAUDE.md`
§17: no dedicated file before a real second consumer).

Tests: `tests/mintcart/blocks/faq-block-provider-test.php`,
`tests/mintcart/blocks/block-renderer-test.php` (scenario 3).

## `FaqBlockProvider`

```php
$settings = (new \MintCart\Blocks\FaqBlockProvider())->build(
    $block['settings'], // mc_block.settings_json, decoded — currently unused
    $block['content']   // mc_block_description.content_json, decoded, current language
);

// $settings = ['title' => string, 'items' => [['question' => string, 'answer' => string], ...]]
```

Unlike `ProductsBlockProvider`/`BannerGridBlockProvider`, an FAQ item has no
structural (non-localized) data at all — a question and its answer are both
translatable text, so both live entirely in `content_json`:

```json
{
  "title": "Frequently Asked Questions",
  "items": [
    { "question": "Do you ship internationally?", "answer": "Yes, worldwide." },
    { "question": "What is your return policy?", "answer": "30 days, no questions asked." }
  ]
}
```

`settings_json` is accepted for signature consistency with every other Block
Provider but is currently unused — there is no non-localized FAQ setting yet
(a possible future one: which item, if any, starts open by default).

Normalization drops any item missing a non-empty `question` or `answer`
(or that isn't itself an array) rather than passing a half-empty item into
the template, and trims both fields. An empty/all-invalid `items` list
normalizes to `[]`.

## `ControllerCommonHome::renderFaqBlock()`

Mirrors `renderProductsBlock()`'s shape but has no product query, no
`tool/image` resize, and no `ProductCardListRenderer` — `FaqBlockProvider`
already returns everything the template needs. Returns `null` (block
skipped, not rendered) when `items` is empty after normalization —
`buildMintBlocks()`'s dispatch treats a `null`/empty-string return from any
block-type renderer the same way, regardless of type.

## `faq.twig`

Renders each item as a native `<details>`/`<summary>` disclosure widget —
no Bootstrap Collapse JS, no `data-bs-toggle`/`aria-expanded` bookkeeping,
no per-instance ID scheme to keep two FAQ blocks on the same page from
colliding. The browser already provides expand/collapse, keyboard
toggling and correct semantics for free
(`MINTCART_IMPLEMENTATION_GUIDE.md` §3/§5: don't reach for a dependency —
Bootstrap's JS collapse included — where a native element already does
the job with less code).

```twig
{% if items %}
<section class="mc-block" data-mc-block-type="faq">
    <div class="container">
        {% if title %}<h2 class="mc-block__title">{{ title }}</h2>{% endif %}
        <div class="mc-faq">
            {% for item in items %}
                <details class="mc-faq__item">
                    <summary class="mc-faq__question">{{ item.question }}</summary>
                    <div class="mc-faq__answer">{{ item.answer }}</div>
                </details>
            {% endfor %}
        </div>
    </div>
</section>
{% endif %}
```

`question`/`answer` are rendered through Twig's default autoescaping (no
`|raw`) — this is admin-authored plain text, not rich HTML, so escaping is
correct here and avoids introducing a stored-XSS surface through block
content.

## What's still not done

- No admin UI to create/edit/reorder `mc_page`/`mc_block` rows yet — an
  `mc_block` row (`type = 'faq'`) currently has to be inserted by hand.
- The remaining first-wave block types not yet claimed by any open PR as of
  this run: Text/Image, Brands, Testimonials (Hero, Categories, Banner Grid
  and Benefits are each claimed by their own open PR — see
  `docs/IMPLEMENTATION_STATUS.md`'s Phase 10 section for current status).
- No live-render verification in this sandbox (no `vendor/twig/twig`, same
  pre-existing gap as every other Twig-touching MintCart test) — a human
  should confirm on a real install: insert a `mc_page` row (`code = 'home'`)
  and an `mc_block` row (`type = 'faq'`, a `content_json` with a couple of
  `items`) and load the homepage, including clicking a question to confirm
  the native disclosure toggle actually expands/collapses.
