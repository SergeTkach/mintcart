# Homepage Builder — Newsletter block

Phase 10 (`MINTCART_TECHNICAL_PLAN.md` §16-17, `MINTCART_IMPLEMENTATION_GUIDE.md`
§51): the **Newsletter** block, a pure content block (no OpenCart query at
all), following the exact Provider/Renderer/Template split the Products
block established (see `docs/developer/blocks/products.md` for the pattern
this mirrors).

PHP:
- `upload/system/library/mintcart/blocks/newsletterblockprovider.php` — `MintCart\Blocks\NewsletterBlockProvider`
- `upload/catalog/controller/common/home.php` — `ControllerCommonHome::renderNewsletterBlock()`

Twig: `upload/catalog/view/theme/mint_default/template/mint/blocks/newsletter.twig`

CSS: `.mc-block--newsletter`/`.mc-block__newsletter-inner`/
`.mc-block__newsletter-text`/`.mc-block__newsletter-form` in
`assets/src/css/components/block.css` (colocated — single consumer so far,
`CLAUDE.md` §17: no dedicated file before a real second consumer).

Tests: `tests/mintcart/blocks/newsletter-block-provider-test.php`,
Scenario 3 in `tests/mintcart/blocks/block-renderer-test.php`.

## Why there's no working subscribe endpoint

Stock OpenCart 3 has no built-in route for an anonymous "subscribe with
just an email address" form. The closest existing feature,
`account/newsletter`, is an opt-in checkbox on an already-registered
customer's account page — not a public subscribe target a guest can POST
an email to.

Rather than inventing a fake endpoint or leaving the form always visible
with nowhere to go, `NewsletterBlockProvider` follows the same rule
`MintCart\Design\FooterViewProvider::buildNewsletter()` already
established for the footer's own newsletter field: the subscribe form is
only `enabled` when a real `action` URL is configured on the block. With
no `action`, the block still renders its `title`/`text` (useful on its
own as a plain announcement/CTA-style block) but omits the form entirely.
Once a real subscribe target exists — a Commerce API endpoint (Phase 15)
or a shipped newsletter extension — a store owner points the block's
`action` setting at it and the form appears, with no template change
needed (`CLAUDE.md` §85: no API/wiring ahead of a real consumer).

## `NewsletterBlockProvider`

```php
$settings = (new \MintCart\Blocks\NewsletterBlockProvider())->build(
    $block['settings'], // mc_block.settings_json, decoded
    $block['content']   // mc_block_description.content_json, decoded, current language
);

// $settings = [
//     'title' => string, 'text' => string,
//     'placeholder' => string, 'submit_label' => string,
//     'enabled' => bool, 'action' => string, 'method' => 'post'|'get',
// ]
```

Structural/localized split: `action`/`method` live in `settings_json` (not
translated per-language — a store has one subscribe endpoint); `title`/
`text`/`placeholder`/`submit_label` live in `content_json` (localized,
admin-editable per language — same as `CtaBlockProvider`'s `cta_label`,
not a hardcoded language-file string, so a store owner can word the call
to action without a developer touching a language file).

Defaults: `method` `'post'` (an unrecognized value, e.g. `'delete'`, falls
back to it rather than propagating into the rendered `<form method="...">`
attribute); `action`/`title`/`text`/`placeholder`/`submit_label` all `''`
when absent. `enabled` is `true` only when `action` is a non-blank,
non-whitespace-only string — a whitespace-only action is treated the same
as no action at all.

Deliberately has no OpenCart dependency (no `\Registry`, no `\DB`) — same
rationale as every other block provider.

## `ControllerCommonHome::renderNewsletterBlock()`

The simplest render method among the block types implemented so far: no
query, no model, no image resize — normalize via the provider, render
through `BlockRenderer`. Returns `null` (renders nothing) only when title,
text, and the subscribe form are all absent — matches the same
"nothing to show" guard `CtaBlockProvider`'s render path uses.
`buildMintBlocks()`'s dispatch gained an
`elseif ($block['type'] === 'newsletter')` branch alongside the existing
`'products'` one — see that method's docblock for the full list of
still-not-merged sibling block-type PRs.

## `newsletter.twig`

```twig
{% if title or text or enabled %}
<section class="mc-block mc-block--newsletter">
    <div class="container">
        <div class="mc-block__newsletter-inner">
            {% if title %}<h2 class="mc-block__title">{{ title }}</h2>{% endif %}
            {% if text %}<p class="mc-block__newsletter-text">{{ text }}</p>{% endif %}
            {% if enabled %}
                <form class="mc-block__newsletter-form" action="{{ action }}" method="{{ method }}">
                    <input type="email" name="email" class="mc-field__control" placeholder="{{ placeholder }}" required>
                    <button type="submit" class="mc-button mc-button--primary">{{ submit_label }}</button>
                </form>
            {% endif %}
        </div>
    </div>
</section>
{% endif %}
```

The email `<input>` reuses `mc-field__control`'s existing look (Phase 4)
and the submit button reuses `mc-button mc-button--primary` (Phase 4) —
no new form-control or button markup introduced, same reuse pattern
`text-image.twig` uses for `mc-prose` and `cta.twig` uses for `mc-button`.
Centered, narrow (`max-width: 32rem`) layout distinguishes it from the
full-width Products/Brands blocks — appropriate for a single-purpose
announcement block.

## What's still not done

- No admin UI to create/edit/reorder `mc_page`/`mc_block` rows yet — a
  `'home'` page with a `'newsletter'` block currently has to be inserted
  by hand to be visible, same gap every other block type carries.
- **No real subscribe endpoint exists to point `action` at** (see above) —
  this is a deliberate, documented gap, not an oversight. A store can
  still use the block as a plain title/text announcement with the form
  disabled.
- No live-render verification in this sandbox (no `vendor/twig/twig`,
  same pre-existing gap as every other Twig-touching MintCart test) — a
  human should confirm on a real install: insert an `mc_block` row
  (`type = 'newsletter'`) with and without an `action` setting on a real
  install and confirm the homepage renders the announcement-only variant
  and the with-form variant correctly.
