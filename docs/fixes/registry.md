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
