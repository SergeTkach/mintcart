<?php
/**
 * Standalone smoke test for MintCart\Update\PackageManifest.
 *
 * No PHPUnit/Composer in the repo yet, same as every other tests/mintcart/
 * smoke test. PackageManifest has no OpenCart dependency by design, so this
 * runs entirely against plain arrays.
 *
 * Run: php tests/mintcart/update/package-manifest-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/update/packagemanifest.php';

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

// 1. A minimal, valid manifest (only the required fields) parses cleanly,
// with the optional fields defaulting to empty/null.
$minimal = PackageManifest::fromArray(['name' => 'mint_default', 'version' => '1.0.0']);
assertSame('mint_default', $minimal->name(), 'minimal: name', $failures);
assertSame('1.0.0', $minimal->version(), 'minimal: version', $failures);
assertSame([], $minimal->requirements(), 'minimal: requirements defaults to empty', $failures);
assertSame([], $minimal->files(), 'minimal: files defaults to empty', $failures);
assertSame([], $minimal->removes(), 'minimal: removes defaults to empty', $failures);
assertSame([], $minimal->migrations(), 'minimal: migrations defaults to empty', $failures);
assertSame(null, $minimal->checksum(), 'minimal: checksum defaults to null', $failures);

// 2. A full manifest round-trips through toArray() unchanged.
$fullData = [
    'name' => 'mint_default',
    'version' => '1.2.0',
    'requirements' => ['mint_version' => '^0.1.0', 'php_version' => '>=8.1.0'],
    'files' => ['catalog/view/theme/mint_default/theme.json'],
    'removes' => ['catalog/view/theme/mint_default/template/mint/components/old-card.twig'],
    'migrations' => ['202608210001_create_mc_migration.php'],
    'checksum' => 'sha256:deadbeef',
];
$full = PackageManifest::fromArray($fullData);
assertSame($fullData, $full->toArray(), 'full: toArray round-trips fromArray input', $failures);
assertSame($fullData['removes'], $full->removes(), 'full: removes() returns the declared list', $failures);

// 3. Missing name/version both fail validation, and both problems are
// reported together (not just the first).
$errors = PackageManifest::validate([]);
assertTrue(count($errors) === 2, 'validate: missing name+version reports 2 errors, got ' . count($errors), $failures);

try {
    PackageManifest::fromArray([]);
    $failures[] = 'fromArray: empty array should throw InvalidArgumentException';
} catch (\InvalidArgumentException $e) {
    assertTrue(strpos($e->getMessage(), 'name') !== false, 'fromArray: exception mentions name', $failures);
    assertTrue(strpos($e->getMessage(), 'version') !== false, 'fromArray: exception mentions version', $failures);
}

// 4. requirements/files/removes/migrations must be arrays when present.
$typeErrors = PackageManifest::validate(['name' => 'x', 'version' => '1.0.0', 'files' => 'not-an-array']);
assertTrue(count($typeErrors) === 1, 'validate: non-array files is rejected', $failures);

$removesTypeErrors = PackageManifest::validate(['name' => 'x', 'version' => '1.0.0', 'removes' => 'not-an-array']);
assertTrue(count($removesTypeErrors) === 1, 'validate: non-array removes is rejected', $failures);

// 5. A requirement constraint that is not a non-empty string is rejected.
$badRequirement = PackageManifest::validate([
    'name' => 'x',
    'version' => '1.0.0',
    'requirements' => ['mint_version' => ''],
]);
assertTrue(count($badRequirement) === 1, 'validate: empty requirement constraint is rejected', $failures);

// 6. satisfiesConstraint(): exact match (no operator prefix).
assertTrue(PackageManifest::satisfiesConstraint('1.0.0', '1.0.0'), 'exact: 1.0.0 satisfies 1.0.0', $failures);
assertTrue(!PackageManifest::satisfiesConstraint('1.0.1', '1.0.0'), 'exact: 1.0.1 does not satisfy 1.0.0', $failures);

// 7. satisfiesConstraint(): comparison operators.
assertTrue(PackageManifest::satisfiesConstraint('1.2.0', '>=1.0.0'), '>=: 1.2.0 satisfies >=1.0.0', $failures);
assertTrue(!PackageManifest::satisfiesConstraint('0.9.0', '>=1.0.0'), '>=: 0.9.0 does not satisfy >=1.0.0', $failures);
assertTrue(PackageManifest::satisfiesConstraint('1.0.0', '<=1.0.0'), '<=: 1.0.0 satisfies <=1.0.0', $failures);
assertTrue(PackageManifest::satisfiesConstraint('0.9.9', '<1.0.0'), '<: 0.9.9 satisfies <1.0.0', $failures);
assertTrue(PackageManifest::satisfiesConstraint('1.0.1', '>1.0.0'), '>: 1.0.1 satisfies >1.0.0', $failures);

// 8. satisfiesConstraint(): caret ranges, including the 0.x "only the
// rightmost nonzero component may float" semver rule.
assertTrue(PackageManifest::satisfiesConstraint('1.9.0', '^1.2.3'), 'caret: 1.9.0 satisfies ^1.2.3', $failures);
assertTrue(!PackageManifest::satisfiesConstraint('2.0.0', '^1.2.3'), 'caret: 2.0.0 does not satisfy ^1.2.3', $failures);
assertTrue(!PackageManifest::satisfiesConstraint('1.2.2', '^1.2.3'), 'caret: 1.2.2 does not satisfy ^1.2.3 (below floor)', $failures);
assertTrue(PackageManifest::satisfiesConstraint('0.2.9', '^0.2.3'), 'caret: 0.2.9 satisfies ^0.2.3', $failures);
assertTrue(!PackageManifest::satisfiesConstraint('0.3.0', '^0.2.3'), 'caret: 0.3.0 does not satisfy ^0.2.3 (0.x minor is the ceiling)', $failures);
assertTrue(!PackageManifest::satisfiesConstraint('0.0.4', '^0.0.3'), 'caret: 0.0.4 does not satisfy ^0.0.3 (0.0.x patch is the ceiling)', $failures);
assertTrue(PackageManifest::satisfiesConstraint('0.0.3', '^0.0.3'), 'caret: 0.0.3 satisfies ^0.0.3 exactly', $failures);

// 9. Real MintCart\Core\Version::VERSION ("0.1.0-dev") against a realistic
// requirement, confirming version_compare()'s pre-release ordering is what
// this class actually relies on.
assertTrue(PackageManifest::satisfiesConstraint('0.1.0-dev', '^0.1.0-dev'), 'real version: 0.1.0-dev satisfies ^0.1.0-dev (itself)', $failures);
assertTrue(!PackageManifest::satisfiesConstraint('0.1.0-dev', '>=0.1.0'), 'real version: 0.1.0-dev is ordered before the 0.1.0 release per version_compare()', $failures);

// 10. isCompatibleWith(): a component with no declared requirement is
// always compatible; a declared one is checked via satisfiesConstraint().
$manifest = PackageManifest::fromArray([
    'name' => 'mint_default',
    'version' => '1.0.0',
    'requirements' => ['mint_version' => '^0.1.0'],
]);
assertTrue($manifest->isCompatibleWith('mint_version', '0.1.5'), 'isCompatibleWith: 0.1.5 satisfies declared ^0.1.0', $failures);
assertTrue(!$manifest->isCompatibleWith('mint_version', '0.0.9'), 'isCompatibleWith: 0.0.9 does not satisfy declared ^0.1.0', $failures);
assertTrue($manifest->isCompatibleWith('php_version', '8.3.0'), 'isCompatibleWith: undeclared component is always compatible', $failures);

// 11. isCompatible(): every declared requirement must be satisfied, and a
// required-but-missing installed version fails closed rather than passing
// silently.
assertTrue($manifest->isCompatible(['mint_version' => '0.1.2']), 'isCompatible: all requirements satisfied', $failures);
assertTrue(!$manifest->isCompatible(['mint_version' => '0.0.1']), 'isCompatible: unsatisfied requirement fails', $failures);
assertTrue(!$manifest->isCompatible([]), 'isCompatible: missing installed version for a declared requirement fails closed', $failures);

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo 'PASS: PackageManifest smoke test (' . 11 . " scenarios, now covering removes())\n";
exit(0);
