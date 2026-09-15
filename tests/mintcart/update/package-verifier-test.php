<?php
/**
 * Standalone smoke test for MintCart\Update\PackageVerifier.
 *
 * No PHPUnit/Composer in the repo yet, same as every other tests/mintcart/
 * smoke test. PackageVerifier has no OpenCart dependency by design, so this
 * runs entirely against temp files created for the run.
 *
 * Run: php tests/mintcart/update/package-verifier-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/update/packagemanifest.php';
require __DIR__ . '/../../../upload/system/library/mintcart/update/packageverifier.php';

use MintCart\Update\PackageManifest;
use MintCart\Update\PackageVerifier;

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
$workspace = sys_get_temp_dir() . '/mintcart-package-verifier-test-' . uniqid();
mkdir($workspace, 0777, true);

// 1. verifyChecksum(): null/blank checksum means nothing to check — always
// true, even against a file that doesn't exist.
assertTrue(
    PackageVerifier::verifyChecksum($workspace . '/does-not-exist.zip', null),
    'verifyChecksum: null checksum is always true (nothing declared)',
    $failures
);
assertTrue(
    PackageVerifier::verifyChecksum($workspace . '/does-not-exist.zip', '  '),
    'verifyChecksum: blank checksum is always true (nothing declared)',
    $failures
);

// 2. verifyChecksum(): a real file matched against its own real sha256
// checksum passes.
$archivePath = $workspace . '/package.zip';
file_put_contents($archivePath, 'mintcart-update-package-contents');
$realChecksum = 'sha256:' . hash_file('sha256', $archivePath);
assertTrue(
    PackageVerifier::verifyChecksum($archivePath, $realChecksum),
    'verifyChecksum: matching sha256 checksum passes',
    $failures
);

// 3. verifyChecksum(): checksum comparison is case-insensitive (hex casing
// should not matter).
assertTrue(
    PackageVerifier::verifyChecksum($archivePath, 'sha256:' . strtoupper(hash_file('sha256', $archivePath))),
    'verifyChecksum: uppercase hex checksum still matches',
    $failures
);

// 4. verifyChecksum(): a wrong hash fails.
assertTrue(
    !PackageVerifier::verifyChecksum($archivePath, 'sha256:' . str_repeat('0', 64)),
    'verifyChecksum: mismatched checksum fails',
    $failures
);

// 5. verifyChecksum(): a missing file fails rather than throwing.
assertTrue(
    !PackageVerifier::verifyChecksum($workspace . '/does-not-exist.zip', $realChecksum),
    'verifyChecksum: missing file fails closed rather than passing',
    $failures
);

// 6. verifyChecksum(): a different supported algorithm (md5) also works.
$md5Checksum = 'md5:' . hash_file('md5', $archivePath);
assertTrue(
    PackageVerifier::verifyChecksum($archivePath, $md5Checksum),
    'verifyChecksum: md5 algorithm is also supported',
    $failures
);

// 7. verifyChecksum(): malformed checksum (no "algo:hash" separator) throws.
try {
    PackageVerifier::verifyChecksum($archivePath, 'deadbeef');
    $failures[] = 'verifyChecksum: malformed checksum (no separator) should throw InvalidArgumentException';
} catch (\InvalidArgumentException $exception) {
    // expected
}

// 8. verifyChecksum(): unsupported algorithm throws.
try {
    PackageVerifier::verifyChecksum($archivePath, 'not-a-real-algo:deadbeef');
    $failures[] = 'verifyChecksum: unsupported algorithm should throw InvalidArgumentException';
} catch (\InvalidArgumentException $exception) {
    // expected
}

// 9. missingFiles(): every manifest file present under baseDir reports no
// gaps.
$extractedDir = $workspace . '/extracted';
mkdir($extractedDir . '/catalog/view/theme/mint_default', 0777, true);
file_put_contents($extractedDir . '/catalog/view/theme/mint_default/theme.json', '{}');
mkdir($extractedDir . '/system/mintcart/migrations', 0777, true);
file_put_contents($extractedDir . '/system/mintcart/migrations/202608210001_create_mc_migration.php', '<?php');

$manifest = PackageManifest::fromArray([
    'name' => 'mint_default',
    'version' => '1.2.0',
    'files' => [
        'catalog/view/theme/mint_default/theme.json',
        'system/mintcart/migrations/202608210001_create_mc_migration.php',
    ],
]);

assertSame(
    [],
    PackageVerifier::missingFiles($extractedDir, $manifest),
    'missingFiles: every declared file present reports no gaps',
    $failures
);

// 10. missingFiles(): a declared file that is absent is reported.
$incompleteManifest = PackageManifest::fromArray([
    'name' => 'mint_default',
    'version' => '1.2.0',
    'files' => [
        'catalog/view/theme/mint_default/theme.json',
        'catalog/view/theme/mint_default/template/mint/components/product-card.twig',
    ],
]);

assertSame(
    ['catalog/view/theme/mint_default/template/mint/components/product-card.twig'],
    PackageVerifier::missingFiles($extractedDir, $incompleteManifest),
    'missingFiles: reports exactly the files that are missing',
    $failures
);

// 11. missingFiles(): a manifest declaring no files reports no gaps
// (nothing to check).
$emptyManifest = PackageManifest::fromArray(['name' => 'mint_default', 'version' => '1.0.0']);
assertSame(
    [],
    PackageVerifier::missingFiles($extractedDir, $emptyManifest),
    'missingFiles: manifest with no declared files reports no gaps',
    $failures
);

// 12. missingFiles(): a trailing slash on $baseDir does not break path
// joining.
assertSame(
    [],
    PackageVerifier::missingFiles($extractedDir . '/', $manifest),
    'missingFiles: trailing slash on baseDir is tolerated',
    $failures
);

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

echo 'PASS: PackageVerifier smoke test (' . 12 . " scenarios)\n";
exit(0);
