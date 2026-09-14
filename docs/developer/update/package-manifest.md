# Update Manager — Package Manifest

`MintCart\Update\PackageManifest`
(`upload/system/library/mintcart/update/packagemanifest.php`)

## What this is

The first increment of the Update Manager flow described in
`MINTCART_TECHNICAL_PLAN.md` §33 and `MINTCART_IMPLEMENTATION_GUIDE.md` §69:

```text
check
→ compatibility
→ backup
→ download
→ verify
→ maintenance
→ files
→ migrations
→ cache clear
→ health check
→ done
```

`PackageManifest` is a pure, OpenCart-independent value object for the
manifest a MintCart update/theme/extension package ships alongside its
files — parsing, validation and compatibility-constraint checking only. It
has no downloader, no installer, no admin controller, and does not touch
`MintCart\Core\Migration\MigrationManager` or a future
`MintCart\Core\HealthCheck` yet. This mirrors how `MigrationManager`
(Phase 1) and `MintCart\Onboarding\WizardProgress` (Phase 13) were both
built as the underlying primitive first, with the flow that actually
*uses* them wired in a later increment once a real caller exists
(`CLAUDE.md` §85 — no API/UI ahead of a real consumer). Building the
manifest shape first, rather than guessing at the full download/install
pipeline in one increment, keeps this piece independently reviewable and
testable without a real package or a live install to try it against.

## Not the same thing as the Compatibility Checker

`MintCart\Compatibility\CompatibilityChecker` (Technical Plan §39,
Functional Scope §29) parses a **third-party OCMOD extension's**
`install.xml` to warn about modified files/controllers/templates before
installation. `PackageManifest` parses **a MintCart package's own**
manifest (name, version, requirements, files, migrations, checksum) to
decide whether *that* package is safe to apply to the current install.
Different documents, different questions, no shared code — keep them
separate rather than merging "is this OCMOD risky" and "is this update
compatible" into one class.

## Manifest shape

```json
{
  "name": "mint_default",
  "version": "1.2.0",
  "requirements": {
    "mint_version": "^0.1.0",
    "php_version": ">=8.1.0"
  },
  "files": [
    "catalog/view/theme/mint_default/theme.json"
  ],
  "removes": [
    "catalog/view/theme/mint_default/template/mint/components/old-card.twig"
  ],
  "migrations": [
    "202608210001_create_mc_migration.php"
  ],
  "checksum": "sha256:deadbeef"
}
```

- `name` and `version` are required, non-empty strings. Everything else is
  optional and defaults to an empty array / `null`.
- `requirements` is `component => version constraint string` — the
  component names are caller-defined (`mint_version`, `php_version`,
  `opencart_version`, ...), not a fixed enum, since only the caller that
  eventually resolves "what's the currently installed X" knows which
  components matter for a given package.
- `files` is a plain list of relative paths this package installs/
  overwrites — used by the `backup` step (see `BackupPlanner` below) to
  back up whatever a file list says will be overwritten, the same open
  question `MINTCART_FUNCTIONAL_SCOPE.md` §21 flags for theme/extension
  rollback.
- `removes` is a plain list of relative paths this package **deletes**,
  distinct from `files`' overwrites. Per
  `docs/adr/005-theme-extension-update-rollback.md` decision #3, a path
  simply missing from a new manifest's `files` list is not, by itself,
  safe to infer as "delete this" (it may have moved elsewhere in the
  package) — a package author must say so explicitly. `BackupPlanner`
  snapshots a `removes` path exactly like an overwritten `files` path
  before anything is deleted.
- `migrations` is a list of migration filenames following
  `MigrationManager`'s own naming convention
  (`upload/system/mintcart/migrations/README.md`) — running them is a
  later increment's job, once the flow actually calls `MigrationManager`
  with this list.
- `checksum` is opaque here (a `sha256:...`-style string) — verifying it
  against a downloaded file is the `verify` step, not this class's job.

## Compatibility constraints

`PackageManifest::satisfiesConstraint(string $version, string $constraint): bool`
is a static, standalone version-constraint checker (no manifest instance
needed) supporting:

- An exact version with no prefix (`"1.2.3"`).
- A comparison operator prefix: `>=`, `<=`, `>`, `<`, `=`.
- A caret range: `^1.2.3`. Follows the same semantics npm/Composer use —
  `^1.2.3` allows any `1.x.y >= 1.2.3`; `^0.2.3` allows any
  `0.2.y >= 0.2.3` (a `0.x` release isn't considered stable, so only the
  rightmost nonzero component is free to float); `^0.0.3` allows only
  `0.0.3` itself.

This is **not** a full semver range grammar — no `~`, no `||`, no combined
`">=1.0 <2.0"` ranges. It covers what a single "minimum compatible
version" declaration in a manifest needs. Extend it only when a real
manifest needs a form this doesn't cover (`CLAUDE.md` §85/§86 — no
speculative grammar ahead of a real need).

Two instance-level convenience methods sit on top of the static checker:

- `isCompatibleWith(string $component, string $installedVersion): bool` —
  checks one component; a component this manifest doesn't declare a
  requirement for is always compatible (nothing to check).
- `isCompatible(array $installedVersions): bool` — checks every declared
  requirement at once. A requirement whose component is **missing** from
  `$installedVersions` fails closed (`false`), rather than being silently
  skipped — an update flow should never treat "we don't know" as
  "compatible."

## The `check` step — `UpdateChecker`

`MintCart\Update\UpdateChecker`
(`upload/system/library/mintcart/update/updatechecker.php`) is the second
increment: the flow's own `check` step. Given an already-parsed
`PackageManifest` plus the version currently installed for whatever the
manifest is an update *for*, plus the currently installed versions of
everything the manifest declares a `requirements` constraint on, it decides
whether the manifest is both newer (`update_available`) and installable
(`compatible`):

```php
$checker = new \MintCart\Update\UpdateChecker();

$result = $checker->check(
    $manifest,                                   // PackageManifest
    \MintCart\Core\Version::VERSION,              // installed version of the thing being updated
    ['mint_version' => \MintCart\Core\Version::VERSION, 'php_version' => PHP_VERSION]
);

// $result === [
//     'update_available' => bool,
//     'compatible'        => bool,
//     'should_apply'      => bool,  // both of the above
//     'manifest_version'  => string,
//     'installed_version' => string,
// ]
```

Same plain-array-contract shape as `MintCart\Onboarding\StoreReadiness::evaluate()`
(`@return array{...}` documented on the method, no dedicated result class —
CLAUDE.md §86, one clear method over a class hierarchy). `update_available`
and `compatible` are deliberately independent booleans, not collapsed into
one: a UI showing "an update exists but isn't compatible yet" needs to
distinguish the two, not just get a single yes/no.

Still has no downloader — resolving *how* to fetch a manifest for `check`
to consume is the flow's own `download` step (see below), a later
increment.

## The `backup` step — `BackupPlanner`

`MintCart\Update\BackupPlanner`
(`upload/system/library/mintcart/update/backupplanner.php`) is the third
increment: the flow's own `backup` step. Given a manifest plus which of
its declared paths currently exist on disk (and their sizes — resolved by
the caller, not this class), it decides exactly what belongs in the
backup and where to put it:

```php
$planner = new \MintCart\Update\BackupPlanner();

$plan = $planner->plan(
    $manifest,                                    // PackageManifest
    ['catalog/view/theme/mint_default/theme.json' => 512],  // path => size in bytes, caller-resolved
    '20260909120000'                              // snapshot timestamp, caller-generated
);

// $plan === [
//     'package'      => 'mint_default',
//     'snapshot_dir' => 'system/storage/mintcart/backup/mint_default/20260909120000',
//     'files'        => ['catalog/view/theme/mint_default/theme.json'],
//     'total_bytes'  => 512,
// ]
```

Design decisions, following `docs/adr/005-theme-extension-update-rollback.md`:

- **Union of `files()` and `removes()`, de-duplicated.** Both an
  overwritten file and a deleted file get backed up before the update
  touches them (decision #3); a path listed in both is only backed up
  once.
- **Only paths that actually exist are backed up.** A `files()`/`removes()`
  path missing from the caller's `$existingFiles` map is treated as "not
  installed yet" — nothing to snapshot (a brand-new file the update only
  adds has nothing to back up).
- **`snapshotPath()` never lands under a web-servable directory**
  (decision #7) — `system/storage/mintcart/backup/<package>/<timestamp>`,
  the same convention OpenCart already uses for its own non-servable files
  (`system/storage/cache`, `system/storage/logs`).
- **`hasEnoughDiskSpace(int $snapshotBytes, int $incomingPackageBytes, int $freeBytes): bool`**
  is a separate pre-flight check (decision #6), sized for *both* the
  snapshot being written and the incoming package's own files landing on
  the same disk. Meant to run — and refuse to start — before any file is
  touched, never to abort a copy already in progress.

Same "resolved values in, normalized contract out" shape as
`PackageManifest`/`UpdateChecker`: no `\Registry`, no `\DB`, no
`disk_free_space()`/`filesize()` call of its own. A real `files` step
caller resolves which paths exist and their sizes (e.g. via `file_exists()`/
`filesize()` against the live install) and passes that resolved map in.

## What's deliberately not done yet

- No caller — no admin controller, no `mintcart/update/*` route, nothing
  fetches a real remote manifest or parses a real downloaded manifest
  today, and nothing calls `BackupPlanner::plan()` against a live
  install's files. `UpdateChecker` exists but nothing calls it outside its
  own test yet.
- No `mc_installed_file` ledger (per-file change detection via sha256,
  ADR 005 decision #1) — `BackupPlanner` only knows what a manifest
  declares, not whether an installed file was hand-edited since its last
  install. That needs the Migration Manager to add the table once a real
  `files` step exists to write to it.
- No actual file copy/restore — `plan()` only decides *what* to copy;
  performing the copy (and a restore action on top of a stored snapshot)
  is later work, once the `files` step itself exists.
- No download/verify/maintenance-mode/file-copy/migration-runner/
  cache-clear/health-check steps — the rest of the Technical Plan §33 flow.
  The `health-check` step should wait for `MintCart\Core\HealthCheck`
  (tracked separately) to land before it's wired in.
- No package **signing** — `checksum` is carried as opaque data; nothing
  computes or verifies a hash against it yet.

Verified: `php -l` clean on every touched/added file, re-swept across the
full `upload`/`tests` tree; `tests/mintcart/update/package-manifest-test.php`
(11 scenarios, including the semver-caret edge cases, a check that this
class's actual `version_compare()` reliance orders `MintCart\Core\Version`'s
real `"0.1.0-dev"` string the way the class assumes, and now `removes()`
coverage), `tests/mintcart/update/update-checker-test.php` (7 scenarios,
including one composing `UpdateChecker` with the real
`MintCart\Core\Version::VERSION`), and the new
`tests/mintcart/update/backup-planner-test.php` (7 scenarios: empty plan, a
not-yet-existing path skipped, an existing `files()` path backed up with
its size counted, a `removes()` path backed up the same way, a path in
both lists de-duplicated, `snapshotPath()`'s exact output, and
`hasEnoughDiskSpace()`'s boundary). Full `tests/mintcart/**/*-test.php`
suite re-run end to end — all passing, no regressions. Pure PHP, no
OpenCart dependency, so no live-install verification gap applies here
(unlike most other increments in this repo) — there is nothing to run
against a real install yet.
