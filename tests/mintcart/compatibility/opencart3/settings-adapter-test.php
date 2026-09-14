<?php
/**
 * Standalone smoke test for MintCart\Compatibility\OpenCart3\SettingsAdapter.
 *
 * No PHPUnit/Composer in the repo yet, no MySQL server available in this
 * environment (same pre-existing gap as every other test in this repo).
 * SettingsAdapter's constructor is deliberately duck-typed rather than
 * bound to OpenCart's real ModelSettingSetting (see the class's own
 * docblock), so this exercises it against a minimal in-memory double —
 * the same "duck-typed OpenCart dependency" shape
 * tests/mintcart/core/migration-manager-test.php already established for
 * \DB.
 *
 * Run: php tests/mintcart/compatibility/opencart3/settings-adapter-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../../upload/system/library/mintcart/compatibility/opencart3/settingsadapter.php';

use MintCart\Compatibility\OpenCart3\SettingsAdapter;

/**
 * Duck-types OpenCart's ModelSettingSetting: getSetting()/editSetting()
 * only, backed by a plain in-memory array keyed by setting code.
 *
 * editSetting() intentionally replaces the whole row for $code, mirroring
 * OpenCart's real behavior (DELETE-then-reinsert) — this is exactly the
 * trap SettingsAdapter::merge() exists to guard callers against, so the
 * double must reproduce it faithfully or the test would prove nothing.
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

// 1. get() on a never-saved code returns an empty array, not an error.
$model = new FakeSettingModel();
$adapter = new SettingsAdapter($model);
assertSame([], $adapter->get('config'), 'get: unsaved code returns empty array', $failures);

// 2. merge() on a never-saved code saves exactly what was passed in.
$merged1 = $adapter->merge('config', ['config_name' => 'My Store']);
assertSame(['config_name' => 'My Store'], $merged1, 'merge: first save returns exactly what was passed', $failures);
assertSame(['config_name' => 'My Store'], $model->storage['config'], 'merge: first save persists exactly what was passed', $failures);

// 3. merge() on an already-populated code preserves existing keys the new
//    values don't mention — the whole point of this class over a bare
//    editSetting() call.
$model->storage['config'] = [
    'config_name'      => 'My Store',
    'config_email'     => 'store@example.com',
    'config_telephone' => '555-1234',
];
$merged2 = $adapter->merge('config', ['config_name' => 'Renamed Store']);
assertSame(
    [
        'config_name'      => 'Renamed Store',
        'config_email'     => 'store@example.com',
        'config_telephone' => '555-1234',
    ],
    $merged2,
    'merge: updates the targeted key while preserving every other existing key',
    $failures
);
assertSame($merged2, $model->storage['config'], 'merge: the merged result is what actually got saved', $failures);

// 4. merge() always calls editSetting() with the merged result, never the
//    raw partial input — guards against a future regression that passes
//    $values straight through instead of $merged.
$model2 = new FakeSettingModel();
$model2->storage['mintcart_contacts'] = ['mintcart_contacts_social' => [['network' => 'facebook', 'href' => 'https://facebook.com/x']]];
$adapter2 = new SettingsAdapter($model2);
$adapter2->merge('mintcart_contacts', ['mintcart_contacts_extra' => 'value']);
assertSame(1, count($model2->editCalls), 'merge: editSetting() called exactly once', $failures);
assertTrue(
    isset($model2->editCalls[0]['data']['mintcart_contacts_social']) && isset($model2->editCalls[0]['data']['mintcart_contacts_extra']),
    'merge: editSetting() receives the merged data, not just the new values',
    $failures
);

// 5. value() returns a single key out of a saved setting.
$model3 = new FakeSettingModel();
$model3->storage['config'] = ['config_name' => 'My Store'];
$adapter3 = new SettingsAdapter($model3);
assertSame('My Store', $adapter3->value('config', 'config_name'), 'value: reads an existing key', $failures);

// 6. value() returns the given default for a missing key or an unsaved code.
assertSame(null, $adapter3->value('config', 'config_email'), 'value: missing key defaults to null', $failures);
assertSame('fallback@example.com', $adapter3->value('config', 'config_email', 'fallback@example.com'), 'value: missing key uses the given default', $failures);
assertSame('n/a', $adapter3->value('never_saved_code', 'anything', 'n/a'), 'value: unsaved code uses the given default', $failures);

// 7. get() never mutates the underlying model's storage — a caller
//    mutating the returned array must not silently affect what's saved.
$model4 = new FakeSettingModel();
$model4->storage['config'] = ['config_name' => 'My Store'];
$adapter4 = new SettingsAdapter($model4);
$read = $adapter4->get('config');
$read['config_name'] = 'Mutated Locally';
assertSame('My Store', $model4->storage['config']['config_name'], 'get: returned array is a copy, mutating it does not affect storage', $failures);

if ($failures) {
    fwrite(STDERR, count($failures) . " failure(s):\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "- {$failure}\n");
    }
    exit(1);
}

echo "PASS: SettingsAdapter smoke test (7 scenarios)\n";
exit(0);
