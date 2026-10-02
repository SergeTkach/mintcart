<?php

declare(strict_types=1);

namespace MintCart\Compatibility\OpenCart3;

/**
 * Adapter over OpenCart 3's order model(s) (`MINTCART_TECHNICAL_PLAN.md`
 * §5: `MintCart\Compatibility\OpenCart3\OrderAdapter` is the last of the
 * six named adapters in that list to be built — `SettingsAdapter`/
 * `ExtensionAdapter`/`CustomerAdapter`/`CategoryAdapter` already exist
 * (plus `TotalsAdapter`, an extra adapter built for Phase 15's checkout
 * totals, not one of the original six); `ProductAdapter` has an open,
 * unmerged PR at the time of writing).
 *
 * OpenCart ships three separate order model classes with a shared
 * `getOrder(int $order_id)` lookup but three different "not found"
 * sentinels:
 *
 * - `admin/model/sale/order.php` (`ModelSaleOrder`, admin order list/view)
 *   — returns `null` (a bare `return;`) when nothing matches.
 * - `catalog/model/checkout/order.php` (`ModelCheckoutOrder`, used while an
 *   order is being placed/confirmed) — returns `false`.
 * - `catalog/model/account/order.php` (`ModelAccountOrder`, a logged-in
 *   customer's own "My Orders") — also returns `false`, and additionally
 *   scopes every lookup to the current session's `customer_id`.
 *
 * This is exactly the "same method name, same row shape on a match,
 * different not-found sentinel" friction `CustomerAdapter`/`CategoryAdapter`
 * already document and normalize for their own pair of OpenCart models —
 * `OrderAdapter` does the same for order lookup, across three models
 * instead of two.
 *
 * Deliberately duck-typed rather than type-hinted against any concrete
 * OpenCart model (there is no `class ModelSaleOrder`/`ModelCheckoutOrder`/
 * `ModelAccountOrder` to `use`/autoload outside a booted OpenCart request)
 * — the same reasoning every other adapter in this namespace documents.
 * Any object exposing `getOrder(int): array|false|null` works; in
 * production that is `$this->model_sale_order`,
 * `$this->model_checkout_order` or `$this->model_account_order`, after the
 * matching `$this->load->model(...)` call.
 *
 * Not wired into any controller yet — no checkout/order call site exists
 * in this codebase yet to route through it (`CheckoutService` is still
 * unstarted per `docs/IMPLEMENTATION_STATUS.md`'s Phase 15 section, and
 * `QuickOrderService` deliberately never calls `checkout/order.php::addOrder()`
 * yet either). Exists now, ahead of that specific call site, because the
 * three-model sentinel mismatch is real today (visible in how
 * `admin/controller/sale/order.php`/`catalog/controller/account/order.php`
 * each have to know which specific model + sentinel they were given), the
 * same "identified concrete future call site, not wired in yet" shape
 * `CustomerAdapter`'s own doc describes for its own callers (a future
 * `CheckoutService`/`OrderService` order-confirmation lookup, and the admin
 * "Quick Order requests" list's eventual "linked order" column, per
 * `docs/IMPLEMENTATION_STATUS.md`'s Phase 15 section).
 */
final class OrderAdapter
{
    /**
     * @var object An object exposing getOrder(int): array|false|null — in
     *             production, OpenCart's admin, checkout, or account order
     *             model.
     */
    private object $orderModel;

    public function __construct(object $orderModel)
    {
        $this->orderModel = $orderModel;
    }

    /**
     * The order row for `$orderId`, or `null` if no order with that id
     * exists (or, on the account model, no order belonging to the current
     * customer). Never throws for a missing id, and never leaks which of
     * `null`/`false`/`[]` the underlying model used to say "not found" —
     * `ModelSaleOrder` returns `null`, `ModelCheckoutOrder`/`ModelAccountOrder`
     * return `false`, all three return a populated array on a match.
     *
     * @return array<string, mixed>|null
     */
    public function find(int $orderId): ?array
    {
        $row = $this->orderModel->getOrder($orderId);

        return is_array($row) && $row !== [] ? $row : null;
    }

    /**
     * Whether an order with this id exists (per `find()`'s rules).
     */
    public function exists(int $orderId): bool
    {
        return $this->find($orderId) !== null;
    }
}
