<?php

use MintCart\Core\Migration\MigrationInterface;

/**
 * Default friendly aliases for the system pages (cart, checkout, search ...,
 * `MINTCART_FUNCTIONAL_SCOPE.md` §4: "on a clean install MintCart must already
 * have adequate default aliases for system routes, if that is safe for
 * languages and compatibility").
 *
 * Stored exactly where the Marketing → SEO → System pages screen stores them:
 * `seo_url` rows with `query` = the route (e.g. `checkout/cart`), store 0, one
 * row per enabled language. They stay inert until the store turns on
 * Settings → Server → "Use SEO URLs" (which needs the web server rewrite rules),
 * so adding them changes nothing on a store that does not use SEO URLs.
 *
 * Safe to run on a store that already has some: a route that already has a row
 * for the language, or a keyword already taken in that language, is skipped.
 * Default words are English URL segments; a store in another language edits
 * them on the same screen.
 */
return new class implements MigrationInterface {
    /** @var array<string, string> route => default alias */
    private const ALIASES = [
        'product/search'       => 'search',
        'checkout/cart'        => 'cart',
        'checkout/checkout'    => 'checkout',
        'information/contact'  => 'contact',
        'account/account'      => 'account',
        'account/login'        => 'login',
        'account/register'     => 'register',
        'account/wishlist'     => 'wishlist',
        'product/compare'      => 'compare',
        'product/special'      => 'special',
        'product/manufacturer' => 'brands',
        'information/sitemap'  => 'sitemap',
    ];

    public function up(object $db): void
    {
        $prefix = DB_PREFIX;
        $languages = $db->query("SELECT `language_id` FROM `{$prefix}language`");

        foreach ($languages->rows as $language) {
            $languageId = (int) $language['language_id'];

            $existing = $db->query("SELECT `query`, `keyword` FROM `{$prefix}seo_url` WHERE `store_id` = '0' AND `language_id` = '{$languageId}'");
            $routes = [];
            $keywords = [];

            foreach ($existing->rows as $row) {
                $routes[$row['query']] = true;
                $keywords[$row['keyword']] = true;
            }

            foreach (self::ALIASES as $route => $alias) {
                if (isset($routes[$route]) || isset($keywords[$alias])) {
                    continue;
                }

                $db->query("INSERT INTO `{$prefix}seo_url` SET `store_id` = '0', `language_id` = '{$languageId}', `query` = '" . $db->escape($route) . "', `keyword` = '" . $db->escape($alias) . "'");
            }
        }
    }
};
