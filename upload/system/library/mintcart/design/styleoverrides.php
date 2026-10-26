<?php
namespace MintCart\Design;

/**
 * The owner's choices on `Design → Styles` (colours, corners, borders, button
 * look, page width, fonts) turned into a small block of CSS custom properties
 * that sits on top of the chosen preset. A choice left empty means "whatever
 * the preset says", so the screen never fights the preset it is layered on.
 *
 * Pure: settings in, CSS out; no OpenCart dependency. The admin screen's live
 * preview (`assets/src/js/components/design-styles.js`) repeats the token
 * values below, so change them in both places.
 */
final class StyleOverrides
{
    public const RADIUS = [
        'sharp'   => ['sm' => '0', 'md' => '0', 'lg' => '0', 'xl' => '0'],
        'soft'    => ['sm' => '0.25rem', 'md' => '0.375rem', 'lg' => '0.5rem', 'xl' => '0.75rem'],
        'rounded' => ['sm' => '0.5rem', 'md' => '0.75rem', 'lg' => '1rem', 'xl' => '1.5rem'],
    ];

    public const BORDER = ['light', 'strong'];

    public const BUTTON_STYLE = ['outline', 'soft'];

    /** Page width: Bootstrap's own 1320px container is the default. */
    public const CONTAINER = ['narrow' => '1140px', 'wide' => '1520px'];

    private const SYSTEM_STACK = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif';

    /**
     * id => [label, CSS stack, loaded from Google Fonts]. Every web font here
     * has Cyrillic, so Ukrainian and Russian text keeps the chosen face.
     */
    public const FONTS = [
        'system'     => ['System font', self::SYSTEM_STACK, false],
        'georgia'    => ['Georgia (serif)', 'Georgia, "Times New Roman", serif', false],
        'Inter'      => ['Inter', '"Inter", sans-serif', true],
        'Roboto'     => ['Roboto', '"Roboto", sans-serif', true],
        'Open Sans'  => ['Open Sans', '"Open Sans", sans-serif', true],
        'Montserrat' => ['Montserrat', '"Montserrat", sans-serif', true],
        'Nunito'     => ['Nunito', '"Nunito", sans-serif', true],
        'Manrope'    => ['Manrope', '"Manrope", sans-serif', true],
        'Rubik'      => ['Rubik', '"Rubik", sans-serif', true],
        'Raleway'    => ['Raleway', '"Raleway", sans-serif', true],
        'Fira Sans'  => ['Fira Sans', '"Fira Sans", sans-serif', true],
        'Source Sans 3' => ['Source Sans 3', '"Source Sans 3", sans-serif', true],
        'Golos Text' => ['Golos Text', '"Golos Text", sans-serif', true],
        'Oswald'     => ['Oswald', '"Oswald", sans-serif', true],
        'Playfair Display' => ['Playfair Display', '"Playfair Display", serif', true],
        'Merriweather' => ['Merriweather', '"Merriweather", serif', true],
        'PT Serif'   => ['PT Serif', '"PT Serif", serif', true],
        'Lora'       => ['Lora', '"Lora", serif', true],
    ];

    /**
     * Drops anything that is not a known value, so a hand-edited setting or
     * form can never put arbitrary text into the stylesheet.
     *
     * @param array<string, mixed> $raw keys: primary, accent, radius, border, button_style, container, font_heading, font_body
     * @return array<string, string> the same keys, '' when unset or invalid
     */
    public static function normalize(array $raw): array
    {
        $pick = static function ($value, array $allowed): string {
            $value = (string)$value;

            return in_array($value, $allowed, true) ? $value : '';
        };

        return [
            'primary'      => self::hex($raw['primary'] ?? ''),
            'accent'       => self::hex($raw['accent'] ?? ''),
            'radius'       => $pick($raw['radius'] ?? '', array_keys(self::RADIUS)),
            'border'       => $pick($raw['border'] ?? '', self::BORDER),
            'button_style' => $pick($raw['button_style'] ?? '', self::BUTTON_STYLE),
            'container'    => $pick($raw['container'] ?? '', array_keys(self::CONTAINER)),
            'font_heading' => $pick($raw['font_heading'] ?? '', array_keys(self::FONTS)),
            'font_body'    => $pick($raw['font_body'] ?? '', array_keys(self::FONTS)),
        ];
    }

    /** '#rgb' or '#rrggbb' to lower-case '#rrggbb'; '' when it is neither. */
    public static function hex($value): string
    {
        $value = strtolower(trim((string)$value));

        if (preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/', $value, $m)) {
            return '#' . $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3];
        }

        return preg_match('/^#[0-9a-f]{6}$/', $value) === 1 ? $value : '';
    }

    /** Black or white, whichever reads better on the given colour. */
    public static function onColor(string $hex): string
    {
        $channel = static function (string $pair): float {
            $c = hexdec($pair) / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };

        $luminance = 0.2126 * $channel(substr($hex, 1, 2)) + 0.7152 * $channel(substr($hex, 3, 2)) + 0.0722 * $channel(substr($hex, 5, 2));

        // Contrast against white is 1.05 / (L + 0.05); against near-black 0.0185 text it is (L + 0.05) / 0.0685.
        return 1.05 / ($luminance + 0.05) >= ($luminance + 0.05) / 0.0685 ? '#ffffff' : '#111111';
    }

    /**
     * @param array<string, mixed> $raw see normalize()
     * @return array{css: string, fonts_url: string} css is '' when nothing is overridden
     */
    public static function build(array $raw): array
    {
        $s = self::normalize($raw);
        $root = [];
        $rules = [];

        if ($s['primary'] !== '') {
            $root[] = '--mc-primary:' . $s['primary'];
            $root[] = '--mc-primary-hover:color-mix(in srgb,' . $s['primary'] . ' 85%,#000)';
            $root[] = '--mc-primary-active:color-mix(in srgb,' . $s['primary'] . ' 70%,#000)';
            $root[] = '--mc-on-primary:' . self::onColor($s['primary']);
        }

        if ($s['accent'] !== '') {
            $root[] = '--mc-accent:' . $s['accent'];
            $root[] = '--mc-on-accent:' . self::onColor($s['accent']);
        }

        if ($s['radius'] !== '') {
            foreach (self::RADIUS[$s['radius']] as $size => $value) {
                $root[] = '--mc-radius-' . $size . ':' . $value;
            }
        }

        if ($s['border'] === 'light') {
            $root[] = '--mc-border:color-mix(in srgb,var(--mc-border) 55%,var(--mc-surface))';
        } elseif ($s['border'] === 'strong') {
            $root[] = '--mc-border-width:2px';
            $root[] = '--mc-border:var(--mc-border-strong)';
        }

        if ($s['font_body'] !== '') {
            $root[] = '--mc-font-family-base:' . self::FONTS[$s['font_body']][1];
        }

        if ($s['font_heading'] !== '') {
            $root[] = '--mc-font-family-heading:' . self::FONTS[$s['font_heading']][1];
        } elseif ($s['font_body'] !== '') {
            $root[] = '--mc-font-family-heading:var(--mc-font-family-base)';
        }

        $primaryButton = ':root:root .mc-button--primary,:root:root .mc-button:not([class*="mc-button--"])';

        if ($s['button_style'] === 'outline') {
            $rules[] = $primaryButton . '{--mc-button-bg:transparent;--mc-button-bg-hover:var(--mc-primary);--mc-button-bg-active:var(--mc-primary-active);--mc-button-color:var(--mc-primary);--mc-button-border-color:var(--mc-primary);box-shadow:none}';
            $rules[] = ':root:root .mc-button--primary:hover,:root:root .mc-button:not([class*="mc-button--"]):hover{color:var(--mc-on-primary);box-shadow:none}';
        } elseif ($s['button_style'] === 'soft') {
            $rules[] = $primaryButton . '{--mc-button-bg:color-mix(in srgb,var(--mc-primary) 12%,var(--mc-surface));--mc-button-bg-hover:color-mix(in srgb,var(--mc-primary) 20%,var(--mc-surface));--mc-button-bg-active:color-mix(in srgb,var(--mc-primary) 28%,var(--mc-surface));--mc-button-color:var(--mc-primary-hover);box-shadow:none}';
            $rules[] = ':root:root .mc-button--primary:hover,:root:root .mc-button:not([class*="mc-button--"]):hover{box-shadow:none}';
        }

        if ($s['container'] !== '') {
            $rules[] = '@media (min-width:1400px){:root:root .container{max-width:' . self::CONTAINER[$s['container']] . '}}';
        }

        $css = '';

        if ($root) {
            $css .= ':root:root{' . implode(';', $root) . '}';
        }

        $css .= implode('', $rules);

        return ['css' => $css, 'fonts_url' => self::fontsUrl([$s['font_heading'], $s['font_body']])];
    }

    /**
     * @param list<string> $ids chosen font ids ('' skipped)
     */
    public static function fontsUrl(array $ids): string
    {
        $families = [];

        foreach (array_unique($ids) as $id) {
            if ($id !== '' && self::FONTS[$id][2]) {
                $families[] = 'family=' . str_replace(' ', '+', $id) . ':wght@400;600;700';
            }
        }

        return $families ? 'https://fonts.googleapis.com/css2?' . implode('&', $families) . '&display=swap' : '';
    }
}
