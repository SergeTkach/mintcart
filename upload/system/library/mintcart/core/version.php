<?php
namespace MintCart\Core;

/**
 * MintCart platform version information.
 *
 * Kept separate from the OpenCart core VERSION constant (system/config/default.php)
 * because MintCart releases independently of the OpenCart upstream baseline it sits on.
 */
final class Version
{
    public const VERSION = '0.1.0-dev';

    public const OPENCART_BASELINE = '3.0.5.1';

    public static function get(): string
    {
        return self::VERSION;
    }

    public static function toArray(): array
    {
        return [
            'version' => self::VERSION,
            'opencart_baseline' => self::OPENCART_BASELINE,
        ];
    }
}
