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

## Phase 15 — Quick Order, never hit end to end

`MintCart\Commerce\QuickOrderService` and the
`202609041200_create_mc_quick_order.php` migration are both covered by
standalone tests, but no live MySQL in this environment means
`mintcart/quickorder/submit` has never actually run, and the modal built by
`assets/src/js/components/quick-order.js` has never been clicked in a real
browser. A human should, on a real install:

1. Run the pending migration (`mc_quick_order` table should appear).
2. Open a product listing (category/search/home), click **Quick Order** on
   a product with no options, fill in name + phone (or email), submit —
   confirm the modal closes, a success toast appears, and a new row lands
   in `mc_quick_order` with the right `product_id`/`quantity`/`name`/
   `telephone`/`email`/`status = 'new'`.
3. Submit with an empty name, or with both phone and email empty, or with
   a malformed email — confirm the matching field gets `has-error` +
   the translated message, and nothing is inserted.
4. Click Quick Order on a product that has required options — confirm it
   redirects to the product page instead of opening the modal's success
   path (same fallback `add-to-cart` already uses), and nothing is
   inserted.
5. Confirm keyboard-only use works: open the modal via Enter/Space on the
   button, tab through the fields, submit via Enter, and that focus
   returns to the triggering button when the modal closes (Bootstrap
   `Modal`'s own focus-management, not custom code here).
