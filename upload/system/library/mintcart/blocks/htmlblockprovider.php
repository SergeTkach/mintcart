<?php
namespace MintCart\Blocks;

/**
 * Normalizes a raw `mc_block` row (type='html') into the data
 * `blocks/html.twig` needs, per MINTCART_FUNCTIONAL_SCOPE.md §10 /
 * MINTCART_IMPLEMENTATION_GUIDE.md §33's first-wave block list ("HTML").
 *
 * The HTML block has no structural `settings_json` fields at all (unlike
 * Products' category_id/limit/sort/order) — its only field is the raw HTML
 * markup itself, which is localized admin-authored content, so it lives in
 * `content_json` (per-language) rather than `settings_json` (structural),
 * matching how MintCart\Blocks\TextBlockProvider treats its `content`
 * field. Deliberately has no OpenCart dependency, same rationale as
 * ProductsBlockProvider/TextBlockProvider — this class only shapes what the
 * caller already has, it does not fetch or sanitize anything itself.
 *
 * The `html` value is rendered with Twig's `|raw` filter by
 * `blocks/html.twig` — this block type exists specifically to let a store
 * owner or admin drop arbitrary markup into the homepage (embed codes,
 * one-off promotional markup, ...), the same trusted-admin-content model
 * OpenCart's own "HTML content" widgets/modules use. It is not a place for
 * unescaped end-user input; block rows are only ever written by an admin
 * with backend access (there is no public-facing block editor yet — Phase
 * 10's Homepage drag-and-drop editor is still not started).
 */
final class HtmlBlockProvider
{
    /**
     * @param array<string, mixed> $settings The block row's decoded
     *        `settings_json`. Unused — the HTML block has no structural
     *        settings, kept only so this provider matches every other
     *        Block Provider's `build(settings, content)` shape.
     * @param array<string, mixed> $content The block row's decoded
     *        `content_json` for the current language (`html`).
     *
     * @return array{html: string}
     */
    public function build(array $settings = [], array $content = []): array
    {
        return [
            'html' => is_string($content['html'] ?? null) ? $content['html'] : '',
        ];
    }
}
