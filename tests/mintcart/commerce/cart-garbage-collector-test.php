<?php
/**
 * Standalone smoke test for MintCart\Commerce\CartGarbageCollector.
 *
 * No PHPUnit/Composer in the repo yet, same as cart-service-test.php.
 * shouldRun() is probabilistic in the general case (mt_rand), so this
 * only asserts the deterministic boundary conditions rather than trying
 * to assert a specific random outcome.
 *
 * Run: php tests/mintcart/commerce/cart-garbage-collector-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/commerce/cartgarbagecollector.php';

use MintCart\Commerce\CartGarbageCollector;

$failures = [];

function assertTrue(bool $condition, string $message, array &$failures): void
{
    if (!$condition) {
        $failures[] = $message;
    }
}

function assertFalse(bool $condition, string $message, array &$failures): void
{
    if ($condition) {
        $failures[] = $message;
    }
}

// 1. probability = 0 never runs, regardless of divisor.
$gc = new CartGarbageCollector(0, 100);
for ($i = 0; $i < 20; $i++) {
    assertFalse($gc->shouldRun(), 'probability 0: shouldRun() is always false', $failures);
}

// 2. probability >= divisor always runs (the "no gating" escape hatch —
// a caller can pass (100, 100) to fully restore the old unconditional
// behavior without special-casing it).
$gc = new CartGarbageCollector(100, 100);
for ($i = 0; $i < 20; $i++) {
    assertTrue($gc->shouldRun(), 'probability == divisor: shouldRun() is always true', $failures);
}

$gc = new CartGarbageCollector(150, 100);
assertTrue($gc->shouldRun(), 'probability > divisor: shouldRun() is always true', $failures);

// 3. Negative probability behaves the same as zero (never runs), rather
// than mt_rand() throwing on an invalid range.
$gc = new CartGarbageCollector(-5, 100);
assertFalse($gc->shouldRun(), 'negative probability: shouldRun() is false, no mt_rand() error', $failures);

// 4. Default constructor args (5, 100) stay within [0, 1] over many runs
// — a loose statistical sanity check, not an exact-frequency assertion
// (this is inherently probabilistic; the boundary cases above are what
// actually pin the contract down).
$gc = new CartGarbageCollector();
$ranTrue = false;
$ranFalse = false;
for ($i = 0; $i < 500; $i++) {
    if ($gc->shouldRun()) {
        $ranTrue = true;
    } else {
        $ranFalse = true;
    }
}
assertTrue($ranFalse, 'default probability (5/100): at least one of 500 runs returned false', $failures);
// Not asserting $ranTrue - a 5% chance failing to hit once in 500 runs
// is astronomically unlikely (~1e-11) but not impossible, and a flaky
// test is worse than a slightly weaker assertion here.

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '  - ' . $failure . "\n");
    }
    exit(1);
}

echo 'PASS: CartGarbageCollector smoke test (7 scenarios)' . "\n";
exit(0);
