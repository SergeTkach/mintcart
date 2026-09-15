<?php

declare(strict_types=1);

namespace MintCart\Compatibility\OpenCart3;

/**
 * Adapter over OpenCart 3's customer model (`MINTCART_TECHNICAL_PLAN.md`
 * §5: `MintCart\Compatibility\OpenCart3\CustomerAdapter` is one of the six
 * named adapters in that list).
 *
 * OpenCart ships two separate customer model classes with diverging
 * method sets — `admin/model/customer/customer.php` (used by admin
 * screens, e.g. the customer Entity Picker's `ControllerMintcartEntity`)
 * and `catalog/model/account/customer.php` (used by storefront checkout).
 * Both expose `getCustomer(int $customer_id)` and
 * `getCustomerByEmail(string $email)` with the same shape (a DB row, or
 * an empty array/result when nothing matches) — this adapter wraps that
 * shared surface so Mint code that needs "look up a customer by id/email"
 * (guest-checkout duplicate-email detection per
 * `MINTCART_FUNCTIONAL_SCOPE.md` §12, and any future `OrderService`/
 * `CheckoutService` needing to attach an existing account to an order)
 * works the same way regardless of which side of OpenCart it runs on,
 * instead of two call sites each re-deriving "is this row empty" checks
 * against two subtly different model classes.
 *
 * Deliberately duck-typed rather than type-hinted against either concrete
 * OpenCart model (there is no `class ModelCustomerCustomer`/
 * `ModelAccountCustomer` to `use`/autoload outside a booted OpenCart
 * request) — the same reasoning `SettingsAdapter`/`ExtensionAdapter`
 * already document. Any object exposing `getCustomer(int): array` and
 * `getCustomerByEmail(string): array` works; in production that is
 * `$this->model_customer_customer` (admin, after
 * `$this->load->model('customer/customer')`) or
 * `$this->model_account_customer` (storefront, after
 * `$this->load->model('account/customer')`).
 *
 * Not wired into any controller yet — no checkout/order call site exists
 * in this codebase yet to route through it (Checkout/QuickOrder's Commerce
 * services are still unstarted per `docs/IMPLEMENTATION_STATUS.md`'s
 * Phase 15 section). Exists now because the model-shape mismatch between
 * OpenCart's two customer models is exactly the kind of friction the
 * adapter layer is meant to absorb before checkout logic accumulates
 * hand-written duplicate-lookup code against whichever model happens to
 * be loaded.
 */
final class CustomerAdapter
{
    /**
     * @var object An object exposing getCustomer(int): array and
     *             getCustomerByEmail(string): array — in production,
     *             OpenCart's admin or catalog customer model.
     */
    private object $customerModel;

    public function __construct(object $customerModel)
    {
        $this->customerModel = $customerModel;
    }

    /**
     * The customer row for `$customerId`, or `null` if no customer with
     * that id exists. Never throws for a missing id — OpenCart's own
     * `getCustomer()` returns an empty row/array in that case on both
     * models, not a specific "not found" signal.
     *
     * @return array<string, mixed>|null
     */
    public function find(int $customerId): ?array
    {
        return $this->normalize($this->customerModel->getCustomer($customerId));
    }

    /**
     * The customer row for `$email` (case-insensitive, matching
     * OpenCart's own `LCASE(email)` lookup on both models), or `null` if
     * no customer is registered under that email.
     *
     * @return array<string, mixed>|null
     */
    public function findByEmail(string $email): ?array
    {
        return $this->normalize($this->customerModel->getCustomerByEmail($email));
    }

    /**
     * Whether an account already exists for `$email` — the guest-checkout
     * duplicate-email check (`MINTCART_FUNCTIONAL_SCOPE.md` §12) needs
     * exactly this, without the caller having to know what an "empty"
     * result looks like on whichever customer model it was given.
     */
    public function existsByEmail(string $email): bool
    {
        return $this->findByEmail($email) !== null;
    }

    /**
     * @param mixed $row Whatever `getCustomer()`/`getCustomerByEmail()`
     *        returned — a populated array on a match, or an empty
     *        array/`false`/`null` on both OpenCart models when nothing
     *        matches.
     *
     * @return array<string, mixed>|null
     */
    private function normalize($row): ?array
    {
        return is_array($row) && $row !== [] ? $row : null;
    }
}
