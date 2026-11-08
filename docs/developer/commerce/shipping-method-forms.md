# Shipping method forms

A shipping extension can show its own fields in the Mint checkout (city, pickup
point, courier address, ...) without a theme change, controller patch or OCMOD.
Each extension owns its fields, its data source and its identifiers: there is no
shared city field and no adapter between carriers (a Nova Poshta city id is not
an Ukrposhta city id).

## Contract

Both methods are optional, on the extension's catalog model
(`catalog/model/extension/shipping/<code>.php`, class
`ModelExtensionShipping<Code>`). `$quote_key` is the part after the dot in the
chosen option id (`<code>.<quote_key>`).

```php
public function getCheckoutForm(string $quote_key): string
```

Returns an HTML fragment (Bootstrap 5 layout, `mc-*` components) that the
checkout puts under the chosen delivery option. It is filled once per chosen
option, so typed text survives recalculation. Inputs inside it are posted with
the checkout form on every update and on confirm. A `<script>` in the fragment
runs (guard it so it initialises once); load the carrier's own vanilla JS from
there. The fragment should read its stored values from the session.

```php
public function processCheckout(string $quote_key, array $post, array $address): array
```

Called after every update and before the order is placed, with the posted form
and the current session shipping address. Returns:

```php
[
    'errors'  => ['np_warehouse' => 'Choose a pickup point'], // shown on confirm
    'address' => ['city' => 'Київ', 'address_1' => 'Відділення №5'], // merged into shipping_address
]
```

Error keys are matched to `data-mc-checkout-field="<key>"` (with an inner
`.mc-field__error` and `.mc-field__control`) or `data-mc-checkout-error="<key>"`
in the fragment; unmatched messages are listed in the checkout's message box.
The extension validates what the customer sent (resolve ids against its own
data, never trust display text) and keeps its own values in the session.

The returned `address` ends up in the order's shipping fields, so stock order
screens, e-mails and shipping labels show the carrier's city and point.

## Address fields

The checkout asks for the delivery country first (inside the delivery step, hidden for a store that sells in one
country), then the delivery methods, and only then the address. While an option with a form is chosen, the plain
**city** and **address** fields are hidden and not validated: the form owns them, and its `address` result
(`city`, `address_1`) goes into both the shipping and the payment address of the order. The postcode field still
follows the country. So a carrier form must return `city` and `address_1` (or report an error for them).

The Nova Poshta and Ukrposhta forms use one shared component (`mc_carrier_points.twig`, `carrier-points.js`): a
searchable city picker (the list comes from the carrier's endpoint as you type) and a searchable picker of the city's
branches (number and address).

## Events

`mc:checkout:updated` (document) fires after each recalculation; a carrier
script can use it to refresh dependent fields.

## Extensions that use this contract

- `mc_novaposhta`, `mc_ukrposhta`: city and branch pickers over a local directory (`carrier-points.js`).
- `mc_inpost_uk`: postcode search through the InPost points API, one locker picker (`mc_pickup_point.twig`).
- `mc_carrier` ("Delivery services"): a configurable list of fixed-price services with no API (DHL, Hermes, Correos,
  UPS, ...). A service marked "Pickup point" asks the customer to type the city and the locker or shop number; both go
  into the delivery address as typed. A carrier that needs live points or tariffs gets its own extension instead.
