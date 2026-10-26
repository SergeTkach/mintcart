<?php
/**
 * Standalone smoke test for MintCart\Update\BackupExecutor.
 *
 * No PHPUnit/Composer in the repo yet, same as every other tests/mintcart/
 * smoke test. Like PackageExtractor's own test, this needs no network and
 * no OpenCart bootstrap — every scenario builds a real small "install
 * root" directory on disk and backs up real files from it, so the atomic
 * temp-dir-then-rename swap and the "skip a file that's already gone"
 * behavior are exercised end to end, not faked.
 *
 * Run: php tests/mintcart/update/backup-executor-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/update/backupexecutor.php';

use MintCart\Update\BackupExecutor;

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

function rrmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    foreach (scandir($dir) as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $path = $dir . '/' . $entry;

        if (is_dir($path)) {
            rrmdir($path);
        } else {
            unlink($path);
        }
    }

    rmdir($dir);
}

function putFile(string $path, string $contents): void
{
    @mkdir(dirname($path), 0777, true);
    file_put_contents($path, $contents);
}

// Temp workspace for this run's fixture files, cleaned up at the end.
$workspace = sys_get_temp_dir() . '/mintcart-backup-executor-test-' . uniqid();
mkdir($workspace, 0777, true);

$executor = new BackupExecutor();

// 1. backup(): copies every existing plan file into the snapshot directory,
// preserving the relative path, and returns them sorted.
$installRoot1 = $workspace . '/install-1';
putFile($installRoot1 . '/catalog/controller/product/category.php', 'old category controller');
putFile($installRoot1 . '/system/config/default.php', 'old config');
$plan1 = [
    'package' => 'mint_default',
    'snapshot_dir' => 'storage/mintcart/backup/mint_default/20260918120000',
    'files' => [
        'system/config/default.php',
        'catalog/controller/product/category.php',
    ],
    'total_bytes' => 40,
];
$result1 = $executor->backup($plan1, $installRoot1);
assertSame(
    ['catalog/controller/product/category.php', 'system/config/default.php'],
    $result1['backed_up'],
    '1. backup: returns every backed-up path, sorted',
    $failures
);
assertSame($plan1['snapshot_dir'], $result1['snapshot_dir'], '1. backup: echoes the plan\'s snapshot_dir', $failures);
assertSame(
    'old category controller',
    file_get_contents($installRoot1 . '/' . $plan1['snapshot_dir'] . '/catalog/controller/product/category.php'),
    '1. backup: snapshot file contents match the live install file',
    $failures
);
assertSame(
    'old config',
    file_get_contents($installRoot1 . '/' . $plan1['snapshot_dir'] . '/system/config/default.php'),
    '1. backup: nested snapshot file contents match the live install file',
    $failures
);

// 2. backup(): a plan file that no longer exists under $installRoot is
// silently skipped, not an error.
$installRoot2 = $workspace . '/install-2';
putFile($installRoot2 . '/catalog/still-here.php', 'still here');
$plan2 = [
    'package' => 'mint_default',
    'snapshot_dir' => 'storage/mintcart/backup/mint_default/20260918130000',
    'files' => ['catalog/still-here.php', 'catalog/already-gone.php'],
    'total_bytes' => 10,
];
$result2 = $executor->backup($plan2, $installRoot2);
assertSame(['catalog/still-here.php'], $result2['backed_up'], '2. backup: a missing plan file is skipped, not an error', $failures);

// 3. backup(): an empty plan (nothing to back up) still produces a
// well-formed, empty snapshot directory rather than throwing.
$installRoot3 = $workspace . '/install-3';
mkdir($installRoot3, 0777, true);
$plan3 = [
    'package' => 'mint_default',
    'snapshot_dir' => 'storage/mintcart/backup/mint_default/20260918140000',
    'files' => [],
    'total_bytes' => 0,
];
$result3 = $executor->backup($plan3, $installRoot3);
assertSame([], $result3['backed_up'], '3. backup: an empty plan backs up nothing', $failures);
assertTrue(is_dir($installRoot3 . '/' . $plan3['snapshot_dir']), '3. backup: an empty plan still creates the snapshot directory', $failures);

// 4. backup(): a pre-existing snapshot directory at the same path is
// replaced wholesale, not merged into (a stale leftover from a previous
// attempt must not survive).
$installRoot4 = $workspace . '/install-4';
putFile($installRoot4 . '/fresh.php', 'fresh contents');
$snapshotDir4 = $installRoot4 . '/storage/mintcart/backup/mint_default/20260918150000';
putFile($snapshotDir4 . '/stale-leftover.txt', 'stale');
$plan4 = [
    'package' => 'mint_default',
    'snapshot_dir' => 'storage/mintcart/backup/mint_default/20260918150000',
    'files' => ['fresh.php'],
    'total_bytes' => 14,
];
$executor->backup($plan4, $installRoot4);
assertTrue(!is_file($snapshotDir4 . '/stale-leftover.txt'), '4. backup: a stale leftover file from a previous backup is gone', $failures);
assertTrue(is_file($snapshotDir4 . '/fresh.php'), '4. backup: the new backup is present after replacing the snapshot directory', $failures);

// 5. backup(): no leftover ".part-*" temp directory remains after a
// successful backup (atomic replace via temp-dir-then-rename).
$tempLeftovers = glob($installRoot4 . '/storage/mintcart/backup/mint_default/*.part-*') ?: [];
assertSame([], $tempLeftovers, '5. backup: no leftover temp directory after a successful backup', $failures);

// 6. backup(): rejects a plan file path containing a ".." segment before
// touching anything.
$installRoot6 = $workspace . '/install-6';
mkdir($installRoot6, 0777, true);
$plan6 = [
    'package' => 'mint_default',
    'snapshot_dir' => 'storage/mintcart/backup/mint_default/20260918160000',
    'files' => ['../escape.php'],
    'total_bytes' => 0,
];
try {
    $executor->backup($plan6, $installRoot6);
    $failures[] = '6. backup: a ".." plan file path should throw InvalidArgumentException';
} catch (\InvalidArgumentException $exception) {
    // expected
}
assertTrue(
    !is_dir($installRoot6 . '/storage/mintcart/backup/mint_default'),
    '6. backup: nothing is written when a ".." plan file path is rejected',
    $failures
);

// Clean up the temp workspace.
rrmdir($workspace);

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo 'PASS: BackupExecutor smoke test (' . 6 . " scenarios)\n";
exit(0);
