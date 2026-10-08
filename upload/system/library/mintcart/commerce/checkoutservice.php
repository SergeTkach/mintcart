<?php

declare(strict_types=1);

namespace MintCart\Commerce;

/**
 * MintCart checkout rules (MINTCART_TECHNICAL_PLAN.md §24/§27,
 * MINTCART_IMPLEMENTATION_GUIDE.md §61, MINTCART_FUNCTIONAL_SCOPE.md §12) —
 * the pure, OpenCart-free half of the Mint single-page checkout.
 *
 * Everything here is plain array in → plain array out, so it is unit
 * tested without an OpenCart bootstrap. The OpenCart-facing half (reading
 * session/cart/customer/models, calling `addOrder()`) lives in
 * `MintCart\Compatibility\OpenCart3\CheckoutOrderAdapter` and
 * `ControllerMintcartCheckout`.
 *
 * Validation rules intentionally mirror stock `checkout/guest.php::save()`
 * field-for-field (same lengths, same language keys), so an order placed
 * through the Mint checkout carries exactly the data a stock-checkout order
 * would. Errors are returned as `field => language key`; the controller
 * resolves the key through `checkout/checkout`'s language file.
 *
 * Contract: docs/developer/commerce/checkout-service.md
 */
final class CheckoutService
{
    public const CONTACT_FIELDS = ['firstname', 'lastname', 'email', 'telephone'];

    public const ADDRESS_FIELDS = ['company', 'address_1', 'address_2', 'city', 'postcode', 'country_id', 'zone_id'];

    /**
     * Same gate every stock checkout/* controller repeats inline: products
     * (or vouchers) present, stock available unless backorders are allowed,
     * and every product's minimum quantity met (summed across cart rows of
     * the same product_id, since options split one product into rows).
     *
     * @param array<int, array{product_id: int|string, quantity: int|string, minimum: int|string}> $products
     */
    public function canCheckout(array $products, bool $hasVouchers, bool $hasStock, bool $stockCheckout): bool
    {
        if (!$products && !$hasVouchers) {
            return false;
        }

        if (!$hasStock && !$stockCheckout) {
            return false;
        }

        $quantities = [];

        foreach ($products as $product) {
            $quantities[$product['product_id']] = ($quantities[$product['product_id']] ?? 0) + (int) $product['quantity'];
        }

        foreach ($products as $product) {
            if ((int) $product['minimum'] > $quantities[$product['product_id']]) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, string> field => language key
     */
    public function validateContact(array $input): array
    {
        $errors = [];

        if (!$this->lengthBetween($input['firstname'] ?? '', 1, 32)) {
            $errors['firstname'] = 'error_firstname';
        }

        if (!$this->lengthBetween($input['lastname'] ?? '', 1, 32)) {
            $errors['lastname'] = 'error_lastname';
        }

        $email = (string) ($input['email'] ?? '');

        if (mb_strlen($email) > 96 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'error_email';
        }

        if (!$this->lengthBetween($input['telephone'] ?? '', 3, 32)) {
            $errors['telephone'] = 'error_telephone';
        }

        return $errors;
    }

    /**
     * @param array|null $country OpenCart country row (needs `postcode_required`), null if not found
     * @param int[] $zoneIds the country's zone ids; when the country has
     *        zones, one of them must be chosen (stock guest.php gets the
     *        same effect from its empty "Please select" option value)
     * @return array<string, string> field => language key
     */
    public function validateAddress(array $input, ?array $country, array $zoneIds): array
    {
        $errors = [];

        if (!$this->lengthBetween($input['address_1'] ?? '', 3, 128)) {
            $errors['address_1'] = 'error_address_1';
        }

        if (!$this->lengthBetween($input['city'] ?? '', 2, 128)) {
            $errors['city'] = 'error_city';
        }

        if ($country && !empty($country['postcode_required']) && !$this->lengthBetween($input['postcode'] ?? '', 2, 10)) {
            $errors['postcode'] = 'error_postcode';
        }

        if (!$country) {
            $errors['country_id'] = 'error_country';
        }

        $zoneId = $input['zone_id'] ?? '';

        if ($zoneId === '' || !is_numeric($zoneId) || ($zoneIds && !in_array((int) $zoneId, array_map('intval', $zoneIds), true))) {
            $errors['zone_id'] = 'error_zone';
        }

        return $errors;
    }

    /**
     * Builds the exact `session['payment_address']`/`['shipping_address']`
     * shape stock `checkout/guest.php::save()` writes, so payment/shipping
     * extensions and `checkout/confirm.php` read it unchanged. One address
     * serves both (the "one address" product decision) — the name comes
     * from the contact section.
     */
    public function buildAddress(array $contact, array $input, ?array $country, ?array $zone): array
    {
        return [
            'firstname'      => (string) ($contact['firstname'] ?? ''),
            'lastname'       => (string) ($contact['lastname'] ?? ''),
            'company'        => (string) ($input['company'] ?? ''),
            'address_1'      => (string) ($input['address_1'] ?? ''),
            'address_2'      => (string) ($input['address_2'] ?? ''),
            'postcode'       => (string) ($input['postcode'] ?? ''),
            'city'           => (string) ($input['city'] ?? ''),
            'country_id'     => (int) ($input['country_id'] ?? 0),
            'zone_id'        => (int) ($input['zone_id'] ?? 0),
            'country'        => $country['name'] ?? '',
            'iso_code_2'     => $country['iso_code_2'] ?? '',
            'iso_code_3'     => $country['iso_code_3'] ?? '',
            'address_format' => $country['address_format'] ?? '',
            'zone'           => $zone['name'] ?? '',
            'zone_code'      => $zone['code'] ?? '',
            'custom_field'   => [],
        ];
    }

    /**
     * Resolves a `code.quote_key` shipping id against OpenCart's raw
     * `shipping_methods` array (the shape `ShippingQuoteAdapter::calculate()`
     * returns). Falls back to the first available quote when the requested
     * id is empty or no longer offered (e.g. the address changed), so the
     * customer always has a valid selection without an extra click.
     *
     * @return array{id: string, quote: array}|null null when nothing is available
     */
    public function pickShippingMethod(string $requested, array $methodData): ?array
    {
        $first = null;

        foreach ($methodData as $code => $method) {
            if (!empty($method['error']) || empty($method['quote'])) {
                continue;
            }

            foreach ($method['quote'] as $key => $quote) {
                $id = $code . '.' . $key;

                if ($id === $requested) {
                    return ['id' => $id, 'quote' => $quote];
                }

                if ($first === null) {
                    $first = ['id' => $id, 'quote' => $quote];
                }
            }
        }

        return $first;
    }

    /**
     * Same fallback rule as pickShippingMethod(), for OpenCart's
     * `payment_methods` array (code => method).
     */
    public function pickPaymentMethod(string $requested, array $methods): ?array
    {
        if ($requested !== '' && isset($methods[$requested])) {
            return $methods[$requested];
        }

        $first = reset($methods);

        return $first === false ? null : $first;
    }

    /**
     * The `$order_data` array `ModelCheckoutOrder::addOrder()` expects —
     * moved here from `checkout/confirm.php` (which now calls it through
     * `CheckoutOrderAdapter`), so stock confirm and the Mint checkout build
     * orders from one implementation.
     *
     * @param array $ctx {
     *     store: array{invoice_prefix, store_id, store_name, store_url},
     *     customer: array{customer_id, customer_group_id, firstname, lastname, email, telephone, custom_field},
     *     payment_address: array, payment_method: array|null,
     *     shipping_required: bool, shipping_address: array|null, shipping_method: array|null,
     *     products: array, vouchers: array, comment: string,
     *     totals: array, total: float,
     *     marketing: array{tracking, affiliate_id, commission, marketing_id},
     *     locale: array{language_id, currency_id, currency_code, currency_value},
     *     client: array{ip, forwarded_ip, user_agent, accept_language}
     * }
     * @param callable $taxOf fn(float $price, int $taxClassId): float
     * @param callable $voucherCode fn(): string — a fresh gift-voucher code
     */
    public function buildOrderData(array $ctx, callable $taxOf, callable $voucherCode): array
    {
        $order = [
            'totals'         => $ctx['totals'],
            'invoice_prefix' => $ctx['store']['invoice_prefix'],
            'store_id'       => $ctx['store']['store_id'],
            'store_name'     => $ctx['store']['store_name'],
            'store_url'      => $ctx['store']['store_url'],
        ];

        foreach (['customer_id', 'customer_group_id', 'firstname', 'lastname', 'email', 'telephone', 'custom_field'] as $key) {
            $order[$key] = $ctx['customer'][$key];
        }

        $order += $this->addressFields('payment', $ctx['payment_address']);
        $order['payment_method'] = $ctx['payment_method']['title'] ?? '';
        $order['payment_code'] = $ctx['payment_method']['code'] ?? '';

        if ($ctx['shipping_required']) {
            $order += $this->addressFields('shipping', $ctx['shipping_address']);
            $order['shipping_method'] = $ctx['shipping_method']['title'] ?? '';
            $order['shipping_code'] = $ctx['shipping_method']['code'] ?? '';
        } else {
            $order += $this->addressFields('shipping', []);
            $order['shipping_method'] = '';
            $order['shipping_code'] = '';
        }

        $order['products'] = [];

        foreach ($ctx['products'] as $product) {
            $options = [];

            foreach ($product['option'] as $option) {
                $options[] = [
                    'product_option_id'       => $option['product_option_id'],
                    'product_option_value_id' => $option['product_option_value_id'],
                    'option_id'               => $option['option_id'],
                    'option_value_id'         => $option['option_value_id'],
                    'name'                    => $option['name'],
                    'value'                   => $option['value'],
                    'type'                    => $option['type'],
                ];
            }

            $order['products'][] = [
                'product_id' => $product['product_id'],
                'name'       => $product['name'],
                'model'      => $product['model'],
                'option'     => $options,
                'download'   => $product['download'],
                'quantity'   => $product['quantity'],
                'subtract'   => $product['subtract'],
                'price'      => $product['price'],
                'total'      => $product['total'],
                'tax'        => $taxOf($product['price'], $product['tax_class_id']),
                'reward'     => $product['reward'],
            ];
        }

        $order['vouchers'] = [];

        foreach ($ctx['vouchers'] as $voucher) {
            $order['vouchers'][] = [
                'description'      => $voucher['description'],
                'code'             => $voucherCode(),
                'to_name'          => $voucher['to_name'],
                'to_email'         => $voucher['to_email'],
                'from_name'        => $voucher['from_name'],
                'from_email'       => $voucher['from_email'],
                'voucher_theme_id' => $voucher['voucher_theme_id'],
                'message'          => $voucher['message'],
                'amount'           => $voucher['amount'],
            ];
        }

        $order['comment'] = $ctx['comment'];
        $order['total'] = $ctx['total'];

        foreach (['tracking', 'affiliate_id', 'commission', 'marketing_id'] as $key) {
            $order[$key] = $ctx['marketing'][$key];
        }

        foreach (['language_id', 'currency_id', 'currency_code', 'currency_value'] as $key) {
            $order[$key] = $ctx['locale'][$key];
        }

        foreach (['ip', 'forwarded_ip', 'user_agent', 'accept_language'] as $key) {
            $order[$key] = $ctx['client'][$key];
        }

        return $order;
    }

    private function addressFields(string $prefix, array $address): array
    {
        $fields = [];

        foreach (['firstname', 'lastname', 'company', 'address_1', 'address_2', 'city', 'postcode', 'zone', 'zone_id', 'country', 'country_id', 'address_format'] as $key) {
            $fields[$prefix . '_' . $key] = $address[$key] ?? '';
        }

        $fields[$prefix . '_custom_field'] = $address['custom_field'] ?? [];

        return $fields;
    }

    private function lengthBetween($value, int $min, int $max): bool
    {
        $length = mb_strlen((string) $value);

        return $length >= $min && $length <= $max;
    }
}
