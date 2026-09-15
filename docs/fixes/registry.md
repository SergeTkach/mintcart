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

## MC-0004 — `tool/backup`'s user/user_group safety-stop uses a hardcoded byte-length instead of `DB_PREFIX`'s actual length

**Problem:** `ControllerToolBackup::import()`
(`upload/admin/controller/tool/backup.php`) streams a `.sql` backup file
back in and executes each `TRUNCATE TABLE`/`INSERT INTO` statement in
batches. Before executing a line, it deliberately checks whether that line
is the `TRUNCATE TABLE` for the `user` or `user_group` table and, if so,
stops the import there (`fseek()`s back and `break`s) rather than
executing it — a safety guard so restoring an old backup mid-session can't
truncate the very `user`/`user_group` tables the currently logged-in admin
account lives in. That check is:

```php
if ($i > 0 && (substr($line, 0, 24) == 'TRUNCATE TABLE `' . DB_PREFIX . 'user`'
    || substr($line, 0, 30) == 'TRUNCATE TABLE `' . DB_PREFIX . 'user_group`')) {
```

`24` and `30` are hardcoded byte-lengths of the two needle strings — but
they are only correct when `DB_PREFIX` is exactly 3 characters long (the
stock default, `oc_`). OpenCart's own installer lets an installer choose
*any* `DB_PREFIX` (MintCart's own Setup Wizard does too, per
`MINTCART_FUNCTIONAL_SCOPE.md` §2's Setup Wizard step list). For any other
prefix length, `substr($line, 0, 24)`/`substr($line, 0, 30)` compares the
wrong number of characters against the (correctly-built, `DB_PREFIX`-aware)
needle string, so the comparison can never be `true` — the safety guard
silently never fires, and the import loop keeps executing straight through
the `user`/`user_group` `TRUNCATE TABLE`/`INSERT INTO` statements instead
of stopping before them. This is exactly the "hardcoded database prefix in
backup/restore logic" item `MINTCART_FUNCTIONAL_SCOPE.md` §18's Known Fix
Research Queue names explicitly.

**Affected OpenCart versions:** Confirmed present in OpenCart 3.0.5.1 (this
repository's documented upstream baseline, `docs/audit.md` §1). Not
independently checked against other 3.x point releases — same rationale as
MC-0001–MC-0003 (no second checkout available to diff against; the fix is
a self-contained, version-agnostic correction of a length computation, not
a behavior redesign).

**Source:** Discovered directly in this repository while auditing the
Known Fix Research Queue candidates in `MINTCART_FUNCTIONAL_SCOPE.md` §18
— not an imported historical community patch. Reproduced with a plain PHP
one-liner (`CLAUDE.md` §84 — verified against the real file, not assumed
from memory): with `DB_PREFIX = 'oc_'`, `strlen('TRUNCATE TABLE \`oc_user\`')`
is exactly 24 and `strlen('TRUNCATE TABLE \`oc_user_group\`')` is exactly
30 — which is *why* this bug has shipped unnoticed (the hardcoded numbers
happen to be right for the one prefix every default install uses). With
`DB_PREFIX = 'mintcart_'` the same two needles are 30 and 36 bytes long —
the hardcoded `24`/`30` comparison no longer matches either one.

**Reproduced on MintCart baseline:** Yes — confirmed by reading the actual
file (`upload/admin/controller/tool/backup.php`, lines 92–96 prior to this
fix) and computing the real needle lengths for both the default prefix and
a representative custom prefix, as described above.

**Upstream fixed:** Not checked against a newer OpenCart release (no
network/second-checkout access in this environment). MintCart tracks a
pinned 3.0.5.1 baseline (`CLAUDE.md` §1) rather than following upstream
point releases, so this doesn't block applying the fix here regardless of
upstream's status.

**MintCart solution:** Replaced the two hardcoded length literals with
`strlen()` of the same needle strings the code already builds from
`DB_PREFIX` — the needles themselves were already correct
(`DB_PREFIX`-aware); only the length used to slice `$line` for comparison
was wrong. No behavior change for the default `oc_` prefix (the lengths
computed by `strlen()` are identical to the old hardcoded `24`/`30` in
that case — see the reproduction above); every other `DB_PREFIX` length
now has the safety guard actually trigger. No new files, no schema change,
no change to the rest of `import()`'s batching/streaming logic.

**Tests:** `php -l` clean on the modified `backup.php`. New
`tests/mintcart/fixes/mc-0004-backup-restore-prefix-test.php` (5 scenarios)
— unlike MC-0001–MC-0003, this bug is pure string-length logic with no
OpenCart runtime dependency, so it's fully testable in isolation: it
reproduces the pre-fix hardcoded-length comparison failing for a 9-char
and a 2-char and an empty custom prefix (while confirming it happens to
still work for the stock 3-char `oc_` prefix, which is why the bug shipped
unnoticed), then confirms the fixed length-from-`strlen()` comparison
succeeds for all of them, plus a negative check that the fixed comparison
doesn't false-positive on an unrelated table merely starting with `user`
(e.g. a hypothetical `user_settings` table). A human should still confirm
on a real install with a non-default `DB_PREFIX`: start a backup restore
of a file containing rows after the `user`/`user_group` tables, and
confirm the import stops at those tables rather than continuing through
them.

---

## MC-0005 — Install-time PHP version pre-flight checks are stale/fragile and disagree with the real requirement

**Problem:** MintCart has two separate PHP version pre-flight checks that
run before installation actually starts:

- `install/cli_install.php`'s `check_requirements()` warned only below
  **PHP 7.3**, using a naive string comparison: `phpversion() < '7.3'`.
- `install/controller/install/step_2.php`'s `validate()` (the web
  installer's requirements step) warned only below **PHP 8.1**, also with
  a naive string comparison: `phpversion() < '8.1'`.

Neither matches the actual enforced requirement: `system/startup.php` —
loaded unconditionally on *every* request path (catalog, admin, and, for
the CLI tool, `cli_install.php` itself via
`require_once(DIR_SYSTEM . 'startup.php');` a few lines above
`check_requirements()`) — hard-exits with `PHP8.1+ Required` below PHP
8.1.0. Two concrete consequences:

1. `cli_install.php`'s own "PHP7.3+" message can never actually be shown
   to a user. On PHP 7.3–8.0, `startup.php`'s stricter guard already kills
   the process at `require_once` time, before `check_requirements()` is
   ever reached (it's only called later, from inside `install()`, itself
   only reached after CLI argument parsing succeeds). The 7.3+ message is
   dead code that also happens to be factually wrong about what the tool
   actually requires.
2. Both checks used a raw string `'<'` comparison instead of
   `version_compare()`, unlike every other PHP-version gate already in
   this codebase (`system/startup.php` itself, and ~90 payment/shipping
   extension call sites) — fragile by construction even where it happens
   not to misfire for typical version strings today.

This is exactly the "PHP 8.x compatibility problems" / "installer edge
cases" pairing `MINTCART_FUNCTIONAL_SCOPE.md` §18's Known Fix Research
Queue names.

**Affected OpenCart versions:** Confirmed present in OpenCart 3.0.5.1 (this
repository's documented upstream baseline, `docs/audit.md` §1). Not
independently checked against other 3.x point releases — same rationale as
MC-0001–MC-0004 (no second checkout available to diff against; the fix is
a self-contained, version-agnostic correction).

**Source:** Discovered directly in this repository while auditing the
Known Fix Research Queue candidates in `MINTCART_FUNCTIONAL_SCOPE.md` §18
— not an imported historical community patch. Reproduced by reading
`cli_install.php` top to bottom (`CLAUDE.md` §84 — verified against the
actual file, not assumed from memory): `require_once(DIR_SYSTEM .
'startup.php')` executes unconditionally at line 40, and
`check_requirements()`'s `'7.3'` check is defined at line 144 and only
ever invoked (from `install()`) after that `require_once` has already run
— so any PHP version between 7.3 and 8.0 that would make the old check
raise its warning can never reach that line at all. Cross-checked
`system/startup.php`'s own threshold (`'8.1.0'`, via `version_compare()`)
and confirmed `step_2.php`'s companion "PHP 8.1" message
(`install/language/en-gb/install/step_2.php`) already agreed with the
right number — only its comparison *operator* was fragile, not its
threshold.

**Reproduced on MintCart baseline:** Yes — via direct code reading and
execution ordering, as described above (a real PHP < 8.1 binary isn't
available in this environment to demonstrate the process exiting live,
but the ordering of `require_once` vs. the `check_requirements()`
call site is unambiguous from the file itself). The regression test
below additionally proves, for a representative set of version strings,
that the fixed logic's verdict now matches `system/startup.php`'s own
verdict exactly, and that a distro-packaged version string (e.g. Debian/
Ubuntu's `8.1.2-1ubuntu2.14`) — which a raw string comparison is more
exposed to than a two-segment threshold against a plain `X.Y.Z` string —
is still handled correctly by `version_compare()`.

**Upstream fixed:** Not checked against a newer OpenCart release (no
network/second-checkout access in this environment). MintCart tracks a
pinned 3.0.5.1 baseline (`CLAUDE.md` §1) rather than following upstream
point releases, so this doesn't block applying the fix here regardless of
upstream's status.

**MintCart solution:** `cli_install.php`'s `check_requirements()` now
checks `version_compare(phpversion(), '8.1.0', '<') === true` (matching
`system/startup.php`'s own threshold and comparison exactly) and its
message now reads "PHP 8.1+" instead of the stale, unreachable "PHP7.3+".
`step_2.php`'s `validate()` keeps its already-correct "8.1" threshold but
now also uses `version_compare(phpversion(), '8.1.0', '<') === true`
instead of a raw string `'<'`, for the same robustness every other
PHP-version gate in this codebase already has. No behavior change for any
version string these two checks are actually exercised against today
(2-segment `'8.1'` was already a safe strict-prefix comparison against any
real `8.1.x` patch string); the fix is about correctness-by-construction
and, for `cli_install.php`, an accurate message rather than a dead,
misleading one. No template change, no schema change, no change to either
function's other checks (`file_uploads`, `session.auto_start`, extension
checks).

**Tests:** `php -l` clean on both modified files. New
`tests/mintcart/fixes/mc-0005-install-php-version-check-test.php` (pure
logic, no OpenCart runtime dependency, like MC-0004's test) — proves the
old `cli_install.php` threshold accepted three versions (`7.3.0`,
`7.4.33`, `8.0.30`) that `system/startup.php`'s own gate already rejects
(the unreachable-dead-code claim above, made concrete), that the fixed
logic's verdict matches `system/startup.php`'s verdict exactly across nine
representative version strings, and that a distro-suffixed version string
is handled correctly on both sides of the 8.1.0 boundary. Full
`tests/mintcart/**/*-test.php` suite re-run end to end (35 files, was 34
at the start of this run) — all passing, no regressions. **Not verified:**
actually invoking `cli_install.php` under a real PHP 7.x binary (none
available in this environment) — a human with access to one could confirm
the process now prints the corrected message instead of dying silently at
`startup.php`'s `require_once`, though the more useful confirmation is
simply that the two messages agree with each other and with
`startup.php`, which this fix guarantees structurally.

---

## MC-0006 — `DB::connected()` calls a method no adaptor implements

**Problem:** `system/library/db.php`'s public `DB::connected()` method
delegates to `$this->adaptor->connected()`:

```php
public function connected() {
    return $this->adaptor->connected();
}
```

but every real adaptor under `system/library/db/` —
`DB\MySQLi`, `DB\PDO`, `DB\PgSQL` — implements a method named
`isConnected()`, never `connected()`. Calling `DB::connected()` against any
real adaptor therefore fatals with `Error: Call to undefined method
DB\MySQLi::isConnected()`-shaped output (actually the inverse — "Call to
undefined method DB\MySQLi::connected()") instead of returning the
boolean the method's own name and signature promise. This is squarely the
Known Fix Research Queue's "database connection / reconnect issues"
category (`MINTCART_FUNCTIONAL_SCOPE.md` §18): any future MintCart code
that wants to check the connection is alive before running a long
operation or deciding whether to reconnect — the exact kind of call a
real reconnect-on-drop feature would need — would hit this fatal the
moment it tried.

**Affected OpenCart versions:** Confirmed present in OpenCart 3.0.5.1 (this
repository's documented upstream baseline, `docs/audit.md` §1). Not
independently checked against other 3.x point releases — same rationale as
MC-0001–MC-0005 (no second checkout available to diff against in this
environment); the mismatch is a plain naming bug between two files that
both ship as part of the same core library, not a version-specific
regression, so cross-checking every intermediate release wasn't necessary
to apply the fix safely.

**Source:** Discovered directly in this repository while working through
`MINTCART_FUNCTIONAL_SCOPE.md` §18's remaining Known Fix Research Queue
items — not an imported historical community patch. Reproduced by
`grep -rn "function connected\|function isConnected" upload/system/library/db.php upload/system/library/db/*.php`:
`db.php` calls `connected()`, all three adaptors define `isConnected()`
only. Confirmed nothing in this repository currently calls
`DB::connected()` (`grep -rn "->connected(" upload/` returns zero
matches) — which is exactly why this has shipped unnoticed: it's dead code
today, but any code written against `DB`'s own public method name would
hit it immediately.

**Reproduced on MintCart baseline:** Yes — see
`tests/mintcart/fixes/mc-0006-db-connected-adaptor-mismatch-test.php`,
which fatals with `Call to undefined method DB\FakeAdaptor::connected()`
against the pre-fix code (verified by hand, reverting the fix locally and
re-running the test) and passes after the fix.

**Upstream fixed:** Not checked against a newer OpenCart release (no
network/second-checkout access in this environment). MintCart tracks a
pinned 3.0.5.1 baseline (`CLAUDE.md` §1) rather than following upstream
point releases, so this doesn't block applying the fix here regardless of
upstream's status.

**MintCart solution:** Changed `DB::connected()`'s one-line body to call
`$this->adaptor->isConnected()` instead of `$this->adaptor->connected()`.
`DB`'s own public method name (`connected()`) is left unchanged — this is
the safest fix (a single call-site correction, no public API rename) in
case any theme/extension already calls `$this->db->connected()` expecting
today's method name to keep working, now with it actually returning a
real value instead of fataling. Deliberately **not** attempted in this
fix: real reconnect-on-drop behavior (catching a dropped-connection error
mid-request and re-establishing the link) — that's a materially larger
feature than fixing a naming mismatch, and no real caller of
`DB::connected()` exists yet to design a reconnect flow around
(`CLAUDE.md` §85); a future fix/feature can build on this method now that
it actually works.

**Tests:** `tests/mintcart/fixes/mc-0006-db-connected-adaptor-mismatch-test.php`
(new) — a pure PHP regression test with no OpenCart/MySQL dependency: a
`DB\FakeAdaptor` duck-typing the real adaptors (implements `isConnected()`
only) is injected into a real `DB` instance via reflection (bypassing the
constructor's real connection attempt, since no MySQL server is available
in this environment), then `DB::connected()` is called for real and its
return value checked against the adaptor's `true`/`false` state. `php -l`
clean on the modified `db.php` and the new test file. Full
`tests/mintcart/**/*-test.php` suite re-run end to end: all passing, no
regressions.

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
as MC-0001–MC-0006 (no second checkout available in this environment to
diff against, and the fix is a self-contained rewrite of one small
class's read/write mechanics with no OpenCart-version-specific
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

---

## MC-0008 — `Cart::__construct()` runs an unconditional cleanup `DELETE` on every single request

**Problem:** `\Cart\Cart::__construct()` (`upload/system/library/cart/cart.php`)
runs `DELETE FROM `.DB_PREFIX.`cart WHERE (api_id > '0' OR customer_id = '0')
AND date_added < DATE_SUB(NOW(), INTERVAL 1 HOUR)` unconditionally, every
single time the class is constructed. `Cart` is instantiated once per
request by both `catalog/controller/startup/startup.php` and
`admin/controller/startup/startup.php` — i.e. on **every** storefront and
admin page load, not only when a visitor actually has cart activity. This
is exactly the "зайві записи/updates session/cart" (excessive session/cart
writes) class of issue `MINTCART_FUNCTIONAL_SCOPE.md` §18's Known Fix
Research Queue names explicitly. On a typical store the overwhelming
majority of requests (anonymous browsing, search, category/product pages,
static pages) have no cart activity at all, so this is a guaranteed extra
write query — real cost on shared hosting, and a contributor to
`information_schema`/binlog write volume that's disproportionate to how
rarely it does useful work (there is nothing to delete on most requests).

**Affected OpenCart versions:** Confirmed present in OpenCart 3.0.5.1 (this
repository's documented upstream baseline, `docs/audit.md` §1). Not
independently checked against other 3.x point releases — same rationale as
MC-0001 through MC-0007 (no second checkout available to diff against, and
the fix is a request-frequency gate around an existing query, not a
version-specific behavior change).

**Source:** Discovered directly in this repository while working through
the Known Fix Research Queue candidates in `MINTCART_FUNCTIONAL_SCOPE.md`
§18 — not an imported historical community patch, though the underlying
"OpenCart cleans up the cart table on every page load" observation is a
long-standing, widely reported community complaint (the probability-gate
mitigation applied here mirrors PHP's own `session.gc_probability`/
`session.gc_divisor` pattern, a well-established fix shape for exactly this
class of "run cleanup only sometimes, not every request" problem).

**Reproduced on MintCart baseline:** Yes — confirmed by reading
`\Cart\Cart::__construct()` directly: the `DELETE` query is the first
statement in the constructor, unconditional, with no request-type or
probability guard anywhere in the method. Confirmed both `catalog/controller/startup/startup.php`
and `admin/controller/startup/startup.php` instantiate `Cart\Cart` directly
in their own `index()` (i.e. once per request, not lazily on first cart
access).

**Upstream fixed:** Not checked against a newer OpenCart release (no
network/second-checkout access in this environment). MintCart tracks a
pinned 3.0.5.1 baseline (`CLAUDE.md` §1) rather than following upstream
point releases, so this doesn't block applying the fix here regardless of
upstream's status.

**MintCart solution:** Added `MintCart\Commerce\CartGarbageCollector`
(`upload/system/library/mintcart/commerce/cartgarbagecollector.php`) — a
small, pure, OpenCart-independent class (`CLAUDE.md` §85's "one clear
service" preference over a bigger abstraction) whose only job is
`shouldRun(): bool`, gating on a `(probability, divisor)` pair defaulting
to `(5, 100)` — a 5% chance per request, the same order of magnitude
PHP's own default session GC uses. `Cart::__construct()`'s existing
`DELETE` query is now wrapped in
`if ((new \MintCart\Commerce\CartGarbageCollector())->shouldRun()) { ... }`
— a two-line diff around the existing query, not a rewrite. The cleanup
behavior itself (what gets deleted, and when a row becomes eligible) is
completely unchanged; only *how often the check runs* changes. A caller
that explicitly wants the old unconditional behavior back can construct
`new CartGarbageCollector(100, 100)` (`shouldRun()` always `true` — see the
class's own boundary-condition contract), so this is a strict behavior
subset, not a divergent reimplementation. No new config/settings UI was
added — the probability is a constructor default, not a merchant-facing
setting, since there is no real consumer yet asking for one to be tunable
(`CLAUDE.md` §85; a future run could surface it as a `Settings → Store`
value if that need appears).

**Tests:** `php -l` clean on both the new class and the modified
`cart.php`. `tests/mintcart/commerce/cart-garbage-collector-test.php` (7
scenarios) — since `shouldRun()` is inherently probabilistic in the
general case, the test asserts only the deterministic boundary conditions
(`probability = 0` never runs, `probability >= divisor` always runs,
negative probability behaves like zero rather than erroring) plus a loose
statistical sanity check on the default `(5, 100)` args (at least one
`false` in 500 runs — not asserting at least one `true`, since a 5% chance
never hitting once in 500 runs is vanishingly unlikely but not provably
impossible, and a flaky test is worse than a slightly weaker assertion).
No live end-to-end request test — this environment has no MySQL server
(same pre-existing gap noted throughout this file). A human should confirm
on a real install (e.g. with `error_log` temporarily added inside the
`if`) that the cleanup query now runs on roughly 1 in 20 requests instead
of every request, and that abandoned carts still expire correctly over
time.

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

---

## MC-0010 — `opayo.php` calls the deprecated `strftime()` (PHP 8.1+)

**Problem:** `catalog/model/extension/payment/opayo.php`'s
`setPaymentData()` (used to build a repeat/cron-triggered recurring-payment
request to Opayo/Sage Pay) calls `strftime("%Y%m%d%H%M%S")` to build part
of a unique `VendorTxCode`. `strftime()` (and `gmstrftime()`) has been
deprecated since PHP 8.1 and emits an `E_DEPRECATED` notice on every call;
a future PHP major version removes it outright. This is exactly the
"PHP 8.x compatibility problems" candidate named in
`MINTCART_FUNCTIONAL_SCOPE.md` §18's Known Fix Research Queue — a prior
run's grep for this same queue item (documented in
`docs/IMPLEMENTATION_STATUS.md`'s Phase 8 section, "Known Fix Research
Queue candidate" update) checked for `each()`/`create_function()`/curly-
brace string offsets/`FILTER_SANITIZE_STRING`/PHP4-style constructors/
mysqli strict-mode and found the baseline otherwise clear, but did not
check for `strftime()`/`gmstrftime()` specifically.

**Affected OpenCart versions:** Confirmed present in OpenCart 3.0.5.1 (this
repository's documented upstream baseline). Not independently checked
against other 3.x point releases (no second checkout available in this
environment) — the fix is a locale-independent, behavior-preserving
substitution, so this doesn't need to be re-verified per point release.

**Source:** Found via a targeted repository-wide grep for historically
known PHP 8.x-incompatible constructs
(`strftime(`/`gmstrftime(`/`money_format(`/`get_magic_quotes`/
`FILTER_SANITIZE_STRING`/`(unset)` casts/`each(`/`create_function(`), run
directly against this repo's `upload/` tree — not an imported historical
community patch. `strftime(` was the only real hit across the entire
`upload/` tree; every other pattern checked had zero occurrences (a few
`each(`/`(unset)` grep hits turned out to be substring false positives —
`foreach(`/`unset(` — confirmed by inspecting each match directly).

**Reproduced on MintCart baseline:** Yes — this environment has PHP 8.4
installed (the same version this repo's own CI, `.github/workflows/ci.yml`,
tests against). `php -r 'strftime("%Y%m%d%H%M%S");'` reproducibly emits
`PHP Deprecated: Function strftime() is deprecated since 8.1, use
IntlDateFormatter::format() instead` on this baseline, confirmed before
making any change.

**Upstream fixed:** Not checked against a newer OpenCart release (no
network/second-checkout access in this environment). MintCart tracks a
pinned 3.0.5.1 baseline (`CLAUDE.md` §1) rather than following upstream
point releases, so this doesn't block applying the fix here regardless of
upstream's status.

**MintCart solution:** Replaced `strftime("%Y%m%d%H%M%S")` with
`date('YmdHis')`. Both format specifiers are purely numeric (year, month,
day, hour, minute, second — no locale-sensitive month/day names), and this
value is never displayed to a user — it only ever feeds into a generated
`VendorTxCode` string sent to the payment gateway — so `date()` is a
byte-for-byte drop-in replacement with no behavior change, only a
different (non-deprecated) code path to the same output. No other file
references `strftime`/`gmstrftime` anywhere in this repository (grepped
both `catalog/` and `admin/` model/controller trees, including
`admin/model/extension/payment/opayo.php`, the admin-side counterpart —
clean).

**Tests:** `php -l` clean on the modified file. New
`tests/mintcart/fixes/mc-0010-opayo-strftime-test.php` — asserts the
deprecated call is gone from the source (regression guard), that
`date('YmdHis')` formats a fixed timestamp identically to how
`strftime('%Y%m%d%H%M%S')` did, and (by shelling out to a small probe
script against this same PHP binary) that `strftime()` still emits a
deprecation notice on this PHP version — proving the test would have
caught the original bug — while `date('YmdHis')` emits none. Full
`tests/mintcart/` suite re-run end to end (35 files, was 34) — all pass, no
regressions. Not verified: no live MySQL/OpenCart admin session in this
environment, and Opayo/Sage Pay recurring payments additionally require a
live merchant account with that gateway — a human with both should confirm
a real repeat/cron-triggered recurring charge still succeeds and that no
deprecation notice appears in the PHP error log for this code path.

---

## MC-0011 — `DB\MySQLi::query()` never recovers from a dropped connection

**Problem:** `system/library/db/mysqli.php`'s `query()` ran
`$this->connection->query($sql)` once and, on any mysqli error, threw
immediately — including errno `2006` ("MySQL server has gone away") and
`2013` ("Lost connection to MySQL server during query"). Both of those
specifically mean the underlying TCP connection was dropped (the
connection sat idle past MySQL's `wait_timeout`, the server restarted,
etc.), not that `$sql` itself is bad — the exact same query would succeed
a moment later on a fresh connection. Squarely the Known Fix Research
Queue's "database connection / reconnect issues" category
(`MINTCART_FUNCTIONAL_SCOPE.md` §18). This matters most for MintCart's own
longer-running operations that issue many queries in one request —
`MintCart\Core\Migration\MigrationManager` applying a batch of pending
migrations, or the Update Manager flow (`MINTCART_TECHNICAL_PLAN.md` §33)
— where a connection sitting idle between steps (waiting on a download,
file I/O, etc.) is exactly the scenario `wait_timeout` drops happen in.

**Affected OpenCart versions:** Confirmed present in OpenCart 3.0.5.1 (this
repository's documented upstream baseline, `docs/audit.md` §1). Not
independently checked against other 3.x point releases — same rationale as
every other Fix Registry entry so far (no second checkout available in
this environment to diff against, and the fix only adds a narrowly-scoped
retry path around behavior that is otherwise unchanged).

**Source:** Discovered directly in this repository while working through
`MINTCART_FUNCTIONAL_SCOPE.md` §18's remaining Known Fix Research Queue
items — not an imported historical community patch. Reproduced by reading
`query()`: it has exactly one `$this->connection->query($sql)` call and no
error-classification of any kind — every `errno` value, recoverable or
not, took the same `throw` branch.

**Reproduced on MintCart baseline:** Yes — by inspection (no live MySQL
server in this environment to force a real `wait_timeout` disconnect) and
via `tests/mintcart/fixes/mc-0011-mysqli-reconnect-test.php`, which drives
the exact pre-fix code path with a fake connection that returns errno
`2006`/`2013` and confirms (against the reverted code, checked by hand)
that it throws with no retry attempted.

**Upstream fixed:** Not checked against a newer OpenCart release (no
network/second-checkout access in this environment). MintCart tracks a
pinned 3.0.5.1 baseline (`CLAUDE.md` §1) rather than following upstream
point releases, so this doesn't block applying the fix here regardless of
upstream's status.

**MintCart solution:** Extracted the constructor's connection-setup body
into a new `connect()` method (verbatim, no behavior change — stores
`$hostname`/`$username`/`$password`/`$database`/`$port` as properties so
`connect()` can be called again later), then split `query()` into a
public entry point and a private `runQuery($sql, $allowReconnect)`. On any
error, if `$allowReconnect` is true and `$this->connection->errno` is one
of exactly `2006`/`2013` (`RECOVERABLE_ERRNOS`), it calls `connect()`
again and retries the *same* `$sql` once, with `$allowReconnect = false`
on the retry — so a connection that drops twice in a row (or a server
that's genuinely down) still throws, matching the pre-fix behavior,
instead of reconnecting forever. Any other errno (bad SQL, missing table,
permission, ...) still throws immediately on the first attempt, completely
unchanged. `connect()` is `protected` rather than `private` specifically
so a test can override it with a fake connection — no other production
code depends on that visibility change.

**Tests:** `tests/mintcart/fixes/mc-0011-mysqli-reconnect-test.php` (new,
5 scenarios) — a `DB\TestableMySQLi` subclass overrides the now-`protected`
`connect()` to pop a fake, duck-typed connection object off a queue
instead of opening a real `\MySQLi` socket (no live MySQL server in this
environment, same as every other DB-layer test in this repo):
1. no error → returns the result, `connect()` called exactly once
   (constructor only), no retry;
2. errno 2006 on the first attempt, success on the retry → returns the
   retry's result, `connect()` called exactly twice;
3. errno 2013 on the first attempt, success on the retry → same as #2 for
   the other recoverable errno;
4. errno 2006 on *both* the original attempt and the retry → throws
   (does not reconnect a third time / loop forever), `connect()` called
   exactly twice;
5. a non-recoverable errno (1146, "table doesn't exist") → throws
   immediately, `connect()` called exactly once, no reconnect attempted.

`php -l` clean on the modified `mysqli.php` and the new test file. Full
`tests/mintcart/**/*-test.php` suite re-run end to end (36 files, was 35)
— all passing, no regressions. **Not verified:** no live MySQL server in
this environment to force a real `wait_timeout`/restart disconnect and
confirm the reconnect against an actual socket — a human should confirm
on a real install that a long-running MintCart operation (e.g. applying
several pending migrations back to back with a deliberately-lowered
`wait_timeout`) survives a mid-run disconnect instead of fataling.

---

## MC-0012 — `ModelSettingSetting::editSetting()` deletes then reinserts with no transaction

**Problem:** `upload/admin/model/setting/setting.php`'s `editSetting($code,
$data, $store_id)` — the single method every settings screen in OpenCart
(every payment/shipping/total/module/report extension's own config save,
`Settings → Store`, and now MintCart's own Setup Wizard steps) uses to
persist a group of settings — first issues a `DELETE FROM setting WHERE
code = '<code>'`, then loops over `$data` issuing one `INSERT` per key,
with no transaction wrapping either step. `DB\MySQLi::query()`
(`upload/system/library/db/mysqli.php`) throws a plain `\Exception` on any
SQL error (a lock wait timeout, a deadlock with a concurrent request
writing to the same table, or a dropped connection — the exact scenario
MC-0011, `DB\MySQLi::query() never recovers from a dropped connection`,
describes). If that throw happens partway through the `INSERT` loop, the
`DELETE` at the top has already removed every existing row for that
`code` group, but only some (or none) of the new rows have been
re-inserted — the store silently loses every setting in that group except
whichever ones happened to insert before the failure, with no rollback and
no partial-save indication to the admin user. This is in the "installer
edge cases" / general reliability family of
`MINTCART_FUNCTIONAL_SCOPE.md` §18's Known Fix Research Queue, and directly
undermines the "merge-then-save, never a bare partial write" discipline
`docs/developer/onboarding/setup-wizard.md` already documents MintCart's
own Wizard steps having to work around (`saveStoreStep()`/
`saveLanguageCurrencyStep()`'s own `editSetting()` calls) — that discipline
protects against overwriting *other* keys in the same group on a
successful call, but nothing previously protected against this method
losing data on a *failed* one.

**Affected OpenCart versions:** Confirmed present in OpenCart 3.0.5.1 (this
repository's documented upstream baseline, `docs/audit.md` §1). Not
independently checked against other 3.x point releases — same rationale as
every earlier registry entry (no second checkout available in this
environment to diff against); the fix only adds transaction boundaries
around an existing, version-agnostic delete/insert sequence.

**Source:** Discovered directly in this repository while auditing
`upload/admin/model/setting/setting.php` (117 call sites across the
codebase, `grep -rc "editSetting("` ) as part of the "installer edge
cases" Known Fix Research Queue family — not an imported historical
community patch.

**Reproduced on MintCart baseline:** Yes — via a standalone unit test
against the real `ModelSettingSetting` class (loaded with OpenCart's real
`upload/system/engine/model.php` base class, no OpenCart bootstrap
needed), driven by an in-memory DB double that actually implements
transaction semantics (buffers writes between `START TRANSACTION`/`COMMIT`,
discards them on `ROLLBACK`) rather than merely recording which SQL
strings were sent. Re-running the identical test against the unmodified
pre-fix method confirms the exact defect: a simulated mid-loop `INSERT`
failure leaves `getSetting('config')` returning the *new*, incomplete data
with the previous `config_email` value gone entirely — not a rollback to
the pre-call state (see "Tests" below).

**Upstream fixed:** Not checked against a newer OpenCart release (no
network/second-checkout access in this environment). MintCart tracks a
pinned 3.0.5.1 baseline (`CLAUDE.md` §1) rather than following upstream
point releases, so this doesn't block applying the fix here regardless of
upstream's status.

**MintCart solution:** Wrapped the existing `DELETE` + `INSERT`-loop
sequence in `START TRANSACTION` / `COMMIT`, with a `catch (\Throwable $e)`
that issues `ROLLBACK` and rethrows the original exception unchanged — the
same raw-SQL transaction pattern (OpenCart 3's `\DB\MySQLi` has no
transaction-wrapper methods) `MintCart\Core\Migration\MigrationManager`
and `ControllerCatalogCategory::move()`/`ModelCatalogCategory::moveCategory()`
already established for the identical reason (`CLAUDE.md` §12 "critical
multi-step operations should use transactions"). No change to the method's
signature, return type, or the SQL it issues on the success path — a
caller sees identical behavior for every request that doesn't hit a mid-loop
failure; the only observable difference is that a failure that previously
left settings partially deleted now leaves them exactly as they were
before the call.

**Tests:** `php -l` clean on the modified file.
`tests/mintcart/fixes/mc-0012-setting-transaction-test.php` — 4 scenarios
(a normal call starts with `START TRANSACTION` and ends with `COMMIT` with
every key saved; a second, unrelated `code` group is unaffected; a
simulated mid-loop failure rethrows the original exception, issues
`ROLLBACK`, never commits, and — the critical assertion — leaves the
pre-call settings fully intact rather than half-deleted; a later call
still works normally after an earlier rollback). Verified not vacuous: the
identical test run against the pre-fix method fails on exactly that
critical assertion (`config_email` comes back `NULL` instead of the
pre-call value). Full `tests/mintcart/**/*-test.php` suite re-run end to
end (35 files, was 34) — all passing, no regressions.

---

## MC-0013 — `Mail\Smtp::send()` sends a malformed `EHLO`/`HELO` outside a web request

**Problem:** `system/library/mail/smtp.php` (OpenCart's own SMTP transport,
used whenever `config_mail_engine` is set to `smtp` — the recommended
setting for any store on shared hosting where the local `mail()` transport
is unreliable or blocked) built its `EHLO`/`HELO` commands directly from
`getenv('SERVER_NAME')`, at three call sites (initial `EHLO`, the
post-`STARTTLS` `EHLO`, and the no-auth `HELO` fallback). `SERVER_NAME` is
populated by the SAPI/webserver on a real HTTP request, but is empty
(`getenv()` returns `false`) in any CLI/cron context — and MintCart's own
roadmap explicitly adds more of those: Functional Scope §7's chunked/
resumable batch jobs, Technical Plan §33's Update Manager `check`/`backup`/
etc. steps, or simply a store's existing OpenCart cron already running
`index.php?route=...` from the command line to send a scheduled email.
`false` concatenated into the command string produces a bare
`EHLO \r\n`/`HELO \r\n` with no hostname argument at all — malformed per
RFC 5321 §4.1.1.1 ("the argument field contains ... the domain name of the
SMTP client") — and several real-world SMTP configurations (Postfix's
`smtpd_helo_restrictions = reject_invalid_helo_hostname`, and a number of
greylisting/anti-spam appliances) reject the session outright at that
point, silently breaking every outgoing email the store tries to send
through that code path (order confirmations, password resets, the Setup
Wizard's own future notification steps) with no visible error to the
merchant beyond "email never arrived."

**Affected OpenCart versions:** Confirmed present in OpenCart 3.0.5.1 (this
repository's documented upstream baseline, `docs/audit.md` §1). Not
independently checked against other 3.x point releases — same rationale as
MC-0001–MC-0003 (no second checkout available to diff against in this
environment); the fix only adds a fallback for an already-possible `false`
value, it does not change behavior when `SERVER_NAME` is present.

**Source:** Discovered directly in this repository while auditing
`system/library/mail/` for the Known Fix Research Queue's general
robustness/edge-case candidates (`MINTCART_FUNCTIONAL_SCOPE.md` §18) — not
an imported historical community patch. Reproduced by reading
`Mail\Smtp::send()`: all three `fputs($handle, 'EHLO ' . getenv('SERVER_NAME') . ...)`/
`'HELO ' . getenv('SERVER_NAME')` call sites use the raw `getenv()` return
value with no fallback anywhere in the file, and PHP's own documentation
for `getenv()` states it returns `false` when the named variable is not
set — which `$_SERVER`/environment tables genuinely omit outside a real
request in the common PHP CLI SAPI configuration.

**Upstream fixed:** Not checked against a newer OpenCart release (no
network/second-checkout access in this environment). MintCart tracks a
pinned 3.0.5.1 baseline (`CLAUDE.md` §1) rather than following upstream
point releases, so this doesn't block applying the fix here regardless of
upstream's status.

**MintCart solution:** Added
`MintCart\Mail\HeloHostname::resolve()`
(`upload/system/library/mintcart/mail/helohostname.php`) — a small, pure,
OpenCart-independent class (no `\Registry`/`\Config` dependency, same
reasoning as `MintCart\Catalog\CategoryMoveValidator` from Phase 17) that
returns `getenv('SERVER_NAME')` when it is a non-empty string, otherwise
falls back to `php_uname('n')` (the machine's own hostname, always
available in a CLI/cron context), and finally to the literal string
`'localhost'` if even that is somehow empty — so the method's contract is
simply "never returns an empty string." `smtp.php`'s three call sites now
compute `$helo_hostname = \MintCart\Mail\HeloHostname::resolve();` once
near the top of the SMTP conversation and reuse it, relying on OpenCart's
existing autoloader for `MintCart\*` classes (the same autoloading
`CategoryMoveValidator` already depends on — no new `require`/`include`
needed). No behavior changes when `SERVER_NAME` is already present and
non-empty (the overwhelmingly common case on normal web hosting) — this is
purely a CLI/cron/misconfigured-SAPI fallback.

**Tests:** `php -l` clean on both the modified `smtp.php` and the new
`helohostname.php`. New
`tests/mintcart/mail/helo-hostname-test.php` (3 scenarios — `SERVER_NAME`
present, absent via `putenv('SERVER_NAME')`, and present-but-empty via
`putenv('SERVER_NAME=')` — all resolving to a non-empty string, restoring
the test runner's own original environment state afterward so the test
can't leak into anything run after it), passing. Full `tests/mintcart/`
suite re-run end to end (35 files now, was 34) — all pass, no regressions
(this is a pure addition plus a narrow three-line substitution in one
existing file, nothing else touched). No live SMTP-server test was
possible (this environment has no outbound SMTP access to verify against a
real mail server) — a human should confirm on a real install, with
`config_mail_engine` set to `smtp`, that outgoing mail sent from a CLI/cron
context (e.g. `php index.php route=... --cli` or an OS-level cron entry,
if/when one exists) now succeeds where it previously failed at the `EHLO`
step; `docs/MANUAL_VERIFICATION.md` has a new entry for this.

---

## MC-0014 — Klarna payment extensions call the deprecated `utf8_encode()`/`utf8_decode()`

**Problem:** `catalog/controller/extension/payment/klarna_account.php` (2
call sites) and `catalog/controller/extension/payment/klarna_invoice.php`
(3 call sites) convert strings between the Klarna XML-RPC API's
ISO-8859-1 wire format and PHP's internal UTF-8 strings using
`utf8_encode()`/`utf8_decode()`. Both functions were deprecated in PHP 8.2
(`E_DEPRECATED`, not removed) — this is exactly the "PHP 8.x compatibility
problems" family flagged as a Known Fix Research Queue candidate
(`MINTCART_FUNCTIONAL_SCOPE.md` §18) and already confirmed as a live risk
for this codebase in `docs/audit.md` §"CLI PHP available in this
environment: PHP 8.4.19". Left unfixed, every Klarna checkout on PHP
8.2+ spams the PHP error log with deprecation notices on both the
success path (building the invoice digest) and the failure path (decoding
Klarna's XML-RPC fault string) — not fatal, but exactly the kind of
"upstream doesn't fully work on a modern PHP" gap MintCart's Fixes Policy
exists to close, in the same family as MC-0010 (`opayo.php`'s
`strftime()`).

**Affected OpenCart versions:** Confirmed present in OpenCart 3.0.5.1 (this
repository's documented upstream baseline, `docs/audit.md` §1). Not
independently checked against other 3.x point releases — same rationale as
every other entry in this registry (no second checkout available to diff
against, and the fix is a drop-in, behavior-preserving replacement, not a
version-specific workaround).

**Source:** Discovered directly in this repository while auditing
`MINTCART_FUNCTIONAL_SCOPE.md` §18's "PHP 8.x compatibility problems"
candidate — not an imported historical community patch. Found by grepping
`upload/` for `utf8_encode(`/`utf8_decode(`: only these two files matched
(`opayo.php`'s equivalent `strftime()` issue was already fixed separately
as MC-0010).

**Reproduced on MintCart baseline:** Yes — `grep -rn "utf8_encode\|utf8_decode" upload/catalog/controller/extension/payment/klarna_account.php upload/catalog/controller/extension/payment/klarna_invoice.php`
returned 5 matches prior to this fix (2 in `klarna_account.php`, 3 in
`klarna_invoice.php`), confirmed against the unmodified stock files.

**Upstream fixed:** Not checked against a newer OpenCart release (no
network/second-checkout access in this environment). MintCart tracks a
pinned 3.0.5.1 baseline (`CLAUDE.md` §1) rather than following upstream
point releases, so this doesn't block applying the fix here regardless of
upstream's status.

**MintCart solution:** Replaced every `utf8_encode($x)` call with
`mb_convert_encoding($x, 'UTF-8', 'ISO-8859-1')` and the one
`utf8_decode($x)` call (in `klarna_invoice.php`'s invoice-digest checksum
builder) with `mb_convert_encoding($x, 'ISO-8859-1', 'UTF-8')` — the
documented, byte-for-byte equivalent replacement for both directions
(`mbstring` is already a hard OpenCart 3 install requirement, so this adds
no new dependency). The invoice-digest call site is the one place where
"behavior-preserving" matters most: that digest is a checksum sent to
Klarna's API, so changing its output would break checkout, not just
silence a log — `mb_convert_encoding()` was chosen specifically because it
is the exact inverse operation `utf8_decode()` performed, not a "cleaner"
UTF-8 handling rewrite. No template change, no language string change, no
change to either controller's control flow.

**Tests:** `php -l` clean on both modified files.
`tests/mintcart/fixes/mc-0014-klarna-charset-test.php` (17 assertions)
proves `mb_convert_encoding()` is byte-for-byte equivalent to
`utf8_encode()`/`utf8_decode()` for representative ASCII and
Latin-1-range samples (accented characters, ß, £) using PHP's own
(deprecated but still callable, even on the PHP 8.4.19 CLI available in
this environment) `utf8_encode()`/`utf8_decode()` as the oracle, and
confirms both touched files no longer contain any `utf8_encode(`/
`utf8_decode(` call. A live payment-gateway request test would need a real
Klarna merchant sandbox account and a live OpenCart checkout, neither
available in this environment — a human should confirm a real Klarna
Invoice/Account checkout still completes successfully (and that PHP's
error log no longer logs a deprecation notice for these two files) on a
PHP 8.2+ install.
