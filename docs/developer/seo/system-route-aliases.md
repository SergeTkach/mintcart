# System route URL aliases — `Design → System Pages`

`MINTCART_FUNCTIONAL_SCOPE.md` §4, `MINTCART_TECHNICAL_PLAN.md` §31,
`MINTCART_IMPLEMENTATION_GUIDE.md` §65.

A friendlier facade over the exact same `seo_url` table the stock
**Design → SEO URL** screen already edits (`CLAUDE.md` §6/§39 — not a
second SEO storage). The stock screen makes an admin type a raw `query`
value like `checkout/cart` by hand, with nothing telling them which of the
store's many internal routes are worth a pretty URL. This screen is one
row per route, for a fixed, documented list of twelve.

PHP: `upload/admin/controller/mintcart/seo/system_pages.php`
(class `ControllerMintcartSeoSystemPages`, route
`mintcart/seo/system_pages`)

Pure whitelist: `upload/system/library/mintcart/seo/systemroutes.php`
(class `MintCart\Seo\SystemRoutes`)

View: `upload/admin/view/template/mintcart/seo/system_pages.twig`

Language: `upload/admin/language/en-gb/mintcart/seo/system_pages.php`

Menu: `upload/admin/controller/common/column_left.php`'s existing
**Design** group, right after the stock **SEO URL** entry.

Test: `tests/mintcart/seo/system-routes-test.php`
(`php tests/mintcart/seo/system-routes-test.php`, 8 scenarios)

## `MintCart\Seo\SystemRoutes`

No OpenCart dependency — a plain constant map, unit-testable in isolation,
same shape as `MintCart\Design\ActiveTheme`:

```php
public const ROUTES = [
    'search'       => 'product/search',
    'cart'         => 'checkout/cart',
    'checkout'     => 'checkout/checkout',
    'contact'      => 'information/contact',
    'account'      => 'account/account',
    'login'        => 'account/login',
    'register'     => 'account/register',
    'wishlist'     => 'account/wishlist',
    'compare'      => 'product/compare',
    'special'      => 'product/special',
    'manufacturer' => 'product/manufacturer',
    'sitemap'      => 'information/sitemap',
];
```

Every value was checked against the actual controller in this repo
(`CLAUDE.md` §84 — never assumed from memory): `checkout/cart.php`,
`checkout/checkout.php`, `information/contact.php`, `account/account.php`,
`account/login.php`, `account/register.php`, `account/wishlist.php`,
`product/compare.php`, `product/special.php`, `product/search.php`,
`product/manufacturer.php` (the manufacturer *listing* page — a specific
manufacturer's own page already gets a pretty URL via its own
`manufacturer_id` `seo_url` row, unrelated to this list),
`information/sitemap.php`.

`isValidAlias(string $alias): bool` — blank is always valid ("no alias set
for this route"); a non-blank alias must be lowercase
letters/digits/hyphens/underscores only, 1–100 characters. This is a
MintCart-side sanity check, not an OpenCart `seo_url` constraint — the
`keyword` column itself has no format rule beyond non-empty + unique. The
constraint exists because `ControllerStartupSeoUrl::index()` splits an
incoming path on `/` and compares each segment to `keyword` verbatim; a
keyword containing a literal space or slash could never match a real
request path.

## `ControllerMintcartSeoSystemPages`

One row per `SystemRoutes::ROUTES` entry, one alias `<input>`, one Save —
no per-row add/edit/delete like the stock `design/seo_url` list+form
screens, since the set of rows is fixed (exactly twelve, never more or
fewer).

- **Read:** for each route, looks up any existing `seo_url` row via
  `ModelDesignSeoUrl::getSeoUrlsByQuery($route)`, filtered in PHP to
  `store_id === 0` and `language_id === config_language_id` (see "Known
  limitation" below), and pre-fills that row's `keyword` as the alias.
- **Validate:** format (`SystemRoutes::isValidAlias()`), no two routes in
  this same form sharing one alias, and no collision against any *other*
  existing `seo_url` row for this store (`getSeoUrlsByKeyword()`) — the
  same store-scoped uniqueness rule `ControllerDesignSeoUrl::validateForm()`
  already enforces for the generic screen (`keyword` uniqueness is
  store-scoped only, not language-scoped, because
  `ControllerStartupSeoUrl::index()`'s incoming-request decode query has no
  `language_id` filter — two languages sharing one keyword for different
  routes would be genuinely ambiguous to decode).
- **Save:** blank alias deletes any existing row for that route (falls
  back to the default `index.php?route=...` link); non-blank
  adds-or-edits, `ModelDesignSeoUrl` has no upsert of its own so this looks
  the row up first, the same way `validate()` already did.

## Known limitation: single store, single language

Scoped to `store_id = 0` (the main/default store — the same value
`ControllerStartupSeoUrl` matches incoming requests against on a
single-store install) and `config_language_id` (the storefront's default
language). A store running OpenCart's multi-store or multi-language
features would need a per-store/per-language variant of this screen — not
attempted this run (`CLAUDE.md` §85 — no real multi-store MintCart consumer
yet to justify the extra UI). The underlying `seo_url` storage already
supports per-store/per-language rows (that's what the stock generic screen
exposes); only this friendlier facade is scoped down.

## Why the legacy admin theme, not `mc-*`/Bootstrap 5

Same reason every other MintCart admin screen in this repo gives (see
`docs/developer/blocks/homepage-admin.md`'s "Why this screen uses the
legacy admin theme"): the shared admin `header`/`column_left`/`footer`
chrome wraps every admin screen, and MintCart's own compiled asset bundle
has never been enqueued into it anywhere in this repo (`CLAUDE.md` §10).

## The `rewrite()` core change

Setting an alias here only changes what a *generated* link looks like —
see `docs/upstream-diff.md`'s `startup/seo_url.php` entry for the small,
additive `ControllerStartupSeoUrl::rewrite()` change this facade's aliases
now feed, and why *incoming* request decoding already worked with zero
core changes.

## Definition of Done

- [x] Pure, OpenCart-independent whitelist, unit-tested in isolation
- [x] Facade reuses stock `seo_url` storage — no second SEO table
- [x] Read/validate/save wired to a real admin screen
- [x] Outgoing link generation (`rewrite()`) also produces the alias, not
      just incoming decode
- [ ] Validated against a real page/request (`docs/MANUAL_VERIFICATION.md`
      — this environment has no MySQL to render a real page)
- [ ] Per-store/per-language variant (deferred — see "Known limitation")
