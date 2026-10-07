# Product cards in lists — `mintcart/product_cards`

One controller turns OpenCart product rows into rendered `product-card`
components for every product list on a Mint-aware theme
(MINTCART_TECHNICAL_PLAN.md §15C/§15D, CLAUDE.md §8A). Callers never repeat
the image / price / special / tax / rating preparation.

```php
$data['product_cards'] = $this->load->controller('mintcart/product_cards', array(
    'results'      => $results,        // model_catalog_product rows (getProducts(), getProduct(), ...)
    'listing_type' => 'module_latest', // context.listing_type for the card
    'link_prefix'  => 'path=20&',     // optional, before product_id in product links
    'url'          => '&sort=p.price', // optional, appended to product links
    'image_width'  => 200,             // optional, theme image_product size by default
    'image_height' => 200,
    'diagnostics'  => $mint_diagnostics // optional MintCart\Core\Diagnostics
));
```

Returns `string[]` (card HTML) or `null` when the active theme has no
`theme.json` — the caller then keeps rendering its legacy `products` array,
so `default` and third-party themes are untouched.

Pipeline: row → `ProductViewProvider` (normalized contract) →
`ComponentRenderer` (`product-card.twig`, active theme → parent →
mint_default) via `ProductCardListRenderer`. A card that throws is logged
(`MintCart\Core\Logger`) and the remaining ones are skipped.

Strings: `catalog/language/<lang>/mintcart/product_card.php`
(+ `button_cart` / `button_compare` / `button_wishlist` from the global
language file).

## Consumers

| List | Controller | Template (mint_default) |
|---|---|---|
| Featured / Bestseller / Special / Latest modules | `extension/module/*.php` | `extension/module/*.twig` (`mc-product-list`) |
| Category | `product/category.php` (`link_prefix` = `path=…&`) | `product/category.twig` |
| Search | `product/search.php` | `product/search.twig` |
| Specials page | `product/special.php` | `product/special.twig` |
| Manufacturer | `product/manufacturer.php` (`link_prefix` = `manufacturer_id=…&`) | `product/manufacturer_info.twig` |
| Related products | `product/product.php` (related image size) | `product/product.twig` |
| Homepage Products block | `common/home.php` | `mint/blocks/products.twig` |

## `mc-product-list`

Titled card list whose column count follows its container width
(`repeat(auto-fill, minmax(--mc-product-list-min, 1fr))`), so a module
works in `content_*` and in narrow `column_*` positions alike. Title is a
styled label (`<p>`), not a heading (Guide §19.1).
