<?php
namespace MintCart\Design;

/**
 * Normalizes the rows `ModelCatalogProduct::getProducts()` already returns
 * into the `{items: [{id, name, image}], total}` shape `McEntityPicker`'s
 * `fetchItems` contract expects (`docs/developer/components/entity-picker.md`),
 * the third entity backend after `CategoryPickerProvider` and
 * `ManufacturerPickerProvider`.
 *
 * Deliberately has no OpenCart dependency, same reasoning as those two -
 * fetching rows, resolving image thumbnails and enforcing admin permission
 * stay `ControllerMintcartEntity`'s job, upstream of this class.
 *
 * Unlike category/manufacturer, this provider does **not** page in PHP.
 * `ModelCatalogProduct::getProducts()` already supports `start`/`limit` at
 * the SQL level, and - unlike `ModelCatalogCategory`/`ModelCatalogManufacturer`
 * - `ModelCatalogProduct::getTotalProducts($data)` accepts the *same*
 * `filter_name` and returns a filtered count, not just an unfiltered
 * store-wide total. So the controller already does real SQL-level paging
 * before calling `build()` here: `$rows` is only the current page, and
 * `$total` is the real filtered count, not `count($rows)`. This is exactly
 * the "once a second PickerProvider needs a filtered count" case
 * `docs/developer/components/entity-picker.md`'s "Admin backends" section
 * flagged as the natural next step - product catalogs are the entity type
 * most likely to be large enough for PHP-side slicing of the full result
 * set to matter.
 */
final class ProductPickerProvider
{
    /**
     * @param array<int, array{product_id: int|string, name: string, image_url?: ?string}> $rows
     *        Already the current page (SQL `start`/`limit` applied by the
     *        controller's `getProducts()` call), each row augmented with an
     *        `image_url` key (resolved via `model_tool_image`, or `null`).
     * @param int $total The filtered, store-wide total from
     *        `ModelCatalogProduct::getTotalProducts($filterData)` - not
     *        `count($rows)`, since `$rows` is only one page.
     *
     * @return array{items: array<int, array{id: int, name: string, image: ?string}>, total: int}
     */
    public function build(array $rows, int $total): array
    {
        $items = [];

        foreach ($rows as $row) {
            $items[] = [
                'id' => (int) $row['product_id'],
                'name' => strip_tags(html_entity_decode((string) $row['name'], ENT_QUOTES, 'UTF-8')),
                'image' => $row['image_url'] ?? null,
            ];
        }

        return [
            'items' => $items,
            'total' => max(0, $total),
        ];
    }
}
