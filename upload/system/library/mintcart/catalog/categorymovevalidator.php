<?php
namespace MintCart\Catalog;

/**
 * Cycle-prevention check for Phase 17 (Categories drag & drop,
 * `MINTCART_TECHNICAL_PLAN.md` §30, `MINTCART_IMPLEMENTATION_GUIDE.md`
 * §64): "prevent cycle" from the documented move flow
 * ("begin transaction, validate target, prevent cycle, change parent,
 * rebuild affected category_path subtree, update sort order, commit").
 *
 * Deliberately has no OpenCart dependency (no \Registry, no \DB) so it is
 * unit-testable in isolation, same reasoning as
 * `MintCart\Commerce\CartService::validateOptions()`. Fetching the target
 * parent's ancestor chain (`ModelCatalogCategory::getCategoryPath()`, the
 * existing `category_path` closure-table lookup) and actually moving the
 * category stay the admin controller/model's job upstream of this class.
 *
 * The check itself is not new logic invented for this class: it is the
 * same "is the category being moved one of the new parent's own
 * ancestors?" test `ControllerCatalogCategory::validateForm()` already
 * performs for the category edit form (`error_parent` — "The parent
 * category you have chosen is a child of the current one!") — extracted
 * here so the drag & drop `move()` action can reuse the identical,
 * already-proven rule instead of re-deriving it.
 */
final class CategoryMoveValidator
{
    /**
     * @param int $categoryId The category being moved.
     * @param int $newParentId The target parent (0 = top-level, always valid).
     * @param list<int|string> $newParentAncestorPathIds Every `path_id`
     *        from `ModelCatalogCategory::getCategoryPath($newParentId)` —
     *        the target parent's own ancestor chain, including itself.
     *        Pass an empty array when `$newParentId` is 0.
     *
     * @return string|null An error message, or null when the move is valid.
     */
    public function validate(int $categoryId, int $newParentId, array $newParentAncestorPathIds): ?string
    {
        if ($newParentId === $categoryId) {
            return 'A category cannot become its own parent.';
        }

        foreach ($newParentAncestorPathIds as $pathId) {
            if ((int) $pathId === $categoryId) {
                return 'The parent category you have chosen is a child of the current one!';
            }
        }

        return null;
    }
}
