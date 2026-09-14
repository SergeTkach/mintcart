# Release packaging

MintCart's end users must never need Node or Composer to install the
store — `npm install`/`npm run build` (frontend) and the PHP vendor
libraries already committed under `upload/system/**/vendor/` are
dev/release-time tooling only. This document is the release-packaging
step that turns a committed state of this repository into the installable
artifact those users actually download, the same way OpenCart itself
ships a pre-built `upload/` zip rather than its own source tree.

This is a standalone packaging script for now, not part of a full Update
Manager (`MINTCART_TECHNICAL_PLAN.md` §33, not started — see
`docs/IMPLEMENTATION_STATUS.md`'s "Phase 11 (Technical Plan) — Update /
Compatibility" section). It produces the same kind of artifact an Update
Manager's package-manifest step would eventually need to build; when that
phase starts, this script (or its logic) is the natural first building
block, not something to redo from scratch.

## Usage

```sh
npm run release
```

Requires `git` and `zip` on `PATH` (both present in this repo's own CI
runner — see `.github/workflows/ci.yml`'s `ubuntu-latest` jobs). Produces
`release/mintcart-<version>.zip`, where `<version>` is read from
`MintCart\Core\Version::VERSION`
(`upload/system/library/mintcart/core/version.php`) — the single source of
truth for the platform version; the script never hardcodes or re-types it.

`release/` is gitignored — it is build output, not a source directory.

## What it does

1. **Builds the frontend** (`npm run build`) — fresh minified
   `assets/dist/{css,js}`.
2. **Stages the *committed* `upload/` tree** via `git archive HEAD upload`
   piped into `tar -x`. This deliberately packages `HEAD`, not the working
   tree — commit your changes first, or the release won't include them.
   Because `git archive` only ever emits tracked files, everything
   `.gitignore` already excludes from a real install — `config.php`,
   `admin/config.php`, `.htaccess` (only the stock `.htaccess.txt`
   template ships, same as upstream OpenCart), `install.lock`, and every
   runtime `storage/`/`image/cache/` directory's contents — is excluded
   automatically, with no separate exclude-list to keep in sync.
3. **Copies the built assets into `upload/assets/{css,js}`** inside that
   staged copy. `assets/` lives outside `upload/` in this repository (so
   the frontend build pipeline isn't nested inside the OpenCart docroot),
   but `upload/` is what a webserver actually serves — so the built output
   has to land inside `upload/assets/` to be reachable by URL. This is the
   exact same convention already documented in `.gitignore` for a
   local-dev checkout (`upload/assets/` there is a gitignored, dev-time
   copy of the same build output for the same reason); the release script
   is what makes that copy for real, deploy-time installs instead of
   requiring every dev machine to remember to do it by hand.
4. **Zips** the staged `upload/` directory into
   `release/mintcart-<version>.zip`.

## What it deliberately does not do (yet)

- **No version bump.** The script reads `Version::VERSION`, it never
  writes it — bumping the version for a release is a separate, deliberate
  step (edit `version.php`, commit, then run `npm run release`).
- **No compatibility check, backup, migration runner, or checksum/signature.**
  Those are Update Manager concerns (`MINTCART_TECHNICAL_PLAN.md` §33's
  full `check → compatibility → backup → download → verify → maintenance →
  files → migrations → cache clear → health check → done` flow) — this
  script only produces the "files" artifact that flow would eventually
  download and install; it does not install anything itself.
- **No `upload/install/` removal.** OpenCart's own downloadable zip ships
  `install/` and expects the installer to prompt the user to delete it
  after a successful install — this script follows that same convention
  rather than silently removing it, so behavior stays familiar to anyone
  who has installed stock OpenCart before.
- **No PHP Composer vendor bundling beyond what's already committed.**
  `upload/system/**/vendor/` is already tracked in this repo (per this
  file's opening paragraph), so `git archive` already includes it; this
  script does not run `composer install`.

## Verifying a release locally

```sh
npm run release
unzip -l release/mintcart-*.zip | head -30      # sanity-check the file list
unzip -l release/mintcart-*.zip | grep -E '/\.htaccess$|config\.php$|install\.lock$'
# ^ should print nothing (only .htaccess.txt / config-dist.php templates ship)
unzip -l release/mintcart-*.zip | grep 'upload/assets/'
# ^ should show css/index.min.css, js/catalog.min.js, js/admin.min.js
```

This was run manually against this repository's own `HEAD` while building
the script (2944 files, ~14.5 MB, `mintcart-0.1.0-dev.zip`) — the checks
above all passed: no live `.htaccess`/`config.php`/`install.lock` present,
only the stock `-dist`/`.txt` templates; the three built asset files
present under `upload/assets/`. No automated test wraps this (it shells
out to `git`/`zip`/`npm` and produces a multi-megabyte binary artifact —
not something a `tests/mintcart/**/*-test.php`-style smoke test is a good
fit for); a human/CI run of `npm run release` itself, as shown above, is
the verification.
