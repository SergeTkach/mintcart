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

## Live wiring

`category.php` and `home.php` now call this class (see
`docs/upstream-diff.md`'s entries for both files for the full writeup):

```php
// catalog/controller/product/category.php, inside the success branch
$mint_meta = (new \MintCart\Seo\MetaBuilder())->build(
    $category_info['meta_title'],
    $category_info['meta_description'],
    $category_info['name']
);

$this->document->setTitle($mint_meta['title']);
$this->document->setDescription($mint_meta['description']);
```

```php
// catalog/controller/common/home.php, top of index()
$mint_meta = (new \MintCart\Seo\MetaBuilder())->build(
    $this->config->get('config_meta_title'),
    $this->config->get('config_meta_description'),
    $this->config->get('config_name')
);

$this->document->setTitle($mint_meta['title']);
$this->document->setDescription($mint_meta['description']);
```

**Title-only fallback at these call sites:** the third argument supplies
only a title. Neither controller passes the optional fourth argument, so an
empty description remains empty. The shipped header templates omit its meta
tag. Do not infer a description fallback from the title argument.

`product.php` now calls this class too (MC-0016):

```php
// catalog/controller/product/product.php, inside the success branch
$mint_meta = (new \MintCart\Seo\MetaBuilder())->build(
    $product_info['meta_title'],
    $product_info['meta_description'],
    $product_info['name']
);

$this->document->setTitle($mint_meta['title']);
$this->document->setDescription($mint_meta['description']);
```

Same title-only fallback shape as `category.php`/`home.php`: no fallback
description is offered, so a blank `meta_description` stays empty.

## What's deliberately not done yet

- **`product/manufacturer.php` and `product/search.php` are still
  unwired**, and deliberately so — see "The problem it fixes" above:
  neither has an entity meta field to fall back from (manufacturer has
  none in stock OpenCart; search always builds a heading + search term,
  never a blank title), so they are not instances of the bug this class
  fixes, not a remaining gap.
- **No length truncation/HTML stripping.** `meta_title`/
  `meta_description` are already plain-text admin fields in stock
  OpenCart (distinct from a product's full HTML `description`), so there
  is nothing to strip; a length cap wasn't added since no current call
  site does one today and it isn't needed to fix the blank-value bug this
  class targets — CLAUDE.md §17 ("don't create abstractions... for
  hypothetical future use").
- **Fix Registry entries:** `docs/fixes/registry.md` MC-0015 documents the
  `category.php`/`home.php` blank-title defect; MC-0016 documents the same
  defect on `product.php`, per `CLAUDE.md` §13's five-step Fixes Policy
  (the Policy applies to each *wiring* increment, not to this pure
  primitive, which predates both entries).

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

**Update (live wiring, MC-0015):** `php -l` clean on both modified
controllers. No new test file needed — this class's own existing
scenarios 5/7 already cover the exact shapes now flowing through
`category.php`/`home.php`. Full `tests/mintcart/` suite (77 files) re-run
end to end — all passing, no regressions. Not verified: no live
MySQL/Twig session in this environment — see `docs/fixes/registry.md`
MC-0015 and `docs/MANUAL_VERIFICATION.md`'s Phase 18 section for the
manual steps a human should run against a real install.

**Update (live wiring, MC-0016):** `php -l` clean on `product.php`. No new
test file needed — scenario 6 already covers this exact shape. Full
`tests/mintcart/` suite (77 files) re-run end to end — all passing, no
regressions. Not verified: same no-live-install gap as MC-0015 — see
`docs/fixes/registry.md` MC-0016 for the manual step a human should run.
