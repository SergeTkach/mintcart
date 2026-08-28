<?php
namespace MintCart\Design;

/**
 * Normalizes the rows `ModelCatalogCategory::getCategories()` already
 * returns into the `{items: [{id, name, image}], total}` shape
 * `McEntityPicker`'s `fetchItems` contract expects
 * (`docs/developer/components/entity-picker.md`).
 *
 * Deliberately has no OpenCart dependency (no \Registry, no \DB) so it is
 * unit-testable in isolation, the same reasoning `ProductViewProvider` and
 * `FooterViewProvider` already follow — fetching the rows and enforcing
 * admin permission stay `ControllerMintcartEntity`'s job, upstream of this
 * class.
 *
 * Paging is done here, in PHP, over the full filtered result set rather
 * than via `getCategories()`'s own `start`/`limit` SQL params, because
 * `ModelCatalogCategory` has no *filtered* total-count method to pair with
 * it (only `getTotalCategories()`, which counts every category store-wide,
 * not a `filter_name` match) — see `docs/developer/components/entity-picker.md`
 * "Admin backends" for why a new model method wasn't added to get one.
 * Category lists are small enough (dozens to low hundreds, even for a
 * large catalog) that this is not a real cost today.
 */
final class CategoryPickerProvider
{
    /**
     * @param array<int, array{category_id: int|string, name: string}> $rows
     *        The full filtered (but unpaged) result of
     *        `ModelCatalogCategory::getCategories($filterData)`.
     *
     * @return array{items: array<int, array{id: int, name: string, image: null}>, total: int}
     */
    public function build(array $rows, int $page, int $limit): array
    {
        $page = max(1, $page);
        $limit = max(1, $limit);

        $total = count($rows);
        $slice = array_slice($rows, ($page - 1) * $limit, $limit);

        $items = [];

        foreach ($slice as $row) {
            $items[] = [
                'id' => (int) $row['category_id'],
                'name' => strip_tags(html_entity_decode((string) $row['name'], ENT_QUOTES, 'UTF-8')),
                // ModelCatalogCategory::getCategories() doesn't select an
                // image column - McEntityPicker already treats a null
                // image as "no thumbnail for this row", the same default
                // its getImage() option falls back to.
                'image' => null,
            ];
        }

        return [
            'items' => $items,
            'total' => $total,
        ];
    }
}
