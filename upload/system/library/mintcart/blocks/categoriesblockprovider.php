<?php
namespace MintCart\Blocks;

/**
 * Normalizes a raw `mc_block` row (type='categories') into the settings the
 * Categories block's controller-side query and `blocks/categories.twig`
 * need, per MINTCART_TECHNICAL_PLAN.md §16 ("Block Provider" normalizes
 * settings, it does not query anything itself) — same shape as
 * `ProductsBlockProvider`.
 *
 * Deliberately has no OpenCart dependency (no \Registry, no \DB, no
 * category model) — given the block's already-decoded
 * `settings_json`/`content_json`, this class only shapes what the caller
 * (a controller) already has. Querying `ModelCatalogCategory::getCategories()`
 * for the normalized `parent_id` and resizing each category's image stays
 * the controller's job, same division of labour as the Products block.
 */
final class CategoriesBlockProvider
{
    private const DEFAULT_LIMIT = 6;

    /**
     * @param array<string, mixed> $settings The block row's decoded
     *        `settings_json` (structural, non-localized: `parent_id`,
     *        `limit`).
     * @param array<string, mixed> $content The block row's decoded
     *        `content_json` for the current language (localized: `title`).
     *
     * @return array{title: string, parent_id: int, limit: int}
     */
    public function build(array $settings = [], array $content = []): array
    {
        $parentId = isset($settings['parent_id']) ? (int) $settings['parent_id'] : 0;

        $limit = isset($settings['limit']) ? (int) $settings['limit'] : self::DEFAULT_LIMIT;

        return [
            'title' => is_string($content['title'] ?? null) ? $content['title'] : '',
            'parent_id' => $parentId > 0 ? $parentId : 0,
            'limit' => $limit > 0 ? $limit : self::DEFAULT_LIMIT,
        ];
    }
}
