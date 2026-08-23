# 003 — Page-template fallback fix: the Theme API milestone wasn't actually end-to-end

## Context

`docs/adr/002-theme-api-milestone-proof.md` recorded a "pass, with one
caveat" verdict for the `CLAUDE.md` §19 / `MINTCART_TECHNICAL_PLAN.md` §45
milestone, based on `MintCart\Design\ComponentRenderer::resolve()` correctly
finding `test_theme`'s `product-card.twig` override in a unit test against
real repository files. The stated caveat was that no live install existed
yet to confirm the override actually *renders* on a real request.

A live install became available and the milestone was re-tested manually
(`docs/MANUAL_VERIFICATION.md`'s Sub-step 3 section): set `config_theme` to
`test_theme`, load a real category page.

**Result: it failed.** The category page rendered, but with zero MintCart
markup anywhere — plain stock OpenCart output, as if `config_theme` had
never changed. A debug marker manually added to `test_theme`'s
`product-card.twig` did not appear in the response.

## Root cause

Two independent resolution mechanisms exist in this codebase, and only one
of them knew about `mint_default` as a fallback tier:

1. `MintCart\Design\ComponentRenderer::resolve()` — resolves individual
   *component* templates (e.g. `product-card`). Chain: active theme → its
   `theme.json` parent chain → `mint_default`. This one was correct and is
   what scenario 10's unit test actually exercised.
2. `catalog/controller/event/theme.php` (OpenCart's own, unmodified until
   this fix) — resolves whole-*page* route templates (e.g.
   `product/category`) via the `view/*/before` event fired from every
   `Loader::view()` call. Chain: active theme → `default`. **No
   `mint_default` tier existed here at all.**

`ControllerProductCategory::index()` renders the page via
`$this->load->view('product/category', $data)` (mechanism 2), while
building each product's card HTML via `ComponentRenderer` (mechanism 1) and
stashing it in `$data['product_cards']`.

`mint_default` happens to work because it ships its own full
`template/product/category.twig` (added in Phase 8 sub-step 2), which reads
`product_cards`. `test_theme` was deliberately built to ship *only* a
component override, per the milestone's own premise ("a theme overrides one
component and gets everything else automatically") — so mechanism 2 found
no `test_theme/template/product/category.twig`, fell straight through to
`default`'s page template, and `default`'s template has no idea
`product_cards` exists. The HTML `ComponentRenderer` built was computed and
then never printed anywhere.

In other words: the exact scenario the milestone exists to prove — override
only the component, inherit the rest — is the one scenario that didn't
work, because "inherit the rest" for a whole page route silently meant
"fall to `default`", not "fall to `mint_default`".

## Decision

Extended `catalog/controller/event/theme.php`'s page-route resolution with
the same three-tier shape `ComponentRenderer` already uses: active theme →
`mint_default` (gated on the active theme shipping a `theme.json`, i.e.
being Mint-aware) → `default`. See `docs/upstream-diff.md` for the exact
diff and compatibility reasoning.

This is a second direct OpenCart core file modification (the first was
`category.php` in Phase 8 sub-step 2). It was chosen over inventing a
parallel MintCart-owned page-route resolver because `event/theme.php`
already correctly handles this for every other route (search, manufacturer,
checkout, ...); duplicating that logic elsewhere risked drift, while
extending the one hook OpenCart already calls for this exact purpose is the
smaller, more maintainable diff.

## Verdict

**Re-tested live after the fix: pass.** `curl` against the real running
install, before and after:

- Before: category page HTML contains zero `mc-product-card` occurrences
  with `test_theme` active.
- After: same request returns 5 product cards carrying `test_theme`'s own
  `mc-product-card--test` marker, no `quick-order`/`toggle-wishlist` (per
  `test_theme`'s deliberately minimal contract) — confirmed against a real
  Twig render on a real install, not a resolve()-only unit test.

Still to confirm (tracked in `docs/MANUAL_VERIFICATION.md`): switching
`config_theme` back to `mint_default` and confirming its own DOM returns —
the fallback has to hold in both directions, and only the override
direction has been observed live so far.

## Consequences

- `docs/adr/002-theme-api-milestone-proof.md`'s "pass" verdict is
  superseded by this ADR for the end-to-end claim specifically — its
  `resolve()`-level proof was correct as far as it went, but was not
  sufficient evidence for the milestone as a whole. Future ADRs recording a
  "pass, caveat: not observed live" verdict should be treated as
  provisional, not final, until the live check actually happens — this is
  exactly the failure mode that caveat was meant to flag, and it did.
- The fix is general: it benefits every future route (search, manufacturer,
  checkout, ...) and every future Mint-aware theme automatically, not just
  `test_theme`/`product/category`. No further per-route page-template
  plumbing should be needed for a theme that overrides only components.
- `docs/IMPLEMENTATION_STATUS.md`'s Phase 8 section is updated to reflect
  this fix and its live verification.

## A note on "0 controller patches"

`catalog/controller/event/theme.php` is, literally,
`class ControllerEventTheme extends Controller` — so this fix is a
controller patch in the strict textual sense of the `CLAUDE.md` §19
criterion. Worth being explicit about why this doesn't violate the
criterion's intent: the milestone's "0 controller patches" targets a
**per-theme** patch — needing to edit an OpenCart controller *specifically
because this one theme* wants a different look, which is exactly the
pre-MintCart Cartello/OCMOD pattern `CLAUDE.md` §74 describes and rejects.
This fix is the opposite shape: one small, generic, already-tracked
(`docs/upstream-diff.md`) core hook extended *once*, which now benefits
every current and future Mint-aware theme and every route uniformly —
`test_theme` itself required zero changes to make its override work after
this fix landed. The same reasoning already applied to `category.php`'s
Phase 8 sub-step 2 modification (also a controller, also tracked as
upstream diff, also justified the same way). If a *third* Mint-aware theme
ever needed its own bespoke controller edit to make its override work,
*that* would be the pattern to stop and reject.
