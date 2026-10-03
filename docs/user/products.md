# Products

`Store → Products` (`Catalog → Products` in the admin menu today — see
"Known gaps" below) is where you add and manage what your store sells.
MintCart does not replace this screen with something new; it is stock
OpenCart 3's product list and edit form, with a handful of MintCart
additions layered on top where they genuinely save time
(`MINTCART_PRODUCT_VISION.md` §5: MintCart removes decisions you shouldn't
have to make, not screens that already work).

**This guide describes what's actually on this screen today**, including
the MintCart parts and their current limits — see
[`MINTCART_FUNCTIONAL_SCOPE.md`](../MINTCART_FUNCTIONAL_SCOPE.md) §7/§8 for
the fuller Bulk Editor / Entity Picker vision this screen is still growing
into.

## The product list

Route: `catalog/product`. This is OpenCart's own list — search/filter by
name, model, price, quantity and status; sort any column; paginate. Nothing
about the list's own filtering or table is MintCart-specific.

### Bulk update toolbar

Above the list, next to the usual Add/Copy/Delete buttons, is a **bulk
update** toolbar: pick a field, type a value, select the products you want
to change (the row checkboxes), then confirm. It applies one value to every
selected product in a single action — no need to open and re-save each
product individually for something like a store-wide price bump.

Six fields are supported:

| Field | What it writes | Value rules |
|---|---|---|
| Status | Enabled/disabled | `1` (enabled) or `0` (disabled) |
| Quantity | Stock quantity | A whole number, `0` or greater |
| Price | Base price | A number, `0` or greater |
| Manufacturer | `manufacturer_id` | A whole number (an existing manufacturer's ID), or `0` for "no manufacturer" |
| Stock Status | `stock_status_id` | A whole number — an existing ID from `Settings → Stock Statuses` |
| Special Price | Special-price override | Blank (removes any special price) or a number, `0` or greater |

The value field is currently one plain text box shared by all six fields —
typing a value that doesn't fit the field you picked (a letter into
Quantity, for example) is caught and rejected when you submit, not before.
Manufacturer and Stock Status are entered as raw numeric IDs today, not
picked by name from a dropdown.

This only ever changes the one field you picked, for the products you
selected — it never touches any other field on those products (description,
images, SEO, categories, …), and it's a single immediate action with no
undo beyond editing the affected products back by hand or individually.

## The product edit form

Route: `catalog/product.add` / `catalog/product.edit`. Opening a product
(or clicking Add New) gives you OpenCart's usual tabbed form — General,
Data, Links, Attribute, Option, Recurring, Discount, Special, Image,
Reward Points, SEO, Design. Every tab's fields, validation and save
behavior are exactly stock OpenCart's — nothing here has been rewritten.

### Faster ways to pick things

Three fields on this form have gained an additional way to fill them in,
next to (not instead of) the field OpenCart already gives you:

- **Links → Manufacturer** — a "Browse" button opens a searchable,
  paginated list instead of only typing a name into the autocomplete box.
- **Links → Categories** — the same kind of searchable, paginated list,
  letting you check several categories at once rather than adding them one
  at a time.
- **Links → Related Products** — the same multi-select list for picking
  which other products show up as "related" on this product's page.

All three exist alongside OpenCart's original input — if you're used to
typing a manufacturer or category name and picking it from the dropdown
that already appears as you type, that still works exactly as before, and
whichever way you use, saving the product works exactly the same. Two more
fields planned for this same treatment — Attributes and Options — are not
built yet; use their existing OpenCart inputs for now.

Nothing about this picker changes what gets saved: it just offers a
different way to find and select the same category/manufacturer/product
records OpenCart's own fields already expect.

## Known gaps

- The main admin menu still shows this screen under **Catalog**, not the
  business-language **Store** section `MINTCART_PRODUCT_VISION.md` §8
  describes — the underlying screen is ready, the friendlier navigation
  around it isn't built yet.
- The bulk update toolbar's value field is one generic text box for all six
  fields, not a dedicated control per field (a dropdown for Status, a
  number spinner elsewhere) — see the table above for exactly what each
  field expects.
- Bulk-updating a product's categories isn't available yet — the six
  fields above are the only ones the bulk toolbar supports today.
- Bulk actions apply immediately when you confirm, with no progress bar —
  fine for a typical small-store selection, but there's no chunked/resumable
  batch job yet for very large selections (`MINTCART_FUNCTIONAL_SCOPE.md`
  §7's "AJAX batch jobs" is still on the roadmap).
- The Browse pickers above don't yet have a "create new" shortcut (e.g.
  adding a brand-new manufacturer without leaving this screen) —
  `MINTCART_FUNCTIONAL_SCOPE.md` §8 lists this as a future addition.
