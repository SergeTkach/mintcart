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
into a new `$data['mc_footer']` string. Adds a private
`mintActiveThemeDirectory()` helper identical to
`ControllerProductCategory`'s own (mirrors
`catalog/controller/event/theme.php`'s active-theme resolution) — kept as a
second small, self-contained copy rather than extracted into a shared
helper class for just two 7-line call sites; revisit if a third controller
needs it (`CLAUDE.md` §85/§86).

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
