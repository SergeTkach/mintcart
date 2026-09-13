<?php
namespace MintCart\Design;

/**
 * Normalizes the rows `ModelCatalogInformation::getInformations()` already
 * returns into the `{items: [{id, name, image}], total}` shape
 * `McEntityPicker`'s `fetchItems` contract expects
 * (`docs/developer/components/entity-picker.md`) — the third entity
 * backend after `CategoryPickerProvider`/`ManufacturerPickerProvider`, same
 * shape and same rationale (no OpenCart dependency, so unit-testable in
 * isolation; fetching rows and enforcing admin permission stay
 * `ControllerMintcartEntity`'s job, upstream of this class).
 *
 * Unlike category/manufacturer, `ModelCatalogInformation::getInformations()`
 * has no `filter_name` support at all (its `$data`-driven query path only
 * understands `sort`/`order`/`start`/`limit` — see the model source) — so,
 * unlike the other two providers (which only page an already-filtered row
 * set), this one also does the name filtering itself, in PHP, against the
 * full row set the controller passes in unfiltered. Adding `filter_name`
 * support to `ModelCatalogInformation` for a single new consumer would be a
 * genuine OpenCart-core-file modification (`CLAUDE.md` §10); information
 * page counts are small (a handful to a few dozen even for a large store),
 * so PHP-side filtering plus PHP-side paging is not a real cost today —
 * same reasoning that already justified PHP-side paging alone for category
 * and manufacturer.
 *
 * Matching is case-insensitive substring (mirrors
 * `ModelCatalogCategory::getCategories()`'s `LIKE '%…%'` semantics, the
 * more permissive of the two existing backends' matching styles).
 */
final class InformationPickerProvider
{
    /**
     * @param array<int, array{information_id: int|string, title: string}> $rows
     *        The full, unfiltered result of
     *        `ModelCatalogInformation::getInformations()`.
     * @param string $filterName Case-insensitive substring to match against
     *        each row's `title`. Empty string matches every row.
     *
     * @return array{items: array<int, array{id: int, name: string, image: null}>, total: int}
     */
    public function build(array $rows, string $filterName, int $page, int $limit): array
    {
        $page = max(1, $page);
        $limit = max(1, $limit);

        if ($filterName !== '') {
            $rows = array_values(array_filter($rows, static function (array $row) use ($filterName): bool {
                return mb_stripos((string) $row['title'], $filterName) !== false;
            }));
        }

        $total = count($rows);
        $slice = array_slice($rows, ($page - 1) * $limit, $limit);

        $items = [];

        foreach ($slice as $row) {
            $items[] = [
                'id' => (int) $row['information_id'],
                'name' => strip_tags(html_entity_decode((string) $row['title'], ENT_QUOTES, 'UTF-8')),
                // ModelCatalogInformation::getInformations() doesn't select
                // an image column - information pages have no thumbnail
                // concept in OpenCart. McEntityPicker already treats a null
                // image as "no thumbnail for this row".
                'image' => null,
            ];
        }

        return [
            'items' => $items,
            'total' => $total,
        ];
    }
}
