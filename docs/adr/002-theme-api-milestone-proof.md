# 002 — Theme API milestone proof: test_theme overriding only product-card

## Context

`CLAUDE.md` §19 and `MINTCART_TECHNICAL_PLAN.md` §45 set a hard gate: MintCart
must not scale into broad feature build-out (Phase 9+) until it is proven that
a second theme can override a single component and get everything else from
Mint Default automatically, with:

```text
0 OCMOD
0 controller patches
0 copied business logic
```

Phase 8 sub-steps 1–2 built the mechanism (`MintCart\Design\ComponentRenderer`,
`mint_default`'s real `product-card.twig`, `ControllerProductCategory` wired to
both). This ADR records the outcome of sub-step 3, the actual proof.

## Decision

Added `upload/catalog/view/theme/test_theme/`:

- `theme.json` with `"parent": null` — deliberately **not** declaring
  `mint_default` as a parent. This is a stronger version of the proof than a
  theme that opts in via `parent`: `ComponentRenderer::resolve()` walks the
  active theme's own parent chain first, but after that chain is exhausted it
  always checks Mint Default regardless of whether Mint Default was ever named
  (see `componentrenderer.php`'s `resolve()` — the `MINT_DEFAULT_THEME` check
  after the `while` loop is unconditional). A pass here shows the fallback is
  automatic for *any* Mint-aware theme, not merely one that explicitly
  extends Mint Default.
- `template/mint/components/product-card.twig` — the **only** file test_theme
  ships. Its DOM is genuinely different from Mint Default's, not a renamed
  copy: a single badge (`product.badges[0]`) instead of the full badge list,
  no secondary-image hover swap, no wishlist/compare quick-action buttons, no
  quick-order button. It still consumes exactly the same
  `product`/`display`/`context` contract and the same `text_*` strings
  documented in `docs/developer/components/product-card.md` — a real override
  has to satisfy the same contract as the component it replaces, not invent
  its own.

`tests/mintcart/design/component-renderer-test.php` scenario 10 exercises this
against the real, shipped repository files (the same technique scenario 8 used
for `mint_default` alone, not a fixture directory):

1. `ComponentRenderer($realThemesRoot, 'test_theme')->resolve('product-card')`
   returns `test_theme`'s own file, not Mint Default's.
2. That file's markup carries `test_theme`'s own marker
   (`mc-product-card--test`) and does **not** contain `toggle-wishlist` —
   confirming it is a genuinely different template, not a copy.
3. `ComponentRenderer($realThemesRoot, 'mint_default')->resolve('product-card')`
   still returns Mint Default's own file — the fallback direction has to hold
   too, not just the override direction.

All 10 scenarios pass (`php tests/mintcart/design/component-renderer-test.php`).

## Verdict

**Pass, with one caveat.** The resolution mechanism itself — the part
`ComponentRenderer` is actually responsible for — is proven: a second theme
gets a different `product-card` DOM with zero OCMOD, zero controller edits,
and zero duplicated `ProductViewProvider`/`ControllerProductCategory` logic.
Both theme directories are ordinary files under `catalog/view/theme/`; nothing
about adding `test_theme` touched any OpenCart core file, any `install.xml`,
or any PHP outside the test file itself.

The caveat is real and stated plainly rather than glossed over: this
environment has no MySQL server and no `vendor/twig/twig` installed (no
`composer install` has been run against `upload/composer.json`), the same
pre-existing gap noted in every earlier Phase 8 entry in
`docs/IMPLEMENTATION_STATUS.md`. `ComponentRenderer::render()` — which
delegates to OpenCart's own `\Template('Twig')` adaptor to actually turn the
resolved `.twig` file into HTML — is not exercised by this test, only
`resolve()`'s file-path logic is. A human still needs to: set `config_theme`
to `test_theme` on a real install, load a live category page, confirm the
override HTML actually renders (not just that the right file path was found),
then switch back to `mint_default` and confirm its own DOM returns. Until that
live check happens, treat this ADR as "the resolution mechanism is proven;
the rendering pipeline it feeds is still assumed, not observed, to work".

## Consequences

- The Theme API's core promise — override one component, inherit the rest —
  is validated well enough to unblock Phase 9+ (Presets) and Phase 7
  (Header/Footer), per the ordering in `CLAUDE.md` §21.
- `test_theme` stays in the repository as a permanent regression fixture for
  `ComponentRenderer`'s real-file resolution (scenario 10), the same role
  `mint_default` already plays for scenario 8 — it is not meant to be deleted
  once the milestone is "proven"; deleting it would remove the regression
  coverage.
- `.gitignore`'s broad `upload/**/test*` rule (meant for ad-hoc runtime/scratch
  files an admin might create on a live install) needed a targeted negation to
  stop swallowing this real, source-controlled theme directory. Any *other*
  future path starting with `test` under `upload/` is still ignored by
  default — only `upload/catalog/view/theme/test_theme/` is exempted.
- The "human must verify on a real install" caveat is not new debt introduced
  here — it is the same no-MySQL/no-Twig-vendor gap already tracked against
  every prior Phase 6/8 entry. This ADR does not resolve it; it only confirms
  that the piece of the milestone actually testable in this environment
  passes cleanly.
