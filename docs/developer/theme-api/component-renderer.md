# `MintCart\Design\ComponentRenderer`

The Theme API's component resolver (`MINTCART_TECHNICAL_PLAN.md` §11,
`MINTCART_IMPLEMENTATION_GUIDE.md` §28-29). This is the mechanism that makes
`CLAUDE.md` §19's "first architecture milestone" possible: a new theme can
override a single component and get every other component from Mint Default
automatically, with 0 OCMOD and 0 controller patches.

PHP: `upload/system/library/mintcart/design/componentrenderer.php`
Test: `tests/mintcart/design/component-renderer-test.php`

## Why this is separate from OpenCart's `event/theme.php`

OpenCart 3 already resolves **whole page routes** against the active theme:
`catalog/controller/event/theme.php` checks
`catalog/view/theme/<active>/template/<route>.twig`, falling back to
`catalog/view/theme/default/template/<route>.twig`, and sets
`template_directory` accordingly before `Loader::view()` renders the page.

A MintCart **component** (`product-card`, and later `header`/`footer`/etc.)
is different: it is included by many unrelated page routes (Category,
Search, Manufacturer, Special, Homepage Blocks), so its override lookup
can't be tied to whichever route happens to be rendering it. `ComponentRenderer`
provides the same override → fallback idea, scoped to components instead of
routes, and reusable from any controller or block provider.

## Resolution order

```text
active theme (template/mint/components/<component>.twig)
  -> theme.json "parent" chain (MINTCART_IMPLEMENTATION_GUIDE.md §30), if any
  -> mint_default (template/mint/components/<component>.twig)
```

A missing or malformed `theme.json` is treated as "no parent" rather than a
hard failure — a theme without a valid manifest simply doesn't extend
anything; it does not break resolution for anything else in the chain. A
cyclic parent chain (`A` parents `B`, `B` parents `A`) cannot infinite-loop —
each theme code is only ever checked once — and still falls through to Mint
Default if the component is found nowhere in the cycle.

If the component exists nowhere in the chain — **including Mint Default** —
`resolve()` throws. Every documented component (`docs/developer/components/*.md`)
must ship a Mint Default implementation; a theme overrides business-data-driven
markup, it does not invent a component Mint Default never defined
(`MINTCART_IMPLEMENTATION_GUIDE.md` §29).

## No OpenCart dependency in `resolve()`

Same rationale as `MintCart\Commerce\ProductViewProvider`: `ComponentRenderer`
takes a themes root directory and the active theme's directory name as plain
strings (a caller resolves those from OpenCart's `config_theme` /
`theme_default_directory` settings — not duplicated here, per `CLAUDE.md` §6).
`resolve()` only touches the filesystem, so it is fully unit-testable without
bootstrapping OpenCart, which is exactly how
`tests/mintcart/design/component-renderer-test.php` exercises all six
fallback/edge-case scenarios against real fixture directories under
`sys_get_temp_dir()`.

`render()` is the one method with an OpenCart dependency: it delegates to
the existing `\Template('Twig')` adaptor (`system/library/template.php` /
`system/library/template/twig.php`) to actually render the resolved file's
Twig markup, reusing OpenCart's own Twig integration rather than
reimplementing it (`MINTCART_IMPLEMENTATION_GUIDE.md` §9's allowance for
wrapping an existing mechanism). This is not exercised by the smoke test —
this environment has no `vendor/twig/twig` installed (no `composer install`
has been run against `upload/composer.json`), which is a pre-existing gap
affecting every Twig-rendered OpenCart page in this repo when run outside
the full web request lifecycle, not something introduced by this class.

## Usage

```php
$renderer = new \MintCart\Design\ComponentRenderer(
    DIR_TEMPLATE,           // catalog/view/theme/
    $activeThemeDirectory   // resolved from config_theme / theme_default_directory,
                            // the same way event/theme.php already does
);

$html = $renderer->render('product-card', [
    'product' => $productView['product'],
    'display' => $productView['display'],
    'context' => $productView['context'],
    'text_badge' => ['sale' => $this->language->get('text_sale'), /* ... */],
    'text_wishlist' => $this->language->get('text_wishlist'),
    // ...the rest of product-card.md's documented text_* strings
]);
```

`$productView` is a `MintCart\Commerce\ProductViewProvider::build()` result
(`docs/developer/commerce/product-view-provider.md`).

## What this does not do yet

- **No controller wiring yet.** No controller calls `ComponentRenderer` or
  `ProductViewProvider` together yet — `ControllerProductCategory` (the
  simplest existing listing controller) still builds its own product array
  and renders `product/category.twig`'s inline product loop. That wiring,
  plus a second minimal test theme proving the override milestone
  end-to-end, is the next increment (`docs/IMPLEMENTATION_STATUS.md`'s
  Phase 8 section has the concrete plan).
- **Only `product-card` has a Mint Default template today.** `header`,
  `footer` and other future components (`MINTCART_IMPLEMENTATION_GUIDE.md`
  §26) will each need their own `catalog/view/theme/mint_default/template/mint/components/<name>.twig`
  file as they are built (Phase 6+ per the technical plan) — `ComponentRenderer`
  itself does not care how many components exist, it only resolves the ones
  it's asked for.
- **No caching across `render()` calls.** Each call builds its own
  `\Template('Twig')` instance, which builds its own `\Twig\Environment`
  internally. Once controller wiring (the next increment) calls `render()`
  once per product in a listing, this means one `Twig\Environment` per
  product on that page rather than one reused across the loop. Not
  addressed here per `MINTCART_IMPLEMENTATION_GUIDE.md` §67 ("start with
  measurements", not a cache built ahead of an observed cost) — revisit once
  controller wiring exists and this can actually be profiled on a real
  listing page.
