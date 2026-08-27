<?php
namespace MintCart\Blocks;

/**
 * Normalizes a raw `mc_block` row (type='newsletter') into what
 * `blocks/newsletter.twig` needs, per MINTCART_TECHNICAL_PLAN.md §16
 * ("Block Provider" normalizes settings) and
 * MINTCART_IMPLEMENTATION_GUIDE.md §33/§51 (Newsletter is one of the
 * first-wave block types).
 *
 * A pure content block, same shape as CtaBlockProvider: no OpenCart query
 * involved at all. `action`/`method` (where the subscribe form submits to)
 * are structural, non-localized settings; `title`/`text`/`placeholder`/
 * `submit_label` are localized content an admin edits per language.
 *
 * There is no built-in OpenCart endpoint for an anonymous "subscribe with
 * just an email address" form — the closest existing feature,
 * `account/newsletter`, is an opt-in checkbox on an already-registered
 * customer's account page, not a public subscribe target. Mirroring
 * MintCart\Design\FooterViewProvider's own newsletter field (see its
 * `buildNewsletter()`), this provider never invents a submit target: a
 * block with no `action` configured normalizes to `enabled: false`, and
 * `blocks/newsletter.twig` renders the title/text without a dead-end form
 * rather than a subscribe box with nowhere to go
 * (CLAUDE.md §85 — no API/wiring ahead of a real consumer). Once a real
 * subscribe endpoint exists (Commerce API, Phase 15, or a shipped
 * newsletter extension), a store owner can point `action` at it and the
 * form appears with no template change needed.
 */
final class NewsletterBlockProvider
{
    private const DEFAULT_METHOD = 'post';

    /**
     * @param array<string, mixed> $settings The block row's decoded
     *        `settings_json` (structural, non-localized: `action`,
     *        `method`).
     * @param array<string, mixed> $content The block row's decoded
     *        `content_json` for the current language (localized: `title`,
     *        `text`, `placeholder`, `submit_label`).
     *
     * @return array{
     *     title: string,
     *     text: string,
     *     placeholder: string,
     *     submit_label: string,
     *     enabled: bool,
     *     action: string,
     *     method: string
     * }
     */
    public function build(array $settings = [], array $content = []): array
    {
        $action = is_string($settings['action'] ?? null) ? trim($settings['action']) : '';
        $method = is_string($settings['method'] ?? null) ? strtolower($settings['method']) : '';

        return [
            'title' => is_string($content['title'] ?? null) ? $content['title'] : '',
            'text' => is_string($content['text'] ?? null) ? $content['text'] : '',
            'placeholder' => is_string($content['placeholder'] ?? null) ? $content['placeholder'] : '',
            'submit_label' => is_string($content['submit_label'] ?? null) ? $content['submit_label'] : '',
            // Only ever true when a real action URL is also configured -
            // same rule FooterViewProvider::buildNewsletter() enforces, so
            // the template never has to guess whether a blank action means
            // "not configured yet" or "submit to the current page".
            'enabled' => $action !== '',
            'action' => $action,
            'method' => in_array($method, ['post', 'get'], true) ? $method : self::DEFAULT_METHOD,
        ];
    }
}
