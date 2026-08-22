# Upstream diff

Tracks every direct modification to an OpenCart 3.0.5.1 core file
(`CLAUDE.md` §11, `MINTCART_TECHNICAL_PLAN.md` §41). Every entry here is
technical debt that needs justification — the goal is to keep this list as
small as possible, and to move logic into the MintCart layer instead of the
core file whenever practical.

Files that MintCart adds from scratch (new `MintCart\*` classes, new theme
directories, new `mc_*` tables) are **not** upstream diffs — they don't
modify anything OpenCart shipped. Only edits to a file that already existed
in the OpenCart 3.0.5.1 baseline belong on this list.

---

## `upload/catalog/controller/product/category.php`

**Reason:** Wires the Theme API's first real page (Phase 8,
`MINTCART_TECHNICAL_PLAN.md` §11/§15C-D) — for each product row, in addition
to the controller's existing inline array building, also builds a
`MintCart\Commerce\ProductViewProvider` result and renders it through
`MintCart\Design\ComponentRenderer::render('product-card', ...)` into a new
`$data['product_cards']` array. Adds a small private
`mintActiveThemeDirectory()` helper mirroring
`catalog/controller/event/theme.php`'s own active-theme resolution.

**Why it can't live in the MintCart layer instead:** `ProductViewProvider`
and `ComponentRenderer` are deliberately OpenCart-agnostic (no `\Registry`
dependency in the parts that can avoid it) so they're unit-testable in
isolation — but *something* still has to call them with this controller's
already-resolved product data (formatted price/special/tax, resized image,
rating) once per row. That call site is inherently controller-specific:
OpenCart's own `Loader`/routing has no generic "run this for every listing
controller" hook, and Technical Plan §15C's reusable listing pipeline
(`ProductListingService`) doesn't exist yet — building it now, ahead of a
second real consumer (Search/Manufacturer/Special), would be exactly the
premature abstraction `CLAUDE.md` §85 warns against. This wiring is the
first of several near-identical call sites; extracting the shared shape
into a real `ProductListingService` is expected once Search/Manufacturer/
Special need the same thing (`docs/IMPLEMENTATION_STATUS.md`'s Phase 8
notes call this out explicitly).

**Compatibility impact:** None for stores on the legacy `default` OpenCart
theme, or on any unrelated third-party OpenCart theme. The new code only
runs when the active theme (`config_theme`, resolved the same way
`event/theme.php` already does) ships a `theme.json` manifest — i.e. it is
Mint-API-aware. Checking for that manifest, rather than only excluding the
literal string `'default'`, matters because a third-party OC3 theme is
neither `'default'` nor Mint-aware: without the manifest check, every
category page load on such a theme would still build a
`ComponentRenderer`/`ProductViewProvider` and render a `product-card` for
every listed product, purely wasted work its own template never reads.
Under the manifest-based gate, no non-Mint theme (stock `default` or
third-party) runs any of this new code, and the controller's original
`$data['products']` array is completely unchanged either way: same keys,
same values, same order, same computation.

A failure inside the new pipeline (e.g. a missing/unreadable
`product-card.twig`) is caught per-product and disables the pipeline for
the rest of that request rather than propagating — a broken Mint component
degrades that page's product-card grid, it does not take down the whole
category page for every visitor.

**Tests:** No live end-to-end request test — this environment has no MySQL
server and no `vendor/twig/twig` installed (`composer install` has not been
run against `upload/composer.json`), the same pre-existing gap noted in
every earlier MintCart PR. Verified instead: `php -l` (clean);
`tests/mintcart/design/component-renderer-test.php` scenario 9 confirms
`ProductViewProvider::build()`'s output shape is exactly what
`product-card.twig` expects, using the same field names this controller now
passes (`docs/developer/components/product-card.md`'s documented contract);
scenario 8 confirms `ComponentRenderer` resolves the real, shipped
`mint_default/template/mint/components/product-card.twig` file at its real
repository path, not just a fixture. A human should still smoke-test a real
category page on a configured install before this reaches production.

---

## `upload/catalog/language/en-gb/product/category.php`

**Reason:** Adds five new language strings the `product-card` component's
documented contract needs and that didn't already exist under any name:
`text_rated`, `text_quick_order`, `text_badge_sale`, `text_badge_out_of_stock`,
`text_badge_new`. (`text_wishlist`/`text_compare`/`text_add_to_cart` reuse
the existing global `button_wishlist`/`button_compare`/`button_cart` strings
from `en-gb.php` instead of duplicating them under new keys.)

**Why it can't live in the MintCart layer instead:** OpenCart's language
system is file-per-route, loaded by `$this->load->language('product/category')`
in the controller above — there's no separate MintCart-owned language
loading mechanism, and inventing one for five strings would be the kind of
parallel system `CLAUDE.md` §39 says not to build when OpenCart's existing
mechanism already does the job.

**Compatibility impact:** Purely additive — five new keys appended after
the existing ones, nothing removed or renamed. Only `en-gb` exists in this
repo today (no other locale directories to keep in sync).

**Tests:** `php -l` (clean). No automated i18n-completeness check exists in
this repo yet for any language file.
