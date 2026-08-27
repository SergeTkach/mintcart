<?php
namespace MintCart\Blocks;

/**
 * Normalizes a raw `mc_block` row (type='text') into the settings the
 * Text block's `blocks/text.twig` needs, per MINTCART_TECHNICAL_PLAN.md
 * §16 ("Block Provider" normalizes settings) and
 * MINTCART_IMPLEMENTATION_GUIDE.md §51's "Text" block (distinct from
 * "Text + Image", which is a separate, not-yet-started block type).
 *
 * Deliberately has no OpenCart dependency (no \Registry, no \DB) — same
 * rationale as MintCart\Blocks\ProductsBlockProvider: given the block's
 * already-decoded `settings_json`/`content_json`, this class only shapes
 * what the caller (a controller) already has. The `content` field is raw
 * HTML (e.g. from an admin rich-text editor) — this provider does not
 * sanitize it; same trust boundary as OpenCart's own Information page
 * `description` field, which the stock `default` theme already renders
 * with `|raw` (`catalog/view/theme/default/template/information/information.twig`).
 */
final class TextBlockProvider
{
    private const DEFAULT_TEXT_ALIGN = 'left';

    /**
     * @param array<string, mixed> $settings The block row's decoded
     *        `settings_json` (structural, non-localized: `text_align`).
     * @param array<string, mixed> $content The block row's decoded
     *        `content_json` for the current language (localized: `title`,
     *        `content`).
     *
     * @return array{
     *     title: string,
     *     content: string,
     *     text_align: 'left'|'center'
     * }
     */
    public function build(array $settings = [], array $content = []): array
    {
        $align = is_string($settings['text_align'] ?? null) ? strtolower($settings['text_align']) : '';

        return [
            'title' => is_string($content['title'] ?? null) ? $content['title'] : '',
            'content' => is_string($content['content'] ?? null) ? $content['content'] : '',
            'text_align' => in_array($align, ['left', 'center'], true) ? $align : self::DEFAULT_TEXT_ALIGN,
        ];
    }
}
