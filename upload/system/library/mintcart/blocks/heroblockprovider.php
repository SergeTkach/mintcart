<?php
namespace MintCart\Blocks;

/**
 * Normalizes a raw `mc_block` row (type='hero') into the settings
 * `blocks/hero.twig` needs, per MINTCART_TECHNICAL_PLAN.md §16 ("Block
 * Provider" normalizes settings) and MINTCART_IMPLEMENTATION_GUIDE.md §51
 * (Hero is one of the first-wave Homepage Builder block types).
 *
 * Deliberately has no OpenCart dependency (no \Registry, no \DB, no image
 * model) — same rationale as MintCart\Blocks\ProductsBlockProvider and
 * MintCart\Commerce\ProductViewProvider: given the block's already-decoded
 * `settings_json`/`content_json`, this class only shapes what the caller (a
 * controller) already has. Resolving the `image` path into a resized URL
 * (via OpenCart's `tool/image` model, mirroring how the stock Banner module
 * handles module-configured images) stays the controller's job.
 */
final class HeroBlockProvider
{
    private const DEFAULT_IMAGE_WIDTH = 1920;

    private const DEFAULT_IMAGE_HEIGHT = 600;

    /**
     * @param array<string, mixed> $settings The block row's decoded
     *        `settings_json` (structural, non-localized: `image`,
     *        `image_width`, `image_height`, `cta_url`).
     * @param array<string, mixed> $content The block row's decoded
     *        `content_json` for the current language (localized: `title`,
     *        `subtitle`, `cta_label`).
     *
     * @return array{
     *     title: string,
     *     subtitle: string,
     *     cta_label: string,
     *     cta_url: string,
     *     image: ?string,
     *     image_width: int,
     *     image_height: int
     * }
     */
    public function build(array $settings = [], array $content = []): array
    {
        $image = $settings['image'] ?? null;

        return [
            'title' => is_string($content['title'] ?? null) ? $content['title'] : '',
            'subtitle' => is_string($content['subtitle'] ?? null) ? $content['subtitle'] : '',
            'cta_label' => is_string($content['cta_label'] ?? null) ? $content['cta_label'] : '',
            'cta_url' => is_string($settings['cta_url'] ?? null) ? $settings['cta_url'] : '',
            'image' => is_string($image) && $image !== '' ? $image : null,
            'image_width' => isset($settings['image_width']) && (int) $settings['image_width'] > 0
                ? (int) $settings['image_width']
                : self::DEFAULT_IMAGE_WIDTH,
            'image_height' => isset($settings['image_height']) && (int) $settings['image_height'] > 0
                ? (int) $settings['image_height']
                : self::DEFAULT_IMAGE_HEIGHT,
        ];
    }
}
