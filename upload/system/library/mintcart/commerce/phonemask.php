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
     * A usable mask has at least one `#` and only digits, `#`, spaces and
     * `+ ( ) -`.
     */
    public static function isValid(string $mask): bool
    {
        return strlen($mask) <= 32 && strpos($mask, '#') !== false && preg_match('/^[\d#\s+()\-]+$/', $mask) === 1;
    }
}
