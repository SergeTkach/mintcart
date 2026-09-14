<?php
namespace MintCart\Seo;

/**
 * Decides the per-page `<meta name="robots" content="...">` directive
 * (`MINTCART_FUNCTIONAL_SCOPE.md` §16 "robots controls" under SEO Core,
 * `MINTCART_TECHNICAL_PLAN.md` §31 "Metadata"). The one Phase 18 SEO Core
 * sub-item still unclaimed after canonical (already stock OpenCart),
 * hreflang, XML sitemap and system-route-aliases each shipped as their own
 * increment — see `docs/IMPLEMENTATION_STATUS.md`'s Phase 18 section.
 *
 * No robots signal of any kind exists anywhere in this repository before
 * this class, other than `information/information.php`'s own narrow
 * `X-Robots-Tag: noindex` HTTP header for a handful of "bottom position"
 * information pages — see "Relationship to information.php's
 * X-Robots-Tag" below for why this class deliberately doesn't touch or
 * duplicate that.
 *
 * Deliberately has **no OpenCart dependency** — no `\Registry`, `\DB`,
 * `\Document` — same "resolved values in, normalized contract out" shape
 * as `MintCart\Seo\HreflangBuilder`/`SchemaOrgBuilder` and
 * `MintCart\Commerce\SearchService`. A caller resolves the current route
 * (and, for search, whether it actually matched anything) upstream; this
 * class only decides the index/follow directive and formats it.
 */
final class RobotsBuilder
{
    /**
     * Utility/account/checkout routes that carry no unique public content a
     * search engine should ever list — every visitor's cart, checkout flow
     * and account pages are functionally identical (or behind a login
     * wall), so indexing them only creates duplicate-content/thin-content
     * noise and, for checkout in particular, could leak an in-progress
     * order's URL into search results. Every value below was checked
     * against the real controller in this repo (`CLAUDE.md` §84 — never
     * assumed from memory): `checkout/cart.php`, `checkout/checkout.php`,
     * `checkout/success.php`, `checkout/failure.php`, `checkout/guest.php`,
     * `checkout/register.php`, `checkout/confirm.php`, `account/account.php`,
     * `account/login.php`, `account/register.php`, `account/forgotten.php`,
     * `account/logout.php`, `account/success.php`, `account/edit.php`,
     * `account/password.php`, `account/address.php`, `account/wishlist.php`,
     * `account/order.php`, `account/return.php`, `account/download.php`,
     * `account/reward.php`, `account/transaction.php`, `account/voucher.php`,
     * `account/newsletter.php`, `account/recurring.php`,
     * `account/affiliate.php`, `account/tracking.php`.
     *
     * Not necessarily exhaustive of every sub-action each controller
     * exposes (e.g. `account/address.add`/`.edit` share their parent's
     * `account/address` entry below) — this is the set of *primary*,
     * directly-linked routes a crawler would actually reach, not every
     * possible query-string permutation.
     */
    public const NOINDEX_ROUTES = [
        'checkout/cart',
        'checkout/checkout',
        'checkout/success',
        'checkout/failure',
        'checkout/guest',
        'checkout/register',
        'checkout/confirm',
        'account/account',
        'account/login',
        'account/register',
        'account/forgotten',
        'account/logout',
        'account/success',
        'account/edit',
        'account/password',
        'account/address',
        'account/address/add',
        'account/address/edit',
        'account/wishlist',
        'account/order',
        'account/order/info',
        'account/return',
        'account/return/add',
        'account/return/info',
        'account/download',
        'account/reward',
        'account/transaction',
        'account/voucher',
        'account/newsletter',
        'account/recurring',
        'account/recurring/info',
        'account/affiliate',
        'account/tracking',
    ];

    /**
     * @param string $route An OpenCart `route=...` value, e.g. `checkout/cart`.
     * @param bool $hasResults For `product/search` only: whether the query
     *        actually matched any product. Ignored for every other route.
     *        An empty search-results page is thin, duplicate-shaped content
     *        (the same "no results" markup for every failed query) and
     *        should not be indexed — but its own links (e.g. "browse
     *        categories instead") are still worth a crawler following, so
     *        this stays `noindex, follow`, never `nofollow`.
     *
     * @return array{index: bool, follow: bool}
     */
    public function directives(string $route, bool $hasResults = true): array
    {
        if (in_array($route, self::NOINDEX_ROUTES, true)) {
            return ['index' => false, 'follow' => true];
        }

        if ($route === 'product/search' && !$hasResults) {
            return ['index' => false, 'follow' => true];
        }

        return ['index' => true, 'follow' => true];
    }

    /**
     * The exact string a `<meta name="robots" content="...">` tag's
     * `content` attribute should carry, e.g. `noindex, follow`.
     */
    public function metaContent(string $route, bool $hasResults = true): string
    {
        $directives = $this->directives($route, $hasResults);

        return ($directives['index'] ? 'index' : 'noindex') . ', ' . ($directives['follow'] ? 'follow' : 'nofollow');
    }
}
