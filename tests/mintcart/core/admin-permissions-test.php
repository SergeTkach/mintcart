<?php
/**
 * Standalone smoke test for MintCart\Core\AdminPermissions::routes().
 *
 * Run: php tests/mintcart/core/admin-permissions-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/core/adminpermissions.php';

use MintCart\Core\AdminPermissions;

$failures = [];

function assertSame($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

// 1. Top-level controller: one route.
assertSame(['mintcart/wizard'], AdminPermissions::routes(['mintcart/wizard.php']), 'top-level file', $failures);

// 2. Nested controller: full route + the two-part route startup/permission checks.
assertSame(
    ['mintcart/business', 'mintcart/business/delivery'],
    AdminPermissions::routes(['mintcart/business/delivery.php']),
    'nested file gets both routes',
    $failures
);

// 3. Windows separators, duplicates collapse, sorted.
assertSame(
    ['mintcart/business', 'mintcart/business/contacts', 'mintcart/business/delivery', 'mintcart/wizard'],
    AdminPermissions::routes(['mintcart\\wizard.php', 'mintcart/business/delivery.php', 'mintcart\\business\\contacts.php']),
    'separators normalized, unique, sorted',
    $failures
);

// 4. Nothing in, nothing out.
assertSame([], AdminPermissions::routes([]), 'empty', $failures);

if ($failures) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: $failure\n");
    }
    exit(1);
}

echo "AdminPermissions: all tests passed\n";
