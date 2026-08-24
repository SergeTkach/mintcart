<?php
/**
 * Standalone smoke test for MintCart\Commerce\CartService.
 *
 * No PHPUnit/Composer in the repo yet, same as product-view-provider-test.php.
 * CartService has no OpenCart dependency by design, so this runs entirely
 * against plain arrays.
 *
 * Run: php tests/mintcart/commerce/cart-service-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/commerce/cartservice.php';

use MintCart\Commerce\CartService;

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

$service = new CartService();

// --- validateOptions() ---

// 1. A required option with no submitted value is flagged.
$missing = $service->validateOptions(
    [['product_option_id' => 10, 'required' => '1']],
    []
);
assertSame([10], $missing, 'validateOptions: missing required option is flagged', $failures);

// 2. A required option with a submitted value is not flagged.
$missing = $service->validateOptions(
    [['product_option_id' => 10, 'required' => '1']],
    [10 => '5']
);
assertSame([], $missing, 'validateOptions: submitted required option is not flagged', $failures);

// 3. A non-required option with no submitted value is not flagged.
$missing = $service->validateOptions(
    [['product_option_id' => 11, 'required' => '0']],
    []
);
assertSame([], $missing, 'validateOptions: non-required option is never flagged', $failures);

// 4. No product options at all - nothing to flag.
assertSame([], $service->validateOptions([], []), 'validateOptions: no options is trivially valid', $failures);

// 5. Multiple required options, only one submitted.
$missing = $service->validateOptions(
    [
        ['product_option_id' => 10, 'required' => '1'],
        ['product_option_id' => 20, 'required' => '1'],
    ],
    [10 => 'red']
);
assertSame([20], $missing, 'validateOptions: only the unanswered required option is flagged', $failures);

// --- snapshot() ---

// 6. Empty cart.
$snapshot = $service->snapshot([], ['subtotal' => '$0.00', 'total' => '$0.00']);
assertSame(0, $snapshot['count'], 'snapshot: empty cart has count 0', $failures);
assertSame([], $snapshot['items'], 'snapshot: empty cart has no items', $failures);

// 7. Count sums quantities, not line count.
$snapshot = $service->snapshot(
    [
        ['cart_id' => 1, 'product_id' => 42, 'name' => 'Widget', 'url' => '#', 'thumb' => 'w.jpg', 'quantity' => 2, 'price' => '$5.00', 'total' => '$10.00'],
        ['cart_id' => 2, 'product_id' => 43, 'name' => 'Gadget', 'url' => '#', 'thumb' => 'g.jpg', 'quantity' => 3, 'price' => '$2.00', 'total' => '$6.00'],
    ],
    ['subtotal' => '$16.00', 'total' => '$16.00']
);
assertSame(5, $snapshot['count'], 'snapshot: count sums item quantities across two lines', $failures);
assertSame(2, count($snapshot['items']), 'snapshot: items array has one entry per cart line', $failures);
assertSame('$16.00', $snapshot['subtotal'], 'snapshot: subtotal passes through', $failures);
assertSame('$16.00', $snapshot['total'], 'snapshot: total passes through', $failures);

// 8. totals[] is built from subtotal/tax/total, tax omitted when absent.
$snapshot = $service->snapshot([], ['subtotal' => '$10.00', 'total' => '$12.00']);
assertSame(
    [['title' => 'Sub-Total', 'text' => '$10.00'], ['title' => 'Total', 'text' => '$12.00']],
    $snapshot['totals'],
    'snapshot: totals[] omits Tax when not supplied',
    $failures
);

$snapshot = $service->snapshot([], ['subtotal' => '$10.00', 'tax' => '$2.00', 'total' => '$12.00']);
assertSame(
    [['title' => 'Sub-Total', 'text' => '$10.00'], ['title' => 'Tax', 'text' => '$2.00'], ['title' => 'Total', 'text' => '$12.00']],
    $snapshot['totals'],
    'snapshot: totals[] includes Tax when supplied',
    $failures
);

if ($failures) {
    fwrite(STDERR, count($failures) . " failure(s):\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "- {$failure}\n");
    }
    exit(1);
}

echo "PASS: CartService smoke test (8 scenarios)\n";
exit(0);
