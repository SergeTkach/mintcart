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

- [ ] **Category page on `mint_default`.** Set `config_theme` to `mint_default`,
      open a real category page with several products (include at least one
      with a `special` price, one with a `rating`, one out of stock). Confirm:
  - [ ] The page renders without a PHP fatal/Twig exception (this is the
        first time `product-card.twig` is actually compiled by Twig —
        so far only checked for balanced `{% %}` tags by a script, never
        parsed by the real Twig engine).
  - [ ] Product cards show the correct image, name, price/special/tax,
        rating stars, and badges (`sale` / `out-of-stock` / `new` where
        applicable) matching what the product actually has.
  - [ ] An out-of-stock product's "Add to Cart" button is **disabled** when
        `config_stock_checkout` (Settings → Stock → "Stock Checkout Warning"
        / backorders) is **off**, and **enabled** when it's **on** — this is
        the exact backorder gap found and fixed in PR #10's review
        (`MintCart\Commerce\ProductViewProvider::buildActions()`,
        `allow_out_of_stock_purchase`). Toggle the setting and check both
        states; this logic has never run against real config values.
  - [ ] Breadcrumbs, category refine list, sort/limit dropdowns, and
        pagination all work (they reuse the controller's existing data,
        unchanged by PR #10, but are rendered by the *new*
        `mint_default/template/product/category.twig` page for the first
        time).
  - [ ] Header/footer/column_left/column_right render normally (they're
        OpenCart's existing pre-rendered HTML, embedded as-is — should be
        unaffected, but confirm).
- [ ] **Category page on the legacy `default` theme.** Set `config_theme`
      back to `default` (or leave it, if that's still the store's active
      theme) and load the same category page. Confirm it renders **exactly**
      as it did before PR #10 — same markup, same data — since the new
      pipeline is designed to run zero new code on this theme. This is the
      regression check for the "zero behavior change on `default`" claim in
      `docs/upstream-diff.md`.
- [ ] **A third-party / non-Mint theme, if one is installed.** Confirm it
      also renders unaffected, and that `MintCart\Design\ComponentRenderer`/
      `MintCart\Commerce\ProductViewProvider` are **not** instantiated for
      that request (e.g. via a quick `error_log`/Xdebug breakpoint check) —
      this confirms the `theme.json`-presence gate in
      `ControllerProductCategory::index()` actually excludes non-Mint themes,
      not just the literal string `'default'`.
- [ ] **Broken-component fallback.** Temporarily rename/remove
      `catalog/view/theme/mint_default/template/mint/components/product-card.twig`
      and reload the category page on `mint_default`. Confirm the page still
      renders (product cards area empty/degraded, `mintcart.log` gets an
      ERROR line via `MintCart\Core\Logger`) instead of a fatal error — this
      exercises the try/catch added in PR #10's review and has never
      actually been triggered. Restore the file afterward.

## Sub-step 3 (once merged) — the Theme API milestone proof

- [ ] Set `config_theme` to the second test theme. Confirm the product-card
      renders that theme's **override** DOM (visibly different from Mint
      Default's), not Mint Default's.
- [ ] Confirm every other part of the page (header/footer/breadcrumbs/etc.)
      still renders normally via the Mint Default fallback.
- [ ] Switch `config_theme` back to `mint_default` and confirm the original
      Mint Default product-card DOM returns — the fallback has to work in
      both directions, not just override-away-from-default.
- [ ] Confirm no OCMOD, no controller patch, and no copied business logic
      was needed to make the second theme's override work (`CLAUDE.md` §19).

---

Add new sections above as later phases land more unverified-in-this-sandbox
work — don't let this file go stale once it exists.
