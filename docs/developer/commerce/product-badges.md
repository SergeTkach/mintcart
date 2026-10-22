# Manual product badges ("stickers")

Short labels the store owner puts on chosen products: "Bestseller", "-20%",
"Eco". They are shown on product cards after the automatic badges (Sale,
Out of stock, New). Spec: `MINTCART_FUNCTIONAL_SCOPE.md` §11 "configurable
badges/stickers".

## Where the owner edits them

Admin → Marketing → **Product Badges** (`route=mintcart/badge`).
One page: an add/edit form and the list of badges.

- Product: the stock OpenCart admin autocomplete (as in the coupon form).
  The product of an existing badge cannot be changed; delete and add again.
- Badge text: 1–32 characters, one text for all languages.
- Colour: optional `#rrggbb`. Empty means the theme's neutral `mc-badge` look.
  The text colour (dark or white) is picked automatically for contrast.
- Sort order: badges of one product are shown in this order.

A product may have several badges.

## Storage

Table `mc_product_badge` (migration `202610221200_create_mc_product_badge.php`):
`badge_id`, `product_id`, `label`, `color`, `sort_order`, `date_added`.
InnoDB, utf8mb4, no foreign key (as the rest of OpenCart's schema).
A badge of a deleted product is simply never read; the admin list shows it as
`#<product_id>` so it can be removed.

## Code

| Part | File |
|---|---|
| Validation and colour logic | `upload/system/library/mintcart/commerce/productbadge.php` (`MintCart\Commerce\ProductBadge`) |
| Admin screen | `upload/admin/controller/mintcart/badge.php`, `admin/model/mintcart/badge.php`, `admin/view/template/mintcart/badge.twig`, `admin/language/en-gb/mintcart/badge.php` |
| Storefront read | `upload/catalog/model/mintcart/badge.php` (`getByProductIds()`, one query per list) |
| Wiring | `upload/catalog/controller/mintcart/product_cards.php` puts `manual_badges` on each product row |
| Contract | `ProductViewProvider::buildBadges()` appends them to `product.badges[]` |
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
A custom theme that ignores `color` still shows the label.

## Tests

`tests/mintcart/commerce/product-badge-test.php` (validation, contrast,
injection attempts, provider ordering).

## Not done

- Rules ("every product with a special price gets -N%") are not supported;
  badges are per product.
- Badges are not shown on the product page itself, only on cards.
- One text for all languages; a per-language label would need a second table.
