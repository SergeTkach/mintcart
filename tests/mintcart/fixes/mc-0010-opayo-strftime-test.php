<?php
/**
 * Regression test for MC-0010 (docs/fixes/registry.md): Opayo/Sage Pay's
 * recurring-payment model called strftime("%Y%m%d%H%M%S"), a function
 * deprecated since PHP 8.1 (removed in a future PHP major version) that
 * emits an E_DEPRECATED notice on every call. There is no live OpenCart
 * bootstrap in this environment to instantiate ModelExtensionPaymentOpayo
 * itself (it depends on Config/Currency/Session/Log from the OpenCart
 * registry), so this test asserts the fix two ways that don't need one:
 *
 * 1. The deprecated call is gone from the source file (regression guard —
 *    catches anyone reintroducing strftime()/gmstrftime() here).
 * 2. The replacement expression (date('YmdHis')) is a byte-for-byte
 *    equivalent of strftime("%Y%m%d%H%M%S") for this purpose (a purely
 *    numeric, locale-independent timestamp used to build a unique
 *    VendorTxCode, never displayed to a user) and produces zero
 *    deprecation output on PHP 8.1+ where the original started warning.
 *
 * Run: php tests/mintcart/fixes/mc-0010-opayo-strftime-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

function fail(string $message): void
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function check(bool $condition, string $message): void
{
    if (!$condition) {
        fail($message);
    }
}

$file = __DIR__ . '/../../../upload/catalog/model/extension/payment/opayo.php';
$source = file_get_contents($file);

check($source !== false, 'opayo.php should be readable');
check(!str_contains($source, 'strftime(') && !str_contains($source, 'gmstrftime('), 'opayo.php must not call the deprecated strftime()/gmstrftime() (MC-0010)');
check(str_contains($source, "date('YmdHis')"), "opayo.php's VendorTxCode should build its timestamp with date('YmdHis')");

// Same numeric-only format, both should agree to the second on a fixed
// timestamp — proves the replacement is not a silent format change.
$fixedTimestamp = mktime(13, 5, 9, 3, 21, 2026);

check(date('YmdHis', $fixedTimestamp) === '20260321130509', "date('YmdHis') must format identically to strftime('%Y%m%d%H%M%S') did");

// Confirm the deprecation notice is actually gone by exercising the two
// expressions directly: strftime() must still warn on this PHP version
// (proving the test would have caught the original bug), date() must not.
$probe = <<<'PHP'
<?php
error_reporting(E_ALL);
$errors = [];
set_error_handler(function ($errno, $errstr) use (&$errors) {
    $errors[] = $errstr;
    return true;
});
@strftime('%Y%m%d%H%M%S');
$deprecatedCount = count($errors);
$errors = [];
date('YmdHis');
$replacementCount = count($errors);
restore_error_handler();
echo $deprecatedCount . ':' . $replacementCount;
PHP;

$probeFile = tempnam(sys_get_temp_dir(), 'mc_0010_probe_') . '.php';
file_put_contents($probeFile, $probe);
$output = shell_exec(PHP_BINARY . ' ' . escapeshellarg($probeFile) . ' 2>/dev/null');
unlink($probeFile);

[$deprecatedCount, $replacementCount] = array_map('intval', explode(':', trim((string) $output)));

check($deprecatedCount > 0, 'sanity check: strftime() should still emit a deprecation notice on this PHP version (if this fails, the check itself is stale, e.g. a future PHP major removed strftime() outright)');
check($replacementCount === 0, "date('YmdHis') must not emit any deprecation notice");

echo "PASS: MC-0010 opayo.php strftime() regression test\n";
