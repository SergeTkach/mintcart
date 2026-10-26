<?php
namespace MintCart\Blocks;

/**
 * Homepage Builder "Slider" block: the store's main slider. A step up from
 * a plain banner slider: each slide has a small line above the title
 * ("eyebrow"), a title with an optional highlighted word, a subtitle, a price
 * with an old price, and two buttons.
 *
 * Three layouts (`settings.layout`):
 *   split  - text on one side, the picture on the other (default)
 *   full   - the picture fills the slide, text over it (`color` navy|light
 *            picks light or dark text)
 *   cards  - the slider next to two promo cards (`settings.cards`)
 *
 * Structural data lives in `settings_json`, localized text in `content_json`;
 * slides and cards are matched by position (same convention as Banner Grid
 * and Testimonials). No OpenCart dependency: the controller resolves images
 * and links, this class only normalizes and makes the text safe to print
 * (the template prints it unescaped).
 *
 * Documentation: docs/developer/blocks/slider.md
 */
final class SliderBlockProvider
{
    private const LAYOUTS = ['split', 'full', 'cards'];

    private const COLORS = ['navy', 'light'];

    private const DEFAULT_AUTOPLAY = 6000;

    private const MIN_AUTOPLAY = 2000;

    /**
     * @param array<string, mixed> $settings `layout`, `autoplay` (ms, 0 = off),
     *        `items[]` {image, color, link, btn2_link}, `cards[]` {image, link}
     * @param array<string, mixed> $content `items[]` {eyebrow, title, subtitle,
     *        price, old_price, btn_text, btn2_text, image_alt},
     *        `cards[]` {kicker, title, link_text}
     *
     * @return array{
     *     layout: string,
     *     autoplay: int,
     *     slides: list<array<string, string>>,
     *     cards: list<array<string, string>>
     * }
     */
    public function build(array $settings = [], array $content = []): array
    {
        $layout = is_string($settings['layout'] ?? null) && in_array($settings['layout'], self::LAYOUTS, true)
            ? $settings['layout']
            : 'split';

        $autoplay = isset($settings['autoplay']) ? (int) $settings['autoplay'] : self::DEFAULT_AUTOPLAY;
        // 0 turns autoplay off; anything faster than 2 s is raised to 2 s.
        $autoplay = $autoplay <= 0 ? 0 : max(self::MIN_AUTOPLAY, $autoplay);

        $slides = [];
        $slideSettings = is_array($settings['items'] ?? null) ? array_values($settings['items']) : [];

        foreach (is_array($content['items'] ?? null) ? array_values($content['items']) : [] as $index => $text) {
            if (!is_array($text)) {
                continue;
            }

            $structure = is_array($slideSettings[$index] ?? null) ? $slideSettings[$index] : [];
            $title = self::title($text['title'] ?? '');
            $image = self::raw($structure['image'] ?? '');

            // A slide with neither a picture nor a title has nothing to show.
            if ($title === '' && $image === '') {
                continue;
            }

            $color = is_string($structure['color'] ?? null) && in_array($structure['color'], self::COLORS, true)
                ? $structure['color']
                : 'navy';

            $slides[] = [
                'image' => $image,
                'image_alt' => self::plain($text['image_alt'] ?? '') !== '' ? self::plain($text['image_alt']) : strip_tags($title),
                'color' => $color,
                'link' => self::raw($structure['link'] ?? ''),
                'btn2_link' => self::raw($structure['btn2_link'] ?? ''),
                'eyebrow' => self::plain($text['eyebrow'] ?? ''),
                'title' => $title,
                'subtitle' => self::plain($text['subtitle'] ?? ''),
                'price' => self::plain($text['price'] ?? ''),
                'old_price' => self::plain($text['old_price'] ?? ''),
                'btn_text' => self::plain($text['btn_text'] ?? ''),
                'btn2_text' => self::plain($text['btn2_text'] ?? ''),
            ];
        }

        $cards = [];

        if ($layout === 'cards') {
            $cardSettings = is_array($settings['cards'] ?? null) ? array_values($settings['cards']) : [];

            foreach (is_array($content['cards'] ?? null) ? array_values($content['cards']) : [] as $index => $text) {
                $structure = is_array($cardSettings[$index] ?? null) ? $cardSettings[$index] : [];
                $image = self::raw($structure['image'] ?? '');

                if ($image === '') {
                    continue;
                }

                $text = is_array($text) ? $text : [];

                $cards[] = [
                    'image' => $image,
                    'link' => self::raw($structure['link'] ?? ''),
                    'kicker' => self::plain($text['kicker'] ?? ''),
                    'title' => self::title($text['title'] ?? ''),
                    'link_text' => self::plain($text['link_text'] ?? ''),
                ];

                if (count($cards) === 2) {
                    break;
                }
            }
        }

        return [
            'layout' => $layout,
            'autoplay' => $autoplay,
            'slides' => $slides,
            'cards' => $cards,
        ];
    }

    /** Plain text, escaped for printing. */
    private static function plain($value): string
    {
        return is_string($value) ? htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8') : '';
    }

    /** A path or URL kept as is (the controller resolves and escapes it). */
    private static function raw($value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    /**
     * A title: escaped text where only `<em>` (the highlighted word) and `<br>`
     * survive, without attributes.
     */
    private static function title($value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $escaped = htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');

        return str_replace(
            ['&lt;em&gt;', '&lt;/em&gt;', '&lt;br&gt;', '&lt;br/&gt;', '&lt;br /&gt;'],
            ['<em>', '</em>', '<br>', '<br>', '<br>'],
            $escaped
        );
    }
}
