<?php
/**
 * Regression test for MC-0005 (docs/fixes/registry.md): the two install-time
 * PHP version pre-flight checks - install/cli_install.php's
 * check_requirements() and install/controller/install/step_2.php's
 * validate() - used a naive string '<' comparison against a two-segment
 * threshold ('7.3' / '8.1') instead of version_compare(). cli_install.php's
 * threshold was also stale: '7.3' while system/startup.php (required
 * unconditionally at the top of that same file, before check_requirements()
 * can ever run) already hard-exits below PHP 8.1.0, making the old 7.3+
 * message both wrong and unreachable.
 *
 * These functions can't be called directly (they call phpversion(), which
 * can't be swapped per-test in one process, and step_2's validate() needs a
 * live Loader/Language/Request bootstrap this environment doesn't have).
 * This test instead proves the logic in isolation, parameterized by a
 * version string, the same approach MC-0004's test used.
 *
 * Run: php tests/mintcart/fixes/mc-0005-install-php-version-check-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

$failures = [];

function assertTrue(bool $condition, string $message, array &$failures): void
{
    if (!$condition) {
        $failures[] = $message;
    }
}

/** The pre-fix cli_install.php logic: naive '<' against a stale '7.3' threshold. */
function cliInstallBelowMinimum_buggy(string $phpVersion): bool
{
    return $phpVersion < '7.3';
}

/** The fixed cli_install.php logic now shipped: version_compare() against 8.1.0. */
function cliInstallBelowMinimum_fixed(string $phpVersion): bool
{
    return version_compare($phpVersion, '8.1.0', '<') === true;
}

/** system/startup.php's own gate - the actual enforced requirement, unchanged by this fix. */
function startupRejects(string $phpVersion): bool
{
    return version_compare($phpVersion, '8.1.0', '<') === true;
}

// 1. The core bug this fix addresses: cli_install.php's own pre-flight
//    check used to consider PHP 7.4-8.0 acceptable ('Warning' not raised),
//    but system/startup.php - required unconditionally at the top of the
//    very same file, before check_requirements() is ever reached - already
//    hard-exits for any of those versions. The old check's "OK" verdict
//    could never actually be experienced by a user: the process was
//    already dead by the time it would run.
foreach (['7.3.0', '7.4.33', '8.0.30'] as $version) {
    assertTrue(
        !cliInstallBelowMinimum_buggy($version) && startupRejects($version),
        "buggy: cli_install.php's own check passed {$version} as OK while system/startup.php (loaded earlier in the same file) already hard-exits on it - the stale threshold (MC-0005)",
        $failures
    );
    assertTrue(
        cliInstallBelowMinimum_fixed($version),
        "fixed: cli_install.php now correctly flags {$version} as below the real minimum",
        $failures
    );
}

// 2. Versions genuinely at or above the real requirement must still pass,
//    both before and after the fix (no new false positives introduced).
foreach (['8.1.0', '8.1.29', '8.4.19', '9.0.0'] as $version) {
    assertTrue(
        !cliInstallBelowMinimum_fixed($version),
        "fixed: {$version} correctly accepted (meets the 8.1.0 minimum)",
        $failures
    );
    assertTrue(
        !startupRejects($version),
        "sanity: {$version} is accepted by system/startup.php's own gate too",
        $failures
    );
}

// 3. A version genuinely below the real requirement must be rejected by
//    the fixed logic (not just "no longer silently accepted" - it must
//    actively flag as an error, matching startup.php's own verdict for
//    the exact same input).
foreach (['5.6.40', '7.0.33', '8.0.0'] as $version) {
    assertTrue(
        cliInstallBelowMinimum_fixed($version) === startupRejects($version),
        "fixed: cli_install.php's verdict for {$version} now matches system/startup.php's own verdict exactly",
        $failures
    );
}

// 4. A distro-suffixed version string (e.g. Debian/Ubuntu-packaged PHP
//    reports phpversion() like '8.1.2-1ubuntu2.14') must not confuse the
//    fixed comparison - version_compare() parses the leading numeric
//    portion correctly, unlike a raw string '<'.
assertTrue(
    !cliInstallBelowMinimum_fixed('8.1.2-1ubuntu2.14'),
    'fixed: a distro-suffixed 8.1.x version string is still correctly accepted',
    $failures
);
assertTrue(
    cliInstallBelowMinimum_fixed('7.4.3-4+ubuntu20.04.1+deb.sury.org+1'),
    'fixed: a distro-suffixed pre-8.1 version string is still correctly rejected',
    $failures
);

if ($failures) {
    fwrite(STDERR, count($failures) . " failure(s):\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "- {$failure}\n");
    }
    exit(1);
}

echo "PASS: MC-0005 install PHP-version-check smoke test\n";
exit(0);
