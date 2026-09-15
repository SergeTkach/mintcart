# 005 — Theme/extension update rollback: research and design decisions

## Context

`MINTCART_FUNCTIONAL_SCOPE.md` §21 and `MINTCART_TECHNICAL_PLAN.md` §34 both
flag "Rollback для theme/extension update" as a **RESEARCH** item, distinct
from the general Update Manager backup step in the main flow (`check` →
`compatibility` → `backup` → `download` → `verify` → `maintenance` →
`files` → `migrations` → `cache clear` → `health check` → `done`,
`MINTCART_IMPLEMENTATION_GUIDE.md` §69). The Functional Scope is explicit
that this is "не тотожна повному backup" (not the same thing as a full
backup) — its scenario is narrower: before a *theme or extension* update
overwrites specific files, snapshot exactly what will be overwritten (which
may include a client's manual edits) so that update can be undone.

Per CLAUDE.md §13/§20 and Functional Scope §21's own instruction
("Перед реалізацією визначити..." — determine before implementing), this
ADR is the "determine" step, not the implementation. It answers each of
Functional Scope §21's open questions and turns them into concrete design
decisions a future increment can build against. **No rollback code is
added in this increment** — per `CLAUDE.md` §85, an API/table only gets
created once a real consumer exists, and there is no rollback consumer yet
(the Update Manager flow itself is still at its first increment,
`MintCart\Update\PackageManifest` — see
`docs/developer/update/package-manifest.md` — with no `files`/`backup`
step implemented to hang a snapshot off of).

Relevant existing primitive: `MintCart\Update\PackageManifest`
(`upload/system/library/mintcart/update/packagemanifest.php`) already
parses a package's declared `files` (list of relative paths it installs/
overwrites) and an optional whole-package `checksum`. It has no per-file
checksum and no notion of files a *new* version removes — both matter for
rollback and are addressed below.

## Decision

Seven questions, seven decisions:

### 1. How to reliably detect changed files

Record a per-file `sha256` in a new install ledger at the moment a
package's files are actually copied onto disk (the Update Manager `files`
step, not yet implemented) — one row per `(package_name, file_path)`,
overwritten on every successful install of that package. A file counts as
"changed since install" (possibly by hand, possibly by the site owner)
when its current on-disk `sha256` no longer matches the ledger's last
recorded value for that path — compared against the **previous recorded
install**, never against the *incoming* package's expected content (which
would flag every routine content update as a manual change, telling the
site owner nothing useful).

### 2. Can the package manifest itself be diffed?

Yes, and it's the right tool for classifying *why* a file is touched by an
update, complementary to #1: comparing the old package's
`PackageManifest::files()` against the new one's separates "this path is
in both — a routine overwrite, back it up" from "this path is only in the
old one — see #3" from "this path is only in the new one — nothing to back
up, it doesn't exist yet." The per-file ledger from #1 then answers the
*separate* question of whether the specific file being overwritten also
carries local edits worth warning about before it's replaced.

### 3. Files a new package version removes

`PackageManifest`'s `files` list has no `removes` field today, and
inferring "anything missing from the new list must be deleted" is too
fragile — a path can legitimately drop off the list because its content
was merged elsewhere, not because it should be deleted from disk. Decision:
add an optional `removes: string[]` field to the manifest shape in the
increment that implements this (mirrors `files`; optional, same as
`migrations`/`checksum` already are in `PackageManifest::fromArray()`).
Files listed in `removes` get backed up through the same ledger/snapshot
mechanism as an overwrite, then deleted — never deleted without a snapshot.

### 4. Interaction with DB migrations

No automatic DB rollback is promised — Functional Scope §21 says this
explicitly ("Для DB changes не обіцяти повний автоматичний rollback без
надійного механізму"), and it holds here: `MintCart\Core\Migration\MigrationManager`
migrations are apply-once and forward-only (Phase 1); nothing in
`upload/system/mintcart/migrations/` has a `down()`, and adding one now
with no real caller would be exactly the speculative API `CLAUDE.md` §85
warns against. A file/theme rollback restores files and the ledger to
their pre-update state but leaves any migrations that ran during that
update applied — the rollback UI must say this plainly rather than imply
a full state restore.

### 5. Retention policy

One snapshot slot per package, not a version history. This matches the
Functional Scope's framing of "undo my last update," not a backup browser.
A new snapshot for package X replaces X's previous snapshot only once the
update that created it completes successfully (its health-check step
passes, once that step exists per Phase 1's `HealthCheck`); if the health
check fails, the snapshot just taken *is* the rollback target and is never
overwritten or pruned automatically.

### 6. Disk limits

A pre-flight check, before any file is touched: sum the byte size of every
file about to be snapshotted and compare against `disk_free_space()` on the
snapshot's target volume, refusing to *start* the update (not aborting
partway through) if free space is under a safety margin sized for both the
snapshot and the incoming package's own files. No compression and no
configurable retention count for this first design — both are additional
complexity with no concrete requirement driving them yet.

### 7. Security

Snapshots live outside any web-servable path — `system/storage/mintcart/backup/<package>/`,
mirroring where OpenCart already keeps its own non-servable files under
`system/storage` — so a snapshot of a file like `config.php` can never be
fetched directly over HTTP. Triggering a rollback requires whatever admin
permission already gates Update Manager/Extensions; this is not a new
permission group.

## Consequences

- A future implementation increment needs one new Mint-owned table (working
  name `mc_installed_file`; InnoDB/utf8mb4 per `CLAUDE.md` §12) added through
  the Migration Manager, plus the optional `removes` field on
  `PackageManifest`. Neither is added by this ADR — this document exists so
  that increment doesn't have to re-derive these seven answers from
  scratch, per Functional Scope §21's own gate.
- Rollback is confirmed to be its own action/service layered on top of the
  Update Manager's `files` step, not a step inside the main Update Manager
  flow itself — consistent with Functional Scope §21 calling it out as a
  separate function from the general backup step.
- None of these seven answers rose to "a real product or architectural
  fork that only the project owner can make" (`CLAUDE.md` §20) — each
  follows directly from constraints already stated in the canonical docs
  (no mandatory background daemon, no automatic DB rollback promise, no
  API ahead of a real consumer), so none were escalated for a decision.
- `docs/IMPLEMENTATION_STATUS.md`'s Phase 11 (Technical Plan) section
  records this research as done, with the rollback checklist item still
  unchecked (design only, no code) and this ADR linked as the next
  increment's starting point.
