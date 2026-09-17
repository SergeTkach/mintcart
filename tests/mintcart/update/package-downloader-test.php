<?php
/**
 * Standalone smoke test for MintCart\Update\PackageDownloader.
 *
 * No PHPUnit/Composer in the repo yet, same as every other tests/mintcart/
 * smoke test. No outbound network access in this environment either, so
 * every scenario here injects a fake transport callable instead of exercising
 * the class's real `file_get_contents()`-based default transport.
 *
 * Run: php tests/mintcart/update/package-downloader-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/update/packagedownloader.php';

use MintCart\Update\PackageDownloader;

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
$workspace = sys_get_temp_dir() . '/mintcart-package-downloader-test-' . uniqid();
mkdir($workspace, 0777, true);

// 1. download(): writes the transport's returned bytes verbatim to the
// destination path.
$destination = $workspace . '/package.zip';
$downloader = new PackageDownloader(function (string $url) {
    return 'fake-package-bytes-for-' . $url;
});
$downloader->download('https://updates.example.test/mint_default-1.2.0.zip', $destination);
assertTrue(is_file($destination), 'download: writes the destination file', $failures);
assertSame(
    'fake-package-bytes-for-https://updates.example.test/mint_default-1.2.0.zip',
    file_get_contents($destination),
    'download: destination file contains exactly what the transport returned',
    $failures
);

// 2. download(): the transport receives the exact URL passed in.
$seenUrl = null;
(new PackageDownloader(function (string $url) use (&$seenUrl) {
    $seenUrl = $url;

    return 'bytes';
}))->download('https://updates.example.test/theme.zip', $workspace . '/theme.zip');
assertSame('https://updates.example.test/theme.zip', $seenUrl, 'download: transport receives the exact requested URL', $failures);

// 3. download(): creates the destination's parent directory if it does not
// exist yet.
$nestedDestination = $workspace . '/nested/does/not/exist/yet/package.zip';
(new PackageDownloader(function (string $url) {
    return 'bytes';
}))->download('https://updates.example.test/package.zip', $nestedDestination);
assertTrue(is_file($nestedDestination), 'download: creates missing parent directories', $failures);

// 4. download(): no leftover ".part-*" temp file remains after a successful
// download (atomic write via temp-file-then-rename).
$tempLeftovers = glob($workspace . '/*.part-*') ?: [];
assertSame([], $tempLeftovers, 'download: no leftover temp file after a successful write', $failures);

// 5. download(): overwrites an existing destination file (a re-run/retry is
// not blocked by a stale file from a previous attempt).
file_put_contents($destination, 'stale-previous-download');
$downloader->download('https://updates.example.test/mint_default-1.2.0.zip', $destination);
assertSame(
    'fake-package-bytes-for-https://updates.example.test/mint_default-1.2.0.zip',
    file_get_contents($destination),
    'download: overwrites a stale existing destination file',
    $failures
);

// 6. download(): rejects a non-http(s) URL before ever calling the
// transport.
$transportCalled = false;
try {
    (new PackageDownloader(function (string $url) use (&$transportCalled) {
        $transportCalled = true;

        return 'bytes';
    }))->download('file:///etc/passwd', $workspace . '/should-not-exist.zip');
    $failures[] = 'download: a file:// URL should throw InvalidArgumentException';
} catch (\InvalidArgumentException $exception) {
    // expected
}
assertTrue(!$transportCalled, 'download: the transport is never invoked for a rejected scheme', $failures);
assertTrue(!is_file($workspace . '/should-not-exist.zip'), 'download: no file is written for a rejected scheme', $failures);

// 7. download(): rejects a URL with no scheme at all.
try {
    (new PackageDownloader(function (string $url) {
        return 'bytes';
    }))->download('updates.example.test/package.zip', $workspace . '/no-scheme.zip');
    $failures[] = 'download: a schemeless URL should throw InvalidArgumentException';
} catch (\InvalidArgumentException $exception) {
    // expected
}

// 8. download(): throws when the transport returns an empty body.
try {
    (new PackageDownloader(function (string $url) {
        return '';
    }))->download('https://updates.example.test/empty.zip', $workspace . '/empty.zip');
    $failures[] = 'download: an empty transport response should throw RuntimeException';
} catch (\RuntimeException $exception) {
    // expected
}
assertTrue(!is_file($workspace . '/empty.zip'), 'download: no file is written when the transport returns an empty body', $failures);

// 9. download(): a transport failure (thrown exception) propagates rather
// than being swallowed, and no destination file is left behind.
try {
    (new PackageDownloader(function (string $url) {
        throw new \RuntimeException('connection reset');
    }))->download('https://updates.example.test/fails.zip', $workspace . '/fails.zip');
    $failures[] = 'download: a transport failure should propagate';
} catch (\RuntimeException $exception) {
    assertSame('connection reset', $exception->getMessage(), 'download: the original transport exception message propagates unchanged', $failures);
}
assertTrue(!is_file($workspace . '/fails.zip'), 'download: no file is written when the transport throws', $failures);

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

echo 'PASS: PackageDownloader smoke test (' . 9 . " scenarios)\n";
exit(0);
