<?php
/**
 * Standalone smoke test for MintCart\Commerce\QuickOrderStatus.
 *
 * No PHPUnit/Composer in the repo yet, same as every other tests/mintcart/
 * file. QuickOrderStatus has no OpenCart dependency by design.
 *
 * Run: php tests/mintcart/commerce/quick-order-status-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/commerce/quickorderstatus.php';

use MintCart\Commerce\QuickOrderStatus;

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

// 1. Every documented status is valid.
foreach (['new', 'contacted', 'converted', 'rejected'] as $status) {
    assertTrue(QuickOrderStatus::isValid($status), "isValid: \"{$status}\" is a recognised status", $failures);
}

// 2. STATUSES matches the same four values, in a stable order (admin
//    <select> options render in this order).
assertSame(['new', 'contacted', 'converted', 'rejected'], QuickOrderStatus::STATUSES, 'STATUSES: exact four-value list', $failures);

// 3. Unknown/garbage/empty/case-mismatched values are rejected.
assertTrue(!QuickOrderStatus::isValid('done'), 'isValid: "done" is not a recognised status', $failures);
assertTrue(!QuickOrderStatus::isValid(''), 'isValid: blank is not a recognised status', $failures);
assertTrue(!QuickOrderStatus::isValid('New'), 'isValid: status check is case-sensitive', $failures);
assertTrue(!QuickOrderStatus::isValid('0'), 'isValid: "0" is not a recognised status', $failures);

// 4. Class constants match the string values the DB migration/controller
//    round-trip (a typo here would silently desync the constant from the
//    actual stored value).
assertSame('new', QuickOrderStatus::NEW, 'NEW constant value', $failures);
assertSame('contacted', QuickOrderStatus::CONTACTED, 'CONTACTED constant value', $failures);
assertSame('converted', QuickOrderStatus::CONVERTED, 'CONVERTED constant value', $failures);
assertSame('rejected', QuickOrderStatus::REJECTED, 'REJECTED constant value', $failures);

// 5. labelKey() builds the admin language key each status maps to.
assertSame('text_status_new', QuickOrderStatus::labelKey('new'), 'labelKey: new', $failures);
assertSame('text_status_contacted', QuickOrderStatus::labelKey('contacted'), 'labelKey: contacted', $failures);
assertSame('text_status_converted', QuickOrderStatus::labelKey('converted'), 'labelKey: converted', $failures);
assertSame('text_status_rejected', QuickOrderStatus::labelKey('rejected'), 'labelKey: rejected', $failures);

if ($failures) {
    fwrite(STDERR, count($failures) . " failure(s):\n");

    foreach ($failures as $failure) {
        fwrite(STDERR, "  - {$failure}\n");
    }

    exit(1);
}

echo "quick-order-status-test.php: all assertions passed\n";
exit(0);
