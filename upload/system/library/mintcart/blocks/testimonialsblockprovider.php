<?php
namespace MintCart\Blocks;

/**
 * Normalizes a raw `mc_block` row (type='testimonials') into the items
 * `blocks/testimonials.twig` needs, per MINTCART_TECHNICAL_PLAN.md §16
 * ("Block Provider" normalizes settings, it does not fetch/render anything
 * itself) and MINTCART_IMPLEMENTATION_GUIDE.md §51 (Testimonials is one of
 * the first-wave block types).
 *
 * A testimonial item's structural (non-localized) data — `avatar` path/
 * `avatar_width`/`avatar_height`/`rating` — lives in `settings_json`;
 * localized `quote`/`author_name`/`author_role` live in `content_json`.
 * The two lists are matched up by array position, same convention
 * BannerGridBlockProvider/BenefitsBlockProvider use for their own items —
 * there is no per-item ID to join on, since a Homepage Builder admin UI
 * to manage these rows doesn't exist yet (only hand-inserted mc_block
 * rows do).
 *
 * Deliberately has no OpenCart dependency (no \Registry, no \DB, no image
 * model) — resizing an item's optional `avatar` via `model_tool_image` is
 * the controller's job, same split ProductsBlockProvider/
 * BannerGridBlockProvider use.
 */
final class TestimonialsBlockProvider
{
    private const MIN_RATING = 1;

    private const MAX_RATING = 5;

    /**
     * @param array<string, mixed> $settings The block row's decoded
     *        `settings_json` (structural: `title`, `items[].avatar`,
     *        `items[].avatar_width`, `items[].avatar_height`,
     *        `items[].rating`).
     * @param array<string, mixed> $content The block row's decoded
     *        `content_json` for the current language (localized: `title`,
     *        `items[].quote`, `items[].author_name`, `items[].author_role`).
     *
     * @return array{
     *     title: string,
     *     items: list<array{
     *         quote: string,
     *         author_name: string,
     *         author_role: string,
     *         avatar: ?string,
     *         avatar_width: int,
     *         avatar_height: int,
     *         rating: ?int
     *     }>
     * }
     */
    public function build(array $settings = [], array $content = []): array
    {
        $settingsItems = is_array($settings['items'] ?? null) ? $settings['items'] : [];
        $contentItems = is_array($content['items'] ?? null) ? $content['items'] : [];

        $items = [];

        foreach ($contentItems as $position => $contentItem) {
            if (!is_array($contentItem)) {
                continue;
            }

            $quote = is_string($contentItem['quote'] ?? null) ? trim($contentItem['quote']) : '';
            $authorName = is_string($contentItem['author_name'] ?? null) ? trim($contentItem['author_name']) : '';

            if ($quote === '' || $authorName === '') {
                // A testimonial with no quote or no attributed author is not
                // renderable — dropped rather than showing a blank card.
                continue;
            }

            $settingsItem = is_array($settingsItems[$position] ?? null) ? $settingsItems[$position] : [];

            $rating = isset($settingsItem['rating']) ? (int) $settingsItem['rating'] : null;

            $items[] = [
                'quote' => $quote,
                'author_name' => $authorName,
                'author_role' => is_string($contentItem['author_role'] ?? null) ? trim($contentItem['author_role']) : '',
                'avatar' => is_string($settingsItem['avatar'] ?? null) && $settingsItem['avatar'] !== ''
                    ? $settingsItem['avatar']
                    : null,
                'avatar_width' => isset($settingsItem['avatar_width']) && (int) $settingsItem['avatar_width'] > 0
                    ? (int) $settingsItem['avatar_width']
                    : 64,
                'avatar_height' => isset($settingsItem['avatar_height']) && (int) $settingsItem['avatar_height'] > 0
                    ? (int) $settingsItem['avatar_height']
                    : 64,
                'rating' => $rating !== null && $rating >= self::MIN_RATING && $rating <= self::MAX_RATING
                    ? $rating
                    : null,
            ];
        }

        return [
            'title' => is_string($content['title'] ?? null) ? $content['title'] : '',
            'items' => $items,
        ];
    }
}
