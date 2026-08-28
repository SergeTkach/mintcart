<?php
namespace MintCart\Blocks;

/**
 * Normalizes a raw `mc_block` row (type='gallery') into the list of photo
 * items `blocks/gallery.twig` needs, per MINTCART_TECHNICAL_PLAN.md §16
 * ("Block Provider" normalizes settings) and MINTCART_IMPLEMENTATION_GUIDE.md
 * §51 (Gallery is one of the first-wave block types).
 *
 * Same structural/localized split as BannerGridBlockProvider/BrandsBlockProvider
 * between `settings_json` (`columns`, each item's `image`/`image_width`/
 * `image_height`) and `content_json` (`title`, each item's `alt`/`caption`),
 * matched up by array position - a gallery item has no stable ID of its own,
 * same rationale as every other list-of-items block provider.
 *
 * Unlike BannerGridBlockProvider, an item has no `url` - a Gallery block is a
 * plain photo grid (store photos, lifestyle imagery), not a set of banner
 * links, so `blocks/gallery.twig` renders each item as a non-interactive
 * `<figure>` rather than an `<a>`.
 *
 * Deliberately has no OpenCart dependency - resizing each item's `image` via
 * `tool/image` stays the controller's job, same as every other block
 * provider that references an uploaded image.
 */
final class GalleryBlockProvider
{
    private const DEFAULT_COLUMNS = 4;

    // Only divisors of Bootstrap's 12-column grid, so col_width is always a
    // whole number - same constraint BannerGridBlockProvider uses, extended
    // with 6 since a photo grid commonly wants more, smaller columns than a
    // banner grid does.
    private const ALLOWED_COLUMNS = [2, 3, 4, 6];

    /**
     * @param array<string, mixed> $settings The block row's decoded
     *        `settings_json` (`columns`, `items[]` each with `image`/
     *        `image_width`/`image_height`).
     * @param array<string, mixed> $content The block row's decoded
     *        `content_json` for the current language (`title`, `items[]`
     *        each with `alt`/`caption`).
     *
     * @return array{
     *     title: string,
     *     columns: int,
     *     col_width: int,
     *     items: list<array{image: string, image_width: ?int, image_height: ?int, alt: string, caption: string}>
     * }
     */
    public function build(array $settings = [], array $content = []): array
    {
        $columns = isset($settings['columns']) ? (int) $settings['columns'] : self::DEFAULT_COLUMNS;

        if (!in_array($columns, self::ALLOWED_COLUMNS, true)) {
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

            // A gallery item without an image has nothing to render - skip
            // it rather than emitting an empty <img>.
            if ($image === '') {
                continue;
            }

            $contentItem = is_array($contentItems[$index] ?? null) ? $contentItems[$index] : [];

            $items[] = [
                'image' => $image,
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
            // template - $columns is constrained to ALLOWED_COLUMNS, each an
            // exact divisor of Bootstrap's 12-column grid.
            'col_width' => intdiv(12, $columns),
            'items' => $items,
        ];
    }
}
