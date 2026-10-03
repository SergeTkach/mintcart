<?php
/**
 * Standalone smoke test for MintCart\Catalog\LimitOptionsBuilder.
 *
 * No PHPUnit/Composer in the repo yet, same as every other tests/mintcart/
 * smoke test. LimitOptionsBuilder has no OpenCart dependency by design, so
 * this runs entirely against plain scalars.
 *
 * Scenarios replay the exact `array_unique(array($themeLimit, 25, 50, 75,
 * 100)); sort();` combinations
 * `catalog/controller/product/category.php`,
 * `catalog/controller/product/manufacturer.php` and
 * `catalog/controller/product/search.php` already build inline, asserting
 * this class reproduces their existing output exactly (a refactor
 * extraction, not a behavior change).
 *
 * Run: php tests/mintcart/catalog/limit-options-builder-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/catalog/limitoptionsbuilder.php';

use MintCart\Catalog\LimitOptionsBuilder;

$failures = [];

function assertSame($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

$builder = new LimitOptionsBuilder();

// 1. Theme limit matches one of the fixed choices (typical default: 20 is
// NOT one of 25/50/75/100, but a theme could be configured to e.g. 50) —
// no duplicate entry.
assertSame([25, 50, 75, 100], $builder->build(50), 'theme limit 50 (a fixed choice): no duplicate', $failures);

// 2. Theme limit below the fixed range (the stock default theme's 20 is
// exactly this case): merged in and sorted first.
assertSame([20, 25, 50, 75, 100], $builder->build(20), 'theme limit 20: merged in ascending', $failures);

// 3. Theme limit above the fixed range: merged in and sorted last.
assertSame([25, 50, 75, 100, 150], $builder->build(150), 'theme limit 150: merged in ascending, last', $failures);

// 4. Theme limit between two fixed choices: merged in the middle.
assertSame([25, 40, 50, 75, 100], $builder->build(40), 'theme limit 40: merged in ascending, middle', $failures);

// 5. Result is a plain list (0-indexed, sequential keys) — the
// controller's `foreach ($limits as $value)` relies on this, and
// `array_unique()` alone would leave gapped keys.
$result = $builder->build(20);
assertSame(array_keys($result), range(0, count($result) - 1), 'result keys are sequential (list, not gapped)', $failures);

if ($failures) {
    echo "FAILED (" . count($failures) . "):\n";
    foreach ($failures as $failure) {
        echo "  - $failure\n";
    }
    exit(1);
}

echo "OK: LimitOptionsBuilder — all scenarios passed.\n";
exit(0);
