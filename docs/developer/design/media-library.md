# Media Library backend (`design/media`)

`MINTCART_FUNCTIONAL_SCOPE.md` §25 "Media UX", priority **P1**.

The real data source `mc-media-picker`'s own documented usage example
(`docs/developer/components/media-picker.md`) was always written
against, now actually implemented:

- PHP: `upload/admin/model/design/media.php` (`ModelDesignMedia`) +
  `upload/admin/controller/design/media.php` (`ControllerDesignMedia`)
- Language: `upload/admin/language/en-gb/design/media.php`
- Path-safety primitive: `upload/system/library/mintcart/design/mediapath.php`
  (`MintCart\Design\MediaPath`)
- Tests: `tests/mintcart/design/media-path-test.php` (7 scenarios, pure),
  `tests/mintcart/design/media-model-test.php` (9 scenarios, real
  filesystem I/O against a temp fixture tree)

## What this is

Browses, uploads to, and deletes from OpenCart's own existing
`image/catalog/` directory — the exact same directory
`upload/admin/controller/common/filemanager.php` already manages for the
legacy image-picker dialog. This is **not** a new Mint-owned media store
(`CLAUDE.md` §6/§39 — don't duplicate OpenCart storage); it is a second,
JSON-shaped API in front of the same files, built because
`common/filemanager.php` only ever renders a server-side HTML page for
its own dialog, not JSON a JS component's data-source callbacks can
consume directly.

Every path-traversal/filename-length/extension/mime-type rule here
matches `common/filemanager.php`'s own rules exactly — checked against
that real controller before writing this (`CLAUDE.md` §84/§10) and
factored into the shared, unit-tested `MintCart\Design\MediaPath` class
so neither controller has to re-derive them. `common/filemanager.php`
itself is completely untouched by this work.

## Routes

All four require the same admin session (`user_token`) and
`hasPermission()` check every other admin controller uses; OpenCart's
own controller-directory permission auto-discovery covers `design/media`
with no separate registration step (the same convention
`design/homepage` already relies on).

| Route | Method | Permission | Request | Response |
|---|---|---|---|---|
| `design/media/list` | GET | `access` | `folder`, `query`, `page`, `pageSize` | `{ items: [...], total: int }` |
| `design/media/folders` | GET | `access` | — | `{ folders: [{ id, label }, ...] }` |
| `design/media/upload` | POST | `modify` | `file[]` (multipart), `folder` (query) | `{ success, paths: string[] }` or `{ error }` |
| `design/media/delete` | POST | `modify` | `paths[]` (relative paths from `list`'s `id`) | `{ success }` or `{ error }` |

An `items` entry is either:

```text
{ id, type: 'directory', name }
{ id, type: 'image', name, thumbnail, url }
```

`id` is always the path relative to `DIR_IMAGE` (e.g.
`catalog/shirts/red.jpg`) — the same value a later `delete` call's
`paths[]` should contain, and the same value a later `list`/`folders`
call's `folder` parameter expects for browsing into that directory.

## Why the route is `design/media/list`, not `design/media.list`

`media-picker.md`'s original usage example (written before this backend
existed) used the dot form `design/media.list`. OpenCart 3's own routing
(`Action::__construct()`, `upload/system/engine/action.php`) strips every
character that is not a letter, digit, underscore, or slash from the
route **before** parsing it — a `.` is silently deleted, not treated as a
separator. `design/media.list` would therefore resolve to
`design/medialist` (a nonexistent controller), never to
`ControllerDesignMedia::list()`. Caught by reading `Action`'s real
implementation before wiring this route (`CLAUDE.md` §84), not assumed —
fixed in this document and `media-picker.md` alongside adding the
controller.

## `MintCart\Design\MediaPath`

Pure, OpenCart-independent helpers, unit-tested in isolation:

- `sanitizeFilterName()` / `sanitizeName()` — the same wildcard/
  path-separator-stripping and HTML-entity-decode-then-basename rules
  `common/filemanager.php` applies to a search term / uploaded filename.
- `isValidFilenameLength()` / `isValidFolderNameLength()` — the same 3-255
  / 3-128 character bounds.
- `hasAllowedExtension()` / `hasAllowedMimeType()` — the same
  jpg/jpeg/gif/png/webp whitelist.
- `isWithinCatalog()` — the containment check every
  `common/filemanager.php` entry point repeats (a `realpath()`'d target
  must sit inside the `realpath()`'d catalog root), but **stricter**:
  `filemanager.php`'s own version is a bare string-prefix comparison,
  which would treat `image/catalog-evil/` as "within" `image/catalog/`
  (both share that literal character prefix). This method requires an
  exact match or a `/` boundary right after the shared prefix. This is
  new code, not a byte-for-byte port of `filemanager.php`, so there is no
  "faithful extraction" obligation to reproduce that flaw.

## What's deliberately not done yet

- **No admin screen mounts `mc-media-picker` anywhere.** The other half
  of "real consumer" — replacing a Product/Category/Blocks image field's
  existing legacy `filemanager` popup with `mc-media-picker` inside an
  `mc-drawer`/`mc-modal` — needs the same unresolved admin asset-enqueue
  question `docs/developer/blocks/homepage-admin.md` flags (no MintCart
  JS/CSS bundle is enqueued on any admin screen yet). An open PR proposes
  resolving that question for `McEntityPicker`; the same fix would unblock
  this picker too. Until then, these four routes exist and are tested,
  but nothing in the admin UI calls them.
- **No folder-creation endpoint.** `mc-media-picker`'s own documented
  contract (`fetchItems`/`fetchFolders`/`uploadFiles`/`deleteItems`) has
  no "create folder" affordance, so none was added here either
  (`CLAUDE.md` §85 — no API ahead of a real consumer). Browsing into an
  existing subdirectory (via `folder`) is fully supported.
- **`addFile()`'s actual file move is untested in this environment.**
  `move_uploaded_file()` only succeeds against a file PHP's own SAPI
  registered as uploaded during a real HTTP request — it cannot be
  exercised from a CLI test. The validation path that runs before it
  (directory/filename/extension checks) is fully covered; a human should
  confirm a real multipart upload on a live install.
- **No live-install verification for `list`/`folders`/`delete` against a
  real admin session** — same pre-existing gap noted throughout
  `docs/IMPLEMENTATION_STATUS.md` (no MySQL/OpenCart bootstrap in this
  environment). `tests/mintcart/design/media-model-test.php` exercises
  the model's real filesystem logic (a temp fixture tree, including an
  actual delete), which is the part that matters most for correctness,
  but not the controller/permission/routing layer end to end.

## Definition of Done checklist (`MINTCART_IMPLEMENTATION_GUIDE.md` §79)

- [x] Documented contract (this file)
- [x] No OCMOD, no modification to `common/filemanager.php` or any other
      existing OpenCart file (fully additive)
- [x] Path-safety rules verified against the real
      `common/filemanager.php` controller, not assumed from memory
      (`CLAUDE.md` §84)
- [x] Smoke tests: `MediaPath` (pure, 7 scenarios) and `ModelDesignMedia`
      (real filesystem I/O against a temp fixture tree, 9 scenarios)
- [ ] Theme override checked — not applicable; this is an admin
      controller/model, not a themeable component
- [ ] Live data source verified end to end on a real install — deferred,
      see "What's deliberately not done yet"
