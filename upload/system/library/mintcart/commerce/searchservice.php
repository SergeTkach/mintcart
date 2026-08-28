<?php
namespace MintCart\Commerce;

/**
 * Normalizes AJAX search results for the Theme API's Search contract
 * (MINTCART_TECHNICAL_PLAN.md §26, MINTCART_IMPLEMENTATION_GUIDE.md §59,
 * MINTCART_FUNCTIONAL_SCOPE.md §14) — the same normalization role
 * `CartService` plays for the cart, applied to a search result set.
 *
 * Deliberately has no OpenCart dependency (no \Registry, no \DB, no
 * `model_catalog_product`) so it is unit-testable in isolation — running
 * the actual product query (`model_catalog_product->getProducts()`),
 * resizing thumbs and formatting currency all stay the controller's job
 * upstream of this class, exactly the split `CartService`/
 * `ProductViewProvider` already established.
 *
 * Deliberately a smaller item shape than `ProductViewProvider`'s full
 * `ProductView` contract (id, name, url, image, price, special only) —
 * Technical Plan §26 documents this as the AJAX Search response's own
 * minimum, distinct from the full product-card contract a listing page
 * renders. A search-results *page* (as opposed to this suggest/typeahead
 * endpoint) still goes through the normal Category/Search controller +
 * `ProductViewProvider` pipeline; this class only serves the lightweight
 * JSON endpoint.
 */
final class SearchService
{
    /**
     * Below this length a query is almost always a stray keystroke, not a
     * real search intent — treating it as invalid lets the controller skip
     * running a `LIKE '%x%'` product query on every keypress of a
     * typeahead field.
     */
    private const MIN_QUERY_LENGTH = 2;

    /**
     * Trims and collapses internal whitespace, then caps the length so an
     * accidentally pasted block of text can't reach the product query as a
     * single filter value. Deliberately does not strip punctuation/HTML —
     * that stays OpenCart's own `model_catalog_product->getProducts()`
     * filter handling, unchanged by this class.
     */
    public function normalizeQuery(string $raw): string
    {
        $query = trim($raw);
        $query = preg_replace('/\s+/', ' ', $query) ?? $query;

        return mb_substr($query, 0, 128);
    }

    /**
     * @see MIN_QUERY_LENGTH
     */
    public function isQueryValid(string $query): bool
    {
        return mb_strlen($query) >= self::MIN_QUERY_LENGTH;
    }

    /**
     * @param list<array{
     *     id: int|string,
     *     name: string,
     *     url: string,
     *     image: string,
     *     price: string|false,
     *     special: string|false
     * }> $items Already-resolved result rows (image already resized,
     *        price/special already formatted via `$this->currency`) — same
     *        "presentation values already resolved upstream" contract
     *        `ProductViewProvider::build()`/`CartService::snapshot()` use.
     * @param string $query The normalized query this result set answers
     *        (echoed back so the caller can guard against a stale response
     *        overwriting a newer keystroke's result in the UI).
     * @param int $total Total matching products (may exceed `count(items)`
     *        when the controller capped the result list, e.g. a
     *        typeahead's "top 8" limit).
     *
     * @return array{query: string, count: int, items: list<array<string, mixed>>}
     */
    public function results(array $items, string $query, int $total): array
    {
        return [
            'query' => $query,
            'count' => $total,
            'items' => array_values($items),
        ];
    }
}
