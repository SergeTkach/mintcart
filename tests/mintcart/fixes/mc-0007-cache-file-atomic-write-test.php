<?php
/**
 * Standalone smoke test for MC-0007: Cache\File::set() writes atomically.
 *
 * No PHPUnit/Composer in the repo yet, same as every other
 * tests/mintcart/**-test.php file. Cache\File has no dependency beyond a
 * writable DIR_CACHE directory, so this runs entirely against a real
 * temporary directory - no OpenCart/Registry/MySQL bootstrap needed.
 *
 * Run: php tests/mintcart/fixes/mc-0007-cache-file-atomic-write-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

$cacheDir = sys_get_temp_dir() . '/mintcart-mc-0007-' . uniqid('', true) . '/';
mkdir($cacheDir, 0777, true);

define('DIR_CACHE', $cacheDir);

require __DIR__ . '/../../../upload/system/library/cache/file.php';

use Cache\File;

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

function cacheFilesFor(string $cacheDir, string $key): array
{
    return glob($cacheDir . 'cache.' . $key . '.*') ?: [];
}

function tempFilesIn(string $cacheDir): array
{
    return glob($cacheDir . 'cache.*.tmp') ?: [];
}

// 1. Basic set()/get() round-trip still works (regression safety - the
// fix must not change observable behavior for the non-concurrent case).
$cache = new File(3600);
$cache->set('widget', ['name' => 'Widget', 'price' => 9.99]);
assertSame(['name' => 'Widget', 'price' => 9.99], $cache->get('widget'), 'set/get: basic round-trip returns the stored value', $failures);

// 2. get() on a never-set key returns false, not an error.
assertSame(false, $cache->get('never-set-key'), 'get: an unset key returns false', $failures);

// 3. No stray *.tmp file survives a set() call - the temp file used for
// the atomic write is always renamed away, never left behind.
assertSame([], tempFilesIn($cacheDir), 'set: no *.tmp file is left behind after a successful write', $failures);

// 4. Exactly one cache file exists for the key after set() - the old
// pattern (delete() then fopen($file, 'w')) already guaranteed this for
// a single, non-racing writer; confirms the rename()-based rewrite keeps
// that invariant (no duplicate/orphaned temp-turned-permanent file).
assertSame(1, count(cacheFilesFor($cacheDir, 'widget')), 'set: exactly one cache file exists for the key after a single set()', $failures);

// 5. Overwriting an existing key replaces its value and still leaves
// exactly one file for that key (the old differently-timestamped file is
// still cleaned up by the existing delete()-first step).
$cache->set('widget', ['name' => 'Widget', 'price' => 12.5]);
assertSame(['name' => 'Widget', 'price' => 12.5], $cache->get('widget'), 'set: overwriting a key returns the new value', $failures);
assertSame(1, count(cacheFilesFor($cacheDir, 'widget')), 'set: overwriting a key still leaves exactly one cache file for it', $failures);

// 6. delete() removes the file entirely.
$cache->delete('widget');
assertSame(false, $cache->get('widget'), 'delete: a deleted key reads back as false', $failures);
assertSame(0, count(cacheFilesFor($cacheDir, 'widget')), 'delete: no cache file remains for the key', $failures);

// 7. Concurrency check: a real second OS process writing the SAME key
// (colliding on the same cache.<key>.<expire-second> filename, since the
// filename only has second-granularity) must never leave a reader able
// to observe a torn/empty file. Before this fix, set() truncated the
// target filename directly via fopen($file, 'w') *before* taking any
// lock, so a concurrent set() for the same key (unlink-then-recreate via
// its own delete()+fopen('w')) could stomp the first writer's
// in-progress file, and any get() racing in between could briefly see a
// 0-byte file (json_decode('') === null, not false/valid data). This is
// only exercisable with a real second process - a single PHP process is
// single-threaded, so two same-process set() calls never actually race.
if (function_exists('proc_open')) {
    $childScript = __DIR__ . '/mc-0007-cache-file-atomic-write-child.php';

    $observedNull = false;
    $observedValid = false;
    $iterations = 150;

    for ($i = 0; $i < $iterations; $i++) {
        $descriptorSpec = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open(
            escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($childScript) . ' ' . escapeshellarg($cacheDir) . ' ' . escapeshellarg((string) $i),
            $descriptorSpec,
            $pipes
        );

        // Race the child's set() with our own reads on the same key
        // while it's running.
        while (proc_get_status($process)['running']) {
            $value = $cache->get('race-key');

            if ($value === null) {
                // json_decode('') on a torn (0-byte, or truncated by an
                // unlink-mid-write) file - the exact corruption this fix
                // exists to prevent.
                $observedNull = true;
            } elseif ($value !== false) {
                $observedValid = true;
            }
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
    }

    assertTrue(!$observedNull, 'concurrency: a concurrent set() from a second process never leaves get() reading a torn/empty cache file (' . $iterations . ' iterations)', $failures);
    // Not asserted as a failure on its own (timing-dependent whether the
    // reader ever catches the writer mid-flight at all), but recorded so
    // this test is known to actually be racing, not accidentally
    // no-op-ing.
    if (!$observedValid) {
        fwrite(STDERR, "NOTE: concurrency check never observed a hit while the writer was running - timing-dependent, not a failure.\n");
    }
} else {
    fwrite(STDERR, "NOTE: proc_open() unavailable in this environment - skipping the real-concurrency check (scenarios 1-6 already cover the non-racing contract).\n");
}

// Cleanup.
foreach (glob($cacheDir . '*') ?: [] as $leftover) {
    @unlink($leftover);
}
@rmdir($cacheDir);

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo "PASS: MC-0007 Cache\\File atomic write test\n";
exit(0);
