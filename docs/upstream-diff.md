# Upstream diff

**PR #115 review correction (2026-09-16):**
`upload/admin/view/template/catalog/product_form.twig` uses data attributes
for the new manufacturer picker, preserving existing OpenCart IDs/names.
Its embedded spacing is owned by MintCart's token-based CSS; the new trigger
uses a text label, tracks expanded state, and regains focus after confirmation.
These changes belong at the form's integration point; shared layout and hidden
state rules live in `assets/src/css/components/entity-picker.css`.
Validation: production build, preset checks and isolated handler checks passed.
Live admin verification remains required, including the existing autocomplete.

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

**Update (this run — bugfix, `docs/fixes/registry.md` MC-0001):**
`category.twig` (both the stock `default` theme and `mint_default`) reads
`text_refine`/`text_sort`/`text_limit`/`text_empty`/`button_continue`, but
`index()`'s success branch never assigned any of those five keys to
`$data` — a pre-existing OpenCart 3.0.5.1 stock bug, present on both
themes, silently rendering blank via Twig's non-strict-variables mode
(first noticed while wiring `search.php` on this branch — see that
controller's entry below, which already assigns the equivalent keys for
its own new template). Fixed by adding the same five
`$data[...] = $this->language->get(...)` assignments `search.php`/
`special.php` already use, placed next to the existing
`$data['continue']` assignment. All five language keys already existed in
`catalog/language/en-gb/product/category.php` (`text_refine`, `text_sort`,
`text_limit`, `text_empty`) and the global `en-gb.php`
(`button_continue`) — no new language strings needed. Zero behavioral
change beyond making the already-referenced template variables non-blank;
the `$data['products']`/`$data['product_cards']` arrays are untouched.
**Compatibility impact:** fixes the same silent blank on the legacy
`default` theme too (same unassigned keys, same template gap) — this is a
strict improvement, not a Mint-only change, and carries no risk of
behavior change for stores that don't notice the labels were blank.
**Tests:** `php -l` clean. See `docs/fixes/registry.md` MC-0001 for the
full fix-policy writeup (reproduction, upstream status, resolution).

**Update (this run — Phase 18, SEO Core):** right before rendering, inside
a fresh `ActiveTheme::isMintAware()` check (not `$mint_render_product_cards`,
which by this point in the method may have been flipped to `false` by an
unrelated per-product `ProductCardListRenderer` failure), builds
`$data['mc_schema_breadcrumb']` via
`MintCart\Seo\SchemaOrgBuilder::breadcrumbList($data['breadcrumbs'])` — the
same breadcrumb array `category.twig`'s visible nav already renders, now
also emitted as `BreadcrumbList` JSON-LD. Same caught-and-logged failure
handling as every other `SchemaOrgBuilder`/`ComponentRenderer` call site.
Full design rationale: `docs/developer/seo/schema-org.md`. **Tests:**
`php -l` clean; `tests/mintcart/seo/schema-org-builder-test.php` (16
scenarios) covers `breadcrumbList()` directly; full `tests/mintcart/` suite
re-run end to end — all passing, no regressions.

**Update (this run — Phase 10 (Technical Plan), Diagnostics):** constructs
a `MintCart\Core\Diagnostics` instance right after `$mint_theme`/
`$mint_render_product_cards` are resolved, gated by a new
`mintcart_dev_mode` setting (`(bool)$this->config->get('mintcart_dev_mode')`
— standard OpenCart settings storage, no new Mint table, off by default
since an unset setting reads as falsy). Passes it as `ComponentRenderer`'s
new optional 4th constructor argument when the Mint pipeline runs, and
right before rendering the view, appends `$diagnostics->summary()` as an
HTML comment to the response body when (and only when) dev mode is
enabled. See `docs/developer/theme-api/component-renderer.md`'s
"`$diagnostics` (Phase 10)" section and `docs/developer/core/diagnostics.md`
for the full design rationale.

**Why it can't live in the MintCart layer instead:** something still has
to decide when a given request is in dev mode and construct/thread the
`Diagnostics` instance through the request — `Diagnostics` itself
deliberately has no `\Registry`/`\Config` dependency so it stays unit-
testable in isolation (same rationale as `ProductViewProvider`/
`ComponentRenderer`). A controller is the natural (and, for this first
increment, the only) place that already knows both `config_theme` and
whether to run the Mint pipeline at all.

**Compatibility impact:** None when `mintcart_dev_mode` is unset (the
default) — `Diagnostics(false)` makes every `mark()`/`increment()`/`time()`
call a no-op per its own contract, `ComponentRenderer::render()`'s Twig
call still runs exactly as before, and no HTML comment is appended. When
explicitly enabled, the only visible change is the appended HTML comment
— `$data`/the rendered template are never touched, so no theme override
can accidentally surface diagnostics in visible markup (Functional Scope
§19 "Production UI не повинен показувати sensitive debug data").

**Tests:** `php -l` clean. `tests/mintcart/design/component-renderer-test.php`
gained three new scenarios (14-16, 16 total) covering: an intentionally-
empty override never touches `Diagnostics` bookkeeping; a thrown
resolution error never touches it either; and passing no `Diagnostics`
(the default) leaves existing behavior byte-for-byte unaffected — none of
which require `vendor/twig/twig` (same pre-existing gap noted throughout
this file), since all three exercise paths that never reach
`new \Template('Twig')`. The happy-path Twig render with `Diagnostics`
attached is, like every other `ComponentRenderer::render()` call, not
exercised end-to-end in this environment. Full `tests/mintcart/` suite
(65 files) re-run end to end — all passing, no regressions.

**Update (this run — Phase 18, `MintCart\Seo\MetaBuilder` wiring):**
`index()`'s success branch swapped its direct
`$this->document->setTitle($category_info['meta_title'])`/
`setDescription($category_info['meta_description'])` pass-through for a
call to `MintCart\Seo\MetaBuilder::build()`, falling back to the
category's own `name` when `meta_title` is blank; a blank
`meta_description` stays blank because no fallback description is supplied —
see `docs/developer/seo/meta-builder.md`. `setKeywords()` is unchanged
(no fallback rule exists for keywords). Pure string logic (`trim()` +
ternary fallback), cannot throw, so no `try`/`catch` guard was added,
unlike the `SchemaOrgBuilder`/JSON-LD blocks above which do JSON
encoding.

**Compatibility impact:** behavior-identical when a category already has
its own `meta_title`/`meta_description` filled in (the common case for
any store that has gone through its catalog) — `MetaBuilder`'s internal
fallback picker returns the entity's own trimmed value unchanged. Only a
category whose admin "Meta Tag Title"/"Meta Tag Description" fields were
left blank changes only its title: it now ships `<title>{category name}</title>`.
A blank description stays blank; the shipped header templates omit its meta tag.
The title fallback fixes
the exact defect `docs/developer/seo/meta-builder.md` documents. No
template change, no new `$data` key (still `setTitle()`/`setDescription()`
on `Document`, same as before).

**Tests:** `php -l` clean. `MetaBuilder` itself is already covered by
`tests/mintcart/seo/meta-builder-test.php` (9 scenarios, including
scenario 5 which replays this exact category-blank-meta shape) — no new
test needed since this change only threads that already-tested pure
function's output into two existing `Document` setter calls. Full
`tests/mintcart/` suite (77 files) re-run end to end — all passing, no
regressions. **Not verified:** no live MySQL/Twig session in this
environment — a human should confirm on a real install that a category
with a blank "Meta Tag Title" now renders `<title>{category name}</title>`
instead of an empty tag (see `docs/MANUAL_VERIFICATION.md`'s Phase 18
section).

**Update (this run — Phase 18, SEO Core, `CanonicalUrlBuilder`):** the
inline `http://googlewebmastercentral.blogspot.com/2011/09/pagination-with-relnext-and-relprev.html`
canonical/prev/next block is replaced with a call to
`MintCart\Seo\CanonicalUrlBuilder::build()` (already shipped as a pure,
consumer-less primitive in an earlier run — see
`docs/developer/seo/canonical-url-builder.md`), wrapped in the same
`try`/`catch (\Throwable)` + `Logger` pattern the `SchemaOrgBuilder` call
just above already uses. Runs unconditionally regardless of active theme
(not gated by `isMintAware()`), matching `RobotsBuilder`'s wiring in
`common/header.php` — a canonical link is a per-route SEO signal, not a
Mint component DOM contract. Behavior is byte-for-byte unchanged: the
builder was extracted from, and tested against, this exact controller's
own page/limit/total arithmetic. **Tests:** `php -l` clean;
`tests/mintcart/seo/canonical-url-builder-test.php` (8 scenarios, already
existing) covers the builder itself; full `tests/mintcart/` suite (78
files) re-run end to end — all passing, no regressions.

**Update (2026-09-18, routine run — Performance, responsive images):**
`$data['thumb']`'s resize call already reads
`theme_<theme>_image_category_width`/`_height` from config — this run
just also stores those two ints as `$data['thumb_width']`/
`$data['thumb_height']` so `mint_default/template/product/category.twig`
can render explicit `<img width>`/`<height>` on the category thumbnail
(`MINTCART_FUNCTIONAL_SCOPE.md` §19 "responsive images; width/height" —
prevents layout shift while the image loads), the same convention
`ProductViewProvider` already established for the listing product card's
`product.image_width`/`product.image_height`. No resize behavior change:
the same two config values are passed into `resize()` as before, just
captured into named variables first instead of being read from
`$this->config->get()` twice. **Why it can't live in the MintCart layer:**
`$data` is this controller's own view-data array; there is no other place
to add these two keys. **Compatibility impact:** none — purely additive
keys, no existing key/behavior changed; the stock `default` theme's own
`category.twig` is untouched and never reads these new keys. **Tests:**
`php -l` clean; full `tests/mintcart/` suite (81 files) re-run end to
end — all passing, no regressions (no new test file needed — this is
config-value threading, not new logic to unit-test). `category.twig`'s
Twig `if`/`endif`/`for`/`endfor` tag counts checked balanced before and
after (13/13, 5/5). **Not verified:** no live MySQL/Twig session in this
environment — see `docs/MANUAL_VERIFICATION.md`'s new Phase 19 entry.

**Update (2026-09-29, routine run — Phase 10, Diagnostics JSON surface):**
the existing `if ($mint_diagnostics->isEnabled()) { $output .= "\n<!--
mintcart-diagnostics ... -->"; }` block, right before `setOutput()`, now
has a sibling branch checked first: when the request also carries
`mintcart_diagnostics=json` (still gated by `mintcart_dev_mode` being on),
the controller returns `$mint_diagnostics->summary()` as a standalone
`application/json` response instead of the normal page HTML, with an
early `return` right after `setOutput()`. This was
`docs/developer/core/diagnostics.md`'s own "Next step" list's third
bullet ("a structured JSON endpoint ... as an alternative surface to the
HTML comment") — see that file's new "JSON diagnostics surface" section
for the full writeup. The identical two-line addition was made at the
same point in `search.php`/`special.php`/`manufacturer.php`/`product.php`
— see each file's own entry below for its own brief note. **Why it can't
live in the MintCart layer:** the branch has to sit exactly where the
existing HTML-comment branch already sits, right before this specific
controller's own `setOutput()` call — there is no shared "finish the
response" hook these five controllers funnel through today (each already
duplicates the HTML-comment append independently; this mirrors that same
existing, deliberate small-duplication shape rather than introducing a
new shared helper for a two-line addition, per `CLAUDE.md` §17/§86).
**Compatibility impact:** none for any request without
`mintcart_diagnostics=json` — byte-for-byte the same behavior as before,
since the new branch's condition is simply false. For a request that does
pass it, behavior only changes when `mintcart_dev_mode` is also on (the
same pre-existing gate the HTML comment already uses); with dev mode off,
`$mint_diagnostics->isEnabled()` is `false` and the whole branch is
skipped exactly as before. **Tests:** `php -l` clean on all five touched
files; full `tests/mintcart/**/*-test.php` suite re-run end to end — all
passing, no regressions (no new test file needed — `Diagnostics::summary()`
itself is already covered by `tests/mintcart/core/diagnostics-test.php`,
and this change only threads its existing return value to a different
response method, no new logic to unit-test). **Not verified:** no live
MySQL/browser session in this environment — see
`docs/MANUAL_VERIFICATION.md`'s new Phase 10 entry.

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

**Update:** refactored to call the newly-extracted
`MintCart\Design\ActiveTheme::directory()`/`isMintAware()` instead of this
controller's own private `mintActiveThemeDirectory()` helper — see
`docs/developer/theme-api/product-card-list-renderer.md`. `CLAUDE.md`
§85/§86 had this entry deliberately keep its own small copy rather than
extract a shared helper for just two call sites; `ControllerProductSearch`
(also that run) is the third, so the duplication is now extracted. No
behavioral change.

**Update (this run):** `$social` is no longer always a hardcoded empty
array. Now reads the `mintcart_contacts` setting (`getSetting()`, via the
existing `catalog/model/setting/setting.php` — same model class the admin
side already uses, `$this->load->model('setting/setting')` newly added to
this controller) and passes its `mintcart_contacts_social` value straight
through to `FooterViewProvider::build()`. That setting is written by the
Setup Wizard's new Contacts step (`docs/developer/onboarding/setup-wizard.md`)
in the exact `{network, href}` shape `build()`'s `$social` parameter already
expects — no reshaping in this controller. A store that hasn't run that
Wizard step yet still gets `$social = []`, identical to the previous
hardcoded behavior — this is purely additive for stores that have.

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

## `upload/catalog/language/en-gb/common/header.php`

**Reason:** Adds two new language strings (`text_menu`, `text_close`) for
Header's new mobile navigation off-canvas panel (`mc-header__nav`, see
`upload/catalog/controller/common/header.php`'s own entry above and
`docs/developer/components/header.md`) — neither existed under any name
(`text_category` is "Categories", a different label for a different
purpose; the panel wraps the whole `widgets.menu` fragment, not just
categories). Follows the exact same "reuse `mc-drawer`'s open/close pattern,
add the two labels its markup needs" precedent
`docs/developer/components/drawer.md`'s own basic-usage example already
documents.

**Why it can't live in the MintCart layer instead:** Same as
`common/footer.php`'s own entry above — OpenCart's language system is
file-per-route, loaded by `$this->load->language('common/header')` in the
controller.

**Compatibility impact:** Purely additive — two new keys appended after the
existing ones, nothing removed or renamed. Only `en-gb` exists in this repo
today.

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
**Fixed in a later run on this branch** — see `category.php`'s own entry
above and `docs/fixes/registry.md` MC-0001.

**Tests:** `php -l` (clean) on `search.php` and the new
`en-gb/product/search.php` strings. `tests/mintcart/design/
product-card-list-renderer-test.php` (new, 8 scenarios) covers the shared
renderer this controller now calls. No live end-to-end request test — same
pre-existing gap as every earlier phase (no MySQL/`vendor/twig/twig` in
this environment). A human should smoke-test a real search results page
on a `mint_default`-configured install before this reaches production.

**Update (a later run, same branch/PR): a second pre-existing gap fixed —
`index()` never emitted `canonical`/`prev`/`next` `<link>` tags at all**
(unlike the sibling `category.php`/`manufacturer.php`/`special.php`
controllers, which all do). See `docs/fixes/registry.md` MC-0002. Adds a
`$canonical_url` block mirroring the exact pattern those three sibling
controllers already use (result-defining params only — `search`, `tag`,
`description`, `category_id`, `sub_category` — deliberately excluding
display-only params like `sort`/`order`/`limit` from the canonical URL,
same as `category.php`'s `path`-only canonical). No template change, no
new language strings, `$data['products']`/`$data['product_cards']`
untouched. `php -l` clean.

**Update (this run — Phase 18, SEO Core):** right before rendering, inside
a fresh `ActiveTheme::isMintAware()` check (same reasoning as `category.php`'s
own entry above — not `$mint_render_product_cards`, which may have been
flipped `false` by an unrelated per-product render failure by this point),
builds `$data['mc_schema_breadcrumb']` via
`MintCart\Seo\SchemaOrgBuilder::breadcrumbList($data['breadcrumbs'])`. Same
caught-and-logged failure handling as every other `SchemaOrgBuilder` call
site. Full design rationale: `docs/developer/seo/schema-org.md`. **Tests:**
`php -l` clean; `tests/mintcart/seo/schema-org-builder-test.php` (16
scenarios) covers `breadcrumbList()` directly; full `tests/mintcart/` suite
re-run end to end — all passing, no regressions.

**Update (this run — Phase 10 (Technical Plan), Diagnostics):** same
`MintCart\Core\Diagnostics` wiring as `category.php`'s own "Update" entry
above — constructs it right after `$mint_render_product_cards` is
resolved, gated by `mintcart_dev_mode`, passes it as `ComponentRenderer`'s
4th constructor argument, and appends `$diagnostics->summary()` as an HTML
comment before `setOutput()` only when dev mode is enabled. **Why it can't
live in the MintCart layer:** same reasoning as `category.php`'s entry —
a controller is the only place that already knows both `config_theme` and
whether to run the Mint pipeline. **Compatibility impact:** none when
`mintcart_dev_mode` is unset (the default). **Tests:** `php -l` clean; no
new test file (covered by `component-renderer-test.php`'s existing
scenarios, which are call-site-agnostic); full `tests/mintcart/` suite
(77 files) re-run end to end — all passing, no regressions.

**Update (this run — Phase 18, SEO Core, `CanonicalUrlBuilder`):** the
MC-0002 `canonical`/`prev`/`next` block (see the "Update" entry above)
now calls `MintCart\Seo\CanonicalUrlBuilder::build()` instead of
repeating the page-number arithmetic inline — `$canonical_url` (the
already-fixed, result-defining-params-only filter set) is passed through
unchanged as the builder's `$baseQuery`. Same `try`/`catch (\Throwable)`
+ `Logger` wrapping, same unconditional-regardless-of-theme wiring as
`category.php`'s identical update. Behavior unchanged — same reasoning
as `category.php`'s entry. **Tests:** `php -l` clean; full
`tests/mintcart/` suite (78 files) re-run end to end — all passing, no
regressions.

**Update (2026-09-29, routine run — Phase 10, Diagnostics JSON surface):**
same `mintcart_diagnostics=json` sibling branch added right before this
file's own `setOutput()` call — see `category.php`'s entry above (the
"Update (2026-09-29 ...)" paragraph) for the full writeup; this file's
change is byte-for-byte the same two-line shape. **Tests:** `php -l`
clean; full `tests/mintcart/` suite re-run end to end — all passing.
**Update (this run — Phase 18, SEO Core, `RobotsBuilder` `$hasResults`
registry bridge):** resolves the `header.php`-side "Known limitation"
`docs/developer/seo/robots.md` documented. A new
`$mint_search_has_results` local, initialized `true` before the
`isset($this->request->get['search']) || isset($this->request->get['tag'])`
guard (a bare `product/search` request with neither param never runs the
query below — the plain search form, not a "no results" page — so it keeps
the same `true` default `RobotsBuilder` already uses), is set to
`($product_total > 0)` right after `$product_total` is computed inside
that guard. Immediately before the existing
`$data['header'] = $this->load->controller('common/header');` call, a new
line writes it to the registry:
`$this->registry->set(\MintCart\Seo\RobotsBuilder::HAS_RESULTS_REGISTRY_KEY, $mint_search_has_results);`
— the same `$this->registry->set()`/`->get()` bridge
`upload/system/engine/startup/startup.php` already uses to hand
`session`/`customer`/`cart` between stages of a single request; no new
table, session key or event. **Why it can't live in the MintCart layer:**
`RobotsBuilder` itself deliberately has no `\Registry` dependency (see its
own docblock) — only a controller that already knows both "did this
specific search run" and "is this the point where header.php is about to
be invoked" can produce and hand off this value at the right moment.
**Compatibility impact:** None. The registry key is new and MintCart-only;
no existing `$data` key, template variable, or return value changes. A
`product/search` request that never sets `search`/`tag` behaves exactly as
before (`true`, i.e. `header.php` renders `index, follow` for it, same as
every route that never touches this key at all). **Tests:** `php -l`
clean on `search.php`/`header.php`/`robotsbuilder.php`.
`tests/mintcart/seo/robots-builder-test.php` gained two assertions (76,
up from 74) checking the new `HAS_RESULTS_REGISTRY_KEY` constant's shape —
`directives()`/`metaContent()`'s own logic is unchanged, so no new
behavioral test was needed there. Full `tests/mintcart/**/*-test.php`
suite re-run end to end (84 files) — all passing, no regressions. Same
pre-existing no-live-request gap as every earlier Phase 18 entry — a human
should confirm on a real install that a `product/search` query with zero
matches renders `<meta name="robots" content="noindex, follow">` while one
with matches still renders `content="index, follow"` (see
`docs/MANUAL_VERIFICATION.md`'s Phase 18 section).

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

**Update (this run — Phase 10 (Technical Plan), Diagnostics):** same
`MintCart\Core\Diagnostics` wiring as `category.php`/`search.php`'s own
"Update" entries above — constructs it right after
`$mint_render_product_cards` is resolved, gated by `mintcart_dev_mode`,
passes it as `ComponentRenderer`'s 4th constructor argument, and appends
`$diagnostics->summary()` as an HTML comment before `setOutput()` only
when dev mode is enabled. **Why it can't live in the MintCart layer:**
same reasoning as `category.php`'s entry. **Compatibility impact:** none
when `mintcart_dev_mode` is unset (the default). **Tests:** `php -l`
clean; full `tests/mintcart/` suite (77 files) re-run end to end — all
passing, no regressions.

**Update (this run — Phase 18, SEO Core, `CanonicalUrlBuilder`):** the
inline `http://googlewebmastercentral.blogspot.com/2011/09/pagination-with-relnext-and-relprev.html`
canonical/prev/next block now calls `MintCart\Seo\CanonicalUrlBuilder::build()`
with an empty `$baseQuery` (Special has no identifying query parameter),
keeping the `secure = true` third argument to `Url::link()` exactly as
stock OpenCart had it — this is the only one of the four sibling
controllers that forces an SSL link. Same `try`/`catch (\Throwable)` +
`Logger` wrapping, same unconditional-regardless-of-theme wiring as
`category.php`'s identical update. Behavior unchanged — same reasoning
as `category.php`'s entry. **Tests:** `php -l` clean; full
`tests/mintcart/` suite (78 files) re-run end to end — all passing, no
regressions.

**Update (2026-09-17, routine run — Phase 18, SEO Core, `SchemaOrgBuilder`
BreadcrumbList):** right before rendering, inside a fresh
`ActiveTheme::isMintAware()` check (not `$mint_render_product_cards`, per
the same reasoning as `category.php`'s identical update), builds
`$data['mc_schema_breadcrumb']` via
`MintCart\Seo\SchemaOrgBuilder::breadcrumbList($data['breadcrumbs'])`.
`special.twig` renders it as a second `<script type="application/ld+json">`
block right after `{{ header }}`, same as `category.twig`/`search.twig`.
Same caught-and-logged failure handling as every other `SchemaOrgBuilder`
call site. Full design rationale: `docs/developer/seo/schema-org.md`.
**Tests:** `php -l` clean; Twig tag balance checked; full `tests/mintcart/`
suite (77 files) re-run end to end — all passing, no regressions.

**Update (2026-09-29, routine run — Phase 10, Diagnostics JSON surface):**
same `mintcart_diagnostics=json` sibling branch added right before this
file's own `setOutput()` call — see `category.php`'s entry (the "Update
(2026-09-29 ...)" paragraph) for the full writeup; this file's change is
byte-for-byte the same two-line shape. **Tests:** `php -l` clean; full
`tests/mintcart/` suite re-run end to end — all passing.

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

---

## `upload/catalog/controller/common/header.php`

**Reason:** Wires the Theme API's Header component (Phase 7,
`MINTCART_TECHNICAL_PLAN.md` §14, `MINTCART_IMPLEMENTATION_GUIDE.md` §48) —
after the controller's existing per-field data resolution, also builds a
`MintCart\Design\HeaderViewProvider` result and renders it through
`MintCart\Design\ComponentRenderer::render('header', ...)` into a new
`$data['mc_header']`.

**Update (merge reconciliation, 2026-08-24):** refactored to call
`MintCart\Design\ActiveTheme::directory()`/`isMintAware()` instead of this
controller's own private `mintActiveThemeDirectory()` copy — by the time
this PR merged, `footer.php`/`category.php`/`search.php`/`special.php`/
`manufacturer.php` had all already been consolidated onto the shared
`ActiveTheme` class (see those files' own entries above), so a sixth
private copy would have reintroduced exactly the duplication that
consolidation removed. No behavioral change: `ActiveTheme::directory()`/
`isMintAware()` are the same two lines this controller's own helper used
to inline.

**Why it can't live in the MintCart layer instead:** Same reasoning as
`ControllerCommonFooter`/`ControllerProductCategory` — `HeaderViewProvider`
and `ComponentRenderer` are OpenCart-agnostic on purpose, but something
controller-specific still has to call them with this controller's
already-resolved data (config values, customer session state, the five
legacy sub-controller HTML fragments) exactly once per request.

**Compatibility impact:** None for stores on the legacy `default` OpenCart
theme, or on any unrelated third-party OpenCart theme — same
`theme.json`-manifest gate as the footer/category wiring, so no non-Mint
theme runs any of this new code. The controller's original per-field
`$data` (used by `default/template/common/header.twig` and any
third-party theme's own header template) is completely unchanged: same
keys, same values, same computation, same sub-controller calls, same
order. A failure inside the new pipeline (e.g. a missing/unreadable
`header.twig`) is caught and logged rather than propagating — a broken Mint
header degrades to no `mc_header` (mint_default's wrapper renders nothing
in `<body>`), it does not fatal the entire page for every visitor, since
header renders on every single page of the site.

**Tests:** No live end-to-end request test — same pre-existing gap as
every earlier MintCart controller change (no MySQL server/`vendor/twig/twig`
in this environment). Verified instead: `php -l` (clean);
`tests/mintcart/design/header-view-provider-test.php` (7 scenarios, plain
arrays, no OpenCart dependency); `tests/mintcart/design/component-renderer-test.php`
scenario 13 confirms `ComponentRenderer` resolves the real, shipped
`mint_default`/`test_theme` `header.twig` files at their real repository
paths, both fallback directions (renumbered from scenario 11 during merge
reconciliation — scenarios 11-12 were already taken by the `footer.twig`
coverage `footer.php`'s entry above added). A human should still
smoke-test a real page on a configured install before this reaches
production — see `docs/developer/components/header.md`'s "Known gap"
section for the one deliberately-deferred piece (MintCart's own compiled
asset bundle is still not enqueued on any live page).

**Update (this run): mobile navigation.** `mc-header__nav` (the `<nav>`
wrapping `widgets.menu`) now also carries Bootstrap 5's responsive
off-canvas classes (`.offcanvas.offcanvas-lg.offcanvas-start`) plus
`.mc-drawer` (`docs/developer/components/drawer.md`) — at the `lg`
breakpoint and up it renders exactly as before (a normal static block, this
component's own Definition of Done already had a `[ ]` next to "no
responsive off-canvas/hamburger behavior yet"); below `lg` it becomes a
slide-in panel opened by a new `.mc-header__nav-toggle` hamburger button via
the existing `data-mc-action="open-drawer"` contract — no new JS wiring
needed, `initDrawerTriggers()` is already called from `catalog.js`. Two new
language keys (`text_menu`, `text_close`, see that file's own entry above)
were added to the `$header_view + array(...)` merge already building
`$data['mc_header']` — same array, same pattern as the existing
`text_account`/`text_wishlist`/... entries, no other line changed. Also
extended `assets/src/js/components/drawer.js` (`syncTriggerAriaExpanded()`)
to keep `aria-expanded` on any `[data-mc-action="open-drawer"]` trigger in
sync with the drawer's actual shown/hidden state — this is `mc-drawer`'s
first real trigger-button consumer in this repo, and nothing previously
kept a static `aria-expanded="false"` written in markup honest once the
panel opened (`MINTCART_FUNCTIONAL_SCOPE.md` §27, keyboard/screen-reader
correctness). **Tests:** `php -l` clean on all touched PHP; `node --check`
on `drawer.js`; Twig `{% if %}`/`{% endif %}`/`{% for %}`/`{% endfor %}` tag
counts re-balanced (11/11) after the template change; `npm run build` clean,
`grep` against the built bundles confirms `.mc-header__nav-toggle`/
`.mc-header__nav-toggle-bar` in `index.min.css` and `CSS.escape`/
`data-mc-target` in `catalog.min.js`; full `tests/mintcart/**/*-test.php`
suite re-run end to end (34 files) — all passing, no regressions (no PHP
test covers Twig/CSS/JS directly, but confirms nothing else in the touched
PHP files broke). Same pre-existing no-live-browser gap as every earlier
Phase 7 entry — see `docs/MANUAL_VERIFICATION.md` for what a human should
confirm (tap the hamburger below `lg`, confirm the panel slides in with
focus trapped and `Escape`/backdrop/close-button all work, confirm the nav
renders inline with no toggle visible at `lg` and up).
**Update (this run — Phase 18, SEO Core):** inside the same
`isMintAware()`-gated block, also builds `$data['mc_schema_organization']`
via the new `MintCart\Seo\SchemaOrgBuilder::organization()` — one
Organization JSON-LD payload per request, reusing the same config values
(`config_name`/`config_logo`/`config_telephone`) plus `config_address`
already read for `HeaderViewProvider`. Same failure handling as `mc_header`
above: caught, logged via `MintCart\Core\Logger`, never fatals the page.
Rendered in `mint_default/template/common/header.twig`'s `<head>`. Full
design rationale: `docs/developer/seo/schema-org.md`. **Tests:** `php -l`
clean; `tests/mintcart/seo/schema-org-builder-test.php` (16 scenarios)
covers `organization()`/`toJsonLd()` directly; full `tests/mintcart/`
suite re-run end to end — all passing. Same pre-existing no-live-request
gap as every earlier phase — see `docs/MANUAL_VERIFICATION.md`'s new
Phase 18 entry.

**Update (2026-09-15, routine run — Phase 18, SEO Core): `MintCart\Seo\RobotsBuilder`
live wiring.** Also builds `$data['mc_robots_content']` via
`RobotsBuilder::metaContent()`, resolving the current route from
`$this->request->get['route']` (same defensive `isset()` guard
`common/content_top.php` already uses for the same superglobal, since
header renders as a sub-controller of every page). Unlike `mc_header`/
`mc_schema_organization`, this block deliberately sits **outside** the
`$mint_render_header` (`isMintAware()`) gate: a robots directive is a
per-route SEO signal, not a Mint component DOM contract, so it is computed
for every request regardless of active theme — a theme/template that
doesn't read `$data['mc_robots_content']` (the legacy `default` theme, or
any third-party theme) simply never sees the extra array key, at zero
cost. Same catch-and-log failure handling as the two blocks above it. `<head>`
output added to `mint_default/template/common/header.twig` right after the
existing `keywords` meta tag.

**Update (this run): `product/search` `$hasResults` now threaded via a
registry bridge — see `docs/developer/seo/robots.md`.** Reads
`$this->registry->get(\MintCart\Seo\RobotsBuilder::HAS_RESULTS_REGISTRY_KEY)`
(falling back to `RobotsBuilder`'s own default, `true`, when unset) instead
of always passing the implicit default, so `metaContent()`'s second
argument is now real for `product/search` and unchanged for every other
route (which never writes that registry key). See
`upload/catalog/controller/product/search.php`'s own entry below for the
write side of this bridge.

**Compatibility impact:** None for the legacy `default` theme or any
unrelated third-party theme (their own header templates never read
`$data['mc_robots_content']`, so nothing new renders for them — same
"extra unused array key" cost model as every other new `$data[...]` this
controller has ever added). For `mint_default`, purely additive: a new
`<meta name="robots">` tag only appears when `RobotsBuilder` resolves a
non-null route; a failure inside `RobotsBuilder` is caught and logged,
degrading to no robots meta tag rather than fataling the page.

**Tests:** `php -l` clean on `header.php` and `header.twig`'s Twig
`{% if %}`/`{% endif %}` tag balance re-checked by hand (unchanged
count otherwise, one new matched pair added). `tests/mintcart/seo/robots-builder-test.php`
(pre-existing, 74 assertions) already covers `metaContent()` itself —
no new PHP unit test needed since this change only threads an existing,
already-tested pure function's output into `$data` via a one-line call. Full
`tests/mintcart/**/*-test.php` suite re-run end to end (77 files) — all
passing, no regressions. Same pre-existing no-live-request gap as every
earlier phase — see `docs/MANUAL_VERIFICATION.md`'s new Phase 18 addition
for what a human should confirm once installed (checkout/account pages
render `content="noindex, follow"`; ordinary catalog pages render
`content="index, follow"`).

**Update (2026-09-19, routine run): enqueue MintCart's own
`components.min.css`/`catalog.min.js` bundle.** Resolves the standing
"Known gap" every Phase 4-15 component doc has carried since it was
written (`docs/developer/components/header.md`, referenced from dozens of
other component docs): nothing in this codebase had ever linked any file
under `assets/dist/` on the storefront, so every `mc-*` component CSS rule
and every `data-mc-action` JS hook (`add-to-cart`, `quick-order`,
`toggle-wishlist`, `open-modal`, ...) this project has built across many
prior runs was inert on a live page — present in the DOM, styled by
nothing, listened to by nothing. Two new lines inside the existing
`isMintAware()` theme-awareness check this file already computes for
`mc_header`/`mc_schema_organization`:

```php
$this->document->addStyle($server . 'assets/dist/css/components.min.css');
$this->document->addScript($server . 'assets/dist/js/catalog.min.js');
```

Deliberately `components.min.css` (`assets/src/css/components.css`), not
`index.min.css` — the same two-bundle split
`docs/developer/admin/asset-enqueue.md` already proved safe on
Bootstrap-3-legacy admin screens applies identically here, since
`mint_default`'s `common/header.twig` wraps every storefront page in that
same Bootstrap-3/jQuery stack (the exact page where `index.min.css` was
tried directly and reverted, per that file's own now-updated comment).
`catalog.min.js`, unlike `admin.min.js`, was not previously enqueued
anywhere at all (admin's `components.min.css`/`admin.min.js` pair only
ever reached `ControllerCatalogProduct::getForm()`). One structural
change beyond the two new lines: `$mint_theme`/`$mint_render_header` are
now computed once, right after `$server`, instead of being recomputed
lower down immediately before the `$mint_header`/`mc_schema_organization`
block — required because `Document::getStyles()`/`getScripts()` (a few
lines below the new calls) return a snapshot at call time, and that
snapshot already ran before the file's *original* theme-awareness check
further down, so adding the two new calls there instead would have
silently had no effect on the current page's `<head>`. The later block
now reuses the same two variables instead of recomputing them — no
behavioral change to anything it already did. Full design rationale,
including why this is not the same thing as the still-not-attempted "swap
the legacy stack out entirely": `docs/developer/admin/asset-enqueue.md`'s
new "Storefront: `ControllerCommonHeader`" section.

**Compatibility impact:** None for the legacy `default` theme or any
unrelated third-party theme — same `isMintAware()` gate as `mc_header`, so
neither pays for or receives the two new tags. For `mint_default`
(`test_theme` too, though it has no `common/header.twig` of its own yet to
render the resulting `<head>` — pre-existing, separate gap): two new
`<link>`/`<script>` tags via the existing `styles`/`scripts` loops
`common/header.twig` already had; the legacy Bootstrap-3/jQuery/
`common.js`/Font-Awesome stack on the same page is completely untouched,
same as every other increment gated by this check.

**Tests:** `php -l` clean. `npm install` (0 vulnerabilities), `npm run
build` and `npm run dev` both compile cleanly — this increment touches no
JS/CSS source file, only which two already-existing build outputs get
referenced from PHP/Twig, so the bundles themselves are byte-identical to
before. Twig `{% if %}`/`{% endif %}`/`{% for %}`/`{% endfor %}` tag count
unchanged (4/4/4/4) after the comment-only template edit. Full
`tests/mintcart/**/*-test.php` suite re-run end to end (84 files) — all
passing, no regressions (no PHP test exercises `ControllerCommonHeader`
directly — it has a full-`\Registry` OpenCart dependency with no test
double in this repo, same pre-existing gap as this controller's every
earlier entry above). **Not verified live** — no MySQL/browser session in
this environment; see `docs/developer/components/header.md`'s "Known gap
(partially resolved)" section and `docs/MANUAL_VERIFICATION.md` for what a
human should confirm on a real install (network tab shows both files
loading with a 200 response on a storefront page; `mc-product-card`'s Add
to Cart/Quick Order/wishlist-toggle buttons actually respond to a click,
which they visibly did not before this change; the legacy Bootstrap-3/
jQuery-driven parts of the same page — account pages, `common/cart.info`
AJAX, `common.js` — still work unmodified).

**Update (2026-09-19, routine run): `text_search` added to the
`$header_view + array(...)` merge.** One new key, same array, same pattern
as the existing `text_account`/`text_menu`/`text_close`/... entries — no
other line changed. Consumed by `mint_default`'s and `test_theme`'s
`header.twig` for `mc-search-box`'s `placeholder`/`aria-label` (see those
two files' own entries below and
`docs/developer/components/search-box.md`). `text_search` already exists
in `catalog/language/en-gb/common/header.php` (no new language file
change needed). **Tests:** `php -l` clean; full `tests/mintcart/` suite
(84 files) re-run end to end, unaffected (no class touched) — still
passing.

---

## `upload/catalog/view/theme/mint_default/template/mint/components/header.twig` (this entry, 2026-09-19 routine run)

**Reason:** Replaces the `{% if widgets.search %}<div class="mc-legacy">
{{ widgets.search|raw }}</div>{% endif %}` block (the legacy jQuery-
autocomplete `common/search` fragment) with `mc-search-box`'s documented
markup contract (`docs/developer/components/search-box.md`) — the
component's JS/CSS have existed since an earlier Phase 15 increment with
no live template consumer; this was the explicitly-named "next step" in
`docs/IMPLEMENTATION_STATUS.md`'s Phase 15 section, unblocked once
`components.min.css`/`catalog.min.js` were enqueued on the storefront
(see this file's `header.php` entry above, "Update ... own compiled asset
bundle").

**Why it can't live in the MintCart layer instead:** It is Twig markup for
one specific theme's specific component template — by definition theme
content, not platform code.

**Compatibility impact:** None outside `mint_default` — this is a template
file, not shared code. `HeaderViewProvider`'s `widgets.search` key is
untouched (still computed by `header.php`, still exposed in the contract);
`mint_default` simply stopped reading it. A theme that copies
`mint_default` as a starting point and still wants the legacy fragment can
render `widgets.search` itself — the key did not disappear.

**Tests:** `if`/`for` tag counts re-balanced (10/10, `for` unused) after
the edit. Full `tests/mintcart/` suite (84 files) re-run end to end,
unaffected (no PHP class touched) — still passing. **Not verified live**
— no MySQL/browser session in this environment; see
`docs/MANUAL_VERIFICATION.md`'s Phase 15 entry.

---

## `upload/catalog/view/theme/test_theme/template/mint/components/header.twig` (this entry, 2026-09-19 routine run)

**Reason:** Adds the same `mc-search-box` markup contract in a different
placement (directly after the branding link, before the account/cart
widgets, rather than `mint_default`'s topbar-adjacent `mc-header__widgets`
slot) — proving the Theme API's override-fallback story for this
component too, the same way `test_theme`'s product-card/header/footer
overrides already proved it for theirs (`CLAUDE.md` §19/§45).

**Why it can't live in the MintCart layer instead:** Same as above — this
is theme content, by definition theme-owned.

**Compatibility impact:** None outside `test_theme`, which exists solely
as the second reference theme proving Theme API fallback (never a
production theme).

**Tests:** `if`/`for` tag counts re-balanced (3/3) after the edit. Same
`tests/mintcart/` suite re-run as above, unaffected.

---

## `assets/src/css/components.css` (this entry, 2026-09-19 routine run)

**Reason:** Bug fix, found while verifying the `mc-search-box` wiring
above. This file (the Bootstrap-5-reset-free bundle `ControllerCommonHeader`
actually enqueues on the storefront as `components.min.css`, deliberately
*not* `index.min.css` — see the `header.php` entry above) was missing
three `@import` lines `index.css` already had:
`./components/product-gallery.css`, `./components/quick-order.css`, and
`./components/search-box.css`. This file's own docblock says to "keep this
list's imports (order included) identical to `index.css` any time
`index.css` gains or reorders one" — that instruction was not followed
when those three component CSS files were added to `index.css` in earlier
increments, so none of their styling ever reached a live page (only
`index.min.css`, which nothing on the storefront enqueues, had it). Fixed
by adding the three missing imports, in the same relative order
`index.css` uses.

**Why it can't live in the MintCart layer instead:** Build-pipeline
config, not application code — there is no "MintCart layer" for a CSS
entry-point file.

**Compatibility impact:** Additive only — `components.min.css` grows by
the CSS these three component files already contribute to `index.min.css`
(none of which redeclares a bare `body`/`html`/`*`/element selector, per
this file's own docblock guarantee — re-checked for all three before
adding). No existing selector changed. Confirmed none of the three
introduces a Bootstrap-structural dependency (`.modal`/`.offcanvas`/
`.toast`/`.dropdown`) this bundle deliberately excludes — the one `.modal-body`
selector in `quick-order.css` only styles content nested inside a modal
that `mc-modal.css` (already imported here) already documents as
unstyled-without-`index.css` chrome, an existing, unrelated limitation
this change does not widen.

**Tests:** `npm install` (0 vulnerabilities), `npm run build` compiles
cleanly. `grep -c mc-search-box assets/dist/css/components.min.css` went
from 0 to 1 after the fix — the actual regression test for this bug.
`npm test` (preset-token contract checks, unaffected) still passing.

---

## `upload/catalog/controller/product/manufacturer.php`

**Reason:** Wires `ControllerProductManufacturer::info()` as the fourth
real listing consumer of the Theme API's `product-card` pipeline
(`MINTCART_TECHNICAL_PLAN.md` §15C/§15D, `CLAUDE.md` §8A) — same
`MintCart\Design\ActiveTheme`/`MintCart\Design\ProductCardListRenderer`
pipeline `category.php`/`search.php`/`special.php` already use, same
manifest gate, same degrade-on-first-failure behaviour. Also fixes
**MC-0003** (`docs/fixes/registry.md`): `text_sort`/`text_limit`/
`text_empty`/`button_continue` were never assigned to `$data` in this
method — the same class of bug as MC-0001 in `category.php` — found here
while wiring the new template.

**Why it can't live in the MintCart layer instead:** Same rationale as
`category.php`/`search.php`/`special.php` — `ProductViewProvider`/
`ComponentRenderer`/`ProductCardListRenderer` are deliberately
OpenCart-agnostic, but something controller-specific has to call them with
this controller's already-resolved product data (formatted price/special/
tax, resized image, rating) once per row.

**Compatibility impact:** None for the legacy `default` theme or any
unrelated third-party theme — same manifest-gated pipeline as the three
sibling controllers, and the controller's original `$data['products']`
array is completely unchanged: same keys, same values, same order, same
computation. MC-0003's fix is purely additive (`$data[...] =` assignments
using strings the language file already declares) and applies to every
theme, not just Mint-aware ones — the stock `default` theme's own
`manufacturer_info.twig` references the same four variables and was
equally silently blank before this fix.

**Tests:** `php -l` (clean) on `manufacturer.php` and the updated
`en-gb/product/manufacturer.php` strings; `manufacturer_info.twig`'s
`if`/`for`/`endif`/`endfor` tags checked for balanced nesting (same
technique used for every earlier `.twig` file in this repo).
`tests/mintcart/design/product-card-list-renderer-test.php` already covers
the shared renderer this controller now calls (no controller-specific test
needed — same class, same contract, already exercised by three sibling
controllers' wiring). No live end-to-end request test — same pre-existing
gap as every earlier phase (no MySQL/`vendor/twig/twig` in this
environment). A human should smoke-test a real manufacturer page on a
`mint_default`-configured install before this reaches production —
including confirming MC-0003's sort/limit labels and empty-state message
are no longer blank on both `default` and `mint_default`.

**Update (this run — Phase 10 (Technical Plan), Diagnostics):** same
`MintCart\Core\Diagnostics` wiring as the three sibling controllers' own
"Update" entries above, applied only to `info()` (the only method that
constructs a `ComponentRenderer` — the plain manufacturer-list `index()`
and the `error/not_found` fallback are untouched). Constructs
`Diagnostics` right after `$mint_render_product_cards` is resolved, gated
by `mintcart_dev_mode`, passes it as `ComponentRenderer`'s 4th
constructor argument, and appends `$diagnostics->summary()` as an HTML
comment before `setOutput()` only when dev mode is enabled. **Why it
can't live in the MintCart layer:** same reasoning as `category.php`'s
entry. **Compatibility impact:** none when `mintcart_dev_mode` is unset
(the default). **Tests:** `php -l` clean; full `tests/mintcart/` suite
(77 files) re-run end to end — all passing, no regressions.

**Update (this run — Phase 18, SEO Core, `CanonicalUrlBuilder`):** the
inline `http://googlewebmastercentral.blogspot.com/2011/09/pagination-with-relnext-and-relprev.html`
canonical/prev/next block now calls `MintCart\Seo\CanonicalUrlBuilder::build()`
with `'manufacturer_id=' . $this->request->get['manufacturer_id']` as
`$baseQuery`, same as before. Same `try`/`catch (\Throwable)` + `Logger`
wrapping, same unconditional-regardless-of-theme wiring as `category.php`'s
identical update. Behavior unchanged — same reasoning as `category.php`'s
entry. **Tests:** `php -l` clean; full `tests/mintcart/` suite (78 files)
re-run end to end — all passing, no regressions.

**Update (2026-09-17, routine run — Phase 18, SEO Core, `SchemaOrgBuilder`
BreadcrumbList):** applied only to `info()` (the only method that builds
`$data['breadcrumbs']` for a real listing page — the plain manufacturer-list
`index()` has no breadcrumb list of individual products to describe).
Right before rendering, inside a fresh `ActiveTheme::isMintAware()` check
(same reasoning as `category.php`'s identical update), builds
`$data['mc_schema_breadcrumb']` via
`MintCart\Seo\SchemaOrgBuilder::breadcrumbList($data['breadcrumbs'])`.
`manufacturer_info.twig` renders it as a second
`<script type="application/ld+json">` block right after `{{ header }}`,
same as `category.twig`/`search.twig`. Same caught-and-logged failure
handling as every other `SchemaOrgBuilder` call site. Full design
rationale: `docs/developer/seo/schema-org.md`. **Tests:** `php -l` clean;
Twig tag balance checked; full `tests/mintcart/` suite (77 files) re-run
end to end — all passing, no regressions.

**Update (2026-09-19, routine run — Fixes Registry, MC-0017):**
`index()` (the plain brand-index/`manufacturer_list` page, not `info()`)
never assigned `heading_title`/`text_index`/`text_no_results`/
`button_continue` to `$data` at all — the same class of bug MC-0001/
MC-0003 already fixed on `category.php`/this controller's own `info()`
method, found while building `mint_default`'s own `manufacturer_list.twig`
(see the new-file entry below). Added the four missing assignments
immediately before the existing `$data['continue'] = ...` line, same
placement MC-0003 already used one method below. Full writeup:
`docs/fixes/registry.md` MC-0017. **Why it can't live in the MintCart
layer:** same reasoning as MC-0001/MC-0003 — `$data` is this controller's
own view-data array. **Compatibility impact:** none — purely additive
`$data` keys using strings the language file already declares; applies
equally to the stock `default` theme (whose own `manufacturer_list.twig`
references the same four keys) and `mint_default`. **Tests:** `php -l`
clean; full `tests/mintcart/**/*-test.php` suite (81 files) re-run end to
end — all passing, no regressions (no new test file needed — same
rationale as MC-0001/MC-0003, four already-proven `language->get()`
calls). **Not verified:** no live MySQL/Twig session in this environment
— see `docs/MANUAL_VERIFICATION.md`'s new Phase 6 entry.

**Update (2026-09-29, routine run — Phase 10, Diagnostics JSON surface):**
same `mintcart_diagnostics=json` sibling branch added right before this
file's `info()` method's own `setOutput()` call — see `category.php`'s
entry (the "Update (2026-09-29 ...)" paragraph) for the full writeup;
this file's change is byte-for-byte the same two-line shape. `index()`'s
plain manufacturer-list path never constructs `$mint_diagnostics` and is
untouched. **Tests:** `php -l` clean; full `tests/mintcart/` suite re-run
end to end — all passing.

---

## `upload/catalog/language/en-gb/product/manufacturer.php`

**Reason:** Adds the same five `product-card` language keys
`product/category.php`/`product/search.php`/`product/special.php`'s
language files already added (`text_rated`, `text_quick_order`,
`text_badge_sale`, `text_badge_out_of_stock`, `text_badge_new`) — needed
now that Manufacturer also renders `product-card`.

**Why it can't live in the MintCart layer instead:** Same as the three
sibling language files — OpenCart's language system is file-per-route.

**Compatibility impact:** Purely additive — five new keys appended after
the existing ones, nothing removed or renamed.

**Tests:** `php -l` (clean).

---

## `upload/catalog/view/theme/mint_default/template/product/manufacturer_info.twig` (new file)

Not an upstream diff — a new file `mint_default` ships. Noted here only
because it is the page-level template `ControllerProductManufacturer::info()`'s
`$this->load->view('product/manufacturer_info', $data)` call resolves to
once `mint_default` is active (via the `event/theme.php` three-tier
fallback, `docs/adr/003-page-template-fallback-fix.md`). Mirrors
`category.twig`'s structure minus the categories/refine section (not
applicable — a manufacturer page lists one brand's products, not a
category tree).

---

## `upload/catalog/view/theme/mint_default/template/product/manufacturer_list.twig` (new file)

**Reason:** `mint_default` had no template for `product/manufacturer`'s
plain `index()` route (the alphabetical brand-index page, distinct from
`info()`'s single-brand product listing, which already had
`manufacturer_info.twig` above) — the theme fell through `event/theme.php`'s
three-tier fallback straight to the stock `default` theme's Bootstrap-3
`manufacturer_list.twig` for this one page, the only remaining gap in this
controller's own two-route family. Not an upstream diff — a new file
`mint_default` ships, resolved via the same fallback chain as every other
`mint_default` page template. Mirrors `manufacturer_info.twig`'s
container/`mc-breadcrumb`/column layout; the brand list itself uses
`mc-button--ghost`/`mc-button--sm` for the A-Z jump links and
`mc-button--secondary` for each brand link (both modifiers already used
elsewhere in this theme), replacing `default`'s bare anchor tags. Also the
first live consumer of MC-0017's fix (see the `manufacturer.php` entry
above) — without it, `heading_title`/`text_index`/`text_no_results`/
`button_continue` would render blank on this new template exactly as they
did on `default`'s own copy.

**Compatibility impact:** None — a new file only `mint_default` (or a
Mint-aware theme with no override of its own) ever resolves to; the stock
`default` theme's own `manufacturer_list.twig` is untouched.

**Tests:** `php -l` not applicable (Twig, not PHP). `{% if %}`/`{% endif %}`/
`{% for %}`/`{% endfor %}` tag counts checked balanced (4/4, 4/4). Not
verified: no live MySQL/Twig session in this environment — see
`docs/MANUAL_VERIFICATION.md`'s new Phase 6 entry for the full manual
script a human should run.

---

## `upload/catalog/controller/common/home.php`

**Reason:** Wires Phase 10's Homepage Builder Block API
(`MINTCART_TECHNICAL_PLAN.md` §16-17, `MINTCART_IMPLEMENTATION_GUIDE.md`
§32-33) — the first real consumer of `MintCart\Blocks\BlockRenderer` and
`MintCart\Blocks\ProductsBlockProvider`. Added a `buildMintBlocks()` stage
that (when the active theme is Mint-API-aware) loads the `'home'` `mc_page`
row and its enabled `mc_block` rows via the new `ModelMintcartBlock`, and
for each `type = 'products'` block queries products and renders it through
the same `ProductViewProvider`/`ProductCardListRenderer` pipeline
Category/Search/Special/Manufacturer already use. Result is passed to the
view as a new `$data['mc_blocks']` (list of already-rendered block HTML
strings) — see `docs/developer/blocks/products.md` for the full writeup.
`buildMintBlocks()`'s dispatch now also handles `type = 'banner-grid'` via
a sibling `renderBannerGridBlock()` method
(`MintCart\Blocks\BannerGridBlockProvider` + `$this->model_tool_image`,
no product query involved — see `docs/developer/blocks/banner-grid.md`).
The controller's original five `$this->load->controller(...)` calls
(`column_left`/`column_right`/`content_top`/`content_bottom`/`footer`/`header`)
are completely untouched.

**Update (Text block):** `buildMintBlocks()`'s per-block dispatch now also
handles `type = 'text'`, calling a new `renderTextBlock()` (normalizes via
`MintCart\Blocks\TextBlockProvider`, renders `blocks/text.twig`) — no
product query, no additional OpenCart service touched. See
`docs/developer/blocks/text.md` for the full writeup.

**Why it can't live in the MintCart layer instead:** Same rationale as
every other page-controller wiring in this file — `BlockRenderer`,
`ProductsBlockProvider` and `ModelMintcartBlock` are deliberately
OpenCart-request-agnostic (or, for the model, a plain reusable OpenCart
model), but something has to call them once per homepage request and pass
OpenCart's own product query/currency/tax services into the per-product
mapping. OpenCart's `Loader`/routing has no generic hook to inject that
without touching this controller.

**Compatibility impact:** None for the legacy `default` theme or any
unrelated third-party theme — `buildMintBlocks()` returns an empty array
immediately unless the active theme ships a `theme.json` manifest (same
gate as every other Mint rendering pipeline), and neither `default`'s nor
a third-party theme's own `common/home.twig` reads `$data['mc_blocks']`.
On a Mint-aware theme with no `'home'` `mc_page` row yet (every install
until Homepage Builder's admin UI exists) it also returns an empty array —
the homepage renders exactly as before, just with an unused, empty
`mc_blocks` variable passed to the view. A failure inside
`ProductCardListRenderer::render()` for one product degrades that block's
grid for the rest of the request (same behaviour as every other consumer)
rather than fataling the homepage.

**Update (this run): added the Spacer block.** `buildMintBlocks()`'s
per-block dispatch (previously a single `if ($block['type'] === 'products')`)
is now an `if`/`elseif`/`else` also handling `type = 'spacer'` via a new
`renderSpacerBlock()` method and `MintCart\Blocks\SpacerBlockProvider` — see
`docs/developer/blocks/spacer.md` for the full writeup. No OpenCart query
is made for a spacer (unlike products); it only normalizes a `size` setting
and renders `blocks/spacer.twig`. Any other block `type` still falls
through to `null` (skipped, not fatal), same as before this change.
Compatibility impact is identical to the Products block's own: gated behind
the same Mint-aware-theme check, and a store with no `'spacer'`-type
`mc_block` row is completely unaffected.

**Tests:** `php -l` (clean). `tests/mintcart/blocks/block-renderer-test.php`
(3 scenarios, was 2 — added a real-repo `spacer.twig` resolution check) and
`tests/mintcart/blocks/spacer-block-provider-test.php` (4 scenarios, new)
and `tests/mintcart/blocks/products-block-provider-test.php` (3 scenarios)
cover the classes this wiring calls. Full `tests/mintcart/` suite re-run
end to end after this change — still passing (12 files). `npm run build`
compiles cleanly; `mc-block--spacer`/`mc-block--spacer-sm|md|lg|xl` present
in the bundled `assets/dist/css/index.min.css`. Same pre-existing gap as
every earlier phase: no MySQL/`vendor/twig/twig` in this environment, so no
live end-to-end request was exercised — a human should insert a `mc_page`
row (`code = 'home'`) and an `mc_block` row (`type = 'products'` or
`type = 'spacer'`, `settings_json = '{}'` or `'{"size":"lg"}'`) on a real
install and confirm the homepage renders the corresponding block, then
confirm the legacy `default` theme's homepage is pixel-identical to
before.

**Update (a later run): `buildMintBlocks()`'s dispatch now also handles
`type = 'hero'`**, via a new `renderHeroBlock()` private method calling the
new `MintCart\Blocks\HeroBlockProvider` — a settings-only banner block (no
product query), see `docs/developer/blocks/hero.md` for the full writeup.
The `foreach` loop was reshaped from an `if`-with-inline-append into an
`if`/`elseif`/`else` that assigns `$markup` then appends once, so adding a
third block type later is a single new `elseif` branch rather than another
copy of the append-if-non-empty check. No behavioural change for existing
`'products'` blocks. **Tests:** `php -l` clean;
`tests/mintcart/blocks/hero-block-provider-test.php` (3 scenarios, new);
full `tests/mintcart/` suite re-run end to end — still passing (12 files).
Same pre-existing gap: a human should insert an `mc_block` row
(`type = 'hero'`) on a real install and confirm the homepage renders a
Hero banner, both with and without a background `image` setting.

**Update (Categories block):** `buildMintBlocks()`'s per-block dispatch was
reshaped from `if('products')` into `if`/`elseif`/`else` — for each
`type = 'categories'` block, a new `renderCategoriesBlock()` method queries
`ModelCatalogCategory::getCategories($parent_id)` (the same core model
`common/menu`/`extension/module/category` already use) via the new
`MintCart\Blocks\CategoriesBlockProvider`, and renders it through
`blocks/categories.twig`. No change to the method's public shape or to the
Products path. See `docs/developer/blocks/categories.md`. Verified: `php -l`
clean; `tests/mintcart/blocks/categories-block-provider-test.php` (3
scenarios, new) and `block-renderer-test.php`'s new Scenario 3 (real
`categories.twig` resolves) both pass; full `tests/mintcart/` suite
re-run end to end — still passing. Same live-MySQL gap as above — a human
should also insert an `mc_block` row (`type = 'categories'`,
`settings_json = '{}'`) and confirm the homepage renders a Categories grid.

**Update: Benefits block added.** `buildMintBlocks()`'s per-block dispatch
gained an `elseif ($block['type'] === 'benefits')` branch calling a new
`renderBenefitsBlock()`, which normalizes settings via the new
`MintCart\Blocks\BenefitsBlockProvider` and renders `blocks/benefits.twig`
— no OpenCart query or model involved (pure content block; see
`docs/developer/blocks/benefits.md`). Same Mint-aware-theme gate, same
"skip when empty, never fatal" behaviour as the `'products'` branch.
Verified: `tests/mintcart/blocks/benefits-block-provider-test.php` (3
scenarios, new) and a third scenario added to
`tests/mintcart/blocks/block-renderer-test.php` confirming the real
`benefits.twig` resolves; full `tests/mintcart/` suite re-run end to end
(12 files) — still passing. Same live-render gap as above — a human
should also insert an `mc_block` row (`type = 'benefits'`) and confirm the
homepage renders it.

**Update (later run): `buildMintBlocks()`'s dispatch gained a `type = 'faq'`
branch** (`renderFaqBlock()`), alongside the existing `'products'` one —
see `docs/developer/blocks/faq.md` for the full writeup. Pure content
block: no product query, no `tool/image` resize, no new language strings.
Same compatibility posture as the Products branch — a legacy/third-party
theme's `buildMintBlocks()` call still short-circuits before reaching
either branch. Tests: `tests/mintcart/blocks/faq-block-provider-test.php`
(3 scenarios), `tests/mintcart/blocks/block-renderer-test.php` scenario 3
(real `faq.twig` resolution). Full `tests/mintcart/` suite re-run end to
end — still passing (12 files).

**Increment (Testimonials block, this run):** `buildMintBlocks()`'s
dispatch gained a `renderTestimonialsBlock()` branch for
`type = 'testimonials'` blocks, reusing
`MintCart\Blocks\TestimonialsBlockProvider` (pure content, no product
query) and resizing each item's optional avatar via
`model_tool_image->resize()` the same `is_file()`-guarded way the stock
Banner module does. See `docs/developer/blocks/testimonials.md` for the
full writeup. No change to the `'products'` branch or to any other
existing behaviour. Tests: `tests/mintcart/blocks/testimonials-block-provider-test.php`
(3 scenarios, new) and Scenario 3 added to `block-renderer-test.php`
(confirms the real `testimonials.twig` resolves) — full
`tests/mintcart/` suite re-run end to end, 12 files, all passing.

**Update (Brands block increment):** `buildMintBlocks()`'s dispatch gained
an `elseif ($block['type'] === 'brands')` branch calling a new
`renderBrandsBlock()`, following the exact same shape as
`renderProductsBlock()`. See `docs/developer/blocks/brands.md` for the
full writeup. `tests/mintcart/blocks/brands-block-provider-test.php` (3
scenarios) covers the new `MintCart\Blocks\BrandsBlockProvider`; a 3rd
scenario was added to `block-renderer-test.php` confirming the real
`brands.twig` resolves. Full `tests/mintcart/` suite re-run end to end —
still passing (12 files). Same live-render verification gap as above —
not yet exercised against a real MySQL/Twig install.

**Update (Phase 10, Text/Image block):** `buildMintBlocks()`'s dispatch
gained an `elseif ($block['type'] === 'text-image')` branch and a new
`renderTextImageBlock()` private method, following the exact shape
`renderHeroBlock()` (added by a sibling increment) established — no change
to the `'products'` branch or to `buildMintBlocks()`'s Mint-aware-theme gate.
See `docs/developer/blocks/text-image.md` for the full writeup. Verified the
same way: `php -l` clean; full `tests/mintcart/` suite (13 files, including
the new `tests/mintcart/blocks/text-image-block-provider-test.php` and a 3rd
scenario in `block-renderer-test.php`) passing; `npm run build` confirms
`mc-prose`/`text-image-media` land in the bundled CSS. Same live-render gap
as above still applies.

**Increment (this run):** `buildMintBlocks()`'s dispatch gained an
`elseif ($block['type'] === 'cta')` branch calling a new
`renderCtaBlock()`, which normalizes via the new
`MintCart\Blocks\CtaBlockProvider` (pure content, no query/model involved)
and renders through the existing `BlockRenderer` — see
`docs/developer/blocks/cta.md`. `tests/mintcart/blocks/cta-block-provider-test.php`
(3 scenarios) and a 3rd scenario in `block-renderer-test.php` cover it; full
`tests/mintcart/` suite re-run end to end, still passing (12 files).

**Update (HTML block):** `buildMintBlocks()`'s per-block dispatch now also
recognizes `type = 'html'`, calling a new `renderHtmlBlock()` method that
normalizes the block's raw markup via the new
`MintCart\Blocks\HtmlBlockProvider` and renders it through the same
`BlockRenderer` the Products block uses — see
`docs/developer/blocks/html.md`. No change to the `'products'` path or
the Mint-awareness gate. Verified: `php -l` (clean); full
`tests/mintcart/` suite re-run end to end — still passing (12 files, plus
the new `tests/mintcart/blocks/html-block-provider-test.php`); same
pre-existing no-MySQL/no-Twig-vendor gap — a human should insert an
`mc_block` row (`type = 'html'`, `content_json = '{"html":"<p>Hello</p>"}'`)
on a real install and confirm it renders on the homepage.

**Update:** the `buildMintBlocks()` dispatch gained an
`elseif ($block['type'] === 'gallery')` branch calling a new
`renderGalleryBlock()`, which normalizes settings via the new
`MintCart\Blocks\GalleryBlockProvider` and resolves each item's image via
`tool/image` (same `is_file`-guarded `resize()` call `renderProductsBlock()`
already uses), rendering `blocks/gallery.twig`. See
`docs/developer/blocks/gallery.md`. Same compatibility/testing shape as
the `products` branch above — an unrecognized/not-yet-implemented block
type is still skipped, not fatal. (Note for whoever merges: several other
Homepage Builder block types — Hero, Categories, Banner Grid, Benefits,
Brands, Testimonials, FAQ, Text/Image, CTA, Newsletter, Spacer, Text,
HTML — have their own open PRs adding a similar `elseif` branch to this
same dispatch chain as of this run; a small manual merge-order
reconciliation of `buildMintBlocks()` will be needed once more than one
lands.)

**Update (this run — Phase 18, `MintCart\Seo\MetaBuilder` wiring):**
`index()`'s top swapped its direct
`$this->document->setTitle($this->config->get('config_meta_title'))`/
`setDescription($this->config->get('config_meta_description'))`
pass-through for a call to `MintCart\Seo\MetaBuilder::build()`, falling
back to the store name (`config_name`) only when `config_meta_title` is blank.
A blank `config_meta_description` stays blank — the documented fresh-install case
in `docs/developer/seo/meta-builder.md` (these settings are empty until a
merchant fills them in under Settings, a step Setup Wizard doesn't force).
`setKeywords()` is unchanged. Pure string logic, cannot throw, no
`try`/`catch` needed — same reasoning as `category.php`'s identical
wiring above. Does not touch `buildMintBlocks()` or the block-type
dispatch chain at all, so it carries none of that method's open-PR
merge-order risk noted in the `Update` immediately above.

**Compatibility impact:** behavior-identical for any store that has
already filled in Settings → Store's "Meta Tag Title"/"Meta Tag
Description" — the entity's own trimmed value still wins. Only a fresh
install (or any store that left those two settings blank) changes: the
homepage now ships `<title>{store name}</title>` instead of an empty
`<title>` tag. No template change, no new `$data` key.

**Tests:** `php -l` clean. Same already-tested `MetaBuilder` primitive as
`category.php`'s entry above (`tests/mintcart/seo/meta-builder-test.php`
scenario 7 replays this exact homepage-blank-meta shape) — no new test
needed. Full `tests/mintcart/` suite (77 files) re-run end to end — all
passing, no regressions. **Not verified:** no live MySQL/Twig session in
this environment — a human should confirm on a fresh install (before
Settings → Store's meta fields are filled in) that the homepage renders
`<title>{store name}</title>` instead of an empty tag (see
`docs/MANUAL_VERIFICATION.md`'s Phase 18 section).

**Update (2026-09-18, routine run — Performance, responsive images):**
`renderTextImageBlock()` already resolves the block's image via
`model_tool_image->resize($settings['image'], $settings['image_width'],
$settings['image_height'])` — `TextImageBlockProvider::build()` has
always computed those two ints (defaulting to 800x600) — but the render
array passed to `mint/blocks/text-image.twig` never included them, so the
template had no way to render `<img width>`/`<height>` at all. This run
adds `'image_width'`/`'image_height'` to that render array; the template
change lives in the `mint_default` theme entry below, not here (this file
only tracks OpenCart-side controller changes, and `home.php` — unlike
`category.php`/`product.php` above — is itself already a MintCart-owned,
fully-additive controller method with no stock OpenCart behavior to stay
compatible with). **Why it can't live in the MintCart layer:** the
provider already computed the right values; the gap was purely in this
controller forgetting to pass them through to the renderer, no different
in kind from a typo. **Compatibility impact:** none — an unused key added
to an array only this block's own template reads. **Tests:** `php -l`
clean; full `tests/mintcart/` suite (81 files) re-run end to end — all
passing, no regressions (no new test file needed — `TextImageBlockProvider`
already unit-tests `image_width`/`image_height`'s own value, this is
wiring-only). `text-image.twig`'s Twig `if`/`endif` tag counts checked
balanced before and after (8/8). **Not verified:** no live MySQL/Twig
session in this environment — see `docs/MANUAL_VERIFICATION.md`'s new
Phase 19 entry.

---

## `upload/catalog/view/theme/mint_default/template/common/home.twig` (new file)

Not an upstream diff — `mint_default` did not previously override
`common/home.twig` at all (it fell through the `event/theme.php`
fallback to the legacy `default` theme's copy, which does not know about
`mc_blocks`). Noted here only because it is the page-level template
`ControllerCommonHome::index()`'s `$this->load->view('common/home', $data)`
call now resolves to once `mint_default` is active: loops `mc_blocks`
full-width above the existing `column_left`/`content_top`/`content_bottom`/
`column_right` layout, which is otherwise identical to `default`'s own
`common/home.twig` (same `header`/`footer` calls, same column-width
logic translated to Bootstrap 5 `col-lg-*` per
`docs/developer/layout-conventions.md`).

**Update (Newsletter block increment):** `buildMintBlocks()`'s dispatch
gained an `elseif ($block['type'] === 'newsletter')` branch calling a new
`renderNewsletterBlock()` — the simplest render method so far (no query,
no model, no image resize): normalize via
`MintCart\Blocks\NewsletterBlockProvider`, render through `BlockRenderer`.
See `docs/developer/blocks/newsletter.md` for the full writeup, including
why the block's subscribe form has no working endpoint yet.
`tests/mintcart/blocks/newsletter-block-provider-test.php` (4 scenarios)
covers the new provider; a 3rd scenario was added to
`block-renderer-test.php` confirming the real `newsletter.twig` resolves.
Full `tests/mintcart/` suite re-run end to end — still passing (12 files).
Same live-render verification gap as above — not yet exercised against a
real MySQL/Twig install.

---

## `upload/catalog/language/en-gb/common/home.php` (new file)

**Reason:** Adds the same 8 `product-card` language keys
`product/category.php`/`product/search.php`/`product/special.php`/
`product/manufacturer.php`'s language files already each duplicate
(`text_tax`, `text_rated`, `text_quick_order`, `text_badge_sale`,
`text_badge_out_of_stock`, `text_badge_new`, `text_added_to_cart`,
`text_add_to_cart_error`) — needed now that the Homepage Products block
also renders `product-card`. No prior `common/home` language file existed
(`ControllerCommonHome` loaded none before this change).

**Why it can't live in the MintCart layer instead:** Same as the four
sibling language files — OpenCart's language system is file-per-route.

**Compatibility impact:** New file, purely additive. `button_wishlist`/
`button_compare`/`button_cart` are not duplicated here — they already come
from the base `catalog/language/en-gb/en-gb.php`, loaded for every
request.

**Tests:** `php -l` (clean).

---

## `upload/admin/controller/common/column_left.php`

**Reason:** Adds a "Homepage" entry to the admin `Design` menu, gated by
`$this->user->hasPermission('access', 'design/homepage')` exactly like
every sibling entry (Layouts/Theme/Language Editor/Banners/SEO URL) —
the new Homepage Builder admin editor
(`upload/admin/controller/design/homepage.php`, this run) otherwise has
no menu entry pointing at it at all. Placed first in the `$design` array
so it reads as the primary Design action, ahead of the lower-level
Layouts/Theme screens, matching `MINTCART_PRODUCT_VISION.md` §8's target
IA (`Design > Homepage` before `Design > Header/Footer/...`).

**Why it can't live in the MintCart layer instead:** The admin left menu
is built by one procedural controller with no plugin/extension point for
a third party (or MintCart) to append a menu entry — same situation every
other admin-menu-touching change in this codebase would face; there is no
smaller diff available.

**Compatibility impact:** Purely additive (one new `if` block before the
existing Layouts check) — no existing menu entry, permission check, or
route is touched. A user group without the new `design/homepage`
permission simply never sees the entry (`user_permission.php` already
discovers the new route automatically by scanning `controller/*`, per
`docs/IMPLEMENTATION_STATUS.md`'s Phase 10 write-up — no separate
permission registration file exists to update).

**Tests:** `php -l` (clean). No automated test for admin menu rendering
exists in this repo (would need a live OpenCart `Registry`/session, same
gap the whole `upload/admin/**` tree currently has) — verified by reading
the diff against the existing Layouts/Theme/Banner/SEO URL entries it
sits next to, which it mirrors exactly.

**Update (this run — Phase 14, `Business → Delivery`/`Payments`):** adds a
new top-level "Business" menu group (`menu-mintcart-business`), placed
just before the existing Marketing group, with "Delivery"/"Payments"
children gated by `mintcart/business/delivery`/`mintcart/business/payments`
access respectively — same conditional-array-then-conditional-group-push
shape every other menu group in this file already uses (see the Marketing
group immediately below it). `docs/developer/onboarding/business-delivery-payments.md`
has the full design writeup. Purely additive; no existing menu entry or
permission check touched. **Tests:** `php -l` clean.

**Update (2026-09-18, routine run — `System → Diagnostics`):** adds one
entry to the existing `$system` menu array, right after the stock
"Settings" entry, gated by
`$this->user->hasPermission('access', 'mintcart/settings/diagnostics')` —
same conditional-push shape every sibling entry in this file already
uses. Points at the new `ControllerMintcartSettingsDiagnostics`
(`upload/admin/controller/mintcart/settings/diagnostics.php`), an admin UI
toggle for the `mintcart_dev_mode` setting that
`docs/developer/core/diagnostics.md`'s "Next step" section named as still
missing (the flag could previously only be set by writing directly to
`oc_setting`). Purely additive; no existing menu entry, permission check,
or route touched. **Tests:** `php -l` clean; full `tests/mintcart/`
suite (81 files) re-run end to end, all passing, no regressions.

---

## `upload/admin/model/catalog/category.php` / `upload/admin/controller/catalog/category.php`

**Reason:** Phase 17 (Categories drag & drop, `MINTCART_TECHNICAL_PLAN.md`
§30, `MINTCART_IMPLEMENTATION_GUIDE.md` §64) needed a transactional "move
this category to a new parent and/or new sibling position" write, following
the documented flow exactly — "begin transaction, validate target, prevent
cycle, change parent, rebuild affected category_path subtree, update sort
order, commit". Two changes:

1. **Model:** `editCategory()`'s inline `category_path` closure-table
   rebuild block (the "MySQL Hierarchical Data Closure Table Pattern"
   comment) was extracted, verbatim, into a new private
   `rebuildCategoryPath($category_id, $parent_id)` method — same SQL, same
   order of operations, no behavior change to `editCategory()` itself. A
   new public `moveCategory($category_id, $parent_id, array $sort_order = [])`
   method reuses it inside a `START TRANSACTION`/`COMMIT`/`ROLLBACK` block
   (the same raw-SQL transaction pattern
   `MintCart\Core\Migration\MigrationManager` already established), then
   assigns sequential `sort_order` values to `$sort_order`'s list of sibling
   category_ids. Unlike `editCategory()`, it touches only `category.parent_id`/
   `category.sort_order` and `category_path` — never
   `category_description`/`category_filter`/`category_to_store`/`seo_url`/
   `category_to_layout`, since a drag & drop move changes structure, not
   content.
2. **Controller:** a new, fully-additive `move()` action (route
   `catalog/category.move`) — permission check, `category_id`/`parent_id`
   existence checks via the existing `getCategory()`, cycle-prevention via
   the new `MintCart\Catalog\CategoryMoveValidator` fed by the existing
   `getCategoryPath($parent_id)` (the *same* ancestor-chain lookup
   `validateForm()` already uses for the single-category edit form's own
   `error_parent` check — this reuses that proven rule rather than
   re-deriving it), then `moveCategory()`. One new language key,
   `error_category` (`en-gb/catalog/category.php`).

**Why it can't live in the MintCart layer instead:** The `category`/
`category_path` tables and their closure-table maintenance are OpenCart
core storage (`CLAUDE.md` §39 — do not duplicate OpenCart DB). A new
MintCart-owned table/service that re-implemented category hierarchy storage
alongside the existing one would fork a single source of truth into two.
Extending the existing model/controller — the same place `addCategory()`/
`editCategory()`/`deleteCategory()` already live — keeps `category_path`
consistency in one place.

**Compatibility impact:** Purely additive from the legacy `default` theme's
and every existing admin screen's perspective — no existing action, method
signature, or return value changed; `editCategory()`'s own behavior is
unchanged (verbatim extraction). Nothing calls the new `move()` action yet
(no template wiring — see `docs/developer/catalog/category-drag-drop.md`
"Not done yet").

**Update (later run):** `move()`'s inline `getCategory()`/`getCategoryPath()`
existence/ancestor-chain checks were routed through the new
`MintCart\Compatibility\OpenCart3\CategoryAdapter` (Phase 1's adapter
layer, `MINTCART_TECHNICAL_PLAN.md` §5) — see
`docs/developer/compatibility/category-adapter.md`. Implementation only,
no behavior change: same existence-check semantics, same
`$ancestor_path_ids` values in the same order. `moveCategory()` itself
(the model's transactional write) is untouched.

**Tests:** `php -l` clean on all four touched/added PHP files (both admin
files, the new `MintCart\Catalog\CategoryMoveValidator`, and its test). New
`tests/mintcart/catalog/category-move-validator-test.php` (6 scenarios —
top-level move, unrelated-parent move, self-parent rejection, direct-child
cycle rejection, indirect (grandchild) cycle rejection, numeric-string
`path_id` values from a real DB row). Full `tests/mintcart/` suite re-run
end to end (13 files, was 12) — all pass, no regressions. Same pre-existing
gap as every earlier phase: no MySQL in this environment, so
`moveCategory()`/`rebuildCategoryPath()` and the `move()` action itself were
never exercised against a real database — a human should confirm on a real
install (see `docs/MANUAL_VERIFICATION.md`'s new Phase 17 section).

---

## `upload/catalog/controller/extension/payment/klarna_account.php` / `upload/catalog/controller/extension/payment/klarna_invoice.php`

**Reason:** Fixes **MC-0014** (`docs/fixes/registry.md`): both files called
the deprecated (PHP 8.2+) `utf8_encode()`/`utf8_decode()` functions to
convert between the Klarna XML-RPC API's ISO-8859-1 wire format and PHP's
internal UTF-8 strings — a pure bugfix, no Theme API/component work
involved, in the same "PHP 8.x compatibility" family as MC-0010
(`opayo.php`'s `strftime()`).

**Why it can't live in the MintCart layer instead:** These lines are deep
inside stock OpenCart payment-extension controller logic (building an
XML-RPC request body / parsing its response) — there is no MintCart-layer
equivalent to intercept or wrap; the only place to fix a deprecated
built-in function call is at the call site itself.

**Compatibility impact:** None. `mb_convert_encoding($x, 'UTF-8',
'ISO-8859-1')` / `mb_convert_encoding($x, 'ISO-8859-1', 'UTF-8')` are the
documented byte-for-byte equivalents of `utf8_encode()`/`utf8_decode()`
(`mbstring` is already a hard OpenCart 3 install requirement, so this adds
no new dependency); no control flow, template, or language string changed
in either file. The one call site building the Klarna invoice checksum
digest (`klarna_invoice.php`) was treated with extra care since that
digest is sent to Klarna's API — the replacement preserves its exact byte
output rather than taking the chance to "clean up" charset handling.

**Tests:** `php -l` clean on both files.
`tests/mintcart/fixes/mc-0014-klarna-charset-test.php` (17 assertions)
proves the replacement is byte-for-byte equivalent to the functions it
replaces, using PHP's own (deprecated but still callable) `utf8_encode()`/
`utf8_decode()` as the oracle. No live payment-gateway request test — same
pre-existing gap as every earlier phase, and this one specifically needs a
real Klarna merchant sandbox account this environment doesn't have — a
human should confirm a real Klarna Invoice/Account checkout still
completes successfully on a PHP 8.2+ install.
## `upload/system/library/mail/smtp.php`

**Reason:** Fix MC-0013 (`docs/fixes/registry.md`) — the three `EHLO`/`HELO`
call sites in `Mail\Smtp::send()` built the command directly from
`getenv('SERVER_NAME')`, which is empty (`false`) outside a real web
request. Any CLI/cron-triggered mail send (a scheduled OpenCart cron
hitting a route by literal PHP CLI invocation, or MintCart's own planned
batch-job/Update Manager work, `MINTCART_FUNCTIONAL_SCOPE.md` §7/§21)
produced a malformed, argument-less `EHLO \r\n`/`HELO \r\n` that a number of
real SMTP server configurations reject outright, silently breaking outgoing
mail with no visible error to the merchant. Replaced the raw `getenv()` call
at all three sites with `\MintCart\Mail\HeloHostname::resolve()`.

**Why it can't live in the MintCart layer instead:** `Mail\Smtp::send()` is
where the SMTP conversation itself happens (socket I/O, `EHLO`/`STARTTLS`/
`AUTH`/`DATA` sequencing) — there is no MintCart-owned mail transport to
reroute this through, and building a parallel one merely to fix one
hostname-resolution call would duplicate OpenCart's entire SMTP client for
a three-line fix. The actual hostname-fallback *logic*, which is genuinely
new and independently testable, does live in the MintCart layer
(`MintCart\Mail\HeloHostname`, `upload/system/library/mintcart/mail/`) —
this core-file edit is reduced to the minimum: call the Mint helper instead
of `getenv()` directly, at the three sites that needed it.

**Compatibility impact:** None when `SERVER_NAME` is present and non-empty
(the common case on real web hosting) — `HeloHostname::resolve()` returns
it unchanged, so the `EHLO`/`HELO` argument sent is byte-for-byte identical
to before. Only the previously-broken `SERVER_NAME`-absent case changes
behavior, and only for the better (a real hostname instead of none). No
method signature, public property, or other class in the `Mail\` namespace
changed.

**Tests:** `php -l` clean on both the modified `smtp.php` and the new
`helohostname.php`. New `tests/mintcart/mail/helo-hostname-test.php` (3
scenarios), passing. Full `tests/mintcart/` suite re-run end to end (35
files now, was 34) — all pass, no regressions. No live SMTP-server test was
possible in this environment (no outbound SMTP access) — see
`docs/MANUAL_VERIFICATION.md`'s new MC-0013 entry for what a human should
confirm on a real install.
## `upload/admin/model/setting/setting.php`

**Reason:** MC-0012 (`docs/fixes/registry.md`) — `editSetting()` deleted
every existing `setting` row for a `code` group, then looped issuing one
`INSERT` per key, with no transaction around either step.
`DB\MySQLi::query()` throws on any SQL error (a lock timeout, a deadlock
with a concurrent writer, a dropped connection — MC-0011's scenario), and
this method is the single save path for every OpenCart settings screen
(117 call sites: every payment/shipping/total/module extension's config
form, `Settings → Store`, and MintCart's own Setup Wizard steps). A
failure partway through the `INSERT` loop previously left that `code`
group's settings partially deleted — the `DELETE` had already committed,
but only some of the replacement rows existed — with no rollback and no
indication to the admin that the save had failed destructively. Wrapped
the whole sequence in `START TRANSACTION` / `COMMIT`, with `ROLLBACK` +
rethrow on any `\Throwable`.

**Why it can't live in the MintCart layer instead:** This is OpenCart's
own core settings-persistence method, called directly by dozens of stock
extension controllers this codebase does not own or control (payment/
shipping/total/module/report extensions across `upload/admin/controller/
extension/**`) as well as `Settings → Store` and MintCart's own
`ControllerMintcartWizard`. A parallel MintCart-owned settings-save path
would only protect Mint's own callers, leaving every stock OpenCart
extension's config form exposed to the exact same data-loss window this
fix closes. Fixing the shared method in place protects every caller with
one change, the same rationale MC-0007's `Cache\File` fix and Phase 17's
`ModelCatalogCategory::moveCategory()` extraction both already used for
their own shared, heavily-called core methods.

**Compatibility impact:** Purely internal to the write mechanics —
`editSetting($code, $data, $store_id)`'s signature, return type (`void`),
and the exact `DELETE`/`INSERT` SQL it issues on the success path are all
unchanged, so every existing caller (OpenCart core, every stock extension,
MintCart's own Wizard) keeps working identically when nothing fails
mid-call. The only observable behavior change is the failure path: a
`\Throwable` from any query now rolls back the transaction before
rethrowing the same exception object, so a caller that already wraps
`editSetting()` in a `try`/`catch` (none currently do, but nothing
prevents one from being added later) sees the identical exception type and
message as before — only the database's own state differs (intact instead
of half-deleted).

**Tests:** `php -l` clean on the modified file.
`tests/mintcart/fixes/mc-0012-setting-transaction-test.php` (4 scenarios —
see `docs/fixes/registry.md`'s MC-0012 entry for the full list). Verified
not vacuous: the identical test run against the pre-fix method fails on
the critical rollback assertion. Full `tests/mintcart/**/*-test.php` suite
re-run end to end (35 files, was 34) — all pass, no regressions. Same
pre-existing gap as every earlier phase: no MySQL server in this
environment, so the real `\DB\MySQLi` transaction queries themselves were
never exercised against a live database (only the double's transaction
semantics were) — a human should confirm on a real install that a
`ROLLBACK` genuinely restores the previous row set under InnoDB (the
engine every `oc_setting` table in a MintCart install uses).
## `upload/system/library/db/mysqli.php`

**Reason:** MC-0011 (`docs/fixes/registry.md`) — `query()` threw
immediately on any mysqli error, including errno 2006/2013 (dropped TCP
connection), instead of reconnecting and retrying the same query once.
Extracted the constructor's connection setup into a `connect()` method and
added a bounded (max one) reconnect-and-retry path for exactly those two
errnos; every other errno still throws immediately, unretried, same as
before.

**Why it can't live in the MintCart layer instead:** `DB\MySQLi` is the
concrete adaptor OpenCart's core `DB` class instantiates by class name
(`system/library/db.php`'s `new $class(...)`) — there is no MintCart-owned
seam between `DB` and the adaptor to intercept a query and retry it
without either modifying this file or replacing OpenCart's whole DB
bootstrap, which is a much larger and riskier change for a two-errno retry
fix.

**Compatibility impact:** Every existing success/error path is unchanged
for every errno *except* 2006/2013, which previously threw and now
transparently retry once before falling back to the exact same throw
behavior if the retry also fails. No public method signature changed;
`connect()` is a new `protected` method (extracted from the constructor,
not exposed as a new public API) so a test can override it with a fake
connection. Nothing in this codebase subclasses `DB\MySQLi` today, so the
visibility change from implicit-constructor-only to a named `protected`
method has no other effect.

**Tests:** `php -l` clean. New
`tests/mintcart/fixes/mc-0011-mysqli-reconnect-test.php` (5 scenarios) —
see the MC-0011 registry entry for full detail. Full `tests/mintcart/`
suite re-run end to end (36 files) — all pass, no regressions. Not
verified against a real MySQL server (none available in this
environment) — see the registry entry's "Not verified" note.
## `upload/catalog/model/extension/payment/opayo.php`

**Reason:** MC-0010 (`docs/fixes/registry.md`) — `setPaymentData()` called
the deprecated `strftime()` (deprecated since PHP 8.1) to build part of a
recurring-payment `VendorTxCode`. `MINTCART_FUNCTIONAL_SCOPE.md` §18's
Known Fix Research Queue names "PHP 8.x compatibility problems" as a
candidate to investigate; a prior run already grepped for several other
PHP 8-incompatible constructs and found the baseline otherwise clear, but
didn't check for `strftime()`/`gmstrftime()` specifically (see
`docs/IMPLEMENTATION_STATUS.md`'s Phase 8 section for that earlier grep).

**Why it can't live in the MintCart layer instead:** This is a one-line
fix inside a stock OpenCart payment extension's own request-building logic
— there is no Mint-layer equivalent to route this through, and per
`CLAUDE.md` §13/§39 a historical/compatibility bugfix like this is applied
directly at its source, not duplicated into a parallel Mint-owned copy of
the gateway integration.

**Compatibility impact:** Purely additive/behavior-preserving —
`strftime("%Y%m%d%H%M%S")` and `date('YmdHis')` produce byte-identical
output for this purely numeric format (no locale-sensitive tokens), so the
generated `VendorTxCode` values are unaffected. No method signature, route,
or `$data`/request-payload shape changed.

**Tests:** `php -l` clean. New
`tests/mintcart/fixes/mc-0010-opayo-strftime-test.php` (see MC-0010 for the
full writeup) — full `tests/mintcart/` suite re-run end to end (35 files,
was 34), all pass. Not verified: no live MySQL/OpenCart admin session or
Opayo/Sage Pay merchant account in this environment — a human with both
should confirm a real recurring charge still succeeds.
## `upload/catalog/controller/startup/seo_url.php` (MC-0009)

**Reason:** Fix MC-0009 (`docs/fixes/registry.md`) — `index()`'s incoming
pretty-URL decode looked up a `seo_url` row by `keyword`/`store_id` only,
missing the `language_id` filter this same class's own `rewrite()` method
already applies on every one of its lookups. On a multi-language store
where two different entities in different languages happen to share the
same `keyword` text, this could silently serve the wrong product/category
to a visitor, with no error.

**What changed:** `index()`'s per-segment lookup now filters by
`language_id = config_language_id` first (the request's already-resolved
language — set earlier in the pre-action chain by
`ControllerStartupStartup::index()`); if that returns no rows, it falls
back to the original language-agnostic query so a store with SEO keywords
configured for only one language keeps working unchanged. No other method,
route, or `$data` shape touched.

**Why it can't live in the MintCart layer instead:** `ControllerStartupSeoUrl::index()`
is OpenCart's own registered pre-action (`system/config/catalog.php`'s
`action_pre_action` list) that decodes every incoming storefront request
before any controller runs — there is no MintCart-owned equivalent hook
positioned earlier in the request lifecycle to intercept and correct this
in a separate layer without duplicating the entire pre-action mechanism.

**Compatibility impact:** Additive fallback — every currently-working URL
on a store with no cross-language keyword collision resolves identically
to before. The one behavior change is the fix's actual purpose: a
colliding keyword now resolves to the row matching the visitor's current
language instead of an arbitrary one of the colliding rows.

**Tests:** `php -l` clean. No dedicated regression test — `index()` needs
a live OpenCart `Registry`/`Router` bootstrap this environment can't
provide (same gap as every other `startup/*`/controller-level fix in this
repo). See `docs/fixes/registry.md`'s MC-0009 entry for the full
reproduction/verification writeup and the two manual-verification steps a
human should run on a real multi-language install.
## `upload/system/library/cart/cart.php`

**Reason:** MC-0008 (`docs/fixes/registry.md`) — `Cart::__construct()` ran
an unconditional stale-cart-cleanup `DELETE` query on every single request
(the class is instantiated once per page load by both
`catalog/controller/startup/startup.php` and
`admin/controller/startup/startup.php`), the "excessive session/cart
writes" class of issue `MINTCART_FUNCTIONAL_SCOPE.md` §18 names explicitly.
Wrapped the existing query in
`if ((new \MintCart\Commerce\CartGarbageCollector())->shouldRun()) { ... }`
— a probability gate (default 5% of requests), same shape as PHP's own
`session.gc_probability`/`session.gc_divisor`. Full write-up, including why
this is a strict behavior subset rather than a divergent reimplementation,
is in the MC-0008 registry entry.

**Why it can't live in the MintCart layer instead:** `Cart` is a core
OpenCart library class instantiated directly by both front controllers'
startup sequence, not resolved through any MintCart-owned factory or event
hook — there is no extension point around "which class gets constructed as
`registry->get('cart')`" to intercept from outside this file. The gating
*decision* itself does live in the Mint layer
(`MintCart\Commerce\CartGarbageCollector`, pure, no OpenCart dependency,
independently unit-tested); only the two-line `if` wrapping the existing
query had to land in the core file, which is the smallest possible diff
for this fix.

**Compatibility impact:** The query's own logic (which rows are eligible,
what gets deleted) is completely unchanged — only how often the check runs.
No behavior visible to a legitimate cart owner: their own cart rows are
never touched by this query regardless (it only targets `api_id > '0' OR
customer_id = '0'` rows past the 1-hour mark). The only observable
difference is that an abandoned anonymous cart may now take up to
~20 requests longer, on average, to actually get swept — still bounded,
still eventual, and immaterial next to the 1-hour age threshold itself.
Every other `Cart\Cart` method (`add`/`update`/`remove`/`getProducts`/...)
is untouched.

**Tests:** `php -l` clean on both the modified `cart.php` and the new
`MintCart\Commerce\CartGarbageCollector`.
`tests/mintcart/commerce/cart-garbage-collector-test.php` (7 scenarios,
see the MC-0008 registry entry for what's asserted and why a full
statistical test wasn't attempted). Full `tests/mintcart/` suite re-run
end to end — no regressions (pure addition of one new class plus a
two-line wrap around an existing query in an unrelated file). Same
pre-existing gap as every earlier phase: no MySQL server in this
environment, so the gated query itself was never exercised against a real
database — a human should confirm on a real install per the registry
entry's "Tests" section.
## `upload/system/library/cache/file.php`

**Reason:** MC-0007 (`docs/fixes/registry.md`) — the file-cache backend's
`set($key, $value)` truncated the final cache filename directly via
`fopen($file, 'w')` *before* taking any lock, and that filename only has
second-granularity (`cache.<key>.<time()+expire>`). Two concurrent
`set()` calls for the same key within the same wall-clock second (a real
scenario: several PHP-FPM workers regenerating the same just-expired
catalog/localization/checkout cache entry at once — exactly the domains
`MintCart\Core\Cache\CacheInvalidator`, once wired, will invalidate in
bulk) collide on the identical filename: one writer's in-progress file
gets unlinked-and-recreated by the other's own `delete()`-then-`fopen('w')`
sequence, silently losing whichever write happened first, and a `get()`
racing in between can observe a 0-byte or partially-written file
(`json_decode('')` returns `null`, not the expected `false`/data). Fixed
by writing to a uniquely-named temp file, then `rename()`-ing it into
place — atomic on the same filesystem, so a reader only ever sees the
previous complete file or the new one, never a torn one. While fixing
this, `get()` was also hardened against a related, pre-existing TOCTOU:
the file `glob()` just matched can be deleted by a concurrent `set()`'s
own `delete($key)` before `get()`'s `fopen()` runs, which previously
passed a `false` handle straight to `flock()`/`fread()` and fataled the
request instead of returning a clean cache miss; `filesize($files[0])`'s
separate stat-by-path call (subject to the same race) was replaced with
`stream_get_contents($handle)`, reading through the already-open handle.

**Why it can't live in the MintCart layer instead:** This is OpenCart's
own stock file-cache adapter (`\Cache\File`, selected by default —
`cache_engine` defaults to `file`), instantiated directly by OpenCart's
`\Cache` facade with no MintCart-owned equivalent or wrapper point
upstream of it. A parallel MintCart-owned cache backend would fork the
one cache every controller/model in this codebase already reads/writes
through (`CLAUDE.md` §39 — don't duplicate what OpenCart already
provides), and every consumer would need to be repointed at it. Fixing
the existing adapter in place is the smaller, safer diff.

**Compatibility impact:** Purely internal to the write/read mechanics —
`set($key, $value)`/`get($key)`/`delete($key)`'s public signatures,
return types and cache-file naming convention (`cache.<key>.<expire>`)
are all unchanged, so every existing caller (OpenCart core and any
third-party extension) keeps working identically for the non-concurrent
case. The only observable behavior change is the two bugs described above
no longer happening: a `get()` that could previously fatal on a
concurrently-deleted file now returns `false`, and a `set()` that could
previously lose a concurrent write or hand back a torn read no longer
does either.

**Tests:** `php -l` clean on the modified file.
`tests/mintcart/fixes/mc-0007-cache-file-atomic-write-test.php` (7
scenarios: basic set/get round-trip, unset-key miss, no stray `.tmp` file
left behind, exactly one cache file per key after `set()`/after an
overwrite, `delete()` correctness, and a real multi-process concurrency
check — spawns 150 real child PHP processes via `proc_open()` writing the
same key while the parent races `get()` against them, asserting `get()`
never returns `null`/a torn value, only `false` or fully-valid decoded
data). **Verified the test is not vacuous**: re-ran the identical test
against the pre-fix code in isolation — it reliably reproduces the exact
fatal error (`flock(): Argument #1 ($stream) must be of type resource,
false given`) this fix removes. Full `tests/mintcart/**/*-test.php` suite
re-run end to end (all files, including the new one) — all pass, no
regressions.
## `upload/catalog/controller/checkout/payment_method.php`

**Reason:** This file inlined a ~20-line loop — load every installed
`total` extension, sort by `total_<code>_sort_order`, call each enabled
one's `getTotal($total_data)` by reference — **identical** to the same
loop `checkout/confirm.php` also inlined. Extracted into
`MintCart\Compatibility\OpenCart3\TotalsAdapter`
(`docs/developer/compatibility/totals-adapter.md`) once a third real
consumer (`ControllerMintcartCheckout::totals()`, a new fully-additive
`mintcart/checkout/totals` AJAX endpoint) needed the exact same
computation — the same "extract on the third call site" threshold this
repo already established for `ActiveTheme`/`ProductCardListRenderer`
(`CLAUDE.md` §85/§86). `index()` now builds `$totals_adapter = new
\MintCart\Compatibility\OpenCart3\TotalsAdapter($this->config, $this->load,
$this->registry)` and calls `$totals_adapter->calculate($this->cart->getTaxes())`,
keeping only the scalar `$total` this file actually uses afterward (it
never renders the `totals` list itself — only `checkout/confirm.php`
does).

**Why it can't live in the MintCart layer instead:** The loop dynamically
loads whichever `extension/total/<code>` models are installed via
`Loader::model()` and reads them back through `Registry::get()` — both are
OpenCart engine mechanics this controller already has access to via
`$this->load`/`$this->registry`. `TotalsAdapter` *is* the MintCart-layer
home for this logic; what remains in this file is only the call site,
which has to live here because this is where OpenCart's own checkout flow
needs the computed `$total` (to pass into each payment extension's
`getMethod($address, $total)`).

**Compatibility impact:** None — same installed extensions, same
`total_<code>_status`/`total_<code>_sort_order` config values, same
`getTotal($total_data)` by-reference call, same final `$total` value.
Nothing about this controller's own behavior (which payment methods it
shows, in what order) changes; only the totals-computation implementation
moved.

**Tests:** `php -l` clean. `tests/mintcart/compatibility/totals-adapter-test.php`
(5 scenarios — nothing installed, installed-but-disabled,
installed-and-enabled, sorted by `sort_order` regardless of installation
order, a seeded `taxes` array visible to and summed by a total extension).
Full `tests/mintcart/` suite re-run end to end (35 files, was 33) — all
pass, no regressions. Same pre-existing gap as every earlier
controller-wiring increment: no MySQL/OpenCart bootstrap in this
environment — a human should confirm a real checkout still shows the same
payment methods/total after this refactor (see
`docs/MANUAL_VERIFICATION.md`).

---

## `upload/catalog/controller/checkout/confirm.php`

**Reason:** Same duplicated totals-computation loop as
`checkout/payment_method.php` above, refactored the same way to use
`MintCart\Compatibility\OpenCart3\TotalsAdapter`. This file additionally
re-sorted its local `$totals` array by `sort_order` a second time after
the loop (`payment_method.php` never needed to, since it only reads the
scalar `$total`) — `TotalsAdapter::calculate()` now always returns
`totals` pre-sorted, so that second sort was simply removed rather than
kept as a redundant no-op; this is not a behavior change (identical input,
identical `array_multisort` call, run once instead of twice).

**Bug found and fixed while refactoring:** `$order_data['total'] =
$total_data['total'];` (further down `index()`, in the order-insert block)
read the removed by-reference wrapper array `$total_data` — a leftover
alias for the exact same value the plain `$total` variable already holds.
Removing the totals loop without also updating this line would have left
`$order_data['total']` reading an undefined variable's array offset (a
warning plus `null`) on every order placed. Repointed at `$total` directly
— same value, no behavior change, but this line would have quietly broken
order totals had it not been caught here.

**Why it can't live in the MintCart layer instead:** Same rationale as
`payment_method.php` above — `TotalsAdapter` already is the MintCart-layer
home for the computation; what remains here is the call site, needed
because this is where OpenCart's own checkout flow builds the `$order_data`
array passed to `model_checkout_order->addOrder()`.

**Compatibility impact:** None for the computed totals/total (see above).
The `$order_data['total']` fix corrects what would otherwise have been a
newly-introduced regression from this very refactor, not a pre-existing
bug — order totals were correct before this change and remain correct
after it.

**Tests:** `php -l` clean. Same `tests/mintcart/compatibility/totals-adapter-test.php`
suite as `payment_method.php` above (one shared adapter, one shared test).
Full `tests/mintcart/` suite re-run end to end (35 files, was 33) — all
pass, no regressions. Same pre-existing gap as every earlier
controller-wiring increment: no MySQL/OpenCart bootstrap in this
environment — a human should confirm a real order placed through checkout
still records the correct `total` in `oc_order` after this refactor (see
`docs/MANUAL_VERIFICATION.md`).
## `upload/admin/controller/catalog/product.php` / `upload/admin/view/template/catalog/product_form.twig` / `upload/admin/language/en-gb/catalog/product.php`

**Reason:** solving the "admin asset-enqueue problem" that #53/#63 (and
every Phase 12/16/17 increment before them) hit and deliberately punted on
— see `docs/developer/admin/asset-enqueue.md` for the full root-cause
writeup. This is the first admin screen where MintCart's own compiled
bundle is actually enqueued, and the Category field is the previously-
identified (never-attempted) first live `McEntityPicker` consumer
(`docs/developer/components/entity-picker.md` "Admin backends").

1. **Controller:** new `enqueueMintAssets()` method, called at the top of
   the existing `getForm()` (shared by both `add()`/`edit()`) —
   `$this->document->addStyle()`/`addScript()` with
   `assets/dist/css/components.min.css`/`assets/dist/js/admin.min.js`,
   `HTTP_CATALOG`-relative (the same base
   `admin/controller/mintcart/wizard.php` already uses for this path).
   Nothing else in `getForm()` changed.
2. **View:** `product_form.twig`'s Category `form-group` gained a "Browse
   categories" button + a hidden `.mc-entity-picker` panel, additive
   alongside the existing jQuery-autocomplete input/well (not a
   replacement). A new inline `<script>` block (plain vanilla JS, not
   jQuery, per `CLAUDE.md` §7) calls
   `window.MintCart.createEntityPicker()` against the existing
   `mintcart/entity/category` backend and appends confirmed selections
   into the *same* `#product-category{id}`/`product_category[]`
   hidden-input markup the autocomplete's own `select` handler already
   produces.
3. **Language:** six new `text_category_picker_*` keys, same file/pattern
   as the template's existing `entry_category`/`help_category` keys.

**Why it can't live in the MintCart layer instead:** `product_form.twig`
and `ControllerCatalogProduct` are the one true owner of the product
edit screen's form/save logic (`CLAUDE.md` §39 — don't duplicate OpenCart
storage or fork a second product-editing UI). The picker is additive
markup/script inside the existing form, not a new page.

**Compatibility impact:** Purely additive — no existing method signature,
route, or `$data` key changed; `validateForm()`/`addProduct()`/
`editProduct()`'s handling of `product_category[]` is completely
unchanged (the new UI writes the identical hidden-input shape the old one
already did). The existing jQuery autocomplete still works exactly as
before — nothing about it was touched.

**Tests:** `php -l` clean on `product.php` and the language file. Twig
`{% if %}`/`{% endif %}`/`{% for %}`/`{% endfor %}` tag counts checked
balanced before and after (38/38, 37/37). The new inline script was
extracted and checked with `node --check` (valid syntax) since there is no
live browser in this environment to exercise it. Full `tests/mintcart/`
suite re-run end to end — no PHP library code changed here, so no new
regressions expected or found. **Not verified:** no live MySQL/admin
session in this environment — a human should confirm the picker opens,
searches, paginates, and that confirming a selection adds the expected
row to the well and duplicate-frees an already-added category (see
`docs/MANUAL_VERIFICATION.md`'s new entry). Separately and pre-existing
(not introduced by this change, not fixed by it either): this trimmed
repository's `ControllerCatalogProduct::getForm()` never assigns any
`entry_*`/`help_*`/`button_*`/`tab_*` label key (`entry_category`
included) into `$data` — `admin/engine/loader.php`'s `Loader::view()` only
passes the controller's own `$data` array to Twig, with no separate
language-object merge, so every such label (this run's new
`text_category_picker_*` keys included) renders blank in this specific
environment today. Confirmed this is not new: `entry_category` itself,
already in this exact template before this change, has the same gap.
Out of scope to fix here (a much larger, unrelated pass over the whole
file); flagging so it isn't mistaken for something this change broke.

**Update (this run — Phase 12, Entity Picker): Manufacturer field wired
into the same admin screen.** `product_form.twig`'s Manufacturer field
(`#input-manufacturer`/`input[name="manufacturer_id"]`, previously only a
jQuery UI `autocomplete()` field) gains the same "Browse manufacturers"
button + hidden `.mc-entity-picker` panel pattern as the Category field
above, against the already-shipped, previously-consumer-less
`mintcart/entity/manufacturer` backend
(`docs/developer/components/entity-picker.md` "Manufacturer backend").
Unlike Category, this is **single-select** (`multiple: false`) since a
product has exactly one `manufacturer_id`: confirming a selection
overwrites the two existing fields directly instead of appending into a
well, so there is no new hidden-input array shape to reason about at
all — `ControllerCatalogProduct::validateForm()`/`addProduct()`/
`editProduct()`'s handling of `manufacturer`/`manufacturer_id` is
completely untouched. No controller change was needed this run — this
screen's `enqueueMintAssets()` already covers the whole form (added for
Category), not a per-field enqueue. Six new
`text_manufacturer_picker_*` language keys were added, same file/pattern
as Category's own six.

**Why it can't live in the MintCart layer instead:** same reasoning as
the Category entry above — this is the one true owner of the product
edit screen's form.

**Compatibility impact:** Purely additive — no existing method signature,
route, or `$data` key changed; the existing jQuery autocomplete still
works exactly as before. `docs/upstream-diff.md`'s Bulk Editor
manufacturer_id entry (PR touching `updateProductField()`/`product_list.twig`)
is unrelated — different file (`product_list.twig`, not
`product_form.twig`) and different code path (bulk column update, not
the single-product edit form).

**Tests:** `php -l` clean on the language file (no PHP changed in the
controller or any `upload/system/library/mintcart/*` class — this is
Twig/JS/language-file only, reusing the already-tested
`ManufacturerPickerProvider`/`ControllerMintcartEntity::manufacturer()`
backend as-is). Twig `{% if %}`/`{% endif %}`/`{% for %}`/`{% endfor %}`
tag counts checked balanced before and after. Full `tests/mintcart/`
suite (77 files) re-run end to end — all passing, no regressions (no
PHP library code changed here). **Not verified:** no live MySQL/admin
session in this environment — see `docs/MANUAL_VERIFICATION.md`'s new
entry for the full manual script. Same pre-existing "trimmed repository"
label-blank gap noted above applies identically to the six new
`text_manufacturer_picker_*` keys — not a new gap this change
introduces.

**Update (this run — Phase 12, Entity Picker): Related Products field
wired into the same admin screen, closing out the entity-picker backend's
own "identified first consumer" note.** `product_form.twig`'s Related
Products field (`#input-related`/`product_related[]`, previously only a
jQuery UI `autocomplete()` field against `catalog/product/autocomplete`)
gains the same "Browse products" button + hidden `.mc-entity-picker`
panel pattern as the Category field above, against the already-shipped,
previously-consumer-less `mintcart/entity/product` backend
(`docs/developer/components/entity-picker.md` "Product backend"). Like
Category (not Manufacturer), this is **multi-select** (`multiple: true`)
since a product can have many related products: confirming a selection
appends into the exact same `#product-related{id}`/`product_related[]`
hidden-input well markup the autocomplete's own `select` handler already
produces, so `ControllerCatalogProduct::validateForm()`/`addProduct()`/
`editProduct()`'s handling of `product_related[]` is completely
untouched. No controller change was needed this run — this screen's
`enqueueMintAssets()` already covers the whole form (added for Category).
Six new `text_related_picker_*` language keys were added, same
file/pattern as Category's/Manufacturer's own six.

**Why it can't live in the MintCart layer instead:** same reasoning as
the Category/Manufacturer entries above — this is the one true owner of
the product edit screen's form.

**Compatibility impact:** Purely additive — no existing method signature,
route, or `$data` key changed; the existing jQuery autocomplete still
works exactly as before.

**Tests:** `php -l` clean on the language file (no PHP changed in the
controller or any `upload/system/library/mintcart/*` class — this is
Twig/JS/language-file only, reusing the already-tested
`ProductPickerProvider`/`ControllerMintcartEntity::product()` backend
as-is). Twig `{% if %}`/`{% endif %}`/`{% for %}`/`{% endfor %}` tag
counts checked balanced before and after (38/38, 37/37, unchanged — this
change adds no new conditional/loop tags). The new inline script was
extracted and checked with `node --check` (valid syntax), same approach
as the Category/Manufacturer entries, since there is no live browser in
this environment to exercise it. Full `tests/mintcart/**/*-test.php`
suite (81 files) re-run end to end — all passing, no regressions (no PHP
library code changed here). **Not verified:** no live MySQL/admin session
in this environment — see `docs/MANUAL_VERIFICATION.md`'s new entry for
the full manual script. Same pre-existing "trimmed repository"
label-blank gap noted above applies identically to the six new
`text_related_picker_*` keys — not a new gap this change introduces.

**Update (this run — Phase 12, Entity Picker): Attribute tab wired into
the same admin screen.** `product_form.twig`'s Attribute tab (`#attribute`,
previously only a jQuery UI `autocomplete()` field per dynamically-added
row) gains a "Browse" button + hidden `.mc-entity-picker` panel, against
the already-shipped, previously-consumer-less `mintcart/entity/attribute`
backend (`docs/developer/components/entity-picker.md` "Attribute
backend"). Unlike Category/Manufacturer/Related, attribute rows are added
dynamically (`addAttribute()`), so this is **one shared picker instance**
(single-select, `multiple: false`) reused across every row via event
delegation on the `#attribute` table, not a picker-per-field: each row's
new "Browse" button carries `data-mc-action="browse-attribute"
data-mc-attribute-row="N"`; clicking it records which row is active, and
`onConfirm` writes into that row's new `[data-mc-attribute-name]`/
`[data-mc-attribute-id]` hooks. Both the initial Twig-rendered rows
(`{% for product_attribute in product_attributes %}`) and the
JS-generated row markup (`addAttribute()`'s own HTML-string template)
were updated identically so a row added via either path behaves the same.
Confirming a selection writes into the exact same fields
`attributeautocomplete()`'s own jQuery UI `select` handler already writes
to, so `ControllerCatalogProduct::validateForm()`/`addProduct()`/
`editProduct()`'s handling of `product_attribute[]` is completely
untouched. No controller change was needed — this screen's
`enqueueMintAssets()` already covers the whole form (added for Category).
Six new `text_attribute_picker_*` language keys were added, same
file/pattern as Category's/Manufacturer's/Related's own six.

**Why it can't live in the MintCart layer instead:** same reasoning as
the Category/Manufacturer/Related entries above — this is the one true
owner of the product edit screen's form.

**Compatibility impact:** Purely additive — no existing method signature,
route, or `$data` key changed; the existing jQuery autocomplete still
works exactly as before, on every row regardless of whether it was
present at page load or added afterward.

**Tests:** `php -l` clean on the language file (no PHP changed in the
controller or any `upload/system/library/mintcart/*` class — this is
Twig/JS/language-file only, reusing the already-tested
`AttributePickerProvider`/`ControllerMintcartEntity::attribute()` backend
as-is). Twig `{% if %}`/`{% endif %}`/`{% for %}`/`{% endfor %}` tag
counts checked balanced before and after (38/38, 37/37, unchanged — this
change adds no new conditional/loop tags). Both modified inline
`<script>` blocks (and all 8 other, untouched ones) extracted and checked
with `node --check` (all pass), same approach as the Category/
Manufacturer/Related entries, since there is no live browser in this
environment to exercise it. Full `tests/mintcart/**/*-test.php` suite (84
files) re-run end to end — all passing, no regressions (no PHP library
code changed here). **Not verified:** no live MySQL/admin session in this
environment — see `docs/MANUAL_VERIFICATION.md`'s new entry for the full
manual script, which specifically covers the multi-row/shared-instance
behavior this field introduces (browsing into the correct row, resetting
search state between rows, surviving row removal/re-add) that the
single-field Category/Manufacturer/Related pickers didn't need to check.
Same pre-existing "trimmed repository" label-blank gap noted above
applies identically to the six new `text_attribute_picker_*` keys — not a
new gap this change introduces.
## `upload/system/library/db.php`

**Reason:** MC-0006 (`docs/fixes/registry.md`) — `DB::connected()` called
`$this->adaptor->connected()`, but every real adaptor
(`DB\MySQLi`/`DB\PDO`/`DB\PgSQL`) implements `isConnected()`, never
`connected()`. Calling `DB::connected()` against a real adaptor fatals
instead of returning a bool. Fixed the one call site to dispatch to
`isConnected()` instead.

**Why it can't live in the MintCart layer instead:** `db.php` *is* the
core file whose own public method is broken — there's no MintCart-owned
equivalent to route around it, and adding a MintCart-side wrapper just to
work around one core method's internal typo would be an odd indirection
for a one-line fix to the file that already owns this responsibility.

**Compatibility impact:** None. `DB::connected()`'s public signature
(name, visibility, no parameters, returns whatever the adaptor reports) is
unchanged; only its internal delegation target changed, from a method
name no adaptor has ever implemented to the one all three actually do.
Nothing in this repository calls `DB::connected()` today (`grep -rn
"->connected(" upload/` — zero matches), so this has no observable effect
on any existing code path; it only makes the method usable for the first
time.

**Tests:** `php -l` clean. New
`tests/mintcart/fixes/mc-0006-db-connected-adaptor-mismatch-test.php` — see
the MC-0006 registry entry for full detail, including manual confirmation
that the test fatals against the pre-fix code and passes after the fix.
Full `tests/mintcart/` suite re-run end to end (35 files) — all pass, no
regressions.
## `upload/install/cli_install.php`

**Reason:** MC-0005 (`docs/fixes/registry.md`) — `check_requirements()`'s
PHP version pre-flight check warned only below PHP 7.3, using a raw string
`'<'` comparison. Both were wrong: `system/startup.php` — required
unconditionally a few lines above, before `check_requirements()` is ever
reached — already hard-exits below PHP 8.1.0, so the 7.3+ message was
stale, unreachable dead code as well as factually incorrect about what the
tool actually requires. Full reproduction and rationale: `docs/fixes/registry.md`'s
MC-0005 entry.

**Why it can't live in the MintCart layer instead:** This is a one-function
correction inside OpenCart's own CLI installer script, tightly coupled to
its own top-of-file `require_once(DIR_SYSTEM . 'startup.php')` ordering.
There is no separate MintCart-owned CLI install path to move this into —
MintCart's Setup Wizard (Functional Scope §2/`MINTCART_TECHNICAL_PLAN.md`
§21) is a post-install, browser-based onboarding flow, not a replacement
for this stock command-line installer. Fixing the threshold/comparison in
place is the smaller, safer diff.

**Compatibility impact:** None for anyone already on PHP 8.1+ (the only
PHP version range this tool can actually run under in practice, per the
`require_once` ordering explained above) — the fixed check's verdict for
8.1+ versions is unchanged (still "OK"). The only behavior change is the
displayed warning text for PHP < 8.1, which was previously stale/wrong and
is now accurate and matches `system/startup.php`'s own message. No change
to `install()`, `setup_db()`, `write_config_files()`, or any other function
in the file.

**Tests:** `php -l` clean on the modified file. New
`tests/mintcart/fixes/mc-0005-install-php-version-check-test.php` — pure
version-string logic, no OpenCart runtime dependency; see the registry
entry for what it covers. Full `tests/mintcart/**/*-test.php` suite re-run
end to end — all passing, no regressions. **Not verified:** actually
running `cli_install.php` under a real PHP 7.x binary (none available in
this environment) — see the registry entry's own note on this gap.

---

## `upload/install/controller/install/step_2.php`

**Reason:** MC-0005 (`docs/fixes/registry.md`) — `validate()`'s PHP
version pre-flight check already used the correct threshold ("8.1", which
agrees with `install/language/en-gb/install/step_2.php`'s `error_version`
message text and with `system/startup.php`'s own gate), but compared it
with a raw string `'<'` instead of `version_compare()`, unlike every other
PHP-version gate already in this codebase (`system/startup.php` itself,
and ~90 payment/shipping extension call sites). Full reproduction and
rationale: `docs/fixes/registry.md`'s MC-0005 entry.

**Why it can't live in the MintCart layer instead:** A one-line
comparison-operator correction inside OpenCart's own web installer
requirements-check controller. There is no separate MintCart-owned
requirements-check step to move this into.

**Compatibility impact:** None — `version_compare($v, '8.1.0', '<')`
produces the identical verdict to the old `$v < '8.1'` for every version
string this check is actually exercised against (the old two-segment
threshold was already a safe strict-prefix comparison against any real
`8.1.x` patch string, including distro-suffixed ones); the fix is
correctness-by-construction, not a behavior change. No template change, no
change to any of this method's other checks.

**Tests:** `php -l` clean on the modified file. Covered by the same new
`tests/mintcart/fixes/mc-0005-install-php-version-check-test.php` as the
`cli_install.php` entry above (both checks now share the identical
`version_compare(..., '8.1.0', '<')` logic, tested once). Full
`tests/mintcart/**/*-test.php` suite re-run end to end — all passing, no
regressions.
## `upload/admin/controller/tool/backup.php`

**Reason:** MC-0004 (`docs/fixes/registry.md`) — `ControllerToolBackup::import()`'s
safety check that stops a backup restore before it reaches the `user`/
`user_group` tables' `TRUNCATE TABLE` lines compared `$line` against a
*hardcoded* byte-length (`24`/`30`) instead of the actual length of the
`DB_PREFIX`-aware needle string it already builds. Those numbers only
happen to be correct for the stock 3-character `oc_` prefix; any other
`DB_PREFIX` length (OpenCart's own installer, and MintCart's Setup Wizard,
both allow choosing one) makes the comparison never match, so the safety
guard silently never fires and a restore can run straight through the
`user`/`user_group` tables instead of stopping before them. Full
reproduction and rationale: `docs/fixes/registry.md`'s MC-0004 entry.

**Why it can't live in the MintCart layer instead:** This is a one-line
logic error inside OpenCart's own backup/restore streaming loop
(`ControllerToolBackup::import()`), tightly coupled to that method's file
handle/position/batch-count state. There is no separate MintCart-owned
restore mechanism to move this into — Functional Scope §21's
theme/extension rollback is a distinct, not-yet-built feature, not a
replacement for this stock full-database backup/restore tool. Fixing the
length computation in place is the smaller, safer diff.

**Compatibility impact:** None for a stock `oc_`-prefixed install — the
`strlen()`-derived lengths are identical to the old hardcoded `24`/`30` in
that case (see the registry entry's reproduction). Every install with a
different `DB_PREFIX` length now gets the safety guard actually working,
which is a strictly safer behavior change (the guard can only start
firing where it silently didn't before; it can never newly *fail* to fire
somewhere it used to work). No change to the rest of `import()`'s
batching/streaming logic, no template change, no schema change.

**Tests:** `php -l` clean on the modified file. New
`tests/mintcart/fixes/mc-0004-backup-restore-prefix-test.php` (5 scenarios)
— this bug is pure string-length logic with no OpenCart runtime dependency,
so unlike most controller-wiring fixes in this backlog it's fully testable
in isolation without a live install; see the registry entry for what it
covers. Full `tests/mintcart/**/*-test.php` suite re-run end to end — all
passing, no regressions. **Not verified:** an actual restore against a real
non-default-`DB_PREFIX` MySQL install (this environment has no MySQL
server, the same pre-existing gap noted throughout this file) — a human
should confirm per the registry entry's own manual-verification note.
## `upload/catalog/controller/startup/seo_url.php`

**Reason:** Phase 18 (System route URL aliases,
`MINTCART_FUNCTIONAL_SCOPE.md` §4). `ControllerMintcartSeoSystemPages`
(`docs/developer/seo/system-route-aliases.md`) lets a shop owner set a
friendly `seo_url` alias for a fixed whitelist of "system pages"
(`MintCart\Seo\SystemRoutes::ROUTES` — cart, checkout, search, contact,
account, login, register, wishlist, compare, special, manufacturer,
sitemap) that carry no id parameter of their own. Incoming request decoding
of a plain `keyword => route` `seo_url` row already worked with zero
changes — `index()`'s own generic branch (the `$url[0] != '...id'` checks
near the end of the method) already assigns `$query->row['query']` straight
to `$this->request->get['route']`. **Outgoing link generation did not**:
`rewrite()` only special-cases `product_id`/`manufacturer_id`/`path`/
`information_id` inside its `foreach ($data as $key => $value)` loop — a
plain `route=checkout/cart` link with no id keys never matched any branch,
so `$url` stayed empty and the method fell through to `return $link`
unchanged (`index.php?route=checkout/cart` instead of `/cart`, even with a
matching `seo_url` row already saved). Added one small, additive check
right after the existing loop: if `$url` is still empty and `$data['route']`
is one of the twelve known system routes, look up a `seo_url` row by
`query = route` (same query pattern, same store/language scoping, as every
existing branch in this method) and use its `keyword` the same way the
existing branches do.

**Why it can't live in the MintCart layer instead:** `rewrite()` is
OpenCart's own link-rewriting hook, registered against `Url` via
`$this->url->addRewrite($this)` in this same class's `index()` and called
by `Url::link()` for every generated link in the request. There is no
MintCart-owned equivalent hook for outbound URL rewriting; reimplementing
that mechanism in parallel would mean two systems deciding what a link
looks like. Extending the one
existing method that already owns this decision, with a change scoped to
exactly the twelve routes `SystemRoutes` declares (every other route is
completely unaffected — the new check only runs `if (!$url && ...)`, after
every pre-existing branch has already had its chance), keeps this a small,
targeted addition rather than a parallel routing layer.

**Compatibility impact:** Purely additive — the new branch only ever fires
when (a) no earlier branch already produced a `$url` and (b) `$data['route']`
exactly matches one of the twelve whitelisted routes. Every other route
(the vast majority of links a store generates) is untouched, byte-for-byte
identical output to before this change. A store with no `seo_url` row for
any of the twelve routes (the common case until an admin visits the new
**Design → System Pages** screen and sets at least one alias) sees zero
behavior change — the lookup query simply returns no rows, `$url` stays
empty, same `return $link` fallback as before.

**Tests:** `php -l` clean. `MintCart\Seo\SystemRoutes` (the whitelist this
change consults) is covered by `tests/mintcart/seo/system-routes-test.php`
(8 scenarios) — see `docs/developer/seo/system-route-aliases.md` for why
`rewrite()`'s new branch itself has no standalone test (it is four lines of
already-proven-safe SQL/array-membership glue around a method with no
OpenCart-independent seam to unit-test in isolation, same category as this
method's own four pre-existing branches, none of which have a test either).
Full `tests/mintcart/**/*-test.php` suite re-run end to end — all passing,
no regressions. **Not verified:** no live MySQL/`Url`/`Registry` session in
this environment — a human should confirm on a real install that saving an
alias (e.g. `cart` for `checkout/cart`) via **Design → System Pages** makes
both `/cart` resolve to the shopping cart page (already true before this
change, via `index()`'s existing generic decode) **and** every link the
store itself generates to `route=checkout/cart` (e.g. the header cart icon)
come out as `/cart` instead of `index.php?route=checkout/cart` (the part
this change adds) — see `docs/MANUAL_VERIFICATION.md`'s new Phase 18 entry.
## `upload/catalog/controller/product/product.php` / `upload/catalog/language/en-gb/product/product.php`

**Reason:** Phase 6/8 (Product Card / Theme resolver, `MINTCART_TECHNICAL_PLAN.md`
§15C-D/§45). Every listing controller (Category, Search, Manufacturer,
Special) was already wired to `MintCart\Design\ProductCardListRenderer` —
the product *detail* page was the one page in this whole pipeline that
never was. `mint_default` had no `template/product/product.twig` at all
before this change: a shopper on a Mint-aware theme could browse every
listing page but the product page itself rendered nothing Mint-specific.

Three changes:

1. **Controller:** the existing "Related Products" loop (already computing
   `$data['products']` for the legacy `default` theme) now also builds
   `$data['product_cards']` via the same `ProductCardListRenderer`/
   `ActiveTheme`/`ComponentRenderer` trio Category/Search/Manufacturer/
   Special already use — same Mint-aware gate, same per-product try/catch-
   and-degrade-for-the-rest-of-the-list behavior, same shared
   `text_badge`/`text_wishlist`/`text_compare`/etc. language strings built
   inline (not stored on `$data`, since they only feed `ComponentRenderer`,
   never this page's own template). A handful of plain field labels the
   language file already defined but no prior version of this controller
   ever assigned to `$data` for *any* theme (`text_manufacturer`/
   `text_model`/`text_reward`/`text_stock`/`text_points`/`text_discount`/
   `text_option`/`text_related`/`text_tags`/`tab_description`/
   `tab_attribute`/`text_no_reviews`/`entry_qty`) are now assigned too —
   purely additive `$data` keys the legacy `default` theme's own template
   never reads, needed by the brand-new `mint_default` template being
   written from scratch (see `docs/developer/components/product-card.md`
   for why this differs from `search.php`'s own entry, which found the
   *same* class of gap on an *existing* template and left it undocumented
   rather than fixed).
2. **View:** `catalog/view/theme/mint_default/template/product/product.twig`
   (new file) — gallery (`mc-product-gallery`, new component), title/
   manufacturer/model/rating/price/special/tax/stock/discounts, an option
   picker covering every OpenCart option type (select/radio/checkbox/text/
   textarea/date/time/datetime/file), quantity (`mc-quantity`) + Add to
   Cart (`mc-button`, reusing `product-card.js`'s `add-to-cart` handler),
   `mc-tabs` Description/Specification/Reviews (Reviews is a read-only
   placeholder this run — see "Not done yet" below), tags, and the Related
   Products grid via `mc-product-card`.
3. **JS:** `product-card.js`'s `add-to-cart` handler learns to read a
   linked `mc-quantity` input (`data-quantity-input`) and
   `[data-mc-option-id]` fields inside a `[data-mc-product-form]` ancestor
   — a grid card sets neither attribute, so its own behavior is completely
   unchanged; this is additive, not a rewrite. See
   `docs/developer/components/product-card.md`'s "Extended for the product
   detail page" section for the exact contract and its known gaps
   (multi-value checkbox options, file-type options).

**Why it can't live in the MintCart layer instead:** Same rationale as
every other listing-controller entry above — `ProductViewProvider`/
`ProductCardListRenderer`/`ComponentRenderer` are deliberately OpenCart-
agnostic, but something has to call them with real resolved product rows,
and `catalog/controller/product/product.php` is the one file OpenCart
already routes a product-page request to.

**Compatibility impact:** The controller's original `$data['products']`
array (the one the legacy `default` theme's own `product.twig` reads) is
completely untouched — same keys, same values, same computation, same
order. The new `$data['product_cards']`/label keys are purely additive and
only ever populated when the active theme ships a `theme.json` manifest
(`ActiveTheme::isMintAware()`), so a store on `default` or an unrelated
third-party theme pays no extra cost and sees zero behavior change.
`product-card.js`'s `add-to-cart` change is backward compatible: a card
with no `data-quantity-input`/no `[data-mc-product-form]` ancestor behaves
exactly as before (verified by re-running the full frontend build and
confirming the existing `catalog.min.js`/`admin.min.js` bundling still
produces the expected storefront-only scoping).

**Update (this run — Phase 10 (Technical Plan), Diagnostics):** same
`MintCart\Core\Diagnostics` wiring as `category.php`/`search.php`/
`special.php`/`manufacturer.php`'s own "Update" entries — constructs it
right after `$mint_render_product_cards` is resolved (unconditionally,
so it also covers the case where the theme isn't Mint-aware and the
related-products `ComponentRenderer` never runs), gated by
`mintcart_dev_mode`, passes it as `ComponentRenderer`'s 4th constructor
argument, and appends `$diagnostics->summary()` as an HTML comment before
`setOutput()` only when dev mode is enabled. This was the last remaining
`ComponentRenderer` call site in `upload/catalog/controller/product/`
without `Diagnostics` wired in. **Why it can't live in the MintCart
layer:** same reasoning as `category.php`'s entry — a controller is the
only place that already knows both `config_theme` and whether to run the
Mint pipeline. **Compatibility impact:** none when `mintcart_dev_mode` is
unset (the default). **Tests:** `php -l` clean; no new test file (covered
by `component-renderer-test.php`'s existing scenarios, which are
call-site-agnostic); full `tests/mintcart/` suite (77 files) re-run end
to end — all passing, no regressions.

**Update (this run — Phase 18, `MintCart\Seo\SchemaOrgBuilder::product()`
live wiring):** the one remaining `SchemaOrgBuilder` builder method with no
live consumer (`docs/developer/seo/schema-org.md`'s own "Product: built,
not wired" section, blocked until this file's Phase 6/8 `product.twig`
existed) is now wired in, mirroring `category.php`/`search.php`'s
`BreadcrumbList` wiring: same `ActiveTheme::isMintAware(DIR_TEMPLATE,
$mint_theme)` gate re-checked directly (not `$mint_render_product_cards`,
which may already have been flipped `false` by
`ProductCardListRenderer::isEnabled()` after an unrelated related-product
render failure), same `try`/`catch (\Throwable)` +
`MintCart\Core\Logger` degrade-and-log pattern, placed right before
`$mint_diagnostics->mark('view_rendered')`. The raw numeric price passed to
the builder is computed fresh from `$product_info['price']`/`['special']`
(not reused from the `$tax_price` local, which by this point in the method
has been reassigned inside the related-products `foreach` loop to the last
related product's price, not the main product's) — `false` when
`config_customer_price` hides pricing from a logged-out customer, matching
`SchemaOrgBuilder::product()`'s own "`false` means hidden" contract.
`image`/`url`/`description`/`sku`/`brand` reuse `$data['popup']`/
`$data['share']`/a `strip_tags()`'d copy of `$data['description']`/
`$product_info['sku']`/`$product_info['manufacturer']` — no new data
fetching, all values the controller already resolved. Rendered as a second
`<script type="application/ld+json">` block right after `{{ header }}` in
`product.twig`, same position `category.twig` uses for its own
`mc_schema_breadcrumb` block. **Why it can't live in the MintCart layer:**
same rationale as `category.php`/`search.php`'s own Schema.org entries —
`SchemaOrgBuilder` is deliberately OpenCart-agnostic; something has to
resolve real product values and call it. **Compatibility impact:** none —
purely additive `$data['mc_schema_product']` key, only populated on a
Mint-aware theme, only rendered by `mint_default/product.twig`'s own new
`{% if %}` guard; the legacy `default` theme's product page is untouched.
**Tests:** `php -l` clean; `product.twig`'s Twig `if`/`endif`/`for`/
`endfor` tag counts re-checked balanced (30/30, 11/11); full
`tests/mintcart/**/*-test.php` suite (77 files) re-run end to end — all
passing, no regressions (`SchemaOrgBuilder::product()` itself already
covered by `tests/mintcart/seo/schema-org-builder-test.php`'s existing 16
scenarios; this is wiring-only). **Not verified:** no live MySQL/Twig
session in this environment — a human should load a real product page on a
Mint-aware theme and confirm the `Product` JSON-LD block validates against
Google's Rich Results Test / schema.org validator and matches the visible
price/stock/rating (see `docs/MANUAL_VERIFICATION.md`'s Phase 18 Schema.org
section).

**Not done yet, by design:**
- **Reviews tab is a read-only placeholder** (`text_no_reviews` only,
  Reviews are loaded via a separate `product/product.review` AJAX endpoint
  and a write-review form in the legacy theme, both jQuery-driven) — a
  real Mint-native reviews UI (list + write form) is a separate, self-
  contained increment, not attempted here to keep this one focused on the
  page's core "browse and buy" path.
- **Recurring/subscription payment profiles** (`$data['recurrings']`) have
  no picker on this page yet — a product that requires selecting one still
  redirects back to itself on Add to Cart via the existing
  `mintcart/cart/add` "missing required option/recurring" fallback
  (`ControllerMintcartCart::add()`, Phase 15), the same way an unanswered
  required product option does; not a regression, since no Mint-native
  product page existed to pick one before this change either.
- **Multi-value checkbox options and file-upload options** — see the
  "Known gap" paragraph in `docs/developer/components/product-card.md`'s
  "Extended for the product detail page" section.

**Tests:** `php -l` clean on both touched PHP files, re-swept across the
full `upload`/`tests` tree; full `tests/mintcart/**/*-test.php` suite
re-run end to end — all passing, no regressions (no existing pure-logic
class was modified by this increment, only controller/language/JS wiring
around already-tested classes). `product.twig`'s Twig `if`/`elseif`/`else`/
`endif`/`for`/`endfor` tag counts checked balanced (29/29, 11/11) with the
same script-based technique used for every earlier `.twig` file in this
repo. `npm install` (0 vulnerabilities) + `npm run build` (minified) both
compile cleanly; `mc-product-gallery`/`gallery-thumb` confirmed present in
`catalog.min.js`/`index.min.css` and **absent** from `admin.min.js`
(storefront-only, same scoping technique every other Phase 6/7/15
component doc uses); `npm test` (preset-token contract checks, unaffected)
still passing. **Not verified:** no live MySQL/`vendor/twig/twig` session
in this environment (same pre-existing gap as every earlier phase) — a
human should load a real product page on a `mint_default`-themed install
and confirm the gallery/options/add-to-cart/tabs/related-products all
render and behave correctly, and that the legacy `default` theme's own
product page is pixel-identical to before this change. See
`docs/MANUAL_VERIFICATION.md`'s new entry for the exact steps.

**Update (this run — Phase 18 (SEO Core), MC-0016):** the controller's
`$this->document->setTitle($product_info['meta_title']);
$this->document->setDescription($product_info['meta_description']);`
pass-through (byte-for-byte the same blank-title defect MC-0015 already
fixed on `category.php`/`home.php`) now routes through
`MintCart\Seo\MetaBuilder::build()`, falling back to `$product_info['name']`
when `meta_title` is blank. Deliberately deferred from the original
Phase 6/8 increment above and MC-0015 itself, since at the time both
shipped this file was already being edited by the Diagnostics wiring
increment (also documented above) — this run picked it up once that
collision cleared. **Why it can't live in the MintCart layer:** same
reasoning as every other `MetaBuilder`/`Diagnostics` call site — a
controller is the only place that already has both the entity's raw field
and a sensible fallback value on hand. **Compatibility impact:** none when
`meta_title`/`meta_description` are already filled in (the fallback only
applies when blank); `setKeywords()` is untouched. **Tests:** `php -l`
clean; `tests/mintcart/seo/meta-builder-test.php`'s pre-existing scenario 6
already covers this exact shape, no new test file needed; full
`tests/mintcart/` suite (77 files) re-run end to end — all passing, no
regressions. **Not verified:** no live MySQL/Twig session in this
environment — see `docs/fixes/registry.md` MC-0016 for the manual step a
human should run.

**Update (2026-09-18, routine run — Performance, responsive images):**
`$data['thumb']` (main product image) and each `$data['images'][]` entry's
`'thumb'` (gallery strip thumbnail) already resize via
`theme_<theme>_image_thumb_width`/`_height` and
`theme_<theme>_image_additional_width`/`_height` config respectively —
this run captures those same values into `$data['thumb_width']`/
`$data['thumb_height']` and each image's own `'thumb_width'`/
`'thumb_height'` so `mint_default/template/product/product.twig` can
render explicit `<img width>`/`<height>` (`MINTCART_FUNCTIONAL_SCOPE.md`
§19 "responsive images; width/height" — prevents layout shift), same
convention as the `category.php` entry above. No resize behavior change —
the same config values are passed into `resize()` as before, just also
captured into named variables/array keys. The gallery thumbnail strip
(`mc-product-gallery__thumb-image`, small below/beside the main image)
also gains `loading="lazy"`; the main gallery image
(`mc-product-gallery__main`, the page's LCP candidate) deliberately does
not, matching `product-card.twig`'s own already-established convention of
never lazy-loading the primary above-the-fold image. **Why it can't live
in the MintCart layer:** `$data`/`$data['images']` are this controller's
own view-data arrays; there is no other place to add these keys.
**Compatibility impact:** none — purely additive keys/attribute, no
existing key/behavior changed; the legacy `default` theme's own
`product.twig` is untouched and never reads these new keys. **Tests:**
`php -l` clean; full `tests/mintcart/` suite (81 files) re-run end to
end — all passing, no regressions (no new test file needed — this is
config-value threading, not new logic to unit-test). `product.twig`'s
Twig `if`/`endif`/`for`/`endfor` tag counts checked balanced before and
after (36/36, 11/11). **Not verified:** no live MySQL/Twig session in
this environment — see `docs/MANUAL_VERIFICATION.md`'s new Phase 19
entry.

**Update (2026-09-29, routine run — Phase 10, Diagnostics JSON surface):**
same `mintcart_diagnostics=json` sibling branch added right before this
file's own `setOutput()` call (the related-products `$mint_diagnostics`
instance) — see `category.php`'s entry above (the "Update (2026-09-29
...)" paragraph) for the full writeup; this file's change is
byte-for-byte the same two-line shape. **Tests:** `php -l` clean; full
`tests/mintcart/` suite re-run end to end — all passing.

---

## `upload/admin/model/catalog/product.php` / `upload/admin/controller/catalog/product.php` / `upload/admin/view/template/catalog/product_list.twig`

**Reason:** Phase 16 (Bulk Editor, `MINTCART_FUNCTIONAL_SCOPE.md` §7/§17,
`MINTCART_TECHNICAL_PLAN.md` §29, `MINTCART_IMPLEMENTATION_GUIDE.md` §62)
needed a mass status/quantity/price update over the existing `Catalog →
Products` multi-select checkboxes. Full design writeup:
`docs/developer/catalog/bulk-editor.md`. Three changes:

1. **Model:** a new, additive `updateProductField($product_id, $field,
   $value)` method — a single-column partial `UPDATE oc_product SET
   <field> = ..., date_modified = NOW()`, deliberately **not**
   `editProduct()` (which deletes and reinserts descriptions/attributes/
   options/discounts/specials/etc. wholesale on every call, and would
   silently wipe them if called with only a partial field set). `$field`
   must be one of the three columns `MintCart\Catalog\BulkProductUpdateValidator::FIELDS`
   whitelists (`status`/`quantity`/`price`); an unrecognized field is a
   defensive no-op.
2. **Controller:** a new, fully-additive `bulkUpdate()`/`validateBulkUpdate()`
   pair (route `catalog/product.bulkupdate`) — same form-POST-then-redirect
   shape as the existing `delete()`/`copy()` actions (same `selected[]`
   checkboxes, same filter-preserving redirect URL), fed by the new
   `MintCart\Catalog\BulkProductUpdateValidator` for field/value validation
   and selected-id normalization.
3. **View:** `product_list.twig`'s toolbar gained one `<select>` +
   `<input>` + submit button, using `type="submit"`/`formaction`/`onclick="return
   confirm(...)"` rather than the existing Delete button's `type="button"`
   + manual `$('#form-product').submit()` pattern — see
   `docs/developer/catalog/bulk-editor.md` "Why `type=\"submit\"`..." for
   why copying Delete's own pattern verbatim would have been unsafe for a
   button whose `formaction` differs from the form's default `action`.

**Why it can't live in the MintCart layer instead:** `oc_product` is
OpenCart core storage (`CLAUDE.md` §39 — do not duplicate OpenCart DB). A
new MintCart-owned table/service that re-implemented product field
storage alongside the existing one would fork a single source of truth
into two. Extending the existing model/controller/template — the same
place `addProduct()`/`editProduct()`/`deleteProduct()` and their `delete()`/
`copy()` actions already live — keeps the product table's one true owner
in one place.

**Compatibility impact:** Purely additive — no existing method signature,
column, or return value changed; `editProduct()`'s own behavior is
unchanged. The toolbar addition to `product_list.twig` is a new button
alongside the existing Add/Copy/Delete/Filter buttons, not a change to
any of them.

**Tests:** `php -l` clean on all three touched/added PHP files (both admin
files and the new `MintCart\Catalog\BulkProductUpdateValidator`). New
`tests/mintcart/catalog/bulk-product-update-validator-test.php` (25
scenarios — field whitelist, per-field value validation including every
rejection case, product-id normalization/dedup/dropping non-positive
ids). Full `tests/mintcart/` suite re-run end to end — all pass, no
regressions. `product_list.twig`'s `{% if %}`/`{% endif %}`/`{% for %}`/
`{% endfor %}` tag counts checked balanced before and after the toolbar
addition. Same pre-existing gap as every earlier phase: no MySQL in this
environment, so `updateProductField()` and the `bulkUpdate()` action
itself were never exercised against a real database — a human should
confirm on a real install, in particular that a bulk update leaves every
other relation (descriptions, options, discounts, specials) untouched —
see `docs/MANUAL_VERIFICATION.md`'s new Phase 16 section.

**Update (this run — Phase 16, Bulk Editor):** added `manufacturer_id` as
a fourth whitelisted bulk-update field, same shape as status/quantity/
price — `BulkProductUpdateValidator::FIELDS` gains the entry (validated
as a non-negative integer, `0` meaning "no manufacturer", matching
OpenCart's own product-edit-form convention), `updateProductField()`
gains the matching column mapping, and `product_list.twig`'s toolbar
`<select>` gains a "Manufacturer" option (plus its
`text_bulk_field_manufacturer` language key). **Why it can't live in the
MintCart layer:** same reasoning as the entry above — `oc_product` stays
the one true owner of its own columns. **Compatibility impact:** none —
purely additive, no existing field/column/behavior changed. **Tests:**
`php -l` clean on all touched files;
`tests/mintcart/catalog/bulk-product-update-validator-test.php` grows
from 27 to 34 assertions (manufacturer_id whitelist + value-validation
coverage, including the rejection cases); full `tests/mintcart/` suite
(77 files) re-run end to end — all passing, no regressions.
`product_list.twig`'s tag counts re-checked balanced (14/14, 2/2) after
the new `<option>`. Same pre-existing gap: no live MySQL/admin session in
this environment — see `docs/MANUAL_VERIFICATION.md`'s updated Phase 16
section.

**Update (2026-09-18, routine run — Phase 16, Bulk Editor):** added
`special_price` as a sixth whitelisted bulk-update field.
`BulkProductUpdateValidator::FIELDS` gains the entry (blank = remove the
special, or a non-negative number). Unlike every prior field,
`special_price` does not map to an `oc_product` column — it lives in
`oc_product_special` — so `ModelCatalogProduct::updateProductField()`
gained a dedicated branch for it instead of a `$columns` map entry:
delete every existing `oc_product_special` row for the product, then (only
if a non-blank value was given) insert exactly one row at the store's
default customer group (`config_customer_group_id`), `priority = 1`, no
date range (`date_start`/`date_end` both `'0000-00-00'`) — a deliberate
simplification of the full product-edit form's multi-row/date-ranged
Special support, the simplest thing a single `bulk_value` text input can
express (full reasoning: `docs/developer/catalog/bulk-editor.md`, "Why a
new model method, not `editProduct()`"). `product_list.twig`'s toolbar
`<select>` gains a "Special Price" option (plus its
`text_bulk_field_special` language key). `category_ids` remains
deliberately excluded from this whitelist — see the same doc's "Next
step" for why (it needs a multi-select control this single-text-input
toolbar doesn't have, and is already validated separately by
`MintCart\Catalog\BulkProductUpdatePlanner`).

**Why it can't live in the MintCart layer:** same reasoning as the two
entries above — `oc_product`/`oc_product_special` stay OpenCart core
storage; extending the existing model/controller/template keeps the
product's special price under one true owner instead of forking it into a
second, Mint-only table.

**Compatibility impact:** none — purely additive. No existing method
signature, column, or `editProduct()`/product-edit-form behavior changed;
a bulk special-price update only touches `oc_product_special` rows for
the exact products the merchant selected in this action.

**Tests:** `php -l` clean on all touched/added files. `tests/mintcart/catalog/bulk-product-update-validator-test.php`
grows from 41 to 46 assertions (special_price whitelist + value-validation
coverage including blank-is-valid and the rejection cases); full
`tests/mintcart/**/*-test.php` suite (81 files) re-run end to end — all
passing, no regressions. `product_list.twig`'s tag counts re-checked
balanced (14/14, 2/2) after the new `<option>`. Same pre-existing gap: no
live MySQL/admin session in this environment — a human should confirm a
bulk special-price update replaces (not adds to) any existing
`oc_product_special` row(s) for a product, including one previously set
via the normal edit form with a non-default customer group or a date
range — see `docs/MANUAL_VERIFICATION.md`'s updated Phase 16 section.

**Update (2026-09-28, routine run — Phase 16, Bulk Editor):** the toolbar's
single generic `bulk_value` text `<input>` is now two controls sharing
that `name` — an Enabled/Disabled `<select>` for the `status` field, a
`type="number"` input (whole numbers for quantity/manufacturer_id/
stock_status_id, decimals for price/special_price) for every other field
— toggled by a small inline `<script>` block on `#input-bulk-field`'s
`change` event, which `disabled`s whichever control is not the active
one. This touches only `product_list.twig`; no PHP file in this section
changed — `BulkProductUpdateValidator`/`ControllerCatalogProduct::bulkUpdate()`
already read `bulk_value` as a plain string regardless of which HTML
control produced it, and their validation is unchanged.

**Why it can't live in the MintCart layer:** it is admin-form UX for one
specific pre-existing OpenCart template, not platform code.

**Compatibility impact:** none — a disabled form control is excluded from
the browser's own POST body, so the controller still only ever receives a
single `bulk_value`, exactly as before; the six existing fields' bulk
updates are otherwise untouched.

**Tests:** no PHP touched, so `tests/mintcart/` was not re-run (nothing it
covers changed). `product_list.twig`'s `{% if %}`/`{% for %}` tag counts
unchanged and re-checked balanced (14/14, 2/2 — no `{% %}` tag touched).
The file's three inline `<script>` blocks (including the new toggle)
checked for JS syntax errors with a standalone Node harness (Twig `{{ }}`
output substituted with a placeholder string) — all parse cleanly. **Not
verified live** — no MySQL/admin session in this environment; see
`docs/MANUAL_VERIFICATION.md`'s updated Phase 16 section (new step 8).

## `upload/catalog/controller/extension/feed/google_sitemap.php`

**Reason:** Phase 18 (SEO Core, `MINTCART_FUNCTIONAL_SCOPE.md` §16 /
`MINTCART_TECHNICAL_PLAN.md` §31)'s last unclaimed sub-item was the XML
sitemap. `MintCart\Seo\SitemapBuilder` (a prior run) shipped as a pure,
tested XML assembler with no live consumer — `docs/developer/seo/sitemap.md`
deliberately deferred wiring a brand-new `mintcart/seo/sitemap.xml` route
because that would need a live OpenCart bootstrap (pagination, multi-store/
multi-language URL resolution) this environment's lack of MySQL can't
verify end to end. This run found a lower-risk real consumer instead: OC3
already ships a working XML sitemap feed at
`ControllerExtensionFeedGoogleSitemap` (the stock "Google Sitemap"
extension, `Marketing → Marketing → Feeds`), gated by its own existing
`feed_google_sitemap_status` setting, already resolving every product/
category/manufacturer/information URL via the same models/`$this->url->link()`
calls `ProductViewProvider`/`ProductListingService` already read from. Its
`index()` method built the `<urlset>`/`<url>`/`<image:image>` XML by hand,
string-by-string. This run replaces that hand-built string concatenation
with `MintCart\Seo\SitemapBuilder::urlset()`, and extends the builder
itself with optional per-entry `images` (an `xmlns:image` block, only
emitted when at least one entry carries images) so the existing Google
Image sitemap extension keeps working through the shared builder instead
of needing its own separate, un-reviewed XML-escaping code path.

**Why it can't live in the MintCart layer instead:** `SitemapBuilder`
itself already does — it is a pure, `\Registry`-free class
(`upload/system/library/mintcart/seo/sitemapbuilder.php`). What remains
controller-specific is exactly what OpenCart's own `Loader`/routing has no
generic hook for: resolving `oc_product`/`oc_category`/`oc_manufacturer`/
`oc_information` rows into `{loc, lastmod, changefreq, priority, images}`
arrays via `$this->load->model(...)` and `$this->url->link(...)`. That
resolution has to happen inside a controller; only the final XML assembly
was worth centralizing, and now is.

**Compatibility impact:** None. Same config gate
(`feed_google_sitemap_status`), same models called in the same order with
the same (empty) arguments, same URLs/`changefreq`/`priority` values per
entry, same recursive category-tree traversal, same image resize call and
`html_entity_decode()` name normalization. The only externally-visible
difference: `SitemapBuilder::escape()` now also escapes the primary
`<loc>`/`<image:loc>` URL text itself (the original hand-built version
escaped `<image:caption>`/`<image:title>` but not `<loc>`/`<image:loc>`) —
a strict correctness improvement for any store URL containing `&` (e.g. a
SEO keyword with a query string), not a behavior change for the common
case where URLs contain no XML-special characters. `<image:caption>`/
`<image:title>` escaping is byte-identical:
`SitemapBuilder::escape()` uses the same `htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8')`
pair the original controller already called by hand.

**Tests:** `php -l` clean on both touched files. `SitemapBuilder`'s own
test (`tests/mintcart/seo/sitemap-builder-test.php`) grows from 15 to 20
scenarios covering the new `images` parameter (no-images byte-identical
output, one image, optional caption/title, blank/duplicate image `loc`
skipped/de-duplicated, `&` escaping in both `image:loc` and
`image:caption`). Full `tests/mintcart/**/*-test.php` suite (77 files)
re-run end to end — all passing, no regressions. **Not verified:** no live
MySQL/OpenCart bootstrap in this environment — a human with a real install
should enable `feed_google_sitemap_status`, request the feed's URL, and
diff its output against the pre-change version for a store with a
representative product/category/manufacturer/information catalog (see
`docs/MANUAL_VERIFICATION.md`'s new Phase 18 "Google Sitemap" section).
**Update (this run — Phase 16, Bulk Editor):** added `stock_status_id` as
a fifth whitelisted bulk-update field, same shape as `manufacturer_id` —
`BulkProductUpdateValidator::FIELDS` gains the entry (validated as a
non-negative integer; `0` is accepted purely because it mirrors
`ControllerCatalogProduct::getForm()`'s own `$data['stock_status_id'] = 0;`
fallback, not because it is a meaningful merchant-chosen target — the
default install's `oc_stock_status` rows start at id `5`),
`updateProductField()` gains the matching column mapping, and
`product_list.twig`'s toolbar `<select>` gains a "Stock Status" option
(plus its `text_bulk_field_stock_status` language key). **Why it can't
live in the MintCart layer:** same reasoning as the entries above —
`oc_product` stays the one true owner of its own columns. **Compatibility
impact:** none — purely additive, no existing field/column/behavior
changed. **Tests:** `php -l` clean on all touched files;
`tests/mintcart/catalog/bulk-product-update-validator-test.php` grows
from 34 to 41 assertions (stock_status_id whitelist + value-validation
coverage, including the rejection cases); full `tests/mintcart/` suite
(77 files) re-run end to end — all passing, no regressions.
`product_list.twig`'s tag counts re-checked balanced (14/14, 2/2) after
the new `<option>`. Same pre-existing gap: no live MySQL/admin session in
this environment — see `docs/MANUAL_VERIFICATION.md`'s updated Phase 16
section.
