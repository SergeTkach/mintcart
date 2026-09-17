<?php
namespace MintCart\Update;

use MintCart\Compatibility\OpenCart3\SettingsAdapter;

/**
 * The `maintenance` step of the Update Manager flow
 * (`MINTCART_TECHNICAL_PLAN.md` §33 / `MINTCART_IMPLEMENTATION_GUIDE.md`
 * §69):
 *
 * ```text
 * check → compatibility → backup → download → verify → maintenance →
 * files → migrations → cache clear → health check → done
 * ```
 *
 * `docs/developer/update/package-verifier.md`'s own "What's deliberately
 * not done yet" section names this step directly as one of the two
 * candidates for the flow's next increment ("`maintenance` mode (put the
 * store in maintenance before `files`/`migrations` run)"). This class is
 * that step — same "pure, OpenCart-independent primitive first" pattern
 * `PackageManifest`, `UpdateChecker`, `PackageVerifier` and `BackupPlanner`
 * already used, deliberately with no orchestrator caller yet (the flow has
 * no single entry point that runs `files`/`migrations` in sequence —
 * `CLAUDE.md` §85).
 *
 * Storage: OpenCart's own `config_maintenance` setting
 * (`oc_setting`, code `config`), already read by the storefront's
 * `ControllerStartupMaintenance` on every request. `CLAUDE.md` §39 forbids
 * a second, Mint-owned maintenance flag duplicating this — MintCart's
 * Update Manager toggles the exact same switch a store owner can already
 * flip by hand under `Settings → Store → Maintenance Mode`, through
 * `MintCart\Compatibility\OpenCart3\SettingsAdapter` (Phase 1, until now
 * built but never wired into a real call site — see that class's own
 * docblock) so the update flow never wipes the rest of the `config` code's
 * saved values the way a bare `editSetting()` would.
 *
 * `enable()` reports whether maintenance mode was already on *before* this
 * call so a future orchestrator can decide the flow's own end state
 * correctly: a store that was already in maintenance mode when an update
 * started should very likely still be in maintenance mode when it ends,
 * not silently reopened by `disable()` — that decision belongs to the
 * caller that knows the full flow's outcome, not to this class.
 */
final class MaintenanceMode
{
    private SettingsAdapter $settings;

    public function __construct(SettingsAdapter $settings)
    {
        $this->settings = $settings;
    }

    /**
     * True when the store is currently in maintenance mode.
     */
    public function isEnabled(): bool
    {
        return self::isTruthy($this->settings->value('config', 'config_maintenance', '0'));
    }

    /**
     * Turns maintenance mode on (idempotent — a no-op write when it is
     * already on, still returning the same true value).
     *
     * @return bool Whether maintenance mode was already enabled before
     *              this call, so a caller can later decide whether
     *              `disable()` is actually appropriate.
     */
    public function enable(): bool
    {
        $wasEnabled = $this->isEnabled();

        if (!$wasEnabled) {
            $this->settings->merge('config', ['config_maintenance' => '1']);
        }

        return $wasEnabled;
    }

    /**
     * Turns maintenance mode off (idempotent — a no-op write when it is
     * already off).
     */
    public function disable(): void
    {
        if ($this->isEnabled()) {
            $this->settings->merge('config', ['config_maintenance' => '0']);
        }
    }

    /**
     * OpenCart stores `config_maintenance` as the string `'1'`/`'0'` (see
     * `upload/install/opencart.sql`), but tolerate a real bool/int too —
     * a caller may have already coerced the raw setting value before this
     * class sees it (or a future non-OpenCart-3 adapter might).
     */
    private static function isTruthy($value): bool
    {
        return $value === true || $value === 1 || $value === '1';
    }
}
