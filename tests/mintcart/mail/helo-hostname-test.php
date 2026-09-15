<?php
/**
 * Standalone smoke test for MintCart\Mail\HeloHostname.
 *
 * Pure function, no OpenCart/\Registry dependency - exercised directly
 * against resolve()'s input/output contract by manipulating real
 * getenv()/putenv() state, same style as every other
 * tests/mintcart/**-test.php file (no PHPUnit/Composer in this repo).
 *
 * Run: php tests/mintcart/mail/helo-hostname-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/mail/helohostname.php';

use MintCart\Mail\HeloHostname;

$failures = [];

function assertSameHeloHostname($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

// Preserve whatever SERVER_NAME the test runner's own environment has, so
// this test never leaks a changed value into anything run after it.
$originalServerName = getenv('SERVER_NAME');

// 1. SERVER_NAME set (the normal web-request case, e.g. Apache/nginx
//    populating it via a FastCGI param) - used as-is.
putenv('SERVER_NAME=shop.example.com');
assertSameHeloHostname(
    'shop.example.com',
    HeloHostname::resolve(),
    'Scenario 1: SERVER_NAME present is returned unchanged',
    $failures
);

// 2. SERVER_NAME unset entirely (the CLI/cron case this fix targets, e.g.
//    php index.php run from a crontab with no web server involved) -
//    getenv() returns false; must fall back to a real, non-empty hostname
//    rather than emitting an empty EHLO/HELO argument.
putenv('SERVER_NAME');
$fallback = HeloHostname::resolve();
assertSameHeloHostname(
    true,
    is_string($fallback) && $fallback !== '',
    'Scenario 2: SERVER_NAME absent still resolves to a non-empty string',
    $failures
);

// 3. SERVER_NAME set to an empty string (some SAPI configs define the var
//    but leave it blank) - same fallback path as scenario 2, not treated
//    as "present".
putenv('SERVER_NAME=');
$emptyFallback = HeloHostname::resolve();
assertSameHeloHostname(
    true,
    is_string($emptyFallback) && $emptyFallback !== '',
    'Scenario 3: SERVER_NAME set to an empty string still resolves to a non-empty string',
    $failures
);

// Restore original environment state.
if ($originalServerName === false) {
    putenv('SERVER_NAME');
} else {
    putenv('SERVER_NAME=' . $originalServerName);
}

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo 'PASS: HeloHostname smoke test (3 scenarios)' . "\n";
exit(0);
