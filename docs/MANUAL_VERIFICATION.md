# Manual verification checklist

What still needs a human on a real, configured install before treating the
corresponding work as fully done. This is a **live backlog, not an audit
log** — once an item is confirmed with no loose ends, remove it from this
file entirely rather than leaving a checked-off record here. The full
"how it was verified, what was found" narrative belongs in
`docs/IMPLEMENTATION_STATUS.md`, which is the permanent record; this file
only exists to make "what's still outstanding" scannable at a glance.

If something fails, open an issue/PR referencing the item below rather
than silently fixing and forgetting to update this file.

---

## Phase 8 — rating stars never actually seen rendered

No product in the demo dataset has a non-zero rating (no approved reviews
seeded), so `mc-rating`'s star display has been confirmed correct in
isolation (component test) but never observed on a real product-card.
Add a review via Admin → Catalog → Reviews (approve one) and reload a
category page containing that product.

## Phase 8 — `mintcart.log` didn't get written during a live degrade test

Confirmed: the category page degrades gracefully (HTTP 200, no fatal) when
`product-card.twig` is temporarily removed. But the `ERROR` line
`MintCart\Core\Logger` should write to `mintcart.log` never appeared
during that live HTTP request, even though isolated `php -r` checks
confirm both `ComponentRenderer::resolve()`'s throw and `Logger`'s write
work correctly on their own. Likely a local Apache-process-user vs.
`storage/logs/` write-permission gap, not a code defect — not root-caused
further. Worth checking file permissions before this matters for
production.

## Phase 8 — third-party theme (N/A until one exists)

No third-party (non-Mint) theme is installed in this environment. When
one is, confirm it renders unaffected and that `ComponentRenderer`/
`ProductViewProvider` are never instantiated for that request — confirms
the `theme.json`-presence gate actually excludes non-Mint themes, not
just the literal string `'default'`.

## Phase 9 — Presets, entirely unverified visually (currently blocked)

The preset *contract* is machine-checked (`npm test`), but whether Warm
actually **looks** like a coherent, radically different design has never
been seen — and currently can't be, on a real MintCart page:
`assets/dist/css/index.min.css` still isn't enqueued anywhere (a blanket
enqueue attempt broke legacy Bootstrap 3 widgets and was reverted — see
`header.twig`'s own comment). Once that's resolved, on a `mint_default`
category page:

```js
document.documentElement.dataset.mcPreset = 'warm';   // or 'clean'
```

- Warm reads as one deliberate look (terracotta buttons, cream surfaces,
  serif headings, rounder corners, roomier spacing, warm-tinted shadows),
  not a half-applied palette.
- Nothing is left showing mint green/neutral gray — any hit is a
  component reading a hardcoded color instead of a semantic token (a
  component bug, not a preset bug, per ADR 004).
- Text contrast holds under Warm (WCAG 2.2 AA) — picked for contrast on
  paper, never measured against real rendered output.
- Removing the attribute looks identical to `data-mc-preset="clean"`
  (ADR 004 decision 3).
- The same two presets applied to `test_theme`'s different DOM also both
  render coherently.

In the meantime, a standalone HTML file that links the built CSS directly
(no OpenCart involved) would let someone eyeball the palette without
waiting for the enqueue fix, if that's wanted sooner.

## Phase 15 — header cart dropdown DOM swap (browser-only)

Confirmed via `curl` that `common/cart/info`'s own response reflects an
add made through `mintcart/cart/add` (both endpoints share the same
session cart). The actual JS behavior — does clicking product-card's Add
to Cart visibly swap `#cart`'s markup in the header — is browser-only;
`curl` can't click a button. A human should confirm in a real browser.

## Phase 10 — Homepage Builder, never exercised end to end

`MintCart\Blocks\BlockRenderer`/`ProductsBlockProvider`/`ModelMintcartBlock`/
`ModelDesignHomepage` are all covered by standalone tests, but no live
MySQL/`vendor/twig/twig`/OpenCart admin session in this environment means
neither the admin editor nor the homepage render has ever been observed
live. `docs/developer/blocks/homepage-admin.md`'s "Manual verification"
section is the checklist (add a block via `Design > Homepage`, confirm it
renders on the storefront, reorder/disable/delete, confirm each takes
effect) — a human should work through it on a real install.

## Phase 12 — `mintcart/entity/category` endpoint, never hit end to end

`MintCart\Design\CategoryPickerProvider` is covered by a standalone test,
but no live MySQL in this environment means
`ControllerMintcartEntity::category()` itself was never actually invoked
against a real database. On a real install:

1. Log in to Admin, go to **Users → User Groups**, edit the group you're
   logged in as, and grant **Access** to `mintcart/entity` (a fresh
   install won't have this yet — see
   `docs/developer/components/entity-picker.md` "Admin backends" for why).
2. Request `index.php?route=mintcart/entity/category&user_token=<your
   token>&filter_name=&page=1&limit=20` and confirm a JSON
   `{"items": [...], "total": N}` response with real category names,
   matching what `catalog/category/autocomplete` returns for the same
   store (same rows, different shape).
3. Confirm a request with `filter_name` set to a substring of one
   category's name returns only matching categories, and that `total`
   reflects the filtered count, not the store's full category count.

## Phase 14 — Store Readiness, never hit end to end

`MintCart\Onboarding\StoreReadiness` is covered by a standalone unit test
(pure logic, no OpenCart dependency), and `ModelMintcartReadiness`'s SQL
was read carefully against the real `oc_language`/`oc_currency`/
`extension`/`setting` schema, but no live MySQL/admin session in this
environment means `mintcart/readiness` was never actually requested. On a
real install, as a logged-in admin user: `GET admin/index.php?route=mintcart/readiness&...`
(with the usual `token`/session params) and confirm it returns the 7-item
checklist as JSON, with each `complete` flag matching the store's real
configuration. Then flip one signal (e.g. enable a payment extension via
its own settings screen) and reload — confirm that check flips to `true`
and `percent`/`ready_for_test_order` update accordingly.

## Phase 17 — category move/reorder backend, never exercised against a real DB

`ModelCatalogCategory::moveCategory()`/`rebuildCategoryPath()` and
`ControllerCatalogCategory::move()` are new (see
`docs/developer/catalog/category-drag-drop.md`), but no live MySQL in this
environment means the transaction/closure-table rebuild was never run
against a real `category_path` table — only `MintCart\Catalog\CategoryMoveValidator`'s
cycle-prevention math is unit-tested. On a real install, with an admin
user token, using an existing multi-level category tree:

1. `POST index.php?route=catalog/category.move&user_token=<token>` with
   `category_id`, `parent_id`, and `order[]` set to move a category under
   a new, unrelated parent. Confirm `{"success": "..."}`, then check
   **Catalog → Categories** shows the new parent and that the storefront
   category page for both the old and new parent still resolves correctly
   (breadcrumb, product listing).
2. Attempt to move a category into one of its own children (`parent_id` =
   a known child's `category_id`). Confirm `{"error": "The parent
   category you have chosen is a child of the current one!"}` and that
   nothing in the database changed.
3. Reorder several siblings via `order[]` alone (same `parent_id` as
   today's) and confirm **Catalog → Categories**' sort order updates to
   match.
4. Confirm `editCategory()` (the existing add/edit form) still works
   exactly as before — `rebuildCategoryPath()` is an extraction of its
   prior inline logic, not a rewrite, but this should still be re-checked
   live once MySQL is available.

## Phase 12 — `mintcart/entity/manufacturer` endpoint, never hit end to end

Same gap as the category endpoint above, plus one thing category couldn't
exercise at all: `image` resolution. On a real install, with at least one
manufacturer that has an image set and one that doesn't:

1. Grant `mintcart/entity` **Access** if not already done for the category
   endpoint above (same permission covers both routes).
2. Request `index.php?route=mintcart/entity/manufacturer&user_token=<your
   token>&filter_name=&page=1&limit=20` and confirm a JSON
   `{"items": [...], "total": N}` response with real manufacturer names.
3. Confirm the manufacturer with an image returns a real, loadable
   `image` URL (not `null`, and the URL actually resolves to the same
   100x100 thumbnail `Catalog → Manufacturers`' own list shows), and the
   one without an image returns `image: null`.
4. Confirm `filter_name` set to the *start* of one manufacturer's name
   matches it, but a substring from the *middle* of that name does not
   (prefix match, not substring — see
   `docs/developer/components/entity-picker.md`'s manufacturer section for
   why this differs from category's substring match).

## Phase 13 — Wizard Contacts step, never exercised end to end

`ControllerMintcartWizard::validateContactsStep()`/`saveContactsStep()`
are `php -l`-clean and manually reviewed, but no live MySQL/admin session
in this environment means the form was never actually submitted. On a
real install, at `mintcart/wizard` once Store is completed:

1. Submit the Contacts step with all four fields blank — confirm it saves
   (no validation error) and advances to the next step / "ready" state.
2. Go back to Contacts (via the step nav), fill in one valid URL (e.g.
   `https://facebook.com/example`) and one deliberately malformed value
   (e.g. `not a url`) — confirm the malformed field shows a validation
   error and the form is *not* saved (the valid field's value should still
   be in the input on reload, not lost).
3. Fill in all four with valid URLs, submit, then reload the Contacts step
   — confirm all four fields re-populate from storage (not blank).
4. Load a storefront page on a `mint_default`-themed store and confirm the
   footer's social links section renders the saved URLs with working
   `href`s (currently a plain first-letter glyph per link — icon rendering
   is still a documented follow-up, see `footer.twig`'s own `mc_icon()`
   comment, unrelated to this change).

## Phase 12 — `mintcart/entity/product` endpoint, never hit end to end

Same gap as category/manufacturer above, plus one thing neither of those
exercised: SQL-level pagination (this backend counts/pages in the
database, not PHP — see `docs/developer/components/entity-picker.md`'s
Product backend section). On a real install, with a catalog of more than
20 products and at least one product that has an image set and one that
doesn't:

1. Grant `mintcart/entity` **Access** if not already done for the
   category/manufacturer endpoints above (same permission covers all
   three routes).
2. Request `index.php?route=mintcart/entity/product&user_token=<your
   token>&filter_name=&page=1&limit=20` and confirm a JSON
   `{"items": [...], "total": N}` response where `total` is the real
   store-wide product count (not just 20, the page size).
3. Request `page=2` with the same `limit` and confirm it returns the
   *next* 20 products, not the same page again or an empty set — this is
   the first entity backend where a wrong `start` calculation could
   silently repeat or skip rows, since paging happens in SQL rather than
   `array_slice()` over an already-fetched PHP array.
4. Confirm the product with an image returns a real, loadable `image` URL
   (the same 100x100 thumbnail `Catalog → Products`' own list shows), and
   the one without an image returns `image: null`.
5. Confirm `filter_name` set to the *start* of one product's name matches
   it, but a substring from the middle does not (prefix match, same as
   manufacturer, not category's substring match) — and confirm `total`
   reflects the *filtered* count when `filter_name` is set, not the
   store-wide total.

## Phase 12 — `mintcart/entity/information` endpoint, never hit end to end

Same never-hit-live gap as category/manufacturer above, plus one thing
those two couldn't exercise: this backend filters in PHP, not SQL, since
`ModelCatalogInformation::getInformations()` has no `filter_name` support
at all. On a real install, with at least three information pages (the
default OpenCart demo data already ships "About Us", "Delivery
Information" and "Privacy Policy" — good enough):

1. Grant `mintcart/entity` **Access** if not already done for the category
   endpoint above (same permission covers all three routes).
2. Request `index.php?route=mintcart/entity/information&user_token=<your
   token>&filter_name=&page=1&limit=20` and confirm a JSON
   `{"items": [...], "total": N}` response with real information page
   titles, `image` always `null`.
3. Set `filter_name=delivery` (lowercase) and confirm only "Delivery
   Information" comes back, with `total: 1` — confirms the case-insensitive
   PHP-side match actually runs against live data, not just the unit test's
   plain arrays.
4. Set `filter_name` to a substring from the *middle* of a title (e.g.
   `mation` for "Delivery Information") and confirm it still matches —
   confirms this is a substring match, not a prefix match like
   manufacturer's.
5. With more information pages than one page's `limit`, confirm page 2
   returns the next slice of the *filtered* set, not the next slice of the
   full unfiltered set (paging is applied after filtering, per
   `InformationPickerProvider`'s own doc comment — this ordering has no
   unit-test equivalent against a live cache invalidation path).

## Phase 12 — `mintcart/entity/attribute` endpoint, never hit end to end

Same gap as the category/manufacturer endpoints above. On a real install,
with at least two attributes (e.g. under **Catalog → Attributes**) sharing
a name pattern where one match is at the start and one only in the middle:

1. Grant `mintcart/entity` **Access** if not already done for the category
   endpoint above (same permission covers all three routes).
2. Request `index.php?route=mintcart/entity/attribute&user_token=<your
   token>&filter_name=&page=1&limit=20` and confirm a JSON
   `{"items": [...], "total": N}` response with real attribute names and
   `image: null` on every row (attributes have no image concept).
3. Confirm `filter_name` set to the *start* of one attribute's name
   matches it, but a substring from the *middle* of that name does not
   (prefix match, same `LIKE '…%'` behavior as the manufacturer endpoint,
   not category's substring match — see
   `docs/developer/components/entity-picker.md`'s attribute section).
4. Confirm paging (`page=2`) returns the next slice of the same
   alphabetically-sorted list, not a repeat or a skip.
