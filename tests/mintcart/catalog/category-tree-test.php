<?php
/**
 * Standalone smoke test for MintCart\Catalog\CategoryTree.
 *
 * Run: php tests/mintcart/catalog/category-tree-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/catalog/categorytree.php';

use MintCart\Catalog\CategoryTree;

$failures = [];

function check($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

// 1 Mugs (top) > 2 Coffee > 3 Espresso > 4 (too deep); 5 Hidden (not top); 6 Lamps (top)
$rows = [
    0 => [
        ['category_id' => 1, 'name' => 'Mugs', 'top' => 1, 'column' => 2],
        ['category_id' => 5, 'name' => 'Hidden', 'top' => 0, 'column' => 0],
        ['category_id' => 6, 'name' => 'Lamps', 'top' => 1, 'column' => 0],
    ],
    1 => [['category_id' => 2, 'name' => 'Coffee', 'top' => 0, 'column' => 0]],
    2 => [['category_id' => 3, 'name' => 'Espresso', 'top' => 0, 'column' => 0]],
    3 => [['category_id' => 4, 'name' => 'TooDeep', 'top' => 0, 'column' => 0]],
];
$childrenOf = static fn (int $parent): array => $rows[$parent] ?? [];
$link = static fn (string $path): string => 'cat?path=' . $path;

// Menu mode: only `top` roots, three levels, counts below level 1.
$tree = (new CategoryTree($childrenOf, $link, static fn (int $id): int => $id * 10))->build(true);

check(2, count($tree), 'topOnly keeps only top roots', $failures);
check('Mugs', $tree[0]['name'], 'level-1 name has no count', $failures);
check(2, $tree[0]['column'], 'column passes through', $failures);
check(1, $tree[1]['column'], 'column defaults to 1', $failures);
check('cat?path=1', $tree[0]['href'], 'level-1 link', $failures);
check('Coffee (20)', $tree[0]['children'][0]['name'], 'level-2 name carries the count', $failures);
check('cat?path=1_2', $tree[0]['children'][0]['href'], 'level-2 link is the full path', $failures);
check('Espresso (30)', $tree[0]['children'][0]['children'][0]['name'], 'level-3 exists', $failures);
check('cat?path=1_2_3', $tree[0]['children'][0]['children'][0]['href'], 'level-3 link is the full path', $failures);
check([], $tree[0]['children'][0]['children'][0]['children'], 'depth is capped at three levels', $failures);

// Module mode: every root, no counter -> plain names.
$all = (new CategoryTree($childrenOf, $link))->build();
check(3, count($all), 'without topOnly every root is listed', $failures);
check('Coffee', $all[0]['children'][0]['name'], 'no counter -> plain names', $failures);

// Module options: counted roots, only the active root expanded.
$mod = (new CategoryTree($childrenOf, $link, static fn (int $id): int => $id * 10))->build(false, true, 6);
check('Mugs (10)', $mod[0]['name'], 'countRoots appends the count to root names', $failures);
check([], $mod[0]['children'], 'only the expandRoot gets children', $failures);
$mod = (new CategoryTree($childrenOf, $link))->build(false, false, 1);
check('Coffee', $mod[0]['children'][0]['name'], 'the expanded root keeps its subtree', $failures);

// A shallower cap.
$flat = (new CategoryTree($childrenOf, $link, null, 2))->build(true);
check([], $flat[0]['children'][0]['children'], 'maxDepth 2 stops at level 2', $failures);

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo "PASS: CategoryTree smoke test\n";
exit(0);
