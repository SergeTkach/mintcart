<?php
/**
 * Standalone smoke test for MintCart\Seo\SitemapBuilder.
 *
 * No PHPUnit/Composer in the repo yet, same as every other
 * tests/mintcart/**\/*-test.php file. SitemapBuilder has no OpenCart
 * dependency by design, so this runs entirely against plain arrays/strings.
 *
 * Run: php tests/mintcart/seo/sitemap-builder-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/seo/sitemapbuilder.php';

use MintCart\Seo\SitemapBuilder;

$failures = [];

function assertSame($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

function assertTrue(bool $condition, string $message, array &$failures): void
{
    if (!$condition) {
        $failures[] = $message;
    }
}

$builder = new SitemapBuilder();

// --- urlset(): structure ---

// 1. Empty entries still produce a well-formed, empty <urlset>.
$xml = $builder->urlset([]);
assertTrue(strpos($xml, '<?xml version="1.0" encoding="UTF-8"?>') === 0, 'urlset: starts with XML declaration', $failures);
assertTrue(strpos($xml, '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">') !== false, 'urlset: has the sitemaps.org namespace', $failures);
assertTrue(strpos($xml, '</urlset>') !== false, 'urlset: closes the root element', $failures);
assertSame(0, substr_count($xml, '<url>'), 'urlset: no <url> blocks for empty input', $failures);

// 2. A single loc-only entry.
$xml = $builder->urlset([['loc' => 'https://example.test/']]);
assertSame(1, substr_count($xml, '<url>'), 'urlset: one <url> block for one entry', $failures);
assertTrue(strpos($xml, '<loc>https://example.test/</loc>') !== false, 'urlset: renders the loc verbatim', $failures);
assertTrue(strpos($xml, '<lastmod>') === false, 'urlset: omits <lastmod> when not given', $failures);
assertTrue(strpos($xml, '<changefreq>') === false, 'urlset: omits <changefreq> when not given', $failures);
assertTrue(strpos($xml, '<priority>') === false, 'urlset: omits <priority> when not given', $failures);

// 3. lastmod as a plain string passes through.
$xml = $builder->urlset([['loc' => 'https://example.test/a', 'lastmod' => '2026-09-04']]);
assertTrue(strpos($xml, '<lastmod>2026-09-04</lastmod>') !== false, 'urlset: string lastmod passes through', $failures);

// 4. lastmod as a DateTimeInterface is formatted as ATOM ("c").
$date = new DateTimeImmutable('2026-01-02T03:04:05+00:00');
$xml = $builder->urlset([['loc' => 'https://example.test/b', 'lastmod' => $date]]);
assertTrue(strpos($xml, '<lastmod>' . $date->format('c') . '</lastmod>') !== false, 'urlset: DateTimeInterface lastmod is formatted with format("c")', $failures);

// 5. A blank-string lastmod is treated as absent, not rendered as an empty tag.
$xml = $builder->urlset([['loc' => 'https://example.test/c', 'lastmod' => '   ']]);
assertTrue(strpos($xml, '<lastmod>') === false, 'urlset: whitespace-only lastmod is treated as absent', $failures);

// 6. A valid changefreq is kept.
$xml = $builder->urlset([['loc' => 'https://example.test/d', 'changefreq' => 'daily']]);
assertTrue(strpos($xml, '<changefreq>daily</changefreq>') !== false, 'urlset: a valid changefreq value is rendered', $failures);

// 7. An invalid changefreq is dropped rather than rendered as garbage.
$xml = $builder->urlset([['loc' => 'https://example.test/e', 'changefreq' => 'often']]);
assertTrue(strpos($xml, '<changefreq>') === false, 'urlset: an invalid changefreq value is dropped', $failures);

// 8. priority is clamped to [0.0, 1.0] and formatted to one decimal.
$xml = $builder->urlset([['loc' => 'https://example.test/f', 'priority' => 5]]);
assertTrue(strpos($xml, '<priority>1.0</priority>') !== false, 'urlset: priority above 1.0 clamps to 1.0', $failures);

$xml = $builder->urlset([['loc' => 'https://example.test/g', 'priority' => -3]]);
assertTrue(strpos($xml, '<priority>0.0</priority>') !== false, 'urlset: priority below 0.0 clamps to 0.0', $failures);

$xml = $builder->urlset([['loc' => 'https://example.test/h', 'priority' => 0.5]]);
assertTrue(strpos($xml, '<priority>0.5</priority>') !== false, 'urlset: a mid-range priority formats to one decimal', $failures);

// 9. A blank loc entry is skipped entirely.
$xml = $builder->urlset([['loc' => '  '], ['loc' => 'https://example.test/i']]);
assertSame(1, substr_count($xml, '<url>'), 'urlset: a blank loc entry is skipped, valid entries still render', $failures);

// 10. Duplicate loc values are de-duplicated, keeping the first occurrence's data.
$xml = $builder->urlset([
    ['loc' => 'https://example.test/dup', 'priority' => 0.9],
    ['loc' => 'https://example.test/dup', 'priority' => 0.1],
]);
assertSame(1, substr_count($xml, '<url>'), 'urlset: duplicate loc collapses to one <url> block', $failures);
assertTrue(strpos($xml, '<priority>0.9</priority>') !== false, 'urlset: duplicate loc keeps the first occurrence\'s data', $failures);

// 11. XML special characters in loc are escaped.
$xml = $builder->urlset([['loc' => 'https://example.test/?a=1&b=2']]);
assertTrue(strpos($xml, '<loc>https://example.test/?a=1&amp;b=2</loc>') !== false, 'urlset: "&" in loc is escaped as &amp;', $failures);

// 12. A single sitemap never exceeds MAX_URLS_PER_SITEMAP entries, per the protocol.
$many = [];
for ($i = 0; $i < SitemapBuilder::MAX_URLS_PER_SITEMAP + 5; $i++) {
    $many[] = ['loc' => 'https://example.test/product-' . $i];
}
$xml = $builder->urlset($many);
assertSame(SitemapBuilder::MAX_URLS_PER_SITEMAP, substr_count($xml, '<url>'), 'urlset: truncates to MAX_URLS_PER_SITEMAP entries', $failures);

// --- sitemapIndex() ---

// 13. Empty sitemaps list still produces a well-formed, empty <sitemapindex>.
$xml = $builder->sitemapIndex([]);
assertTrue(strpos($xml, '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">') !== false, 'sitemapIndex: has the sitemaps.org namespace', $failures);
assertSame(0, substr_count($xml, '<sitemap>'), 'sitemapIndex: no <sitemap> blocks for empty input', $failures);

// 14. A populated sitemap index with lastmod.
$xml = $builder->sitemapIndex([
    ['loc' => 'https://example.test/sitemap-products.xml', 'lastmod' => '2026-09-01'],
    ['loc' => 'https://example.test/sitemap-categories.xml'],
]);
assertSame(2, substr_count($xml, '<sitemap>'), 'sitemapIndex: one <sitemap> block per entry', $failures);
assertTrue(strpos($xml, '<lastmod>2026-09-01</lastmod>') !== false, 'sitemapIndex: lastmod renders when given', $failures);

// 15. Blank loc skipped, duplicate loc de-duplicated (same rules as urlset()).
$xml = $builder->sitemapIndex([
    ['loc' => ''],
    ['loc' => 'https://example.test/sitemap-a.xml'],
    ['loc' => 'https://example.test/sitemap-a.xml'],
]);
assertSame(1, substr_count($xml, '<sitemap>'), 'sitemapIndex: blank loc skipped and duplicate loc de-duplicated', $failures);

if ($failures) {
    fwrite(STDERR, count($failures) . " failure(s):\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "- {$failure}\n");
    }
    exit(1);
}

echo "PASS: SitemapBuilder smoke test (15 scenarios)\n";
exit(0);
