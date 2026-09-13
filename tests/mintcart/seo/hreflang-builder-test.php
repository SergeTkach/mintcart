<?php
/**
 * Standalone smoke test for MintCart\Seo\HreflangBuilder.
 *
 * No PHPUnit/Composer in the repo yet, same as every other tests/mintcart/
 * suite. HreflangBuilder has no OpenCart dependency by design, so this
 * runs entirely against plain arrays.
 *
 * Run: php tests/mintcart/seo/hreflang-builder-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/seo/hreflangbuilder.php';

use MintCart\Seo\HreflangBuilder;

$failures = [];

function assertSame($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

$builder = new HreflangBuilder();

// 1. Empty input produces an empty list, not an error.
assertSame([], $builder->build([]), 'build: empty input -> empty list', $failures);

// 2. Basic pass-through, input order preserved.
$result = $builder->build([
    ['hreflang' => 'en-gb', 'href' => 'https://example.test/en/'],
    ['hreflang' => 'uk-ua', 'href' => 'https://example.test/uk/'],
]);
assertSame(2, count($result), 'build: one entry per valid alternate', $failures);
assertSame('en-gb', $result[0]['hreflang'], 'build: preserves input order (first)', $failures);
assertSame('uk-ua', $result[1]['hreflang'], 'build: preserves input order (second)', $failures);
assertSame('https://example.test/uk/', $result[1]['href'], 'build: href passed through unchanged', $failures);

// 3. An entry missing hreflang is skipped.
$result = $builder->build([
    ['hreflang' => '', 'href' => 'https://example.test/en/'],
    ['hreflang' => 'uk-ua', 'href' => 'https://example.test/uk/'],
]);
assertSame(1, count($result), 'build: skips an entry with a blank hreflang', $failures);
assertSame('uk-ua', $result[0]['hreflang'], 'build: keeps the valid entry after a skip', $failures);

// 4. An entry missing href is skipped.
$result = $builder->build([
    ['hreflang' => 'en-gb', 'href' => ''],
    ['hreflang' => 'uk-ua', 'href' => 'https://example.test/uk/'],
]);
assertSame(1, count($result), 'build: skips an entry with a blank href', $failures);

// 5. An entry missing either key entirely (not just blank) is skipped.
$result = $builder->build([
    ['hreflang' => 'en-gb'],
    ['href' => 'https://example.test/uk/'],
]);
assertSame([], $result, 'build: skips entries missing hreflang or href keys', $failures);

// 6. A duplicate hreflang keeps only the first occurrence.
$result = $builder->build([
    ['hreflang' => 'en-gb', 'href' => 'https://example.test/en/first'],
    ['hreflang' => 'en-gb', 'href' => 'https://example.test/en/second'],
]);
assertSame(1, count($result), 'build: de-duplicates by hreflang', $failures);
assertSame('https://example.test/en/first', $result[0]['href'], 'build: first occurrence wins on a duplicate', $failures);

// 7. No x-default entry when $defaultHreflang is not passed.
$result = $builder->build([
    ['hreflang' => 'en-gb', 'href' => 'https://example.test/en/'],
]);
assertSame(1, count($result), 'build: no x-default synthesized when not requested', $failures);

// 8. x-default is synthesized and appended last when the default language matches an entry.
$result = $builder->build([
    ['hreflang' => 'uk-ua', 'href' => 'https://example.test/uk/'],
    ['hreflang' => 'en-gb', 'href' => 'https://example.test/en/'],
], 'en-gb');
assertSame(3, count($result), 'build: x-default adds one extra entry', $failures);
assertSame('x-default', $result[2]['hreflang'], 'build: x-default is appended last', $failures);
assertSame('https://example.test/en/', $result[2]['href'], 'build: x-default href matches the default language entry', $failures);

// 9. $defaultHreflang that matches no entry synthesizes nothing.
$result = $builder->build([
    ['hreflang' => 'uk-ua', 'href' => 'https://example.test/uk/'],
], 'en-gb');
assertSame(1, count($result), 'build: no x-default when the default language has no matching entry', $failures);

// 10. Whitespace-only hreflang/href are treated as blank.
$result = $builder->build([
    ['hreflang' => '   ', 'href' => 'https://example.test/en/'],
]);
assertSame([], $result, 'build: whitespace-only hreflang is treated as blank', $failures);

if ($failures) {
    fwrite(STDERR, count($failures) . " failure(s):\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "- {$failure}\n");
    }
    exit(1);
}

echo "PASS: HreflangBuilder smoke test (10 scenarios)\n";
exit(0);
