<?php
/**
 * Standalone smoke test for MintCart\Seo\SchemaOrgBuilder.
 *
 * No PHPUnit/Composer in the repo yet, same as every other tests/mintcart/
 * suite. SchemaOrgBuilder has no OpenCart dependency by design, so this
 * runs entirely against plain strings/arrays.
 *
 * Run: php tests/mintcart/seo/schema-org-builder-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/seo/schemaorgbuilder.php';

use MintCart\Seo\SchemaOrgBuilder;

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

$builder = new SchemaOrgBuilder();

// --- organization() ---

// 1. Requires 'name'.
try {
    $builder->organization(['url' => 'https://example.test']);
    $failures[] = 'organization: missing name should throw';
} catch (\InvalidArgumentException $e) {
    // expected
}

// 2. Requires 'url'.
try {
    $builder->organization(['name' => 'MintCart Demo']);
    $failures[] = 'organization: missing url should throw';
} catch (\InvalidArgumentException $e) {
    // expected
}

// 3. Minimal input produces the base @type/name/url shape, no optional keys.
$org = $builder->organization(['name' => 'MintCart Demo', 'url' => 'https://example.test']);
assertSame('https://schema.org', $org['@context'], 'organization: sets @context', $failures);
assertSame('Organization', $org['@type'], 'organization: sets @type', $failures);
assertSame('MintCart Demo', $org['name'], 'organization: sets name', $failures);
assertTrue(!isset($org['logo']), 'organization: omits logo when not given', $failures);
assertTrue(!isset($org['contactPoint']), 'organization: omits contactPoint when no telephone given', $failures);
assertTrue(!isset($org['address']), 'organization: omits address when not given', $failures);

// 4. Full input includes logo/contactPoint/address.
$org = $builder->organization([
    'name' => 'MintCart Demo',
    'url' => 'https://example.test',
    'logo' => 'https://example.test/logo.png',
    'telephone' => '+1 555 0100',
    'address' => '123 Main St',
]);
assertSame('https://example.test/logo.png', $org['logo'], 'organization: sets logo', $failures);
assertSame('ContactPoint', $org['contactPoint'][0]['@type'], 'organization: contactPoint @type', $failures);
assertSame('+1 555 0100', $org['contactPoint'][0]['telephone'], 'organization: contactPoint telephone', $failures);
assertSame('customer service', $org['contactPoint'][0]['contactType'], 'organization: contactPoint contactType', $failures);
assertSame('PostalAddress', $org['address']['@type'], 'organization: address @type', $failures);
assertSame('123 Main St', $org['address']['streetAddress'], 'organization: address streetAddress', $failures);

// --- breadcrumbList() ---

// 5. Empty input produces an empty itemListElement, not an error.
$breadcrumb = $builder->breadcrumbList([]);
assertSame('BreadcrumbList', $breadcrumb['@type'], 'breadcrumbList: sets @type', $failures);
assertSame([], $breadcrumb['itemListElement'], 'breadcrumbList: empty input -> empty list', $failures);

// 6. Items are numbered from position 1, in input order.
$breadcrumb = $builder->breadcrumbList([
    ['text' => 'Home', 'href' => 'https://example.test/'],
    ['text' => 'Laptops', 'href' => 'https://example.test/laptops'],
]);
assertSame(2, count($breadcrumb['itemListElement']), 'breadcrumbList: one ListItem per valid input item', $failures);
assertSame(1, $breadcrumb['itemListElement'][0]['position'], 'breadcrumbList: first item is position 1', $failures);
assertSame('Home', $breadcrumb['itemListElement'][0]['name'], 'breadcrumbList: item name from text', $failures);
assertSame('https://example.test/', $breadcrumb['itemListElement'][0]['item'], 'breadcrumbList: item url from href', $failures);
assertSame(2, $breadcrumb['itemListElement'][1]['position'], 'breadcrumbList: second item is position 2', $failures);

// 7. Items missing text/href are skipped, positions stay contiguous.
$breadcrumb = $builder->breadcrumbList([
    ['text' => 'Home', 'href' => 'https://example.test/'],
    ['text' => '', 'href' => 'https://example.test/blank'],
    ['text' => 'Laptops', 'href' => 'https://example.test/laptops'],
]);
assertSame(2, count($breadcrumb['itemListElement']), 'breadcrumbList: skips items with an empty text/href', $failures);
assertSame(2, $breadcrumb['itemListElement'][1]['position'], 'breadcrumbList: positions stay contiguous after a skip', $failures);

// --- product() ---

// 8. Requires 'name' and 'url'.
try {
    $builder->product(['url' => 'https://example.test/p/1']);
    $failures[] = 'product: missing name should throw';
} catch (\InvalidArgumentException $e) {
    // expected
}
try {
    $builder->product(['name' => 'Mug']);
    $failures[] = 'product: missing url should throw';
} catch (\InvalidArgumentException $e) {
    // expected
}

// 9. Minimal input has no offers/aggregateRating when price/rating absent.
$product = $builder->product(['name' => 'Mug', 'url' => 'https://example.test/p/1']);
assertSame('Product', $product['@type'], 'product: sets @type', $failures);
assertTrue(!isset($product['offers']), 'product: omits offers when no price given', $failures);
assertTrue(!isset($product['aggregateRating']), 'product: omits aggregateRating when no rating given', $failures);

// 10. A false price (OpenCart's "price hidden" convention) also omits offers.
$product = $builder->product(['name' => 'Mug', 'url' => 'https://example.test/p/1', 'price' => false]);
assertTrue(!isset($product['offers']), 'product: a false price omits offers (OpenCart hidden-price convention)', $failures);

// 11. A real price builds a correctly-shaped Offer, defaulting currency to USD.
$product = $builder->product([
    'name' => 'Mug',
    'url' => 'https://example.test/p/1',
    'price' => 12.5,
    'in_stock' => true,
]);
assertSame('Offer', $product['offers']['@type'], 'product: offers @type', $failures);
assertSame('12.50', $product['offers']['price'], 'product: price formatted to 2 decimals', $failures);
assertSame('USD', $product['offers']['priceCurrency'], 'product: priceCurrency defaults to USD', $failures);
assertSame('https://schema.org/InStock', $product['offers']['availability'], 'product: in_stock=true -> InStock', $failures);

// 12. Out of stock -> OutOfStock availability, explicit currency respected.
$product = $builder->product([
    'name' => 'Mug',
    'url' => 'https://example.test/p/1',
    'price' => 12.5,
    'currency' => 'EUR',
    'in_stock' => false,
]);
assertSame('https://schema.org/OutOfStock', $product['offers']['availability'], 'product: in_stock=false -> OutOfStock', $failures);
assertSame('EUR', $product['offers']['priceCurrency'], 'product: explicit currency is respected', $failures);

// 13. aggregateRating only appears when both rating_value and rating_count are truthy.
$product = $builder->product([
    'name' => 'Mug',
    'url' => 'https://example.test/p/1',
    'rating_value' => 4.5,
    'rating_count' => 12,
]);
assertSame('AggregateRating', $product['aggregateRating']['@type'], 'product: aggregateRating @type', $failures);
assertSame('4.5', $product['aggregateRating']['ratingValue'], 'product: ratingValue as string', $failures);
assertSame(12, $product['aggregateRating']['reviewCount'], 'product: reviewCount as int', $failures);

$product = $builder->product([
    'name' => 'Mug',
    'url' => 'https://example.test/p/1',
    'rating_value' => 4.5,
    'rating_count' => 0,
]);
assertTrue(!isset($product['aggregateRating']), 'product: rating_count=0 omits aggregateRating', $failures);

// --- toJsonLd() ---

// 14. Produces valid, decodable JSON.
$json = $builder->toJsonLd(['@type' => 'Thing', 'name' => 'Test']);
$decoded = json_decode($json, true);
assertSame('Thing', $decoded['@type'] ?? null, 'toJsonLd: round-trips through json_decode', $failures);

// 15. `<`/`>` are hex-escaped so a value can never break out of a
//     `<script>` tag (e.g. a store name containing "</script>").
$dangerous = '</script><script>alert(1)</script>';
$json = $builder->toJsonLd(['name' => $dangerous]);
assertTrue(strpos($json, '</script>') === false, 'toJsonLd: escapes literal </script> sequences', $failures);
assertTrue(strpos($json, '<') === false, 'toJsonLd: no literal angle bracket survives encoding', $failures);
assertSame($dangerous, json_decode($json, true)['name'], 'toJsonLd: still round-trips back to the original string via json_decode', $failures);

// 16. Slashes are left unescaped (JSON_UNESCAPED_SLASHES) so URLs stay readable.
$json = $builder->toJsonLd(['url' => 'https://example.test/p/1']);
assertTrue(strpos($json, 'https://example.test/p/1') !== false, 'toJsonLd: leaves URL slashes unescaped', $failures);

if ($failures) {
    fwrite(STDERR, count($failures) . " failure(s):\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "- {$failure}\n");
    }
    exit(1);
}

echo "PASS: SchemaOrgBuilder smoke test (16 scenarios)\n";
exit(0);
