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

## MC-0007 — `Cache\File`'s `set()` is not an atomic write; `get()` fatals on a concurrently-deleted file

**Problem:** `upload/system/library/cache/file.php` is OpenCart's stock
file-based cache backend, the default for a fresh install
(`cache_engine` defaults to `file` in `upload/system/config/default.php`).
Two independent bugs, both in the "cache race conditions" family of
`MINTCART_FUNCTIONAL_SCOPE.md` §18's Known Fix Research Queue:

1. `set($key, $value)` opened the final cache filename directly —
   `fopen($file, 'w')` truncates it — *before* taking any lock. The
   filename only has second-granularity
   (`cache.<key>.<time()+expire>`), so two concurrent `set()` calls for
   the same key within the same wall-clock second (several PHP-FPM
   workers regenerating the same just-expired entry at once, a routine
   occurrence under real traffic — a "cache stampede") collide on the
   identical filename: each call's own leading `delete($key)` can unlink
   the *other* call's in-progress file, silently discarding whichever
   write happened first, and a `get()` racing in between can observe a
   0-byte or partially-written file (`json_decode('')` returns `null`,
   not the expected `false`).
2. `get($key)` calls `fopen($files[0], 'r')` on a file `glob()` just
   found to exist, with no check that `fopen()` actually succeeded —
   if a concurrent `set()`'s own `delete($key)` (step 1's same
   mechanism) removes that exact file in the gap between `glob()` and
   `fopen()`, `fopen()` returns `false` and the very next line
   (`flock($handle, ...)`) fatals the request with a `TypeError`
   instead of returning a clean cache miss. `filesize($files[0])`
   (a second, independent stat-by-path call) was subject to the same
   TOCTOU window.

**Affected OpenCart versions:** Confirmed present in OpenCart 3.0.5.1
(this repository's documented upstream baseline, `docs/audit.md` §1). Not
independently checked against other 3.x point releases — same rationale
as MC-0001–MC-0003/MC-0004–MC-0006 (no second checkout available in this
environment to diff against, and the fix is a self-contained rewrite of
one small class's read/write mechanics with no OpenCart-version-specific
assumptions).

**Source:** Discovered directly in this repository while working through
`MINTCART_FUNCTIONAL_SCOPE.md` §18's "cache race conditions" Known Fix
Research Queue candidate — not an imported historical community patch.
Reproduced with a real multi-process concurrency test (see "Tests"
below), not merely inferred from reading the code.

**Reproduced on MintCart baseline:** Yes. A standalone test spawning 150
real child PHP processes (`proc_open()`) all calling `set()` on the same
key while the parent process races `get()` against them reliably
reproduces bug 2's fatal error
(`flock(): Argument #1 ($stream) must be of type resource, false given`)
against the unmodified stock file — confirmed by running the identical
test against a saved copy of the pre-fix code in isolation.

**Upstream fixed:** Not checked against a newer OpenCart release (no
network/second-checkout access in this environment). MintCart tracks a
pinned 3.0.5.1 baseline (`CLAUDE.md` §1) rather than following upstream
point releases, so this doesn't block applying the fix here regardless of
upstream's status.

**MintCart solution:** `set()` now writes to a uniquely-named temp file
(`.cache-tmp.<uniqid>`, deliberately outside the `cache.*` glob namespace
so it's invisible to `get()`/`delete()`/the constructor's own stale-file
sweep while in progress) and `rename()`s it into the final `cache.<key>.<expire>`
path once fully written — `rename()` on the same filesystem is atomic, so
a concurrent `get()` only ever sees the previous complete file or the new
one, never a torn one, and two same-second writers for the same key no
longer stomp each other's in-progress file. `get()` now treats a failed
`fopen()` (the file having been deleted by a concurrent writer between
`glob()` and `fopen()`) as a cache miss (`return false`) instead of
fataling, and reads via `stream_get_contents($handle)` on the already-open
handle instead of a second, separately-raced `filesize($files[0])` stat.
Public method signatures, return types and the on-disk cache filename
convention are all unchanged — every existing caller (OpenCart core and
any third-party extension) keeps working identically for the
non-concurrent case. Full writeup: `docs/upstream-diff.md`'s
`upload/system/library/cache/file.php` entry.

**Tests:** `php -l` clean on the modified file.
`tests/mintcart/fixes/mc-0007-cache-file-atomic-write-test.php` — 7
scenarios (basic set/get round-trip, unset-key miss, no stray `.tmp` file
survives a `set()`, exactly one cache file per key after `set()`/after an
overwrite, `delete()` correctness, and the real multi-process concurrency
check described above). Verified not vacuous: the identical test run
against the pre-fix code fails with the exact fatal error this fix
removes (see "Reproduced on MintCart baseline"). Full
`tests/mintcart/**/*-test.php` suite re-run end to end — all passing, no
regressions.
