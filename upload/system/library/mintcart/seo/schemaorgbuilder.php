<?php
namespace MintCart\Seo;

/**
 * Builds Schema.org JSON-LD payloads (as plain PHP arrays) for the three
 * structured-data types MINTCART_FUNCTIONAL_SCOPE.md §16 / Technical Plan
 * §31 name explicitly: Product, BreadcrumbList, Organization.
 *
 * Deliberately has no OpenCart dependency (no \Registry, no \DB, no
 * currency/tax/url services) so it is unit-testable in isolation - same
 * shape as MintCart\Commerce\ProductViewProvider/SearchService. Callers
 * resolve raw values (numeric price, ISO currency code, absolute URLs)
 * upstream; this class only assembles the JSON-LD structure and encodes it
 * for safe embedding inside a `<script type="application/ld+json">` tag.
 */
final class SchemaOrgBuilder
{
    /**
     * @param array{name: string, url: string, logo?: ?string, telephone?: ?string, address?: ?string} $store
     * @return array<string, mixed>
     */
    public function organization(array $store): array
    {
        foreach (['name', 'url'] as $required) {
            if (empty($store[$required])) {
                throw new \InvalidArgumentException("SchemaOrgBuilder::organization() requires a non-empty '{$required}' key.");
            }
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $store['name'],
            'url' => $store['url'],
        ];

        if (!empty($store['logo'])) {
            $schema['logo'] = $store['logo'];
        }

        if (!empty($store['telephone'])) {
            $schema['contactPoint'] = [[
                '@type' => 'ContactPoint',
                'telephone' => $store['telephone'],
                'contactType' => 'customer service',
            ]];
        }

        if (!empty($store['address'])) {
            $schema['address'] = [
                '@type' => 'PostalAddress',
                'streetAddress' => $store['address'],
            ];
        }

        return $schema;
    }

    /**
     * @param list<array{text?: string, href?: string}> $items Same shape
     *        every listing controller already builds for `$data['breadcrumbs']`
     *        (OpenCart's own `text`/`href` keys - not renamed here so the
     *        controller can pass that array straight through).
     * @return array<string, mixed>
     */
    public function breadcrumbList(array $items): array
    {
        $elements = [];
        $position = 1;

        foreach ($items as $item) {
            if (empty($item['text']) || empty($item['href'])) {
                continue;
            }

            $elements[] = [
                '@type' => 'ListItem',
                'position' => $position,
                'name' => $item['text'],
                'item' => $item['href'],
            ];

            $position++;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $elements,
        ];
    }

    /**
     * @param array{
     *     name: string,
     *     url: string,
     *     image?: ?string,
     *     description?: ?string,
     *     sku?: ?string,
     *     brand?: ?string,
     *     price?: float|int|string|false|null,
     *     currency?: ?string,
     *     in_stock?: bool,
     *     rating_value?: float|int|string|null,
     *     rating_count?: int|null
     * } $product Raw (not currency-formatted) values - resolving them from
     *        OpenCart's `product_info`/tax/currency services stays the
     *        caller's job, same division of responsibility as
     *        `ProductViewProvider`.
     * @return array<string, mixed>
     */
    public function product(array $product): array
    {
        foreach (['name', 'url'] as $required) {
            if (empty($product[$required])) {
                throw new \InvalidArgumentException("SchemaOrgBuilder::product() requires a non-empty '{$required}' key.");
            }
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product['name'],
            'url' => $product['url'],
        ];

        if (!empty($product['image'])) {
            $schema['image'] = $product['image'];
        }

        if (!empty($product['description'])) {
            $schema['description'] = $product['description'];
        }

        if (!empty($product['sku'])) {
            $schema['sku'] = $product['sku'];
        }

        if (!empty($product['brand'])) {
            $schema['brand'] = ['@type' => 'Brand', 'name' => $product['brand']];
        }

        if (array_key_exists('price', $product) && $product['price'] !== null && $product['price'] !== false && $product['price'] !== '') {
            $schema['offers'] = [
                '@type' => 'Offer',
                'url' => $product['url'],
                'priceCurrency' => $product['currency'] ?? 'USD',
                'price' => number_format((float) $product['price'], 2, '.', ''),
                'availability' => !empty($product['in_stock'])
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
            ];
        }

        if (!empty($product['rating_value']) && !empty($product['rating_count'])) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => (string) $product['rating_value'],
                'reviewCount' => (int) $product['rating_count'],
            ];
        }

        return $schema;
    }

    /**
     * Encodes a schema array for embedding inside a
     * `<script type="application/ld+json">` tag. `JSON_HEX_TAG` escapes
     * `<`/`>` as `<`/`>` so a store name/description containing
     * `</script>` can never break out of the tag - the same class of risk
     * the Setup Wizard's Contacts step already guarded against for
     * `javascript:` URLs (see `docs/upstream-diff.md`'s `wizard.php` entry).
     */
    public function toJsonLd(array $schema): string
    {
        return (string) json_encode(
            $schema,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG
        );
    }
}
