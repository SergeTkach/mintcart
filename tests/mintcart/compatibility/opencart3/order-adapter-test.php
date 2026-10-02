<?php
/**
 * Standalone smoke test for MintCart\Compatibility\OpenCart3\OrderAdapter.
 *
 * No PHPUnit/Composer in the repo yet, no MySQL server available in this
 * environment (same pre-existing gap as every other test in this repo).
 * OrderAdapter's constructor is deliberately duck-typed rather than bound
 * to any of OpenCart's three concrete order model classes (see the
 * class's own docblock), so this exercises it against three minimal
 * in-memory doubles, each returning a different "not found" sentinel
 * (`null`, `false`, `false`) — the exact divergence OrderAdapter exists to
 * normalize away. Same "duck-typed OpenCart dependency" shape
 * tests/mintcart/compatibility/opencart3/customer-adapter-test.php
 * already established.
 *
 * Run: php tests/mintcart/compatibility/opencart3/order-adapter-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../../upload/system/library/mintcart/compatibility/opencart3/orderadapter.php';

use MintCart\Compatibility\OpenCart3\OrderAdapter;

/** Mirrors admin/model/sale/order.php::getOrder() — returns null on no match. */
final class FakeAdminOrderModel
{
    /** @var array<int, array<string, mixed>> */
    public array $orders = [];

    public function getOrder(int $orderId)
    {
        return $this->orders[$orderId] ?? null;
    }
}

/** Mirrors catalog/model/checkout/order.php::getOrder() — returns false on no match. */
final class FakeCheckoutOrderModel
{
    /** @var array<int, array<string, mixed>> */
    public array $orders = [];

    public function getOrder(int $orderId)
    {
        return $this->orders[$orderId] ?? false;
    }
}

/**
 * Mirrors catalog/model/account/order.php::getOrder() — returns false on
 * no match, and (in production) additionally scopes to the current
 * customer; this double only needs to reproduce the sentinel, not the
 * session scoping.
 */
final class FakeAccountOrderModel
{
    /** @var array<int, array<string, mixed>> */
    public array $orders = [];

    public function getOrder(int $orderId)
    {
        return $this->orders[$orderId] ?? false;
    }
}

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

// --- find() / exists() against the admin model (null sentinel) ---

$adminModel = new FakeAdminOrderModel();
$adminModel->orders[100] = ['order_id' => 100, 'order_status_id' => 5, 'total' => 49.99];
$adminAdapter = new OrderAdapter($adminModel);

assertSame(
    ['order_id' => 100, 'order_status_id' => 5, 'total' => 49.99],
    $adminAdapter->find(100),
    'find() returns the matching row (admin model, null sentinel)',
    $failures
);
assertSame(null, $adminAdapter->find(999), 'find() returns null for an unknown id (admin model)', $failures);
assertTrue($adminAdapter->exists(100), 'exists() is true for a known order id (admin model)', $failures);
assertTrue(!$adminAdapter->exists(999), 'exists() is false for an unknown order id (admin model)', $failures);

// --- find() / exists() against the checkout model (false sentinel) ---

$checkoutModel = new FakeCheckoutOrderModel();
$checkoutModel->orders[200] = ['order_id' => 200, 'order_status_id' => 0, 'total' => 19.50];
$checkoutAdapter = new OrderAdapter($checkoutModel);

assertSame(
    ['order_id' => 200, 'order_status_id' => 0, 'total' => 19.50],
    $checkoutAdapter->find(200),
    'find() returns the matching row (checkout model, false sentinel)',
    $failures
);
assertSame(null, $checkoutAdapter->find(999), 'find() normalizes false to null for an unknown id (checkout model)', $failures);
assertTrue($checkoutAdapter->exists(200), 'exists() is true for a known order id (checkout model)', $failures);

// --- find() / exists() against the account model (false sentinel, customer-scoped in production) ---

$accountModel = new FakeAccountOrderModel();
$accountModel->orders[300] = ['order_id' => 300, 'customer_id' => 7, 'total' => 5.00];
$accountAdapter = new OrderAdapter($accountModel);

assertSame(
    ['order_id' => 300, 'customer_id' => 7, 'total' => 5.00],
    $accountAdapter->find(300),
    'find() returns the matching row (account model, false sentinel)',
    $failures
);
assertSame(null, $accountAdapter->find(1), 'find() returns null for an order outside the current customer scope (account model)', $failures);
assertTrue(!$accountAdapter->exists(1), 'exists() is false for an order outside the current customer scope (account model)', $failures);

// --- results ---

if ($failures) {
    echo "FAILED (" . count($failures) . "):\n";
    foreach ($failures as $failure) {
        echo "  - {$failure}\n";
    }
    exit(1);
}

echo "OK: all OrderAdapter assertions passed.\n";
exit(0);
