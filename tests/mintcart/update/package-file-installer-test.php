<?php
/**
 * Standalone smoke test for MintCart\Update\PackageFileInstaller.
 *
 * No PHPUnit/Composer in the repo yet, same as every other tests/mintcart/
 * smoke test. PackageFileInstaller has no OpenCart dependency by design,
 * so this runs entirely against temp directories created for the run.
 *
 * Run: php tests/mintcart/update/package-file-installer-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/update/packagemanifest.php';
require __DIR__ . '/../../../upload/system/library/mintcart/update/packageverifier.php';
require __DIR__ . '/../../../upload/system/library/mintcart/update/packagefileinstaller.php';

use MintCart\Update\PackageFileInstaller;
use MintCart\Update\PackageManifest;

$failures = [];
$scenarios = 0;

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

function freshWorkspace(): string
{
    $workspace = sys_get_temp_dir() . '/mintcart-package-file-installer-test-' . uniqid('', true);
    mkdir($workspace . '/source', 0777, true);
    mkdir($workspace . '/dest', 0777, true);

    return $workspace;
}

$installer = new PackageFileInstaller();

// 1. install(): copies a file's exact bytes from source to dest and
// returns its sha256.
$workspace = freshWorkspace();
file_put_contents($workspace . '/source/theme.json', 'hello-manifest');
$manifest = PackageManifest::fromArray([
    'name' => 'mint_default',
    'version' => '1.0.0',
    'files' => ['theme.json'],
]);
$result = $installer->install($manifest, $workspace . '/source', $workspace . '/dest');
$scenarios++;
assertSame('hello-manifest', @file_get_contents($workspace . '/dest/theme.json'), 'install: file content copied byte-for-byte', $failures);
$scenarios++;
assertSame(
    [['path' => 'theme.json', 'sha256' => hash('sha256', 'hello-manifest')]],
    $result['installed'],
    'install: returns the installed path and its sha256',
    $failures
);
$scenarios++;
assertSame([], $result['removed'], 'install: no removes() declared means nothing removed', $failures);
rrmdir($workspace);

// 2. install(): creates missing destination parent directories.
$workspace = freshWorkspace();
mkdir($workspace . '/source/template/mint/components', 0777, true);
file_put_contents($workspace . '/source/template/mint/components/product-card.twig', '<article></article>');
$manifest = PackageManifest::fromArray([
    'name' => 'mint_default',
    'version' => '1.0.0',
    'files' => ['template/mint/components/product-card.twig'],
]);
$installer->install($manifest, $workspace . '/source', $workspace . '/dest');
$scenarios++;
assertTrue(
    is_file($workspace . '/dest/template/mint/components/product-card.twig'),
    'install: creates missing nested destination directories',
    $failures
);
rrmdir($workspace);

// 3. install(): no leftover .part-* temp file after a successful install.
$workspace = freshWorkspace();
file_put_contents($workspace . '/source/theme.json', 'content');
$manifest = PackageManifest::fromArray(['name' => 'x', 'version' => '1.0.0', 'files' => ['theme.json']]);
$installer->install($manifest, $workspace . '/source', $workspace . '/dest');
$leftovers = array_filter(scandir($workspace . '/dest'), static function (string $entry): bool {
    return strpos($entry, '.part-') !== false;
});
$scenarios++;
assertSame([], array_values($leftovers), 'install: no leftover .part-* temp file after success', $failures);
rrmdir($workspace);

// 4. install(): a stale existing destination file is overwritten (retry).
$workspace = freshWorkspace();
file_put_contents($workspace . '/dest/theme.json', 'old-content');
file_put_contents($workspace . '/source/theme.json', 'new-content');
$manifest = PackageManifest::fromArray(['name' => 'x', 'version' => '1.0.0', 'files' => ['theme.json']]);
$installer->install($manifest, $workspace . '/source', $workspace . '/dest');
$scenarios++;
assertSame('new-content', file_get_contents($workspace . '/dest/theme.json'), 'install: retry overwrites a stale existing destination file', $failures);
rrmdir($workspace);

// 5. install(): missing source file throws and writes nothing at all
// (pre-flight, not partial — even the file that WAS present is not
// written).
$workspace = freshWorkspace();
file_put_contents($workspace . '/source/present.json', 'ok');
$manifest = PackageManifest::fromArray([
    'name' => 'x',
    'version' => '1.0.0',
    'files' => ['present.json', 'missing.json'],
]);
$threw = false;
try {
    $installer->install($manifest, $workspace . '/source', $workspace . '/dest');
} catch (\RuntimeException $e) {
    $threw = true;
    $scenarios++;
    assertTrue(strpos($e->getMessage(), 'missing.json') !== false, 'install: missing-source exception names the missing file', $failures);
}
$scenarios++;
assertTrue($threw, 'install: missing source file throws RuntimeException', $failures);
$scenarios++;
assertTrue(!is_file($workspace . '/dest/present.json'), 'install: pre-flight failure writes nothing, even files that were present', $failures);
rrmdir($workspace);

// 6. install(): a path containing a ".." segment throws
// InvalidArgumentException before anything is touched.
$workspace = freshWorkspace();
file_put_contents($workspace . '/source/theme.json', 'ok');
$manifest = PackageManifest::fromArray([
    'name' => 'x',
    'version' => '1.0.0',
    'files' => ['../../etc/passwd'],
]);
$threw = false;
try {
    $installer->install($manifest, $workspace . '/source', $workspace . '/dest');
} catch (\InvalidArgumentException $e) {
    $threw = true;
}
$scenarios++;
assertTrue($threw, 'install: a ".." path segment throws InvalidArgumentException', $failures);
rrmdir($workspace);

// 7. install(): removes() deletes a present destination file and reports
// it in removed[].
$workspace = freshWorkspace();
file_put_contents($workspace . '/dest/legacy.php', 'old code');
$manifest = PackageManifest::fromArray([
    'name' => 'x',
    'version' => '1.0.0',
    'files' => [],
    'removes' => ['legacy.php'],
]);
$result = $installer->install($manifest, $workspace . '/source', $workspace . '/dest');
$scenarios++;
assertTrue(!is_file($workspace . '/dest/legacy.php'), 'install: removes() deletes the file from destination', $failures);
$scenarios++;
assertSame(['legacy.php'], $result['removed'], 'install: removes() reports the deleted path', $failures);
rrmdir($workspace);

// 8. install(): removes() for an already-absent path is idempotent, not
// an error, and is not reported as removed.
$workspace = freshWorkspace();
$manifest = PackageManifest::fromArray([
    'name' => 'x',
    'version' => '1.0.0',
    'files' => [],
    'removes' => ['already-gone.php'],
]);
$result = $installer->install($manifest, $workspace . '/source', $workspace . '/dest');
$scenarios++;
assertSame([], $result['removed'], 'install: removing an already-absent path is a no-op, not reported', $failures);
rrmdir($workspace);

// 9. install(): empty files()/removes() returns empty installed/removed.
$workspace = freshWorkspace();
$manifest = PackageManifest::fromArray(['name' => 'x', 'version' => '1.0.0']);
$result = $installer->install($manifest, $workspace . '/source', $workspace . '/dest');
$scenarios++;
assertSame(['installed' => [], 'removed' => []], $result, 'install: empty manifest installs/removes nothing', $failures);
rrmdir($workspace);

// 10. missingRemoves(): reports exactly the removes() paths not present
// under destRoot.
$workspace = freshWorkspace();
file_put_contents($workspace . '/dest/present.php', 'x');
$manifest = PackageManifest::fromArray([
    'name' => 'x',
    'version' => '1.0.0',
    'removes' => ['present.php', 'absent.php'],
]);
$scenarios++;
assertSame(['absent.php'], PackageFileInstaller::missingRemoves($workspace . '/dest', $manifest), 'missingRemoves: reports only the absent removes() path', $failures);
rrmdir($workspace);

// 11. missingRemoves(): trailing slash on destRoot is tolerated (matches
// PackageVerifier::missingFiles()'s own tolerance).
$workspace = freshWorkspace();
file_put_contents($workspace . '/dest/present.php', 'x');
$manifest = PackageManifest::fromArray(['name' => 'x', 'version' => '1.0.0', 'removes' => ['present.php']]);
$scenarios++;
assertSame([], PackageFileInstaller::missingRemoves($workspace . '/dest/', $manifest), 'missingRemoves: trailing slash on destRoot is tolerated', $failures);
rrmdir($workspace);

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo 'PASS: PackageFileInstaller smoke test (' . $scenarios . " scenarios)\n";
exit(0);
