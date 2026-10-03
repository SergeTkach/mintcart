<?php
namespace MintCart\Admin;

/**
 * Admin "quick settings search" catalog (`MINTCART_FUNCTIONAL_SCOPE.md`
 * §23 — "Швидкий пошук налаштувань", P1): lets a merchant find
 * `currency`/`logo`/`delivery`/`SEO URL`/`email`/`tax`/`checkout`
 * (that section's own example list) without knowing which admin menu
 * group (`Store`/`Design`/`Marketing`/`Business`/`Settings`/`Advanced`)
 * they live under.
 *
 * MVP scope, per that section's own wording: "Пошук повинен вести не
 * просто на сторінку, а за можливості на конкретну секцію/field" — "за
 * можливості" (if possible) marks per-field targeting as a stretch goal,
 * not a requirement. This class only ever resolves to a whole admin page
 * (a `route` string OpenCart's own `$this->url->link()` already resolves
 * everywhere else in this codebase) — see
 * `docs/developer/admin/settings-search.md` for the field-level follow-up
 * this leaves open.
 *
 * Deliberately has no OpenCart dependency (no `\Registry`, no `\DB`,
 * nothing from `$this->user`/`$this->url`) so it is unit-testable in
 * isolation, same reasoning as `MintCart\Catalog\BulkProductUpdateValidator`
 * — this class only ever deals in plain strings in, plain
 * `{label, route}` arrays out. Resolving `route` into an actual URL (which
 * needs a live `user_token`) is the caller's job —
 * `ControllerMintcartAdminsearch::index()`.
 *
 * Every `route` below is a real, already-reachable admin route this
 * repository's own `admin/controller/common/column_left.php` menu links
 * to today (grepped before writing this list — `CLAUDE.md` §84, never
 * assumed from memory) — this class does not invent any new screen.
 */
final class SettingsSearchIndex
{
    /**
     * @var list<array{label:string, route:string, keywords:list<string>}>
     */
    private const ENTRIES = [
        ['label' => 'Store Settings', 'route' => 'setting/store', 'keywords' => ['store name', 'logo', 'email', 'telephone', 'phone', 'address', 'store', 'favicon', 'meta']],
        ['label' => 'Currency', 'route' => 'localisation/currency', 'keywords' => ['currency', 'exchange rate', 'money']],
        ['label' => 'Language', 'route' => 'localisation/language', 'keywords' => ['language', 'locale']],
        ['label' => 'Tax Classes', 'route' => 'localisation/tax_class', 'keywords' => ['tax', 'vat']],
        ['label' => 'Tax Rates', 'route' => 'localisation/tax_rate', 'keywords' => ['tax rate', 'vat rate']],
        ['label' => 'Order Statuses', 'route' => 'localisation/order_status', 'keywords' => ['checkout', 'order status']],
        ['label' => 'Stock Statuses', 'route' => 'localisation/stock_status', 'keywords' => ['stock', 'inventory status']],
        ['label' => 'Countries', 'route' => 'localisation/country', 'keywords' => ['country', 'region']],
        ['label' => 'Zones', 'route' => 'localisation/zone', 'keywords' => ['zone', 'state', 'province']],
        ['label' => 'Geo Zones', 'route' => 'localisation/geo_zone', 'keywords' => ['geo zone', 'shipping zone', 'tax zone']],
        ['label' => 'SEO URLs', 'route' => 'design/seo_url', 'keywords' => ['seo', 'url', 'alias', 'slug', 'pretty url', 'keyword']],
        ['label' => 'System Pages SEO', 'route' => 'mintcart/seo/system_pages', 'keywords' => ['seo', 'cart url', 'checkout url', 'system pages', 'wishlist url']],
        ['label' => 'Business → Contacts', 'route' => 'mintcart/business/contacts', 'keywords' => ['contacts', 'phone', 'email', 'address', 'social media']],
        ['label' => 'Business → Delivery', 'route' => 'mintcart/business/delivery', 'keywords' => ['delivery', 'shipping']],
        ['label' => 'Business → Payments', 'route' => 'mintcart/business/payments', 'keywords' => ['payment', 'payments', 'gateway']],
        ['label' => 'Design → Homepage', 'route' => 'design/homepage', 'keywords' => ['homepage', 'blocks', 'builder']],
        ['label' => 'Design → Layouts', 'route' => 'design/layout', 'keywords' => ['layout', 'positions']],
        ['label' => 'Design → Themes', 'route' => 'design/theme', 'keywords' => ['theme', 'design']],
        ['label' => 'Design → Banners', 'route' => 'design/banner', 'keywords' => ['banner', 'slider']],
        ['label' => 'Diagnostics', 'route' => 'mintcart/settings/diagnostics', 'keywords' => ['diagnostics', 'debug', 'dev mode', 'query count']],
        ['label' => 'Products', 'route' => 'catalog/product', 'keywords' => ['product', 'catalog']],
        ['label' => 'Categories', 'route' => 'catalog/category', 'keywords' => ['category']],
        ['label' => 'Manufacturers', 'route' => 'catalog/manufacturer', 'keywords' => ['manufacturer', 'brand']],
        ['label' => 'Orders', 'route' => 'sale/order', 'keywords' => ['order', 'sale']],
        ['label' => 'Customers', 'route' => 'customer/customer', 'keywords' => ['customer']],
        ['label' => 'Coupons', 'route' => 'marketing/coupon', 'keywords' => ['coupon', 'discount code', 'promotion']],
        ['label' => 'Marketing', 'route' => 'marketing/marketing', 'keywords' => ['marketing', 'affiliate', 'tracking']],
        ['label' => 'Extensions', 'route' => 'marketplace/extension', 'keywords' => ['extension', 'module', 'shipping method', 'payment method']],
        ['label' => 'Users', 'route' => 'user/user', 'keywords' => ['user', 'admin account']],
        ['label' => 'Backup / Restore', 'route' => 'tool/backup', 'keywords' => ['backup', 'restore', 'database']],
        ['label' => 'Error Log', 'route' => 'tool/log', 'keywords' => ['log', 'error log']],
    ];

    /**
     * @return list<array{label:string, route:string}> Ranked, highest
     *         score first; empty for a blank query or no match.
     */
    public function search(string $query, int $limit = 8): array
    {
        $query = trim(mb_strtolower($query));

        if ($query === '' || $limit < 1) {
            return [];
        }

        $scored = [];

        foreach (self::ENTRIES as $entry) {
            $score = $this->score($query, $entry);

            if ($score > 0) {
                $scored[] = ['label' => $entry['label'], 'route' => $entry['route'], 'score' => $score];
            }
        }

        usort($scored, static function (array $a, array $b): int {
            return $b['score'] <=> $a['score'];
        });

        $scored = array_slice($scored, 0, $limit);

        return array_map(static function (array $entry): array {
            return ['label' => $entry['label'], 'route' => $entry['route']];
        }, $scored);
    }

    /**
     * Exposed so a future caller (and this class's own test) can list the
     * full catalog without reaching into the private `ENTRIES` constant.
     *
     * @return list<array{label:string, route:string, keywords:list<string>}>
     */
    public function all(): array
    {
        return self::ENTRIES;
    }

    /**
     * @param array{label:string, route:string, keywords:list<string>} $entry
     */
    private function score(string $query, array $entry): int
    {
        $label = mb_strtolower($entry['label']);

        if ($label === $query) {
            return 100;
        }

        if (strpos($label, $query) === 0) {
            return 80;
        }

        if (strpos($label, $query) !== false) {
            return 60;
        }

        $bestKeywordScore = 0;

        foreach ($entry['keywords'] as $keyword) {
            $keyword = mb_strtolower($keyword);

            if ($keyword === $query) {
                $bestKeywordScore = max($bestKeywordScore, 90);
            } elseif (strpos($keyword, $query) === 0) {
                $bestKeywordScore = max($bestKeywordScore, 70);
            } elseif (strpos($keyword, $query) !== false) {
                $bestKeywordScore = max($bestKeywordScore, 40);
            }
        }

        return $bestKeywordScore;
    }
}
