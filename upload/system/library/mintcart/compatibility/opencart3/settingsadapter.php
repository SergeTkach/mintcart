<?php

declare(strict_types=1);

namespace MintCart\Compatibility\OpenCart3;

/**
 * Adapter over OpenCart 3's `ModelSettingSetting` (`Technical Plan` §5:
 * `MintCart\Compatibility\OpenCart3\SettingsAdapter` is the first-named
 * candidate in that list, "when Business Settings/Setup Wizard starts" —
 * both now have a real, merged consumer, see below).
 *
 * `ModelSettingSetting::editSetting($code, $data)` DELETEs every existing
 * row for `$code` before reinserting only the keys `$data` contains — a
 * bare `editSetting('config', ['config_name' => 'x'])` silently wipes
 * every other `config_*` key the store already had saved. This exact trap,
 * and the merge-then-save fix for it (`getSetting()` first, `array_merge()`
 * the new values in, `editSetting()` the merged result), has already been
 * independently written out and documented three times in this codebase:
 * `ControllerMintcartWizard::saveStoreStep()` (Phase 13, merged), and the
 * same pattern again in the Setup Wizard's Contacts step and the Business
 * → Delivery/Payments toggle actions (both in flight as of this writing —
 * see `docs/developer/compatibility/settings-adapter.md` for exact
 * references). `SettingsAdapter` exists to make that fix a single,
 * unit-tested piece of code instead of a hand-copied comment.
 *
 * Deliberately duck-typed rather than type-hinted against
 * `\ModelSettingSetting` in the constructor: OpenCart's model classes are
 * created dynamically by its `Loader`/`Registry` (there is no
 * `class ModelSettingSetting` to `use`/autoload outside a booted OpenCart
 * request), the same reason no other `MintCart\*` class in this codebase
 * type-hints an OpenCart model or `Registry` member directly. Any object
 * exposing `getSetting(string $code): array` and
 * `editSetting(string $code, array $data): void` works — in production
 * that is `$this->model_setting_setting` after
 * `$this->load->model('setting/setting')`; in
 * `tests/mintcart/compatibility/opencart3/settings-adapter-test.php` it is
 * a minimal in-memory double, the same "duck-typed OpenCart dependency"
 * testing shape `tests/mintcart/core/migration-manager-test.php`
 * established for `\DB`.
 *
 * First real consumer: `MintCart\Update\MaintenanceMode` (Phase 11, the
 * Update Manager's `maintenance` step —
 * `docs/developer/update/maintenance-mode.md`), which toggles OpenCart's
 * own `config_maintenance` setting through `merge()` rather than a bare
 * `editSetting()` call. The three call sites named above
 * (`saveStoreStep()`, the Setup Wizard's Contacts step, the Business →
 * Delivery/Payments toggle actions) still duplicate the same
 * merge-then-save fix inline rather than going through this class —
 * routing each through `SettingsAdapter` remains a self-contained,
 * behavior-preserving follow-up — see
 * `docs/developer/compatibility/settings-adapter.md`'s "Not done yet".
 */
final class SettingsAdapter
{
    /**
     * @var object An object exposing getSetting(string): array and
     *             editSetting(string, array): void — in production,
     *             OpenCart's ModelSettingSetting.
     */
    private object $settingModel;

    public function __construct(object $settingModel)
    {
        $this->settingModel = $settingModel;
    }

    /**
     * All values currently saved under `$code`, or an empty array if
     * nothing has ever been saved under it yet (a brand-new install, or a
     * setting code no one has written to). Never throws for a missing
     * code — the same "not configured yet is a valid state" behavior
     * every direct `getSetting()` caller in this codebase already relies
     * on (Wizard's `loadContactsSocial()`, Store Readiness's signal
     * checks).
     *
     * @return array<string, mixed>
     */
    public function get(string $code): array
    {
        $setting = $this->settingModel->getSetting($code);

        return is_array($setting) ? $setting : [];
    }

    /**
     * Reads the current value of `$code`, merges `$values` on top of it,
     * and saves the merged result — never a bare `editSetting($code,
     * $values)`, which would delete every key `$values` doesn't mention.
     * Existing keys `$values` doesn't touch are preserved; keys `$values`
     * does set overwrite the previous value, same `array_merge()`
     * semantics `saveStoreStep()` already documents for `config`.
     *
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed> The merged result actually saved.
     */
    public function merge(string $code, array $values): array
    {
        $merged = array_merge($this->get($code), $values);

        $this->settingModel->editSetting($code, $merged);

        return $merged;
    }

    /**
     * A single value out of `$code`'s saved settings, or `$default` if
     * either the code has never been saved or that particular key isn't
     * in it. Convenience for the common "read one field" case
     * (`saveStoreStep()`'s own `config_name`/`config_email`/... reads)
     * without every caller re-writing the same `$setting[$key] ?? $default`
     * check.
     */
    public function value(string $code, string $key, $default = null)
    {
        $setting = $this->get($code);

        return $setting[$key] ?? $default;
    }
}
