# OpenCart 3 Compatibility Adapter — `CustomerAdapter`

`MintCart\Compatibility\OpenCart3\CustomerAdapter`
(`upload/system/library/mintcart/compatibility/opencart3/customeradapter.php`)

`MINTCART_TECHNICAL_PLAN.md` §5 (`MintCart\Compatibility\OpenCart3\*`
adapter layer, Phase 1 foundation), `MINTCART_IMPLEMENTATION_GUIDE.md` §38.

## What this is

The fourth `MintCart\Compatibility\OpenCart3\*` adapter (after
`SettingsAdapter` and `ExtensionAdapter`, both open PRs at the time of
writing). Wraps OpenCart 3's customer lookup so Mint code can ask "does a
customer with this id/email exist" without knowing which of OpenCart's
two customer model classes is currently loaded.

## Why this exists — two models, one shared surface

OpenCart 3 ships two separate customer model classes:

- `admin/model/customer/customer.php` — used by admin screens (the
  customer Entity Picker's `ControllerMintcartEntity` already loads this
  one via `$this->load->model('customer/customer')`).
- `catalog/model/account/customer.php` — used by the storefront
  (checkout, account pages).

They diverge in most of their methods (admin has affiliate/reward/IP
tracking methods the storefront model doesn't, the storefront model has
password/token methods the admin model doesn't), but both expose the same
two methods with the same shape:

```php
getCustomer(int $customer_id): array   // a full row, or [] if not found
getCustomerByEmail(string $email): array // case-insensitive (LCASE), same shape
```

Any Mint code that needs "look up a customer by id/email" — guest-checkout
duplicate-email detection (`MINTCART_FUNCTIONAL_SCOPE.md` §12: "Guest
checkout — first-class"), or a future `OrderService`/`CheckoutService`
attaching an existing account to an order — would otherwise have to
duck-type against whichever model happens to be loaded and re-derive the
"empty array/result means not found" check by hand at every call site.
`CustomerAdapter` normalizes that once.

## API

```php
$adapter = new \MintCart\Compatibility\OpenCart3\CustomerAdapter($this->model_customer_customer);
// or, on the storefront:
$adapter = new \MintCart\Compatibility\OpenCart3\CustomerAdapter($this->model_account_customer);

$adapter->find(42);                    // array|null — the customer row, or null if not found
$adapter->findByEmail('a@b.com');      // array|null — case-insensitive lookup
$adapter->existsByEmail('a@b.com');    // bool
```

The constructor is duck-typed, not type-hinted against either concrete
OpenCart model class — there is no `class ModelCustomerCustomer` to
`use`/autoload outside a booted OpenCart request, the same reasoning
`SettingsAdapter`/`ExtensionAdapter` already document. Any object exposing
`getCustomer(int): array` and `getCustomerByEmail(string): array` works.

## Not wired into any controller yet

No checkout/order call site exists in this codebase yet — `CheckoutService`
and `QuickOrderService`'s duplicate-order-path work are both still
unstarted (see `docs/IMPLEMENTATION_STATUS.md`'s Phase 15 section). This
adapter exists ahead of that specific call site because the two-models
friction it resolves is real today (visible in how the customer Entity
Picker backend already has to know which specific model it was given),
not because of a hypothetical future need — the same "identified concrete
future call site, not wired in yet" shape `SettingsAdapter`'s own doc
describes for its three known-but-not-yet-routed callers.

## Testing

`tests/mintcart/compatibility/opencart3/customer-adapter-test.php` — a
standalone smoke test (no PHPUnit/Composer/MySQL available in this
environment, same pre-existing gap as every other test in this repo).
Exercises `CustomerAdapter` against two differently-shaped in-memory
doubles (one mirroring the admin model, one mirroring the storefront
model) to prove it genuinely works against either without depending on
which one it was constructed with.

Run:

```
php tests/mintcart/compatibility/opencart3/customer-adapter-test.php
```

## Not done yet

- Not wired into any controller (see above).
- No `ProductAdapter`, `CategoryAdapter`, or `OrderAdapter` yet — the
  three remaining named adapters from `MINTCART_TECHNICAL_PLAN.md` §5.
- No write operations (`addCustomer`/`editCustomer`) — only lookup is
  needed by the identified future consumers so far; per `CLAUDE.md` §85,
  adding them ahead of a real write consumer would be inventing API.
