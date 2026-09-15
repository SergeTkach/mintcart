<?php
/**
 * Standalone smoke test for MintCart\Seo\MetaBuilder.
 *
 * No PHPUnit/Composer in the repo yet, same as every other
 * tests/mintcart/ smoke test. MetaBuilder has no OpenCart dependency by
 * design, so this runs entirely against plain scalars.
 *
 * Scenarios 1-4 cover the basic fallback rule; 5-9 replay the exact
 * blank-meta shapes `product/category.php`, `product/product.php` and
 * `common/home.php` produce today (see the class docblock), confirming
 * this fixes the silent-blank-title/description case without changing
 * anything when a real value is already set.
 *
 * Run: php tests/mintcart/seo/meta-builder-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/seo/metabuilder.php';

use MintCart\Seo\MetaBuilder;

$failures = [];

function assertSame($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

$builder = new MetaBuilder();

// 1. Entity values present: used as-is, fallback ignored entirely.
$r = $builder->build('Fresh Fruit | Acme', 'The best fruit in town.', 'Fresh Fruit', 'fallback desc');
assertSame('Fresh Fruit | Acme', $r['title'], 'entity title wins over fallback', $failures);
assertSame('The best fruit in town.', $r['description'], 'entity description wins over fallback', $failures);

// 2. Entity title is an empty string: falls back to the supplied fallback title.
$r = $builder->build('', null, 'Fresh Fruit');
assertSame('Fresh Fruit', $r['title'], 'blank entity title falls back', $failures);

// 3. Entity title is whitespace-only: treated the same as empty.
$r = $builder->build("   \n\t", null, 'Fresh Fruit');
assertSame('Fresh Fruit', $r['title'], 'whitespace-only entity title falls back', $failures);

// 4. Entity title is null: falls back.
$r = $builder->build(null, null, 'Fresh Fruit');
assertSame('Fresh Fruit', $r['title'], 'null entity title falls back', $failures);

// 5. category.php's real shape: a category created without a meta title
// ever being set (`meta_title` is `''` in the DB row, matching
// ModelCatalogCategory::getCategory()'s default). Title falls back to
// the category's own name (`$data['heading_title']`); description has
// no fallback offered (category.php has nothing else to offer) so it
// stays empty, same as today, but now driven by an explicit "no
// fallback available" rather than an accidental blank pass-through.
$r = $builder->build('', '', 'Fruit');
assertSame('Fruit', $r['title'], 'category.php shape: title falls back to category name', $failures);
assertSame('', $r['description'], 'category.php shape: no description fallback available stays empty', $failures);

// 6. product.php's real shape: same pattern, fallback is the product name.
$r = $builder->build('', '', 'Apple iPhone');
assertSame('Apple iPhone', $r['title'], 'product.php shape: title falls back to product name', $failures);
assertSame('', $r['description'], 'product.php shape: no description fallback available stays empty', $failures);

// 7. home.php's real shape: a fresh install where config_meta_title/
// config_meta_description were never configured, falling back to the
// store name for the title only (no sensible description fallback
// exists at install time).
$r = $builder->build('', '', 'My Store', null);
assertSame('My Store', $r['title'], 'home.php shape: title falls back to store name', $failures);
assertSame('', $r['description'], 'home.php shape: description stays empty with no fallback given', $failures);

// 8. Entity description blank but a fallback description is supplied
// (e.g. a future caller reusing the entity's own short description
// field): falls back rather than staying blank.
$r = $builder->build('Fruit', '', 'Fruit', 'Seasonal fruit, picked fresh.');
assertSame('Seasonal fruit, picked fresh.', $r['description'], 'blank entity description falls back when a fallback is given', $failures);

// 9. Both entity value and fallback are empty: result is an empty
// string, never null (a caller can pass this straight to
// Document::setTitle()/setDescription() without a null check).
$r = $builder->build('', '', '');
assertSame('', $r['title'], 'both empty: title is empty string, not null', $failures);
assertSame('', $r['description'], 'both empty: description is empty string, not null', $failures);

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo 'PASS: MetaBuilder smoke test (' . 9 . " scenarios)\n";
exit(0);
