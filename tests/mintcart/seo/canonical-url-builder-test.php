<?php
/**
 * Standalone smoke test for MintCart\Seo\CanonicalUrlBuilder.
 *
 * No PHPUnit/Composer in the repo yet, same as every other tests/mintcart/
 * smoke test. CanonicalUrlBuilder has no OpenCart dependency by design, so
 * this runs entirely against plain scalars.
 *
 * Scenarios 1-5 replay the exact page/limit/total combinations
 * `catalog/controller/product/category.php`,
 * `catalog/controller/product/manufacturer.php`,
 * `catalog/controller/product/search.php` and
 * `catalog/controller/product/special.php` already special-case inline,
 * asserting this class reproduces their existing behavior exactly (a
 * refactor extraction, not a behavior change).
 *
 * Run: php tests/mintcart/seo/canonical-url-builder-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/seo/canonicalurlbuilder.php';

use MintCart\Seo\CanonicalUrlBuilder;

$failures = [];

function assertSame($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

$builder = new CanonicalUrlBuilder();

// 1. Page 1 of a listing with more pages: bare canonical, no prev, a next.
// Matches category.php's `path=5` example with 40 products at a limit of 20.
$r = $builder->build(1, 20, 40, 'path=5');
assertSame('path=5', $r['canonical'], 'page1: canonical is bare (no &page=)', $failures);
assertSame(null, $r['prev'], 'page1: no prev link', $failures);
assertSame('path=5&page=2', $r['next'], 'page1: next carries &page=2', $failures);

// 2. Page 2 of 2: canonical carries &page=2, prev is bare (page 1 has no
// &page= param, matching category.php's `($page - 2) ? ... : ''` rule),
// no next (this is the last page).
$r = $builder->build(2, 20, 40, 'path=5');
assertSame('path=5&page=2', $r['canonical'], 'page2-of-2: canonical carries &page=2', $failures);
assertSame('path=5', $r['prev'], 'page2-of-2: prev is bare (back to page 1)', $failures);
assertSame(null, $r['next'], 'page2-of-2: no next (last page)', $failures);

// 3. Page 3 of 3: both prev and next carry a &page= param.
$r = $builder->build(3, 20, 60, 'path=5');
assertSame('path=5&page=3', $r['canonical'], 'page3-of-3: canonical carries &page=3', $failures);
assertSame('path=5&page=2', $r['prev'], 'page3-of-3: prev carries &page=2', $failures);
assertSame(null, $r['next'], 'page3-of-3: no next (last page)', $failures);

// 4. special.php's shape: baseQuery starts empty (no id/path param at all).
// Page 1: bare canonical (empty string), matching `$this->url->link('product/special', '', true)`.
$r = $builder->build(1, 20, 5, '');
assertSame('', $r['canonical'], 'empty base, page1: canonical is empty string', $failures);
assertSame(null, $r['prev'], 'empty base, page1: no prev', $failures);
assertSame(null, $r['next'], 'empty base, page1: no next (only 5 of 20 fit on one page)', $failures);

// 5. empty base, page 2 of 3: canonical/next carry &page=N, prev is bare
// empty string (page 1 == the empty base itself).
$r = $builder->build(2, 20, 60, '');
assertSame('&page=2', $r['canonical'], 'empty base, page2-of-3: canonical is &page=2', $failures);
assertSame('', $r['prev'], 'empty base, page2-of-3: prev is the empty base (page 1)', $failures);
assertSame('&page=3', $r['next'], 'empty base, page2-of-3: next is &page=3', $failures);

// 6. A limit of 0 (OpenCart's "show all" / disabled pagination case) never
// emits a next link, regardless of total.
$r = $builder->build(1, 0, 500, 'path=5');
assertSame(null, $r['next'], 'limit=0: no next link ever', $failures);
assertSame(null, $r['prev'], 'limit=0: no prev link on page 1', $failures);
assertSame('path=5', $r['canonical'], 'limit=0: canonical stays bare', $failures);

// 7. Zero total results: page 1 canonical is bare, no prev/next.
$r = $builder->build(1, 20, 0, 'path=5');
assertSame('path=5', $r['canonical'], 'zero total: canonical stays bare', $failures);
assertSame(null, $r['prev'], 'zero total: no prev', $failures);
assertSame(null, $r['next'], 'zero total: no next', $failures);

// 8. Out-of-range input is clamped rather than producing a negative/zero
// page or a negative limit/total (defensive — no controller currently
// passes these, but the class should not misbehave if one ever did).
$r = $builder->build(0, -5, -10, 'path=5');
assertSame('path=5', $r['canonical'], 'clamped: page<1 treated as page 1', $failures);
assertSame(null, $r['prev'], 'clamped: negative page has no prev', $failures);
assertSame(null, $r['next'], 'clamped: negative limit has no next', $failures);

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo 'PASS: CanonicalUrlBuilder smoke test (' . 8 . " scenarios)\n";
exit(0);
