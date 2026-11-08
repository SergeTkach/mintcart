# ADR 020 — Carrier pickers without Select2, a "Delivery services" extension, address after delivery

## Context
Pickup-point carriers (Nova Poshta, Ukrposhta, InPost, DHL Packstation, ...) need the customer to find a city and a
branch among thousands. The previous checkout asked for the delivery address first and showed carrier fields under the
chosen method. Select2 was the obvious tool, but the project forbids jQuery in new code and Select2 depends on it.
Writing a dozen carrier modules, each with its own API and key, is also not realistic for the long tail of carriers.

## Decision
- One vanilla picker component (`mc-picker`, `carrier-points.js`): a button that opens a panel with a search box and a
  list. The city list is fetched as you type (2+ letters); the branches of the chosen city are loaded once and
  filtered in the browser. A carrier extension supplies only its endpoints and its form template.
- The checkout order is contacts, **delivery** (the country when the store sells abroad, then the methods), then the
  **delivery address**, then payment. While an option with its own form is chosen the plain city and address fields are
  hidden and not validated; the carrier returns `city` and `address_1` and they go into both order addresses.
- Region is not asked at checkout or in the wizard (country only), and a store setting
  `config_mc_sell_abroad` decides whether the country is asked at all.
- Nova Poshta uses a local directory in the database (like Ukrposhta), refilled from the extension's admin screen,
  instead of calling the API while the customer types.
- One extension, **Delivery services** (`mc_carrier`), covers fixed-price carriers without an API: a list of services
  (name, price, zone, optional typed pickup point). A carrier whose points or tariffs must be live gets its own
  extension with the same form contract (`docs/developer/commerce/shipping-method-forms.md`).

## Consequences
- No Select2 and no jQuery in the checkout; the picker is ours to keep accessible (keyboard, labels, focus).
- A carrier extension cannot rely on the plain address fields: it must return `city` and `address_1`.
- Shipping rules tied to a specific region (geo zones by region) only work at country level in the Mint checkout.
- The local directory must be loaded once per store; until then the method is not offered.
- InPost UK searches live by postcode and was written against the documented API only; it needs a check against the
  real service (and a token where required) before it is relied on.
