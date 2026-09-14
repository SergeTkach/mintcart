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

Consumer: `upload/admin/controller/mintcart/compatibility.php`
(`ControllerMintcartCompatibility`, route `mintcart/compatibility/check`,
POST, JSON)

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

### Why no admin menu entry yet

`admin/controller/common/column_left.php` is, at the time of this
increment, already being edited by three other open pull requests (each
adding its own `Business`/`Design` menu entry) — editing it a fourth time
here would be the exact kind of shared-hot-file collision this routine's
own dedup process (`docs/IMPLEMENTATION_STATUS.md`'s "Next step" section)
is meant to avoid. This mirrors the precedent `mintcart/wizard` and
`mintcart/readiness` already set: both shipped, and were reachable by
navigating to their route directly, before either got a menu entry.
Adding a `Advanced → Compatibility Checker` menu entry (or a real
upload-based screen at that route) is a self-contained follow-up once
`column_left.php` isn't contended.

## Not done yet, by design

- **Admin UI list/upload screen and menu entry** — see above; the
  endpoint itself is fully functional today via a direct POST (e.g. from
  a REST client or a future JS-driven form), it just has no rendered page
  of its own yet.
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
- **Not verified** — same pre-existing gap as every other controller
  increment in this repo: no live MySQL/OpenCart admin session in this
  environment. A human should grant the auto-discovered
  `mintcart/compatibility` permission to a user group, POST a real,
  known OCMOD `install.xml` (ideally one with genuine Bootstrap 3 markup
  and/or a file that collides with MintCart's own modified-file list) to
  `mintcart/compatibility/check`, and confirm the JSON report matches
  the file's actual contents.
