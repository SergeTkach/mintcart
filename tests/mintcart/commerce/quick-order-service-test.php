<?php
/**
 * Standalone smoke test for MintCart\Commerce\QuickOrderService.
 *
 * No PHPUnit/Composer in the repo yet, same as cart-service-test.php.
 * QuickOrderService has no OpenCart dependency by design, so this runs
 * entirely against plain arrays.
 *
 * Run: php tests/mintcart/commerce/quick-order-service-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/commerce/quickorderservice.php';

use MintCart\Commerce\QuickOrderService;

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

$service = new QuickOrderService();

// 1. A fully valid request (phone only) passes.
$result = $service->validate(['quantity' => '2', 'name' => 'Jane Doe', 'telephone' => '+1 555 0100', 'email' => '']);
assertTrue($result['valid'], 'validate: quantity+name+phone is valid', $failures);
assertSame([], $result['errors'], 'validate: no errors on a valid phone-only request', $failures);
assertSame(2, $result['quantity'], 'validate: quantity is cast to int', $failures);

// 2. A fully valid request (email only) passes.
$result = $service->validate(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
assertTrue($result['valid'], 'validate: name+email (no phone) is valid', $failures);
assertSame(1, $result['quantity'], 'validate: omitted quantity defaults to 1', $failures);

// 3. Missing name is an error.
$result = $service->validate(['name' => '', 'telephone' => '555-0100']);
assertTrue(!$result['valid'], 'validate: empty name is invalid', $failures);
assertSame('error_name', $result['errors']['name'] ?? null, 'validate: empty name reports error_name', $failures);

// 4. Whitespace-only name is treated as empty.
$result = $service->validate(['name' => '   ', 'telephone' => '555-0100']);
assertTrue(!$result['valid'], 'validate: whitespace-only name is invalid', $failures);

// 5. Neither phone nor email is an error.
$result = $service->validate(['name' => 'Jane Doe', 'telephone' => '', 'email' => '']);
assertTrue(!$result['valid'], 'validate: no phone and no email is invalid', $failures);
assertSame('error_contact', $result['errors']['contact'] ?? null, 'validate: missing contact reports error_contact', $failures);

// 6. An invalid email format is an error, even with a phone also present.
$result = $service->validate(['name' => 'Jane Doe', 'telephone' => '555-0100', 'email' => 'not-an-email']);
assertTrue(!$result['valid'], 'validate: malformed email is invalid', $failures);
assertSame('error_email', $result['errors']['contact'] ?? null, 'validate: malformed email reports error_email', $failures);

// 7. Zero/negative quantity is an error, clamped to 1 in the returned value.
$result = $service->validate(['quantity' => '0', 'name' => 'Jane Doe', 'telephone' => '555-0100']);
assertTrue(!$result['valid'], 'validate: quantity 0 is invalid', $failures);
assertSame('error_quantity', $result['errors']['quantity'] ?? null, 'validate: quantity 0 reports error_quantity', $failures);
assertSame(1, $result['quantity'], 'validate: invalid quantity is still clamped to 1 in the result', $failures);

// 8. A non-numeric quantity is an error.
$result = $service->validate(['quantity' => 'abc', 'name' => 'Jane Doe', 'telephone' => '555-0100']);
assertTrue(!$result['valid'], 'validate: non-numeric quantity is invalid', $failures);
assertSame('error_quantity', $result['errors']['quantity'] ?? null, 'validate: non-numeric quantity reports error_quantity', $failures);

// 9. Multiple errors can be reported at once.
$result = $service->validate(['quantity' => '-1', 'name' => '', 'telephone' => '', 'email' => '']);
assertSame(['quantity', 'name', 'contact'], array_keys($result['errors']), 'validate: multiple simultaneous errors are all reported', $failures);

// 10. Name/telephone/email are trimmed in the returned value.
$result = $service->validate(['name' => '  Jane Doe  ', 'telephone' => ' 555-0100 ', 'email' => '']);
assertSame('Jane Doe', $result['name'], 'validate: name is trimmed', $failures);
assertSame('555-0100', $result['telephone'], 'validate: telephone is trimmed', $failures);

if ($failures) {
    fwrite(STDERR, count($failures) . " failure(s):\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "- {$failure}\n");
    }
    exit(1);
}

echo "PASS: QuickOrderService smoke test (10 scenarios)\n";
exit(0);
