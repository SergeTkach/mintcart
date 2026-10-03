<?php
/**
 * Standalone smoke test for MintCart\Catalog\SortOptionsBuilder.
 *
 * No PHPUnit/Composer in the repo yet, same as every other tests/mintcart/
 * smoke test. SortOptionsBuilder has no OpenCart dependency by design, so
 * this runs entirely against plain scalars.
 *
 * Scenarios replay the exact sort/order/text_key combinations
 * `catalog/controller/product/category.php`,
 * `catalog/controller/product/manufacturer.php` and
 * `catalog/controller/product/search.php` already build inline, asserting
 * this class reproduces their existing order and review-status gate
 * exactly (a refactor extraction, not a behavior change).
 *
 * Run: php tests/mintcart/catalog/sort-options-builder-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/catalog/sortoptionsbuilder.php';

use MintCart\Catalog\SortOptionsBuilder;

$failures = [];

function assertSame($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

$builder = new SortOptionsBuilder();

// 1. Reviews disabled: 7 options, no rating entries, in the documented order.
$options = $builder->build(false);
assertSame(7, count($options), 'reviews disabled: 7 options', $failures);
assertSame(
    ['p.sort_order', 'pd.name', 'pd.name', 'p.price', 'p.price', 'p.model', 'p.model'],
    array_column($options, 'sort'),
    'reviews disabled: sort column order',
    $failures
);
assertSame(
    ['ASC', 'ASC', 'DESC', 'ASC', 'DESC', 'ASC', 'DESC'],
    array_column($options, 'order'),
    'reviews disabled: order column order',
    $failures
);
assertSame(
    ['text_default', 'text_name_asc', 'text_name_desc', 'text_price_asc', 'text_price_desc', 'text_model_asc', 'text_model_desc'],
    array_column($options, 'text_key'),
    'reviews disabled: text_key order',
    $failures
);

// 2. Reviews enabled: 9 options, rating DESC/ASC inserted between
// price-desc and model-asc (matches every controller's identical
// `if ($this->config->get('config_review_status'))` placement).
$options = $builder->build(true);
assertSame(9, count($options), 'reviews enabled: 9 options', $failures);
assertSame(
    ['p.sort_order', 'pd.name', 'pd.name', 'p.price', 'p.price', 'rating', 'rating', 'p.model', 'p.model'],
    array_column($options, 'sort'),
    'reviews enabled: sort column order',
    $failures
);
assertSame(
    ['ASC', 'ASC', 'DESC', 'ASC', 'DESC', 'DESC', 'ASC', 'ASC', 'DESC'],
    array_column($options, 'order'),
    'reviews enabled: order column order',
    $failures
);
assertSame(
    ['text_default', 'text_name_asc', 'text_name_desc', 'text_price_asc', 'text_price_desc', 'text_rating_desc', 'text_rating_asc', 'text_model_asc', 'text_model_desc'],
    array_column($options, 'text_key'),
    'reviews enabled: text_key order',
    $failures
);

// 3. Each entry is a plain 3-key shape, no extra keys the controller has
// to strip before assigning to $data['sorts'][].
foreach ($builder->build(true) as $option) {
    assertSame(['sort', 'order', 'text_key'], array_keys($option), 'entry has exactly sort/order/text_key keys', $failures);
}

if ($failures) {
    echo "FAILED (" . count($failures) . "):\n";
    foreach ($failures as $failure) {
        echo "  - $failure\n";
    }
    exit(1);
}

echo "OK: SortOptionsBuilder — all scenarios passed.\n";
exit(0);
