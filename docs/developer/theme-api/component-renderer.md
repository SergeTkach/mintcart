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

## `$kind` (Phase 10): resolving something other than `template/mint/components/`

The constructor takes an optional 3rd `string $kind = 'components'`
parameter — it's what `componentPath()`/`render()` interpolate into
`template/mint/<kind>/<name>.twig` instead of the hardcoded
`components`. `MintCart\Blocks\BlockRenderer`
(`docs/developer/blocks/products.md`) is the one real consumer, passing
`'blocks'` so `template/mint/blocks/<type>.twig` reuses this class's
already-tested fallback walk instead of a second copy of it. Every
existing 2-argument call site is unaffected (`$kind` defaults to
`'components'`, unchanged behavior).

## `$diagnostics` (Phase 10): render-time instrumentation

The constructor's optional 4th argument is `?MintCart\Core\Diagnostics
$diagnostics = null` (`docs/developer/core/diagnostics.md`,
`MINTCART_TECHNICAL_PLAN.md` §35). `render()` is the one method every
component render — `product-card`, blocks, header, footer, anything
resolved through this class regardless of `$kind` — already passes
through, so this is the single choke point that instruments every current
and future consumer without touching each call site individually.

When a `Diagnostics` instance is passed, `render()`:

- increments its `component_render` counter once per call that actually
  reaches Twig (see below for what does *not* count), and
- times the Twig render itself into the `component_render` duration
  bucket (`Diagnostics::time()` accumulates across calls, so a listing
  page's 20-40 product-card renders sum into one total).

The "intentionally empty override" short-circuit (`$code === ''`, see
above) returns before any of this — an empty override does no real
rendering work, so counting it would inflate the numbers with calls that
never touched Twig. Same for a thrown resolution error (`resolve()`
finding the component nowhere in the chain): the exception propagates
before `Diagnostics` is ever touched.

`null` (the default) means zero behavior change — every existing 2-/3-
argument call site is unaffected, same guarantee `$kind` already has.
`ControllerProductCategory` is the first real caller passing one, gated
behind a `mintcart_dev_mode` setting read via `$this->config->get()`
(standard OpenCart settings storage — `CLAUDE.md` §6, no new Mint table);
when enabled, the controller appends the resulting
`$diagnostics->summary()` as an HTML comment to the response body (never
into `$data`/the template itself, so no theme override can accidentally
surface it in visible markup — Functional Scope §19's "Production UI не
повинен показувати sensitive debug data"). Real DB query counting is
still not wired anywhere — that remains the deliberately deferred,
separate upstream-diff decision `docs/developer/core/diagnostics.md`
documents.

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

- **One controller wired so far.** `ControllerProductCategory`
  (`docs/upstream-diff.md` has the full compatibility rationale) calls both
  classes per product row and renders the result through the new
  `mint_default/template/product/category.twig` page. Search, Manufacturer
  and Special still build their own inline product arrays — same pattern,
  different controller, still to do.
- **Milestone proof done, with one caveat.** `catalog/view/theme/test_theme/`
  overrides only `product-card.twig`, with a genuinely different DOM, and
  `resolve()` correctly returns test_theme's file when it's active and Mint
  Default's file otherwise (`tests/mintcart/design/component-renderer-test.php`
  scenario 10; full writeup in `docs/adr/002-theme-api-milestone-proof.md`).
  What's proven is the resolution mechanism; `render()`'s actual Twig
  rendering of the override still needs a human on a real install (no
  MySQL/`vendor/twig/twig` in this environment).
- **Only `product-card` has a Mint Default template today.** `header`,
  `footer` and other future components (`MINTCART_IMPLEMENTATION_GUIDE.md`
  §26) will each need their own `catalog/view/theme/mint_default/template/mint/components/<name>.twig`
  file as they are built (Phase 6+ per the technical plan) — `ComponentRenderer`
  itself does not care how many components exist, it only resolves the ones
  it's asked for.
- **No caching across `render()` calls.** Each call builds its own
  `\Template('Twig')` instance, which builds its own `\Twig\Environment`
  internally. `ControllerProductCategory` now calls `render()` once per
  product in a listing, so a category page with 20-40 products builds
  20-40 separate `Twig\Environment` instances rather than one reused across
  the loop. Not addressed here per `MINTCART_IMPLEMENTATION_GUIDE.md` §67 ("start with
  measurements", not a cache built ahead of an observed cost) — revisit once
  this can actually be profiled against a real database and a real Twig
  install, which this environment still doesn't have (see "No OpenCart
  dependency in `resolve()`" above).
