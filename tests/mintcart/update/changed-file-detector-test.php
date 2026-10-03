<?php
/**
 * Standalone smoke test for MintCart\Update\ChangedFileDetector.
 *
 * No PHPUnit/Composer in the repo yet, same as every other tests/mintcart/
 * smoke test. ChangedFileDetector has no OpenCart dependency by design, so
 * this runs entirely against temp files created for the run.
 *
 * Run: php tests/mintcart/update/changed-file-detector-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/update/changedfiledetector.php';

use MintCart\Update\ChangedFileDetector;

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

// Temp workspace for this run's fixture files, cleaned up at the end.
$workspace = sys_get_temp_dir() . '/mintcart-changed-file-detector-test-' . uniqid();
mkdir($workspace, 0777, true);

// 1. An unchanged file (current sha256 matches the ledger snapshot).
file_put_contents($workspace . '/catalog.min.js', 'console.log("v1");');
$snapshot = [
    'catalog.min.js' => hash_file('sha256', $workspace . '/catalog.min.js'),
];
$result = ChangedFileDetector::detect($workspace, $snapshot);
assertSame(['catalog.min.js'], $result['unchanged'], 'detect: unchanged file lands in "unchanged"', $failures);
assertSame([], $result['modified'], 'detect: unchanged file is not in "modified"', $failures);
assertSame([], $result['missing'], 'detect: unchanged file is not in "missing"', $failures);
assertTrue(!ChangedFileDetector::hasChanges($workspace, $snapshot), 'hasChanges: false when everything is unchanged', $failures);

// 2. A modified file (content on disk edited since the ledger was recorded).
$originalHash = hash_file('sha256', $workspace . '/catalog.min.js');
file_put_contents($workspace . '/catalog.min.js', 'console.log("hand-edited");');
$snapshotStale = ['catalog.min.js' => $originalHash];
$result = ChangedFileDetector::detect($workspace, $snapshotStale);
assertSame(['catalog.min.js'], $result['modified'], 'detect: edited file lands in "modified"', $failures);
assertSame([], $result['unchanged'], 'detect: edited file is not in "unchanged"', $failures);
assertSame([], $result['missing'], 'detect: edited file is not in "missing"', $failures);
assertTrue(ChangedFileDetector::hasChanges($workspace, $snapshotStale), 'hasChanges: true when a file was modified', $failures);

// 3. A missing file (deleted from disk since the ledger was recorded).
$snapshotMissing = ['does-not-exist.js' => 'deadbeef'];
$result = ChangedFileDetector::detect($workspace, $snapshotMissing);
assertSame(['does-not-exist.js'], $result['missing'], 'detect: deleted file lands in "missing"', $failures);
assertSame([], $result['modified'], 'detect: deleted file is not in "modified"', $failures);
assertSame([], $result['unchanged'], 'detect: deleted file is not in "unchanged"', $failures);
assertTrue(ChangedFileDetector::hasChanges($workspace, $snapshotMissing), 'hasChanges: true when a file is missing', $failures);

// 4. A mix of all three states in one snapshot, in insertion order.
file_put_contents($workspace . '/a.css', 'a{color:red}');
file_put_contents($workspace . '/b.css', 'b{color:blue}');
$hashA = hash_file('sha256', $workspace . '/a.css');
$hashB = hash_file('sha256', $workspace . '/b.css');
file_put_contents($workspace . '/b.css', 'b{color:green}'); // edited after snapshot taken
$mixedSnapshot = [
    'a.css' => $hashA,
    'b.css' => $hashB,
    'c.css' => 'deadbeef',
];
$result = ChangedFileDetector::detect($workspace, $mixedSnapshot);
assertSame(['a.css'], $result['unchanged'], 'detect: mixed snapshot — unchanged entry correct', $failures);
assertSame(['b.css'], $result['modified'], 'detect: mixed snapshot — modified entry correct', $failures);
assertSame(['c.css'], $result['missing'], 'detect: mixed snapshot — missing entry correct', $failures);
assertTrue(ChangedFileDetector::hasChanges($workspace, $mixedSnapshot), 'hasChanges: true for a mixed snapshot with at least one change', $failures);

// 5. An empty snapshot (package with no recorded files, or unknown package
// per InstalledFileLedger::forPackage()) reports nothing and no changes.
$result = ChangedFileDetector::detect($workspace, []);
assertSame(['modified' => [], 'missing' => [], 'unchanged' => []], $result, 'detect: empty snapshot returns three empty lists', $failures);
assertTrue(!ChangedFileDetector::hasChanges($workspace, []), 'hasChanges: false for an empty snapshot', $failures);

// 6. Recorded hash comparison is case-insensitive (hash_file() always
// returns lowercase hex, but a ledger row is not guaranteed to have been
// written that way by every caller).
file_put_contents($workspace . '/upper.txt', 'same content');
$upperHash = strtoupper(hash_file('sha256', $workspace . '/upper.txt'));
$result = ChangedFileDetector::detect($workspace, ['upper.txt' => $upperHash]);
assertSame(['upper.txt'], $result['unchanged'], 'detect: uppercase recorded hash still matches (case-insensitive compare)', $failures);

// 7. A nested relative path (mirrors a real theme file like
// "catalog/view/theme/mint_default/template/mint/components/header.twig")
// resolves correctly under $baseDir, and a trailing slash on $baseDir is
// tolerated (same convention as PackageVerifier::missingFiles()).
mkdir($workspace . '/nested/dir', 0777, true);
file_put_contents($workspace . '/nested/dir/file.twig', 'twig content');
$nestedHash = hash_file('sha256', $workspace . '/nested/dir/file.twig');
$result = ChangedFileDetector::detect($workspace . '/', ['nested/dir/file.twig' => $nestedHash]);
assertSame(['nested/dir/file.twig'], $result['unchanged'], 'detect: nested relative path under a trailing-slash baseDir resolves correctly', $failures);

// Clean up the temp workspace.
function rrmdir(string $dir): void
{
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

rrmdir($workspace);

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo 'PASS: ChangedFileDetector smoke test (' . 15 . " scenarios)\n";
exit(0);
