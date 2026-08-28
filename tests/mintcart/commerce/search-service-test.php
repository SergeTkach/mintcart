<?php
/**
 * Standalone smoke test for MintCart\Commerce\SearchService.
 *
 * No PHPUnit/Composer in the repo yet, same as cart-service-test.php.
 * SearchService has no OpenCart dependency by design, so this runs
 * entirely against plain strings/arrays.
 *
 * Run: php tests/mintcart/commerce/search-service-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/commerce/searchservice.php';

use MintCart\Commerce\SearchService;

$failures = [];

function assertSame($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

$service = new SearchService();

// --- normalizeQuery() ---

// 1. Leading/trailing whitespace is trimmed.
assertSame('mug', $service->normalizeQuery('  mug  '), 'normalizeQuery: trims leading/trailing whitespace', $failures);

// 2. Internal runs of whitespace collapse to a single space.
assertSame('coffee mug', $service->normalizeQuery("coffee\t\n  mug"), 'normalizeQuery: collapses internal whitespace', $failures);

// 3. An empty/whitespace-only query normalizes to an empty string.
assertSame('', $service->normalizeQuery('   '), 'normalizeQuery: whitespace-only input normalizes to empty', $failures);

// 4. A very long query is capped at 128 characters.
$long = str_repeat('a', 500);
assertSame(128, mb_strlen($service->normalizeQuery($long)), 'normalizeQuery: caps length at 128 chars', $failures);

// --- isQueryValid() ---

// 5. Empty query is invalid.
assertSame(false, $service->isQueryValid(''), 'isQueryValid: empty string is invalid', $failures);

// 6. A single character is invalid (below MIN_QUERY_LENGTH).
assertSame(false, $service->isQueryValid('a'), 'isQueryValid: single character is invalid', $failures);

// 7. Two characters is valid (at MIN_QUERY_LENGTH).
assertSame(true, $service->isQueryValid('ab'), 'isQueryValid: two characters is valid', $failures);

// 8. A normal query is valid.
assertSame(true, $service->isQueryValid('mug'), 'isQueryValid: a normal query is valid', $failures);

// --- results() ---

// 9. Empty result set.
$results = $service->results([], 'zz', 0);
assertSame('zz', $results['query'], 'results: echoes back the query', $failures);
assertSame(0, $results['count'], 'results: count is 0 for no matches', $failures);
assertSame([], $results['items'], 'results: items is empty for no matches', $failures);

// 10. Populated result set, count can exceed items (capped list vs. total matches).
$items = [
    ['id' => 42, 'name' => 'Mug', 'url' => '#', 'image' => 'mug.jpg', 'price' => '$5.00', 'special' => false],
    ['id' => 43, 'name' => 'Big Mug', 'url' => '#', 'image' => 'big-mug.jpg', 'price' => '$8.00', 'special' => '$6.00'],
];
$results = $service->results($items, 'mug', 25);
assertSame('mug', $results['query'], 'results: echoes back the query', $failures);
assertSame(25, $results['count'], 'results: count reflects the total match count, not just items()', $failures);
assertSame(2, count($results['items']), 'results: items array has one entry per resolved row', $failures);
assertSame('$6.00', $results['items'][1]['special'], 'results: item fields pass through unchanged', $failures);

if ($failures) {
    fwrite(STDERR, count($failures) . " failure(s):\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "- {$failure}\n");
    }
    exit(1);
}

echo "PASS: SearchService smoke test (10 scenarios)\n";
exit(0);
