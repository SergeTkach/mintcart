<?php
namespace MintCart\Commerce;

/**
 * Validates a Quick Order request (MINTCART_FUNCTIONAL_SCOPE.md §13,
 * MINTCART_TECHNICAL_PLAN.md §28, MINTCART_IMPLEMENTATION_GUIDE.md §60) —
 * "product ID, quantity, required options, name, phone/email, basic
 * validation", the same reduced field set (no billing/shipping address, no
 * payment method) across every source document that describes Quick Order.
 *
 * Deliberately has no OpenCart dependency (no \Registry, no \DB) so it is
 * unit-testable in isolation, the same split `CartService`/`SearchService`
 * already established — fetching the product, checking required options
 * (delegated to `CartService::validateOptions()`, not re-derived here) and
 * persisting the request all stay the controller's job upstream of this
 * class.
 *
 * A validated request is a *lead*, not an order — see the
 * `202609041200_create_mc_quick_order.php` migration's docblock for why
 * this never calls into `checkout/order.php::addOrder()`.
 */
final class QuickOrderService
{
    /**
     * Below this a submitted quantity is almost certainly a broken/tampered
     * request, not a real order size — clamped up to 1 only when the field
     * was omitted entirely (a plain quick-order form always sends 1), and
     * flagged as an error when it was submitted but invalid, so a genuine
     * client bug doesn't silently get recorded as "quantity: 1".
     */
    private const MIN_QUANTITY = 1;

    /**
     * Texts of the Quick Order dialog (`text_<key>` in `mintcart/quickorder`
     * language file); the controllers hand them to `quick-order.js` as JSON.
     */
    public const TEXT_KEYS = ['title', 'quantity', 'decrease', 'increase', 'name', 'phone', 'email', 'cancel', 'submit', 'close', 'ok', 'failed'];

    /**
     * @param array{
     *     product_id?: mixed,
     *     quantity?: mixed,
     *     name?: mixed,
     *     telephone?: mixed,
     *     email?: mixed
     * } $request Raw submitted values (form-encoded, so everything arrives
     *        as a string) — this method does the trimming/casting/format
     *        checks that `checkout/*` controllers normally do inline.
     *
     * @param string $phoneMask the store's phone mask (`PhoneMask`), '' = any phone
     *
     * @return array{
     *     valid: bool,
     *     errors: array<string, string>,
     *     quantity: int,
     *     name: string,
     *     telephone: string,
     *     email: string
     * } `errors` is keyed by field name (`quantity`, `name`, `telephone`, `email`, or `contact` when neither is given),
     *        empty when `valid` is true. `quantity`/`name`/`telephone`/
     *        `email` are always the trimmed/cast values regardless of
     *        `valid`, so a controller can echo them back into a re-shown
     *        form without re-parsing the raw request itself.
     */
    public function validate(array $request, string $phoneMask = ''): array
    {
        $errors = [];

        $rawQuantity = $request['quantity'] ?? null;

        if ($rawQuantity === null || $rawQuantity === '') {
            $quantity = self::MIN_QUANTITY;
        } else {
            $quantity = (int) $rawQuantity;

            if ((string) $quantity !== trim((string) $rawQuantity) || $quantity < self::MIN_QUANTITY) {
                $errors['quantity'] = 'error_quantity';
                $quantity = self::MIN_QUANTITY;
            }
        }

        $name = trim((string) ($request['name'] ?? ''));

        if ($name === '') {
            $errors['name'] = 'error_name';
        }

        $telephone = trim((string) ($request['telephone'] ?? ''));
        $email = trim((string) ($request['email'] ?? ''));

        // `contact`: neither given; otherwise the error belongs to the field that is wrong.
        if ($telephone === '' && $email === '') {
            $errors['contact'] = 'error_contact';
        }

        if ($telephone !== '' && !PhoneMask::isComplete($telephone, $phoneMask)) {
            $errors['telephone'] = 'error_telephone';
        }

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'error_email';
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'quantity' => $quantity,
            'name' => $name,
            'telephone' => $telephone,
            'email' => $email,
        ];
    }
}
