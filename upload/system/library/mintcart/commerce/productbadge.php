<?php
namespace MintCart\Commerce;

/**
 * Manual product badges ("stickers": "Bestseller", "-20%", "Eco") set by the
 * store owner in Marketing → Badges and stored in `mc_product_badge`
 * (MINTCART_FUNCTIONAL_SCOPE.md §11 "configurable badges/stickers").
 *
 * Pure functions, no OpenCart dependency: the admin controller uses
 * normalize() to validate a submitted form, the catalog side uses
 * toView() to turn a stored row into the `badges[]` entry
 * ProductViewProvider appends after the automatic ones (sale / out of
 * stock / new).
 */
final class ProductBadge
{
    public const MAX_LABEL_LENGTH = 32;

    /** Used when a badge has no colour of its own: the neutral mc-badge look. */
    public const NO_COLOR = '';

    /**
     * @param array<string, mixed> $input Raw form values: label, color.
     * @return array{label: string, color: string}|null Null when the label is
     *         empty or the colour is not a #rrggbb value.
     */
    public static function normalize(array $input): ?array
    {
        $label = trim((string) ($input['label'] ?? ''));
        $label = preg_replace('/\s+/u', ' ', $label) ?? '';

        if ($label === '' || mb_strlen($label) > self::MAX_LABEL_LENGTH) {
            return null;
        }

        $color = strtolower(trim((string) ($input['color'] ?? '')));

        if ($color !== '' && !preg_match('/^#[0-9a-f]{6}$/', $color)) {
            return null;
        }

        return ['label' => $label, 'color' => $color];
    }

    /**
     * @param array<string, mixed> $row A stored mc_product_badge row.
     * @return array{type: string, label: string, color: string, on_color: string}
     */
    public static function toView(array $row): array
    {
        $color = strtolower((string) ($row['color'] ?? ''));

        // The colour ends up in a style attribute: only ever a plain #rrggbb.
        if (!preg_match('/^#[0-9a-f]{6}$/', $color)) {
            $color = '';
        }

        return [
            'type' => 'custom',
            'label' => (string) ($row['label'] ?? ''),
            'color' => $color,
            'on_color' => $color === '' ? '' : self::readableTextColor($color),
        ];
    }

    /** Black or white, whichever has the better contrast on the given #rrggbb. */
    public static function readableTextColor(string $hex): string
    {
        $r = hexdec(substr($hex, 1, 2));
        $g = hexdec(substr($hex, 3, 2));
        $b = hexdec(substr($hex, 5, 2));

        $luminance = (0.2126 * self::channel($r)) + (0.7152 * self::channel($g)) + (0.0722 * self::channel($b));

        // White text wins when the background is darker than the midpoint of
        // the two contrast ratios (~0.179).
        return $luminance > 0.179 ? '#1d1f23' : '#ffffff';
    }

    private static function channel(int $value): float
    {
        $c = $value / 255;

        return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    }
}
