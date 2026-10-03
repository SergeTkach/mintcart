# Business → Payments

`Business → Payments` is the same idea as
[`Business → Delivery`](delivery.md), for payment methods instead of
shipping methods: a shortcut for turning a payment method on or off
without opening `Extensions → Payment Methods` and its full settings form
just to flip a status flag (`MINTCART_PRODUCT_VISION.md` §6.1/§8,
`MINTCART_FUNCTIONAL_SCOPE.md` §3).

**This is not a second payment system.** It lists the payment methods
you've already installed through OpenCart's normal `Extensions` screen and
lets you enable/disable each one; installing a *new* payment method, and
every method-specific setting (API keys, test mode, order status on
completion, …), still lives on that extension's own settings screen.

## Reaching it

`Business → Payments` in the main admin menu, once your admin account has
been granted access to `mintcart/business/payments` (the same permission
system as any other admin menu item).

## What you'll see

A table of every payment method you've already installed, each row
showing:

- **Payment method** — the method's own display name.
- **Status** — a green "Enabled" or grey "Disabled" label.
- An **Configure** button (gear icon) — opens that method's own settings
  screen (`Extensions → Payment Methods → <method>`), where API keys, test
  mode, and everything else specific to that method is set.
- An **Enable/Disable** toggle button.

A cog button at the top right ("Manage payment methods") takes you to
`Extensions → Payment Methods` if you need to install a payment method
that isn't in this list yet.

If you haven't installed any payment methods, the table is replaced with
a message and a link straight to `Extensions → Payment Methods`.

## What toggling does — and doesn't do

Clicking Enable/Disable only flips that method's status flag. It does not
touch any of that method's other saved settings (API keys, test mode, …)
— those are read, merged, and written back untouched, so turning Bank
Transfer off temporarily and back on keeps whatever account details you
had configured.

## Known gaps

- **The Enable/Disable button currently doesn't work.** Clicking it
  produces an error instead of toggling the method, because of a routing
  bug in how this screen's toggle action is currently wired — tracked as
  an open fix in `docs/IMPLEMENTATION_STATUS.md`'s Phase 13/Phase 14
  history. Until that lands, use **Configure** to open the method's own
  settings screen and change its **Status** field there instead — that
  path is unaffected.
- This screen only covers payment *methods* already installed. Choosing
  which payment extensions to install in the first place is still done
  through the regular `Extensions → Payment Methods` screen.
