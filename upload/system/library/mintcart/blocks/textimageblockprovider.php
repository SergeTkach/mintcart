<?php
namespace MintCart\Blocks;

/**
 * Normalizes a raw `mc_block` row (type='text-image') into the settings
 * `blocks/text-image.twig` needs, per MINTCART_TECHNICAL_PLAN.md §16
 * ("Block Provider" normalizes settings) and
 * MINTCART_IMPLEMENTATION_GUIDE.md §51 (Text/Image is one of the
 * first-wave Homepage Builder block types).
 *
 * Covers both the "Text" and "Text + Image" catalog entries
 * (MINTCART_IMPLEMENTATION_GUIDE.md §33/§26) as one block type: an image is
 * optional, so a block with no `image` setting is a plain text block,
 * exactly like a "Text" block would be — a second, near-identical provider
 * would only duplicate this one's title/body handling.
 *
 * Deliberately has no OpenCart dependency (no \Registry, no \DB, no image
 * model) — same rationale as MintCart\Blocks\HeroBlockProvider and
 * MintCart\Blocks\ProductsBlockProvider: given the block's already-decoded
 * `settings_json`/`content_json`, this class only shapes what the caller (a
 * controller) already has. Resolving the `image` path into a resized URL
 * stays the controller's job.
 */
final class TextImageBlockProvider
{
    private const DEFAULT_IMAGE_WIDTH = 800;

    private const DEFAULT_IMAGE_HEIGHT = 600;

    private const DEFAULT_IMAGE_POSITION = 'right';

    /**
     * @param array<string, mixed> $settings The block row's decoded
     *        `settings_json` (structural, non-localized: `image`,
     *        `image_width`, `image_height`, `image_position`).
     * @param array<string, mixed> $content The block row's decoded
     *        `content_json` for the current language (localized: `title`,
     *        `body`, `image_alt`).
     *
     * @return array{
     *     title: string,
     *     body: string,
     *     image: ?string,
     *     image_alt: string,
     *     image_width: int,
     *     image_height: int,
     *     image_position: string
     * }
     */
    public function build(array $settings = [], array $content = []): array
    {
        $image = $settings['image'] ?? null;

        $imagePosition = is_string($settings['image_position'] ?? null)
            ? strtolower($settings['image_position'])
            : '';

        return [
            'title' => is_string($content['title'] ?? null) ? $content['title'] : '',
            // Rich text (e.g. from a future CKEditor-based admin field) —
            // rendered raw inside `.mc-prose`, same convention OpenCart's
            // own `product/category.twig` already uses for `description`
            // (Twig autoescape is disabled repo-wide, see
            // `upload/system/library/template/twig.php`).
            'body' => is_string($content['body'] ?? null) ? $content['body'] : '',
            'image' => is_string($image) && $image !== '' ? $image : null,
            'image_alt' => is_string($content['image_alt'] ?? null) ? $content['image_alt'] : '',
            'image_width' => isset($settings['image_width']) && (int) $settings['image_width'] > 0
                ? (int) $settings['image_width']
                : self::DEFAULT_IMAGE_WIDTH,
            'image_height' => isset($settings['image_height']) && (int) $settings['image_height'] > 0
                ? (int) $settings['image_height']
                : self::DEFAULT_IMAGE_HEIGHT,
            'image_position' => in_array($imagePosition, ['left', 'right'], true)
                ? $imagePosition
                : self::DEFAULT_IMAGE_POSITION,
        ];
    }
}
