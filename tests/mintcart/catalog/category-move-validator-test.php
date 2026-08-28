<?php
/**
 * Standalone smoke test for MintCart\Catalog\CategoryMoveValidator.
 *
 * Pure cycle-prevention logic, no OpenCart/\DB dependency - exercised
 * directly against validate()'s input/output contract, same style as
 * every other tests/mintcart/**-test.php file (no PHPUnit/Composer in
 * this repo).
 *
 * Run: php tests/mintcart/catalog/category-move-validator-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/catalog/categorymovevalidator.php';

use MintCart\Catalog\CategoryMoveValidator;

$failures = [];

function assertSameCategoryMove($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

$validator = new CategoryMoveValidator();

// 1. Moving to top-level (parent_id 0) is always valid - empty ancestor list.
$result = $validator->validate(25, 0, []);
assertSameCategoryMove(null, $result, 'Scenario 1: moving to top-level (no ancestors) is valid', $failures);

// 2. Moving under an unrelated category is valid - the target's ancestor
//    chain (itself + its own ancestors) never contains the category being
//    moved.
$result = $validator->validate(25, 33, [33, 20]);
assertSameCategoryMove(null, $result, 'Scenario 2: moving under an unrelated category is valid', $failures);

// 3. A category cannot become its own parent, even before checking the
//    ancestor chain.
$result = $validator->validate(25, 25, [25]);
assertSameCategoryMove(
    'A category cannot become its own parent.',
    $result,
    'Scenario 3: category_id === parent_id is rejected',
    $failures
);

// 4. Direct cycle: moving a category to be a child of its own direct child.
//    The child's ancestor chain (getCategoryPath($newParentId)) includes
//    the category being moved.
$result = $validator->validate(20, 25, [25, 20]);
assertSameCategoryMove(
    'The parent category you have chosen is a child of the current one!',
    $result,
    'Scenario 4: moving into a direct child is rejected (matches ControllerCatalogCategory::validateForm()\'s error_parent wording)',
    $failures
);

// 5. Indirect cycle: moving a category to be a child of a grandchild -
//    still rejected, the grandchild's full ancestor chain is checked, not
//    just its immediate parent.
$result = $validator->validate(20, 40, [40, 25, 20]);
assertSameCategoryMove(
    'The parent category you have chosen is a child of the current one!',
    $result,
    'Scenario 5: moving into an indirect descendant (grandchild) is rejected',
    $failures
);

// 6. Ancestor path_id values arriving as numeric strings (real
//    ModelCatalogCategory::getCategoryPath() rows are unfiltered DB
//    strings) are still compared correctly.
$result = $validator->validate(20, 25, ['25', '20']);
assertSameCategoryMove(
    'The parent category you have chosen is a child of the current one!',
    $result,
    'Scenario 6: numeric-string ancestor path_id values are still matched (loose DB row shape)',
    $failures
);

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo 'PASS: CategoryMoveValidator smoke test (6 scenarios)' . "\n";
exit(0);
