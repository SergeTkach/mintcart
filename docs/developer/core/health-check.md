# `MintCart\Core\HealthCheck`

Foundation-level "is this MintCart install healthy right now" evaluator
(`MINTCART_TECHNICAL_PLAN.md` §4 "health checks" under MintCart Core, §33's
Update Manager flow ending in a health-check step before "done";
`CLAUDE.md` §21's starting sequence).

PHP: `upload/system/library/mintcart/core/healthcheck.php`
(class `MintCart\Core\HealthCheck`)

Test: `tests/mintcart/core/health-check-test.php`
(`php tests/mintcart/core/health-check-test.php`)

Consumer: `upload/admin/controller/mintcart/health.php`
(`ControllerMintcartHealth`, route `mintcart/health`, GET, JSON)

## Design

`HealthCheck` has **no OpenCart dependency** — no `\Registry`, filesystem
calls, or `\DB` — same "resolved values in, normalized contract out" shape
as `MintCart\Onboarding\StoreReadiness`/`CartService`/`ProductViewProvider`.
It takes an array of already-resolved signals and returns:

```php
[
    'status' => 'ok',              // worst of every individual check: ok < warning < unknown < error
    'checks' => [
        ['key' => 'php_version', 'label' => 'PHP version', 'status' => 'ok', 'detail' => '8.2.10'],
        ['key' => 'extension_mysqli', 'label' => 'PHP extension: mysqli', 'status' => 'ok', 'detail' => 'loaded'],
        // ... one per MintCart\Core\HealthCheck::REQUIRED_EXTENSIONS
        ['key' => 'writable_storage_logs', 'label' => 'Writable: storage/logs/', 'status' => 'ok', 'detail' => 'writable'],
        // ... one per MintCart\Core\HealthCheck::REQUIRED_WRITABLE_PATHS
        ['key' => 'migrations', 'label' => 'Database migrations', 'status' => 'ok', 'detail' => 'up to date'],
        ['key' => 'mint_version', 'label' => 'MintCart version', 'status' => 'ok', 'detail' => '0.1.0-dev (OpenCart 3.0.5.1 baseline)'],
    ],
]
```

### Why a missing signal is `'unknown'`, not `'ok'` — the opposite of `StoreReadiness`

`StoreReadiness` treats a missing signal as "not ready yet" (`false`) — a
completely normal state during onboarding, before every setting exists.
`HealthCheck` treats a missing signal as **`'unknown'`, never `'ok'`**: a
health check that quietly reports "healthy" because a signal wasn't
actually supplied would be worse than not having the check at all. The
worst-status rule (`ok < warning < unknown < error`) means a stray missing
signal surfaces as a visibly incomplete report, not a false "all clear."

### Why pending migrations are `'warning'`, not `'error'`

A store mid-update (files deployed, migrations not run yet) is an expected,
transient state, not a broken one — flagging it at the same severity as a
missing PHP extension would train an operator to ignore the check. Any
`'error'` elsewhere still outranks a `'warning'` for the overall `status`
(covered by test scenario 6b).

## Where each signal actually comes from

`HealthCheck::evaluate()` itself doesn't know or care — that's
`ModelMintcartHealth::getSignals()`'s job
(`upload/admin/model/mintcart/health.php`):

| Signal | Source |
| --- | --- |
| `php_version` | `phpversion()` |
| `extensions[<name>]` | `extension_loaded($name)`, one per `HealthCheck::REQUIRED_EXTENSIONS` |
| `writable[<key>]` | `is_writable()`, one per `HealthCheck::REQUIRED_WRITABLE_PATHS` |
| `pending_migrations` | `count((new MigrationManager(...))->pending())` |
| `mint_version` / `opencart_baseline` | `MintCart\Core\Version::VERSION` / `::OPENCART_BASELINE` |

**`REQUIRED_EXTENSIONS`/`REQUIRED_WRITABLE_PATHS` are not separately
invented lists** — both are read directly from
`upload/install/controller/install/step_2.php` (OpenCart's own installer
requirement check) before writing this class, per `CLAUDE.md` §10/§84, and
live as public constants on `HealthCheck` itself so `ModelMintcartHealth`
can iterate them instead of duplicating the list a second time. `pdo`/
`pgsql` (present in the installer's own DB-driver check) are intentionally
omitted — MintCart's own `\DB\MySQLi` (`system/library/db/mysqli.php`) only
ever uses `mysqli`, so requiring the other two would be a stricter gate
than this codebase actually needs.

`MIN_PHP_VERSION` (`8.1.0`) mirrors `upload/system/startup.php`'s own hard
version gate (`version_compare(phpversion(), '8.1.0', '<')`, which the
whole install already refuses to boot below) — not a separately chosen
threshold, so this check can never disagree with what the running install
already enforces just to start.

## A latent `MigrationManager` bug this surfaced, and its fix

`HealthCheck` is the **first caller that asks `MigrationManager::pending()`
before `run()` has ever been called** — every earlier caller (the CI test
suite, this repo's own `mc_page`/`mc_block` migrations) always called
`run()` first, which happened to call `ensureSchema()` before anything else
touched the tracking table. On a real `\DB\MySQLi`
(`mysqli_report(MYSQLI_REPORT_ERROR)` is enabled — see
`system/library/db/mysqli.php`), `applied()`'s `SELECT` against a table
that has never been `CREATE`d throws instead of returning zero rows — which
is exactly what a fresh MintCart install looks like before its first
migration ever runs.

Fixed by having `applied()` call `ensureSchema()` itself, so any caller —
not just `run()` — can safely ask `pending()`/`applied()` at any time.
`ensureSchema()` now memoizes per `MigrationManager` instance
(`$schemaEnsured`) so this doesn't add a second `CREATE TABLE IF NOT
EXISTS` query to every `run()` (which itself calls `applied()` twice: once
directly for `skipped`, once again inside `pending()`). Covered by a new
scenario in `tests/mintcart/core/migration-manager-test.php` using a
`FakeDb` that now actually throws on `SELECT` against a table it was never
told to `CREATE` — the previous double silently answered either way,
which is why this gap wasn't caught until a real second caller existed.

No OpenCart core file is touched by this — `MigrationManager` is
MintCart's own file, so this isn't an `docs/upstream-diff.md` entry.

## Why a new controller/route instead of a Diagnostics screen directly

`mintcart/health` is a plain read-only JSON endpoint, not a rendered
dashboard panel — same reasoning `docs/developer/onboarding/store-readiness.md`
gives for `mintcart/readiness`. Per `CLAUDE.md` §85 ("API appears when
there's a real consumer, not before"), this endpoint is itself the first
real consumer of `HealthCheck` — the smallest self-contained slice that
proves the service end-to-end without also deciding a Diagnostics screen's
markup/CSS. A Diagnostics screen (Technical Plan §35) and the future Update
Manager's own final "health check" step (Technical Plan §33) are the
expected next callers of this same JSON contract, not a second copy of the
checks.

The `mintcart/health` permission needs no separate registration —
`admin/controller/user/user_permission.php` auto-discovers every file
under `admin/controller/*` by its relative path (same mechanism
`mintcart/readiness` already relies on), so an admin user group simply
needs "MintCart Health" checked once a human grants it.

## Verified / not verified

- `php -l` clean on every new/modified file.
- `tests/mintcart/core/health-check-test.php` (12 assertions across 8
  scenarios: empty signals → every check `unknown`/overall `unknown`;
  fully healthy signals → `ok`; PHP below/at the minimum; a missing
  extension; an unwritable path; pending migrations degrading to
  `warning` not `error`; an error outranking a warning for the overall
  status; label overrides; the `mint_version` detail including the
  OpenCart baseline).
- `tests/mintcart/core/migration-manager-test.php` re-run with a new
  scenario (`FakeDb` now throws on `SELECT` before any `CREATE TABLE`) —
  confirms `applied()`/`pending()` no longer throw when called before any
  `run()`, and the existing "tracking table created once" assertion still
  holds after the `ensureSchema()` memoization fix.
- Full `tests/mintcart/` suite re-run end to end (32 files) — all pass, no
  regressions.
- **Not verified** — same pre-existing gap as every other controller/model
  wiring increment in this repo: no live MySQL/OpenCart admin session in
  this environment. A human should hit `mintcart/health` on a real install
  (as a logged-in admin user with the auto-discovered `mintcart/health`
  permission) and confirm: all checks report `ok` on a normal install;
  deleting/chmod-ing a required writable directory flips its check to
  `error`; running `mc_block`/`mc_page`'s own migrations from a pending
  state flips `migrations` from `warning` to `ok`.
