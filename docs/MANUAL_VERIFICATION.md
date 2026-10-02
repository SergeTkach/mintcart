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

## Phase 0 — restored `Config` engine class never booted live

`upload/system/library/config.php` (OpenCart's core `Config` class) was
missing from this repository entirely until this run (a `.gitignore`
false positive silently excluded it since the initial baseline commit —
see `docs/IMPLEMENTATION_STATUS.md`'s Phase 0 section and `docs/audit.md`'s
"Correction" note for the full writeup). It has been restored
byte-for-byte from the upstream OpenCart `3.0.5.1` tag and passes `php -l`,
but no environment used by any routine run so far has live PHP/MySQL to
actually boot `index.php`/`admin/index.php`. Confirm on a real install:
both the storefront and admin now load past `system/framework.php`
without a fatal on `new Config()` (they would have fatal'd on every single
request before this fix), and that a page depending on
`Config::set()`/`Config::has()` (e.g. anything reading
`config_theme`/`config_language_id`) behaves normally.

## Phase 13 — Wizard Delivery step never rendered live

No MySQL/OpenCart admin session in this environment. Confirm on a real
install: `mintcart/wizard?step=delivery` lists every installed shipping
extension with its real enabled/disabled state; clicking Enable/Disable
(`mintcart/wizard.toggleDelivery`) flips only that extension's
`shipping_<code>_status` key and leaves its other settings (cost, tax
class, geo zone, ...) untouched — the same check
`docs/developer/onboarding/business-delivery-payments.md`'s own
`Business → Delivery` entry already asks for, now against a second call
site; "Manage delivery methods" links to a working `mintcart/business/delivery`
page; a store with zero shipping extensions installed shows the empty
state instead of an empty list; and clicking Continue with nothing enabled
still advances to the Ready step (no required-field validation for this
step), while the Ready step's own checklist correctly still shows
"shipping" as outstanding until at least one method is enabled.

## Phase 13 — Wizard Language & currency step never rendered live

No MySQL/OpenCart admin session in this environment. Confirm on a real
install: `mintcart/wizard?step=language_currency` renders the real
`oc_language`/`oc_currency` rows as `<select>` options; submitting it
saves `config_language`/`config_currency` and leaves every other
`config_*` setting (name, email, telephone, address, tax, checkout, ...)
untouched; and submitting an invalid/tampered language or currency code
shows the expected validation error instead of silently saving garbage.

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

## MC-0013 — SMTP `EHLO`/`HELO` hostname fallback, never exercised against a real SMTP server

This environment has no outbound SMTP access, so `HeloHostname::resolve()`
is only unit-tested (`tests/mintcart/mail/helo-hostname-test.php`) against
`getenv()`/`php_uname()` directly, never against a live `Mail\Smtp::send()`
call. On a real install with `Settings → Mail` (`config_mail_engine`) set
to `SMTP` and real SMTP host/port/username/password configured:

1. Trigger any email through the normal web request path (e.g. **Sales →
   Orders** → an order's own "Send" email action, or a customer
   registration confirmation) and confirm it still arrives — this exercises
   the unchanged, overwhelmingly common case where `SERVER_NAME` is present,
   confirming the fix introduced no regression there.
2. Trigger the same kind of email from a CLI context instead — e.g. `php
   index.php route=<any route that sends mail>` run directly from a shell,
   or an existing OS-level cron entry that hits an OpenCart route by URL
   with `curl`/`wget` (which still runs the request through the normal web
   SAPI, so this specifically needs a *literal* PHP CLI invocation to
   reproduce the original bug) — and confirm it also now arrives, where
   before this fix the SMTP server would have rejected the malformed empty
   `EHLO`/`HELO` at that step. Compare against `system/storage/logs/`
   (OpenCart's own error log) for an `"Error: EHLO not accepted from
   server!"`/`"Error: HELO not accepted from server!"` exception before vs.
   after the fix, if a CLI-triggered mail send doesn't already exist to
   test against.
## MC-0010 — Opayo/Sage Pay recurring payment, never hit end to end

`setPaymentData()` (`catalog/model/extension/payment/opayo.php`) is only
called for a repeat/cron-triggered recurring charge, which needs both a
live OpenCart order with an active recurring plan and a real Opayo/Sage
Pay merchant account — neither exists in this environment. A human with
both should, after this fix:

1. Trigger a recurring payment run (however this store's cron/recurring
   job is configured) against a test/sandbox Opayo account.
2. Confirm the charge still succeeds and the gateway accepts the generated
   `VendorTxCode` (proving `date('YmdHis')` produces a value Opayo accepts
   the same way the old `strftime()`-built one did).
3. Check the PHP error log for that request and confirm no
   `strftime() is deprecated` notice appears (the bug this fix removes).
## Fix MC-0009 — `seo_url.php` incoming decode language filter, never exercised end to end

No live MySQL/multi-language storefront in this environment. On a real
multi-language install (`Localisation → Languages` has at least two
enabled languages):

1. With SEO URLs enabled (`Settings → Store → Server → Use SEO URL: Yes`)
   and only one language's products/categories given custom SEO keywords,
   browse the storefront in every enabled language and confirm every
   existing pretty URL still resolves to the same page as before this
   change (the fallback path — no behavior change expected here).
2. Create two different products (or a product and a category), each in a
   different language, and give both the *exact same* SEO keyword text
   (`Design → SEO URL`, or each entity's own SEO tab).
3. Switch the storefront to the first product's language (language
   switcher, or clear the `language` cookie and let Accept-Language/
   session detection pick it) and request that keyword's URL directly —
   confirm it resolves to the product/category belonging to *that*
   language, not the other one.
4. Repeat step 3 switched to the second entity's language and confirm the
   opposite resolution — proving the fix actually disambiguates by
   language rather than being coincidentally correct in one direction.
## Phase 7 — Header mobile navigation, never opened in a real browser

`mc-header__nav`'s new responsive off-canvas behavior (`docs/developer/components/header.md`
"Mobile navigation") is unit-verified only as far as this environment
allows (Twig tag balance, `npm run build` output, no runtime DOM). On a
`config_theme=mint_default` install, once MintCart's own asset bundle is
enqueued (`docs/developer/components/header.md`'s "Known gap" — still
required before *any* Mint component is visually verifiable):

1. At a viewport ≥992px (`lg` and up), confirm the store's navigation menu
   renders as a normal horizontal bar under the header, with **no**
   hamburger button visible.
2. Resize below 992px (or use a mobile device/emulator). Confirm the
   navigation menu disappears from the page and a hamburger button
   (`.mc-header__nav-toggle`) appears instead.
3. Tap/click the hamburger. Confirm the navigation slides in from the left
   edge as a panel with a "Menu" heading and a close (`×`) button, a
   backdrop appears behind it, and the page behind it stops scrolling.
4. With the panel open, confirm: pressing `Escape` closes it; clicking the
   backdrop closes it; clicking the close button closes it; keyboard focus
   is trapped inside the panel while open (`Tab`/`Shift+Tab` cycle only
   through its own focusable elements) and returns to the hamburger button
   once closed.
5. With a screen reader (or the browser's accessibility inspector),
   confirm the hamburger button's `aria-expanded` reads `false` when
   closed and `true` while the panel is open (`docs/developer/components/drawer.md`'s
   `syncTriggerAriaExpanded()`).
6. Resize back to ≥992px while the panel is open below it, and confirm the
   navigation returns to its normal inline position with no leftover
   backdrop/scroll-lock (Bootstrap's own responsive-offcanvas behavior).
## Phase 15 — Checkout totals refactor + new endpoint, never exercised against a real DB

No MySQL/OpenCart session in this environment. `MintCart\Compatibility\OpenCart3\TotalsAdapter`
is unit-tested in isolation (`tests/mintcart/compatibility/totals-adapter-test.php`),
and `checkout/payment_method.php`/`checkout/confirm.php` are `php -l` clean
after being refactored to use it, but neither controller was ever actually
run against a real cart. On a real install, with at least one shipping
extension and one payment extension enabled:

1. Add a product to the cart and walk through checkout up to and including
   the Payment step — confirm the same payment methods appear, in the same
   order, as before this change, and that the amount each payment
   extension is quoted (e.g. a percentage-of-total surcharge) matches what
   it showed pre-refactor.
2. Complete an order end to end and check the resulting row in `oc_order`
   — `total` should match the total shown throughout checkout (this is
   the specific line `docs/upstream-diff.md`'s `confirm.php` entry
   describes fixing from a latent bug this refactor would otherwise have
   exposed — worth confirming it actually landed correctly).
3. Request `index.php?route=mintcart/checkout/totals` (GET, logged-in
   session with items in the cart) and confirm a JSON `{lines: [...],
   total: "..."}` response — `lines` should list the same total rows
   (Sub-Total, Shipping if a method is selected, Tax if applicable, Total)
   in the same order the cart/checkout page already shows them.
4. Repeat step 3 with **no** shipping method selected yet in session —
   confirm the `shipping` line is simply absent (matches
   `extension/total/shipping.php`'s own guard), not an error.
## Admin asset-enqueue fix + `product_form.twig`'s Category `McEntityPicker`, never exercised end to end

`docs/developer/admin/asset-enqueue.md` has the full design writeup. No
live MySQL/admin session/browser in this environment, so none of this was
exercised. On a real install:

1. Grant `mintcart/entity` **Access** to your user group if not already
   done (same permission the category/manufacturer/product backends
   already need).
2. Go to **Catalog → Products → Edit** (or **Add**) any product, open the
   **Links** tab.
3. Open the page's view-source / devtools and confirm
   `assets/dist/css/components.min.css` and `assets/dist/js/admin.min.js`
   are both linked in `<head>`, and both load with a 200 (not 404) — this
   confirms `HTTP_CATALOG`-relative resolution is correct for your actual
   deployment layout.
4. Confirm the existing Category autocomplete input + well still work
   exactly as before (type a few letters, pick a suggestion, confirm it
   adds a removable tag) — this change must not have altered that at all.
5. Confirm a new "Browse categories" button appears below the well, styled
   as an `mc-button` (not unstyled/broken — this is the actual visual
   proof the CSS bundle loaded and didn't break anything already on the
   page: check the rest of the Bootstrap-3-based form around it still
   looks completely normal, particularly `.container`/`.row`/`.btn`/
   `.form-control`/`.panel` elsewhere on the same page).
6. Click it — confirm a search box + category list appears, paginates via
   Previous/Next, and that typing filters results (debounced, ~250ms).
7. Select two or three categories, click "Add selected" — confirm the
   panel closes, the well now shows those categories as new rows (in
   addition to whatever the autocomplete already added), and each has a
   working "remove" (minus-circle icon) affordance identical to the
   autocomplete-added ones.
8. Re-open the picker and select a category already in the well — confirm
   it does not create a duplicate row (the row is replaced in place, same
   `id`).
9. Save the product — confirm every category from both the old
   autocomplete and the new picker ends up saved
   (`product_to_category`), and re-opening the edit form shows all of
   them pre-populated in the well as before (this part of `getForm()`/
   `product_categories` was not touched by this change).
10. Confirm labels on the new UI (button text, search placeholder, empty-
    state text, Previous/Next/"Add selected") render as real text, not
    blank — see `docs/upstream-diff.md`'s note on this trimmed
    repository's pre-existing `entry_category`-and-friends gap. If they
    render blank on your install too, that's the pre-existing gap, not
    something new; if your install's `product.php` already has the full
    `$data['entry_xxx'] = ...` block (a real, un-trimmed OpenCart 3
    install would), they should render correctly with no further change
    needed.

## `product_form.twig`'s Manufacturer `McEntityPicker`, never exercised end to end

Same asset-enqueue mechanism as the Category picker above (already loaded
on this same form — no separate enqueue to verify), but **single-select**
instead of multi-select. On a real install:

1. Go to **Catalog → Products → Edit** (or **Add**) any product, open the
   **Links** tab (same tab as Category/Manufacturer today).
2. Confirm the existing Manufacturer autocomplete input still works
   exactly as before (type a few letters, pick a suggestion, confirm the
   text field and hidden `manufacturer_id` both update) — this change
   must not have altered that at all.
3. Confirm a new "Browse manufacturers" button appears below the
   Manufacturer input, styled as an `mc-button`.
4. Click it — confirm a search box + manufacturer list appears (with
   thumbnails for manufacturers that have a logo image, `null`/no image
   otherwise), paginates via Previous/Next, and that typing filters
   results (debounced, ~250ms).
5. Select a manufacturer, click "Select" — confirm the panel closes and
   the Manufacturer text input + hidden `manufacturer_id` field are both
   overwritten with the picked manufacturer (not appended — there is no
   well for this field, unlike Category).
6. Select a different manufacturer from the picker afterward — confirm it
   replaces the previous selection again (single-select, not additive).
7. Save the product — confirm `manufacturer_id` is saved correctly
   regardless of whether it was set via the old autocomplete or the new
   picker, and re-opening the edit form shows the same manufacturer
   pre-populated in both the text input and the picker's underlying
   state.
8. Confirm labels on the new UI render as real text, not blank — same
   pre-existing "trimmed repository" caveat as the Category picker's own
   step 10 above; not a new gap.

## `product_form.twig`'s Related Products `McEntityPicker`, never exercised end to end

Same asset-enqueue mechanism as the Category/Manufacturer pickers above
(already loaded on this same form — no separate enqueue to verify), and
**multi-select** like Category (not single-select like Manufacturer). On
a real install:

1. Go to **Catalog → Products → Edit** (or **Add**) any product, open the
   **Links** tab.
2. Confirm the existing Related Products autocomplete input still works
   exactly as before (type a few letters, pick a suggestion, confirm it
   appears in the well below with a red minus-circle icon) — this change
   must not have altered that at all.
3. Confirm a new "Browse products" button appears below the Related
   Products well, styled as an `mc-button`.
4. Click it — confirm a search box + product list appears (with
   thumbnails for products that have an image, `null`/no image
   otherwise), paginates via Previous/Next, and that typing filters
   results (debounced, ~250ms). Unlike Category/Manufacturer's PHP-side
   paging, this list pages at the SQL level — confirm page 2 shows the
   *next* set of products, not a repeat or a skip.
5. Select two or three products (multi-select, same interaction as the
   Category picker), click "Add selected" — confirm the panel closes and
   every selected product appears in the well below (`#product-related`),
   each with its own hidden `product_related[]` input, without disturbing
   any product already added via the old autocomplete.
6. Re-open the picker and select a product already present in the well —
   confirm it does not create a duplicate row (the existing row is
   replaced, same de-dupe behavior the Category picker's own step
   verifies).
7. Remove a related product via the well's minus-circle icon (either one
   added by the picker or by the old autocomplete) — confirm removal
   works identically regardless of which UI added it.
8. Save the product — confirm every `product_related[]` value is saved
   correctly regardless of whether it was added via the old autocomplete
   or the new picker, and re-opening the edit form shows the same
   products pre-populated in the well.
9. Confirm labels on the new UI render as real text, not blank — same
   pre-existing "trimmed repository" caveat as the Category picker's own
   step 10 above; not a new gap.

## `product_form.twig`'s Option `McEntityPicker`, never exercised end to end

Same asset-enqueue mechanism as the Category/Manufacturer/Related pickers
above (already loaded on this same form — no separate enqueue to
verify), and **single-select** like Manufacturer (confirming a pick adds
one new option tab, it does not append into a well). On a real install:

1. Go to **Catalog → Products → Edit** (or **Add**) any product, open the
   **Option** tab.
2. Confirm the existing Option autocomplete input (`#input-option`) still
   works exactly as before for every option type (type a few letters,
   pick a suggestion, confirm a new tab-pane opens with the right
   type-specific fields — a plain value field for `text`/`textarea`/
   `file`/`date`/`time`/`datetime`, a value table + "add value" row for
   `select`/`radio`/`checkbox`/`image`) — this change must not have
   altered any of that (the handler body itself was only moved into a
   named function, not rewritten).
3. Confirm a new "Browse options" button appears below the option pills
   list, styled as an `mc-button`.
4. Click it — confirm a search box + option list appears, paginates via
   Previous/Next, and that typing filters results (debounced, ~250ms).
5. Select a **text**-type option, click "Add option" — confirm the panel
   closes and a new tab opens with a plain value `<input>`, identical in
   shape to what the autocomplete produces for the same option.
6. Select a **select**/**radio**/**checkbox**/**image**-type option via
   the picker — confirm the new tab's value table's "add value" `<select>`
   is pre-populated with that option's real values (fetched via the new
   per-row `option_value` lookup), and that adding a value row via the
   `+` button works exactly as it does for a tab opened through the old
   autocomplete.
7. Add one option via the old autocomplete and a second via the new
   picker in the same edit session — confirm both tabs coexist correctly
   (no tab-index collision — `option_row` is shared and incremented by
   both paths) and both can be removed independently via their tab's
   minus-circle icon.
8. Save the product — confirm every `product_option[]` entry is saved
   correctly regardless of whether its tab was opened via the old
   autocomplete or the new picker, and re-opening the edit form shows all
   of them pre-populated as before (`getForm()`'s existing
   `product_options` handling was not touched by this change).
9. Confirm labels on the new UI render as real text, not blank — same
   pre-existing "trimmed repository" caveat as the Category picker's own
   step 10 above; not a new gap.

## Phase 18 — System route URL aliases, never exercised end to end

`ControllerMintcartSeoSystemPages` is `php -l` clean and its pure
whitelist (`MintCart\Seo\SystemRoutes`) is unit-tested, but no live
MySQL/admin session in this environment means the form was never actually
submitted, and the `ControllerStartupSeoUrl::rewrite()` core change was
never exercised against a real request. On a real install
(`config_theme = mint_default` not required — this feature is
theme-independent):

1. Grant `mintcart/seo/system_pages` **Access** + **Modify** to your user
   group under **System → Users → User Groups**, then confirm a new
   "System Pages" entry appears in the left nav under **Design**, next to
   "SEO URL".
2. Open **Design → System Pages** and confirm all twelve rows render with
   their correct default route shown (`route=checkout/cart`, etc.) and a
   blank alias field (assuming a fresh install with no prior `seo_url`
   rows for these routes).
3. Set the "Shopping Cart" row's alias to `cart` and Save. Confirm the
   success message, and that reloading the screen shows `cart` still
   filled in (round-trips through `seo_url`, not lost).
4. Visit `/cart` directly (no `index.php?route=...`) and confirm it
   resolves to the shopping cart page — this direction already worked
   before this change (`ControllerStartupSeoUrl::index()`'s pre-existing
   generic decode branch), so a failure here indicates a decode-side
   regression, not this change.
5. **The part this change actually adds:** find a link the storefront
   itself generates to `route=checkout/cart` (e.g. the header's cart
   icon/link) and confirm its `href` is now `/cart` rather than
   `index.php?route=checkout/cart`.
6. Attempt to save two different rows (e.g. "Shopping Cart" and
   "Checkout") with the same alias. Confirm both show a validation error
   and neither saves.
7. Set the "Checkout" row's alias to a value already used by an unrelated
   `seo_url` row created via the stock **Design → SEO URL** screen (e.g.
   a category's own keyword). Confirm a validation error and nothing
   saves — the store-scoped `getSeoUrlsByKeyword()` collision check should
   catch it regardless of which screen created the conflicting row.
8. Clear the "Shopping Cart" alias (blank the field) and Save. Confirm the
   corresponding `seo_url` row is deleted (visible in **Design → SEO
   URL**'s own list) and `/cart` no longer resolves (falls back to
   `index.php?route=checkout/cart`).
## Phase 14 — `Business → Delivery`/`Payments` facades, never exercised end to end

`ControllerMintcartBusinessDelivery`/`...Payments` are `php -l` clean and
reuse already-existing `ModelSettingExtension`/`ModelSettingSetting`
calls, but no live MySQL/admin session in this environment means neither
screen was ever actually loaded. On a real install, with at least one
shipping extension and one payment extension installed
(`Extensions → Shipping Methods`/`Payment Methods`):

1. Grant `mintcart/business/delivery` and `mintcart/business/payments`
   **Access** + **Modify** to your user group under **System → Users →
   User Groups**, then confirm a new "Business" menu group with
   "Delivery"/"Payments" entries appears in the left nav.
2. Open **Business → Delivery** and confirm it lists every installed
   shipping method with its real name and current enabled/disabled state
   (matching what `Extensions → Shipping Methods` itself shows).
3. Click the toggle button on one method, confirm the redirect lands back
   on the same screen with a success message and the status flipped, then
   open that method's own settings form (the "Configure"/pencil icon) and
   confirm every other setting (cost, tax class, geo zone, ...) is
   completely unchanged — the entire point of merge-then-save instead of
   a bare status write.
4. Repeat steps 2-3 for **Business → Payments** with an installed payment
   extension.
5. Confirm `mintcart/readiness`'s `shipping`/`payment` signals (Phase 14,
   `docs/developer/onboarding/store-readiness.md`) flip in lockstep with
   the toggle here — both read the exact same `<type>_<code>_status`
   setting keys.
6. Confirm the "Manage" link on each screen opens the real, unmodified
   `Extensions → Shipping Methods`/`Payment Methods` screen.
## Phase 6/8 — Mint Default product detail page, never rendered live

`mint_default/template/product/product.twig` is new this run; the
controller wiring is `php -l` clean and reuses already-tested classes, but
no live MySQL/`vendor/twig/twig` session in this environment means the
page was never actually rendered. On a `config_theme=mint_default` install,
with at least one product that has: a secondary image, a special price, a
required select-type option, a manufacturer, and at least one related
product configured:

1. Load the product page and confirm the gallery's main image + thumbnail
   strip render, clicking a thumbnail swaps the main image without a page
   reload, and the thumbnail links still work (right-click → open image)
   even if JS is disabled.
2. Confirm price/special/tax render via `mc-price`, stock/manufacturer/
   model render, and the rating stars render only when the product has
   approved reviews (same pre-existing gap noted for Phase 8's own rating
   entry — no demo product has a non-zero rating).
3. Select the required option and click Add to Cart — confirm it succeeds
   (toast, `mc:cart:updated`, header cart count updates); then reload and
   click Add to Cart **without** selecting the option — confirm it
   redirects back to the same product page (the existing "missing required
   option" fallback), not a silent failure.
4. Confirm the Description/Specification/Reviews tabs switch panels
   correctly (`mc-tabs`, keyboard arrow-key navigable) and that
   Specification only appears when the product actually has attributes.
5. Confirm the Related Products grid renders `mc-product-card` markup
   identical in shape to the same product's appearance on a category page.
6. Switch to the legacy `default` theme and confirm the product page is
   pixel-identical to its pre-this-change appearance (this increment must
   not have changed `$data['products']`/any existing key that template
   reads).
7. Switch to `test_theme` and confirm it falls back to `mint_default`'s
   `product.twig` (it has no override of its own) while still rendering
   `test_theme`'s own distinct `product-card.twig` DOM in the Related
   Products grid — the same per-component-name fallback independence
   `component-renderer-test.php` already proves at the unit level.
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

## Phase 12 — `mintcart/entity/option` endpoint, wired into `product_form.twig` (see the dedicated section above), raw endpoint response never independently checked

The live UI (see "`product_form.twig`'s Option `McEntityPicker`" above)
exercises this endpoint indirectly. This section checks the raw JSON
shape directly. On a real install, with at least one `select`/`radio`/
`checkbox`/`image`-type option and one `text`-type option configured
(`Catalog → Options`):

1. Grant `mintcart/entity` **Access** if not already done for the
   category/manufacturer endpoints above (same permission covers all
   routes).
2. Request `index.php?route=mintcart/entity/option&user_token=<your
   token>&filter_name=&page=1&limit=20` and confirm a JSON
   `{"items": [...], "total": N}` response with real option names,
   `image` always `null`, and a `type` matching each option's real type
   (`Catalog → Options` list).
3. Confirm `filter_name` set to the *start* of one option's name matches
   it, but a substring from the *middle* of that name does not (prefix
   match, same as manufacturer, not category's substring match).
4. For a `select`/`radio`/`checkbox`/`image`-type option in the response,
   confirm its item has an `option_value` array with one entry per real
   `oc_option_value` row (`option_value_id`, `name`), matching what
   `Catalog → Options → Edit` shows for that option.
5. For a `text`/`textarea`/`file`/`date`/`time`/`datetime`-type option,
   confirm its `option_value` is an empty array — no `getOptionValues()`
   call should run for these types.

## Phase 12 — `mintcart/entity/customer` endpoint, never hit end to end

Same no-MySQL/no-live-request gap as category/manufacturer above, plus a
check unique to this backend: it's the first entity picker to page at the
SQL level rather than in PHP, so pagination itself needs a real database to
confirm.

1. Grant `mintcart/entity` **Access** if not already done for the other two
   endpoints above (same permission covers all three routes).
2. With at least 25 customers on the store, request `index.php?route=mintcart/entity/customer&user_token=<your
   token>&filter_name=&page=1&limit=20` and confirm a JSON `{"items": [...
   20 items ...], "total": N}` response where `N` is the real total
   customer count (not 20).
3. Request `page=2` with the same `limit=20` and confirm it returns the
   *next* 20 customers (rows 21+), not a repeat of page 1 and not an empty
   set — this is the SQL-paging-specific check `ProductPickerProvider`'s
   own manual-verification entry called out; category/manufacturer's
   PHP-side slicing never needed it.
4. Set `filter_name` to part of one customer's full name (`firstname
   lastname`) and confirm both the returned `items` and the `total` reflect
   only matching customers (not the store-wide total) — `total` here comes
   from a real filtered `COUNT(*)`, unlike category/manufacturer where
   `total` is `count()` of an unfiltered PHP array.
5. Confirm every returned item's `image` is `null` (customers have no
   image concept in OpenCart).

## Phase 14 — Store Readiness dashboard widget, never installed end to end

`ControllerExtensionDashboardMintcartReadiness` is `php -l` clean and
reuses already-tested `StoreReadiness`/`ModelMintcartReadiness`, but no
live MySQL/admin session in this environment means it was never actually
installed or rendered. On a real install:

1. Go to **Extensions → Extensions**, filter by type **Dashboard**, find
   "Store Readiness", click Install.
2. Grant the auto-discovered `extension/dashboard/mintcart_readiness`
   permission (Access + Modify) to your user group under **System → Users
   → User Groups**.
3. Open its settings screen (the pencil/edit icon next to it in the
   Dashboard extension list) and confirm the Width/Status/Sort Order form
   saves and reloads correctly (same form shape as the stock "People
   Online"/"Recent Activity" widgets).
4. Set Status to Enabled, go to the Dashboard, and confirm the tile
   renders: a progress bar matching `mintcart/readiness`'s own `percent`,
   a checklist with a check/circle icon per item, and a "ready for a test
   order" / "not ready yet" label matching that same endpoint's
   `ready_for_test_order` value.
5. Change a real signal (e.g. enable a payment extension) and confirm the
   tile's percent/checklist/ready-for-test-order state updates on the next
   dashboard load — it should always match `mintcart/readiness`'s live
   JSON response exactly, since both read the same computation.
6. Click the tile's "Continue setup..." link and confirm it lands on
   `mintcart/wizard`.

## Phase 14 — Business → Contacts facade, never exercised end to end

`ControllerMintcartBusinessContacts` is `php -l` clean and reuses the
already-tested `MintCart\Onboarding\SocialProfileLinks` helper, but no
live MySQL/admin session in this environment means the form was never
actually submitted. On a real install:

1. Grant `mintcart/business/contacts` **Access** + **Modify** to your user
   group under **System → Users → User Groups**, then confirm a new
   "Business" menu group with a "Contacts" entry appears in the left nav.
2. Open **Business → Contacts** and confirm the Telephone/E-Mail/Address
   fields are pre-filled from the store's existing `config_*` settings
   (e.g. whatever the Setup Wizard's Store step already saved), and the
   four social fields are pre-filled from whatever the Wizard's Contacts
   step saved (blank if that step was never run).
3. Submit with a deliberately malformed email and a `javascript:` social
   URL — confirm both show a validation error and nothing saves (reload
   the page and confirm the bad values are gone, not persisted).
4. Submit valid values for all fields, confirm the success message, reload
   the page, and confirm every field re-populates from storage.
5. Confirm the storefront footer (`mint_default` theme) reflects the new
   social links, and that `mintcart/readiness`'s `contacts` signal / the
   Setup Wizard's Store/Contacts steps show the same telephone/email/address
   values — all four should be reading the exact same `config`/
   `mintcart_contacts` storage, not a second copy.

## Phase 15 — `mc-search-box` component, never exercised in a browser

`assets/src/js/components/search-box.js` is bundle-verified (present in
`catalog.min.js`, absent from `admin.min.js`) and, as of 2026-09-19, is
live in both `mint_default/template/mint/components/header.twig` (the
`mc-header__widgets` slot, replacing the legacy `widgets.search` fragment)
and `test_theme/template/mint/components/header.twig` (next to the
branding link) — see `docs/developer/components/search-box.md` "Wired
into `mint_default`'s and `test_theme`'s header". Still never exercised in
an actual browser (no MySQL/browser session in this environment). On a
`config_theme=mint_default` (and separately, `config_theme=test_theme`)
install:

1. Type a 1-character query and confirm no network request fires (open the
   browser's network tab) and the dropdown stays closed.
2. Type a 2+ character query matching at least one real product and
   confirm the dropdown opens with image/name/price per result.
3. Type quickly (e.g. paste then keep typing) and confirm only the final
   query's response is ever rendered — no flicker back to an earlier,
   slower response (the `AbortController` cancellation this component
   relies on).
4. From the input, press ArrowDown and confirm focus moves into the first
   result; ArrowDown/ArrowUp move between results; ArrowUp from the first
   result returns focus to the input; Enter navigates to the focused
   result's product page; Escape closes the dropdown and returns focus to
   the input.
5. Search for a term matching more than 8 products (the server's
   `SUGGEST_LIMIT`) and confirm a "View all results" link appears and
   navigates to `product/search`'s full results page with the same query;
   search for a term matching 8 or fewer and confirm it does not appear.
6. Click outside the dropdown and confirm it closes without clearing the
   typed query.
7. Confirm the header no longer renders the legacy jQuery-autocomplete
   search box (no `#search` element, no `common/search` fragment in the
   page source) on `mint_default` — only the new `.mc-search-box`. Confirm
   the network tab shows `components.min.css` (not `index.min.css`)
   loading with the `mc-search-box` rules actually present (this was
   broken before the `assets/src/css/components.css` import fix landed —
   worth double-checking the box is visually styled, not just
   functional).
8. Switch `config_theme` to `test_theme` and repeat steps 1-2 against its
   own `.mc-search-box` (different placement — next to the branding link)
   to confirm both themes' overrides work independently.

## Phase 18 — Schema.org JSON-LD, never validated against a real page

`SchemaOrgBuilder` is unit-tested in isolation (16 scenarios), and its
output shape was checked against the
[Schema.org Organization](https://schema.org/Organization)/
[BreadcrumbList](https://schema.org/BreadcrumbList) specs by hand, but
never rendered on a real page or run through an actual validator. On a
`config_theme=mint_default` install:

1. Load the homepage (or any page — the header wraps every page) and
   confirm a `<script type="application/ld+json">` block with
   `"@type": "Organization"` appears in `<head>`, with `name`/`url`
   matching **Settings → Store**, and `logo`/`contactPoint`/`address`
   present only when a logo/telephone/address is actually configured.
2. Load a category page, a search-results page, a manufacturer page
   (`product/manufacturer/info`), and a specials page (`product/special`);
   confirm a second `<script type="application/ld+json">` block with
   `"@type": "BreadcrumbList"` appears on each, `itemListElement` matching
   the visible breadcrumb trail exactly (same labels, same order, position
   starting at 1). (2026-09-17: manufacturer/special wiring is new this
   run — not yet rendered on a real page.)
3. Paste both blocks into
   [Google's Rich Results Test](https://search.google.com/test/rich-results)
   (or the [Schema.org validator](https://validator.schema.org/)) and
   confirm no errors.
4. Confirm neither block appears on the legacy `default` theme (the
   `ActiveTheme::isMintAware()` gate should make this a no-op there, same
   as every other Mint-only controller addition).
5. Load a product page on `mint_default`; confirm a
   `<script type="application/ld+json">` block with `"@type": "Product"`
   appears right after `{{ header }}`, with `name`/`url`/`image` matching
   the visible page, `offers.price` matching the visible price (or the
   whole `offers` key absent when pricing is hidden from a logged-out
   customer via `config_customer_price`), `offers.availability` matching
   the visible stock state, and `aggregateRating` present only when the
   product actually has reviews (absent for a product with zero reviews,
   even if it has a computed `rating` value). Paste it into the same
   Rich Results Test / validator as step 3 and confirm no errors. Confirm
   it does **not** appear on the legacy `default` theme's product page.
6. `manufacturer.php`/`special.php` `BreadcrumbList` wiring has an open,
   unmerged PR as of this run — nothing to verify live here until it
   merges (see that PR's own description for its verification steps).
5. `SchemaOrgBuilder::product()` has no live consumer yet — `mint_default`
   does have `template/product/product.twig` (Phase 6/8 wired the product
   detail page), but nothing on it calls `SchemaOrgBuilder::product()` yet
   — nothing to verify live until that wiring lands.

## Phase 18 — `RobotsBuilder` meta tag, never validated against a real page

`RobotsBuilder` is unit-tested in isolation (76 assertions), but its
`ControllerCommonHeader` wiring (2026-09-15, `$hasResults` registry bridge
added in a later run) was never rendered on a real page. On a
`config_theme=mint_default` install:

1. Load the homepage, a category page, and a product page; confirm
   `<meta name="robots" content="index, follow">` appears in `<head>`.
2. Load `checkout/cart`, `checkout/checkout` (add an item to the cart
   first), and — while logged in — `account/account`, `account/order`,
   `account/wishlist`; confirm each instead renders
   `<meta name="robots" content="noindex, follow">`.
3. Load `product/search` with a query that matches nothing (e.g. a random
   string); confirm it now renders `content="noindex, follow"` — the
   `ControllerProductSearch` → `ControllerCommonHeader` registry bridge
   (`RobotsBuilder::HAS_RESULTS_REGISTRY_KEY`, see
   `docs/developer/seo/robots.md`) should have closed this gap.
4. Load `product/search` with a query that matches at least one product;
   confirm it still renders `content="index, follow"`.
5. Confirm the tag does **not** appear at all on the legacy `default`
   theme (this wiring is not gated by `isMintAware()`, but `default`'s own
   `common/header.twig` never reads `$data['mc_robots_content']`, so it
   should render unchanged).

## Phase 16 — Bulk Editor (`catalog/product.bulkupdate`), never exercised end to end

`ModelCatalogProduct::updateProductField()`/`ControllerCatalogProduct::bulkUpdate()`
are `php -l` clean and `BulkProductUpdateValidator` is unit-tested in
isolation, but no live MySQL/admin session in this environment means the
form was never actually submitted. On a real install, with at least three
products, at least one carrying non-default descriptions/options/discounts/
specials:

1. Go to **Catalog → Products**, select two or three products via their
   row checkboxes.
2. Choose "Quantity" in the new bulk-update field select, type a number,
   click the new toolbar button (a checkmark/square icon next to
   Copy/Delete), confirm the dialog, and confirm the redirect lands back
   on the same filtered/sorted/paged list with a success message and the
   selected products' Quantity column updated to the new value.
3. Repeat for "Status" (choose "Enabled"/"Disabled" from the value
   `<select>` that now appears in place of the number input — see step 8),
   "Price" (a decimal value),
   "Manufacturer" (an existing `manufacturer_id`, and separately `0` for
   "no manufacturer"), and "Stock Status" (an existing `stock_status_id`
   from **Settings → Stock Statuses**, e.g. the ID shown for "In Stock")
   and confirm each updates only that column — for "Stock Status" in
   particular, open the product's edit form afterwards and confirm the
   **Data** tab's Stock Status dropdown now shows the expected label, not
   just that the raw column changed.
4. For the product with existing descriptions/options/discounts/specials,
   open its edit form after a bulk update and confirm every one of those
   is completely unchanged — the entire reason `updateProductField()`
   exists instead of reusing `editProduct()` (see
   `docs/developer/catalog/bulk-editor.md` "Why a new model method").
5. Submit the bulk-update button with no products selected, and again
   with a deliberately invalid value (e.g. `-5` for quantity, `abc` for
   price, `-1` for manufacturer, `-1` for stock status) and confirm each
   shows the appropriate warning rather than silently doing nothing or
   throwing a fatal error.
6. Confirm the legacy Delete/Copy buttons still behave exactly as before
   (this increment must not have changed their behavior).
7. Choose "Special Price" and type a number (e.g. `9.99`), submit, and
   confirm: (a) the selected products' **Data** tab now shows a Special
   row at that price for the default customer group with no date range,
   and (b) for a product that already had one or more existing Special
   rows (set via the normal edit form, especially one with a non-default
   customer group or a date range), those rows are fully **replaced** by
   the single new flat-price row, not appended to. Then bulk-update the
   same products again with "Special Price" left **blank** and confirm
   the Special row(s) are removed entirely (product reverts to its normal
   Price). Also submit "Special Price" with an invalid value (e.g. `-1`)
   and confirm the same rejection-warning behavior as the other numeric
   fields.
8. Change the bulk-update field `<select>` between "Status" and every
   other option and confirm the value control swaps correctly each time:
   an Enabled/Disabled `<select>` shows (and the number input hides) only
   when "Status" is selected; a plain number input shows for every other
   field, accepting only whole numbers for Quantity/Manufacturer/Stock
   Status (the browser's own spinner/step behavior) and decimals for
   Price/Special Price. Confirm via the browser's dev tools that whichever
   control is hidden is also `disabled` (so only one `bulk_value` is ever
   sent), and that switching the field back and forth several times before
   submitting still posts the value currently visible, not a stale one
   from an earlier selection.

## Phase 14 — `menu-mintcart-business` merge fix, never seen rendered live

Was two separate `$data['menus'][]` entries in
`upload/admin/controller/common/column_left.php`, both with
`'id' => 'menu-mintcart-business'` (one from the earlier Business →
Contacts increment, a second, independent one added by PR #65's Business →
Delivery/Payments — no textual git conflict, so both merged cleanly and
CI stayed green, but they were never reconciled into one group). Fixed in
code: merged into a single `$business = array();` block (Contacts first,
then Delivery, then Payments, each still gated by its own
`hasPermission()` check) producing one `$data['menus'][]` entry; the
duplicate `text_mintcart_business` language-key definition in
`upload/admin/language/en-gb/common/column_left.php` was also
de-duplicated. `php -l` clean on both files; no live admin session in
this environment to confirm the rendered sidebar. On a real install,
confirm:

1. Log into admin and look at the left sidebar: **"Business" appears as
   exactly one collapsible menu group**, containing "Contacts",
   "Delivery", and "Payments" together, in that order.
2. Toggle each of the three permissions independently (Contacts only,
   Delivery only, Payments only, all three, none) and confirm each link
   shows/hides correctly and the group itself disappears entirely when
   all three are denied.

## Phase 18 — `MintCart\Seo\MetaBuilder` live wiring (MC-0015), never exercised end to end

`category.php`/`home.php` are `php -l` clean and `MetaBuilder` itself is
unit-tested in isolation, but no live MySQL/Twig session in this
environment means neither controller's rendered `<title>`/meta tags were
ever actually inspected. On a real install:

1. Create a category and leave its "Meta Tag Title" and "Meta Tag
   Description" fields blank on the Data tab. Visit that category page
   and view source: confirm `<title>{category name}</title>` in the shipped
   themes. The description stays blank: their header templates omit
   `<meta name="description">` when it is empty. A custom theme may render
   an empty tag; neither case should synthesize the title as a description.
2. Edit that same category and fill in a real "Meta Tag Title"/
   "Meta Tag Description". Reload the page and confirm those exact values
   now appear (with surrounding whitespace trimmed) — the title fallback must
   only apply when the field is genuinely blank.
3. On a fresh install (or after clearing Settings → Store's "Meta Tag
   Title"/"Meta Tag Description" fields), visit the homepage and view
   source: confirm `<title>` contains the store name
   (Settings → Store's "Store Name") instead of being empty. The description
   remains blank, and the shipped themes omit its meta tag.
4. Fill in Settings → Store's meta fields, reload the homepage, and
   confirm those values now appear instead of the store-name fallback.
5. Confirm `product/product.php`'s product detail pages are **unaffected**
   by this change (still whatever behavior they had before — this
   increment deliberately did not touch `product.php`, see
   `docs/developer/seo/meta-builder.md`'s "What's deliberately not done
   yet").

## Phase 18 — `CanonicalUrlBuilder` live wiring, never exercised end to end

`category.php`/`manufacturer.php`/`search.php`/`special.php` now all call
`MintCart\Seo\CanonicalUrlBuilder::build()` instead of their own inline
canonical/prev/next arithmetic (a behavior-preserving refactor, not a new
feature) — `php -l` clean, the builder's own 8-scenario unit test already
covers the arithmetic, and the full `tests/mintcart/` suite passes, but no
live MySQL/browser session in this environment means the four listing
pages were never actually loaded to confirm the rendered `<link>` tags are
unchanged. On a real install with enough products to produce at least 3
pages of results (set the theme's product limit low, e.g. 4, if needed):

1. Load page 1 of a category, a manufacturer, a search-results page, and
   the specials page. Confirm each has exactly one
   `<link rel="canonical" href="...">` with **no** `page` parameter.
2. Load page 2 of each: confirm `canonical` now includes `page=2`, and a
   `<link rel="prev" href="...">` appears **without** a `page` parameter
   (pointing bare at page 1).
3. Load page 3 (or later) of each: confirm `prev` now includes `page=2`
   (or one less than the current page), and a `<link rel="next" ...>`
   appears pointing to the next page.
4. Load the last page of results: confirm `next` is absent.
5. Confirm `product/special`'s canonical/prev/next links are all `https://`
   (or match whatever `config_ssl` resolves to) — the only one of the four
   that forces `secure = true`.
6. Confirm this is unaffected by which theme is active (`default` or
   `mint_default`) — canonical links are not gated by Mint-awareness.

## Phase 18 — Google Sitemap wired through `MintCart\Seo\SitemapBuilder`, never exercised end to end

`ControllerExtensionFeedGoogleSitemap::index()` now builds a plain
`{loc, lastmod, changefreq, priority, images}` array per product/category/
manufacturer/information row and delegates the actual XML string assembly
to `MintCart\Seo\SitemapBuilder::urlset()`, instead of concatenating
`<url>...</url>` strings by hand — `php -l` clean, `SitemapBuilder`'s own
20-scenario unit test covers the XML-assembly rules (including the new
`images` parameter), and the full `tests/mintcart/` suite passes, but no
live MySQL/OpenCart bootstrap in this environment means the feed's actual
output was never fetched and compared. On a real install with existing
products (at least one with a product image), categories, manufacturers,
and an information page:

1. Install/enable the "Google Sitemap" extension
   (`Marketing → Marketing → Feeds → Google Sitemap`) if not already
   enabled, and note its feed URL.
2. Load that URL both **before** (checkout a commit prior to this change)
   and **after** this change, and diff the two XML documents. They should
   be identical except: (a) whitespace/indentation (the builder uses tab
   indentation, the original used two-space), and (b) any product/category/
   manufacturer/information URL that happens to contain an `&` or other
   XML-special character in its query string now has that character
   correctly escaped in `<loc>`/`<image:loc>` (a correctness fix — the
   original never escaped those two tags, only `<image:caption>`/
   `<image:title>`).
3. Confirm the response's `Content-Type: application/xml` header is
   unchanged.
4. Confirm a product **without** an image still produces a `<url>` block
   with no `<image:image>` child (unaffected by the new `images` support).
5. Confirm the `xmlns:image` namespace attribute is present on `<urlset>`
   whenever at least one product has an image, and validate the whole
   document against an XML parser (e.g. `xmllint --noout`) to confirm it
   is well-formed.
6. Validate the feed against Google's sitemap requirements (e.g. via
   Google Search Console's sitemap report) to confirm no regression in
   acceptance.

## Phase 6 — `mint_default`'s new `manufacturer_list.twig` + MC-0017, never rendered live

`php -l` clean on the modified `manufacturer.php`, the new template's Twig
tag counts checked balanced (4/4, 4/4) — but no live MySQL/Twig session in
this environment means neither was ever actually rendered in a browser.
On a real install with `mint_default` active:

1. Go to **Catalog → Manufacturers** in admin and confirm at least two
   manufacturers exist, with names starting with different letters (and,
   if possible, one starting with a digit).
2. Visit the storefront's `product/manufacturer` route (the "Brands" link,
   wherever the active theme's header/footer links to it). Confirm:
   - the page has a real `<h1>` (not blank) — this is MC-0017's
     `heading_title` fix;
   - a "Brand Index:" line above the brand list, with one small ghost
     button per letter that has at least one manufacturer (not blank
     labels) — MC-0017's `text_index` fix;
   - clicking a letter button jumps to that letter's group on the same
     page (`#letter-<LETTER>` anchor);
   - each manufacturer under a letter renders as a real secondary-style
     button linking to that manufacturer's `info()` page, not a bare
     text link — confirm clicking one navigates correctly.
3. Temporarily disable/delete every manufacturer (or test against a fresh
   install with none configured) and reload `product/manufacturer`.
   Confirm a real "no brands to list" message appears (not blank) and a
   working "Continue" button navigates back to the homepage — both are
   MC-0017's `text_no_results`/`button_continue` fixes.
4. Confirm the legacy `default` theme's own `manufacturer_list.twig`
   (untouched by this change) also now shows the same non-blank
   heading/index label/empty-state text on the same store, since
   MC-0017's `$data` fix applies to every theme, not just `mint_default`.
5. Confirm `product/manufacturer/info` (an individual brand's product
   listing, already shipped) is unaffected — this change only touches the
   sibling `index()` method and adds a new template for a different route.
## Phase 19 — Responsive image `width`/`height` + lazy loading, never rendered in a browser

`category.php`/`product.php`/`home.php`'s new `thumb_width`/`thumb_height`/
`image_width`/`image_height` values are simple config-value threading (no
new logic), and `php -l` plus the full `tests/mintcart/` suite (81 files)
pass, and every touched `.twig` file's `if`/`endif`/`for`/`endfor` tag
counts were re-checked balanced — but no live MySQL/Twig session in this
environment means none of this was ever actually rendered and inspected
in a browser. On a real `mint_default`-themed install:

1. Open a category page for a category that has its own image configured
   (`Catalog → Categories → Edit → Data → Image`). Confirm the category
   thumbnail (`img-fluid rounded`, next to the description) has explicit
   `width`/`height` attributes matching
   `Design → Theme` (or `Settings → Store` per‑theme) settings'
   "Category Image" width/height, and that the layout does not visibly
   shift once the image finishes loading (throttle the network in dev
   tools to see the reserved space hold before the image paints).
2. Open a product detail page for a product with at least one additional
   image (`Catalog → Products → Edit → Links/Images` — the "Additional
   Images" tab). Confirm:
   - the main gallery image (`mc-product-gallery__main`) has explicit
     `width`/`height` matching the theme's "Thumb Image" size, and has
     **no** `loading="lazy"` attribute (it should load eagerly — it's the
     page's largest above-the-fold image);
   - every thumbnail in the strip below it (`mc-product-gallery__thumb-image`,
     including the first one that duplicates the main image) has both
     explicit `width`/`height` matching the theme's "Additional Image"
     size **and** `loading="lazy"`;
   - clicking a thumbnail still swaps the main image via the existing
     `data-mc-action="gallery-thumb"` JS exactly as before — this change
     only adds attributes to already-existing `<img>` tags, no markup
     structure or JS behavior changed.
3. Open a homepage that has a Homepage Builder "Text + Image" (or plain
   "Text") block configured with an image. Confirm the block's `<img>` now
   has explicit `width`/`height` (defaulting to 800×600 when the block's
   own settings don't override them — `MintCart\Blocks\TextImageBlockProvider`'s
   defaults) and `loading="lazy"`, matching the Banner Grid/Gallery blocks'
   already-existing images on the same page. Confirm a "Text + Image"
   block with **no** image configured still renders correctly (title/body
   only, no broken `<img>` tag, no layout regression) — this change only
   threads values through when an image exists, per `renderTextImageBlock()`'s
   own `$image === null` short-circuit.
4. Confirm the legacy `default` theme's `category.twig`/`product.twig`
   (neither of which this change touches) render byte-identical to before —
   this increment only ever changes `mint_default`'s own templates plus the
   controllers' `$data` arrays, and `default`'s templates never read the
   new keys.

## Phase 7 — MintCart's own asset bundle enqueued on the storefront, never validated in a browser

`ControllerCommonHeader::index()` now calls
`$this->document->addStyle()`/`addScript()` for
`assets/dist/css/components.min.css`/`assets/dist/js/catalog.min.js`
(`docs/upstream-diff.md`'s `header.php` entry,
`docs/developer/admin/asset-enqueue.md`'s "Storefront:
`ControllerCommonHeader`" section). `php -l`, the full `tests/mintcart/`
suite (84 files) and `npm run build`/`npm run dev` all pass, but this
environment has no MySQL/browser session, so none of the following was
ever actually observed on a rendered page — before this run, it wasn't
even possible to check, since nothing linked any `assets/dist/` file on
the storefront at all. On a real `mint_default`-configured install, with
the release process (or a manual copy, per `.gitignore`'s own comment on
`upload/assets/`) having placed a built `assets/dist/` next to `catalog/`/
`admin/`/`system/` at the site's webroot:

1. Open any storefront page (home, a category, a product). Open the
   browser's Network tab and confirm two new requests succeed with a `200`
   status: `.../assets/dist/css/components.min.css` and
   `.../assets/dist/js/catalog.min.js`. Confirm the existing Bootstrap 3/
   jQuery/Font Awesome/`common.js` requests are all still present and
   unchanged (same file, same order, before the two new ones) — this
   change is additive only.
2. On a category/search/manufacturer/special listing page (any page
   rendering `mc-product-card`), click a product card's "Add to Cart"
   button. Confirm it actually adds the item (a toast/cart update, per
   `docs/developer/commerce/cart-client.md`'s `mc:cart:updated` contract)
   instead of doing nothing — before this change, this button was `<button
   type="button">` with no `<form>` and no JS listening for
   `data-mc-action="add-to-cart"`, so clicking it visibly did nothing at
   all.
3. Click a product card's "Quick Order" button (where shown). Confirm the
   `mc-modal` Quick Order dialog actually opens (`docs/developer/commerce/quick-order-service.md`)
   — again, previously inert with no bundle loaded.
4. Click the heart ("wishlist") icon on a product card. Confirm it toggles
   `aria-pressed` and visibly changes state.
5. Confirm the page still looks and behaves correctly outside `mc-*`
   markup: legacy Bootstrap-3-driven widgets (the stock cart dropdown,
   any third-party module still using jQuery/Bootstrap-3 markup),
   `common.js`-driven behavior, and account/checkout pages all work
   exactly as they did before this change — `components.min.css`
   deliberately excludes Bootstrap 5's own reset/grid/utility layer for
   exactly this reason (see the doc references above), so nothing already
   on the page should visibly shift, shrink, or overlap the way the
   earlier, reverted `index.min.css` attempt did.
6. Confirm a store configured with the legacy `default` theme (not
   `mint_default`) shows **no** new network requests for either file —
   the enqueue is gated behind `isMintAware()`, so a non-Mint theme pays
   nothing.

## Phase 12 — Attribute field's `McEntityPicker`, never exercised end to end

Same underlying gap as the other Entity Picker fields above (no admin
bundle enqueued yet, per `docs/developer/admin/asset-enqueue.md`), plus
this is the first Entity Picker field with *multiple, dynamically added*
rows sharing one picker instance, so it has its own failure modes worth
checking specifically once a bundle is loaded on `catalog/product/form`:

1. Grant `mintcart/entity` **Access** if not already done for the other
   Entity Picker endpoints (same permission covers all seven routes).
2. Open a product's **Attribute** tab. Click "Add Attribute" twice, so two
   empty rows exist. Click the new "Browse" button on the *second* row,
   search for and select an attribute, and confirm it fills the *second*
   row's name/attribute_id fields — not the first row's, and not a third,
   not-yet-added row.
3. Repeat on the first row. Confirm the picker's search box/page/results
   reset (not left over from the previous row's search) and the selection
   lands in the first row this time.
4. Confirm the legacy jQuery UI autocomplete on each row's name `<input>`
   still works unmodified — type a partial attribute name and select it
   from the browser's native autocomplete dropdown; confirm it still fills
   the same fields the picker does, and neither interferes with the other.
5. Remove a row (the red minus button), add a new one, and confirm its
   "Browse" button opens the picker and writes into the *new* row, not a
   stale reference to the removed one's DOM node.
6. Save the product and confirm the attribute (added via the picker) is
   actually persisted — `ControllerCatalogProduct`'s save logic reads the
   same `product_attribute[N][name]`/`[attribute_id]` fields regardless of
   which UI filled them in, so this should need no special-casing.
