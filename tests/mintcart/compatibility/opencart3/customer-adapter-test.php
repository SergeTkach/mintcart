<?php
/**
 * Standalone smoke test for MintCart\Compatibility\OpenCart3\CustomerAdapter.
 *
 * No PHPUnit/Composer in the repo yet, no MySQL server available in this
 * environment (same pre-existing gap as every other test in this repo).
 * CustomerAdapter's constructor is deliberately duck-typed rather than
 * bound to either of OpenCart's two concrete customer model classes (see
 * the class's own docblock), so this exercises it against a minimal
 * in-memory double covering both models' shared surface — the same
 * "duck-typed OpenCart dependency" shape
 * tests/mintcart/compatibility/opencart3/settings-adapter-test.php
 * already established.
 *
 * Run: php tests/mintcart/compatibility/opencart3/customer-adapter-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../../upload/system/library/mintcart/compatibility/opencart3/customeradapter.php';

use MintCart\Compatibility\OpenCart3\CustomerAdapter;

/**
 * Duck-types OpenCart's customer models: getCustomer()/getCustomerByEmail()
 * only, backed by a plain in-memory array keyed by customer_id.
 *
 * Mirrors OpenCart's real "no match" behavior — an empty array, not null
 * or an exception — since that is exactly the shape CustomerAdapter exists
 * to normalize away.
 */
final class FakeCustomerModel
{
    /** @var array<int, array<string, mixed>> */
    public array $customers = [];

    public function getCustomer(int $customerId): array
    {
        return $this->customers[$customerId] ?? [];
    }

    public function getCustomerByEmail(string $email): array
    {
        foreach ($this->customers as $customer) {
            if (strtolower((string) $customer['email']) === strtolower($email)) {
                return $customer;
            }
        }

        return [];
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

// --- find() ---

$model = new FakeCustomerModel();
$model->customers[42] = ['customer_id' => 42, 'firstname' => 'Ada', 'email' => 'ada@example.com'];
$adapter = new CustomerAdapter($model);

assertSame(
    ['customer_id' => 42, 'firstname' => 'Ada', 'email' => 'ada@example.com'],
    $adapter->find(42),
    'find() returns the matching row',
    $failures
);

assertSame(null, $adapter->find(999), 'find() returns null for an unknown id', $failures);

// --- findByEmail() is case-insensitive, matching OpenCart's LCASE() lookup ---

assertSame(
    ['customer_id' => 42, 'firstname' => 'Ada', 'email' => 'ada@example.com'],
    $adapter->findByEmail('ADA@EXAMPLE.COM'),
    'findByEmail() matches case-insensitively',
    $failures
);

assertSame(null, $adapter->findByEmail('nobody@example.com'), 'findByEmail() returns null for an unregistered email', $failures);

// --- existsByEmail() ---

assertTrue($adapter->existsByEmail('ada@example.com'), 'existsByEmail() is true for a registered email', $failures);
assertTrue(!$adapter->existsByEmail('nobody@example.com'), 'existsByEmail() is false for an unregistered email', $failures);

// --- works identically against a model shaped like the storefront's account/customer model too ---

final class FakeAccountCustomerModel
{
    public function getCustomer(int $customerId): array
    {
        return $customerId === 7 ? ['customer_id' => 7, 'email' => 'guest@example.com'] : [];
    }

    public function getCustomerByEmail(string $email): array
    {
        return strtolower($email) === 'guest@example.com' ? ['customer_id' => 7, 'email' => 'guest@example.com'] : [];
    }
}

$storefrontAdapter = new CustomerAdapter(new FakeAccountCustomerModel());
assertSame(['customer_id' => 7, 'email' => 'guest@example.com'], $storefrontAdapter->find(7), 'works against a differently-shaped duck-typed model (storefront)', $failures);
assertTrue($storefrontAdapter->existsByEmail('guest@example.com'), 'existsByEmail() works against the storefront model shape too', $failures);

// --- results ---

if ($failures) {
    echo "FAILED (" . count($failures) . "):\n";
    foreach ($failures as $failure) {
        echo "  - {$failure}\n";
    }
    exit(1);
}

echo "OK: all CustomerAdapter assertions passed.\n";
exit(0);
