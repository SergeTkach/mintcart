# Business → Delivery

`Business → Delivery` is a shortcut for the everyday shipping task most
stores need — turning a shipping method on or off — without opening
`Extensions → Shipping Methods` and each method's full settings form just
to flip a status flag (`MINTCART_PRODUCT_VISION.md` §6.1/§8,
`MINTCART_FUNCTIONAL_SCOPE.md` §3).

**This is not a second shipping system.** It lists the shipping methods
you've already installed through OpenCart's normal `Extensions` screen and
lets you enable/disable each one; installing a *new* shipping method, and
every method-specific setting (cost, tax class, geo zone, API keys, …),
still lives on that extension's own settings screen.

## Reaching it

`Business → Delivery` in the main admin menu, once your admin account has
been granted access to `mintcart/business/delivery` (the same permission
system as any other admin menu item).

## What you'll see

A table of every shipping method you've already installed, each row
showing:

- **Shipping method** — the method's own display name.
- **Status** — a green "Enabled" or grey "Disabled" label.
- An **Configure** button (gear icon) — opens that method's own settings
  screen (`Extensions → Shipping Methods → <method>`), where cost, tax
  class, geo zone, and everything else specific to that method is set.
- An **Enable/Disable** toggle button.

A cog button at the top right ("Manage shipping methods") takes you to
`Extensions → Shipping Methods` if you need to install a shipping method
that isn't in this list yet.

If you haven't installed any shipping methods, the table is replaced with
a message and a link straight to `Extensions → Shipping Methods`.

## What toggling does — and doesn't do

Clicking Enable/Disable only flips that method's status flag. It does not
touch any of that method's other saved settings (cost, tax class, geo
zone, …) — those are read, merged, and written back untouched, so turning
Flat Rate off for a sale and back on afterwards keeps whatever cost you
had configured.

## Known gaps

- **The Enable/Disable button currently doesn't work.** Clicking it
  produces an error instead of toggling the method, because of a routing
  bug in how this screen's toggle action is currently wired — tracked as
  an open fix in `docs/IMPLEMENTATION_STATUS.md`'s Phase 13/Phase 14
  history. Until that lands, use **Configure** to open the method's own
  settings screen and change its **Status** field there instead — that
  path is unaffected.
- This screen only covers shipping *methods* already installed. Choosing
  which shipping extensions to install in the first place is still done
  through the regular `Extensions → Shipping Methods` screen.
