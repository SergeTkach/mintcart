# Update Manager — Package Verifier

`MintCart\Update\PackageVerifier`
(`upload/system/library/mintcart/update/packageverifier.php`)

## What this is

The `verify` step of the Update Manager flow described in
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

`docs/developer/update/package-manifest.md`'s own "What's deliberately not
done yet" section calls this step out directly: `PackageManifest::checksum()`
is carried as opaque data, and "verifying it against a downloaded file is
the `verify` step, not this class's job." `PackageVerifier` is that step —
same "pure, OpenCart-independent primitive first" pattern `PackageManifest`
itself, `MigrationManager` (Phase 1) and `WizardProgress` (Phase 13) already
used: build the checkable primitive before there is a real `download` step
to hand it a fetched archive (`CLAUDE.md` §85 — no API ahead of a real
consumer).

## Two independent questions

A caller moving from `download` to `maintenance`/`files` needs both
answered before it is safe to proceed:

1. **Does the downloaded archive match the manifest's declared checksum?**
   `PackageVerifier::verifyChecksum(string $filePath, ?string $checksum): bool`
2. **Are all the files the manifest says it installs actually present in
   the extracted package?**
   `PackageVerifier::missingFiles(string $baseDir, PackageManifest $manifest): array`

These are deliberately separate methods, not one combined `verify()`: the
first checks a single archive file's integrity before extraction, the
second checks an already-extracted directory's completeness against the
manifest's own `files()` list. A caller may only need one of the two
(e.g. an update fetched over a channel that already guarantees byte
integrity, but not completeness) and forcing both into one call would
require inventing a combined return shape (`CLAUDE.md` §86 — no
abstraction beyond a real consumer's actual need).

## `verifyChecksum()`

```php
$ok = PackageVerifier::verifyChecksum('/tmp/mint_default-1.2.0.zip', $manifest->checksum());
```

- `$checksum` is the `"algorithm:hash"` string `PackageManifest` carries
  opaquely (e.g. `"sha256:deadbeef..."`). `null` or blank means the
  manifest declared no checksum — nothing to check, so this returns `true`
  (matches `PackageManifest`'s own "No package signing… nothing computes or
  verifies a hash against it yet" note: a manifest without a checksum is
  not itself an error, it just cannot be integrity-checked).
- The algorithm is whatever PHP's own `hash_file()`/`hash_algos()` support
  (`sha256`, `sha1`, `md5`, …) — not a fixed enum, since the manifest
  format doesn't fix one either.
- Hex comparison is case-insensitive and constant-time (`hash_equals()`).
- A missing file at `$filePath` fails closed (`false`), not an exception —
  "the archive isn't there yet" is a normal verify-step outcome a caller
  checks for, not a manifest-authoring bug.
- A malformed checksum string (no `:` separator) or an algorithm this PHP
  build doesn't support both throw `\InvalidArgumentException` — those
  *are* manifest-authoring bugs and should surface loudly rather than
  silently reporting "unverified."

## `missingFiles()`

```php
$missing = PackageVerifier::missingFiles('/tmp/mint_default-extracted', $manifest);
// [] means every file the manifest declared is present under baseDir.
```

- Walks `$manifest->files()` (the relative paths the manifest says it
  installs/overwrites) and reports which ones are not present as regular
  files under `$baseDir`.
- Says nothing about file *contents* — only presence. Content integrity is
  `verifyChecksum()`'s job, checked once against the whole archive before
  extraction, not per extracted file (the manifest only carries one
  package-level checksum, not a per-file manifest).
- A manifest declaring no files (`files()` defaults to `[]`) always
  reports no gaps — nothing to check.

## What's deliberately not done yet

- No archive extraction — `missingFiles()` assumes a caller has already
  extracted the package (that's the `download` step's job, not `verify`'s).
- No signature/PKI verification — `PackageManifest` still carries no
  signing metadata beyond the opaque `checksum` string; this class checks
  exactly what that field is (a hash), nothing more.
- No caller yet — no admin controller, no `mintcart/update/*` route calls
  this. `download` (`docs/developer/update/package-downloader.md`,
  `MintCart\Update\PackageDownloader`), `maintenance` mode
  (`docs/developer/update/maintenance-mode.md`,
  `MintCart\Update\MaintenanceMode`) and `files`
  (`docs/developer/update/package-file-installer.md`,
  `MintCart\Update\PackageFileInstaller`) each now have their own
  primitive, but nothing yet chains them together. The next real
  increment in this flow is the orchestrator that runs
  `check → ... → download → verify → maintenance → files → ...` end to
  end.

Verified: `php -l` clean; `tests/mintcart/update/package-verifier-test.php`
(12 scenarios — null/blank checksum short-circuits, a real sha256 match,
case-insensitive hex comparison, a checksum mismatch, a missing archive
file, a second algorithm (md5), a malformed checksum string throwing, an
unsupported algorithm throwing, `missingFiles()` reporting no gaps when
everything is present, reporting exactly the files that are absent, an
empty `files()` list, and a trailing-slash `$baseDir`). Pure PHP against
temp-directory fixtures, no OpenCart dependency, so no live-install
verification gap applies here (same as `PackageManifest` itself) — there
is no real download/extraction pipeline yet for this to run against on a
live install.
