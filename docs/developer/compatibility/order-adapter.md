# OpenCart 3 Compatibility Adapter — `OrderAdapter`

`MintCart\Compatibility\OpenCart3\OrderAdapter`
(`upload/system/library/mintcart/compatibility/opencart3/orderadapter.php`)

`MINTCART_TECHNICAL_PLAN.md` §5 (`MintCart\Compatibility\OpenCart3\*`
adapter layer, Phase 1 foundation), `MINTCART_IMPLEMENTATION_GUIDE.md` §38.

## What this is

The last of the six named `MintCart\Compatibility\OpenCart3\*` adapters
from `MINTCART_TECHNICAL_PLAN.md` §5 to be built. `SettingsAdapter`/
`ExtensionAdapter`/`CustomerAdapter`/`CategoryAdapter` already exist on
`master`; `ProductAdapter` has an open, unmerged PR (#143 at the time of
writing). Wraps OpenCart 3's order lookup (`getOrder()`) so Mint code
doesn't have to know which of OpenCart's three order model classes it was
given, or which "not found" sentinel that particular model uses.

## Why this exists — three models, three sentinels, one row shape

OpenCart 3 ships three separate order model classes:

- `admin/model/sale/order.php` (`ModelSaleOrder`) — used by the admin
  order list/view. `getOrder($order_id)` returns `null` (a bare `return;`)
  when nothing matches.
- `catalog/model/checkout/order.php` (`ModelCheckoutOrder`) — used while an
  order is being placed/confirmed during checkout. `getOrder($order_id)`
  returns `false` when nothing matches.
- `catalog/model/account/order.php` (`ModelAccountOrder`) — used by a
  logged-in customer's own "My Orders" page. `getOrder($order_id)` also
  returns `false`, and additionally scopes every lookup to
  `$this->customer->getId()` (a customer can never look up another
  customer's order id through this model).

All three return a fully-populated associative row (customer/payment/
shipping/totals fields, joined `order_status` name) on a match — the
divergence is purely in what "not found" looks like. This is exactly the
"same method name, same shape on a match, different not-found sentinel"
friction `CustomerAdapter` (two customer models) and `CategoryAdapter` (two
category models) already normalize for their own OpenCart model pairs;
`OrderAdapter` does the same across three order models instead of two.

## API

```php
$adapter = new \MintCart\Compatibility\OpenCart3\OrderAdapter($this->model_sale_order);
// or, during checkout: new OrderAdapter($this->model_checkout_order);
// or, on a customer's account page: new OrderAdapter($this->model_account_order);

$adapter->find(42);     // array|null — the order row, or null if not found
$adapter->exists(42);   // bool
```

The constructor is duck-typed, not type-hinted against any concrete
OpenCart model class — there is no `class ModelSaleOrder`/
`ModelCheckoutOrder`/`ModelAccountOrder` to `use`/autoload outside a booted
OpenCart request, the same reasoning every other adapter in this namespace
documents. Any object exposing `getOrder(int): array|false|null` works; in
production that is `$this->model_sale_order`, `$this->model_checkout_order`
or `$this->model_account_order`, after the matching
`$this->load->model(...)` call.

## Not wired into any controller yet

No checkout/order call site exists in this codebase yet to route through
it: `CheckoutService` is still unstarted (see
`docs/IMPLEMENTATION_STATUS.md`'s Phase 15 section), and `QuickOrderService`
deliberately never calls `checkout/order.php::addOrder()` — it explicitly
stops short of creating a real order (see its own docblock). This mirrors
`CustomerAdapter`'s own precedent: shipped ahead of a wired call site,
because the model-shape mismatch it resolves is real today (visible in how
`admin/controller/sale/order.php`'s own order view, and
`catalog/controller/account/order.php`'s own "My Orders" page, each have to
know which specific model + sentinel they were given), not because of a
hypothetical future need (`CLAUDE.md` §85). The identified future
consumers:

- A future `CheckoutService`/`OrderService`'s order-confirmation lookup,
  once checkout order creation itself exists.
- The admin "Quick Order requests" list's eventual "linked order" column
  (`docs/IMPLEMENTATION_STATUS.md`'s Phase 15 section), once a Quick Order
  request can actually be converted into a real order.

## Testing

`tests/mintcart/compatibility/opencart3/order-adapter-test.php` — a
standalone smoke test (no PHPUnit/Composer/MySQL available in this
environment, same pre-existing gap as every other test in this repo).
Exercises `OrderAdapter` against three differently-shaped in-memory
doubles, one per real OpenCart order model (admin/checkout/account),
proving `find()`/`exists()` normalize all three "not found" sentinels
(`null`, `false`, `false`) to the same `null` result, and that a match
still returns the exact row the underlying model produced.

Run:

```
php tests/mintcart/compatibility/opencart3/order-adapter-test.php
```

## Not done yet

- Not wired into any controller (see above).
- No `getOrders()`/`getTotalOrders()` list/count wrapping — `getOrder()` is
  the one method genuinely shared, with the same signature, across all
  three order models; `getOrders()`/`getTotalOrders()` only exist on the
  admin model today, so wrapping them here would mean inventing a contract
  with no second real implementation to normalize against. Per `CLAUDE.md`
  §85, that waits for a real consumer that specifically needs listing/
  counting, not just single-order lookup.
- No write operations (`addOrder`/`addOrderHistory`/`confirm`) — only
  lookup is needed by the identified future consumers so far, and
  `addOrder()`'s own ~90-field `$data` contract is exactly the kind of
  large, risky surface `CheckoutService` itself has been deliberately
  deferred over in every prior run.
- This was the last of the six named adapters from `MINTCART_TECHNICAL_PLAN.md`
  §5 to be built. `ProductAdapter` has an open, unmerged PR (#143) at the
  time of writing — once it merges, all six exist.
