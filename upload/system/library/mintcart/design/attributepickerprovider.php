<?php
namespace MintCart\Design;

/**
 * Normalizes the rows `ModelCatalogAttribute::getAttributes()` already
 * returns into the `{items: [{id, name, image}], total}` shape
 * `McEntityPicker`'s `fetchItems` contract expects
 * (`docs/developer/components/entity-picker.md`).
 *
 * Same shape and reasoning as `CategoryPickerProvider`: no OpenCart
 * dependency (unit-testable in isolation), and paging happens here, in
 * PHP, over the full filtered result set — `ModelCatalogAttribute` has
 * `getTotalAttributes()` (unfiltered, store-wide) and
 * `getTotalAttributesByAttributeGroupId()` (filtered by group, not name),
 * but no *filtered-by-name* total-count method to pair with
 * `getAttributes()`'s own `start`/`limit` SQL support. Attribute counts
 * are small (dozens, even for a large catalog's option/attribute set) so
 * this isn't a real cost today — see
 * `docs/developer/components/entity-picker.md` "Attribute backend" for
 * the full writeup.
 */
final class AttributePickerProvider
{
    /**
     * @param array<int, array{attribute_id: int|string, name: string}> $rows
     *        The full filtered (but unpaged) result of
     *        `ModelCatalogAttribute::getAttributes($filterData)`.
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
                'id' => (int) $row['attribute_id'],
                'name' => strip_tags(html_entity_decode((string) $row['name'], ENT_QUOTES, 'UTF-8')),
                // ModelCatalogAttribute::getAttributes() has no image
                // column - attributes have no image concept in OpenCart,
                // same as categories and information pages.
                'image' => null,
            ];
        }

        return [
            'items' => $items,
            'total' => $total,
        ];
    }
}
