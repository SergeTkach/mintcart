<?php
namespace MintCart\Blocks;

/**
 * Normalizes a raw `mc_block` row (type='brands') into the list of logo
 * items `blocks/brands.twig` needs, per MINTCART_TECHNICAL_PLAN.md §16
 * ("Block Provider" normalizes settings) and
 * MINTCART_IMPLEMENTATION_GUIDE.md §51 (Brands is one of the first-wave
 * block types).
 *
 * Same split as BannerGridBlockProvider/HeroBlockProvider between
 * structural, non-localized data (`settings_json`: each item's `image`/
 * `url`/`image_width`/`image_height`) and localized data (`content_json`:
 * the block's `title` plus each item's `alt`), matched up by array
 * position — a brand logo item has no stable ID of its own, same as a
 * Banner Grid item.
 *
 * Deliberately simpler than BannerGridBlockProvider: no `columns` setting
 * (brands render as a wrapping logo strip, not a fixed-column grid — see
 * `blocks/brands.twig`) and no `caption` (a brand logo is identified by
 * its image/alt text alone, unlike a banner which is a clickable
 * marketing tile).
 *
 * Deliberately has no OpenCart dependency — resizing each item's `image`
 * via `tool/image` stays the controller's job, same as every other
 * image-bearing block provider.
 */
final class BrandsBlockProvider
{
    private const DEFAULT_IMAGE_WIDTH = 160;

    private const DEFAULT_IMAGE_HEIGHT = 80;

    /**
     * @param array<string, mixed> $settings The block row's decoded
     *        `settings_json` (`items[]` each with `image`/`url`/
     *        `image_width`/`image_height`).
     * @param array<string, mixed> $content The block row's decoded
     *        `content_json` for the current language (`title`, `items[]`
     *        each with `alt`).
     *
     * @return array{
     *     title: string,
     *     items: list<array{image: string, url: string, image_width: int, image_height: int, alt: string}>
     * }
     */
    public function build(array $settings = [], array $content = []): array
    {
        $settingsItems = is_array($settings['items'] ?? null) ? array_values($settings['items']) : [];
        $contentItems = is_array($content['items'] ?? null) ? array_values($content['items']) : [];

        $items = [];

        foreach ($settingsItems as $index => $settingsItem) {
            if (!is_array($settingsItem)) {
                continue;
            }

            $image = is_string($settingsItem['image'] ?? null) ? $settingsItem['image'] : '';

            // A brand without a logo image has nothing to render - skip it
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
                    : self::DEFAULT_IMAGE_WIDTH,
                'image_height' => isset($settingsItem['image_height']) && (int) $settingsItem['image_height'] > 0
                    ? (int) $settingsItem['image_height']
                    : self::DEFAULT_IMAGE_HEIGHT,
                'alt' => is_string($contentItem['alt'] ?? null) ? $contentItem['alt'] : '',
            ];
        }

        return [
            'title' => is_string($content['title'] ?? null) ? $content['title'] : '',
            'items' => $items,
        ];
    }
}
