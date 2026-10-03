# `MintCart\Design\ProductCardListRenderer` and `MintCart\Design\ActiveTheme`

The shared per-product-row rendering step every listing controller
(Category, Search, Manufacturer, Special, Homepage Products blocks) needs
(`MINTCART_TECHNICAL_PLAN.md` §15C/§15D, `CLAUDE.md` §8A). Composes
`MintCart\Commerce\ProductViewProvider` (normalizes the row) with
`MintCart\Design\ComponentRenderer` (resolves + renders `product-card`)
so a controller has one call to make per product instead of duplicating
that pairing and its failure handling.

PHP: `upload/system/library/mintcart/design/productcardlistrenderer.php`,
`upload/system/library/mintcart/design/activetheme.php`
Test: `tests/mintcart/design/product-card-list-renderer-test.php`

## Why this exists

`ControllerProductCategory` (Phase 8) was the first controller wired to
`ProductViewProvider`/`ComponentRenderer`, and inlined its own per-product
`try`/`catch`-and-disable-for-the-rest-of-the-request loop plus a private
`mintActiveThemeDirectory()` helper. `docs/upstream-diff.md`'s `category.php`
entry explicitly named extracting that shape into a shared service as
expected "once Search/Manufacturer/Special need the same thing" —
premature before then, per `CLAUDE.md` §85 (no abstraction before a real
second consumer exists). `ControllerProductSearch` is that second consumer,
so the shared shape is extracted here, and `ControllerProductCategory` /
`ControllerCommonFooter` (which had carried an identical private
`mintActiveThemeDirectory()` copy for Footer, Phase 7) are refactored to use
it too — with no behavioral change to either.

## `ActiveTheme`

Two static, OpenCart-agnostic helpers factored out of what three
controllers now need identically:

```php
MintCart\Design\ActiveTheme::directory(?string $configTheme, ?string $themeDefaultDirectory): string
MintCart\Design\ActiveTheme::isMintAware(string $themesDirectory, string $directory): bool
```

`directory()` mirrors `catalog/controller/event/theme.php`'s own
`config_theme`/`theme_default_directory` resolution exactly (`'default'`,
or an unset setting, resolves through `theme_default_directory`; anything
else passes through unchanged). `isMintAware()` is the same
"does this theme ship a `theme.json`" gate `category.php`, `footer.php`,
and `event/theme.php` itself already used before this extraction — a store
on the legacy `default` theme, or any unrelated third-party OC3 theme,
never triggers any Mint-owned rendering pipeline.

Deliberately takes plain string values rather than an OpenCart `Config`
object, so it stays unit-testable without bootstrapping OpenCart — same
rationale as `ComponentRenderer`/`ProductViewProvider`.

## `ProductCardListRenderer`

```php
$renderer = new \MintCart\Design\ProductCardListRenderer(
    new \MintCart\Design\ComponentRenderer(DIR_TEMPLATE, $mintTheme)
);

$card = $renderer->render($product, $display, $context, $languageStrings, $onError);
```

- `$product`/`$display`/`$context` are passed straight through to
  `ProductViewProvider::build()` — see
  `docs/developer/commerce/product-view-provider.md` for their shape.
- `$languageStrings` is merged into the resolved `product-card.twig`'s
  template data alongside the `ProductViewProvider` output (the component's
  documented `text_*` variables, `docs/developer/components/product-card.md`).
- `$onError` (optional) is invoked as `function (\Throwable $e, array $product)`
  when rendering fails, before the renderer disables itself for the rest of
  the list — each controller keeps its own `MintCart\Core\Logger` message
  wording rather than this class hardcoding one.
- Return value is `?string`: the rendered markup, or `null` when this call
  (or an earlier one on the same instance) failed. `null` means "skip this
  product", not "something is broken" — the caller checks it, it does not
  need to catch anything itself.

Once one product in a list fails to render (a missing/unreadable
`product-card.twig`, a deploy mistake, a future refactor bug), every
further product in the *same* `ProductCardListRenderer` instance is skipped
too, via `isEnabled()`:

```php
if ($mint_render_product_cards) {
    $card = $renderer->render(...);

    if ($card !== null) {
        $data['product_cards'][] = $card;
    }

    $mint_render_product_cards = $renderer->isEnabled();
}
```

This matches `ControllerProductCategory`'s original behaviour exactly: a
broken Mint component degrades that page's product-card grid for the rest
of the request rather than throwing per-product errors that would fatal
the whole page for every visitor. The controller's own listing array
(`$data['products']`, what the legacy `default` theme and any third-party
theme actually render) is never touched by this class either way.

A fresh `ProductCardListRenderer` instance always starts enabled — nothing
here is static or shared across requests, so one broken product on one
request never affects the next.

## Extension slot wiring (optional 3rd constructor argument)

```php
$renderer = new \MintCart\Design\ProductCardListRenderer(
    $componentRenderer,
    null,   // $productViewProvider - null uses the default instance
    $slotRegistry  // MintCart\Design\SlotRegistry, or omit/null entirely
);
```

`$slotRegistry` (`MintCart\Design\SlotRegistry`,
`docs/developer/theme-api/slots.md`) is `null` by default, matching every
existing call site byte-for-byte — no `slots` key is added to the
template data unless a caller explicitly passes one. When non-null,
`render()` adds `$productView['slots']['product.after_actions']` (the
result of `$slotRegistry->render('product.after_actions', $productView['product'])`)
before handing the data to `ComponentRenderer` — `product-card.twig`'s own
documented `product.after_actions` slot
(`docs/developer/components/product-card.md`) is the one real consumer
today.

No controller constructs a `SlotRegistry` yet (`CLAUDE.md` §85 — no real
extension needs one registered yet); this argument exists so the mechanism
is proven end to end at the template level, ready for the first real
caller.

## Consumers

- `ControllerProductCategory` (`upload/catalog/controller/product/category.php`)
- `ControllerProductSearch` (`upload/catalog/controller/product/search.php`)
- `ControllerProductSpecial` (`upload/catalog/controller/product/special.php`)
- `ControllerProductManufacturer` (`upload/catalog/controller/product/manufacturer.php`)

All four listing controllers now share this one class and `ActiveTheme` —
Homepage Products blocks (Phase 10) is the next candidate consumer once
that phase's Block Provider/Renderer exists.
