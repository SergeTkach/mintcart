<?php
namespace MintCart\Design;

/**
 * Normalizes the rows `ModelCatalogOption::getOptions()` already returns
 * into the `{items: [{id, name, image, type}], total}` shape
 * `McEntityPicker`'s `fetchItems` contract expects
 * (`docs/developer/components/entity-picker.md`).
 *
 * Same shape as `CategoryPickerProvider`/`ManufacturerPickerProvider`, plus
 * one extra field: no OpenCart dependency, so it's unit-testable in
 * isolation.
 *
 * Paging is done here, in PHP, over the full filtered result set, for the
 * same reason as category: `ModelCatalogOption` has `getTotalOptions()`
 * (unfiltered, store-wide) but no *filtered* total-count method to pair
 * with `getOptions()`'s own `start`/`limit` SQL params. Option catalogs
 * are small (a store-wide, reusable list, not per-product) so this isn't
 * a real cost today.
 *
 * `type` (`o.type` - `ModelCatalogOption::getOptions()` selects `o.*`, so
 * it's already present on every row at no extra query cost) is the one
 * addition over the plain category/manufacturer shape: `product_form.twig`'s
 * Option tab needs it to pick which type-specific form fields to build for
 * a newly-added option (see `ControllerMintcartEntity::option()`, which
 * layers the `option_value` list on top of this provider's output for the
 * types that need one - `select`/`radio`/`checkbox`/`image` - since that
 * requires a per-row DB call this OpenCart-independent class deliberately
 * doesn't make).
 */
final class OptionPickerProvider
{
    /**
     * @param array<int, array{option_id: int|string, name: string, type: string}> $rows
     *        The full filtered (but unpaged) result of
     *        `ModelCatalogOption::getOptions($filterData)`.
     *
     * @return array{items: array<int, array{id: int, name: string, image: null, type: string}>, total: int}
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
                'id' => (int) $row['option_id'],
                'name' => strip_tags(html_entity_decode((string) $row['name'], ENT_QUOTES, 'UTF-8')),
                // ModelCatalogOption::getOptions() doesn't select an image
                // column - options have no image concept of their own
                // (individual option *values* can carry one, options
                // themselves cannot).
                'image' => null,
                'type' => (string) $row['type'],
            ];
        }

        return [
            'items' => $items,
            'total' => $total,
        ];
    }
}
