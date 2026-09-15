# SEO Core — Meta Builder

`MintCart\Seo\MetaBuilder`
(`upload/system/library/mintcart/seo/metabuilder.php`)

## What this is

The "meta title; meta description" line item from
`MINTCART_FUNCTIONAL_SCOPE.md` §16 "SEO Core" (alongside canonical,
hreflang, robots, sitemap and the three Schema.org types — see
"Relationship to other SEO Core pieces" below).

## The problem it fixes

Several storefront controllers pass an entity's own `meta_title`/
`meta_description` straight through to `Document::setTitle()`/
`setDescription()`, with **no fallback at all**:

```php
// catalog/controller/product/category.php
$this->document->setTitle($category_info['meta_title']);
$this->document->setDescription($category_info['meta_description']);

// catalog/controller/product/product.php
$this->document->setTitle($product_info['meta_title']);
$this->document->setDescription($product_info['meta_description']);

// catalog/controller/common/home.php
$this->document->setTitle($this->config->get('config_meta_title'));
$this->document->setDescription($this->config->get('config_meta_description'));
```

A category or product created without its "Meta Tag Title" field ever
being filled in — a common case for anything added quickly — ships that
page with an **empty `<title>` tag**. The homepage is exactly as exposed:
`config_meta_title`/`config_meta_description` are blank on a fresh
install until a merchant fills them in under Settings, so a store that
hasn't gotten to that screen yet ships a blank `<title>` on its own
homepage. This is a well-known, easily reproduced OpenCart SEO complaint,
not a hypothetical.

`product/manufacturer.php` and `product/search.php` sidestep this
differently — they never use an entity meta field at all (manufacturer
has none; search always builds a heading + search term) — so they are not
part of the case this class fixes.

## Contract

```php
$builder = new \MintCart\Seo\MetaBuilder();

$meta = $builder->build($entityTitle, $entityDescription, $fallbackTitle, $fallbackDescription);
// ['title' => string, 'description' => string] — never null, '' only
// when both the entity value and its fallback were empty/whitespace.
```

- `$entityTitle` / `$entityDescription` — the entity's own field
  (`meta_title`/`meta_description`, or `config_meta_title`/
  `config_meta_description` for the homepage), or `null`.
- `$fallbackTitle` — a value the controller already has on hand: the
  category/product's own `name`, or the store name
  (`$this->config->get('config_name')`) for the homepage. Required (not
  optional) — every real call site has one.
- `$fallbackDescription` — optional. Several call sites genuinely have
  nothing sensible to fall back to (there is no second, shorter
  description field anywhere in stock OpenCart) — omit it and the
  description simply stays `''` when the entity value is blank, exactly
  today's behavior, just now an explicit outcome rather than an
  accidental one.

A caller wires this in as, for example:

```php
$meta = $builder->build(
    $category_info['meta_title'],
    $category_info['meta_description'],
    $category_info['name']
);

$this->document->setTitle($meta['title']);
$this->document->setDescription($meta['description']);
```

## What's deliberately not done yet

- **No controller wiring.** None of `category.php`/`product.php`/
  `home.php` call this class yet — each still has its own direct
  pass-through, unchanged and un-behavior-affected by this increment.
  Swapping the three call sites for calls into this class is a genuinely
  separate, mechanical follow-up (replace, don't redesign) once this
  primitive itself has been reviewed — same reasoning
  `MintCart\Seo\CanonicalUrlBuilder` already documented for its own four
  controller call sites.
- **No length truncation/HTML stripping.** `meta_title`/
  `meta_description` are already plain-text admin fields in stock
  OpenCart (distinct from a product's full HTML `description`), so there
  is nothing to strip; a length cap wasn't added since no current call
  site does one today and it isn't needed to fix the blank-value bug this
  class targets — CLAUDE.md §17 ("don't create abstractions... for
  hypothetical future use").
- **No Fix Registry entry yet.** This is filed as a new SEO Core
  capability (Functional Scope §16), matching every sibling `MintCart\Seo\*`
  builder's own framing. Once a future increment actually wires a
  controller to use it, that wiring increment is the natural point to
  also add a `docs/fixes/registry.md` entry for the blank-title/
  description defect itself (`CLAUDE.md` §13's five-step Fixes Policy —
  reproduce, check upstream, implement, test — applies to the *wiring*
  increment, not to this pure primitive).

## Relationship to other SEO Core pieces

Functional Scope §16 lists SEO URLs, system route URLs, canonical,
hreflang, meta title, meta description, robots controls, sitemap and
three Schema.org types under one "SEO Core" umbrella. Each is being built
as its own small, independently reviewable `MintCart\Seo\*` (or
sibling-namespace) class rather than one large "SEO service" — consistent
with `CLAUDE.md` §86 ("one clear service" over a large multi-responsibility
class). This class covers meta title/description fallback only.

## Verified

`php -l` clean. `tests/mintcart/seo/meta-builder-test.php` (9 scenarios)
— scenarios 1-4 cover the basic fallback rule (entity value wins;
empty/whitespace/null all fall back the same way); 5-7 replay the exact
blank-meta shapes `category.php`, `product.php` and `home.php` produce
today; 8 covers a fallback description being supplied; 9 confirms the
result is always a string, never null. Pure PHP, no OpenCart dependency
— no live-install verification gap applies (same as `PackageManifest`
and `CanonicalUrlBuilder`) since no controller calls this class yet. Full
`tests/mintcart/` suite re-run end to end (35 files, was 34) — all passing,
no regressions (pure addition, no existing file modified).
