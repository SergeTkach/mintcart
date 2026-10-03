<?php
/**
 * Standalone smoke test for MintCart\Commerce\WishlistService.
 *
 * No PHPUnit/Composer in the repo yet, same as cart-service-test.php.
 * WishlistService has no OpenCart dependency by design, so this runs
 * entirely against plain arrays.
 *
 * Run: php tests/mintcart/commerce/wishlist-service-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/commerce/wishlistservice.php';

use MintCart\Commerce\WishlistService;

$failures = [];

function assertSame($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

$service = new WishlistService();

// --- normalizeIds() ---

// 1. Duplicates collapse, non-numeric/zero/negative entries drop out.
assertSame(
    [42, 7],
    $service->normalizeIds(['42', 42, 7, 0, -1, 'not-a-number']),
    'normalizeIds: dedupes, casts to int, drops non-positive ids',
    $failures
);

// 2. Empty input stays empty.
assertSame([], $service->normalizeIds([]), 'normalizeIds: empty list stays empty', $failures);

// --- withProduct() ---

// 3. Adding a new product appends it.
assertSame(
    [1, 2, 3],
    $service->withProduct([1, 2], 3, true),
    'withProduct: adding a new product appends it',
    $failures
);

// 4. Adding an already-present product does not duplicate it.
assertSame(
    [1, 2],
    $service->withProduct([1, 2], 2, true),
    'withProduct: adding an already-present product is a no-op',
    $failures
);

// 5. Removing a present product drops it.
assertSame(
    [1, 3],
    $service->withProduct([1, 2, 3], 2, false),
    'withProduct: removing a present product drops it',
    $failures
);

// 6. Removing an absent product is a no-op (list unchanged, just normalized).
assertSame(
    [1, 2],
    $service->withProduct([1, 2], 99, false),
    'withProduct: removing an absent product leaves the list unchanged',
    $failures
);

// 7. String product id (as POST data arrives) works the same as an int.
assertSame(
    [1, 2, 5],
    $service->withProduct([1, 2], '5', true),
    'withProduct: string product id is cast to int',
    $failures
);

// --- snapshot() ---

// 8. Empty wishlist, no product id given.
$snapshot = $service->snapshot([]);
assertSame(0, $snapshot['count'], 'snapshot: empty wishlist has count 0', $failures);
assertSame([], $snapshot['product_ids'], 'snapshot: empty wishlist has no product_ids', $failures);
assertSame(null, $snapshot['active'], 'snapshot: active is null when no product id given', $failures);

// 9. Non-empty wishlist, count matches normalized unique ids.
$snapshot = $service->snapshot([1, 1, 2, '3']);
assertSame(3, $snapshot['count'], 'snapshot: count reflects normalized unique ids', $failures);
assertSame([1, 2, 3], $snapshot['product_ids'], 'snapshot: product_ids is normalized', $failures);

// 10. active is true when the given product id is in the list.
$snapshot = $service->snapshot([1, 2, 3], 2);
assertSame(true, $snapshot['active'], 'snapshot: active is true when product id is in the list', $failures);

// 11. active is false when the given product id is not in the list.
$snapshot = $service->snapshot([1, 2, 3], 99);
assertSame(false, $snapshot['active'], 'snapshot: active is false when product id is not in the list', $failures);

// 12. active works with a string product id (as POST data arrives).
$snapshot = $service->snapshot([1, 2, 3], '2');
assertSame(true, $snapshot['active'], 'snapshot: active works with a string product id', $failures);

if ($failures) {
    fwrite(STDERR, count($failures) . " failure(s):\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "- {$failure}\n");
    }
    exit(1);
}

echo "PASS: WishlistService smoke test (12 scenarios)\n";
exit(0);
