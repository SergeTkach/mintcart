# Orders

MintCart does not yet have its own order-management screen — **Sale →
Orders** is stock OpenCart 3's admin order list/view/edit, unmodified.
This guide describes what's there today so you're not left guessing; see
"What MintCart adds on top" below for the one addition that does exist,
and `docs/IMPLEMENTATION_STATUS.md`'s Phase 15 section for what's planned
but not built (a `CheckoutService`, a Mint-native checkout UI, and
eventually order creation through it).

Find it under **Sale → Orders** in the admin menu.

## The order list

Each row shows Order ID, Customer, Status, Total and Date Added. Use the
filter row above the table to narrow by Order ID, Customer name, Order
Status, Total or a date range (Date Added / Date Modified) — click
**Filter** to apply, or any column header to sort.

Row actions:

- **View** (magnifying glass) — opens the order's read-only detail page
  (`sale/order.info`), covered below. This is where you update status,
  add a comment, or print an invoice/shipping list — not Edit.
- **Edit** (pencil) — opens the same five-tab form used for creating a
  manual order (Customer → Products → Payment → Shipping → Totals),
  pre-filled with this order's data. Reserved for correcting an order's
  own fields; changing its status/history happens on the View page
  instead, not here.
- **Delete** (trash) — permanently removes the order. There is no undo
  and no admin-side confirmation beyond the browser's own "are you sure"
  dialog, so double-check the Order ID before deleting.

A **Status** of blank/`0` ("missing orders") means an order was started
at checkout but never actually completed/confirmed — these don't show up
under the default filter; you have to explicitly filter by that status to
see them. Store → Settings' order-processing/complete status lists
(stock OpenCart's own `config_processing_status` / `config_complete_status`
settings) control which statuses count toward the dashboard's "Sales"
figures.

## Viewing an order

The View page (**Sale → Orders → View**) is split into panels:

- **Order details** — customer info, both addresses (payment/shipping),
  IP address, store, and the `#`invoice number once one has been
  generated.
- **Products** — each line item with quantity, unit price, line total,
  and any options/add-ons chosen at checkout.
- **Totals** — the same subtotal/shipping/tax/total breakdown the
  customer saw at checkout.
- **History** — every status change ever recorded for this order,
  oldest first, each with its own timestamp, comment, and whether the
  customer was emailed about it.

Two buttons at the top: **Invoice** (opens a printable invoice in a new
tab) and **Shipping List** (a printable packing/shipping slip) — both
read-only exports, they don't change the order.

### Updating status / adding a comment

At the bottom of the History panel:

1. Choose a new **Order Status** from the dropdown (every status defined
   under **Settings → ⚙ → Order Status**, stock OpenCart, is listed —
   MintCart has not added any status of its own).
2. Optionally check **Notify Customer** to email them about this change
   (using the language's order-status email template) — leave it
   unchecked for an internal-only note.
3. Optionally check **Override** — only needed if an anti-fraud extension
   (e.g. a fraud-screening payment module) is blocking this order from
   changing status; otherwise leave unchecked.
4. Optionally type a **Comment** — shown to the customer if Notify is
   checked, otherwise just an internal record.
5. Click **Add History**.

This is an AJAX action — the page doesn't reload, the new row just
appears at the top of the History table and the comment box clears. It
is intentionally separate from the five-tab order **Edit** form: editing
an order's own data (customer/products/addresses) and recording a status
change are different operations with different risk levels, so they
live on different screens — the same split stock OpenCart itself uses.

## Returns

**Sale → Returns** is a separate, independent OpenCart feature for RMA
(Return Merchandise Authorization) requests — a customer or admin can log
a return against an order, with its own status list (**Settings → ⚙ →
Return Status**) and its own history, following the same
list/view/history pattern as Orders. It is not linked from the Orders
screen itself; reach it directly from the **Sale** menu.

## What MintCart adds on top

Nothing on this screen's PHP/Twig yet, with one related but independent
exception: `Sale → Orders → Add New/Edit`'s Customer field has an
additional **"Browse customers"** button next to the existing
autocomplete input, opening a searchable `McEntityPicker` panel as an
alternative way to pick a customer — see
[`docs/developer/components/entity-picker.md`](../developer/components/entity-picker.md)'s
"Customer backend" section. It only touches the Customer field on the
**Edit/Add New** form; the View page and the History/status-update flow
described above are entirely unmodified stock OpenCart.

## What's not built yet

- A Mint-native checkout (`MintCart\Commerce\CheckoutTotalsService`/
  `ShippingMethodService` exist as preview-only AJAX endpoints a future
  checkout UI would call — see `docs/IMPLEMENTATION_STATUS.md`'s Phase 15
  section) still creates orders through stock OpenCart's own
  `checkout/confirm` → `addOrder()` path. Nothing about order *creation*
  changes yet.
- No MintCart-specific order statuses, tags, or internal notes beyond
  stock OpenCart's own History comments.
- No bulk actions on the order list (status change, export) — only the
  single-order History form above.
- No customer-facing order tracking beyond stock OpenCart's own
  **Account → Order History** page.

None of these are dead ends — they're scoped, named follow-ups in
`docs/IMPLEMENTATION_STATUS.md`'s Phase 15 section, not a permanent
ceiling.

## See also

- [`docs/developer/commerce/checkout-totals-service.md`](../developer/commerce/checkout-totals-service.md),
  [`docs/developer/commerce/shipping-method-service.md`](../developer/commerce/shipping-method-service.md) —
  the normalized preview endpoints a future checkout UI will build on.
- [`docs/developer/components/entity-picker.md`](../developer/components/entity-picker.md) —
  the Customer picker mentioned above, and the other entity types it
  covers.
