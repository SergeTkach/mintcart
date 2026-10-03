# `MintCart\Catalog\SortOptionsBuilder` / `LimitOptionsBuilder`

Pure extraction of the "sort" and "items per page" dropdown option lists
that `catalog/controller/product/category.php`, `manufacturer.php` and
`search.php` each build with an identical inline block
(`MINTCART_TECHNICAL_PLAN.md` §15C/§15E/§38C — named `SortOptionsBuilder`/
`LimitOptionsBuilder` as reusable Product Listing pipeline candidates).

PHP: `upload/system/library/mintcart/catalog/sortoptionsbuilder.php`,
`upload/system/library/mintcart/catalog/limitoptionsbuilder.php`
Tests: `tests/mintcart/catalog/sort-options-builder-test.php`,
`tests/mintcart/catalog/limit-options-builder-test.php`

## Why this exists

Every listing controller repeats the exact same nine-entry (seven when
`config_review_status` is off) sort list — same `sort`/`order` pairs, same
language keys, same order, same review-status gate — and the exact same
`array_unique([$themeLimit, 25, 50, 75, 100]); sort();` limit merge.
Extracting both into pure classes means a future fifth listing context
(Homepage Products block, a future Bestsellers/Related listing) gets the
same options for free instead of re-typing the block a fifth time, and any
future change to the option set (Functional Scope §11 "Commerce
Essentials") only has one place to change.

Both classes deliberately have **no OpenCart dependency** — same
"primitive first, wire the controllers separately" pattern
`MintCart\Seo\CanonicalUrlBuilder` and `MintCart\Catalog\CategoryMoveValidator`
already established (`CLAUDE.md` §85, "API appears when a real consumer
exists" — the consumer here is `category.php`'s own long-standing inline
block, not a hypothetical one):

- `SortOptionsBuilder::build(bool $reviewEnabled)` returns a list of
  `['sort' => ..., 'order' => ..., 'text_key' => ...]`. The controller
  still calls `$this->language->get($text_key)` and `$this->url->link()`
  itself — the builder has no opinion on the active language or route.
- `LimitOptionsBuilder::build(int $themeLimit)` returns the merged,
  deduplicated, ascending-sorted list of ints. The controller still builds
  each option's `href`.

## Wired consumers

- `ControllerProductCategory::index()` — both builders, replacing the
  identical inline blocks byte-for-byte (verified behavior-preserving by
  the standalone tests, which replay the exact sort/order/text_key and
  limit combinations the old inline code produced).

## Not yet wired

`ControllerProductManufacturer::index()` and `ControllerProductSearch::index()`
carry the same duplicated blocks and are natural next consumers — each is
a small, independent, single-file wiring change with no shared-file
collision risk. `special.php` does not build a `$data['sorts']`/
`$data['limits']` block at all in stock OpenCart 3.0.5.1 (it has no
sort/limit UI), so there is nothing to wire there.

## Definition of Done checklist (`MINTCART_IMPLEMENTATION_GUIDE.md` §79, adapted for a pure PHP service)

- [x] Documented contract (this file)
- [x] No OpenCart dependency — unit-testable in isolation
- [x] Standalone smoke test replaying the exact existing behavior
- [x] `php -l` clean
- [x] Wired into at least one real consumer (`category.php`)
- [ ] Wired into `manufacturer.php`/`search.php` — next increment
