<?php
namespace MintCart\Blocks;

/**
 * Normalizes a raw `mc_block` row (type='benefits') into the settings
 * `blocks/benefits.twig` needs, per MINTCART_TECHNICAL_PLAN.md §16 (a
 * Block Provider normalizes settings, it does not query anything) - same
 * "no OpenCart dependency" shape as ProductsBlockProvider.
 *
 * Benefits is a pure content block (a row of trust-badge style items -
 * "Free shipping", "Secure payment", ...): no image resize, no product
 * query, no OpenCart model of any kind - the controller only needs to
 * call build() and hand the result straight to BlockRenderer.
 *
 * `items[].icon` is structural (non-localized, lives in `settings_json`) -
 * `items[].title`/`items[].description` are localized (`content_json`) -
 * matched by array position, the same settings/content item split every
 * other multi-item block provider in this codebase uses.
 */
final class BenefitsBlockProvider
{
    private const DEFAULT_ICON = 'check';

    /**
     * @param array<string, mixed> $settings Decoded `settings_json`:
     *        `items[].icon` (a Bootstrap Icons key,
     *        MINTCART_IMPLEMENTATION_GUIDE.md §21).
     * @param array<string, mixed> $content Decoded `content_json` for the
     *        current language: `title` (block heading) and
     *        `items[].title`/`items[].description`.
     *
     * @return array{
     *     title: string,
     *     items: list<array{icon: string, title: string, description: string}>
     * }
     */
    public function build(array $settings = [], array $content = []): array
    {
        $settingsItems = is_array($settings['items'] ?? null) ? array_values($settings['items']) : [];
        $contentItems = is_array($content['items'] ?? null) ? array_values($content['items']) : [];

        $count = max(count($settingsItems), count($contentItems));

        $items = [];

        for ($i = 0; $i < $count; $i++) {
            $settingsItem = is_array($settingsItems[$i] ?? null) ? $settingsItems[$i] : [];
            $contentItem = is_array($contentItems[$i] ?? null) ? $contentItems[$i] : [];

            $title = is_string($contentItem['title'] ?? null) ? trim($contentItem['title']) : '';

            if ($title === '') {
                // A position with no title is not a real benefit (e.g. a
                // stray settings-only entry) - skip it rather than render
                // an empty card.
                continue;
            }

            $items[] = [
                'icon' => is_string($settingsItem['icon'] ?? null) && $settingsItem['icon'] !== ''
                    ? $settingsItem['icon']
                    : self::DEFAULT_ICON,
                'title' => $title,
                'description' => is_string($contentItem['description'] ?? null) ? $contentItem['description'] : '',
            ];
        }

        return [
            'title' => is_string($content['title'] ?? null) ? $content['title'] : '',
            'items' => $items,
        ];
    }
}
