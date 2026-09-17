<?php
/**
 * Standalone smoke test for MintCart\Update\MaintenanceMode.
 *
 * No PHPUnit/Composer in the repo yet, no MySQL server available in this
 * environment (same pre-existing gap as every other test in this repo).
 * MaintenanceMode depends only on SettingsAdapter, which is itself
 * duck-typed against OpenCart's ModelSettingSetting rather than bound to
 * it directly (see SettingsAdapter's own docblock) — this test reuses the
 * exact same minimal in-memory double
 * tests/mintcart/compatibility/opencart3/settings-adapter-test.php
 * already established for it.
 *
 * Run: php tests/mintcart/update/maintenance-mode-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/compatibility/opencart3/settingsadapter.php';
require __DIR__ . '/../../../upload/system/library/mintcart/update/maintenancemode.php';

use MintCart\Compatibility\OpenCart3\SettingsAdapter;
use MintCart\Update\MaintenanceMode;

/**
 * Duck-types OpenCart's ModelSettingSetting: getSetting()/editSetting()
 * only, backed by a plain in-memory array keyed by setting code. Mirrors
 * settings-adapter-test.php's FakeSettingModel exactly — editSetting()
 * replaces the whole row for $code, the real DELETE-then-reinsert
 * behavior SettingsAdapter::merge() exists to guard against.
 */
final class FakeSettingModel
{
    /** @var array<string, array<string, mixed>> */
    public array $storage = [];

    /** @var array<int, array{code: string, data: array<string, mixed>}> */
    public array $editCalls = [];

    public function getSetting(string $code): array
    {
        return $this->storage[$code] ?? [];
    }

    public function editSetting(string $code, array $data): void
    {
        $this->editCalls[] = ['code' => $code, 'data' => $data];
        $this->storage[$code] = $data;
    }
}

$failures = [];

function assertTrue(bool $condition, string $message, array &$failures): void
{
    if (!$condition) {
        $failures[] = $message;
    }
}

function assertSame($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

// 1. isEnabled() on a fresh install (config_maintenance never saved) is false.
$model = new FakeSettingModel();
$maintenance = new MaintenanceMode(new SettingsAdapter($model));
assertTrue($maintenance->isEnabled() === false, 'isEnabled: unsaved setting defaults to false', $failures);

// 2. enable() turns maintenance on and reports it was previously off.
$wasEnabled = $maintenance->enable();
assertSame(false, $wasEnabled, 'enable: reports the store was NOT already in maintenance mode', $failures);
assertTrue($maintenance->isEnabled(), 'enable: isEnabled() now reports true', $failures);
assertSame('1', $model->storage['config']['config_maintenance'], 'enable: persists config_maintenance = "1", the exact string OpenCart expects', $failures);

// 3. enable() is idempotent: calling it again while already on makes no
//    further write and still reports the pre-call state truthfully (true
//    this time).
$editCallsBefore = count($model->editCalls);
$wasEnabledAgain = $maintenance->enable();
assertSame(true, $wasEnabledAgain, 'enable: second call reports the store was already in maintenance mode', $failures);
assertSame($editCallsBefore, count($model->editCalls), 'enable: idempotent — no write when already enabled', $failures);

// 4. disable() turns maintenance off.
$maintenance->disable();
assertTrue($maintenance->isEnabled() === false, 'disable: isEnabled() now reports false', $failures);
assertSame('0', $model->storage['config']['config_maintenance'], 'disable: persists config_maintenance = "0"', $failures);

// 5. disable() is idempotent: calling it again while already off makes no
//    further write.
$editCallsBeforeDisable = count($model->editCalls);
$maintenance->disable();
assertSame($editCallsBeforeDisable, count($model->editCalls), 'disable: idempotent — no write when already disabled', $failures);

// 6. enable()/disable() preserve every other config_* key already saved —
//    the entire reason this class goes through SettingsAdapter::merge()
//    instead of a bare editSetting() call.
$model2 = new FakeSettingModel();
$model2->storage['config'] = [
    'config_name'  => 'My Store',
    'config_email' => 'store@example.com',
];
$maintenance2 = new MaintenanceMode(new SettingsAdapter($model2));
$maintenance2->enable();
assertSame(
    [
        'config_name'         => 'My Store',
        'config_email'        => 'store@example.com',
        'config_maintenance'  => '1',
    ],
    $model2->storage['config'],
    'enable: preserves every other existing config_* key',
    $failures
);
$maintenance2->disable();
assertSame(
    [
        'config_name'         => 'My Store',
        'config_email'        => 'store@example.com',
        'config_maintenance'  => '0',
    ],
    $model2->storage['config'],
    'disable: preserves every other existing config_* key',
    $failures
);

// 7. isEnabled() treats a real bool/int true the same as the string "1"
//    (a future non-OpenCart-3 adapter, or a caller that already coerced
//    the raw setting value, should not silently read as disabled).
$model3 = new FakeSettingModel();
$model3->storage['config'] = ['config_maintenance' => true];
$maintenance3 = new MaintenanceMode(new SettingsAdapter($model3));
assertTrue($maintenance3->isEnabled(), 'isEnabled: treats a real bool true as enabled, not just the string "1"', $failures);

$model4 = new FakeSettingModel();
$model4->storage['config'] = ['config_maintenance' => 1];
$maintenance4 = new MaintenanceMode(new SettingsAdapter($model4));
assertTrue($maintenance4->isEnabled(), 'isEnabled: treats the int 1 as enabled', $failures);

// 8. isEnabled() treats "0" (OpenCart's actual stored default, per
//    upload/install/opencart.sql) as disabled, not truthy-by-non-empty-string.
$model5 = new FakeSettingModel();
$model5->storage['config'] = ['config_maintenance' => '0'];
$maintenance5 = new MaintenanceMode(new SettingsAdapter($model5));
assertTrue($maintenance5->isEnabled() === false, 'isEnabled: the string "0" is treated as disabled', $failures);

if ($failures) {
    fwrite(STDERR, count($failures) . " failure(s):\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "- {$failure}\n");
    }
    exit(1);
}

echo "PASS: MaintenanceMode smoke test (8 scenarios)\n";
exit(0);
