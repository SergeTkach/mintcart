<?php
namespace MintCart\Catalog;

/**
 * Extracts the "items per page" dropdown option list that
 * `catalog/controller/product/category.php`, `manufacturer.php` and
 * `search.php` each build with an identical inline block: the active
 * theme's configured product limit merged with the fixed `25/50/75/100`
 * choices, deduplicated and sorted ascending
 * (`MINTCART_TECHNICAL_PLAN.md` §15C/§15E/§38C "LimitOptionsBuilder"
 * candidate).
 *
 * Deliberately OpenCart-independent, same "primitive first, wire the
 * controllers in a later increment" pattern
 * `MintCart\Catalog\SortOptionsBuilder` follows: it never calls
 * `$this->url->link()` itself, so the controller still owns building each
 * option's `href` from the returned limit values.
 */
final class LimitOptionsBuilder
{
    /**
     * @return list<int> The distinct limit values, ascending, always
     *   including `$themeLimit` even when it does not match any of the
     *   fixed 25/50/75/100 choices (matches the existing
     *   `array_unique()` + `sort()` behavior exactly).
     */
    public function build(int $themeLimit): array
    {
        $limits = array_unique([$themeLimit, 25, 50, 75, 100]);

        sort($limits);

        return array_values($limits);
    }
}
