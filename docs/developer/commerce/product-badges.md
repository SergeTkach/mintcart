# Manual product badges ("stickers")

Short labels the store owner puts on chosen products: "Bestseller", "-20%",
"Eco". A product that carries a badge shows it everywhere the product appears:
on its card in every list (category, search, special, manufacturer, home
blocks, modules) and on the product page. They come after the automatic badges
(Sale, Out of stock, New). Spec: `MINTCART_FUNCTIONAL_SCOPE.md` §11
"configurable badges/stickers".

## How the owner uses them

1. Admin → Marketing → **Product Badges** (`route=mintcart/badge`): create the
   set of badges.
   - Badge text: 1–32 characters, one text for all languages.
   - Colour: optional `#rrggbb`; empty means the theme's neutral `mc-badge`
     look. The text colour (dark or white) is picked for contrast.
   - Sort order: the order badges appear in.
   - The list shows how many products use each badge. Deleting a badge removes
     it from all products.
2. Open a product → **Links** tab → **Badges**: tick the badges this product
   should carry. A product may have several. Unticking all removes them.
   Copying a product copies its badges; deleting a product removes them.

## Storage

Two tables (migrations `202610221200_create_mc_product_badge.php`,
`202610221202_product_badge_definitions.php`; InnoDB, utf8mb4, no foreign keys
like the rest of OpenCart's schema):

- `mc_badge` — `badge_id`, `label`, `color`, `sort_order`, `date_added`
- `mc_product_badge` — `product_id`, `badge_id` (primary key on both)

The second migration converts the first version of the table (one label/colour
row per product) into this shape, so nothing is lost.

## Code

| Part | File |
|---|---|
| Validation and colour logic | `upload/system/library/mintcart/commerce/productbadge.php` (`MintCart\Commerce\ProductBadge`) |
| Admin: the set | `upload/admin/controller/mintcart/badge.php`, `admin/model/mintcart/badge.php`, `admin/view/template/mintcart/badge.twig`, `admin/language/en-gb/mintcart/badge.php` |
| Admin: product form | Links tab row in `product_form.twig`; save/copy/delete in `admin/model/catalog/product.php`; data in `admin/controller/catalog/product.php` (see `docs/upstream-diff.md`) |
| Storefront read | `upload/catalog/model/mintcart/badge.php` (`getByProductIds()`, one query per list) |
| Cards | `upload/catalog/controller/mintcart/product_cards.php` puts `manual_badges` on each product row; `ProductViewProvider::buildBadges()` appends them to `product.badges[]` |
| Product page | `upload/catalog/controller/product/product.php` sets `mc_badges`; `mint_default/.../product/product.twig` renders them above the title |
| Permissions | `202610221201_grant_admin_permissions_badges.php` |

## Contract change (Theme API)

`product.badges[]` entries of `type: "custom"` have two extra keys:

```text
type      "custom"
label     the owner's text
color     "#rrggbb" or ""            (always a validated hex, safe for a style attribute)
on_color  "#ffffff" | "#1d1f23" | "" (readable text colour for `color`)
```

Templates set the colour with the documented data-driven custom property
exception (Implementation Guide §18):

```twig
<span class="mc-badge mc-badge--{{ badge.type }}"{% if badge.color %} style="--mc-badge-bg:{{ badge.color }};--mc-badge-color:{{ badge.on_color }}"{% endif %}>{{ badge.label ?: text_badge[badge.type] }}</span>
```

`mint_default`, `mint_comfort` and `mint_gallery` card templates are updated.
A custom theme that ignores `color` still shows the label. On the product page
the template receives `mc_badges` with the same entries.

## Tests

`tests/mintcart/commerce/product-badge-test.php` (validation, contrast,
injection attempts, provider ordering). The migration conversion, the storefront
list and the product page were checked on the local store; the admin templates
were compiled with the bundled Twig. The product form save was not exercised
in a browser.

## Not done

- Rules ("every product with a special price gets -N%") are not supported;
  badges are assigned per product.
- One text for all languages; a per-language label would need a second table.
- No bulk assign (many products at once) from the product list.
- Other themes' `product.twig` (custom child themes) must add the badge list
  themselves; `mint_comfort` and `mint_gallery` inherit `mint_default`'s.
