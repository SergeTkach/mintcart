<?php
namespace MintCart\Compatibility\OpenCart3;

use MintCart\Commerce\CheckoutService;

/**
 * Collects everything an order needs from the live OpenCart request
 * (session checkout state, cart, logged customer or guest, store config,
 * affiliate/marketing tracking cookie, currency, client headers) and hands
 * it to `CheckoutService::buildOrderData()`; `place()` then calls stock
 * `ModelCheckoutOrder::addOrder()`.
 *
 * This is the OpenCart-reading half of what used to be inline in
 * `checkout/confirm.php::index()`. Both `checkout/confirm.php` and
 * `ControllerMintcartCheckout::confirm()` call it, so orders from the stock
 * checkout and the Mint checkout are built identically.
 *
 * Duck-typed over OpenCart's Registry (session/cart/customer/config/
 * request/tax/currency/load) — same reasoning as the other adapters in this
 * namespace.
 */
final class CheckoutOrderAdapter
{
    private $registry;

    public function __construct($registry)
    {
        $this->registry = $registry;
    }

    /**
     * @param array $totalsResult TotalsAdapter::calculate() result (totals + total)
     */
    public function collect(array $totalsResult): array
    {
        $session = $this->registry->get('session');
        $cart = $this->registry->get('cart');
        $config = $this->registry->get('config');
        $request = $this->registry->get('request');
        $tax = $this->registry->get('tax');
        $currency = $this->registry->get('currency');

        $shippingRequired = (bool) $cart->hasShipping();

        $ctx = [
            'store'             => $this->store($config, $request),
            'customer'          => $this->customer($session),
            'payment_address'   => $session->data['payment_address'],
            'payment_method'    => $session->data['payment_method'] ?? null,
            'shipping_required' => $shippingRequired,
            'shipping_address'  => $shippingRequired ? $session->data['shipping_address'] : null,
            'shipping_method'   => $shippingRequired ? ($session->data['shipping_method'] ?? null) : null,
            'products'          => $cart->getProducts(),
            'vouchers'          => $session->data['vouchers'] ?? [],
            'comment'           => $session->data['comment'] ?? '',
            'totals'            => $totalsResult['totals'],
            'total'             => $totalsResult['total'],
            'marketing'         => $this->marketing($request, $cart),
            'locale'            => [
                'language_id'    => $config->get('config_language_id'),
                'currency_id'    => $currency->getId($session->data['currency']),
                'currency_code'  => $session->data['currency'],
                'currency_value' => $currency->getValue($session->data['currency']),
            ],
            'client'            => $this->client($request),
        ];

        return (new CheckoutService())->buildOrderData(
            $ctx,
            function ($price, $taxClassId) use ($tax) {
                return $tax->getTax($price, $taxClassId);
            },
            function () {
                return token(10);
            }
        );
    }

    public function place(array $orderData): int
    {
        $this->registry->get('load')->model('checkout/order');

        return (int) $this->registry->get('model_checkout_order')->addOrder($orderData);
    }

    private function store($config, $request): array
    {
        $storeId = $config->get('config_store_id');

        if ($storeId) {
            $storeUrl = $config->get('config_url');
        } elseif ($request->server['HTTPS']) {
            $storeUrl = HTTPS_SERVER;
        } else {
            $storeUrl = HTTP_SERVER;
        }

        return [
            'invoice_prefix' => $config->get('config_invoice_prefix'),
            'store_id'       => $storeId,
            'store_name'     => $config->get('config_name'),
            'store_url'      => $storeUrl,
        ];
    }

    private function customer($session): array
    {
        $customer = $this->registry->get('customer');

        $this->registry->get('load')->model('account/customer');

        if ($customer->isLogged()) {
            $info = $this->registry->get('model_account_customer')->getCustomer($customer->getId());

            return [
                'customer_id'       => $customer->getId(),
                'customer_group_id' => $info['customer_group_id'],
                'firstname'         => $info['firstname'],
                'lastname'          => $info['lastname'],
                'email'             => $info['email'],
                'telephone'         => $info['telephone'],
                'custom_field'      => json_decode($info['custom_field'], true),
            ];
        }

        $guest = $session->data['guest'] ?? [];

        return [
            'customer_id'       => 0,
            'customer_group_id' => $guest['customer_group_id'] ?? 0,
            'firstname'         => $guest['firstname'] ?? '',
            'lastname'          => $guest['lastname'] ?? '',
            'email'             => $guest['email'] ?? '',
            'telephone'         => $guest['telephone'] ?? '',
            'custom_field'      => $guest['custom_field'] ?? [],
        ];
    }

    private function marketing($request, $cart): array
    {
        if (!isset($request->cookie['tracking'])) {
            return ['tracking' => '', 'affiliate_id' => 0, 'commission' => 0, 'marketing_id' => 0];
        }

        $tracking = $request->cookie['tracking'];

        $affiliate = $this->registry->get('model_account_customer')->getAffiliateByTracking($tracking);

        $this->registry->get('load')->model('checkout/marketing');

        $marketing = $this->registry->get('model_checkout_marketing')->getMarketingByCode($tracking);

        return [
            'tracking'     => $tracking,
            'affiliate_id' => $affiliate ? $affiliate['customer_id'] : 0,
            'commission'   => $affiliate ? ($cart->getSubTotal() / 100) * $affiliate['commission'] : 0,
            'marketing_id' => $marketing ? $marketing['marketing_id'] : 0,
        ];
    }

    private function client($request): array
    {
        $server = $request->server;

        if (!empty($server['HTTP_X_FORWARDED_FOR'])) {
            $forwardedIp = $server['HTTP_X_FORWARDED_FOR'];
        } elseif (!empty($server['HTTP_CLIENT_IP'])) {
            $forwardedIp = $server['HTTP_CLIENT_IP'];
        } else {
            $forwardedIp = '';
        }

        return [
            'ip'              => $server['REMOTE_ADDR'],
            'forwarded_ip'    => $forwardedIp,
            'user_agent'      => $server['HTTP_USER_AGENT'] ?? '',
            'accept_language' => $server['HTTP_ACCEPT_LANGUAGE'] ?? '',
        ];
    }
}
