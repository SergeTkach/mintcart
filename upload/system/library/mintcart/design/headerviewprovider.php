<?php
namespace MintCart\Design;

/**
 * Normalizes header presentation data into the stable Header component
 * contract described in MINTCART_IMPLEMENTATION_GUIDE.md §48
 * ("Header, Topbar, Logo, Search, Account, Cart, Navigation, Mobile
 * Navigation") and MINTCART_TECHNICAL_PLAN.md §14.
 *
 * Mirrors MintCart\Design\FooterViewProvider's role for footer (and
 * MintCart\Commerce\ProductViewProvider's for product-card): the single
 * place the `header` shape every Mint theme's `header.twig` override
 * renders against gets assembled.
 *
 * Deliberately has no OpenCart dependency, same rationale as
 * FooterViewProvider/ProductViewProvider - given already-resolved values
 * (OpenCart's own `config_name`/`config_logo`/`config_telephone` settings,
 * never duplicated into a Mint-owned table per CLAUDE.md §39), this class
 * only shapes what the caller already has.
 *
 * Navigation, Search, Cart and the language/currency switchers stay as
 * pre-rendered HTML fragments in `widgets` rather than being rebuilt into
 * their own normalized contracts here. Each already has its own real Mint
 * API planned for later (AJAX Search - MINTCART_TECHNICAL_PLAN.md §26,
 * Cart client/`mc:cart:updated` - §25, both Phase 15/Commerce API), and
 * MintCart's own category-menu-walking Navigation component has no second
 * real consumer yet either (CLAUDE.md §85). Until then this is the
 * documented compatibility pattern for embedding OpenCart-native widgets
 * inside a Mint-owned layout shell (MINTCART_IMPLEMENTATION_GUIDE.md §36 -
 * "wrap in mc-legacy, never break IDs/classes/data-* the widget's own JS
 * needs"). The `header` component contract still gives a theme full control
 * over the surrounding DOM (topbar layout, logo placement, account menu
 * markup) without any OCMOD, even while these five widgets remain legacy
 * fragments inside it.
 */
final class HeaderViewProvider
{
    private const LINK_KEYS = [
        'account', 'login', 'register', 'order', 'transaction', 'download',
        'logout', 'wishlist', 'compare', 'shopping_cart', 'checkout', 'contact',
    ];

    private const WIDGET_KEYS = ['menu', 'search', 'cart', 'language', 'currency'];

    /**
     * @param array{name?: string, logo?: ?string, home?: string} $branding
     *        Store name/logo/home URL - OpenCart's existing
     *        `config_name`/`config_logo` settings plus the resolved
     *        `common/home` link, already computed by the caller.
     * @param array{logged?: bool, first_name?: ?string, wishlist_count?: ?int} $account
     *        Current visitor's login/wishlist state
     *        (`$this->customer->isLogged()`/`getFirstName()` and the
     *        session/DB wishlist count OpenCart's header controller already
     *        resolves today).
     * @param array{phone?: ?string} $contacts
     *        Store contact details. Sourced from OpenCart's own
     *        `config_telephone` setting - never re-entered into a
     *        Mint-owned table (mirrors FooterViewProvider's `$contacts`).
     * @param array<string, ?string> $links
     *        Named navigation URLs. Only keys in self::LINK_KEYS are kept;
     *        an unrecognized key is dropped rather than silently exposed
     *        to a theme as an undocumented contract field. Every documented
     *        key is always present in the result, `null` when not supplied,
     *        so a theme's `header.twig` never has to guard against a
     *        missing array key.
     * @param array<string, ?string> $widgets
     *        Pre-rendered HTML fragments from OpenCart's own
     *        `common/menu`, `common/search`, `common/cart`,
     *        `common/language`, `common/currency` sub-controllers (each
     *        already a complete, self-contained widget with its own
     *        markup/JS hooks - see class docblock). Only keys in
     *        self::WIDGET_KEYS are kept; trusted pre-rendered HTML, not
     *        escaped or otherwise touched here (same trust boundary as
     *        FooterViewProvider's `$powered` string).
     *
     * @param array{text?: ?string, url?: ?string} $announcement
     *        Optional top info bar (Business → Contacts): a short plain-text
     *        message and an optional link. Empty text means "no bar" and the
     *        whole key is `null`; a url that is not http(s) or a
     *        site-relative path is dropped so a theme can put it straight
     *        into an `href`.
     *
     * @return array{
     *     branding: array{name: string, logo: ?string, home: string},
     *     account: array{logged: bool, first_name: ?string, wishlist_count: ?int},
     *     contacts: array{phone: ?string},
     *     links: array<string, ?string>,
     *     widgets: array<string, ?string>,
     *     announcement: ?array{text: string, url: ?string}
     * }
     */
    public function build(
        array $branding = [],
        array $account = [],
        array $contacts = [],
        array $links = [],
        array $widgets = [],
        array $announcement = []
    ): array {
        return [
            'branding' => [
                'name' => (string) ($branding['name'] ?? ''),
                'logo' => $branding['logo'] ?? null,
                'home' => (string) ($branding['home'] ?? ''),
            ],
            'account' => [
                'logged' => (bool) ($account['logged'] ?? false),
                'first_name' => $account['first_name'] ?? null,
                'wishlist_count' => isset($account['wishlist_count']) ? (int) $account['wishlist_count'] : null,
            ],
            'contacts' => [
                'phone' => $contacts['phone'] ?? null,
            ],
            'links' => $this->buildKeyed($links, self::LINK_KEYS),
            'widgets' => $this->buildKeyed($widgets, self::WIDGET_KEYS),
            'announcement' => $this->buildAnnouncement($announcement),
        ];
    }

    /**
     * @param array{text?: ?string, url?: ?string} $announcement
     * @return ?array{text: string, url: ?string}
     */
    private function buildAnnouncement(array $announcement): ?array
    {
        $text = trim((string) ($announcement['text'] ?? ''));

        if ($text === '') {
            return null;
        }

        $url = trim((string) ($announcement['url'] ?? ''));

        if ($url !== '' && !preg_match('#^(https?://|/(?!/))#i', $url)) {
            $url = '';
        }

        return ['text' => $text, 'url' => $url !== '' ? $url : null];
    }

    /**
     * @param array<string, ?string> $values
     * @param list<string> $allowedKeys
     * @return array<string, ?string>
     */
    private function buildKeyed(array $values, array $allowedKeys): array
    {
        $result = [];

        foreach ($allowedKeys as $key) {
            $value = $values[$key] ?? null;
            $result[$key] = $value !== null && $value !== '' ? (string) $value : null;
        }

        return $result;
    }
}
