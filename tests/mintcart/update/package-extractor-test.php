<?php
/**
 * Standalone smoke test for MintCart\Update\PackageExtractor.
 *
 * No PHPUnit/Composer in the repo yet, same as every other tests/mintcart/
 * smoke test. Unlike PackageDownloader's test (no outbound network access),
 * PackageExtractor needs no network at all — every scenario here builds a
 * real zip fixture on disk with ZipArchive and extracts it for real, so the
 * zip-slip rejection and atomic-replace behavior are exercised end to end,
 * not faked.
 *
 * Run: php tests/mintcart/update/package-extractor-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/update/packageextractor.php';

use MintCart\Update\PackageExtractor;

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

/**
 * @param array<string, string> $entries relative path => file contents
 */
function makeZip(string $zipPath, array $entries): void
{
    $zip = new \ZipArchive();
    $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

    foreach ($entries as $name => $contents) {
        $zip->addFromString($name, $contents);
    }

    $zip->close();
}

// Temp workspace for this run's fixture files, cleaned up at the end.
$workspace = sys_get_temp_dir() . '/mintcart-package-extractor-test-' . uniqid();
mkdir($workspace, 0777, true);

$extractor = new PackageExtractor();

// 1. extract(): extracts every file entry and returns their relative paths,
// sorted.
$zipPath = $workspace . '/package.zip';
makeZip($zipPath, [
    'upload/system/library/mintcart/foo.php' => '<?php // foo',
    'upload/admin/view/template/mintcart/bar.twig' => '{# bar #}',
]);
$destination = $workspace . '/extracted-1';
$extracted = $extractor->extract($zipPath, $destination);
assertSame(
    ['upload/admin/view/template/mintcart/bar.twig', 'upload/system/library/mintcart/foo.php'],
    $extracted,
    'extract: returns every file entry, sorted',
    $failures
);
assertSame('<?php // foo', file_get_contents($destination . '/upload/system/library/mintcart/foo.php'), 'extract: file contents match the archive entry', $failures);
assertSame('{# bar #}', file_get_contents($destination . '/upload/admin/view/template/mintcart/bar.twig'), 'extract: nested file contents match the archive entry', $failures);

// 2. extract(): directory-only entries are not included in the returned
// list (they carry no content for a caller to verify/copy).
$zipWithDir = $workspace . '/with-dir.zip';
$zip = new \ZipArchive();
$zip->open($zipWithDir, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
$zip->addEmptyDir('empty-dir');
$zip->addFromString('empty-dir/file.txt', 'contents');
$zip->close();
$extractedWithDir = $extractor->extract($zipWithDir, $workspace . '/extracted-2');
assertSame(['empty-dir/file.txt'], $extractedWithDir, 'extract: directory entries are omitted from the returned file list', $failures);

// 3. extract(): rejects a zip-slip entry (".." segment) before extracting
// anything.
$slipZip = $workspace . '/slip.zip';
makeZip($slipZip, [
    'safe.txt' => 'safe',
    '../escaped.txt' => 'malicious',
]);
$slipDestination = $workspace . '/extracted-slip';
try {
    $extractor->extract($slipZip, $slipDestination);
    $failures[] = 'extract: a ".." archive entry should throw RuntimeException';
} catch (\RuntimeException $exception) {
    assertTrue(strpos($exception->getMessage(), 'zip-slip') !== false, 'extract: the zip-slip error message names the reason', $failures);
}
assertTrue(!is_dir($slipDestination), 'extract: nothing is extracted when a zip-slip entry is rejected', $failures);
assertTrue(!is_file(dirname($workspace) . '/escaped.txt'), 'extract: a zip-slip entry never escapes the destination directory', $failures);

// 4. extract(): rejects an absolute-path entry.
$absoluteZip = $workspace . '/absolute.zip';
makeZip($absoluteZip, [
    '/etc/should-not-write.txt' => 'malicious',
]);
try {
    $extractor->extract($absoluteZip, $workspace . '/extracted-absolute');
    $failures[] = 'extract: an absolute-path archive entry should throw RuntimeException';
} catch (\RuntimeException $exception) {
    assertTrue(strpos($exception->getMessage(), 'zip-slip') !== false, 'extract: the absolute-path error message names the reason', $failures);
}

// 5. extract(): replaces an existing destination directory wholesale rather
// than merging into it (a stale leftover file from a previous extraction
// must not survive).
$replaceDestination = $workspace . '/extracted-replace';
mkdir($replaceDestination, 0777, true);
file_put_contents($replaceDestination . '/stale-leftover.txt', 'stale');
$replaceZip = $workspace . '/replace.zip';
makeZip($replaceZip, ['fresh.txt' => 'fresh']);
$extractor->extract($replaceZip, $replaceDestination);
assertTrue(!is_file($replaceDestination . '/stale-leftover.txt'), 'extract: a stale leftover file from a previous extraction is gone', $failures);
assertTrue(is_file($replaceDestination . '/fresh.txt'), 'extract: the new extraction is present after replacing the destination', $failures);

// 6. extract(): no leftover ".part-*" temp directory remains after a
// successful extraction (atomic replace via temp-dir-then-rename).
$tempLeftovers = glob($workspace . '/*.part-*') ?: [];
assertSame([], $tempLeftovers, 'extract: no leftover temp directory after a successful extraction', $failures);

// 7. extract(): throws when the archive does not exist.
try {
    $extractor->extract($workspace . '/does-not-exist.zip', $workspace . '/extracted-missing');
    $failures[] = 'extract: a missing archive should throw RuntimeException';
} catch (\RuntimeException $exception) {
    // expected
}

// 8. extract(): throws when the archive has no entries at all.
$emptyZip = $workspace . '/empty.zip';
makeZip($emptyZip, []);
try {
    $extractor->extract($emptyZip, $workspace . '/extracted-empty');
    $failures[] = 'extract: an archive with zero entries should throw RuntimeException';
} catch (\RuntimeException $exception) {
    // expected
}

// 9. extract(): throws when the file at $archivePath is not a valid zip
// archive.
$notAZip = $workspace . '/not-a-zip.zip';
file_put_contents($notAZip, 'this is not a zip file');
try {
    $extractor->extract($notAZip, $workspace . '/extracted-not-a-zip');
    $failures[] = 'extract: an invalid archive should throw RuntimeException';
} catch (\RuntimeException $exception) {
    // expected
}

// Clean up the temp workspace and any zip-slip escape target from scenario 3
// (should never exist, but clean up defensively in case a regression wrote it).
rrmdir($workspace);
@unlink(dirname($workspace) . '/escaped.txt');

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo 'PASS: PackageExtractor smoke test (' . 9 . " scenarios)\n";
exit(0);
