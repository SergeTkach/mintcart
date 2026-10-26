<?php
namespace MintCart\Seo;

/**
 * The fixed whitelist of OpenCart "system pages" `MINTCART_FUNCTIONAL_SCOPE.md`
 * §4 names explicitly (cart, checkout, search, contact, account, login,
 * register, wishlist, compare, special, manufacturer, sitemap) and each
 * one's real `route=` value, checked against the actual controllers in this
 * repo (`CLAUDE.md` §84 — not assumed from memory):
 * `checkout/cart.php`, `checkout/checkout.php`, `product/search.php`,
 * `information/contact.php`, `account/account.php`, `account/login.php`,
 * `account/register.php`, `account/wishlist.php`, `product/compare.php`,
 * `product/special.php`, `product/manufacturer.php` (the manufacturer
 * *listing* page — a specific manufacturer's own page already gets a pretty
 * URL via its `manufacturer_id` `seo_url` row, unrelated to this list),
 * `information/sitemap.php`.
 *
 * No OpenCart dependency — a plain constant map, unit-testable in
 * isolation, same shape as `MintCart\Design\ActiveTheme`. Two real
 * consumers: `ControllerMintcartSeoSystemPages` (the admin facade that lets
 * a shop owner set a friendly alias per route, reusing the stock
 * `seo_url` table/`ModelDesignSeoUrl` — `CLAUDE.md` §6/§39, no second SEO
 * storage) and `ControllerStartupSeoUrl::rewrite()` (the core method that
 * turns `route=checkout/cart` back into `/cart` when generating a link —
 * see `docs/upstream-diff.md`'s `startup/seo_url.php` entry for why that
 * one small core change was necessary).
 */
final class SystemRoutes
{
    /**
     * @var array<string, string> route code => OpenCart `route=` value
     */
    public const ROUTES = [
        'search'       => 'product/search',
        'cart'         => 'checkout/cart',
        'checkout'     => 'checkout/checkout',
        'contact'      => 'information/contact',
        'account'      => 'account/account',
        'login'        => 'account/login',
        'register'     => 'account/register',
        'wishlist'     => 'account/wishlist',
        'compare'      => 'product/compare',
        'special'      => 'product/special',
        'manufacturer' => 'product/manufacturer',
        'sitemap'      => 'information/sitemap',
        'reviews'      => 'mintcart/reviews',
    ];

    /**
     * Language key for a route's admin-facing label, e.g.
     * `entry_system_route_cart`. The actual English text lives in
     * `admin/language/en-gb/mintcart/seo/system_pages.php`, not here —
     * this class has no OpenCart language service to call.
     */
    public static function labelKey(string $code): string
    {
        return 'entry_system_route_' . $code;
    }

    /**
     * A blank alias is always valid (it means "no custom alias for this
     * route" — the store keeps using `index.php?route=...` for it, same as
     * before this facade existed). A non-blank alias must be a plausible
     * URL path segment: lowercase letters/digits/hyphens/underscores only,
     * 1-100 characters. OpenCart's own `seo_url` table has no format
     * constraint on `keyword` beyond non-empty + unique — this is a
     * MintCart-side sanity check so an admin can't accidentally save a
     * `keyword` containing a space or slash that would silently never
     * match (`ControllerStartupSeoUrl::index()` splits the path on `/` and
     * compares each segment verbatim).
     */
    public static function isValidAlias(string $alias): bool
    {
        if ($alias === '') {
            return true;
        }

        return preg_match('/^[a-z0-9\-_]{1,100}$/', $alias) === 1;
    }
}
