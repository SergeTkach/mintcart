# Compatibility Checker

`MintCart\Compatibility\InstallManifestParser` +
`MintCart\Compatibility\CompatibilityChecker`
(`MINTCART_FUNCTIONAL_SCOPE.md` §29, `MINTCART_IMPLEMENTATION_GUIDE.md`
§70, `MINTCART_TECHNICAL_PLAN.md` §39/§11 — "Compatibility Checker") —
analyzes an OCMOD `install.xml` and reports which files/controllers/
templates it touches, which of those collide with a file MintCart's own
core already modifies, and which look like they assume Bootstrap 3
markup. **Heuristic warnings only — this never blocks an install.**

PHP:
`upload/system/library/mintcart/compatibility/installmanifestparser.php`
(class `MintCart\Compatibility\InstallManifestParser`)
`upload/system/library/mintcart/compatibility/compatibilitychecker.php`
(class `MintCart\Compatibility\CompatibilityChecker`)

Tests:
`tests/mintcart/compatibility/install-manifest-parser-test.php` (8 scenarios)
`tests/mintcart/compatibility/compatibility-checker-test.php` (7 scenarios)

Consumers: `upload/admin/controller/mintcart/compatibility.php`
(`ControllerMintcartCompatibility`) — `check()` (route
`mintcart/compatibility/check`, POST, JSON, unchanged since this class's
first increment) and `index()` (route `mintcart/compatibility`, GET/POST,
rendered form + results, added 2026-09-30 — see "Admin screen and menu
entry" below). Both share one `analyze()` helper so the two consumers can
never drift apart on what "analyze this install.xml" does.

## Design

Both classes have **no OpenCart dependency** — no `\Registry`, `\DB`, no
filesystem access — same "resolved values in, normalized contract out"
shape as `ProductViewProvider`/`CartService`/`StoreReadiness`. They are
split in two because they answer two different questions:

- `InstallManifestParser::parse(string $xml): array` reads raw
  `install.xml` text and turns it into a normalized array (name, code,
  version, author, link, and a `files[]` list of
  `{path, operation_count, content}` — `content` is every `<search>`/
  `<add>` block's text concatenated, the raw material the checker scans
  for Bootstrap 3 markers).
- `CompatibilityChecker::check(array $manifest, array $extraConflictPaths = []): array`
  takes that normalized manifest (or any hand-built array of the same
  shape — see the checker's own unit test, which never touches XML at
  all) and classifies each file, flags Bootstrap 3 assumptions, and flags
  potential conflicts with MintCart's own core changes.

Splitting them means a caller who already has a parsed manifest from
somewhere else (a future extension-marketplace integration, say) can call
the checker directly without re-parsing XML, and the parser is reusable
for anything else that ever needs to read an `install.xml` (e.g. a future
package-manifest reader for Update Manager, Technical Plan §33) without
inheriting the checker's own heuristics.

### `CompatibilityChecker`'s report shape

```php
[
    'files' => [
        ['path' => 'catalog/controller/product/product.php', 'is_controller' => true, 'is_template' => false, 'is_theme' => false],
        // ...
    ],
    'controller_files' => ['catalog/controller/product/product.php'],
    'template_files' => ['catalog/view/theme/default/template/product/product.tpl'],
    'theme_files' => ['catalog/view/theme/default/template/product/product.tpl'],
    'bootstrap3_assumptions' => [
        ['path' => 'catalog/view/theme/default/template/common/footer.tpl', 'markers' => ['panel', 'pull-right']],
    ],
    'potential_conflicts' => ['catalog/controller/product/category.php'],
    'summary' => [
        'file_count' => 3,
        'controller_count' => 1,
        'template_count' => 1,
        'theme_count' => 1,
        'bootstrap3_count' => 1,
        'conflict_count' => 1,
    ],
]
```

### Bootstrap 3 markers reused, not reinvented

The heuristic marker list (`pull-right`, `pull-left`, `hidden-xs`,
`visible-xs`, `btn-default`, `panel`, `panel-heading`, `panel-body`,
`input-group-btn`) is the **exact same list**
`assets/src/css/compatibility/bootstrap3-shim.css` already treats as
needing a compatibility shim (`MINTCART_IMPLEMENTATION_GUIDE.md` §10). If
an OCMOD's markup mentions one of these class names, it's the same signal
this codebase already uses elsewhere to mean "assumes Bootstrap 3" — not
a separately invented heuristic that could drift out of sync with the
shim itself.

### Potential conflicts reused from `docs/upstream-diff.md`, not reinvented

`CompatibilityChecker::KNOWN_MINTCART_MODIFIED_FILES` is the list of
OpenCart core files `docs/upstream-diff.md` documents MintCart as having
directly modified, as of this writing: the four storefront product-listing
controllers (`category.php`/`search.php`/`manufacturer.php`/
`special.php`), `common/header.php`/`common/footer.php`/`common/home.php`,
`event/theme.php`, and three admin-side category-drag-and-drop files
(`admin/controller/common/column_left.php`,
`admin/controller/catalog/category.php`,
`admin/model/catalog/category.php`). An OCMOD touching any of these has a *concrete*, real
chance of a patch/search-and-replace conflict with a MintCart core
change — a sharper signal than a generic "this modifies OpenCart core"
warning. The list is a `private const`, not read from
`docs/upstream-diff.md` at runtime (there's no live consumer that needs
that yet, and hand-parsing a Markdown doc as a data source would be its
own fragile dependency) — a caller can pass additional paths via
`$extraConflictPaths` without a code change here, and this list should be
kept in sync by hand whenever `docs/upstream-diff.md` gains a new entry.

### Never blocks

Per Functional Scope §29 ("Не блокувати install лише за heuristic
warning") and Implementation Guide §70 ("Не блокувати installation лише
через heuristic warning"), `CompatibilityChecker::check()` never throws
for a "bad" result — a manifest with every file flagged as a conflict
still returns a complete, well-shaped report. The only exception is
`InstallManifestParser::parse()` throwing `\InvalidArgumentException` for
genuinely malformed input (not well-formed XML, or the wrong root
element) — that's a parse failure, not a compatibility judgment, and the
admin controller catches it and returns it as `{"error": "..."}` rather
than a fatal.

## Admin endpoint

`route=mintcart/compatibility/check`, POST, one field: `xml` (the raw
`install.xml` text, pasted rather than uploaded as a file — see "Why
pasted text, not a file upload" below). Response:

```json
{
    "manifest": {"name": "...", "code": "...", "version": "...", "author": "...", "link": "..."},
    "files": [...],
    "controller_files": [...],
    "template_files": [...],
    "theme_files": [...],
    "bootstrap3_assumptions": [...],
    "potential_conflicts": [...],
    "summary": {...}
}
```

or, on malformed input / an empty `xml` field / missing permission:
`{"error": "..."}`.

### Why pasted text, not a file upload

An upload-based flow (`<input type="file">`, store the file, parse it,
maybe keep history of past checks) is the more complete admin UX this
should eventually have. Building that also means file-upload validation,
temporary storage, and cleanup — real surface area beyond what this
increment needs to prove the parser/checker contract end to end. Pasting
raw XML into a POST field is the smallest real consumer that exercises
the whole pipeline (`CLAUDE.md` §85/§86) without inventing that
machinery ahead of a second real need for it.

### Admin screen and menu entry (2026-09-30)

`ControllerMintcartCompatibility::index()` (route `mintcart/compatibility`)
is the rendered form/results screen this section used to describe as a
gap: a `<textarea>` to paste `install.xml`, a "Check" button (plain form
POST, no JS/AJAX — see "Why a plain form POST, not AJAX" below), and,
after a successful analysis, the same `files`/`bootstrap3_assumptions`/
`potential_conflicts`/`summary` report `check()` already returned as JSON,
rendered as a table + warning panels instead. Built on the legacy admin
theme (Bootstrap 3 `.panel`/`.table`/`form-horizontal` markup), same
reasoning every other MintCart admin screen in this repo gives (see
`docs/developer/blocks/homepage-admin.md`'s "Why this screen uses the
legacy admin theme").

Menu entry: `System → Compatibility Checker`, right after the existing
`mintcart/settings/diagnostics` entry in `column_left.php`'s `System`
group (the same group Diagnostics already established — no dedicated
"Advanced" top-level menu group exists in `column_left.php` yet, so this
follows that precedent rather than inventing one). At the time this was
added, `list_pull_requests` showed 24 open PRs (#138–#161); none touched
`admin/controller/common/column_left.php`'s `System` array — see
`docs/upstream-diff.md`'s `column_left.php` entry for the exact per-PR
check.

### Why a plain form POST, not AJAX

MintCart's own compiled asset bundle (`assets/dist/{css,js}`) has never
been enqueued into the shared admin `header`/`column_left`/`footer` chrome
anywhere in this repo (`CLAUDE.md` §10, the same blocker
`docs/developer/components/entity-picker.md`'s "Live wiring deliberately
not attempted this run" section documents for `McEntityPicker`). A plain
`<form method="post">` that re-renders the same page server-side needs
none of that — same "GET shows the form / POST re-renders it with
results" shape `ControllerMintcartBusinessContacts` already established
for `Business → Contacts`.

### Why pasted text, not a file upload, still stands

Building a real upload-based flow (`<input type="file">`, store the file,
keep a history of past checks) is still the more complete admin UX this
should eventually have — that's still deferred, only the "no page to
paste into at all" gap is closed by this increment.

## Not done yet, by design

- **Real OCMOD install-pipeline integration** — this never hooks into
  OpenCart's actual extension-install flow (there isn't one to hook into
  cleanly without a core patch, and Functional Scope §29 explicitly says
  this is advisory, not a gate). A future increment could surface this
  report as a warning banner during a real OCMOD install, once a concrete
  integration point is identified.
- **`docs/fixes/registry.md`-style historical corpus** — this checker
  only looks at *this* `install.xml`'s own content; it does not maintain
  a database of previously-seen extensions or known-bad patterns beyond
  the Bootstrap 3 marker list and MintCart's own modified-file list.

## Verified / not verified

- `php -l` clean on every new file, and on the full `upload/`/`tests/`
  tree (re-swept before finishing this increment).
- `tests/mintcart/compatibility/install-manifest-parser-test.php` (8
  scenarios: metadata extraction, missing optional fields defaulting to
  empty strings, multiple `<file>` elements in document order,
  leading-slash path normalization, a `<modification>` with zero
  `<file>` elements, malformed XML, the wrong root element, a `<file>`
  with no `path` attribute being skipped rather than fatal).
- `tests/mintcart/compatibility/compatibility-checker-test.php` (7
  scenarios: a clean file with a full zeroed-out report, `.tpl`/`.twig`
  template classification, Bootstrap 3 marker detection naming the
  specific markers matched, known-conflict detection against
  `docs/upstream-diff.md`'s file list, caller-supplied extra conflict
  paths, an empty manifest never erroring, and defensive handling of a
  hand-built manifest missing the optional `content`/`operation_count`
  keys).
- Full `tests/mintcart/**/*-test.php` suite re-run end to end (35 files,
  was 33) — all passing, no regressions.

**Update (2026-09-30, routine run — admin screen + menu entry):** `php -l`
clean on `compatibility.php`, `column_left.php` (both admin controller and
`en-gb/common/column_left.php`), and the new
`en-gb/mintcart/compatibility.php` keys, re-swept across the full
`upload/`/`tests/` tree. The new `compatibility.twig`'s `{% if %}`/
`{% for %}` tag counts checked balanced (12/12). No new pure PHP class —
`analyze()` is an extract-method refactor of `check()`'s existing,
already-tested logic (no behavior change to `check()`'s own JSON
contract), so no new unit test file. Full `tests/mintcart/**/*-test.php`
suite re-run end to end (84 files) — all passing, no regressions. **Not
verified:** no live MySQL/admin session in this environment — a human
should confirm `System → Compatibility Checker` renders, that pasting a
real `install.xml` shows the expected table/warnings, and that a user
without `mintcart/compatibility` `access` permission sees
`error_permission` instead of the form (see
`docs/MANUAL_VERIFICATION.md`'s new entry).
Same underlying manual step either way: a human should grant the
auto-discovered `mintcart/compatibility` permission to a user group, then
exercise a real, known OCMOD `install.xml` (ideally one with genuine
Bootstrap 3 markup and/or a file that collides with MintCart's own
modified-file list) — either POSTed to `mintcart/compatibility/check`
directly, or pasted into `System → Compatibility Checker` — and confirm
the report matches the file's actual contents.
