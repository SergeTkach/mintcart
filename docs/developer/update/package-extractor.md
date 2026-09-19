# Update Manager — Package Extractor

`MintCart\Update\PackageExtractor`
(`upload/system/library/mintcart/update/packageextractor.php`)

## What this is

Archive extraction for the Update Manager flow described in
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

`PackageDownloader`, `PackageVerifier` and `UpdateOrchestrator` all named
the same gap directly in their own docblocks: an archive is downloaded
(`PackageDownloader`), but nothing in this codebase turned that downloaded
file into the extracted directory `PackageVerifier::missingFiles()`,
`PackageFileInstaller::install()` and `UpdateOrchestrator::apply()` all
already assume as an input. `PackageExtractor` is that step — same "pure,
OpenCart-independent primitive first, wire a caller once it exists"
pattern every other `MintCart\Update\*` class already used (`CLAUDE.md`
§85).

## `extract()`

```php
$extractor = new PackageExtractor();
$extractedFiles = $extractor->extract(
    '/tmp/mint_default-1.2.0.zip',           // downloaded archive
    DIR_SYSTEM . 'storage/download/mint_default-1.2.0/' // destination directory
);
// ['upload/admin/...', 'upload/system/...', ...] — sorted relative paths of
// every FILE entry the archive contained (directory entries are omitted).
```

Two responsibilities, deliberately, same split `PackageDownloader`/
`PackageFileInstaller` already use for their own single on-disk operation:

- **Zip-slip protection.** Every entry name is checked for a `..` path
  segment or an absolute path (leading `/`, `\`, or a Windows drive
  letter) *before* anything is extracted. A malicious or corrupt archive
  throws `\RuntimeException` and writes nothing — mirrors
  `PackageFileInstaller::assertNoTraversal()`'s reasoning one level up
  (manifest paths there, archive entry names here).
- **Atomic extraction.** The archive is extracted to a `.part-<uniqid>`
  temp directory next to the real destination first, then that temp
  directory replaces the destination via `rename()` — the same
  temp-then-rename pattern `PackageDownloader::writeAtomically()`/
  `PackageFileInstaller::copyFile()` already use for single files, applied
  here to a whole directory tree. A request that dies mid-extraction never
  leaves a half-extracted directory at the real destination path.

An existing destination directory (e.g. a retried/resumed update) is
**replaced wholesale**, not merged into — a stale leftover file from a
previous extraction attempt must never silently survive alongside the new
one.

## Deliberately not attempted here

- **Checksum verification of the archive itself.** That is
  `PackageVerifier::verifyChecksum()`'s job, against the still-compressed
  file, and happens *before* this class would run in a real flow.
- **Confirming extracted contents match the manifest.** That is
  `PackageVerifier::missingFiles()`'s job, against the directory this
  class produces, and happens *after* this class runs.
- **Any manifest awareness at all.** This class only knows about an
  archive path and a destination directory — exactly like
  `PackageDownloader` only knows about a URL and a destination file. It
  takes no `PackageManifest` argument.
- ~~Wiring into `UpdateOrchestrator::apply()`.~~ **Done** — `apply()` now
  takes an optional `PackageExtractor` constructor argument (see
  `docs/developer/update/update-orchestrator.md`). When injected, `extract()`
  runs between `download` and `verify`; when omitted (the default),
  `$extractedDir` is still assumed already populated, so every caller
  written against the original signature keeps working unchanged.

## Why `ZipArchive` directly, not an injectable transport

`PackageDownloader` duck-types its transport (`callable(string $url):
string`) because this environment has no outbound network access to
verify a real HTTP fetch end to end. Extracting a *local* zip file needs
no network at all, so `PackageExtractor` uses PHP's built-in `ZipArchive`
directly — this class's own tests build small real zip fixtures on disk
with `ZipArchive` and extract them for real, including the zip-slip
rejection path, with no faking required.

`ZipArchive` is already used elsewhere in this OpenCart 3 codebase
(`upload/admin/controller/marketplace/install.php`'s `unzip()`), so it is
a safe assumption for the target hosting environment (`CLAUDE.md` §1 —
shared hosting, no new PHP extension requirement).

## What's deliberately not done yet

- No admin controller/`mintcart/update/*` route calls any of this yet
  (`UpdateOrchestrator::apply()` now does — see above — but nothing calls
  `apply()` itself from a real admin screen).
- Only zip archives are supported (`ZipArchive`) — no tar/gzip handling.
  Nothing in this codebase's manifest format or `PackageDownloader`
  assumes a specific archive format, but zip is what OpenCart's own
  marketplace installer already uses, so it is the only format this class
  needs to support today (`CLAUDE.md` §85 — no API ahead of a real need).

Verified: `php -l` clean; `tests/mintcart/update/package-extractor-test.php`
(9 scenarios — extracting nested files and returning their sorted relative
paths, directory-only entries omitted from the returned list, a `..`
zip-slip entry rejected before anything is written, an absolute-path entry
rejected, an existing destination directory replaced wholesale rather than
merged into, no leftover `.part-*` temp directory after success, a missing
archive throwing, a zero-entry archive throwing, and a non-zip file at the
archive path throwing). Pure PHP against real `ZipArchive` fixtures built
on temp directories, no OpenCart dependency and no network dependency, so
unlike `PackageDownloader` there is no "never exercised end to end"
caveat for the happy path — the real extraction code runs in every
passing scenario above.
