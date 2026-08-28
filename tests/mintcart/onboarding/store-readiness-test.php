<?php
/**
 * Standalone smoke test for MintCart\Onboarding\StoreReadiness.
 *
 * No PHPUnit/Composer in the repo yet, same as cart-service-test.php.
 * StoreReadiness has no OpenCart dependency by design, so this runs
 * entirely against plain arrays.
 *
 * Run: php tests/mintcart/onboarding/store-readiness-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/onboarding/storereadiness.php';

use MintCart\Onboarding\StoreReadiness;

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

$readiness = new StoreReadiness();

// 1. No signals at all: 0%, every check incomplete, not test-order ready.
$result = $readiness->evaluate([]);
assertSame(0, $result['percent'], 'evaluate: empty signals -> 0%', $failures);
assertSame(7, count($result['checks']), 'evaluate: checklist always has all 7 items, even when unset', $failures);
assertTrue(!$result['ready_for_test_order'], 'evaluate: empty signals -> not ready for a test order', $failures);

foreach ($result['checks'] as $check) {
    assertSame(false, $check['complete'], "evaluate: '{$check['key']}' is incomplete when signal is absent", $failures);
}

// 2. Every signal true: 100%, every check complete, ready for a test order.
$allTrue = [
    'logo' => true,
    'contacts' => true,
    'language' => true,
    'currency' => true,
    'shipping' => true,
    'payment' => true,
    'first_product' => true,
];
$result = $readiness->evaluate($allTrue);
assertSame(100, $result['percent'], 'evaluate: all signals true -> 100%', $failures);
assertTrue($result['ready_for_test_order'], 'evaluate: all signals true -> ready for a test order', $failures);

foreach ($result['checks'] as $check) {
    assertSame(true, $check['complete'], "evaluate: '{$check['key']}' is complete when signal is true", $failures);
}

// 3. Percentage is derived, not hardcoded: exactly 3 of 7 complete.
$result = $readiness->evaluate([
    'logo' => true,
    'contacts' => true,
    'language' => true,
]);
assertSame((int) round(3 / 7 * 100), $result['percent'], 'evaluate: percent is derived from the complete/total ratio (3 of 7)', $failures);
assertTrue(!$result['ready_for_test_order'], 'evaluate: 3-of-7 partial readiness is still not test-order ready', $failures);

// 4. Overall percent can be 100% while test-order readiness is still false
//    (a store can look fully configured but still lack a purchasable
//    product or a shipping/payment method to actually complete checkout).
$result = $readiness->evaluate([
    'logo' => true,
    'contacts' => true,
    'language' => true,
    'currency' => true,
    // no shipping, payment, or first_product
]);
assertSame((int) round(4 / 7 * 100), $result['percent'], 'evaluate: 4-of-7 percent', $failures);
assertTrue(!$result['ready_for_test_order'], 'evaluate: missing shipping/payment/product -> not test-order ready even at partial percent', $failures);

// 5. ready_for_test_order requires ALL of first_product + shipping + payment,
//    not just one of them.
$result = $readiness->evaluate(['first_product' => true, 'shipping' => true]);
assertTrue(!$result['ready_for_test_order'], 'evaluate: first_product + shipping without payment -> not test-order ready', $failures);

$result = $readiness->evaluate(['first_product' => true, 'shipping' => true, 'payment' => true]);
assertTrue($result['ready_for_test_order'], 'evaluate: first_product + shipping + payment -> test-order ready regardless of the other 4 checks', $failures);

// 6. Checklist order is fixed (Functional Scope §2's listed order) and each
//    entry carries its key.
$keys = array_column($result['checks'], 'key');
assertSame(
    ['logo', 'contacts', 'language', 'currency', 'shipping', 'payment', 'first_product'],
    $keys,
    'evaluate: checklist keys are returned in the documented fixed order',
    $failures
);

// 7. Label overrides replace the default label but not the key/complete
//    contract, and unknown override keys are ignored.
$result = $readiness->evaluate(['logo' => true], ['logo' => 'Логотип завантажено', 'unknown_key' => 'ignored']);
$logoCheck = $result['checks'][0];
assertSame('logo', $logoCheck['key'], 'evaluate: label override does not change the key', $failures);
assertSame('Логотип завантажено', $logoCheck['label'], 'evaluate: label override replaces the default label', $failures);
assertSame(true, $logoCheck['complete'], 'evaluate: label override does not change completeness', $failures);

if ($failures) {
    fwrite(STDERR, count($failures) . " failure(s):\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "- {$failure}\n");
    }
    exit(1);
}

echo "PASS: StoreReadiness smoke test (7 scenarios)\n";
exit(0);
