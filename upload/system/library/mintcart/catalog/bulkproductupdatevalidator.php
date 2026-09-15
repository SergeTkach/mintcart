<?php
namespace MintCart\Catalog;

/**
 * Field/value validation for Phase 16 (Bulk Editor,
 * `MINTCART_FUNCTIONAL_SCOPE.md` §7/§17, `MINTCART_TECHNICAL_PLAN.md` §29,
 * `MINTCART_IMPLEMENTATION_GUIDE.md` §62 — "Store -> Products" inline
 * edit / mass actions). Bulk-sets one of a small whitelisted set of
 * `oc_product` columns (status, quantity, price, manufacturer_id) across
 * a multi-selected set of products from `Catalog -> Products`.
 * Category/stock-status/SEO fields are natural follow-ups, same shape.
 *
 * Deliberately has no OpenCart dependency (no `\Registry`, no `\DB`) so it
 * is unit-testable in isolation, same reasoning as
 * `MintCart\Catalog\CategoryMoveValidator`. Actually reading/writing
 * `oc_product` rows stays `ModelCatalogProduct::updateProductField()`'s job
 * (and `ControllerCatalogProduct::bulkUpdate()`'s permission check),
 * upstream of this class.
 */
final class BulkProductUpdateValidator
{
    public const FIELDS = ['status', 'quantity', 'price', 'manufacturer_id'];

    public function isValidField(string $field): bool
    {
        return in_array($field, self::FIELDS, true);
    }

    /**
     * @return string|null An error message, or null when `$value` is a
     *         valid value for `$field`. Caller must check `isValidField()`
     *         first — an unknown field always returns an error here too,
     *         defensively, but with a generic message.
     */
    public function validateValue(string $field, string $value): ?string
    {
        switch ($field) {
            case 'status':
                return in_array($value, ['0', '1'], true)
                    ? null
                    : 'Status must be 0 (disabled) or 1 (enabled).';

            case 'quantity':
                return (ctype_digit($value))
                    ? null
                    : 'Quantity must be a whole number, 0 or greater.';

            case 'price':
                return (is_numeric($value) && (float) $value >= 0)
                    ? null
                    : 'Price must be a number, 0 or greater.';

            case 'manufacturer_id':
                return (ctype_digit($value))
                    ? null
                    : 'Manufacturer must be a manufacturer ID, or 0 for none.';

            default:
                return 'Unknown bulk-update field.';
        }
    }

    /**
     * Casts every raw selected-product id to a positive int, drops
     * anything that isn't one (0, negative, non-numeric), and de-duplicates
     * — the same `selected[]` checkbox array `validateDelete()`/
     * `validateCopy()` already trust as-is; a bulk field write is a
     * heavier operation to repeat, so this run validates it explicitly.
     *
     * @param list<mixed> $rawIds
     * @return list<int>
     */
    public function normalizeProductIds(array $rawIds): array
    {
        $ids = [];

        foreach ($rawIds as $rawId) {
            $id = (int) $rawId;

            if ($id > 0 && !in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
