<?php
namespace MintCart\Design;

/**
 * The one SlotRegistry of the request. An extension registers its fragments on
 * it (typically from a handler of an OpenCart event that runs before the page
 * controller, e.g. `catalog/controller/product/product/before`):
 *
 *     \MintCart\Design\Slots::shared()->listen('product.after_price', function (array $context): string {
 *         return '<p>Ships today</p>';
 *     });
 *
 * Controllers ask it for the slots their templates show; see
 * docs/developer/theme-api/slots.md for the list of slots and their context.
 */
final class Slots
{
    private static ?SlotRegistry $registry = null;

    public static function shared(): SlotRegistry
    {
        return self::$registry ??= new SlotRegistry();
    }

    /** Test helper: forget every listener. */
    public static function reset(): void
    {
        self::$registry = null;
    }
}
