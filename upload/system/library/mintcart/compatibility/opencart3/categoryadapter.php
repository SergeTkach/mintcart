<?php

declare(strict_types=1);

namespace MintCart\Compatibility\OpenCart3;

/**
 * Adapter over OpenCart 3's catalog category model
 * (`MINTCART_TECHNICAL_PLAN.md` §5: `MintCart\Compatibility\OpenCart3\CategoryAdapter`
 * is one of the six named adapters in that list).
 *
 * OpenCart ships two separate category model classes —
 * `admin/model/catalog/category.php` (used by admin screens, including the
 * Phase 17 category drag & drop `move()` action, and the category Entity
 * Picker backend) and `catalog/model/catalog/category.php` (used by the
 * storefront) — that diverge in most of their methods, but both expose
 * `getCategory(int $category_id): array` with the same shape (a DB row, or
 * an empty array when nothing matches — OpenCart 3's `\DB\MySQLi` result
 * always returns `array()` for `->row` on a no-match query, never `null`
 * or `false`). This adapter wraps that shared surface, plus the admin
 * model's `getCategoryPath()` ancestor-chain lookup, the same way
 * `CustomerAdapter` wraps OpenCart's two customer models.
 *
 * `ControllerCatalogCategory::move()` (Phase 17, `upload/admin/controller/catalog/category.php`)
 * already has exactly the duplication this adapter exists to remove: an
 * inline `!$this->model_catalog_category->getCategory($id)` existence
 * check repeated twice, and a `foreach` loop turning `getCategoryPath()`'s
 * row list into a flat `path_id` array for `CategoryMoveValidator` — the
 * same "extract a real, already-duplicated inline pattern into the
 * Compatibility layer" shape `ExtensionAdapter` used for
 * `ModelMintcartReadiness::hasEnabledExtension()`.
 *
 * Deliberately duck-typed rather than type-hinted against either concrete
 * OpenCart model (there is no `class ModelCatalogCategory` to
 * `use`/autoload outside a booted OpenCart request) — the same reasoning
 * `SettingsAdapter`/`ExtensionAdapter`/`CustomerAdapter` already document.
 * Any object exposing `getCategory(int): array` and
 * `getCategoryPath(int): array` works; in production that is
 * `$this->model_catalog_category` after `$this->load->model('catalog/category')`,
 * on either the admin or storefront side.
 *
 * `getCategoryPath()` is only declared/used on the admin model today (the
 * storefront model has no equivalent) — `getAncestorPathIds()` is
 * therefore only safe to call with an adapter built around the admin
 * model. Calling it against a storefront-shaped double without that
 * method throws a normal PHP `Error`, same as calling any other
 * undeclared method — no special handling is added here, since inventing
 * a fallback for a method OpenCart's own storefront model never had would
 * hide a real caller mistake instead of surfacing it.
 */
final class CategoryAdapter
{
    /**
     * @var object An object exposing getCategory(int): array (and, for
     *             getAncestorPathIds(), getCategoryPath(int): array) — in
     *             production, OpenCart's admin or catalog category model.
     */
    private object $categoryModel;

    public function __construct(object $categoryModel)
    {
        $this->categoryModel = $categoryModel;
    }

    /**
     * The category row for `$categoryId`, or `null` if no category with
     * that id exists (or exists but is filtered out by the storefront
     * model's own status/store/language conditions). Never throws for a
     * missing id — OpenCart's own `getCategory()` returns an empty
     * row/array in that case on both models, not a specific "not found"
     * signal.
     *
     * @return array<string, mixed>|null
     */
    public function find(int $categoryId): ?array
    {
        $row = $this->categoryModel->getCategory($categoryId);

        return is_array($row) && $row !== [] ? $row : null;
    }

    /**
     * Whether a category with this id exists (per `find()`'s rules).
     * `ControllerCatalogCategory::move()`'s own existence checks
     * (`!$this->model_catalog_category->getCategory($category_id)`,
     * repeated for both `category_id` and `parent_id`) are exactly this.
     */
    public function exists(int $categoryId): bool
    {
        return $this->find($categoryId) !== null;
    }

    /**
     * Every `path_id` from `getCategoryPath($categoryId)` — the category's
     * own ancestor chain, including itself, from the `category_path`
     * closure table. Feeds `MintCart\Catalog\CategoryMoveValidator::validate()`'s
     * `$newParentAncestorPathIds` argument directly.
     *
     * `ControllerCatalogCategory::move()`'s own version of this is a
     * four-line inline `foreach` building `$ancestor_path_ids[] = $result['path_id']`
     * — this method is that loop, extracted once so a future caller (a
     * second drag & drop-style move endpoint, or the category edit form's
     * own `error_parent` check) doesn't have to re-write it.
     *
     * @return list<int>
     */
    public function getAncestorPathIds(int $categoryId): array
    {
        $ids = [];

        foreach ($this->categoryModel->getCategoryPath($categoryId) as $row) {
            $ids[] = (int) $row['path_id'];
        }

        return $ids;
    }
}
