# Update Manager — Package Downloader

`MintCart\Update\PackageDownloader`
(`upload/system/library/mintcart/update/packagedownloader.php`)

## What this is

The `download` step of the Update Manager flow described in
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

`docs/developer/update/package-verifier.md`'s own "What's deliberately not
done yet" section named this step directly as the remaining candidate before
`PackageVerifier::verifyChecksum()` has a real caller ("a `download` step
that actually fetches a package archive over HTTP, then hands the local path
to `verifyChecksum()`"). `PackageDownloader` is that step — same "pure,
primitive first" pattern `PackageManifest`, `PackageVerifier`,
`BackupPlanner` and `MaintenanceMode` already used: build the checkable/
testable primitive before there is a real orchestrator that runs
`download → verify → maintenance → files → migrations` in sequence
(`CLAUDE.md` §85 — no API ahead of a real consumer).

## Why an injectable transport, not `file_get_contents()`/cURL directly

This environment has no outbound network access, so a real HTTP fetch
cannot be exercised end to end here — the same constraint every other
Update Manager class in this backlog has documented for itself. Rather than
skip testing entirely, `PackageDownloader` takes an injectable
`callable(string $url): string` transport (duck-typed, no interface —
`CLAUDE.md` §86, "simplicity over abstraction"), the same pattern
`MigrationManager` duck-types `\DB` and `SettingsAdapter` duck-types
`ModelSettingSetting` for. Every test scenario injects a fake transport; the
default `file_get_contents()`-based transport (`fetchViaStreamContext()`)
is real production code but is not exercised by this class's own tests — a
human should smoke-test it against a real update-package URL before this
class's first live caller ships.

This also means swapping in a cURL-based transport with download-progress
reporting later needs no change to this class's file-handling logic at all.

## API

```php
$downloader = new PackageDownloader(); // production: real HTTP fetch

$downloader->download(
    'https://updates.example.test/mint_default-1.2.0.zip',
    '/tmp/mintcart-update/mint_default-1.2.0.zip'
);

// PackageVerifier::verifyChecksum() is the natural next step once a
// PackageManifest's declared checksum is available:
PackageVerifier::verifyChecksum('/tmp/mintcart-update/mint_default-1.2.0.zip', $manifest->checksum());
```

- `download(string $url, string $destinationPath): void` — fetches `$url`
  via the transport and writes the result to `$destinationPath`.
- Only `http://`/`https://` URLs are accepted — a `file://`/`phar://`/etc.
  URL, or a URL with no scheme at all, throws `\InvalidArgumentException`
  before the transport is ever called. A package source is expected to be a
  remote endpoint; accepting a local-path-reading wrapper scheme here would
  only be a foot-gun, not a real use case.
- The write is atomic: the response body is written to a temp file
  (`{destination}.part-{random}`) next to the real destination, then
  `rename()`d into place only once the write has fully succeeded. A request
  that dies mid-download (or a transport that throws) never leaves a
  corrupt/partial file at the real destination path for a concurrent
  `verify` step to read.
- The destination's parent directory is created (recursively) if it does
  not exist yet.
- An empty response body, a transport that throws, or a failed
  write/rename all raise `\RuntimeException` (or let the transport's own
  exception propagate unchanged) — nothing is silently swallowed.

## What's deliberately not done yet

- ~~**No orchestrator.**~~ **Done** — `MintCart\Update\UpdateOrchestrator`
  (`docs/developer/update/update-orchestrator.md`) now chains
  `download → extract → verify → backup → maintenance → files →
  migrations → cache clear → health check` end to end (`extract`/`backup`
  optional). Still no admin controller/route calling it.
- ~~**No archive extraction.**~~ **Done** — `MintCart\Update\PackageExtractor`
  (`docs/developer/update/package-extractor.md`) now extracts the archive
  this class downloads into the directory `PackageVerifier::missingFiles()`
  expects, and is wired into `UpdateOrchestrator::apply()` as an optional
  step — see that class's own doc.
- **No progress reporting / resumable downloads.** A future orchestrator
  that needs to show download progress in an admin UI can swap the
  injected transport for one that streams and reports progress — this
  class's own file-handling logic does not need to know about that.
- **No signature/PKI verification** — same open gap `PackageManifest`'s own
  doc already flags ("No package signing").
- **The default `fetchViaStreamContext()` transport is untested** in this
  environment (no outbound network access) — a human should confirm it
  against a real update-package URL before any orchestrator relies on it in
  production.

## Verified

- `php -l` clean on the class and its test.
- `tests/mintcart/update/package-downloader-test.php` (9 scenarios): writes
  the transport's bytes verbatim, the transport receives the exact
  requested URL, missing parent directories are created, no leftover
  `.part-*` temp file after a successful write, a stale existing
  destination file is overwritten on retry, a non-http(s) URL is rejected
  before the transport is ever called (and no file is written), a
  schemeless URL is rejected, an empty transport response throws
  `RuntimeException` with no file written, and a thrown transport exception
  propagates unchanged with no file written.
- Full `tests/mintcart/**/*-test.php` suite re-run end to end — all
  passing, no regressions.
- **Not verified:** the default network transport, and any real HTTP
  fetch — no outbound network access in this environment. No live
  MySQL/OpenCart bootstrap either, but this class has no OpenCart
  dependency to begin with.
