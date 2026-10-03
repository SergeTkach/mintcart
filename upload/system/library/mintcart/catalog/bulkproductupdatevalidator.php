<?php
namespace MintCart\Catalog;

/**
 * Field/value validation for Phase 16 (Bulk Editor,
 * `MINTCART_FUNCTIONAL_SCOPE.md` §7/§17, `MINTCART_TECHNICAL_PLAN.md` §29,
 * `MINTCART_IMPLEMENTATION_GUIDE.md` §62 — "Store -> Products" inline
 * edit / mass actions). Bulk-sets one of a small whitelisted set of
 * fields (status, quantity, price, manufacturer_id, stock_status_id are
 * plain `oc_product` columns; `special_price` is a `oc_product_special`
 * row instead — see `ModelCatalogProduct::updateProductField()`'s own
 * docblock for why that one field needs different storage) across a
 * multi-selected set of products from `Catalog -> Products`.
 * `category_ids` replaces (not merges into) each selected product's
 * `product_to_category` rows — the same "whatever the caller sends is the
 * new full set" semantics `ControllerCatalogProduct::editProduct()`'s own
 * category well already has, just scoped to this one relation instead of
 * a full product-form save. Unlike every scalar field above, its value is
 * a list of category IDs, not a single string `bulk_value` — see
 * `validateCategoryIds()`/`normalizeCategoryIds()` below, kept separate
 * from `validateValue()` (which stays typed for the five plain-text
 * fields) rather than widening that method's signature. It is also
 * validated (as a distinct, independently-built field set) by
 * `MintCart\Catalog\BulkProductUpdatePlanner`, which this class does not
 * depend on or replace.
 *
 * Deliberately has no OpenCart dependency (no `\Registry`, no `\DB`) so it
 * is unit-testable in isolation, same reasoning as
 * `MintCart\Catalog\CategoryMoveValidator`. Actually reading/writing
 * `oc_product`/`oc_product_special`/`oc_product_to_category` rows stays
 * `ModelCatalogProduct::updateProductField()`'s job (and
 * `ControllerCatalogProduct::bulkUpdate()`'s permission check), upstream
 * of this class.
 */
final class BulkProductUpdateValidator
{
    public const FIELDS = ['status', 'quantity', 'price', 'manufacturer_id', 'stock_status_id', 'special_price', 'category_ids'];

    public function isValidField(string $field): bool
    {
        return in_array($field, self::FIELDS, true);
    }

    /**
     * For the five plain-text `bulk_value` fields only — `category_ids` is
     * a list, not a string, so it always errors here defensively; the
     * caller must route it to `validateCategoryIds()` instead (see
     * `ControllerCatalogProduct::validateBulkUpdate()`).
     *
     * @return string|null An error message, or null when `$value` is a
     *         valid value for `$field`. Caller must check `isValidField()`
     *         first — an unknown field always errors here too, defensively,
     *         but with a generic message.
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

            case 'stock_status_id':
                return (ctype_digit($value))
                    ? null
                    : 'Stock status must be a stock status ID, or 0 for none.';

            case 'special_price':
                // Blank means "remove the special price" (no oc_product_special
                // row), matching MintCart\Catalog\BulkProductUpdatePlanner's own
                // validateNullableNonNegativeFloat() semantics for this field.
                return ($value === '' || (is_numeric($value) && (float) $value >= 0))
                    ? null
                    : 'Special price must be blank (to remove it) or a number 0 or greater.';

            case 'category_ids':
                return 'Categories must be set via validateCategoryIds(), not validateValue().';

            default:
                return 'Unknown bulk-update field.';
        }
    }

    /**
     * `category_ids`'s own validator — a list of category IDs, not a
     * scalar `bulk_value` string, so it cannot share `validateValue()`'s
     * signature. An empty list is valid on purpose: it means "remove
     * every category from the selected products", the same
     * blank-means-clear semantics `validateValue()`'s `special_price` case
     * already has for its own field.
     *
     * @param list<mixed> $categoryIds
     * @return string|null An error message, or null when every entry is a
     *         positive integer (or the list is empty).
     */
    public function validateCategoryIds(array $categoryIds): ?string
    {
        foreach ($categoryIds as $categoryId) {
            if (!is_numeric($categoryId) || (int) $categoryId < 1) {
                return 'Categories must be a list of category IDs.';
            }
        }

        return null;
    }

    /**
     * Casts every raw category id to a positive int, drops anything that
     * isn't one, and de-duplicates — call only after `validateCategoryIds()`
     * has confirmed the raw list is valid, same two-step
     * validate-then-normalize shape as `normalizeProductIds()` below.
     *
     * @param list<mixed> $rawCategoryIds
     * @return list<int>
     */
    public function normalizeCategoryIds(array $rawCategoryIds): array
    {
        $ids = [];

        foreach ($rawCategoryIds as $rawId) {
            $id = (int) $rawId;

            if ($id > 0 && !in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
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
