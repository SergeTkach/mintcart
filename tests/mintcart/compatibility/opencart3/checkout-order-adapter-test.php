<?php
/**
 * Standalone smoke test for
 * MintCart\Compatibility\OpenCart3\CheckoutOrderAdapter.
 *
 * Every Registry service the adapter reads (session, cart, customer,
 * config, request, tax, currency, load + the account/customer,
 * checkout/marketing and checkout/order models) is a duck-typed fake. The
 * scenarios cover the request-reading branches that used to be inline in
 * checkout/confirm.php: guest vs logged-in customer, tracking cookie with
 * affiliate + marketing hits, store URL, client headers - plus place().
 * The field-by-field order shape itself is pinned by
 * tests/mintcart/commerce/checkout-service-test.php.
 *
 * Run: php tests/mintcart/compatibility/opencart3/checkout-order-adapter-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

const HTTP_SERVER = 'http://shop.test/';
const HTTPS_SERVER = 'https://shop.test/';

// OpenCart's system/helper/general.php token(), stubbed deterministically.
function token($length = 32)
{
    return str_repeat('T', $length);
}

require __DIR__ . '/../../../../upload/system/library/mintcart/commerce/checkoutservice.php';
require __DIR__ . '/../../../../upload/system/library/mintcart/compatibility/opencart3/checkoutorderadapter.php';

use MintCart\Compatibility\OpenCart3\CheckoutOrderAdapter;

final class FakeOrderRegistry
{
    private array $data = [];

    public function set(string $key, $value): void
    {
        $this->data[$key] = $value;
    }

    public function get(string $key)
    {
        return $this->data[$key] ?? null;
    }
}

final class FakeOrderConfig
{
    public function __construct(private array $values)
    {
    }

    public function get(string $key)
    {
        return $this->values[$key] ?? null;
    }
}

final class FakeOrderSession
{
    public array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }
}

final class FakeOrderCart
{
    public function __construct(private bool $shipping, private array $products)
    {
    }

    public function hasShipping()
    {
        return $this->shipping;
    }

    public function getProducts()
    {
        return $this->products;
    }

    public function getSubTotal()
    {
        return 200.0;
    }
}

final class FakeOrderCustomer
{
    public function __construct(private int $id)
    {
    }

    public function isLogged()
    {
        return $this->id > 0;
    }

    public function getId()
    {
        return $this->id;
    }
}

final class FakeOrderRequest
{
    public function __construct(public array $server, public array $cookie = [])
    {
    }
}

final class FakeOrderTax
{
    public function getTax($price, $taxClassId)
    {
        return $price * 0.1;
    }
}

final class FakeOrderCurrency
{
    public function getId($code)
    {
        return $code === 'UAH' ? 3 : 0;
    }

    public function getValue($code)
    {
        return 1.0;
    }
}

final class FakeOrderLoader
{
    public array $loaded = [];

    public function model(string $route): void
    {
        $this->loaded[] = $route;
    }
}

final class FakeAccountCustomerModel
{
    public function getCustomer($id)
    {
        return ['customer_group_id' => 2, 'firstname' => 'Ivan', 'lastname' => 'Petrenko', 'email' => 'ivan@example.com', 'telephone' => '0501112233', 'custom_field' => '{"1":"x"}'];
    }

    public function getAffiliateByTracking($tracking)
    {
        return $tracking === 'AFF' ? ['customer_id' => 77, 'commission' => 5] : false;
    }
}

final class FakeMarketingModel
{
    public function getMarketingByCode($code)
    {
        return $code === 'AFF' ? ['marketing_id' => 9] : false;
    }
}

final class FakeCheckoutOrderModel
{
    public array $added = [];

    public function addOrder($data)
    {
        $this->added[] = $data;

        return 1001;
    }
}

$failures = [];

function assertSameCheckoutOrderAdapter($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

$address = ['firstname' => 'Ivan', 'lastname' => 'Petrenko', 'company' => '', 'address_1' => 'Main 1', 'address_2' => '', 'city' => 'Kyiv', 'postcode' => '', 'zone' => 'Kyiv', 'zone_id' => 3490, 'country' => 'Ukraine', 'country_id' => 220, 'address_format' => '', 'custom_field' => []];

$product = ['product_id' => 1, 'name' => 'Mug', 'model' => 'M', 'download' => [], 'quantity' => 1, 'subtract' => 1, 'price' => 100.0, 'total' => 100.0, 'tax_class_id' => 9, 'reward' => 0, 'option' => []];

function orderRegistry(int $customerId, array $session, array $server, array $cookie, bool $shipping, array $product): array
{
    $registry = new FakeOrderRegistry();
    $loader = new FakeOrderLoader();
    $orders = new FakeCheckoutOrderModel();

    $registry->set('session', new FakeOrderSession($session));
    $registry->set('cart', new FakeOrderCart($shipping, [$product]));
    $registry->set('customer', new FakeOrderCustomer($customerId));
    $registry->set('config', new FakeOrderConfig(['config_store_id' => 0, 'config_name' => 'Mint Shop', 'config_invoice_prefix' => 'INV-', 'config_language_id' => 1]));
    $registry->set('request', new FakeOrderRequest($server, $cookie));
    $registry->set('tax', new FakeOrderTax());
    $registry->set('currency', new FakeOrderCurrency());
    $registry->set('load', $loader);
    $registry->set('model_account_customer', new FakeAccountCustomerModel());
    $registry->set('model_checkout_marketing', new FakeMarketingModel());
    $registry->set('model_checkout_order', $orders);

    return [new CheckoutOrderAdapter($registry), $orders, $loader];
}

$totals = ['totals' => [['code' => 'total', 'title' => 'Total', 'value' => 110.0]], 'total' => 110.0, 'taxes' => []];

// 1. Guest, shipping cart, no tracking cookie, plain HTTP, X-Forwarded-For.
[$adapter, $orders, $loader] = orderRegistry(
    0,
    [
        'currency' => 'UAH',
        'guest' => ['customer_group_id' => 1, 'firstname' => 'Olena', 'lastname' => 'Koval', 'email' => 'o@example.com', 'telephone' => '0500000000', 'custom_field' => []],
        'payment_address' => $address, 'shipping_address' => $address,
        'payment_method' => ['code' => 'cod', 'title' => 'Cash On Delivery'],
        'shipping_method' => ['code' => 'flat.flat', 'title' => 'Flat Rate'],
        'comment' => 'hi',
    ],
    ['HTTPS' => false, 'REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4', 'HTTP_USER_AGENT' => 'UA'],
    [],
    true,
    $product
);

$order = $adapter->collect($totals);

assertSameCheckoutOrderAdapter(
    [0, 1, 'Olena', 'o@example.com'],
    [$order['customer_id'], $order['customer_group_id'], $order['firstname'], $order['email']],
    'collect: guest contact from session guest',
    $failures
);
assertSameCheckoutOrderAdapter('http://shop.test/', $order['store_url'], 'collect: default store URL over HTTP', $failures);
assertSameCheckoutOrderAdapter(['flat.flat', 'Flat Rate', 'Main 1'], [$order['shipping_code'], $order['shipping_method'], $order['shipping_address_1']], 'collect: shipping from session', $failures);
assertSameCheckoutOrderAdapter(['', 0, 0, 0], [$order['tracking'], $order['affiliate_id'], $order['commission'], $order['marketing_id']], 'collect: no tracking cookie', $failures);
assertSameCheckoutOrderAdapter(['10.0.0.1', '1.2.3.4', 'UA', ''], [$order['ip'], $order['forwarded_ip'], $order['user_agent'], $order['accept_language']], 'collect: client headers', $failures);
assertSameCheckoutOrderAdapter([3, 'UAH', 1.0], [$order['currency_id'], $order['currency_code'], $order['currency_value']], 'collect: currency', $failures);
assertSameCheckoutOrderAdapter(10.0, $order['products'][0]['tax'], 'collect: product tax via Tax::getTax()', $failures);
assertSameCheckoutOrderAdapter(['hi', 110.0], [$order['comment'], $order['total']], 'collect: comment and total', $failures);

assertSameCheckoutOrderAdapter(1001, $adapter->place($order), 'place: returns addOrder() id', $failures);
assertSameCheckoutOrderAdapter([$order], $orders->added, 'place: passes order data through unchanged', $failures);
assertSameCheckoutOrderAdapter(true, in_array('checkout/order', $loader->loaded, true), 'place: loads checkout/order model', $failures);

// 2. Logged-in customer, no-shipping cart, tracking cookie with affiliate + marketing, HTTPS.
[$adapter] = orderRegistry(
    5,
    [
        'currency' => 'UAH',
        'payment_address' => $address,
        'payment_method' => ['code' => 'cod', 'title' => 'Cash On Delivery'],
    ],
    ['HTTPS' => true, 'REMOTE_ADDR' => '10.0.0.1', 'HTTP_CLIENT_IP' => '5.6.7.8', 'HTTP_ACCEPT_LANGUAGE' => 'uk'],
    ['tracking' => 'AFF'],
    false,
    $product
);

$order = $adapter->collect($totals);

assertSameCheckoutOrderAdapter(
    [5, 2, 'Ivan', ['1' => 'x']],
    [$order['customer_id'], $order['customer_group_id'], $order['firstname'], $order['custom_field']],
    'collect: logged customer from account/customer model',
    $failures
);
assertSameCheckoutOrderAdapter('https://shop.test/', $order['store_url'], 'collect: default store URL over HTTPS', $failures);
assertSameCheckoutOrderAdapter(['', '', ''], [$order['shipping_code'], $order['shipping_address_1'], $order['comment']], 'collect: no shipping, no comment', $failures);
assertSameCheckoutOrderAdapter(['AFF', 77, 10.0, 9], [$order['tracking'], $order['affiliate_id'], $order['commission'], $order['marketing_id']], 'collect: tracking -> affiliate commission (5% of 200) + marketing', $failures);
assertSameCheckoutOrderAdapter('5.6.7.8', $order['forwarded_ip'], 'collect: HTTP_CLIENT_IP fallback', $failures);

if ($failures) {
    fwrite(STDERR, "FAIL\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "OK checkout-order-adapter-test\n";
exit(0);
