<?php
namespace MintCart\Seo;

/**
 * A meta title / description template: `{name} - {store}`, `{description:160}`.
 *
 * `{key}` is replaced by the variable's text (HTML stripped, whitespace
 * collapsed); `{key:N}` cuts it to N characters on a word boundary and adds
 * an ellipsis. An unknown or empty variable is dropped, and the separators
 * left dangling by it (`Mug -  - Shop`, a trailing ` - `) are cleaned up, so a
 * product without a brand does not print `Mug -  - Shop`.
 *
 * Pure: no OpenCart dependency. Used by `MetaBuilder`; the admin screen
 * `Marketing -> SEO -> Meta tags` previews templates through it as well.
 */
final class MetaTemplate
{
    /** Characters that join the parts of a title and are removed when a part is missing. */
    private const SEPARATORS = '-–—|·•:;,/';

    /**
     * @param array<string, string|int|float|null> $variables
     */
    public static function render(string $template, array $variables): string
    {
        $text = preg_replace_callback(
            '/\{([a-z_]+)(?::(\d+))?\}/',
            static function (array $match) use ($variables): string {
                $value = isset($variables[$match[1]]) ? self::plain((string) $variables[$match[1]]) : '';

                if ($value !== '' && isset($match[2]) && $match[2] !== '') {
                    $value = self::cut($value, (int) $match[2]);
                }

                return $value;
            },
            $template
        );

        return self::tidy((string) $text);
    }

    /** The variable names a template uses, in order (`{description:160}` -> `description`). */
    public static function variables(string $template): array
    {
        preg_match_all('/\{([a-z_]+)(?::\d+)?\}/', $template, $matches);

        return array_values(array_unique($matches[1]));
    }

    private static function plain(string $value): string
    {
        // Descriptions are stored HTML-escaped (&lt;p&gt;): decode, drop the tags, decode the text.
        $value = html_entity_decode(strip_tags(html_entity_decode($value, ENT_QUOTES, 'UTF-8')), ENT_QUOTES, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    private static function cut(string $value, int $length): string
    {
        if ($length < 1 || mb_strlen($value) <= $length) {
            return $value;
        }

        $cut = mb_substr($value, 0, $length);
        $space = mb_strrpos($cut, ' ');

        // Back to the last whole word, unless that would throw away most of the text.
        if ($space !== false && $space > $length * 0.6) {
            $cut = mb_substr($cut, 0, $space);
        }

        return rtrim($cut, " \t\n\r\0\x0B.,;:-–—") . '…';
    }

    /** Collapses spaces, repeated separators and separators at the ends. */
    private static function tidy(string $text): string
    {
        $separators = preg_quote(self::SEPARATORS, '/');

        $text = (string) preg_replace('/\s+/u', ' ', $text);
        // "A - - B", "A -  | B": keep the first separator of a run.
        $text = (string) preg_replace('/([' . $separators . '])(?:\s*[' . $separators . '])+/u', '$1', $text);
        $text = (string) preg_replace('/^[\s' . $separators . ']+|[\s' . $separators . ']+$/u', '', $text);

        return trim($text);
    }
}
