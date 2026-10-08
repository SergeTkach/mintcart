# 009 — Mint checkout: stock route, stock session keys

## Context

Checkout is P0 (`MINTCART_FUNCTIONAL_SCOPE.md` §12) and the last Commerce
Essential still running on stock OpenCart: a six-step jQuery/Bootstrap 3
accordion spread over 13 `checkout/*` controllers. The Mint version must
keep working with every OpenCart shipping/payment/total extension
(Technical Plan §27: "не дублювати payment extensions, shipping extensions,
totals, order models"), keep existing links (`header`, cart, mini-cart,
third-party modules all point at `checkout/checkout`), and add 0 OCMOD.

Payment extensions are the hard constraint. Their catalog controllers and
templates read `session['payment_address']`, `['payment_method']`,
`['order_id']` etc. directly, render their own confirm button (often with
inline jQuery and sometimes their own `<form>`), and redirect to
`checkout/success`.

Product decisions taken with the user on 2026-10-05: same route, one page
with all sections visible, one address for delivery and billing.

## Decision

1. **Route:** keep `checkout/checkout`. `mint_default` overrides only its
   template (`template/checkout/checkout.twig`); the stock
   `ControllerCheckoutCheckout::index()` is unchanged. Themes without the
   override keep the stock accordion.
2. **State lives in the stock session keys.** New endpoints
   `mintcart/checkout/{state,update,confirm}` write exactly the keys stock
   `guest.php`/`shipping_method.php`/`payment_method.php` write. No Mint
   checkout table, no parallel cart/order state.
3. **`update` persists without validating** and recalculates delivery →
   totals → payment on every change (debounced, abortable). A previous
   selection survives while it is still offered; otherwise the first
   available method is selected. Only **`confirm` validates** (same rules
   and language keys as stock `guest.php`).
4. **One order builder.** The ~200 lines that assembled `$order_data`
   inline in `checkout/confirm.php` moved to
   `MintCart\Commerce\CheckoutService::buildOrderData()` (pure) +
   `MintCart\Compatibility\OpenCart3\CheckoutOrderAdapter` (reads the
   request); the payment-methods loop moved from `payment_method.php` to
   `PaymentMethodsAdapter`. Stock and Mint checkout both call them.
5. **Payment step = the extension's own markup.** After `confirm` creates
   the order, the chosen `extension/payment/<code>` output is injected
   outside the checkout `<form>` (extensions may ship their own form) and
   its inline scripts are re-created so they run. jQuery stays on the page
   for those scripts only.

## Consequences

- Any payment/shipping extension that works with stock checkout works here
  without changes; extensions that hook `checkout/guest` or other stock
  step routes via events are bypassed (none of the bundled ones do).
- Like stock, every `confirm` creates a new pending order (status 0);
  editing the form after confirm hides the stale payment step.
- Not built yet (deliberately, follow-up increments): registered-customer
  address book selection, account registration during checkout, custom
  fields, captcha, separate billing address, coupon/voucher entry on the
  page. A theme that needs different checkout DOM overrides the same
  template and talks to the same three endpoints.
