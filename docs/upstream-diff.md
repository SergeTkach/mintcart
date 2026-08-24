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

## `upload/catalog/controller/event/theme.php`

**Reason:** Found via live manual verification (`docs/MANUAL_VERIFICATION.md`,
`docs/adr/003-page-template-fallback-fix.md`) — this is OpenCart's own
whole-*page*-route template resolver (distinct from
`MintCart\Design\ComponentRenderer`, which only resolves individual
component templates like `product-card`). It only ever had a two-tier
fallback: active theme's own file for this route, else `default`'s. A
Mint-aware theme that overrides *only* a component and ships no page
templates of its own — `catalog/view/theme/test_theme/`, exactly the
`CLAUDE.md` §19 milestone scenario — fell straight through to the legacy
`default` theme's page template for every route, silently discarding
anything `ControllerProductCategory` built via `ComponentRenderer` for that
page (`default`'s templates never read `$data['product_cards']`). Added a
`mint_default` middle tier to the fallback chain: active theme → `mint_default`
(only if the active theme ships a `theme.json`, i.e. is Mint-aware) → `default`.

**Why it can't live in the MintCart layer instead:** This is OpenCart's own
route-to-template resolution, triggered on every `Loader::view()` call via
the `view/*/before` event — there is no MintCart-owned equivalent for whole
pages (only for components, via `ComponentRenderer`). Reimplementing
page-route resolution as a parallel MintCart mechanism and bypassing this
core file entirely would duplicate logic this file already gets right for
every other route (search, manufacturer, checkout, ...) and risk diverging
from it. Extending this one small, already-existing, single-purpose hook is
the smaller diff.

**Compatibility impact:** None for the legacy `default` theme (`config_theme
== 'default'` skips the new branch entirely — same code path as before) or
any third-party theme without a `theme.json` (the added tier is gated on
that manifest's presence, same gate `category.php`'s pipeline already uses).
For `mint_default` itself, the new tier is a no-op (its own directory is
excluded from being added as `mint_default` a second time), so route
resolution order for `mint_default` is unchanged. Only a *third* Mint-aware
theme (or a future one) that overrides some but not all page routes changes
behavior: those un-overridden routes now resolve through `mint_default`
before falling to `default`, instead of jumping straight to `default`.

**Tests:** `php -l` (clean). Verified live against a real install (MySQL +
this environment's own OpenCart install, not a sandbox): before the fix,
`catalog/view/theme/test_theme/template/mint/components/product-card.twig`'s
markup never appeared in the rendered category page HTML (confirmed via
`curl` — zero `mc-product-card` occurrences) despite `ComponentRenderer`
correctly resolving it in isolation; after the fix, the same request returns
`test_theme`'s distinct DOM (`mc-product-card--test`, no `quick-order`/
`toggle-wishlist`) for all 5 listed products. See
`docs/adr/003-page-template-fallback-fix.md` for the full writeup.

---

## `upload/catalog/controller/product/category.php`

**Reason:** Wires the Theme API's first real page (Phase 8,
`MINTCART_TECHNICAL_PLAN.md` §11/§15C-D) — for each product row, in addition
to the controller's existing inline array building, also builds a
`MintCart\Commerce\ProductViewProvider` result and renders it through
`MintCart\Design\ComponentRenderer::render('product-card', ...)` into a new
`$data['product_cards']` array.

**Update (this run):** refactored to call the newly-extracted
`MintCart\Design\ActiveTheme::directory()`/`isMintAware()` and
`MintCart\Design\ProductCardListRenderer::render()` instead of this
controller's own private `mintActiveThemeDirectory()` helper and inline
`try`/`catch`-and-disable loop — see
`docs/developer/theme-api/product-card-list-renderer.md`. No behavioral
change: same inputs, same outputs, same per-product degrade-on-failure
semantics, same `$data['products']` array untouched either way.

**Why it can't live in the MintCart layer instead:** `ProductViewProvider`
and `ComponentRenderer` are deliberately OpenCart-agnostic (no `\Registry`
dependency in the parts that can avoid it) so they're unit-testable in
isolation — but *something* still has to call them with this controller's
already-resolved product data (formatted price/special/tax, resized image,
rating) once per row. That call site is inherently controller-specific:
OpenCart's own `Loader`/routing has no generic "run this for every listing
controller" hook. `MintCart\Design\ProductCardListRenderer` (this run) now
carries the shared *shape* of that call (build + render + degrade), but a
controller still has to invoke it once per row with its own already-resolved
product data — Technical Plan §15C's fuller `ProductListingService`
(covering the *query*/pagination side too) remains future work, not
attempted here.

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
repository path, not just a fixture. **This run's refactor** additionally
verified: `tests/mintcart/design/product-card-list-renderer-test.php`
(8 scenarios, new) exercises `ProductCardListRenderer`'s degrade-on-failure
behaviour directly; full `tests/mintcart/` suite re-run end to end after
the refactor — still passing, no regressions. A human should still
smoke-test a real category page on a configured install before this
reaches production.

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

---

## `upload/catalog/controller/common/footer.php`

**Reason:** Wires Phase 7's Footer component (`MINTCART_TECHNICAL_PLAN.md`
§14, `MINTCART_IMPLEMENTATION_GUIDE.md` §48) — the second real page-level
consumer of the Theme API pattern `ControllerProductCategory` established
in Phase 8. In addition to the controller's existing per-field data
building (`$data['informations']`/`contact`/`return`/.../`newsletter`,
completely untouched), also regroups that same already-resolved data into
`MintCart\Design\FooterViewProvider` columns, reads contacts from
OpenCart's own `config_telephone`/`config_email`/`config_address` settings
(never a duplicate Mint-owned table — `CLAUDE.md` §39), and renders the
result through `MintCart\Design\ComponentRenderer::render('footer', ...)`
into a new `$data['mc_footer']` string.

**Update (this run):** refactored to call the newly-extracted
`MintCart\Design\ActiveTheme::directory()`/`isMintAware()` instead of this
controller's own private `mintActiveThemeDirectory()` helper — see
`docs/developer/theme-api/product-card-list-renderer.md`. `CLAUDE.md`
§85/§86 had this entry deliberately keep its own small copy rather than
extract a shared helper for just two call sites; `ControllerProductSearch`
(also this run) is the third, so the duplication is now extracted. No
behavioral change.

**Why it can't live in the MintCart layer instead:** Same rationale as
`ControllerProductCategory` — `FooterViewProvider` and `ComponentRenderer`
are deliberately OpenCart-agnostic, but something has to call them with
this controller's already-resolved footer data (links, contacts, "Powered
by" text) once per request. OpenCart's `Loader`/routing has no generic
"run this for every common controller" hook.

**Compatibility impact:** None for the legacy `default` theme or any
unrelated third-party theme — the new code only runs when the active theme
ships a `theme.json` manifest (same gate as `category.php`), and neither
`default`'s nor a third-party theme's own `common/footer.twig` reads
`$data['mc_footer']`. The controller's original per-field `$data` (still
what `default`'s `common/footer.twig` renders) is completely unchanged:
same keys, same values, same computation. A failure inside
`ComponentRenderer::render()` (e.g. a missing/unreadable `footer.twig`) is
caught and logged via `MintCart\Core\Logger` rather than propagating — a
broken Mint footer component degrades to an empty `{{ mc_footer }}` on
`mint_default`'s own `common/footer.twig`, it does not fatal every page on
the site (footer is common to all of them).

**Tests:** `php -l` (clean). No new PHP-level test added — `FooterViewProvider`
and `ComponentRenderer` themselves (the two classes this wiring calls) are
already covered by their own smoke tests (`tests/mintcart/design/
footer-view-provider-test.php`, 7 scenarios; `tests/mintcart/design/
component-renderer-test.php` scenario 11 resolves the real `footer.twig`),
neither of which changed in this run. Full `tests/mintcart/` suite
re-run end to end after this change — still passing (migration manager,
`ProductViewProvider` x8, `ComponentRenderer` x11, `FooterViewProvider` x7).
Same pre-existing gap as every earlier phase: no MySQL/`vendor/twig/twig`
in this environment, so no live end-to-end request was exercised. A human
should smoke-test a real page footer on a `mint_default`-configured install
(and confirm the legacy `default` theme's footer is pixel-identical to
before) before this reaches production.

---

## `upload/catalog/view/theme/mint_default/template/common/footer.twig` (new file)

Not an upstream diff — this is a new file `mint_default` ships, not a
modification to an OpenCart-shipped file. Noted here only because it is
the page-level template `ControllerCommonFooter::index()`'s
`$this->load->view('common/footer', $data)` call resolves to once
`mint_default` is active (via the `event/theme.php` three-tier fallback
from `docs/adr/003-page-template-fallback-fix.md`): it outputs
`{{ mc_footer }}` in place of `default`'s own inline footer markup, then
the same `styles`/`scripts`/`</body></html>` closing OpenCart's page
assembly needs on every theme.

---

## `upload/catalog/language/en-gb/common/footer.php`

**Reason:** Adds three new language strings the `footer` component's
documented contract needs and that didn't already exist under any name:
`text_contact_us`, `text_newsletter_email`, `text_newsletter_submit`.
(`text_newsletter` itself already existed — reused as-is for both the
existing "Newsletter" account-link label and the component's newsletter
section heading, same value either way, same reuse pattern
`text_wishlist`/`text_compare`/`text_add_to_cart` followed in Phase 8's
product-card wiring.)

**Why it can't live in the MintCart layer instead:** Same as
`product/category.php`'s language file — OpenCart's language system is
file-per-route, loaded by `$this->load->language('common/footer')` in the
controller above.

**Compatibility impact:** Purely additive — three new keys appended after
the existing ones, nothing removed or renamed. Only `en-gb` exists in this
repo today.

**Tests:** `php -l` (clean).

---

## `upload/catalog/controller/product/search.php`

**Reason:** Wires Search as the second real listing consumer of the Theme
API's `product-card` pipeline (`MINTCART_TECHNICAL_PLAN.md` §15C/§15D,
`CLAUDE.md` §8A) — for each product row, in addition to the controller's
existing inline array building, also builds a `MintCart\Commerce\ProductViewProvider`
result via the shared `MintCart\Design\ProductCardListRenderer` and renders
it into a new `$data['product_cards']` array, same gate
(`MintCart\Design\ActiveTheme::isMintAware()`) `category.php`/`footer.php`
already use. Also exposes several already-loaded language strings
(`text_search`, `entry_search`, `text_keyword`, `text_category`,
`text_sub_category`, `entry_description`, `button_search`, `text_sort`,
`text_limit`, `text_empty`, `button_continue`, `continue`, and a new
`search_performed` boolean) to `$data` for the new
`mint_default/template/product/search.twig` page to consume — the same
`$data['text_compare']` pattern this controller already followed, not new
business logic.

**Why it can't live in the MintCart layer instead:** Same rationale as
`category.php` — `ProductViewProvider`/`ComponentRenderer`/`ProductCardListRenderer`
are deliberately OpenCart-agnostic, but something controller-specific has
to call them with this controller's already-resolved product data
(formatted price/special/tax, resized image, rating) once per row.

**Compatibility impact:** None for the legacy `default` theme or any
unrelated third-party theme — same manifest-gated pipeline as
`category.php`, and the controller's original `$data['products']` array is
completely unchanged either way: same keys, same values, same order, same
computation. A failure inside the shared renderer is caught and logged via
`MintCart\Core\Logger`, disabling the pipeline for the rest of the request
rather than propagating — same degrade behaviour as `category.php`.

One pre-existing gap noticed while writing the new template, **not**
introduced or fixed by this change: `mint_default/template/product/category.twig`
references `text_refine`/`text_sort`/`text_limit`/`text_empty`/`button_continue`
without `category.php` ever assigning those keys to `$data` — Twig's
default `strict_variables => false` renders them as empty strings rather
than erroring, so this has been silently blank on that (already live and
verified) page since Phase 8, on both `mint_default` and the stock
`default` theme (the same unassigned keys, same gap, checked against the
un-modified `default/template/product/category.twig`). Out of scope for
this run — `category.twig` is unrelated to Search — but `search.php`
deliberately does assign every one of the equivalent keys its own new
template reads, rather than repeating the same gap in a brand-new file.
Worth a dedicated small fix (and a `docs/fixes/registry.md` entry per
`CLAUDE.md` §13) for `category.php`/`category.twig` in a future run.

**Tests:** `php -l` (clean) on `search.php` and the new
`en-gb/product/search.php` strings. `tests/mintcart/design/
product-card-list-renderer-test.php` (new, 8 scenarios) covers the shared
renderer this controller now calls. No live end-to-end request test — same
pre-existing gap as every earlier phase (no MySQL/`vendor/twig/twig` in
this environment). A human should smoke-test a real search results page
on a `mint_default`-configured install before this reaches production.

---

## `upload/catalog/language/en-gb/product/search.php`

**Reason:** Adds the same five `product-card` language keys
`product/category.php`'s language file already added
(`text_rated`, `text_quick_order`, `text_badge_sale`, `text_badge_out_of_stock`,
`text_badge_new`) — needed now that Search also renders `product-card`.

**Why it can't live in the MintCart layer instead:** Same as
`product/category.php`'s language file — OpenCart's language system is
file-per-route.

**Compatibility impact:** Purely additive — five new keys appended after
the existing ones, nothing removed or renamed.

**Tests:** `php -l` (clean).

---

## `upload/catalog/view/theme/mint_default/template/product/search.twig` (new file)

Not an upstream diff — a new file `mint_default` ships. Noted here only
because it is the page-level template `ControllerProductSearch::index()`'s
`$this->load->view('product/search', $data)` call resolves to once
`mint_default` is active (via the `event/theme.php` three-tier fallback,
`docs/adr/003-page-template-fallback-fix.md`). Uses a native
`<form method="get">` for the refine-search controls (search/category/
sub_category/description) instead of the `default` theme's jQuery `click`
handler — `CLAUDE.md` §3 disallows jQuery in new MintCart code, and a plain
GET form submitting the same field names to `index.php?route=product/search`
needs no JS at all.

---

## `upload/catalog/controller/product/special.php`

**Reason:** Wires Special as the third real listing consumer of the Theme
API's `product-card` pipeline (`MINTCART_TECHNICAL_PLAN.md` §15C/§15D,
`CLAUDE.md` §8A), mirroring `search.php`'s wiring almost exactly: for each
product row, in addition to the controller's existing inline array
building, also builds a `MintCart\Commerce\ProductViewProvider` result via
the shared `MintCart\Design\ProductCardListRenderer` and renders it into a
new `$data['product_cards']` array, same `MintCart\Design\ActiveTheme::isMintAware()`
gate `category.php`/`search.php`/`footer.php` already use. Also exposes
`text_sort`/`text_limit`/`text_empty`/`button_continue` to `$data` for the
new `mint_default/template/product/special.twig` page to consume — the
same pattern `search.php` already established, not new business logic.
Unlike `search.php`, Special has no optional query gate (it always lists
today's specials), so the whole listing loop runs unconditionally — no new
`search_performed`-style flag was needed.

**Why it can't live in the MintCart layer instead:** Same rationale as
`category.php`/`search.php` — `ProductViewProvider`/`ComponentRenderer`/
`ProductCardListRenderer` are deliberately OpenCart-agnostic, but something
controller-specific has to call them with this controller's
already-resolved product data (formatted price/special/tax, resized image,
rating) once per row.

**Compatibility impact:** None for the legacy `default` theme or any
unrelated third-party theme — same manifest-gated pipeline as
`category.php`/`search.php`, and the controller's original
`$data['products']` array is completely unchanged either way: same keys,
same values, same order, same computation. A failure inside the shared
renderer is caught and logged via `MintCart\Core\Logger`, disabling the
pipeline for the rest of the request rather than propagating — same
degrade behaviour as `category.php`/`search.php`.

**Tests:** `php -l` (clean) on `special.php` and the updated
`en-gb/product/special.php` strings. `tests/mintcart/design/
product-card-list-renderer-test.php` already covers the shared renderer
this controller now calls (no controller-specific test needed — same
class, same contract, already exercised by `search.php`'s wiring). No live
end-to-end request test — same pre-existing gap as every earlier phase (no
MySQL/`vendor/twig/twig` in this environment). A human should smoke-test a
real special-offers page on a `mint_default`-configured install before this
reaches production.

---

## `upload/catalog/language/en-gb/product/special.php`

**Reason:** Adds the same five `product-card` language keys
`product/category.php`/`product/search.php`'s language files already added
(`text_rated`, `text_quick_order`, `text_badge_sale`, `text_badge_out_of_stock`,
`text_badge_new`) — needed now that Special also renders `product-card`.

**Why it can't live in the MintCart layer instead:** Same as
`product/category.php`'s language file — OpenCart's language system is
file-per-route.

**Compatibility impact:** Purely additive — five new keys appended after
the existing ones, nothing removed or renamed.

**Tests:** `php -l` (clean).

---

## `upload/catalog/view/theme/mint_default/template/product/special.twig` (new file)

Not an upstream diff — a new file `mint_default` ships. Noted here only
because it is the page-level template `ControllerProductSpecial::index()`'s
`$this->load->view('product/special', $data)` call resolves to once
`mint_default` is active (via the `event/theme.php` three-tier fallback,
`docs/adr/003-page-template-fallback-fix.md`). Simpler than `search.twig`
— no refine-search form, since Special has no search-criteria UI in the
stock `default` theme either.
