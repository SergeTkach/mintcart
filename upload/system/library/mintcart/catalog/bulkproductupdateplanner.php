<?php
namespace MintCart\Catalog;

/**
 * First increment of Phase 16 (Bulk Editor, `MINTCART_TECHNICAL_PLAN.md`
 * §29, `MINTCART_IMPLEMENTATION_GUIDE.md` §62-63,
 * `MINTCART_FUNCTIONAL_SCOPE.md` §7): validates a bulk product-field-change
 * request and splits it into resumable batches, without touching the
 * database itself.
 *
 * Deliberately has no OpenCart dependency (no \Registry, no \DB), same
 * reasoning as `MintCart\Catalog\CategoryMoveValidator` and
 * `MintCart\Update\PackageManifest`: a pure, unit-testable planning step
 * that a future admin controller/model can call before actually writing
 * anything, instead of validation and DB writes being tangled together in
 * one big controller method.
 *
 * Why this stops at "plan", not "apply": `ModelCatalogProduct::editProduct()`
 * is a full-replace write - it deletes and re-inserts
 * `product_description`, `product_attribute`, `product_option`,
 * `product_discount`, `product_special`, `product_image`,
 * `product_to_category`, `product_to_download`, `product_filter`, etc. on
 * every call, so it cannot be handed a partial "just change price and
 * quantity" payload without first loading each product's full existing
 * data and merging the requested field changes into it. That merge-and-
 * write step (and the actual chunked AJAX endpoint calling it) is real,
 * separate work for a follow-up increment once this planning contract
 * exists to build it against - see `docs/developer/catalog/bulk-product-
 * update.md` "Not done yet" for the exact next step.
 */
final class BulkProductUpdatePlanner
{
    /** Batch size used when the caller does not request a specific one. */
    public const DEFAULT_CHUNK_SIZE = 100;

    /**
     * Bulk-editable fields, per `MINTCART_FUNCTIONAL_SCOPE.md` §7's list
     * (status, quantity, price, special, manufacturer, category, stock
     * state), each mapped to the validation rule below that checks it.
     *
     * SEO fields are in that same §7 list ("where safe") but deliberately
     * excluded here: `ControllerCatalogProduct::validateForm()` enforces
     * per-store/per-language `seo_url` uniqueness against the whole table,
     * which is a different, store/language-keyed shape than every other
     * field here (a single scalar applied to every selected product) and a
     * real conflict risk across a multi-product batch - left for a
     * dedicated follow-up rather than bolted on here.
     */
    private const FIELD_VALIDATORS = [
        'status' => 'validateStatus',
        'quantity' => 'validateNonNegativeInt',
        'price' => 'validateNonNegativeFloat',
        'special_price' => 'validateNullableNonNegativeFloat',
        'manufacturer_id' => 'validateNonNegativeInt',
        'stock_status_id' => 'validatePositiveInt',
        'category_ids' => 'validateCategoryIds',
    ];

    /**
     * Validate a bulk-edit request: which products, which fields change to
     * which values. Does not read or write anything - pure input
     * validation plus a batch-count preview.
     *
     * @param list<int|string> $productIds Product IDs to update. Duplicates
     *        are removed and non-positive/non-numeric entries are rejected.
     * @param array<string, mixed> $fields Field => new value. Only keys
     *        present are changed (an admin controller applying the plan
     *        should leave every other field of each product untouched).
     *        Recognised keys: see `FIELD_VALIDATORS` above.
     * @param int $chunkSize Batch size for the `chunks` preview and for
     *        `nextBatch()`. Must be at least 1.
     *
     * @return array{
     *     errors: list<string>,
     *     product_ids: list<int>,
     *     fields: array<string, mixed>,
     *     total: int,
     *     chunk_size: int,
     *     chunks: list<list<int>>
     * } `errors` is empty when the plan is valid. `chunks` is a full
     *   precomputed preview (e.g. for an admin confirmation screen showing
     *   "N products in M batches"); the actual processing loop should
     *   still call `nextBatch()` per request rather than relying on this
     *   array staying in memory across separate AJAX calls.
     */
    public function plan(array $productIds, array $fields, int $chunkSize = self::DEFAULT_CHUNK_SIZE): array
    {
        $errors = [];

        $normalizedIds = $this->normalizeProductIds($productIds);

        if (!$normalizedIds) {
            $errors[] = 'Select at least one product.';
        }

        if (!$fields) {
            $errors[] = 'Choose at least one field to change.';
        }

        $unknownFields = array_diff(array_keys($fields), array_keys(self::FIELD_VALIDATORS));

        foreach ($unknownFields as $unknownField) {
            $errors[] = "\"{$unknownField}\" is not a bulk-editable field.";
        }

        $sanitizedFields = [];

        foreach ($fields as $field => $value) {
            if (!isset(self::FIELD_VALIDATORS[$field])) {
                continue;
            }

            $method = self::FIELD_VALIDATORS[$field];
            $result = $this->{$method}($value);

            if ($result['error'] !== null) {
                $errors[] = "{$field}: {$result['error']}";

                continue;
            }

            $sanitizedFields[$field] = $result['value'];
        }

        if ($chunkSize < 1) {
            $chunkSize = self::DEFAULT_CHUNK_SIZE;
        }

        return [
            'errors' => $errors,
            'product_ids' => $normalizedIds,
            'fields' => $sanitizedFields,
            'total' => count($normalizedIds),
            'chunk_size' => $chunkSize,
            'chunks' => $errors ? [] : array_chunk($normalizedIds, $chunkSize),
        ];
    }

    /**
     * The resumable stepping primitive an AJAX batch-processing loop calls
     * once per request (Technical Plan §29: "100 items → request → progress
     * → next batch"), rather than trying to keep a precomputed chunk list
     * alive across separate stateless HTTP requests. The caller only needs
     * to remember `next_offset` (e.g. a hidden form field or query param)
     * and the original (already-validated) product ID list.
     *
     * @param list<int> $productIds The same normalized list `plan()`
     *        returned in `product_ids`.
     * @param int $offset Where to resume from; 0 on the first call.
     * @param int $chunkSize Must match what `plan()` used, or progress
     *        reporting against `total` will be misleading.
     *
     * @return array{
     *     product_ids: list<int>,
     *     offset: int,
     *     next_offset: int,
     *     total: int,
     *     done: bool
     * }
     */
    public function nextBatch(array $productIds, int $offset, int $chunkSize = self::DEFAULT_CHUNK_SIZE): array
    {
        $productIds = $this->normalizeProductIds($productIds);
        $total = count($productIds);
        $offset = max(0, $offset);

        if ($chunkSize < 1) {
            $chunkSize = self::DEFAULT_CHUNK_SIZE;
        }

        $batch = array_slice($productIds, $offset, $chunkSize);
        $nextOffset = $offset + count($batch);

        return [
            'product_ids' => array_values($batch),
            'offset' => $offset,
            'next_offset' => $nextOffset,
            'total' => $total,
            'done' => $nextOffset >= $total,
        ];
    }

    /**
     * @param list<int|string> $productIds
     * @return list<int>
     */
    private function normalizeProductIds(array $productIds): array
    {
        $normalized = [];

        foreach ($productIds as $productId) {
            if (!is_numeric($productId) || (int) $productId < 1) {
                continue;
            }

            $normalized[(int) $productId] = (int) $productId;
        }

        return array_values($normalized);
    }

    /** @return array{error: string|null, value: mixed} */
    private function validateStatus($value): array
    {
        if ($value === '0' || $value === '1' || $value === 0 || $value === 1 || is_bool($value)) {
            return ['error' => null, 'value' => (int) $value === 1 ? 1 : 0];
        }

        return ['error' => 'must be 0 (disabled) or 1 (enabled).', 'value' => null];
    }

    /** @return array{error: string|null, value: mixed} */
    private function validateNonNegativeInt($value): array
    {
        if (!is_numeric($value) || (float) $value != (int) $value || (int) $value < 0) {
            return ['error' => 'must be a whole number of 0 or more.', 'value' => null];
        }

        return ['error' => null, 'value' => (int) $value];
    }

    /** @return array{error: string|null, value: mixed} */
    private function validatePositiveInt($value): array
    {
        $result = $this->validateNonNegativeInt($value);

        if ($result['error'] !== null) {
            return $result;
        }

        if ($result['value'] < 1) {
            return ['error' => 'must be a whole number of 1 or more.', 'value' => null];
        }

        return $result;
    }

    /** @return array{error: string|null, value: mixed} */
    private function validateNonNegativeFloat($value): array
    {
        if (!is_numeric($value) || (float) $value < 0) {
            return ['error' => 'must be a number of 0 or more.', 'value' => null];
        }

        return ['error' => null, 'value' => (float) $value];
    }

    /**
     * Special price is nullable: `null` means "remove the special price",
     * matching `product_special` having no row for a product rather than a
     * row with a price of 0.
     *
     * @return array{error: string|null, value: mixed}
     */
    private function validateNullableNonNegativeFloat($value): array
    {
        if ($value === null) {
            return ['error' => null, 'value' => null];
        }

        return $this->validateNonNegativeFloat($value);
    }

    /** @return array{error: string|null, value: mixed} */
    private function validateCategoryIds($value): array
    {
        if (!is_array($value)) {
            return ['error' => 'must be a list of category IDs.', 'value' => null];
        }

        $categoryIds = [];

        foreach ($value as $categoryId) {
            if (!is_numeric($categoryId) || (int) $categoryId < 1) {
                return ['error' => 'contains an invalid category ID.', 'value' => null];
            }

            $categoryIds[(int) $categoryId] = (int) $categoryId;
        }

        return ['error' => null, 'value' => array_values($categoryIds)];
    }
}
