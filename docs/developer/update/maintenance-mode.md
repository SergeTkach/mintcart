# Update Manager — Maintenance Mode

`MintCart\Update\MaintenanceMode`
(`upload/system/library/mintcart/update/maintenancemode.php`)

## What this is

The `maintenance` step of the Update Manager flow described in
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
done yet" section named this step directly as one of the two candidates for
the flow's next increment. `MaintenanceMode` is that step — same "pure,
primitive first" pattern `PackageManifest`, `UpdateChecker`,
`PackageVerifier` and `BackupPlanner` already used: build the checkable/
toggleable primitive before there is a real orchestrator that runs
`files`/`migrations` in sequence and needs to wrap them in maintenance mode
(`CLAUDE.md` §85 — no API ahead of a real consumer).

## No second maintenance flag

`CLAUDE.md` §39 ("Не дублювати OpenCart DB") forbids a second, Mint-owned
maintenance switch. OpenCart 3 already has one: the `config_maintenance`
setting (`oc_setting`, code `config`), which
`ControllerStartupMaintenance` reads on every storefront request and which
a store owner can already flip by hand under
`Settings → Store → Maintenance Mode`. `MaintenanceMode` toggles that exact
same setting through
`MintCart\Compatibility\OpenCart3\SettingsAdapter` (Phase 1) — this is that
adapter's first real, wired-up consumer; it existed since Phase 1 but, per
its own docblock, had no call site that wasn't already contended by another
open PR until now. Going through `SettingsAdapter::merge()` rather than a
bare `editSetting('config', ['config_maintenance' => '1'])` matters here for
the same reason it matters everywhere else in this codebase: a bare
`editSetting()` call would delete every other `config_*` key the store
already has saved (store name, email, telephone, ...).

## API

```php
$maintenance = new MaintenanceMode($settingsAdapter);

$maintenance->isEnabled();   // bool — current state
$wasEnabled = $maintenance->enable();  // bool — state BEFORE this call
$maintenance->disable();
```

- `isEnabled()` reads `config_maintenance` and treats OpenCart's own
  stored string `'1'`/`'0'` (see `upload/install/opencart.sql`) the same as
  a real bool/int `true`/`1`, so a caller that already coerced the raw
  setting value (or a future non-OpenCart-3 adapter) is not misread as
  disabled.
- `enable()`/`disable()` are both idempotent — calling either while already
  in that state makes no further write.
- `enable()` deliberately returns whether the store was **already** in
  maintenance mode before the call, not simply "success." A future
  orchestrator running `files`/`migrations` needs this to decide the
  flow's own end state correctly: a store a site owner had already put
  into maintenance mode by hand before starting an update should very
  likely still be in maintenance mode when the update finishes, not
  silently reopened by an unconditional `disable()` at the end. That
  decision belongs to the orchestrator that knows the whole flow's
  outcome (including whether the `health check` step passed), not to this
  primitive — so `MaintenanceMode` only reports the fact, it does not make
  the call.

## What's deliberately not done yet

- ~~**No orchestrator.**~~ **Done** — `MintCart\Update\UpdateOrchestrator`
  (`docs/developer/update/update-orchestrator.md`) now calls `enable()`/
  `disable()` in sequence around `files`/`migrations`/`cache clear`/
  `health check`, and answers the boundary questions this note used to
  leave open: maintenance stays enabled through every step after it, a
  failure anywhere after `enable()` leaves the store in maintenance mode
  rather than reopening it, and `disable()` only runs on a full success
  when the store was not already in maintenance mode before `apply()` was
  called.
- **No admin UI toggle beyond the one OpenCart already ships**
  (`Settings → Store → Maintenance Mode`). Nothing here needs one — the
  Update Manager flow toggles the same setting programmatically.
- **No maintenance page customization.** `ControllerStartupMaintenance`'s
  own `common/maintenance` route/template is untouched; MintCart does not
  need a Mint-branded maintenance page for this increment.

Verified: `php -l` clean.
`tests/mintcart/update/maintenance-mode-test.php` (8 scenarios — unsaved
setting defaults to disabled, `enable()` persists `config_maintenance =
"1"` and reports the prior `false` state, a second `enable()` call is a
no-op write reporting the prior `true` state, `disable()` persists `"0"`
and is likewise idempotent, both `enable()`/`disable()` preserve every
other existing `config_*` key via `SettingsAdapter::merge()`, and
`isEnabled()` treats a real bool/int the same as OpenCart's stored `"1"`/
`"0"` strings). Reuses the exact same in-memory `FakeSettingModel` double
`tests/mintcart/compatibility/opencart3/settings-adapter-test.php`
established for `SettingsAdapter`'s own duck-typed OpenCart dependency.
Pure PHP, no OpenCart dependency beyond the already-tested
`SettingsAdapter`, so no live-install verification gap applies to this
class itself — there is no real orchestrator yet for it to run against on
a live install (same caveat every other Update Manager primitive in this
codebase carries).
