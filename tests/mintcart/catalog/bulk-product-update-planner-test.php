<?php
/**
 * Standalone smoke test for MintCart\Catalog\BulkProductUpdatePlanner.
 *
 * Pure validation/chunking logic, no OpenCart/\DB dependency - exercised
 * directly against plan()/nextBatch()'s input/output contract, same style
 * as every other tests/mintcart/**-test.php file (no PHPUnit/Composer in
 * this repo).
 *
 * Run: php tests/mintcart/catalog/bulk-product-update-planner-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/catalog/bulkproductupdateplanner.php';

use MintCart\Catalog\BulkProductUpdatePlanner;

$failures = [];

function assertBulkPlan($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

function assertBulkPlanTrue($condition, string $message, array &$failures): void
{
    if (!$condition) {
        $failures[] = $message . ' (expected truthy condition)';
    }
}

$planner = new BulkProductUpdatePlanner();

// 1. No products selected -> rejected, no chunks.
$plan = $planner->plan([], ['status' => 1]);
assertBulkPlanTrue(in_array('Select at least one product.', $plan['errors'], true), 'Scenario 1: empty product list is rejected', $failures);
assertBulkPlan([], $plan['chunks'], 'Scenario 1: no chunks on error', $failures);

// 2. No fields chosen -> rejected.
$plan = $planner->plan([1, 2], []);
assertBulkPlanTrue(in_array('Choose at least one field to change.', $plan['errors'], true), 'Scenario 2: empty field list is rejected', $failures);

// 3. Unknown field name -> rejected, with the field named in the message.
$plan = $planner->plan([1], ['name' => 'New name']);
assertBulkPlanTrue(
    (bool) preg_grep('/"name" is not a bulk-editable field\./', $plan['errors']),
    'Scenario 3: unknown field is rejected by name',
    $failures
);

// 4. Valid single-field update: status.
$plan = $planner->plan([5, 5, '7', 0, -3], ['status' => '1']);
assertBulkPlan([], $plan['errors'], 'Scenario 4: valid status update has no errors', $failures);
assertBulkPlan([5, 7], $plan['product_ids'], 'Scenario 4: product IDs deduped, non-positive/zero entries dropped', $failures);
assertBulkPlan(['status' => 1], $plan['fields'], 'Scenario 4: status sanitized to int 1', $failures);
assertBulkPlan(2, $plan['total'], 'Scenario 4: total reflects normalized product count', $failures);

// 5. Invalid field values are each reported, valid ones still sanitized.
$plan = $planner->plan([1], ['quantity' => -5, 'price' => 19.99, 'stock_status_id' => 0]);
assertBulkPlanTrue(
    (bool) preg_grep('/^quantity: /', $plan['errors']),
    'Scenario 5: negative quantity is rejected',
    $failures
);
assertBulkPlanTrue(
    (bool) preg_grep('/^stock_status_id: /', $plan['errors']),
    'Scenario 5: stock_status_id of 0 is rejected (must be >= 1)',
    $failures
);
assertBulkPlanTrue(!isset($plan['fields']['quantity']), 'Scenario 5: rejected field is not present in sanitized output', $failures);

// 6. special_price accepts null (meaning "remove the special price").
$plan = $planner->plan([1], ['special_price' => null]);
assertBulkPlan([], $plan['errors'], 'Scenario 6: null special_price is valid (clears it)', $failures);
assertBulkPlan(['special_price' => null], $plan['fields'], 'Scenario 6: null special_price passes through as null', $failures);

// 7. category_ids must be a list of positive integers.
$plan = $planner->plan([1], ['category_ids' => [20, '25', 20]]);
assertBulkPlan([], $plan['errors'], 'Scenario 7: valid category_ids list has no errors', $failures);
assertBulkPlan([20, 25], $plan['fields']['category_ids'], 'Scenario 7: category_ids deduped and cast to int', $failures);

$plan = $planner->plan([1], ['category_ids' => [20, 'not-a-number']]);
assertBulkPlanTrue(
    (bool) preg_grep('/^category_ids: /', $plan['errors']),
    'Scenario 7b: non-numeric category_ids entry is rejected',
    $failures
);

// 8. Chunking: 5 products, chunk size 2 -> three chunks of 2/2/1.
$plan = $planner->plan([1, 2, 3, 4, 5], ['status' => 1], 2);
assertBulkPlan([[1, 2], [3, 4], [5]], $plan['chunks'], 'Scenario 8: chunks split evenly with a final remainder chunk', $failures);
assertBulkPlan(2, $plan['chunk_size'], 'Scenario 8: chunk_size echoed back', $failures);

// 9. nextBatch() steps through a resumable batch job.
$productIds = [10, 11, 12, 13, 14];

$batch1 = $planner->nextBatch($productIds, 0, 2);
assertBulkPlan([10, 11], $batch1['product_ids'], 'Scenario 9a: first batch of 2', $failures);
assertBulkPlan(2, $batch1['next_offset'], 'Scenario 9a: next_offset advances by batch size', $failures);
assertBulkPlan(false, $batch1['done'], 'Scenario 9a: not done after first batch', $failures);

$batch2 = $planner->nextBatch($productIds, $batch1['next_offset'], 2);
assertBulkPlan([12, 13], $batch2['product_ids'], 'Scenario 9b: second batch resumes from next_offset', $failures);
assertBulkPlan(false, $batch2['done'], 'Scenario 9b: not done after second batch', $failures);

$batch3 = $planner->nextBatch($productIds, $batch2['next_offset'], 2);
assertBulkPlan([14], $batch3['product_ids'], 'Scenario 9c: final batch is the remainder', $failures);
assertBulkPlan(5, $batch3['next_offset'], 'Scenario 9c: next_offset reaches total', $failures);
assertBulkPlan(true, $batch3['done'], 'Scenario 9c: done once every product has been batched', $failures);

// 10. nextBatch() past the end returns an empty, done batch (idempotent on retry).
$batch4 = $planner->nextBatch($productIds, $batch3['next_offset'], 2);
assertBulkPlan([], $batch4['product_ids'], 'Scenario 10: requesting past the end returns no products', $failures);
assertBulkPlan(true, $batch4['done'], 'Scenario 10: still reports done', $failures);

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo 'PASS: BulkProductUpdatePlanner smoke test (10 scenarios)' . "\n";
exit(0);
