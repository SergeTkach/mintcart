# Manual verification checklist

This development environment has no MySQL server and no `vendor/twig/twig`
installed (no `composer install` has been run against `upload/composer.json`).
Every PR so far has been verified as far as that allows — `php -l`, standalone
unit/smoke tests, static Twig tag-balance checks — but **none of it has been
exercised through a real HTTP request against a configured OpenCart install**.

This file tracks what still needs a human, on a real install (DB configured,
`composer install` run, demo product data), before treating the
corresponding work as done rather than "believed correct, unverified
end-to-end". Check items off as you confirm them; if something fails, open
an issue/PR referencing the item below rather than silently fixing and
forgetting to update this file.

---

## Phase 8 — Theme resolver

Source PRs: #9 (`ComponentRenderer` + `mint_default`'s `product-card.twig`),
#10 (`ControllerProductCategory` wiring).

- [x] **Category page on `mint_default`.** Set `config_theme` to `mint_default`,
      open a real category page with several products (include at least one
      with a `special` price, one with a `rating`, one out of stock). Confirm:
  - [x] The page renders without a PHP fatal/Twig exception (this is the
        first time `product-card.twig` is actually compiled by Twig —
        so far only checked for balanced `{% %}` tags by a script, never
        parsed by the real Twig engine). **Confirmed 2026-08-23** against a
        real install (`http://mintcart.localhost`, path=18/Laptops &
        Notebooks). Found and fixed one real bug in the process: `Ex Tax::`
        (double colon) in the tax line — see `product-card.twig`'s fix and
        `docs/IMPLEMENTATION_STATUS.md`'s Phase 8 notes.
  - [~] Product cards show the correct image, name, price/special/tax,
        rating stars, and badges (`sale` / `out-of-stock` / `new` where
        applicable) matching what the product actually has. **Confirmed
        2026-08-23** via `curl` across several demo categories: image/name/
        price/tax correct (path=18); `special` price renders correctly with
        struck-through original ($110/$122, $98/$122) plus a
        `mc-badge--sale` "Sale" badge (path=20); an out-of-stock product
        shows `mc-badge--out-of-stock` and a stock line both correctly
        using that specific product's own configured stock-status label
        ("Pre-Order", not a generic hardcoded string) (path=57). **Not
        confirmed:** `rating` stars — no product across path=18/20/33/34/57
        had a non-zero rating (this demo dataset has no approved reviews
        seeded); would need a review manually added in admin to exercise.
  - [x] An out-of-stock product's "Add to Cart" button is **disabled** when
        `config_stock_checkout` (Settings → Stock → "Stock Checkout Warning"
        / backorders) is **off**, and **enabled** when it's **on** — this is
        the exact backorder gap found and fixed in PR #10's review
        (`MintCart\Commerce\ProductViewProvider::buildActions()`,
        `allow_out_of_stock_purchase`). Toggle the setting and check both
        states; this logic has never run against real config values.
        **Confirmed 2026-08-23** on "Samsung Galaxy Tab 10.1" (out-of-stock,
        path=57, `mint_default` active) via `curl`: `config_stock_checkout`
        **on** → button renders with no `disabled` attribute; **off** →
        button renders with `disabled` present. Both states verified on the
        same product, same page, config toggled between fetches.
  - [x] Breadcrumbs, category refine list, sort/limit dropdowns, and
        pagination all work (they reuse the controller's existing data,
        unchanged by PR #10, but are rendered by the *new*
        `mint_default/template/product/category.twig` page for the first
        time). **Confirmed 2026-08-23** — all present and correct in the
        live page.
  - [~] Header/footer/column_left/column_right render normally (they're
        OpenCart's existing pre-rendered HTML, embedded as-is — should be
        unaffected, but confirm). Header/left column confirmed visible and
        correct; footer not explicitly checked.
- [x] **Category page on the legacy `default` theme.** Set `config_theme`
      back to `default` (or leave it, if that's still the store's active
      theme) and load the same category page. Confirm it renders **exactly**
      as it did before PR #10 — same markup, same data — since the new
      pipeline is designed to run zero new code on this theme. This is the
      regression check for the "zero behavior change on `default`" claim in
      `docs/upstream-diff.md`. **Confirmed 2026-08-23** via `curl`: zero
      `mc-*` markers, only stock OpenCart markup (`product-layout`,
      `product-thumb`, `button-group`), byte-identical across two separate
      fetches — `default` is fully unaffected by every change made in this
      round (ADR 003's `event/theme.php` fix included, since it explicitly
      skips its new branch when `config_theme == 'default'`).
- [ ] **N/A for now** — no third-party theme is installed on this dev
      environment. Revisit if/when one is installed for testing.
      **A third-party / non-Mint theme, if one is installed.** Confirm it
      also renders unaffected, and that `MintCart\Design\ComponentRenderer`/
      `MintCart\Commerce\ProductViewProvider` are **not** instantiated for
      that request (e.g. via a quick `error_log`/Xdebug breakpoint check) —
      this confirms the `theme.json`-presence gate in
      `ControllerProductCategory::index()` actually excludes non-Mint themes,
      not just the literal string `'default'`.
- [x] **Broken-component fallback.** Temporarily rename/remove
      `catalog/view/theme/mint_default/template/mint/components/product-card.twig`
      and reload the category page on `mint_default`. Confirm the page still
      renders (product cards area empty/degraded, `mintcart.log` gets an
      ERROR line via `MintCart\Core\Logger`) instead of a fatal error — this
      exercises the try/catch added in PR #10's review and has never
      actually been triggered. Restore the file afterward. **Confirmed
      2026-08-23, main claim holds, one loose end:** with the file renamed
      away, the live category page still returned HTTP 200, header/nav
      intact, product-card markup gone (graceful degrade, no fatal) — the
      try/catch works as designed. **But** `mintcart.log` was never created
      by the live request. Isolated `php -r` tests (bypassing HTTP/Apache
      entirely) independently confirmed both halves of the mechanism are
      correct in isolation: `ComponentRenderer::resolve()` does throw
      `RuntimeException` for the missing file, and `MintCart\Core\Logger`
      does successfully write a line when invoked directly. The most likely
      explanation is a local-environment permission gap between the account
      Apache runs as and write access to `upload/system/storage/logs/` on
      this machine, not a code defect — but this wasn't root-caused further.
      If this matters for production readiness later, check that Apache's
      (or whatever serves PHP) process user can create new files in that
      directory, not just append to ones that already exist.

## Sub-step 3 (once merged) — the Theme API milestone proof

- [x] Set `config_theme` to the second test theme. Confirm the product-card
      renders that theme's **override** DOM (visibly different from Mint
      Default's), not Mint Default's. **Confirmed 2026-08-23 — but only
      after a real bug fix.** First attempt: `test_theme` (component-only
      override, no page templates of its own) rendered **zero** MintCart
      markup — plain stock `default` output, because OpenCart's own
      whole-page route resolver (`catalog/controller/event/theme.php`) had
      no `mint_default` fallback tier, only `ComponentRenderer` did. Fixed
      by adding that tier (see `docs/adr/003-page-template-fallback-fix.md`,
      `docs/upstream-diff.md`). Re-verified via `curl` after the fix:
      `mc-product-card--test` present on all 5 listed products, no
      `quick-order`/`toggle-wishlist` (per `test_theme`'s contract).
- [x] Confirm every other part of the page (header/footer/breadcrumbs/etc.)
      still renders normally via the Mint Default fallback. **Confirmed
      2026-08-23** — with `test_theme` active, breadcrumbs/refine/sort/
      pagination/header/nav all present in the live response (the page
      shell now resolves to `mint_default`'s `product/category.twig` via
      the ADR 003 fix, since `test_theme` has none of its own).
- [x] Switch `config_theme` back to `mint_default` and confirm the original
      Mint Default product-card DOM returns — the fallback has to work in
      both directions, not just override-away-from-default. **Confirmed
      2026-08-23** via `curl`: `test_theme`'s markers (`mc-product-card--test`,
      the manually-added debug text) are gone, full Mint Default DOM is back
      (`__media`, `__quick-actions`, `__quick-order`, 10 wishlist/compare
      action buttons across 5 products), and the `Ex Tax:` fix persists
      (single colon, 5 occurrences).
- [~] Confirm no OCMOD, no controller patch, and no copied business logic
      was needed to make the second theme's override work (`CLAUDE.md` §19).
      **Nuanced, not a clean pass**: making this actually work end-to-end
      required a second core controller edit
      (`catalog/controller/event/theme.php`, `docs/adr/003-...md`) — `0
      OCMOD` and `0 copied business logic` hold, but `0 controller patches`
      is only true in the sense that `test_theme` itself needed no
      theme-specific patch (the fix is generic, one-time, shared by every
      Mint-aware theme and route). See ADR 003's "A note on '0 controller
      patches'" section for the full reasoning — don't silently mark this
      a clean pass without reading it.

---

## Phase 9 — Design presets

Source PR: #22 (`presets/clean.css`, `presets/warm.css`,
`docs/developer/theme-api/presets.md`, `docs/adr/004-design-token-presets.md`).

The preset *contract* is machine-checked (`npm test` — each preset file has
exactly one `:root[data-mc-preset='<code>']` block and redeclares only
tokens `settings/tokens.css` already declares). What no check can confirm is
whether the result actually **looks** like a coherent, radically different
design, which is the whole point of Phase 9. Nothing writes
`data-mc-preset` at runtime yet, so set it by hand:

```js
document.documentElement.dataset.mcPreset = 'warm';   // or 'clean'
```

On a real install with `config_theme` = `mint_default` and a category page
of demo products loaded:

- [ ] **Warm preset renders as a coherent design.** With
      `data-mc-preset="warm"` set, confirm the page reads as one deliberate
      look, not a half-applied palette: terracotta primary buttons, cream
      surfaces, serif headings, visibly rounder corners, roomier spacing,
      warm-tinted shadows.
- [ ] **Nothing is left behind on the mint palette.** Look specifically for
      any element still showing mint green or the neutral gray borders —
      each one is a component with a hardcoded color instead of a semantic
      token (ADR 004 makes reading semantic tokens a hard requirement, so
      any hit here is a component bug to fix, not a preset bug).
- [ ] **Text contrast holds in Warm.** Body text, muted text, and text on
      primary/accent backgrounds should still meet WCAG 2.2 AA
      (`MINTCART_FUNCTIONAL_SCOPE.md` §27). The warm palette was picked for
      contrast on paper but has never been measured against real rendered
      output.
- [ ] **Removing the attribute returns the Clean look**, identical to
      setting `data-mc-preset="clean"` — the two are meant to be the same
      state by design (see ADR 004 decision 3).
- [ ] **The same two presets applied to `test_theme`** (different component
      DOM, same semantic tokens) also both render coherently. This is the
      property that makes presets and full themes orthogonal; it has only
      been reasoned about, never seen.

---

Add new sections above as later phases land more unverified-in-this-sandbox
work — don't let this file go stale once it exists.
