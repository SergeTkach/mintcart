<?php

/**
 * Standalone smoke test for MintCart\Admin\SettingsSearchIndex.
 *
 * No PHPUnit/Composer in the repo yet, same as every other tests/mintcart
 * file. SettingsSearchIndex has no OpenCart dependency by design, so this
 * runs entirely against plain strings.
 *
 * Run: php tests/mintcart/admin/settings-search-index-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/admin/settingssearchindex.php';

use MintCart\Admin\SettingsSearchIndex;

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

$index = new SettingsSearchIndex();

// --- blank/empty query ---

assertSame([], $index->search(''), 'search: blank query returns no results', $failures);
assertSame([], $index->search('   '), 'search: whitespace-only query returns no results', $failures);
assertSame([], $index->search('currency', 0), 'search: a limit of 0 returns no results', $failures);

// --- no match ---

assertSame([], $index->search('this-does-not-match-anything-xyz'), 'search: a query matching nothing returns no results', $failures);

// --- Functional Scope §23's own example list all resolve ---

foreach (['currency', 'logo', 'delivery', 'email', 'tax', 'checkout'] as $term) {
    $results = $index->search($term);
    assertTrue(count($results) > 0, "search: '{$term}' (Functional Scope §23's own example list) returns at least one result", $failures);
}

// SEO URL is two words; §23 names it as one example ("SEO URL") - confirm
// each half resolves on its own, since a merchant is as likely to type
// either.
assertTrue(count($index->search('seo')) > 0, "search: 'seo' returns at least one result", $failures);
assertTrue(count($index->search('url')) > 0, "search: 'url' returns at least one result", $failures);

// --- exact label match ranks first ---

$results = $index->search('currency');
assertSame('Currency', $results[0]['label'] ?? null, 'search: an exact label match ranks first', $failures);
assertSame('localisation/currency', $results[0]['route'] ?? null, 'search: Currency resolves to the real localisation/currency route', $failures);

// --- case-insensitive ---

assertSame($index->search('Currency'), $index->search('currency'), 'search: case-insensitive', $failures);
assertSame($index->search('CURRENCY'), $index->search('currency'), 'search: case-insensitive (all caps)', $failures);

// --- keyword match (not just label) ---

$results = $index->search('vat');
assertTrue(count($results) > 0, "search: 'vat' (a Tax Classes/Tax Rates keyword, not in either label) still matches", $failures);

// --- limit is respected ---

assertTrue(count($index->search('a', 3)) <= 3, 'search: result count never exceeds the given limit', $failures);

// --- every entry's route is non-empty and every entry has at least one keyword ---

foreach ($index->all() as $entry) {
    assertTrue($entry['route'] !== '', "all(): every entry has a non-empty route ({$entry['label']})", $failures);
    assertTrue(count($entry['keywords']) > 0, "all(): every entry has at least one keyword ({$entry['label']})", $failures);
}

// --- routes are unique (no two entries silently point at the same screen) ---

$routes = array_map(static fn (array $entry) => $entry['route'], $index->all());
assertSame(count($routes), count(array_unique($routes)), 'all(): every entry has a unique route', $failures);

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo "PASS: SettingsSearchIndex smoke test\n";
exit(0);
