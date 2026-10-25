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
     * @var list<array{label:string, route:string, keywords:list<string>, field?:string}>
     */
    private const ENTRIES = [
        ['label' => 'Store Settings', 'route' => 'setting/store', 'keywords' => ['store name', 'logo', 'email', 'telephone', 'phone', 'address', 'store', 'favicon', 'meta', 'налаштування магазину', 'назва магазину', 'логотип', 'лого', 'телефон', 'адреса', 'пошта', 'іконка', 'настройки магазина', 'название магазина', 'адрес', 'почта']],
        // Field-level entries: the result opens the page and jumps to this input (`field` is its
        // element id; the tab that holds it is opened by admin-search.js, `initFieldJump()`).
        ['label' => 'Store name', 'route' => 'setting/setting', 'field' => 'input-name', 'keywords' => ['store name', 'shop name', 'name', 'назва магазину', 'назва', 'название магазина', 'название']],
        ['label' => 'Store email', 'route' => 'setting/setting', 'field' => 'input-email', 'keywords' => ['email', 'e-mail', 'store email', 'пошта', 'електронна пошта', 'email магазину', 'почта']],
        ['label' => 'Store telephone', 'route' => 'setting/setting', 'field' => 'input-telephone', 'keywords' => ['telephone', 'phone', 'phone number', 'телефон', 'номер телефону', 'номер телефона']],
        ['label' => 'Store address', 'route' => 'setting/setting', 'field' => 'input-address', 'keywords' => ['address', 'store address', 'адреса', 'адреса магазину', 'адрес']],
        ['label' => 'Store logo', 'route' => 'setting/setting', 'field' => 'thumb-logo', 'keywords' => ['logo', 'store logo', 'логотип', 'лого', 'логотип магазину']],
        ['label' => 'Favicon', 'route' => 'setting/setting', 'field' => 'thumb-icon', 'keywords' => ['favicon', 'icon', 'tab icon', 'фавікон', 'іконка', 'фавикон', 'иконка']],
        ['label' => 'Default currency', 'route' => 'setting/setting', 'field' => 'input-currency', 'keywords' => ['default currency', 'main currency', 'основна валюта', 'валюта за замовчуванням', 'основная валюта']],
        ['label' => 'Default language', 'route' => 'setting/setting', 'field' => 'input-language', 'keywords' => ['default language', 'основна мова', 'мова за замовчуванням', 'основной язык']],
        ['label' => 'Meta tags (templates)', 'route' => 'mintcart/seo/meta', 'keywords' => ['meta tags', 'meta title', 'meta description', 'title template', 'seo', 'мета-теги', 'мета теги', 'шаблон заголовка', 'шаблон мета', 'сео', 'шаблон заголовка']],
        ['label' => 'Meta title', 'route' => 'setting/setting', 'field' => 'input-meta-title', 'keywords' => ['meta title', 'title', 'seo title', 'мета заголовок', 'заголовок', 'тайтл']],
        ['label' => 'Currency', 'route' => 'localisation/currency', 'keywords' => ['currency', 'exchange rate', 'money', 'валюта', 'валюти', 'курс', 'гроші', 'валюты', 'деньги']],
        ['label' => 'Language', 'route' => 'localisation/language', 'keywords' => ['language', 'locale', 'мова', 'мови', 'язык', 'языки', 'локаль']],
        ['label' => 'Tax Classes', 'route' => 'localisation/tax_class', 'keywords' => ['tax', 'vat', 'податок', 'податки', 'пдв', 'налог', 'налоги', 'ндс']],
        ['label' => 'Tax Rates', 'route' => 'localisation/tax_rate', 'keywords' => ['tax rate', 'vat rate', 'ставка податку', 'ставка пдв', 'ставка налога']],
        ['label' => 'Order Statuses', 'route' => 'localisation/order_status', 'keywords' => ['checkout', 'order status', 'статус замовлення', 'статуси замовлень', 'статус заказа']],
        ['label' => 'Stock Statuses', 'route' => 'localisation/stock_status', 'keywords' => ['stock', 'inventory status', 'наявність', 'залишок', 'склад', 'статус наявності', 'наличие', 'остаток']],
        ['label' => 'Countries', 'route' => 'localisation/country', 'keywords' => ['country', 'region', 'країна', 'країни', 'страна', 'страны']],
        ['label' => 'Zones', 'route' => 'localisation/zone', 'keywords' => ['zone', 'state', 'province', 'область', 'регіон', 'штат', 'регион']],
        ['label' => 'Geo Zones', 'route' => 'localisation/geo_zone', 'keywords' => ['geo zone', 'shipping zone', 'tax zone', 'геозона', 'зона доставки', 'податкова зона', 'геозоны']],
        ['label' => 'SEO URLs', 'route' => 'design/seo_url', 'keywords' => ['seo', 'url', 'alias', 'slug', 'pretty url', 'keyword', 'чпу', 'сео', 'seo урл', 'посилання', 'адреса сторінки', 'ссылка']],
        ['label' => 'System Pages SEO', 'route' => 'mintcart/seo/system_pages', 'keywords' => ['seo', 'cart url', 'checkout url', 'system pages', 'wishlist url', 'чпу системних сторінок', 'сео', 'адреса кошика', 'адреса оформлення', 'системные страницы']],
        ['label' => 'Business → Contacts', 'route' => 'mintcart/business/contacts', 'keywords' => ['contacts', 'phone', 'email', 'address', 'social media', 'контакти', 'телефон', 'пошта', 'адреса', 'соцмережі', 'соціальні мережі', 'контакты', 'соцсети']],
        ['label' => 'Business → Delivery', 'route' => 'mintcart/business/delivery', 'keywords' => ['delivery', 'shipping', 'доставка', 'відправка', 'нова пошта', 'способи доставки', 'отправка']],
        ['label' => 'Business → Payments', 'route' => 'mintcart/business/payments', 'keywords' => ['payment', 'payments', 'gateway', 'оплата', 'платежі', 'способи оплати', 'платежи', 'способы оплаты']],
        ['label' => 'Design → Homepage', 'route' => 'design/homepage', 'keywords' => ['homepage', 'blocks', 'builder', 'головна', 'головна сторінка', 'блоки', 'конструктор', 'главная']],
        ['label' => 'Design → Layouts', 'route' => 'design/layout', 'keywords' => ['layout', 'positions', 'схеми', 'макети', 'позиції', 'макеты', 'позиции']],
        ['label' => 'Design → Themes', 'route' => 'design/theme', 'keywords' => ['theme', 'design', 'тема', 'дизайн', 'шаблон']],
        ['label' => 'Design → Banners', 'route' => 'design/banner', 'keywords' => ['banner', 'slider', 'банер', 'банери', 'слайдер', 'баннер', 'баннеры']],
        ['label' => 'Diagnostics', 'route' => 'mintcart/settings/diagnostics', 'keywords' => ['diagnostics', 'debug', 'dev mode', 'query count', 'діагностика', 'налагодження', 'отладка']],
        ['label' => 'Products', 'route' => 'catalog/product', 'keywords' => ['product', 'catalog', 'товар', 'товари', 'каталог', 'продукт', 'товары']],
        ['label' => 'Categories', 'route' => 'catalog/category', 'keywords' => ['category', 'категорія', 'категорії', 'розділ', 'категория', 'категории']],
        ['label' => 'Manufacturers', 'route' => 'catalog/manufacturer', 'keywords' => ['manufacturer', 'brand', 'виробник', 'бренд', 'бренди', 'производитель']],
        ['label' => 'Orders', 'route' => 'sale/order', 'keywords' => ['order', 'sale', 'замовлення', 'продажі', 'заказ', 'заказы']],
        ['label' => 'Customers', 'route' => 'customer/customer', 'keywords' => ['customer', 'клієнт', 'клієнти', 'покупець', 'покупці', 'клиент', 'покупатель']],
        ['label' => 'Coupons', 'route' => 'marketing/coupon', 'keywords' => ['coupon', 'discount code', 'promotion', 'купон', 'промокод', 'знижка', 'купоны', 'скидка']],
        ['label' => 'Marketing', 'route' => 'marketing/marketing', 'keywords' => ['marketing', 'affiliate', 'tracking', 'маркетинг', 'партнери', 'відстеження']],
        ['label' => 'Extensions', 'route' => 'marketplace/extension', 'keywords' => ['extension', 'module', 'shipping method', 'payment method', 'розширення', 'модуль', 'модулі', 'доповнення', 'расширения', 'модули']],
        ['label' => 'Users', 'route' => 'user/user', 'keywords' => ['user', 'admin account', 'користувач', 'користувачі', 'адміністратор', 'пользователь']],
        ['label' => 'Backup / Restore', 'route' => 'tool/backup', 'keywords' => ['backup', 'restore', 'database', 'резервна копія', 'бекап', 'відновлення', 'база даних', 'резервная копия']],
        ['label' => 'Error Log', 'route' => 'tool/log', 'keywords' => ['log', 'error log', 'журнал', 'лог', 'помилки', 'журнал помилок', 'ошибки']],
    ];

    /**
     * @return list<array{label:string, route:string, field:string}> Ranked, highest
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
                // A field-level entry beats a page-level one that matches equally well.
                if (!empty($entry['field'])) {
                    $score++;
                }

                $scored[] = ['label' => $entry['label'], 'route' => $entry['route'], 'field' => $entry['field'] ?? '', 'score' => $score];
            }
        }

        usort($scored, static function (array $a, array $b): int {
            return $b['score'] <=> $a['score'];
        });

        $scored = array_slice($scored, 0, $limit);

        return array_map(static function (array $entry): array {
            return ['label' => $entry['label'], 'route' => $entry['route'], 'field' => $entry['field']];
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

        // The label match and the best keyword match compete: an exact keyword ("logo") must not lose to a
        // weaker label hit ("Store logo" contains it).
        $labelScore = 0;

        if (strpos($label, $query) === 0) {
            $labelScore = 80;
        } elseif (strpos($label, $query) !== false) {
            $labelScore = 60;
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

        return max($labelScore, $bestKeywordScore);
    }
}
