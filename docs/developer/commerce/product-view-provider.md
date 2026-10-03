# `MintCart\Commerce\ProductViewProvider`

The single place that assembles the normalized `ProductView` contract
(`MINTCART_IMPLEMENTATION_GUIDE.md` §27, `MINTCART_TECHNICAL_PLAN.md` §15D)
used by `mc-product-card` and every listing context: Category, Search,
Manufacturer, Special, Featured/Latest/Bestsellers, Related Products, and
Homepage Products blocks.

PHP: `upload/system/library/mintcart/commerce/productviewprovider.php`
(class `MintCart\Commerce\ProductViewProvider`)

Test: `tests/mintcart/commerce/product-view-provider-test.php`
(`php tests/mintcart/commerce/product-view-provider-test.php`)

## Why this exists

Today, `catalog/controller/product/category.php` (and search/manufacturer/
special controllers the same way) each build their own
`$data['products'][] = [...]` array by hand, duplicating the same
image/price/special/tax/rating logic. `MINTCART_TECHNICAL_PLAN.md` §15C/§15D
requires one shared provider instead, so that a theme's `product-card.twig`
override automatically works across every listing context.

**This increment ships the provider itself, not the controller wiring.**
`MintCart\Design\ComponentRenderer` (Phase 8) and the actual `mc-product-card`
Twig template (the other half of Phase 6) don't exist yet, so there is no
real caller to point at a live controller without guessing at a contract a
theme override hasn't exercised yet (`CLAUDE.md` §85). Wiring
`ControllerProductCategory` (and search/manufacturer/special) to call this
provider instead of their current inline array-building is follow-up work
once `mc-product-card` and the theme resolver exist.

## Design

`ProductViewProvider` has **no OpenCart dependency** — no `\Registry`,
`\DB`, currency/tax/URL services. It is a pure transform:

```php
$provider = new \MintCart\Commerce\ProductViewProvider();

$view = $provider->build($product, $display, $context);
// $view === ['product' => [...], 'display' => [...], 'context' => [...]]
```

Formatting money (`Currency::format()`), calculating tax (`Tax::calculate()`),
resizing images (`ModelToolImage::resize()`) and building SEO URLs
(`Url::link()`) all stay OpenCart's job **upstream** of this class, exactly
as `category.php` already does them today — the provider only normalizes the
*already-resolved* presentation values into the stable contract shape, and
adds the one piece of logic every controller was duplicating ad hoc: turning
those values into consistent `stock` / `badges` / `actions` fields.

## Input — `$product` (required keys marked)

| Key | Required | Type | Notes |
|---|---|---|---|
| `id` | yes | `int` | |
| `name` | yes | `string` | |
| `url` | yes | `string` | Already-built SEO link (`$this->url->link(...)`) |
| `image` | yes | `string` | Already-resized primary thumb URL |
| `secondary_image` | | `?string` | Already-resized second thumb URL, if any |
| `image_alt` | | `?string` | Defaults to `name` |
| `image_width` | | `?int` | Pixel width `image`/`secondary_image` were resized to (the same value already passed to `model_tool_image->resize()`). Defaults to `null` |
| `image_height` | | `?int` | Pixel height, same source as `image_width`. Defaults to `null` |
| `image_srcset` | | `?string` | `"<1x url> 1x, <2x url> 2x"` — see "Image srcset (responsive images)" below. Defaults to `null` |
| `price` | | `string\|false` | Already currency-formatted, or `false` if hidden (customer-group price restriction) |
| `special` | | `string\|false` | Already currency-formatted, or `false` |
| `tax` | | `string\|false` | Already currency-formatted tax-inclusive price, or `false` |
| `rating` | | `int\|false` | 0–5, or `false` if reviews are disabled |
| `quantity` | | `int\|null` | Raw stock quantity. `null` = stock not tracked → treated as in stock |
| `stock_label` | | `?string` | Pre-resolved stock display text (depends on `config_stock_display`, a store setting the provider doesn't know) |
| `minimum` | | `int` | Minimum order quantity; defaults to `1` |
| `manufacturer` | | `?string` | |
| `allow_out_of_stock_purchase` | | `bool` | Caller's resolved `config_stock_checkout` (OpenCart's backorder setting) — see below |
| `is_new` | | `bool` | Caller-supplied "new" flag — see below |
| `options_summary` | | `mixed` | Reserved, see below |

Throws `\InvalidArgumentException` if `id`, `name`, `url` or `image` is
missing or empty.

## Output contract

```text
product
  id, name, url, image, secondary_image, image_alt
  image_width, image_height   (nullable — see "Image dimensions" below)
  image_srcset   (nullable — see "Image srcset (responsive images)" below)
  price, special, tax, rating
  stock: { in_stock: bool, label: ?string }
  badges: [ { type: 'sale'|'out-of-stock'|'new', label: ?string }, ... ]
  minimum, manufacturer
  options_summary   (reserved — see below)
  actions: { add_to_cart: bool, quick_order: bool, wishlist: bool, compare: bool }

display
  show_rating (default true)
  show_description (default false)
  show_secondary_image (default true)
  action_variant (default 'default')

context
  listing_type (default 'category')
  position (default null; 1-based when the caller sets it — "1st product
            on this page", not a zero-based array index)
```

### Badges

Computed only from data the caller already has — no new business logic, no
OCMOD:

- `sale` — `special` is present and not `false`.
- `out-of-stock` — derived `stock.in_stock` is `false` (i.e. `quantity <= 0`
  when `quantity` was given).
- `new` — **only** when the caller passes `is_new: true` explicitly. A
  "recently added" threshold is a store setting that doesn't exist yet
  (Business Settings, `MINTCART_FUNCTIONAL_SCOPE.md` §3 / Phase 14), so this
  class does not guess one from `date_added` — the future Business Settings
  work decides the threshold and passes the flag in.

### `actions`

`add_to_cart` and `quick_order` are both `true` when the product **either**
has stock **or** the caller passes `allow_out_of_stock_purchase: true`
(OpenCart's `config_stock_checkout` backorder setting —
`catalog/controller/checkout/cart.php` already checks the same flag before
blocking an out-of-stock add at checkout) — **and** has a visible price
(`price !== false`). Without `allow_out_of_stock_purchase`, a store with
backorders enabled would have its add-to-cart button disabled by this
component for every out-of-stock product, silently blocking sales checkout
would otherwise accept — that gap is exactly what surfaced when
`ControllerProductCategory` first wired this class into a live page
(`docs/upstream-diff.md`). `wishlist` and `compare` are always `true`: both
are list actions available regardless of purchasability (OpenCart lets you
wishlist/compare an out-of-stock or price-hidden item).

### Image dimensions

`image_width`/`image_height` are never derived by this class — every
listing controller (Category, Search, Manufacturer, Special, the Homepage
Products block) already resizes to a fixed, theme-configured size (e.g.
`theme_<config_theme>_image_product_width`/`_height`) before calling
`build()`, exactly the same value `model_tool_image->resize()` was just
called with. This class only threads that already-known value through to
the `product-card.twig` contract so the template can set the `<img>`'s
`width`/`height` attributes and avoid a layout shift while the image loads
(`MINTCART_FUNCTIONAL_SCOPE.md` §19 Performance). Both default to `null`
for a caller that doesn't supply them (a hand-built test fixture, or a
future consumer with no fixed resize size) — the template guards on this
and omits the attribute entirely rather than rendering `width=""`.

### Image srcset (responsive images)

`image_srcset` is the same "already built upstream, just threaded
through" shape as `image_width`/`image_height` above, one level further:
Category/Search/Manufacturer/Special each call
`model_tool_image->resize()` a **second** time, at double the configured
width/height, right next to the existing 1x call, and build a
`"<1x url> 1x, <2x url> 2x"` string before calling `build()` —
`MINTCART_FUNCTIONAL_SCOPE.md` §19's "responsive images" Performance item
(`MINTCART_TECHNICAL_PLAN.md` §67/`MINTCART_IMPLEMENTATION_GUIDE.md` §67
both name `srcset`/WebP as remaining Performance work; this is the
`srcset` half — WebP/modern-format generation is a separate, larger
increment: it needs a real "does this format exist for this image"
decision that a plain double-size resize doesn't). This class never
derives it — it only threads the string through, exactly like
`image_width`/`image_height` — and defaults to `null` when a caller
doesn't supply one (a hand-built test fixture, or a future consumer with
no 2x variant), which `product-card.twig` guards on to omit the `srcset`
attribute entirely rather than render `srcset=""`.

A cost worth naming: this is one extra `model_tool_image->resize()` call
per product per page render. OpenCart's own resize already caches its
output to `image/cache/...` on first generation (an existing behavior,
unchanged by this work) — so the real cost is a one-time cache-miss GD
render per product per configured size, the same cost the *first* 1x
resize already pays today, not a new category of cost this doubles
indefinitely.

### `options_summary`

Reserved for the future Product Page listing context
(`MINTCART_TECHNICAL_PLAN.md` §15D lists it in the full `ProductView`
contract). Category/Search/Manufacturer/Special results don't carry option
data — only the single product page does — so there is no real consumer for
this field yet. It is kept in the contract (defaulting to `null`) so a theme
built against `mc-product-card` today doesn't need a breaking contract
change once the Product Page phase populates it, per `CLAUDE.md` §85: the
*key* is documented because the canonical Technical Plan already specifies
it, but no logic is invented to fill it ahead of that consumer.

## Example

```php
$provider = new \MintCart\Commerce\ProductViewProvider();

$view = $provider->build(
    [
        'id' => $result['product_id'],
        'name' => $result['name'],
        'url' => $this->url->link('product/product', 'product_id=' . $result['product_id']),
        'image' => $image, // already resized via model_tool_image->resize()
        'price' => $price, // already currency-formatted, or false
        'special' => $special,
        'tax' => $tax,
        'rating' => $this->config->get('config_review_status') ? (int) $result['rating'] : false,
        'quantity' => (int) $result['quantity'],
        'stock_label' => $result['quantity'] <= 0 ? $result['stock_status'] : null,
        'minimum' => $result['minimum'],
    ],
    [],
    ['listing_type' => 'category']
);
```

## Definition of Done checklist (`MINTCART_IMPLEMENTATION_GUIDE.md` §79, adapted for a PHP contract rather than a CSS component)

- [x] Documented contract (this file)
- [x] No OpenCart dependency — unit-testable in isolation
- [x] Same shape usable by Category, Search, Manufacturer, Special and Blocks (no per-context branching in the output shape)
- [x] No OCMOD required
- [x] Smoke test covering required-field validation, badges, stock, actions and display/context overrides
- [ ] Actual controller wiring (Category/Search/Manufacturer/Special calling this instead of their inline array-building) — deferred until `mc-product-card` + the Theme resolver (Phase 8) exist, so the contract isn't finalized against a consumer that doesn't exist yet
