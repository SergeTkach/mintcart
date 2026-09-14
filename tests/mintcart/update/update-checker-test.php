<?php
/**
 * Standalone smoke test for MintCart\Update\UpdateChecker.
 *
 * No PHPUnit/Composer in the repo yet, same as every other tests/mintcart/
 * smoke test. UpdateChecker has no OpenCart dependency by design, so this
 * runs entirely against plain arrays and a real MintCart\Core\Version.
 *
 * Run: php tests/mintcart/update/update-checker-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/update/packagemanifest.php';
require __DIR__ . '/../../../upload/system/library/mintcart/update/updatechecker.php';
require __DIR__ . '/../../../upload/system/library/mintcart/core/version.php';

use MintCart\Core\Version;
use MintCart\Update\PackageManifest;
use MintCart\Update\UpdateChecker;

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

$checker = new UpdateChecker();

// 1. A manifest newer than the installed version, with no requirements
// declared at all, is available and compatible (nothing to fail against).
$newer = PackageManifest::fromArray(['name' => 'mint_default', 'version' => '1.1.0']);
$result = $checker->check($newer, '1.0.0');
assertTrue($result['update_available'], '1. newer manifest: update_available', $failures);
assertTrue($result['compatible'], '1. no requirements: compatible', $failures);
assertTrue($result['should_apply'], '1. newer + compatible: should_apply', $failures);
assertSame('1.1.0', $result['manifest_version'], '1. manifest_version echoes manifest', $failures);
assertSame('1.0.0', $result['installed_version'], '1. installed_version echoes input', $failures);

// 2. A manifest at the same version as installed is not an update, even
// though it would otherwise be "compatible".
$same = PackageManifest::fromArray(['name' => 'mint_default', 'version' => '1.0.0']);
$result = $checker->check($same, '1.0.0');
assertTrue(!$result['update_available'], '2. same version: not update_available', $failures);
assertTrue(!$result['should_apply'], '2. same version: should_apply is false even though compatible', $failures);

// 3. A manifest older than installed is not an update (downgrades are not
// "available updates" from this step's point of view).
$older = PackageManifest::fromArray(['name' => 'mint_default', 'version' => '0.9.0']);
$result = $checker->check($older, '1.0.0');
assertTrue(!$result['update_available'], '3. older manifest: not update_available', $failures);
assertTrue(!$result['should_apply'], '3. older manifest: should_apply is false', $failures);

// 4. A newer manifest whose declared requirements are NOT satisfied by the
// passed-in installed versions is available but not compatible, and must
// not be flagged should_apply.
$incompatible = PackageManifest::fromArray([
    'name' => 'mint_default',
    'version' => '2.0.0',
    'requirements' => ['php_version' => '>=8.3.0'],
]);
$result = $checker->check($incompatible, '1.0.0', ['php_version' => '8.1.0']);
assertTrue($result['update_available'], '4. newer manifest: update_available', $failures);
assertTrue(!$result['compatible'], '4. unmet requirement: not compatible', $failures);
assertTrue(!$result['should_apply'], '4. update_available but incompatible: should_apply is false', $failures);

// 5. Same as #4 but the requirement IS satisfied: should_apply is true.
$result = $checker->check($incompatible, '1.0.0', ['php_version' => '8.3.2']);
assertTrue($result['should_apply'], '5. update_available and compatible: should_apply is true', $failures);

// 6. A requirement whose component is missing from installedVersions fails
// closed (PackageManifest::isCompatible()'s own contract), so should_apply
// stays false rather than silently passing.
$result = $checker->check($incompatible, '1.0.0', []);
assertTrue(!$result['compatible'], '6. missing installed component: fails closed', $failures);
assertTrue(!$result['should_apply'], '6. missing installed component: should_apply is false', $failures);

// 7. Real MintCart\Core\Version::VERSION ("0.1.0-dev") plugged in as the
// installed version, against a manifest declaring a caret requirement on
// itself — confirms this class composes correctly with the real Version
// class, not just synthetic version strings.
$coreUpdate = PackageManifest::fromArray([
    'name' => 'mintcart-core',
    'version' => '0.2.0',
    // '^0.1.0-dev', not '^0.1.0': version_compare() orders the "-dev"
    // pre-release suffix *before* its plain release (see
    // package-manifest-test.php scenario 9), so 0.1.0-dev does not itself
    // satisfy '^0.1.0' — it satisfies '^0.1.0-dev'.
    'requirements' => ['mint_version' => '^0.1.0-dev'],
]);
$result = $checker->check($coreUpdate, Version::VERSION, ['mint_version' => Version::VERSION]);
assertTrue($result['update_available'], '7. real Version: 0.2.0 is newer than 0.1.0-dev', $failures);
assertTrue($result['compatible'], '7. real Version: 0.1.0-dev satisfies ^0.1.0-dev', $failures);
assertTrue($result['should_apply'], '7. real Version: should_apply is true', $failures);

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo 'PASS: UpdateChecker smoke test (' . 7 . " scenarios)\n";
exit(0);
