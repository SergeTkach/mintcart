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
  overwrites — used later by the backup step (back up whatever a file list
  says will be overwritten, the same open question
  `MINTCART_FUNCTIONAL_SCOPE.md` §21 flags for theme/extension rollback).
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

## What's deliberately not done yet

- No caller — no admin controller, no `mintcart/update/*` route, nothing
  parses a real downloaded manifest today. The next real increment is
  whichever piece needs this first: a `check` step that fetches a remote
  manifest and calls `isCompatible()` against `MintCart\Core\Version`, or
  a `backup` step that reads `files()` to know what to snapshot before
  overwriting.
- No download/verify/backup/maintenance-mode/file-copy/migration-runner/
  cache-clear/health-check steps — the rest of the Technical Plan §33 flow.
- No package **signing** — `checksum` is carried as opaque data; nothing
  computes or verifies a hash against it yet.

Verified: `php -l` clean; `tests/mintcart/update/package-manifest-test.php`
(11 scenarios, including the semver-caret edge cases and a check that this
class's actual `version_compare()` reliance orders `MintCart\Core\Version`'s
real `"0.1.0-dev"` string the way the class assumes). Pure PHP, no OpenCart
dependency, so no live-install verification gap applies here (unlike most
other increments in this repo) — there is nothing to run against a real
install yet.
