# Homepage Builder — Text block

Phase 10 (`MINTCART_TECHNICAL_PLAN.md` §16-17, `MINTCART_IMPLEMENTATION_GUIDE.md`
§51): the second Block Provider/Renderer/Template implementation after
Products (`docs/developer/blocks/products.md`), and the simplest — a Text
block has no OpenCart data dependency at all, unlike Products. This is
also the first real consumer of `mc-prose`
(`MINTCART_IMPLEMENTATION_GUIDE.md` §20), the rich-text container class
documented since Phase 3 but never built until this block needed it
(`CLAUDE.md` §85).

"Text" is distinct from "Text + Image" (`MINTCART_FUNCTIONAL_SCOPE.md` §10)
— both are listed as separate first-wave block types; this block has no
image field.

PHP:
- `upload/system/library/mintcart/blocks/textblockprovider.php` — `MintCart\Blocks\TextBlockProvider`
- `upload/catalog/controller/common/home.php` — `ControllerCommonHome::renderTextBlock()`

Twig: `upload/catalog/view/theme/mint_default/template/mint/blocks/text.twig`

CSS: `assets/src/css/elements/prose.css` (`mc-prose`)

Tests: `tests/mintcart/blocks/text-block-provider-test.php`,
`tests/mintcart/blocks/block-renderer-test.php` (scenario 3)

## `TextBlockProvider`

```php
$settings = (new \MintCart\Blocks\TextBlockProvider())->build(
    $block['settings'], // mc_block.settings_json, decoded (text_align)
    $block['content']   // mc_block_description.content_json, decoded, current language (title, content)
);

// $settings = ['title' => string, 'content' => string, 'text_align' => 'left'|'center']
```

Normalizes a raw block row's settings only — no `\Registry`, no `\DB`, no
OpenCart model — same "no OpenCart dependency" rationale as
`ProductsBlockProvider`/`ProductViewProvider`/`FooterViewProvider`.
Defaults when a setting is missing or invalid: `title`/`content` empty
string, `text_align` `left`. An unrecognized `text_align` value (anything
other than `left`/`center`) falls back to `left` rather than propagating
garbage into the template's class conditional.

`content` is raw HTML (typically produced by an admin rich-text editor)
and is **not** sanitized by this provider or by `text.twig` (which renders
it with `|raw`) — same trust boundary OpenCart's own Information page
`description` field already uses
(`catalog/view/theme/default/template/information/information.twig`
renders `information.description|raw`). Sanitization, if ever needed,
belongs at the point content is *saved* (the future Homepage Builder admin
UI), not at render time.

## `ControllerCommonHome::renderTextBlock()`

Added alongside the existing `renderProductsBlock()` in
`buildMintBlocks()`'s per-block dispatch:

```php
foreach ($blocks as $block) {
    if ($block['type'] === 'products') {
        $markup = $this->renderProductsBlock($block, $block_renderer, $mint_theme);
    } elseif ($block['type'] === 'text') {
        $markup = $this->renderTextBlock($block, $block_renderer);
    } else {
        $markup = null;
    }

    if ($markup !== null && $markup !== '') {
        $rendered[] = $markup;
    }
}
```

`renderTextBlock()` itself is a two-line method: normalize via
`TextBlockProvider::build()`, then `$block_renderer->render('text', $settings)`
— no product query, no image/price/tax mapping, no
`ProductCardListRenderer` involvement. A block with empty `content`
renders nothing (`renderTextBlock()` returns `null`), same "never an empty
`<section>`" rule `renderProductsBlock()` follows.

## `text.twig`

```twig
{% if content %}
<section class="mc-block" data-mc-block-type="text">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8{% if text_align == 'center' %} text-center{% endif %}">
                {% if title %}
                    <h2 class="mc-block__title">{{ title }}</h2>
                {% endif %}

                <div class="mc-block__content mc-prose">
                    {{ content|raw }}
                </div>
            </div>
        </div>
    </div>
</section>
{% endif %}
```

Uses the shared `mc-block`/`mc-block__title` wrapper every block type uses
(`assets/src/css/components/block.css`) — no type-specific CSS
was needed, the generic wrapper's spacing/title styling is already
sufficient. Centered alignment is a plain Bootstrap 5 utility class
(`text-center`), not a new `mc-*` API — alignment is a layout concern, not
part of the Text block's component contract
(`MINTCART_IMPLEMENTATION_GUIDE.md` §7-8: Bootstrap utilities are fine for
layout, `mc-*` is reserved for MintCart-owned ecommerce component
contracts). The content column is capped at `col-lg-8` and centered
(`justify-content-center`) so a long line of prose text doesn't stretch
edge-to-edge on wide screens — the same readable-measure concern
`mc-prose`'s line-height token addresses.

## `mc-prose`

`assets/src/css/elements/prose.css`, imported in `assets/src/css/index.css`
in the `elements` layer (between tokens/presets and the first component,
per the documented import order — `MINTCART_IMPLEMENTATION_GUIDE.md` §11).
Scoped entirely to `.mc-prose` — never a global heading/paragraph/list
reset — so it can never fight a component's own typography
(`MINTCART_IMPLEMENTATION_GUIDE.md` §20's explicit warning against global
rich-text rules). Every value reads a semantic or primitive design token
(`--mc-text`, `--mc-primary`, `--mc-border`, `--mc-muted`,
`--mc-font-family-base`/`-heading`, `--mc-font-weight-semibold`,
`--mc-line-height-relaxed`, `--mc-space-*`, `--mc-radius-md`) so presets
(`docs/developer/theme-api/presets.md`) reach prose content the same way
they reach every other MintCart component — a hardcoded color here would
silently ignore Warm the same way a hardcoded color in any other component
would (Phase 9's carried-forward rule, `docs/IMPLEMENTATION_STATUS.md`
Phase 9 section).

Covers headings (`h2`-`h4`), paragraphs, lists, links, images (capped at
`max-width: 100%`), tables and blockquotes — the common output shapes of a
CKEditor-class rich-text editor (`MINTCART_FUNCTIONAL_SCOPE.md` §24). Not
covered (no real content has needed it yet, `CLAUDE.md` §85): `pre`/`code`
blocks, definition lists, footnotes.

## What's still not done

- No admin UI to create/edit `mc_block` rows of type `text` yet — same
  gap `docs/developer/blocks/products.md` already documents for the whole
  Homepage Builder (a `'text'` block currently has to be inserted by hand
  to be visible).
- Hero, Categories, Banner Grid, Benefits, Text/Image, Brands,
  Testimonials, FAQ, Gallery, HTML, CTA, Newsletter, Spacer block types —
  check open PRs before starting any of these, several were in flight as
  of this block's own PR (see `docs/IMPLEMENTATION_STATUS.md`'s "Next step
  for the next routine run").
- No live-render verification in this sandbox (no `vendor/twig/twig`,
  same pre-existing gap as every other Twig-touching MintCart change) — a
  human should confirm on a real install: insert an `mc_block` row
  (`type = 'text'`, `settings_json = '{}'`, a `mc_block_description` row
  with `content_json = '{"title":"...","content":"<p>...</p>"}'`) on the
  `'home'` page and load the homepage.
- HTML sanitization of `content` — deliberately out of scope here (see
  "Why `content` isn't sanitized" above); becomes relevant once an admin
  save path exists.
