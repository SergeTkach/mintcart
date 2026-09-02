<?php
namespace MintCart\Blocks;

/**
 * Normalizes a raw `mc_block` row (type='cta') into the settings
 * `blocks/cta.twig` needs, per MINTCART_TECHNICAL_PLAN.md §16 ("Block
 * Provider" normalizes settings) and MINTCART_IMPLEMENTATION_GUIDE.md §33's
 * "CTA" block catalog entry.
 *
 * A CTA block is pure content — title, supporting text, and an optional
 * button (label + URL) — with no structural (non-localized) data at all,
 * so everything lives in `content_json`; `settings_json` is accepted for
 * signature consistency with every other block provider but currently
 * unused. Deliberately has no OpenCart dependency (no \Registry, no \DB,
 * no model) — same rationale as ProductsBlockProvider/ProductViewProvider —
 * since there is nothing to query for this block type.
 */
final class CtaBlockProvider
{
    /**
     * @param array<string, mixed> $settings The block row's decoded
     *        `settings_json`. Currently unused — a CTA block has no
     *        structural configuration.
     * @param array<string, mixed> $content The block row's decoded
     *        `content_json` for the current language (localized: `title`,
     *        `text`, `cta_label`, `cta_url`).
     *
     * @return array{
     *     title: string,
     *     text: string,
     *     cta_label: string,
     *     cta_url: string
     * }
     */
    public function build(array $settings = [], array $content = []): array
    {
        $ctaLabel = is_string($content['cta_label'] ?? null) ? trim($content['cta_label']) : '';
        $ctaUrl = is_string($content['cta_url'] ?? null) ? trim($content['cta_url']) : '';

        return [
            'title' => is_string($content['title'] ?? null) ? $content['title'] : '',
            'text' => is_string($content['text'] ?? null) ? $content['text'] : '',
            // A button needs both a label and a URL to be meaningful — a
            // label with no URL (or vice versa) normalizes to "no button"
            // rather than rendering a dead or unlabeled link.
            'cta_label' => $ctaLabel !== '' && $ctaUrl !== '' ? $ctaLabel : '',
            'cta_url' => $ctaLabel !== '' && $ctaUrl !== '' ? $ctaUrl : '',
        ];
    }
}
