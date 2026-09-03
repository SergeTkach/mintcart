<?php
namespace MintCart\Commerce;

/**
 * Normalizes product rows into the AJAX Search response contract
 * described in MINTCART_TECHNICAL_PLAN.md §26 and
 * MINTCART_IMPLEMENTATION_GUIDE.md §59 ("Theme lens only renders the
 * result"), the first real increment of Phase 15's `SearchService`
 * (`docs/IMPLEMENTATION_STATUS.md` Phase 15 — deliberately deferred until
 * now, see `MintCart\Design\HeaderViewProvider`'s own docblock, which
 * named this exact gap).
 *
 * Deliberately a smaller shape than `ProductViewProvider`'s full
 * `product`/`display`/`context` contract — quick search results are a
 * lightweight suggestions list (search-as-you-type/header dropdown), not
 * a full product-card grid. Functional Scope §14 lists the minimum result
 * fields as image/name/URL/price/special; this class produces exactly
 * that shape, nothing more.
 *
 * Deliberately has no OpenCart dependency (no \Registry, no \DB, no
 * currency/tax/url services), same reasoning `CartService`/
 * `ProductViewProvider` already established — resolving thumbnails,
 * formatting currency and running the actual product query stay
 * `ControllerMintcartSearch`'s job upstream of this class, so it is
 * unit-testable in isolation.
 */
final class SearchService
{
    /**
     * @param list<array{
     *     id: int|string,
     *     name: string,
     *     url: string,
     *     image: ?string,
     *     price?: string|false,
     *     special?: string|false
     * }> $rows Already-resolved product rows (thumb resized, price/special
     *        already formatted via the caller's currency/tax services, or
     *        `false` when a price shouldn't be shown — same "false, not
     *        null" convention `ProductViewProvider::build()` uses).
     * @param int $total The real, store-wide filtered match count (from
     *        `ModelCatalogProduct::getTotalProducts($filterData)`), not
     *        `count($rows)` — a caller almost always passes a `$rows` page
     *        smaller than the full match count for a quick-results list.
     *
     * @return array{items: list<array{id: int, name: string, url: string, image: ?string, price: string|false, special: string|false}>, total: int}
     */
    public function results(array $rows, int $total): array
    {
        $items = [];

        foreach ($rows as $row) {
            $items[] = [
                'id' => (int) $row['id'],
                'name' => strip_tags(html_entity_decode((string) $row['name'], ENT_QUOTES, 'UTF-8')),
                'url' => (string) $row['url'],
                'image' => $row['image'] ?? null,
                'price' => $row['price'] ?? false,
                'special' => $row['special'] ?? false,
            ];
        }

        return [
            'items' => $items,
            'total' => max(0, $total),
        ];
    }
}
