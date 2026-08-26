<?php
namespace MintCart\Blocks;

/**
 * Normalizes a raw `mc_block` row (type='products') into the settings the
 * Products block's controller-side query and `blocks/products.twig` need,
 * per MINTCART_TECHNICAL_PLAN.md §16 ("Block Provider" normalizes settings,
 * it does not query products itself) and §15C/§15D (Homepage Products
 * blocks reuse the same product-listing/ProductViewProvider pipeline every
 * other listing controller uses).
 *
 * Deliberately has no OpenCart dependency (no \Registry, no \DB, no product
 * model) and does not itself fetch any product — same rationale as
 * MintCart\Commerce\ProductViewProvider and MintCart\Design\FooterViewProvider:
 * given the block's already-decoded `settings_json`/`content_json`, this
 * class only shapes what the caller (a controller) already has. Querying
 * products for the normalized `category_id`/`limit`/`sort`/`order` and
 * rendering each one via MintCart\Design\ProductCardListRenderer stays the
 * controller's job, exactly like Category/Search/Special/Manufacturer.
 */
final class ProductsBlockProvider
{
    private const DEFAULT_LIMIT = 8;

    private const DEFAULT_SORT = 'p.date_added';

    private const DEFAULT_ORDER = 'DESC';

    /**
     * @param array<string, mixed> $settings The block row's decoded
     *        `settings_json` (structural, non-localized: `category_id`,
     *        `limit`, `sort`, `order`).
     * @param array<string, mixed> $content The block row's decoded
     *        `content_json` for the current language (localized: `title`).
     *
     * @return array{
     *     title: string,
     *     category_id: ?int,
     *     limit: int,
     *     sort: string,
     *     order: string
     * }
     */
    public function build(array $settings = [], array $content = []): array
    {
        $categoryId = isset($settings['category_id']) ? (int) $settings['category_id'] : 0;

        $order = is_string($settings['order'] ?? null) ? strtoupper($settings['order']) : '';

        return [
            'title' => is_string($content['title'] ?? null) ? $content['title'] : '',
            'category_id' => $categoryId > 0 ? $categoryId : null,
            'limit' => isset($settings['limit']) && (int) $settings['limit'] > 0
                ? (int) $settings['limit']
                : self::DEFAULT_LIMIT,
            'sort' => is_string($settings['sort'] ?? null) && $settings['sort'] !== ''
                ? $settings['sort']
                : self::DEFAULT_SORT,
            'order' => in_array($order, ['ASC', 'DESC'], true) ? $order : self::DEFAULT_ORDER,
        ];
    }
}
