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

## Events

`mc:checkout:updated` (document) fires after each recalculation; a carrier
script can use it to refresh dependent fields.
