<?php
namespace MintCart\Commerce;

/**
 * Normalizes wishlist membership for the Theme API's wishlist contract
 * (MINTCART_FUNCTIONAL_SCOPE.md §11, MINTCART_TECHNICAL_PLAN.md §24,
 * MINTCART_IMPLEMENTATION_GUIDE.md §47) — the same "pure array logic, no
 * OpenCart dependency" role `CartService` plays for the cart, applied to
 * wishlist membership.
 *
 * Deliberately has no OpenCart dependency (no \Registry, no \DB, no
 * `ModelAccountWishlist`). Reading/writing the actual membership list
 * (DB rows for a logged-in customer via `ModelAccountWishlist`, or
 * `$this->session->data['wishlist']` for a guest — the exact split stock
 * `ControllerAccountWishList::add()` already uses) stays
 * `ControllerMintcartWishlist`'s job upstream of this class, the same split
 * `CartService`/`ControllerMintcartCart` already established.
 *
 * Compare (`mc:product-card:compare-toggle`) is **not** covered here — it
 * has no backend consumer yet, see `docs/developer/commerce/wishlist-service.md`
 * "Not done yet".
 */
final class WishlistService
{
    /**
     * De-duplicates and normalizes a raw id list (a DB result's
     * `product_id` column, or a guest session array that may contain
     * duplicates/non-numeric leftovers) into a clean list of positive ints.
     *
     * @param list<mixed> $ids
     * @return list<int>
     */
    public function normalizeIds(array $ids): array
    {
        $normalized = array_map('intval', $ids);
        $normalized = array_filter($normalized, static function (int $id): bool {
            return $id > 0;
        });

        return array_values(array_unique($normalized));
    }

    /**
     * The new membership list after adding/removing one product — pure
     * array logic, no storage of its own. The caller persists the result
     * (an `INSERT`/`DELETE` for a logged-in customer, or writing it back to
     * `$this->session->data['wishlist']` for a guest).
     *
     * @param list<mixed> $ids
     * @param int|string $productId
     * @return list<int>
     */
    public function withProduct(array $ids, $productId, bool $add): array
    {
        $normalized = $this->normalizeIds($ids);
        $productId = (int) $productId;

        if ($add) {
            $normalized[] = $productId;

            return $this->normalizeIds($normalized);
        }

        return array_values(array_filter($normalized, static function (int $id) use ($productId): bool {
            return $id !== $productId;
        }));
    }

    /**
     * @param list<mixed> $ids
     * @param int|string|null $productId When given, `active` reports
     *        whether that product is currently in the (normalized) list;
     *        omitted/null when the caller has no single product in mind
     *        (e.g. a plain refresh), matching `CartService::snapshot()`'s
     *        "presentation values already resolved upstream" contract.
     *
     * @return array{count: int, product_ids: list<int>, active: ?bool}
     */
    public function snapshot(array $ids, $productId = null): array
    {
        $normalized = $this->normalizeIds($ids);

        return [
            'count' => count($normalized),
            'product_ids' => $normalized,
            'active' => $productId === null ? null : in_array((int) $productId, $normalized, true),
        ];
    }
}
