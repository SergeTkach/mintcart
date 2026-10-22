<?php
namespace MintCart\Commerce;

/**
 * Normalizes an OpenCart product row (plus the presentation values every
 * listing controller already resolves — formatted price/special/tax,
 * resized image URLs, stock label text) into the stable ProductView
 * contract described in MINTCART_IMPLEMENTATION_GUIDE.md §27 and
 * MINTCART_TECHNICAL_PLAN.md §15D.
 *
 * This is the single place `product` / `display` / `context` shape is
 * assembled. Category, Search, Manufacturer, Special and Homepage Blocks
 * controllers must all call this instead of each building their own
 * `$data['products'][] = [...]` array (MINTCART_TECHNICAL_PLAN.md §15C/§15D).
 *
 * Deliberately has no OpenCart dependency (no \Registry, no \DB, no
 * currency/tax/url services) so it is unit-testable in isolation — currency
 * formatting, tax calculation and SEO URL building stay OpenCart's job
 * upstream of this class, exactly as they are today.
 */
final class ProductViewProvider
{
    /**
     * @param array<string, mixed> $product Raw product + already-resolved
     *        presentation values. Required: id, name, url, image.
     * @param array<string, mixed> $display Display flags for this render.
     * @param array<string, mixed> $context Listing context (which page/block
     *        requested this product, its position within the list).
     *
     * @return array{product: array<string, mixed>, display: array<string, mixed>, context: array<string, mixed>}
     */
    public function build(array $product, array $display = [], array $context = []): array
    {
        foreach (['id', 'name', 'url', 'image'] as $required) {
            if (!array_key_exists($required, $product) || $product[$required] === null || $product[$required] === '') {
                throw new \InvalidArgumentException("ProductViewProvider::build() requires a non-empty '{$required}' key in \$product.");
            }
        }

        $quantity = array_key_exists('quantity', $product) ? $product['quantity'] : null;
        $inStock = $quantity === null ? true : ((int) $quantity > 0);

        $badges = $this->buildBadges($product, $inStock);

        return [
            'product' => [
                'id' => $product['id'],
                'name' => $product['name'],
                'url' => $product['url'],
                'image' => $product['image'],
                'secondary_image' => $product['secondary_image'] ?? null,
                'image_alt' => $product['image_alt'] ?? $product['name'],
                // Explicit pixel dimensions for the rendered <img>, so the
                // template can set width/height and avoid a layout shift
                // while the image loads (MINTCART_FUNCTIONAL_SCOPE.md §19
                // Performance). Every controller already resizes to a
                // fixed theme-configured size before this point (the same
                // dimensions OpenCart's own model_tool_image->resize() was
                // just called with) — this only threads that already-known
                // value through, it never re-derives it.
                'image_width' => $product['image_width'] ?? null,
                'image_height' => $product['image_height'] ?? null,
                // A "1x, 2x" srcset string, same "already built upstream,
                // just threaded through" reasoning as image_width/height
                // above — every listing controller (Category, Search,
                // Manufacturer, Special) already calls
                // model_tool_image->resize() a second time at double the
                // configured width/height and builds this exact string
                // (MINTCART_FUNCTIONAL_SCOPE.md §19 "responsive images",
                // see docs/developer/commerce/product-view-provider.md
                // "Image dimensions"). Defaults to null for a caller that
                // doesn't supply it (a hand-built test fixture, or a future
                // consumer with no 2x variant) — the template guards on
                // this and omits the srcset attribute entirely.
                'image_srcset' => $product['image_srcset'] ?? null,
                'price' => $product['price'] ?? false,
                'special' => $product['special'] ?? false,
                'tax' => $product['tax'] ?? false,
                'rating' => $product['rating'] ?? false,
                'stock' => [
                    'in_stock' => $inStock,
                    'label' => $product['stock_label'] ?? null,
                ],
                'badges' => $badges,
                'minimum' => isset($product['minimum']) && (int) $product['minimum'] > 0 ? (int) $product['minimum'] : 1,
                'manufacturer' => $product['manufacturer'] ?? null,
                // Reserved for the future Product Page listing context
                // (MINTCART_TECHNICAL_PLAN.md §15D). Category/Search/Manufacturer
                // results don't carry option data, so there is no real consumer
                // for this yet — kept in the contract so a theme can rely on the
                // key existing without a schema change later (CLAUDE.md §85: the
                // key is documented because Technical Plan already specifies it,
                // but no logic is invented to populate it ahead of that consumer).
                'options_summary' => $product['options_summary'] ?? null,
                'actions' => $this->buildActions($product, $inStock),
            ],
            'display' => [
                'show_rating' => $display['show_rating'] ?? true,
                'show_description' => $display['show_description'] ?? false,
                'show_secondary_image' => $display['show_secondary_image'] ?? true,
                'action_variant' => $display['action_variant'] ?? 'default',
            ],
            'context' => [
                'listing_type' => $context['listing_type'] ?? 'category',
                'position' => $context['position'] ?? null,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $product
     * @return list<array{type: string, label: ?string, color?: string, on_color?: string}>
     */
    private function buildBadges(array $product, bool $inStock): array
    {
        $badges = [];

        if (!empty($product['special'])) {
            $badges[] = ['type' => 'sale', 'label' => null];
        }

        if (!$inStock) {
            $badges[] = ['type' => 'out-of-stock', 'label' => $product['stock_label'] ?? null];
        }

        // "New" depends on a store-configurable recency threshold that
        // doesn't exist yet (Business Settings, MINTCART_FUNCTIONAL_SCOPE.md
        // §3/Phase 14) — the caller decides and passes the flag explicitly
        // rather than this class guessing a threshold from date_added.
        if (!empty($product['is_new'])) {
            $badges[] = ['type' => 'new', 'label' => null];
        }

        // Manual stickers set in Marketing → Product Badges, shown after
        // the automatic ones. Rows are mc_product_badge rows (label, color).
        foreach ($product['manual_badges'] ?? [] as $row) {
            if (is_array($row) && trim((string) ($row['label'] ?? '')) !== '') {
                $badges[] = ProductBadge::toView($row);
            }
        }

        return $badges;
    }

    /**
     * @param array<string, mixed> $product Recognizes an optional
     *        `allow_out_of_stock_purchase` bool - the caller's resolved
     *        value of OpenCart's `config_stock_checkout` setting (backorder
     *        support, the same flag `catalog/controller/checkout/cart.php`
     *        already checks before blocking an out-of-stock add). Without
     *        it, an out-of-stock product would always disable add-to-cart/
     *        quick-order here even on a store that has backorders enabled.
     * @return array{add_to_cart: bool, quick_order: bool, wishlist: bool, compare: bool}
     */
    private function buildActions(array $product, bool $inStock): array
    {
        $canPurchaseOutOfStock = $inStock || !empty($product['allow_out_of_stock_purchase']);
        $purchasable = $canPurchaseOutOfStock && ($product['price'] ?? false) !== false;

        return [
            'add_to_cart' => $purchasable,
            'quick_order' => $purchasable,
            'wishlist' => true,
            'compare' => true,
        ];
    }
}
