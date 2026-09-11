<?php
/**
 * Standalone smoke test for MintCart\Compatibility\OpenCart3\CategoryAdapter.
 *
 * No PHPUnit/Composer in the repo yet, no MySQL server available in this
 * environment (same pre-existing gap as every other test in this repo).
 * CategoryAdapter's constructor is deliberately duck-typed rather than
 * bound to either of OpenCart's two concrete category model classes (see
 * the class's own docblock), so this exercises it against a minimal
 * in-memory double covering the admin model's shared surface — the same
 * "duck-typed OpenCart dependency" shape
 * tests/mintcart/compatibility/opencart3/customer-adapter-test.php
 * already established.
 *
 * Run: php tests/mintcart/compatibility/opencart3/category-adapter-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../../upload/system/library/mintcart/compatibility/opencart3/categoryadapter.php';

use MintCart\Compatibility\OpenCart3\CategoryAdapter;

/**
 * Duck-types the admin ModelCatalogCategory: getCategory()/getCategoryPath()
 * only, backed by plain in-memory arrays keyed by category_id.
 *
 * getCategory() mirrors OpenCart's real "no match" behavior — an empty
 * array, never null/false — since that is exactly the shape CategoryAdapter
 * exists to normalize away. getCategoryPath() mirrors the real
 * category_path closure-table shape: one row per ancestor (including the
 * category itself), each with a path_id and a level.
 */
final class FakeCategoryModel
{
    /** @var array<int, array<string, mixed>> */
    public array $categories = [];

    /** @var array<int, list<array{category_id: int, path_id: int, level: int}>> */
    public array $paths = [];

    public function getCategory(int $categoryId): array
    {
        return $this->categories[$categoryId] ?? [];
    }

    public function getCategoryPath(int $categoryId): array
    {
        return $this->paths[$categoryId] ?? [];
    }
}

$failures = [];

function assertTrueCategoryAdapter(bool $condition, string $message, array &$failures): void
{
    if (!$condition) {
        $failures[] = $message;
    }
}

function assertSameCategoryAdapter($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

// --- find() / exists() ---

$model = new FakeCategoryModel();
$model->categories[5] = ['category_id' => 5, 'name' => 'Laptops', 'parent_id' => 0];
$adapter = new CategoryAdapter($model);

assertSameCategoryAdapter(
    ['category_id' => 5, 'name' => 'Laptops', 'parent_id' => 0],
    $adapter->find(5),
    'find() returns the matching row',
    $failures
);
assertSameCategoryAdapter(null, $adapter->find(999), 'find() returns null for an unknown id', $failures);

assertTrueCategoryAdapter($adapter->exists(5), 'exists() is true for a known id', $failures);
assertTrueCategoryAdapter(!$adapter->exists(999), 'exists() is false for an unknown id', $failures);

// --- getAncestorPathIds() ---

// Category 5 (Laptops) lives under 2 (Computers) under 1 (Electronics) —
// getCategoryPath(5) returns the full chain, self included, per OpenCart's
// real category_path closure table shape.
$model->paths[5] = [
    ['category_id' => 5, 'path_id' => 1, 'level' => 0],
    ['category_id' => 5, 'path_id' => 2, 'level' => 1],
    ['category_id' => 5, 'path_id' => 5, 'level' => 2],
];

assertSameCategoryAdapter(
    [1, 2, 5],
    $adapter->getAncestorPathIds(5),
    'getAncestorPathIds() flattens getCategoryPath() rows into a plain int list, in order',
    $failures
);

// Top-level category (parent_id = 0) — no ancestor chain, matches
// ControllerCatalogCategory::move()'s own "pass [] when parent_id is 0"
// caller convention (this class doesn't special-case 0 itself; the caller
// simply never calls getAncestorPathIds(0) in that case, same as today's
// inline loop guarded by `if ($parent_id) { ... }`).
assertSameCategoryAdapter(
    [],
    $adapter->getAncestorPathIds(42),
    'getAncestorPathIds() returns an empty list when the model has no path rows for the id',
    $failures
);

// path_id values coming back as numeric strings (a raw DB row, before any
// int cast) must still normalize to real ints — CategoryMoveValidator's
// own cycle check relies on strict (===) int comparison against
// $categoryId.
$model->paths[7] = [
    ['category_id' => 7, 'path_id' => '3', 'level' => 0],
    ['category_id' => 7, 'path_id' => '7', 'level' => 1],
];
assertSameCategoryAdapter(
    [3, 7],
    $adapter->getAncestorPathIds(7),
    'getAncestorPathIds() casts path_id to int even when the underlying row has it as a numeric string',
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

echo "OK: all CategoryAdapter assertions passed.\n";
exit(0);
