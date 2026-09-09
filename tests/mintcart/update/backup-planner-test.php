<?php
/**
 * Standalone smoke test for MintCart\Update\BackupPlanner.
 *
 * No PHPUnit/Composer in the repo yet, same as every other tests/mintcart/
 * smoke test. BackupPlanner has no OpenCart dependency by design, so this
 * runs entirely against plain arrays and a real PackageManifest.
 *
 * Run: php tests/mintcart/update/backup-planner-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/update/packagemanifest.php';
require __DIR__ . '/../../../upload/system/library/mintcart/update/backupplanner.php';

use MintCart\Update\BackupPlanner;
use MintCart\Update\PackageManifest;

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

$planner = new BackupPlanner();

// 1. A manifest with no files/removes at all produces an empty, but still
// well-formed, plan.
$empty = PackageManifest::fromArray(['name' => 'mint_default', 'version' => '1.1.0']);
$plan = $planner->plan($empty, [], '20260909120000');
assertSame('mint_default', $plan['package'], '1. plan: package name echoes manifest', $failures);
assertSame([], $plan['files'], '1. plan: no files to back up', $failures);
assertSame(0, $plan['total_bytes'], '1. plan: total_bytes is 0', $failures);

// 2. A files() path that does not currently exist on disk is not backed
// up — a brand-new file the update only adds has nothing to snapshot.
$adds = PackageManifest::fromArray([
    'name' => 'mint_default',
    'version' => '1.1.0',
    'files' => ['catalog/view/theme/mint_default/template/mint/blocks/new-block.twig'],
]);
$plan = $planner->plan($adds, [], '20260909120000');
assertSame([], $plan['files'], '2. plan: a not-yet-existing files() path is not backed up', $failures);

// 3. A files() path that DOES currently exist is backed up, with its size
// counted toward total_bytes.
$overwrites = PackageManifest::fromArray([
    'name' => 'mint_default',
    'version' => '1.1.0',
    'files' => ['catalog/view/theme/mint_default/theme.json'],
]);
$plan = $planner->plan($overwrites, ['catalog/view/theme/mint_default/theme.json' => 512], '20260909120000');
assertSame(['catalog/view/theme/mint_default/theme.json'], $plan['files'], '3. plan: an existing files() path is backed up', $failures);
assertSame(512, $plan['total_bytes'], '3. plan: total_bytes sums the backed-up file sizes', $failures);

// 4. A removes() path that currently exists is backed up exactly like an
// overwritten files() path (ADR 005 decision #3 — never delete without a
// snapshot), and both files()+removes() bytes are summed together.
$removesManifest = PackageManifest::fromArray([
    'name' => 'mint_default',
    'version' => '1.1.0',
    'files' => ['catalog/view/theme/mint_default/theme.json'],
    'removes' => ['catalog/view/theme/mint_default/template/mint/components/old-card.twig'],
]);
$existing = [
    'catalog/view/theme/mint_default/theme.json' => 512,
    'catalog/view/theme/mint_default/template/mint/components/old-card.twig' => 256,
];
$plan = $planner->plan($removesManifest, $existing, '20260909120000');
assertSame(2, count($plan['files']), '4. plan: both files() and removes() paths are included', $failures);
assertTrue(in_array('catalog/view/theme/mint_default/template/mint/components/old-card.twig', $plan['files'], true), '4. plan: the removes() path is backed up', $failures);
assertSame(768, $plan['total_bytes'], '4. plan: total_bytes sums both files() and removes() sizes', $failures);

// 5. A path listed in both files() and removes() (a caller mistake, or a
// package overwriting then immediately superseding a file) is only
// counted once — never double-backed-up, never double-counted in bytes.
$overlap = PackageManifest::fromArray([
    'name' => 'mint_default',
    'version' => '1.1.0',
    'files' => ['catalog/view/theme/mint_default/theme.json'],
    'removes' => ['catalog/view/theme/mint_default/theme.json'],
]);
$plan = $planner->plan($overlap, ['catalog/view/theme/mint_default/theme.json' => 512], '20260909120000');
assertSame(['catalog/view/theme/mint_default/theme.json'], $plan['files'], '5. plan: a path in both files() and removes() is de-duplicated', $failures);
assertSame(512, $plan['total_bytes'], '5. plan: de-duplicated path is only counted once toward total_bytes', $failures);

// 6. snapshotPath() places snapshots under system/storage/, outside any
// web-servable path (ADR 005 decision #7).
assertSame(
    'system/storage/mintcart/backup/mint_default/20260909120000',
    BackupPlanner::snapshotPath('mint_default', '20260909120000'),
    '6. snapshotPath: builds the documented system/storage/ path',
    $failures
);
assertSame('mint_default', $plan['package'], '6. plan: package name still echoes manifest after overlap case', $failures);
assertTrue(strpos($planner->plan($empty, [], 't')['snapshot_dir'], 'system/storage/') === 0, '6. plan: snapshot_dir also starts under system/storage/', $failures);

// 7. hasEnoughDiskSpace(): exactly enough free space (snapshot + incoming)
// passes; one byte short fails. Sized for BOTH the snapshot being written
// and the incoming package's own files (ADR 005 decision #6).
assertTrue($planner->hasEnoughDiskSpace(1000, 2000, 3000), '7. hasEnoughDiskSpace: exactly enough space passes', $failures);
assertTrue(!$planner->hasEnoughDiskSpace(1000, 2000, 2999), '7. hasEnoughDiskSpace: one byte short fails', $failures);
assertTrue($planner->hasEnoughDiskSpace(0, 0, 0), '7. hasEnoughDiskSpace: nothing needed, nothing free still passes', $failures);

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo 'PASS: BackupPlanner smoke test (' . 7 . " scenarios)\n";
exit(0);
