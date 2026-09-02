<?php
namespace MintCart\Design;

/**
 * Normalizes the rows `ModelCatalogManufacturer::getManufacturers()`
 * already returns into the `{items: [{id, name, image}], total}` shape
 * `McEntityPicker`'s `fetchItems` contract expects
 * (`docs/developer/components/entity-picker.md`) — the second entity
 * backend after `CategoryPickerProvider`, same shape and same rationale
 * (no OpenCart dependency, so unit-testable in isolation; fetching rows,
 * resolving image thumbnails and enforcing admin permission stay
 * `ControllerMintcartEntity`'s job, upstream of this class).
 *
 * Unlike `CategoryPickerProvider` (`image` always `null` —
 * `ModelCatalogCategory::getCategories()`'s query has no image column),
 * `oc_manufacturer` does have one, so this is the first `*PickerProvider`
 * to return a real thumbnail. The provider itself still does not resolve
 * `image` into a URL — that requires OpenCart's `model_tool_image` (a
 * `Loader`/`Registry`-backed resize+cache call, exactly the kind of
 * OpenCart-specific work this class deliberately stays free of) — the
 * controller resolves each row's raw `image` filename into a URL (or
 * `null` when the manufacturer has none) before calling `build()`, under
 * the `image_url` key this class reads.
 *
 * Paging is done here, in PHP, over the full filtered result set — same
 * reason as `CategoryPickerProvider`: `ModelCatalogManufacturer` has no
 * *filtered* total-count method (only `getTotalManufacturers()`,
 * unfiltered, store-wide) to pair with `getManufacturers()`'s own SQL
 * `start`/`limit` support. Manufacturer lists are typically small (dozens,
 * rarely more even for a large catalog), so this isn't a real cost today.
 */
final class ManufacturerPickerProvider
{
    /**
     * @param array<int, array{manufacturer_id: int|string, name: string, image_url?: ?string}> $rows
     *        The full filtered (but unpaged) result of
     *        `ModelCatalogManufacturer::getManufacturers($filterData)`,
     *        each row augmented by the controller with an `image_url` key
     *        (already resolved via `model_tool_image`, or `null`).
     *
     * @return array{items: array<int, array{id: int, name: string, image: ?string}>, total: int}
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
                'id' => (int) $row['manufacturer_id'],
                'name' => strip_tags(html_entity_decode((string) $row['name'], ENT_QUOTES, 'UTF-8')),
                'image' => $row['image_url'] ?? null,
            ];
        }

        return [
            'items' => $items,
            'total' => $total,
        ];
    }
}
