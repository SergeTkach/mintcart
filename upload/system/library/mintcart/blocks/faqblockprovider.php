<?php
namespace MintCart\Blocks;

/**
 * Normalizes a raw `mc_block` row (type='faq') into the settings
 * `blocks/faq.twig` needs, per MINTCART_TECHNICAL_PLAN.md §16 ("Block
 * Provider" normalizes settings, it does not query anything itself) —
 * same shape as MintCart\Blocks\BenefitsBlockProvider.
 *
 * Unlike ProductsBlockProvider/BannerGridBlockProvider, an FAQ item has no
 * structural (non-localized) data at all — a question and its answer are
 * both translatable text, so both live in `content_json` and there is
 * nothing for `settings_json` to carry. `$settings` is still accepted for
 * signature consistency with every other Block Provider (and in case a
 * future non-localized setting, e.g. a default-open first item, is ever
 * needed), but is currently unused.
 *
 * Deliberately has no OpenCart dependency (no \Registry, no \DB, no model)
 * — same rationale as every other Block Provider.
 */
final class FaqBlockProvider
{
    /**
     * @param array<string, mixed> $settings The block row's decoded
     *        `settings_json`. Currently unused — see class docblock.
     * @param array<string, mixed> $content The block row's decoded
     *        `content_json` for the current language: `title` plus an
     *        `items` list, each `['question' => string, 'answer' => string]`.
     *
     * @return array{
     *     title: string,
     *     items: list<array{question: string, answer: string}>
     * }
     */
    public function build(array $settings = [], array $content = []): array
    {
        $items = [];

        if (isset($content['items']) && is_array($content['items'])) {
            foreach ($content['items'] as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $question = is_string($item['question'] ?? null) ? trim($item['question']) : '';
                $answer = is_string($item['answer'] ?? null) ? trim($item['answer']) : '';

                // A question with no answer (or vice versa) is not a
                // renderable FAQ entry — skip it rather than passing a
                // half-empty item into the template.
                if ($question === '' || $answer === '') {
                    continue;
                }

                $items[] = [
                    'question' => $question,
                    'answer' => $answer,
                ];
            }
        }

        return [
            'title' => is_string($content['title'] ?? null) ? $content['title'] : '',
            'items' => $items,
        ];
    }
}
