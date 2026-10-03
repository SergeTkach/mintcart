<?php
namespace MintCart\Commerce;

/**
 * The lead-lifecycle states a `mc_quick_order` row moves through
 * (`202609041200_create_mc_quick_order.php`'s own docblock: "a lead for
 * staff to call back and turn into a real order, or reject" — `status`
 * tracks that follow-up, it is not an OpenCart order status). Kept as its
 * own small, OpenCart-independent primitive (no `\Registry`, no `\DB`)
 * rather than a bare array of string literals scattered across the admin
 * controller/view/language file, the same reasoning
 * `MintCart\Catalog\BulkProductUpdateValidator::FIELDS` already
 * established for a small fixed whitelist a controller needs to validate
 * against.
 *
 * Deliberately just four states, matching a simple phone-lead workflow —
 * not a general-purpose workflow engine. `new` is the migration's own
 * `DEFAULT 'new'`.
 */
final class QuickOrderStatus
{
    public const NEW = 'new';
    public const CONTACTED = 'contacted';
    public const CONVERTED = 'converted';
    public const REJECTED = 'rejected';

    public const STATUSES = [self::NEW, self::CONTACTED, self::CONVERTED, self::REJECTED];

    public static function isValid(string $status): bool
    {
        return in_array($status, self::STATUSES, true);
    }

    /**
     * The `mintcart/quickorder` admin language file's `text_status_<status>`
     * key for a given status — the same "language key per fixed value"
     * shape `MintCart\Seo\SystemRoutes::labelKey()` already established for
     * this codebase's other small fixed-choice admin screens.
     */
    public static function labelKey(string $status): string
    {
        return 'text_status_' . $status;
    }
}
