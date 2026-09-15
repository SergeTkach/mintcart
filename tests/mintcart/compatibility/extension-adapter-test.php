<?php
/**
 * Standalone smoke test for
 * MintCart\Compatibility\OpenCart3\ExtensionAdapter.
 *
 * Both collaborators (OpenCart's `Config` object and the `setting/extension`
 * model proxy) are duck-typed fakes — no OpenCart bootstrap needed, same
 * style as every other tests/mintcart/**-test.php file (no PHPUnit/Composer
 * in this repo).
 *
 * Run: php tests/mintcart/compatibility/extension-adapter-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/compatibility/opencart3/extensionadapter.php';

use MintCart\Compatibility\OpenCart3\ExtensionAdapter;

final class FakeConfig
{
    private array $values;

    public function __construct(array $values)
    {
        $this->values = $values;
    }

    public function get(string $key)
    {
        return $this->values[$key] ?? null;
    }
}

final class FakeModelSettingExtension
{
    private array $installed;

    /** @param array<string, list<string>> $installed */
    public function __construct(array $installed)
    {
        $this->installed = $installed;
    }

    public function getInstalled(string $type): array
    {
        return $this->installed[$type] ?? [];
    }
}

$failures = [];

function assertSameExtensionAdapter($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

// 1. No extensions installed at all for the type — not enabled.
$adapter = new ExtensionAdapter(new FakeConfig([]), new FakeModelSettingExtension([]));
assertSameExtensionAdapter(false, $adapter->hasEnabled('shipping'), 'Scenario 1: no installed extensions is not enabled', $failures);

// 2. Installed but its own status flag is off — not enabled (the
//    installed-vs-enabled distinction this adapter exists to normalize).
$adapter = new ExtensionAdapter(
    new FakeConfig(['shipping_flat_status' => '0']),
    new FakeModelSettingExtension(['shipping' => ['flat']])
);
assertSameExtensionAdapter(false, $adapter->hasEnabled('shipping'), 'Scenario 2: installed but disabled is not enabled', $failures);

// 3. Installed and its status flag is on — enabled.
$adapter = new ExtensionAdapter(
    new FakeConfig(['shipping_flat_status' => '1']),
    new FakeModelSettingExtension(['shipping' => ['flat']])
);
assertSameExtensionAdapter(true, $adapter->hasEnabled('shipping'), 'Scenario 3: installed and enabled is enabled', $failures);

// 4. Several installed, only one enabled — still true (matches
//    ModelMintcartReadiness's original "at least one enabled" semantics).
$adapter = new ExtensionAdapter(
    new FakeConfig(['payment_cod_status' => '0', 'payment_bank_transfer_status' => '1']),
    new FakeModelSettingExtension(['payment' => ['cod', 'bank_transfer']])
);
assertSameExtensionAdapter(true, $adapter->hasEnabled('payment'), 'Scenario 4: at least one enabled among several installed is enabled', $failures);

// 5. Type isolation — an enabled shipping extension must not count as an
//    enabled payment extension, even against the same Config/model pair.
$adapter = new ExtensionAdapter(
    new FakeConfig(['shipping_flat_status' => '1']),
    new FakeModelSettingExtension(['shipping' => ['flat']])
);
assertSameExtensionAdapter(false, $adapter->hasEnabled('payment'), 'Scenario 5: enabled shipping does not count as enabled payment', $failures);

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo 'PASS: ExtensionAdapter smoke test (5 scenarios)' . "\n";
exit(0);
