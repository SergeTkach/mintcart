# OpenCart 3 Compatibility Adapter — `SettingsAdapter`

`MintCart\Compatibility\OpenCart3\SettingsAdapter`
(`upload/system/library/mintcart/compatibility/opencart3/settingsadapter.php`)

`MINTCART_TECHNICAL_PLAN.md` §5 (`MintCart\Compatibility\OpenCart3\*`
adapter layer, Phase 1 foundation), `MINTCART_IMPLEMENTATION_GUIDE.md`
§38.

## What this is

The first `MintCart\Compatibility\OpenCart3\*` adapter — Phase 1's own
remaining checklist item, unstarted until now. Wraps OpenCart 3's
`ModelSettingSetting` (`getSetting()`/`editSetting()`) so MintCart code
reads/writes OpenCart settings through one small, unit-tested class
instead of each caller re-deriving the same merge-then-save fix by hand.

Not the same thing as `MintCart\Compatibility\InstallManifestParser` /
`CompatibilityChecker` (Technical Plan §39/Functional Scope §29, a
different open increment at the time of writing) — those analyze a
**third-party OCMOD extension's** `install.xml`. This adapter has nothing
to do with OCMOD; it wraps **OpenCart's own settings storage** so MintCart
code never calls `ModelSettingSetting` directly.

## Why this exists — a bug pattern already written out three times

`ModelSettingSetting::editSetting($code, $data)` **DELETEs every existing
row for `$code`** before reinserting only the keys `$data` contains. A
caller that wants to change just one field — e.g. flip a shipping
extension's enabled flag, or save one new social link — but calls
`editSetting($code, $newFieldOnly)` directly silently wipes every other
setting already saved under that code.

This project has already hit and fixed this the same way, independently,
three times:

1. `ControllerMintcartWizard::saveStoreStep()` (Phase 13, **merged to
   `master`**) — reads `$this->config->get('config_*')`, merges in the
   four fields the Store step collects, then `editSetting('config',
   $merged)`. Its own doc-comment calls this out explicitly as "never a
   bare `editSetting('config', $partialData)`".
2. The Setup Wizard's Contacts step (`saveContactsStep()` in the same
   file) — same merge-then-save shape for `mintcart_contacts`, written as
   a separate open increment at the time of this writing.
3. `Business → Delivery`/`Business → Payments`'s toggle actions
   (`upload/admin/controller/mintcart/business/{delivery,payments}.php`)
   — same shape again for each shipping/payment extension's own setting
   code, also a separate open increment at the time of this writing, whose
   own doc explicitly says "same trap `ControllerMintcartWizard::saveStoreStep()`
   ... already document[s]".

Three independent, correct fixes for the same underlying trap is exactly
the kind of duplication `MINTCART_TECHNICAL_PLAN.md` §15C/§38E asks to be
extracted into one reusable piece once it's proven itself more than once
— not before (`CLAUDE.md` §85). `SettingsAdapter` is that piece.

## API

```php
$adapter = new \MintCart\Compatibility\OpenCart3\SettingsAdapter($this->model_setting_setting);

$adapter->get('config');                          // array<string, mixed> — [] if never saved
$adapter->value('config', 'config_name');         // single key, null if missing
$adapter->value('config', 'config_name', 'Shop'); // single key, with a default
$adapter->merge('config', ['config_name' => 'x']); // merge-then-save; returns the merged result
```

`get()`/`value()` never throw for a setting code that's never been saved —
same "not configured yet is a valid state" behavior every direct
`getSetting()` caller in this codebase already relies on (Wizard's
`loadContactsSocial()`, Store Readiness's signal checks).

`merge()` is the one that matters: it always reads the current value with
`get()` first, `array_merge()`s the new values on top, and saves the
*merged* result — so an existing key `$values` doesn't mention survives
untouched, and a key it does mention overwrites the old value. It returns
the merged result actually saved, so a caller doesn't need a second
`get()` call to see what's now stored.

## Why duck-typed, not type-hinted against `\ModelSettingSetting`

OpenCart's model classes are created dynamically by its own
`Loader`/`Registry` machinery — there is no `class ModelSettingSetting`
declaration anywhere to `use`/autoload outside a fully booted OpenCart
request. No other `MintCart\*` class in this codebase type-hints an
OpenCart model or `Registry` member directly for the same reason
(`ProductViewProvider`, `FooterViewProvider`, `StoreReadiness` all take
plain resolved values, never a live OpenCart object). `SettingsAdapter`'s
constructor instead accepts any `object` exposing `getSetting(string):
array` and `editSetting(string, array): void` — in production that's
`$this->model_setting_setting` after `$this->load->model('setting/setting')`;
in `tests/mintcart/compatibility/opencart3/settings-adapter-test.php` it's
a minimal in-memory double (`FakeSettingModel`) that faithfully reproduces
the real DELETE-then-reinsert `editSetting()` semantics — the same
"duck-typed OpenCart dependency" testing shape
`tests/mintcart/core/migration-manager-test.php` already established for
`\DB`.

## Not done yet, by design

**First real consumer: `MintCart\Update\MaintenanceMode`** (Phase 11, the
Update Manager's `maintenance` step —
`docs/developer/update/maintenance-mode.md`), which toggles
`config_maintenance` through `merge()` instead of a bare `editSetting()`
call. The three call sites named below still duplicate the same
merge-then-save fix inline rather than going through this class; that
remains open, unclaimed follow-up work.

**Not wired into any of the three call sites named below.** All three
live in files this routine's own duplicate-work check (`docs/IMPLEMENTATION_STATUS.md`'s
"Next step" process) found already contended by other open pull requests
at the time this was written:

- `upload/admin/controller/mintcart/wizard.php` +
  `upload/system/library/mintcart/onboarding/wizardprogress.php` — two
  open PRs (Contacts step, Language & Currency step) both modify these
  files independently; a third simultaneous edit would only add to the
  reconciliation a human already has to do between those two.
- `upload/admin/controller/mintcart/business/delivery.php` /
  `payments.php` — these files don't exist on `master` yet at all; they
  ship as part of the open Business Delivery/Payments PR. There is
  nothing to refactor here until that PR merges.

Routing `saveStoreStep()` (the one call site that **is** already on
`master`) through `SettingsAdapter` is the natural, self-contained
follow-up once `wizard.php` stops being contended by the two open Wizard
PRs — swap its manual `$this->config->get(...)` read +
`array_merge()` + `$this->model_setting_setting->editSetting(...)` calls
for `$adapter->merge('config', [...])`, with no behavior change (same
inputs, same outputs, now backed by a unit-tested class instead of an
inline comment explaining the same trap). The Delivery/Payments toggle
actions and the Contacts step's `saveContactsStep()`/`loadContactsSocial()`
are the same kind of follow-up once those PRs land.

**No second adapter yet** (`ProductAdapter`/`CategoryAdapter`/
`OrderAdapter`/`CustomerAdapter`/`ExtensionAdapter` per Technical Plan
§5's full list) — `SettingsAdapter` is the first because it has the most
already-duplicated real call sites to point at; the others wait for their
own real consumer, per `CLAUDE.md` §85.

## Verified

- `php -l` clean on the new class and test file, and on the full
  `upload/`/`tests/` tree (re-swept before finishing this increment).
- `tests/mintcart/compatibility/opencart3/settings-adapter-test.php` (7
  scenarios: an unsaved code returns `[]`; a first `merge()` on an unsaved
  code saves exactly what was passed; `merge()` on an already-populated
  code preserves every existing key it doesn't mention while overwriting
  the one(s) it does; `editSetting()` is called exactly once per `merge()`
  call, with the *merged* data, not the raw partial input; `value()`
  reads a single key; `value()` falls back to the given default for a
  missing key or an entirely unsaved code; `get()`'s returned array is a
  copy — mutating it locally does not affect the underlying double's
  storage).
- Full `tests/mintcart/**/*-test.php` suite re-run end to end (36 files,
  was 35) — all passing, no regressions; this increment added a new file
  and touched no existing PHP, so no other test could have been affected.

**Not verified — same pre-existing gap as every other increment in this
repo:** no live MySQL/OpenCart admin session in this environment to run
this adapter against the real `ModelSettingSetting`. Since it is not
wired into any controller yet, there is nothing end-to-end to exercise on
a real install until the follow-up above lands; the class itself is
fully covered by the unit test above against a faithful in-memory double.
