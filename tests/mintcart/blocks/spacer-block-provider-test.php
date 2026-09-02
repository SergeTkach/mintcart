<?php
/**
 * Standalone smoke test for MintCart\Blocks\SpacerBlockProvider.
 *
 * Pure normalization logic, no OpenCart/DB dependency - exercised directly
 * against build()'s input/output contract.
 *
 * Run: php tests/mintcart/blocks/spacer-block-provider-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/blocks/spacerblockprovider.php';

use MintCart\Blocks\SpacerBlockProvider;

/** @var string[] $failures */
$failures = [];

function assertSame____($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

$provider = new SpacerBlockProvider();

// 1. Empty settings -> documented default (md).
$result = $provider->build();

assertSame____('md', $result['size'], 'Scenario 1: no size setting should default to md', $failures);

// 2. Every allowed size passes through unchanged.
foreach (['sm', 'md', 'lg', 'xl'] as $size) {
    $result = $provider->build(['size' => $size]);

    assertSame____($size, $result['size'], "Scenario 2: a valid size setting ({$size}) should pass through unchanged", $failures);
}

// 3. An unrecognized/garbage size falls back to the default rather than
//    propagating an arbitrary string into the rendered CSS class.
$result = $provider->build(['size' => 'huge']);

assertSame____('md', $result['size'], 'Scenario 3: an unrecognized size value should fall back to the default (md)', $failures);

// 4. A non-string size (e.g. accidentally stored as a number) also falls
//    back rather than being coerced into something unexpected.
$result = $provider->build(['size' => 4]);

assertSame____('md', $result['size'], 'Scenario 4: a non-string size value should fall back to the default (md)', $failures);

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo "PASS: SpacerBlockProvider smoke test (4 scenarios)\n";
exit(0);
