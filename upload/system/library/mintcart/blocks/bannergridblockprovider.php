<?php
namespace MintCart\Blocks;

/**
 * Normalizes a raw `mc_block` row (type='banner-grid') into the list of
 * banner items `blocks/banner-grid.twig` needs, per
 * MINTCART_TECHNICAL_PLAN.md §16 ("Block Provider" normalizes settings) and
 * MINTCART_IMPLEMENTATION_GUIDE.md §51 (Banner Grid is one of the
 * first-wave block types).
 *
 * Same split as ProductsBlockProvider/HeroBlockProvider between structural,
 * non-localized data (`settings_json`: each item's `image`/`url`/
 * `image_width`/`image_height`) and localized data (`content_json`: the
 * block's `title` plus each item's `alt`/`caption`) - per
 * MINTCART_TECHNICAL_PLAN.md §16's `mc_block_description.content_json`
 * rationale ("structural settings never get duplicated per language").
 * Settings items and content items are matched up by array position, since
 * a banner grid item has no stable ID of its own (the whole item list is
 * replaced wholesale when the admin UI saves the block, same as any other
 * block's settings).
 *
 * Deliberately has no OpenCart dependency - resizing each item's `image`
 * via `tool/image` stays the controller's job, same as
 * MintCart\Blocks\HeroBlockProvider's background image.
 */
final class BannerGridBlockProvider
{
    private const DEFAULT_COLUMNS = 3;

    private const MIN_COLUMNS = 2;

    private const MAX_COLUMNS = 4;

    /**
     * @param array<string, mixed> $settings The block row's decoded
     *        `settings_json` (`columns`, `items[]` each with `image`/`url`/
     *        `image_width`/`image_height`).
     * @param array<string, mixed> $content The block row's decoded
     *        `content_json` for the current language (`title`, `items[]`
     *        each with `alt`/`caption`).
     *
     * @return array{
     *     title: string,
     *     columns: int,
     *     col_width: int,
     *     items: list<array{image: string, url: string, image_width: ?int, image_height: ?int, alt: string, caption: string}>
     * }
     */
    public function build(array $settings = [], array $content = []): array
    {
        $columns = isset($settings['columns']) ? (int) $settings['columns'] : self::DEFAULT_COLUMNS;

        if ($columns < self::MIN_COLUMNS || $columns > self::MAX_COLUMNS) {
            $columns = self::DEFAULT_COLUMNS;
        }

        $settingsItems = is_array($settings['items'] ?? null) ? array_values($settings['items']) : [];
        $contentItems = is_array($content['items'] ?? null) ? array_values($content['items']) : [];

        $items = [];

        foreach ($settingsItems as $index => $settingsItem) {
            if (!is_array($settingsItem)) {
                continue;
            }

            $image = is_string($settingsItem['image'] ?? null) ? $settingsItem['image'] : '';

            // A banner without an image has nothing to render - skip it
            // rather than emitting an empty <img>.
            if ($image === '') {
                continue;
            }

            $contentItem = is_array($contentItems[$index] ?? null) ? $contentItems[$index] : [];

            $items[] = [
                'image' => $image,
                'url' => is_string($settingsItem['url'] ?? null) ? $settingsItem['url'] : '',
                'image_width' => isset($settingsItem['image_width']) && (int) $settingsItem['image_width'] > 0
                    ? (int) $settingsItem['image_width']
                    : null,
                'image_height' => isset($settingsItem['image_height']) && (int) $settingsItem['image_height'] > 0
                    ? (int) $settingsItem['image_height']
                    : null,
                'alt' => is_string($contentItem['alt'] ?? null) ? $contentItem['alt'] : '',
                'caption' => is_string($contentItem['caption'] ?? null) ? $contentItem['caption'] : '',
            ];
        }

        return [
            'title' => is_string($content['title'] ?? null) ? $content['title'] : '',
            'columns' => $columns,
            // Precomputed here rather than with Twig arithmetic in the
            // template - $columns is constrained to {2,3,4}, each an exact
            // divisor of Bootstrap's 12-column grid.
            'col_width' => intdiv(12, $columns),
            'items' => $items,
        ];
    }
}
