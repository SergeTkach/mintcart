# Update Manager — `check` route

`upload/admin/controller/mintcart/update.php` (`ControllerMintcartUpdate`)
— the Update Manager flow's (`MINTCART_TECHNICAL_PLAN.md` §33 /
`MINTCART_IMPLEMENTATION_GUIDE.md` §69) first real admin consumer
(`CLAUDE.md` §85) of `MintCart\Update\PackageManifest` +
`MintCart\Update\UpdateChecker`.

route=`mintcart/update/check` — POST, `manifest` (raw package manifest
JSON text, the same shape `PackageManifest::fromArray()` validates:
`name`, `version`, `requirements`, `files`, `removes`, `migrations`,
`checksum`). JSON response:

```json
{
  "update_available": true,
  "compatible": true,
  "should_apply": true,
  "manifest_version": "0.2.0",
  "installed_version": "0.1.0-dev",
  "manifest": { "name": "mintcart-core", "version": "0.2.0" }
}
```

or, on a validation problem, `{"error": "..."}`.

## Scope

Checks **only** whether the pasted manifest describes an available,
compatible **MintCart Core** update:

- `installedOwnVersion` passed to `UpdateChecker::check()` is always
  `\MintCart\Core\Version::VERSION` — the currently installed MintCart
  version.
- `installedVersions` (checked against the manifest's own `requirements`)
  is always `['mint_version' => Version::VERSION, 'php_version' =>
  PHP_VERSION]`.

A future theme/extension update target (as opposed to MintCart Core
itself) would need its own "currently installed version" lookup — not
attempted here, since no such per-theme/per-extension version registry
exists yet in this codebase (`CLAUDE.md` §85: no API ahead of a real
consumer).

## Deliberately not attempted here

- **Fetching a manifest from a remote update server.** MintCart has no
  update-server integration yet. Resolving *how* to fetch a remote
  manifest is the flow's own `download` step (`MintCart\Update\PackageDownloader`
  already exists as a primitive, `docs/developer/update/package-downloader.md`)
  — a separate, later increment. The manifest is pasted as raw JSON text
  instead, the same scope decision `ControllerMintcartCompatibility`
  already made for `install.xml` (`docs/developer/compatibility/compatibility-checker.md`).
- **Calling `MintCart\Update\UpdateOrchestrator::apply()`.** Applying a
  manifest performs real file writes, a maintenance-mode toggle and
  database migrations against the live install — wiring that into an
  admin action is a separate, larger increment that needs a resolved
  download URL and destination root this check-only screen has no reason
  to require. Every constituent `Update\*` primitive's own doc already
  named "an admin controller/route" as later work
  (`docs/developer/update/update-orchestrator.md`'s own "Deliberately out
  of scope" section).
- **An admin menu entry.** Same reasoning
  `ControllerMintcartCompatibility`/`ControllerMintcartWizard`/
  `ControllerMintcartReadiness` already documented — this route is
  reachable directly for now; wiring `common/column_left.php` is separate,
  later work (and, at the time this route was added, a file several other
  open, unmerged PRs were also touching — see
  `docs/IMPLEMENTATION_STATUS.md`'s Phase 11 (Technical Plan) entry for
  this run).

## Tests

No dedicated controller test — this repo has no live OpenCart admin
bootstrap to instantiate `Controller`/`$this->user`/`$this->load` against
(same pre-existing gap every other `ControllerMintcart*` class has, see
`docs/developer/compatibility/compatibility-checker.md`). The logic this
controller calls is already fully unit-tested:
`tests/mintcart/update/package-manifest-test.php` (11 scenarios) and
`tests/mintcart/update/update-checker-test.php`. `php -l` clean on both
new files.

## Manual verification

See `docs/MANUAL_VERIFICATION.md`'s Update Manager entry.
