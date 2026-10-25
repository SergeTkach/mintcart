<?php
namespace MintCart\Commerce;

/**
 * Which phone input mask the storefront uses: the store's own format from
 * `Business → Contacts` when one is set, otherwise the default for the
 * store's country from `system/mintcart/data/phone-masks.json`, otherwise
 * none (inputs stay free-form). `#` stands for one digit.
 *
 * Pure: no OpenCart dependency. The masking itself lives in
 * `assets/src/js/components/phone-mask.js`.
 */
class PhoneMask
{
    /**
     * @param array<string, string> $defaults ISO 3166-1 alpha-2 => mask
     */
    public static function resolve(string $custom, string $iso2, array $defaults): string
    {
        $custom = trim($custom);

        if ($custom !== '') {
            return $custom;
        }

        return $defaults[strtoupper($iso2)] ?? '';
    }

    /**
     * `resolve()` with the defaults read from `phone-masks.json`.
     */
    public static function forStore(string $custom, string $iso2, string $defaultsFile): string
    {
        $defaults = json_decode((string) @file_get_contents($defaultsFile), true);

        return self::resolve($custom, $iso2, is_array($defaults) ? $defaults : []);
    }

    /**
     * Whether `$phone` holds a full number for `$mask`: as many digits as the
     * mask has `#`. Mirrors `nationalDigits()` in `phone-mask.js` - the country
     * code or a leading trunk `0` may be typed, and are not counted. An empty
     * mask accepts anything.
     */
    public static function isComplete(string $phone, string $mask): bool
    {
        $slots = substr_count($mask, '#');

        if ($slots === 0) {
            return true;
        }

        $prefix = preg_replace('/\D/', '', substr($mask, 0, (int) strpos($mask, '#')));
        $digits = preg_replace('/\D/', '', $phone);

        if ($prefix !== '' && strpos($digits, $prefix) === 0) {
            $digits = substr($digits, strlen($prefix));
        } elseif (strpos($digits, '0') === 0 && $prefix !== '1' && $prefix !== '7') {
            $digits = substr($digits, 1);
        }

        return strlen($digits) === $slots;
    }

    /**
     * A usable mask has at least one `#` and only digits, `#`, spaces and
     * `+ ( ) -`.
     */
    public static function isValid(string $mask): bool
    {
        return strlen($mask) <= 32 && strpos($mask, '#') !== false && preg_match('/^[\d#\s+()\-]+$/', $mask) === 1;
    }
}
