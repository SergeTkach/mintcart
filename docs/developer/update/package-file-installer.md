# Update Manager — Package File Installer

`MintCart\Update\PackageFileInstaller`
(`upload/system/library/mintcart/update/packagefileinstaller.php`)

## What this is

The `files` step of the Update Manager flow described in
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

By the time this step runs, `PackageVerifier::verifyChecksum()` has already
confirmed the downloaded archive's integrity, the archive has been
extracted to a local directory, `BackupPlanner::plan()` has already decided
what to snapshot, and maintenance mode has already been enabled. This class
performs the actual on-disk change: copying every path
`PackageManifest::files()` declares from the extracted package into the
live install, and deleting every path `PackageManifest::removes()` declares
(ADR 005 decision #3) — never the other way around, and never a path
outside those two explicit lists.

## `install()`

```php
$installer = new PackageFileInstaller();
$result = $installer->install($manifest, '/tmp/mint_default-extracted', DIR_APPLICATION . '..');
// [
//   'installed' => [
//       ['path' => 'template/mint/components/product-card.twig', 'sha256' => '...'],
//       ...
//   ],
//   'removed' => ['some/deleted/legacy-file.php'],
// ]
```

- **Pre-flight, not partial.** Before touching any file, `install()` reuses
  `PackageVerifier::missingFiles()` to confirm every declared `files()`
  path is actually present in the extracted package, and throws
  `\RuntimeException` without writing anything if not — same "refuse to
  start rather than abort partway through" philosophy
  `BackupPlanner::hasEnoughDiskSpace()` already established for ADR 005
  decision #6.
- **Atomic per file.** Each copy writes to a `.part-<uniqid>` sibling of
  the real destination first, then `rename()`s it into place, so a request
  that dies mid-copy never leaves a half-written file at the real
  destination path (same temp-file-then-rename pattern used elsewhere in
  this codebase for atomic writes).
- **Idempotent removes.** A `removes()` path that is already absent from
  the live install (e.g. a retried/resumed update) is treated as already
  satisfied, not an error, and is not reported in `removed[]` — matches
  `MaintenanceMode::enable()`/`disable()`'s own idempotency.
- **No path traversal.** A declared path containing a `..` segment throws
  `\InvalidArgumentException` before anything is touched — a
  manifest-authoring bug, not a normal "file missing" outcome.
- **Returns per-file sha256.** Each `installed` entry carries the sha256
  of the file actually written to the destination — exactly what
  `MintCart\Update\InstalledFileLedger::record()`
  (`docs/developer/update/installed-file-ledger.md`) now persists, called
  by `UpdateOrchestrator::apply()` right after this class's `install()`
  succeeds. This class still does not write that ledger itself — it only
  produces the sha256, the same "resolved values in, normalized contract
  out" shape as every other primitive in this flow.

## `missingRemoves()`

```php
$missing = PackageFileInstaller::missingRemoves(DIR_APPLICATION . '..', $manifest);
// [] means every removes() path is already present (install() will delete all of them).
```

Mirrors `PackageVerifier::missingFiles()`'s shape but for the opposite
question — "what would removal actually delete?" A caller may use this to
report what a `removes()` step will do before running `install()`; an
empty result is not an error (see "idempotent removes" above).

Deliberately has no OpenCart dependency (no `\Registry`, no `\DB`) — same
"resolved values in, normalized contract out" shape as `PackageVerifier`/
`BackupPlanner`/`PackageManifest`.

## What's deliberately not done yet

- ~~No caller~~ **Done** — `MintCart\Update\UpdateOrchestrator`
  (`docs/developer/update/update-orchestrator.md`) now calls `install()`
  as its `files` step, between `maintenance` and `migrations`. Still no
  admin controller or `mintcart/update/*` route calling the orchestrator
  itself.
- ~~No `mc_installed_file` ledger write~~ **Done** —
  `MintCart\Update\InstalledFileLedger::record()` now persists every
  `installed` entry's sha256, called by `UpdateOrchestrator::apply()`
  right after this class's `install()` succeeds (see
  `docs/developer/update/installed-file-ledger.md`). `install()` itself
  still does not write the ledger — it only computes and returns the
  sha256, unchanged.
- ~~No `migrations`/`cache clear`/`health check` steps~~ **Done** — the
  orchestrator wires `MintCart\Core\Migration\MigrationManager` (Phase 1),
  `MintCart\Core\Cache\CacheInvalidator` and `MintCart\Core\HealthCheck` in
  after this step, in that order.
- No archive extraction — same as `PackageVerifier`, this assumes a
  caller has already extracted the package (the `download` step's job).

Verified: `php -l` clean; `tests/mintcart/update/package-file-installer-test.php`
(16 scenarios — byte-for-byte copy, nested destination directory creation,
no leftover `.part-*` temp file, a stale existing destination overwritten
on retry, a missing source file throwing with nothing written even for
files that were present, a `..` path segment throwing, `removes()`
deleting a present file and reporting it, `removes()` on an already-absent
path being a silent no-op, an empty manifest installing/removing nothing,
`missingRemoves()` reporting only absent paths, and its trailing-slash
tolerance). Pure PHP against temp-directory fixtures, no OpenCart
dependency, so no live-install verification gap applies to this class
itself — there is no real download/extraction/orchestrator pipeline yet
for this to run against on a live install.
