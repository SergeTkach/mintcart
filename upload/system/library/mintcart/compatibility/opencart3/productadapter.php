<?php

declare(strict_types=1);

namespace MintCart\Compatibility\OpenCart3;

/**
 * Adapter over OpenCart 3's catalog product model
 * (`MINTCART_TECHNICAL_PLAN.md` §5: `MintCart\Compatibility\OpenCart3\ProductAdapter`
 * is one of the six named adapters in that list).
 *
 * Both OpenCart's storefront (`catalog/model/catalog/product.php`) and
 * admin (`admin/model/catalog/product.php`) product models expose
 * `getProduct(int $product_id): array` with the same "empty array when
 * nothing matches" shape `CategoryAdapter::find()` already documents for
 * categories — OpenCart 3's `\DB\MySQLi` result always returns `array()`
 * for `->row` on a no-match query, never `null`/`false`.
 *
 * `ControllerMintcartCart::add()` and `ControllerMintcartQuickorder::submit()`
 * (both MintCart-owned, `upload/catalog/controller/mintcart/*.php`) already
 * had, inline, the exact same duplicated existence check:
 *
 * ```php
 * $product_info = $this->model_catalog_product->getProduct($product_id);
 * if (!$product_info) {
 *     // respond with error_product
 * }
 * ```
 *
 * The same "extract a real, already-duplicated inline pattern into the
 * Compatibility layer" shape `CategoryAdapter`/`ExtensionAdapter` already
 * used — not a hypothetical future need invented ahead of a consumer
 * (`CLAUDE.md` §85). Wired into both call sites in this same increment,
 * since neither file is touched by any other currently open pull request.
 *
 * Deliberately duck-typed rather than type-hinted against either concrete
 * OpenCart model (there is no `class ModelCatalogProduct` to
 * `use`/autoload outside a booted OpenCart request) — the same reasoning
 * `CategoryAdapter`/`SettingsAdapter`/`ExtensionAdapter`/`CustomerAdapter`
 * already document. Any object exposing `getProduct(int): array` works;
 * in production that is `$this->model_catalog_product` after
 * `$this->load->model('catalog/product')`, on either the storefront or
 * admin side.
 */
final class ProductAdapter
{
    /**
     * @var object An object exposing getProduct(int): array — in
     *             production, OpenCart's storefront or admin product model.
     */
    private object $productModel;

    public function __construct(object $productModel)
    {
        $this->productModel = $productModel;
    }

    /**
     * The product row for `$productId`, or `null` if no product with that
     * id exists (or exists but is filtered out by the storefront model's
     * own status/date_available/store conditions). Never throws for a
     * missing id — OpenCart's own `getProduct()` returns an empty
     * row/array in that case on both models, not a specific "not found"
     * signal.
     *
     * @return array<string, mixed>|null
     */
    public function find(int $productId): ?array
    {
        $row = $this->productModel->getProduct($productId);

        return is_array($row) && $row !== [] ? $row : null;
    }

    /**
     * Whether a product with this id exists (per `find()`'s rules).
     * `ControllerMintcartCart::add()`/`ControllerMintcartQuickorder::submit()`'s
     * own existence checks (`!$this->model_catalog_product->getProduct($product_id)`)
     * are exactly this.
     */
    public function exists(int $productId): bool
    {
        return $this->find($productId) !== null;
    }
}
