<?php
/**
 * Standalone smoke test for MintCart\Compatibility\OpenCart3\ProductAdapter.
 *
 * No PHPUnit/Composer in the repo yet, no MySQL server available in this
 * environment (same pre-existing gap as every other test in this repo).
 * ProductAdapter's constructor is deliberately duck-typed rather than
 * bound to either of OpenCart's two concrete product model classes (see
 * the class's own docblock), so this exercises it against a minimal
 * in-memory double covering the shared getProduct() surface, the same
 * "duck-typed OpenCart dependency" shape
 * tests/mintcart/compatibility/opencart3/category-adapter-test.php
 * already established.
 *
 * Run: php tests/mintcart/compatibility/opencart3/product-adapter-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../../upload/system/library/mintcart/compatibility/opencart3/productadapter.php';

use MintCart\Compatibility\OpenCart3\ProductAdapter;

/**
 * Duck-types OpenCart's product model: getProduct() only, backed by a
 * plain in-memory array keyed by product_id.
 *
 * getProduct() mirrors OpenCart's real "no match" behavior — an empty
 * array, never null/false — since that is exactly the shape ProductAdapter
 * exists to normalize away.
 */
final class FakeProductModel
{
    /** @var array<int, array<string, mixed>> */
    public array $products = [];

    public function getProduct(int $productId): array
    {
        return $this->products[$productId] ?? [];
    }
}

$failures = [];

function assertTrueProductAdapter(bool $condition, string $message, array &$failures): void
{
    if (!$condition) {
        $failures[] = $message;
    }
}

function assertSameProductAdapter($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

// --- find() / exists() ---

$model = new FakeProductModel();
$model->products[42] = ['product_id' => 42, 'name' => 'iPhone', 'price' => '500.0000'];
$adapter = new ProductAdapter($model);

assertSameProductAdapter(
    ['product_id' => 42, 'name' => 'iPhone', 'price' => '500.0000'],
    $adapter->find(42),
    'find() returns the matching row',
    $failures
);
assertSameProductAdapter(null, $adapter->find(999), 'find() returns null for an unknown id', $failures);
assertSameProductAdapter(null, $adapter->find(0), 'find() returns null for id 0 (never a real product_id)', $failures);

assertTrueProductAdapter($adapter->exists(42), 'exists() is true for a known id', $failures);
assertTrueProductAdapter(!$adapter->exists(999), 'exists() is false for an unknown id', $failures);
assertTrueProductAdapter(!$adapter->exists(0), 'exists() is false for id 0', $failures);

// A second known product, to confirm find() isn't just returning the
// first/only row it happens to have.
$model->products[7] = ['product_id' => 7, 'name' => 'Samsung Galaxy Tab', 'price' => '199.9900'];

assertSameProductAdapter(
    ['product_id' => 7, 'name' => 'Samsung Galaxy Tab', 'price' => '199.9900'],
    $adapter->find(7),
    'find() returns the correct row when the model holds multiple products',
    $failures
);

// --- results ---

if ($failures) {
    echo 'FAILED (' . count($failures) . "):\n";
    foreach ($failures as $failure) {
        echo "  - {$failure}\n";
    }
    exit(1);
}

echo "OK: all ProductAdapter assertions passed.\n";
exit(0);
