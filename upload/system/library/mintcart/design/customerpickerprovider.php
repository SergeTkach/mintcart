<?php
namespace MintCart\Design;

/**
 * Normalizes the rows `ModelCustomerCustomer::getCustomers()` already
 * returns into the `{items: [{id, name, image}], total}` shape
 * `McEntityPicker`'s `fetchItems` contract expects
 * (`docs/developer/components/entity-picker.md`) — the fifth entity
 * backend, after category/manufacturer/product/information/attribute/option.
 *
 * Deliberately has no OpenCart dependency (no \Registry, no \DB) so it is
 * unit-testable in isolation, same reasoning every other `*PickerProvider`
 * follows — fetching rows, resolving a filtered total and enforcing admin
 * permission stay `ControllerMintcartEntity`'s job, upstream of this class.
 *
 * **Paging happens at the SQL level, not in PHP here** — the same design
 * `ProductPickerProvider` uses, and for the same reason:
 * `ModelCustomerCustomer::getTotalCustomers($data)` accepts the same
 * `filter_name` the list query does and returns a real *filtered* count,
 * unlike `ModelCatalogCategory`/`ModelCatalogManufacturer`/
 * `ModelCatalogAttribute` (which only expose an unfiltered total). So
 * `build()` takes an already-paged row set (via `getCustomers()`'s own
 * `start`/`limit` SQL support) plus the real filtered total, instead of
 * slicing/counting a full result set in PHP — customer tables are also the
 * entity type most likely to be large enough (thousands of rows) for PHP-side
 * slicing to actually matter.
 */
final class CustomerPickerProvider
{
    /**
     * @param array<int, array{customer_id: int|string, name: string}> $rows
     *        One already-paged page of `ModelCustomerCustomer::getCustomers($filterData)`
     *        (SQL `start`/`limit` already applied by the caller).
     * @param int $total
     *        The real filtered row count, from
     *        `ModelCustomerCustomer::getTotalCustomers(['filter_name' => …])`.
     *
     * @return array{items: array<int, array{id: int, name: string, image: null}>, total: int}
     */
    public function build(array $rows, int $total): array
    {
        $items = [];

        foreach ($rows as $row) {
            $items[] = [
                'id' => (int) $row['customer_id'],
                'name' => strip_tags(html_entity_decode((string) $row['name'], ENT_QUOTES, 'UTF-8')),
                // Customers have no image concept in OpenCart - McEntityPicker
                // already treats a null image as "no thumbnail for this row".
                'image' => null,
            ];
        }

        return [
            'items' => $items,
            'total' => max(0, $total),
        ];
    }
}
