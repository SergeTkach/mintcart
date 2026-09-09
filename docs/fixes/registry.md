# MintCart Fixes Registry

Tracks OpenCart bugfixes and compatibility fixes applied by MintCart, per
`CLAUDE.md` §13 / `MINTCART_FUNCTIONAL_SCOPE.md` §17. This is a controlled
list, not "a bag of patches" — every entry must be reproduced (or otherwise
verified) against the current MintCart baseline before it is applied, per
the Fixes Policy:

1. identify affected OpenCart versions;
2. reproduce or verify it on the current MintCart baseline;
3. check whether upstream already fixed it;
4. implement a MintCart solution only if still necessary;
5. add tests.

Entry format: ID, Problem, Affected OpenCart versions, Source, Reproduced
on MintCart baseline, Upstream fixed, MintCart solution, Tests.

---

## MC-0001 — `category.php` never assigns `text_refine`/`text_sort`/`text_limit`/`text_empty`/`button_continue` to `$data`

**Problem:** `catalog/view/theme/*/template/product/category.twig` (both
the stock `default` theme and MintCart's `mint_default`) references five
variables — `text_refine` (the "Refine Search" sidebar heading),
`text_sort` ("Sort By:" label), `text_limit` ("Show:" label), `text_empty`
(the no-products message), and `button_continue` (the empty-category
"Continue" button) — but `ControllerProductCategory::index()`'s success
branch never assigns any of them to `$data`. Twig's default
`strict_variables => false` setting renders an undefined variable as an
empty string rather than throwing, so every affected page element is
silently blank instead of erroring loudly — the kind of gap that's easy to
ship unnoticed.

**Affected OpenCart versions:** Confirmed present in OpenCart 3.0.5.1 (this
repository's documented upstream baseline, `docs/audit.md` §1). Not
independently checked against other 3.x point releases — this environment
has no access to a second OpenCart checkout to diff against, and the fix
below is version-agnostic (it only adds missing `$data` keys the template
already expects, matching a pattern OpenCart's own `search.php` controller
already uses for its equivalent labels), so verifying every intermediate
3.x release wasn't necessary to apply it safely.

**Source:** Discovered directly in this repository while wiring
`ControllerProductSearch` to the shared `ProductCardListRenderer`
(`docs/upstream-diff.md`'s `search.php` entry) — not an imported
historical community patch. Reproduced by grepping
`catalog/view/theme/{default,mint_default}/template/product/category.twig`
for the five keys and cross-referencing every `$data[...] =` assignment in
`category.php`'s success branch: none of the five appear on the left-hand
side anywhere in the file.

**Reproduced on MintCart baseline:** Yes — confirmed against both
`upload/catalog/view/theme/default/template/product/category.twig` (stock,
unmodified) and `upload/catalog/view/theme/mint_default/template/product/category.twig`,
and against the controller's actual `$data[...] =` assignment list (see
above). All five underlying language keys already exist and load
correctly: `text_refine`/`text_sort`/`text_limit`/`text_empty` in
`catalog/language/en-gb/product/category.php`, `button_continue` in the
global `catalog/language/en-gb/en-gb.php` (auto-loaded on every page) — so
this is purely a missing `$data[...] = $this->language->get(...)` call,
not a missing translation.

**Upstream fixed:** Not checked against a newer OpenCart release (no
network/second-checkout access in this environment to diff against
upstream's latest 3.x tag). Since MintCart tracks a pinned 3.0.5.1
baseline (`CLAUDE.md` §1, `MINTCART_TECHNICAL_PLAN.md` §1) rather than
following upstream point releases, this doesn't block applying the fix
here regardless of upstream's status.

**MintCart solution:** Added the five missing assignments to
`ControllerProductCategory::index()`'s success branch, immediately before
the existing `$data['continue'] = $this->url->link('common/home');` line
— the exact same five-line pattern (same keys, same
`$this->language->get(...)` calls) that `search.php` and `special.php`
already established on this same branch for their own new templates. No
new language strings, no template change, no change to
`$data['products']`/`$data['product_cards']`. Applies equally to the
stock `default` theme and `mint_default` — this is a plain bugfix to a
shared core controller, not a Mint-only behavior.

**Tests:** `php -l` clean on the modified `category.php`. No dedicated
regression test added — the fix is five `$this->language->get()` calls
already unit-proven correct by ten other identical call sites in the same
file and by `search.php`'s/`special.php`'s already-tested identical
pattern; a controller-level test would need a live OpenCart bootstrap this
environment can't provide (same pre-existing gap as every other
controller-wiring change in this repo, see `docs/upstream-diff.md`). A
human should confirm on a real install that the category page's "Refine
Search" heading, sort/limit labels, empty-category message and
empty-category "Continue" button are no longer blank, on both the stock
`default` theme and `mint_default`.

---

## MC-0002 — `search.php` never emits `canonical`/`prev`/`next` `<link>` tags

**Problem:** `ControllerProductSearch::index()` never calls
`$this->document->addLink(..., 'canonical'|'prev'|'next')` anywhere in the
file — the search-results page (`route=product/search`) ships with no
canonical URL and no pagination `rel="prev"`/`rel="next"` hints at all.
Its three sibling listing controllers — `product/category.php`,
`product/manufacturer.php`, and `product/special.php` — all implement this
(same `googlewebmastercentral.blogspot.com` "Pagination with rel=next and
rel=prev" comment, same shape: canonical always present, `prev`/`next`
only at the boundaries). Search is squarely a "Known Fix Research Queue"
candidate (`MINTCART_FUNCTIONAL_SCOPE.md` §18's "pagination issue" family)
and directly blocks Functional Scope §16's SEO Core canonical requirement
— a search page is exactly the kind of thin/duplicate-content-prone page
(same results reachable via many `sort`/`order`/`limit`/`page`
combinations) that most needs a canonical tag, and it's the one listing
controller stock OpenCart left without one.

**Affected OpenCart versions:** Confirmed present in OpenCart 3.0.5.1 (this
repository's documented upstream baseline, `docs/audit.md` §1). Not
independently checked against other 3.x point releases — same rationale as
MC-0001 (no second checkout available to diff against, and the fix only
adds behavior already proven correct by three sibling controllers in this
exact codebase).

**Source:** Discovered directly in this repository while auditing the
Known Fix Research Queue candidates in `MINTCART_FUNCTIONAL_SCOPE.md` §18
— not an imported historical community patch. Reproduced by grepping all
four `product/*` listing controllers for `addLink` /
`'canonical'`/`'prev'`/`'next'`: `category.php`, `manufacturer.php`, and
`special.php` each have three matches; `search.php` has zero.

**Reproduced on MintCart baseline:** Yes — `grep -n "addLink" upload/catalog/controller/product/search.php`
returns no results prior to this fix, confirmed against the unmodified
stock file.

**Upstream fixed:** Not checked against a newer OpenCart release (no
network/second-checkout access in this environment). MintCart tracks a
pinned 3.0.5.1 baseline (`CLAUDE.md` §1) rather than following upstream
point releases, so this doesn't block applying the fix here regardless of
upstream's status.

**MintCart solution:** Added a `$canonical_url` block to
`ControllerProductSearch::index()`, placed immediately after the existing
`$data['results'] = sprintf(...)` line (the same relative position
`category.php` uses for its own canonical block). Mirrors the sibling
controllers' established pattern exactly: for `$page == 1` the canonical
URL omits `page`, otherwise it appends `&page=N`; `prev`/`next` links only
appear at the applicable pagination boundary. Deliberately builds the
canonical URL from only the *result-defining* query params — `search`,
`tag`, `description`, `category_id`, `sub_category` — excluding
*display-only* params (`sort`, `order`, `limit`), the same choice
`category.php`'s `path`-only canonical already makes (different sort
orders of the same result set canonicalize to one URL, matching the whole
point of a canonical tag). No template change, no new language strings,
`$data['products']`/`$data['product_cards']` completely unaffected.

**Tests:** `php -l` clean on the modified `search.php`. No dedicated
regression test added — same rationale as MC-0001: the fix is a direct,
minimal port of a pattern already proven correct by three sibling
controllers in this same file tree; a controller-level test would need a
live OpenCart bootstrap this environment can't provide (no MySQL server,
same pre-existing gap noted throughout `docs/upstream-diff.md`). A human
should confirm on a real install that a search-results page's `<head>` now
contains `<link rel="canonical" ...>` (and `rel="prev"`/`rel="next"` where
applicable) with URLs matching this pattern.

---

## MC-0003 — `manufacturer.php` never assigns `text_sort`/`text_limit`/`text_empty`/`button_continue` to `$data`

**Problem:** `catalog/view/theme/*/template/product/manufacturer_info.twig`
(both the stock `default` theme and MintCart's `mint_default`) references
four variables — `text_sort` ("Sort By:" label), `text_limit` ("Show:"
label), `text_empty` (the no-products message), and `button_continue` (the
empty-listing "Continue" button) — but `ControllerProductManufacturer::info()`'s
success branch never assigns any of them to `$data`. Same class of bug as
MC-0001 in `category.php`: Twig's default `strict_variables => false`
renders an undefined variable as an empty string instead of throwing, so
every affected page element is silently blank rather than erroring loudly.

**Affected OpenCart versions:** Confirmed present in OpenCart 3.0.5.1 (this
repository's documented upstream baseline). Not independently checked
against other 3.x point releases — same rationale as MC-0001/MC-0002 (no
second checkout available to diff against, and the fix only adds `$data`
keys the template already expects).

**Source:** Discovered directly in this repository while wiring
`ControllerProductManufacturer::info()` to the shared
`ProductCardListRenderer` (`docs/upstream-diff.md`'s `manufacturer.php`
entry) — not an imported historical community patch. Reproduced by
grepping `catalog/view/theme/{default,mint_default}/template/product/manufacturer_info.twig`
for the four keys and cross-referencing every `$data[...] =` assignment in
`manufacturer.php`'s `info()` success branch: none of the four appear on
the left-hand side anywhere in the file. Confirmed the stock `default`
theme's own `manufacturer_info.twig` references the same four tags
(`{{ text_sort }}`, `{{ text_limit }}`, `{{ text_empty }}`,
`{{ button_continue }}`), so this is not Mint-specific.

**Reproduced on MintCart baseline:** Yes — confirmed against both
`upload/catalog/view/theme/default/template/product/manufacturer_info.twig`
(stock, unmodified) and the controller's actual `$data[...] =` assignment
list. All four underlying language keys already exist and load correctly:
`text_sort`/`text_limit`/`text_empty` in
`catalog/language/en-gb/product/manufacturer.php`, `button_continue` in the
global `catalog/language/en-gb/en-gb.php` (auto-loaded on every page) — so
this is purely a missing `$data[...] = $this->language->get(...)` call,
not a missing translation. Unlike MC-0001, there is no `text_refine`
equivalent here — a manufacturer page has no category tree/refine sidebar.

**Upstream fixed:** Not checked against a newer OpenCart release (no
network/second-checkout access in this environment). MintCart tracks a
pinned 3.0.5.1 baseline (`CLAUDE.md` §1) rather than following upstream
point releases, so this doesn't block applying the fix here regardless of
upstream's status.

**MintCart solution:** Added the four missing assignments to
`ControllerProductManufacturer::info()`'s success branch, immediately
before the existing `$data['continue'] = $this->url->link('common/home');`
line — the same pattern `category.php`/`search.php`/`special.php` already
established on their own branches. No new language strings, no
`$data['products']`/`$data['product_cards']` change. Applies equally to
the stock `default` theme and `mint_default`.

**Tests:** `php -l` clean on the modified `manufacturer.php`. No dedicated
regression test added — same rationale as MC-0001/MC-0002: the fix is four
`$this->language->get()` calls already unit-proven correct by
`category.php`'s identical MC-0001 fix and by every other call site in
this same file. A human should confirm on a real install that the
manufacturer page's sort/limit labels and empty-listing message/button are
no longer blank, on both the stock `default` theme and `mint_default`.

---

## MC-0009 — `seo_url.php`'s incoming URL decode ignores `language_id`, can resolve the wrong entity on a multi-language store

**Problem:** `ControllerStartupSeoUrl::index()` (the pre-action that
decodes an incoming pretty-URL request back into `route`/`product_id`/
`path`/`manufacturer_id`/`information_id`) looks up each URL segment's
`seo_url` row with:

```php
"SELECT * FROM " . DB_PREFIX . "seo_url WHERE keyword = '...' AND store_id = '...'"
```

— no `language_id` filter. `oc_seo_url` has no uniqueness constraint
beyond a plain (non-unique) `KEY` on `keyword` (`install/opencart.sql`
line 3699-3708: `PRIMARY KEY (seo_url_id)`, `KEY query`, `KEY keyword` —
nothing enforcing a keyword is unique even within one store). Nothing
stops two different rows — for two entirely different entities — from
sharing the exact same `keyword` text across two different `language_id`s
on the same store: an admin filling in SEO keywords per-language commonly
leaves a keyword untranslated/reused, or two unrelated products in two
languages happen to get the same auto-suggested slug. When that happens,
this query returns whichever row the database happens to return first —
not necessarily the row for the request's actual language — so a visitor
can silently land on a completely different product/category page than
the URL implies, with no error and no indication anything is wrong.

This is asymmetric with the same class's own `rewrite()` method (outgoing
link generation), which already filters by `store_id` **and**
`language_id` on every one of its lookups (`product_id`/
`manufacturer_id`/`path`/`information_id`, and category path segments) —
the incoming decode side was simply missing the filter the outgoing side
already established as correct.

**Affected OpenCart versions:** Confirmed present in OpenCart 3.0.5.1
(this repository's documented upstream baseline). Not independently
checked against other 3.x point releases — no second checkout available
in this environment to diff against (same rationale as MC-0001–MC-0003);
this exact asymmetry (decode side missing `language_id`, rewrite side
having it) has also been an openly discussed OpenCart community issue for
multi-language stores across the 2.x/3.x line, though that history wasn't
relied on here — the bug was independently reproduced by reading this
repository's own code (`CLAUDE.md` §10/§84), not assumed from memory.

**Source:** Discovered directly in this repository while auditing
`MINTCART_FUNCTIONAL_SCOPE.md` §18's "Known Fix Research Queue"
("multilingual SEO URL issues" candidate) against the current MintCart
baseline — not an imported historical community patch. Reproduced by
reading `index()`'s query (missing `language_id`) side by side with
`rewrite()`'s three lookups in the same file (all three include it), and
confirming `oc_seo_url`'s schema has no constraint that would make two
different-language rows sharing one `keyword` impossible.

**Reproduced on MintCart baseline:** Yes — confirmed against
`upload/catalog/controller/startup/seo_url.php` (stock, unmodified prior
to this fix) and `upload/install/opencart.sql`'s `oc_seo_url` table
definition. Also confirmed `config_language_id` is already set to the
request's actual resolved language (from session/cookie/browser
Accept-Language detection) by `ControllerStartupStartup::index()`, which
runs as an earlier pre-action than `startup/seo_url` in
`system/config/catalog.php`'s `action_pre_action` list — so the value
this fix filters on is genuinely "this request's language," not just the
store's configured default.

**Upstream fixed:** Not checked against a newer OpenCart release (no
network/second-checkout access in this environment). MintCart tracks a
pinned 3.0.5.1 baseline (`CLAUDE.md` §1) rather than following upstream
point releases, so this doesn't block applying the fix here regardless of
upstream's status.

**MintCart solution:** Added `AND language_id = '<config_language_id>'`
to `index()`'s per-segment lookup, mirroring `rewrite()`'s own filtering.
To avoid a behavior regression on a store that has only ever set up SEO
keywords for one language (a very common single-language-effort setup on
an otherwise multi-language store), the language-scoped query only
*replaces* the result when it actually finds a match: if it returns zero
rows, the code falls back to the original language-agnostic query before
concluding the segment doesn't resolve to anything. Net effect: a
language-scoped row wins when one exists (fixing the ambiguous-collision
case this fix targets); a store with no per-language rows at all keeps
working exactly as it did before, for every language. Nothing about
`rewrite()`, `category_id`/`product_id`/`manufacturer_id`/
`information_id` handling below the lookup, or any other controller
changed.

**Compatibility impact:** Additive fallback keeps every currently-working
URL working. The one intentional behavior change: on a multi-language
store where a `keyword` collides across two languages' rows, the visitor
now reaches the row matching their *actual* resolved language instead of
an arbitrary one of the colliding rows — this is the bug being fixed, not
a regression. A narrower edge case worth naming: a store that deliberately
relies on the old collision behavior to always land visitors on one
specific language's page regardless of their detected language (unlikely,
and not a documented/intended OpenCart behavior) would see that specific
row selection change; no code in this repository does that.

**Tests:** `php -l` clean on the modified `seo_url.php`. No dedicated
regression test added — same rationale as MC-0001–MC-0003: `index()` is a
`Controller`-extending pre-action wired through OpenCart's own
`Registry`/`Router`/`Action` machinery (`$this->db`, `$this->config`,
`$this->request`), the same class of dependency `docs/upstream-diff.md`
already notes this environment can't bootstrap live (no MySQL server).
The change itself is a minimal, mechanical addition of the exact
`AND language_id = ...` clause `rewrite()`'s three lookups in this same
file already use, plus a fallback query using the pre-existing,
already-proven-correct original clause verbatim. A human should confirm
on a real multi-language install: (1) with only one language's SEO
keywords configured, every existing pretty URL still resolves exactly as
before, for every store language; (2) after intentionally giving two
different products (in two different languages) the identical keyword,
requesting that keyword resolves to the product matching the visitor's
current language, not an arbitrary one of the two.
