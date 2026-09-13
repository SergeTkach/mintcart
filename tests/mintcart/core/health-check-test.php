<?php
/**
 * Standalone smoke test for MintCart\Core\HealthCheck.
 *
 * No PHPUnit/Composer in the repo yet, same as store-readiness-test.php.
 * HealthCheck has no OpenCart dependency by design, so this runs entirely
 * against plain arrays.
 *
 * Run: php tests/mintcart/core/health-check-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/core/healthcheck.php';

use MintCart\Core\HealthCheck;

$failures = [];

function assertSame($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

function findCheck(array $checks, string $key): ?array
{
    foreach ($checks as $check) {
        if ($check['key'] === $key) {
            return $check;
        }
    }

    return null;
}

$health = new HealthCheck();

$extensionCount = count(HealthCheck::REQUIRED_EXTENSIONS);
$writableCount = count(HealthCheck::REQUIRED_WRITABLE_PATHS);
$totalChecks = 1 /* php_version */ + $extensionCount + $writableCount + 1 /* migrations */ + 1 /* mint_version */;

$healthySignals = [
    'php_version' => '8.2.10',
    'extensions' => array_fill_keys(HealthCheck::REQUIRED_EXTENSIONS, true),
    'writable' => array_fill_keys(array_column(HealthCheck::REQUIRED_WRITABLE_PATHS, 'key'), true),
    'pending_migrations' => 0,
    'mint_version' => '0.1.0-dev',
    'opencart_baseline' => '3.0.5.1',
];

// 1. Empty signals: nothing was checked, so every check is 'unknown' — never
//    silently reported as 'ok' just because the caller passed nothing.
$result = $health->evaluate([]);
assertSame($totalChecks, count($result['checks']), 'evaluate: empty signals still produce every check key', $failures);
assertSame('unknown', $result['status'], 'evaluate: empty signals -> overall status unknown (fail closed, not ok)', $failures);
assertSame('unknown', findCheck($result['checks'], 'php_version')['status'], 'evaluate: unset php_version -> unknown', $failures);

// 2. Fully healthy signals: everything ok.
$result = $health->evaluate($healthySignals);
assertSame('ok', $result['status'], 'evaluate: fully healthy signals -> overall ok', $failures);
foreach ($result['checks'] as $check) {
    assertSame('ok', $check['status'], "evaluate: healthy signals -> '{$check['key']}' is ok", $failures);
}

// 3. PHP version below the minimum -> that check (and overall) is 'error',
//    the minimum itself mirrors system/startup.php's own hard gate.
$signals = $healthySignals;
$signals['php_version'] = '7.4.3';
$result = $health->evaluate($signals);
assertSame('error', $result['status'], 'evaluate: PHP below minimum -> overall error', $failures);
assertSame('error', findCheck($result['checks'], 'php_version')['status'], 'evaluate: PHP below minimum -> php_version check error', $failures);
assertSame('7.4.3 (>= 8.1.0 required)', findCheck($result['checks'], 'php_version')['detail'], 'evaluate: php_version detail names both actual and required version', $failures);

// 3b. PHP version at exactly the minimum is fine (>=, not >).
$signals = $healthySignals;
$signals['php_version'] = HealthCheck::MIN_PHP_VERSION;
$result = $health->evaluate($signals);
assertSame('ok', findCheck($result['checks'], 'php_version')['status'], 'evaluate: PHP exactly at minimum -> ok', $failures);

// 4. One missing PHP extension -> that check is 'error', overall 'error',
//    every other required extension is untouched.
$signals = $healthySignals;
$signals['extensions']['gd'] = false;
$result = $health->evaluate($signals);
assertSame('error', $result['status'], 'evaluate: missing extension -> overall error', $failures);
assertSame('error', findCheck($result['checks'], 'extension_gd')['status'], 'evaluate: missing gd -> extension_gd error', $failures);
assertSame('missing', findCheck($result['checks'], 'extension_gd')['detail'], 'evaluate: missing gd -> detail says missing', $failures);
assertSame('ok', findCheck($result['checks'], 'extension_curl')['status'], 'evaluate: an unrelated extension stays ok', $failures);

// 5. An unwritable required path -> that check is 'error', overall 'error'.
$signals = $healthySignals;
$signals['writable']['storage_logs'] = false;
$result = $health->evaluate($signals);
assertSame('error', $result['status'], 'evaluate: unwritable path -> overall error', $failures);
assertSame('error', findCheck($result['checks'], 'writable_storage_logs')['status'], 'evaluate: unwritable storage_logs -> error', $failures);
assertSame('not writable', findCheck($result['checks'], 'writable_storage_logs')['detail'], 'evaluate: unwritable path -> detail says not writable', $failures);

// 6. Pending migrations degrade to 'warning', not 'error' — an update
//    mid-flight is expected, not broken, and must not read as the same
//    severity as a missing PHP extension.
$signals = $healthySignals;
$signals['pending_migrations'] = 3;
$result = $health->evaluate($signals);
assertSame('warning', $result['status'], 'evaluate: pending migrations alone -> overall warning, not error', $failures);
assertSame('warning', findCheck($result['checks'], 'migrations')['status'], 'evaluate: pending migrations -> migrations check warning', $failures);
assertSame('3 pending', findCheck($result['checks'], 'migrations')['detail'], 'evaluate: migrations detail names the count', $failures);

// 6b. An error elsewhere outranks a warning for the overall status.
$signals = $healthySignals;
$signals['pending_migrations'] = 3;
$signals['extensions']['zip'] = false;
$result = $health->evaluate($signals);
assertSame('error', $result['status'], 'evaluate: error outranks warning for overall status', $failures);

// 7. Label overrides apply per check key, unknown override keys are ignored.
$result = $health->evaluate($healthySignals, ['php_version' => 'Версія PHP', 'no_such_check' => 'ignored']);
assertSame('Версія PHP', findCheck($result['checks'], 'php_version')['label'], 'evaluate: label override applies to the matching check', $failures);
assertSame('PHP extension: gd', findCheck($result['checks'], 'extension_gd')['label'], 'evaluate: default label used when no override given', $failures);

// 8. mint_version check reports the OpenCart baseline alongside it when given.
$result = $health->evaluate($healthySignals);
assertSame('0.1.0-dev (OpenCart 3.0.5.1 baseline)', findCheck($result['checks'], 'mint_version')['detail'], 'evaluate: mint_version detail includes the OpenCart baseline', $failures);

if ($failures) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }
    exit(1);
}

echo "PASS: HealthCheck smoke test ({$totalChecks} checks per evaluation)\n";
