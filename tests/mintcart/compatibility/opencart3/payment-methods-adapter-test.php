<?php
/**
 * Standalone smoke test for
 * MintCart\Compatibility\OpenCart3\PaymentMethodsAdapter.
 *
 * Config/Loader/Registry and the payment extension models are duck-typed
 * fakes - same style as shipping-quote-adapter-test.php. The scenarios are
 * the branches of the loop moved out of checkout/payment_method.php:
 * disabled extension, getMethod() declining, recurring-capable filtering,
 * and sort_order ordering.
 *
 * Run: php tests/mintcart/compatibility/opencart3/payment-methods-adapter-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../../upload/system/library/mintcart/compatibility/opencart3/paymentmethodsadapter.php';

use MintCart\Compatibility\OpenCart3\PaymentMethodsAdapter;

final class FakePaymentConfig
{
    public function __construct(private array $values)
    {
    }

    public function get(string $key)
    {
        return $this->values[$key] ?? null;
    }
}

final class FakePaymentRegistry
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

final class FakePaymentLoader
{
    public array $loaded = [];

    public function model(string $route): void
    {
        $this->loaded[] = $route;
    }
}

final class FakeModelSettingExtensionForPayment
{
    public function __construct(private array $extensions)
    {
    }

    public function getExtensions(string $type): array
    {
        return $type === 'payment' ? $this->extensions : [];
    }
}

final class FakePaymentModel
{
    public array $calls = [];

    public function __construct(private $method)
    {
    }

    public function getMethod($address, $total)
    {
        $this->calls[] = [$address, $total];

        return $this->method;
    }
}

final class FakeRecurringPaymentModel
{
    // OpenCart's Proxy exposes model methods as dynamic properties; the
    // adapter checks property_exists() exactly like stock did.
    public $recurringPayments;

    public function __construct(private $method)
    {
        $this->recurringPayments = true;
    }

    public function getMethod($address, $total)
    {
        return $this->method;
    }

    public function recurringPayments()
    {
        return true;
    }
}

$failures = [];

function assertSamePaymentMethodsAdapter($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

$method = fn ($code, $sort) => ['code' => $code, 'title' => strtoupper($code), 'terms' => '', 'sort_order' => $sort];

function paymentAdapterFor(array $config, array $models): array
{
    $registry = new FakePaymentRegistry();
    $registry->set('model_setting_extension', new FakeModelSettingExtensionForPayment(array_map(fn ($code) => ['code' => $code], array_keys($models))));

    foreach ($models as $code => $model) {
        $registry->set('model_extension_payment_' . $code, $model);
    }

    $loader = new FakePaymentLoader();

    return [new PaymentMethodsAdapter(new FakePaymentConfig($config), $loader, $registry), $loader];
}

$address = ['country_id' => 220, 'zone_id' => 3490];

// 1. Enabled methods come back keyed by code, sorted by sort_order; the
//    address and total reach getMethod() unchanged.
$cod = new FakePaymentModel($method('cod', 5));
[$adapter, $loader] = paymentAdapterFor(
    ['payment_cod_status' => 1, 'payment_bank_transfer_status' => 1],
    ['cod' => $cod, 'bank_transfer' => new FakePaymentModel($method('bank_transfer', 1))]
);
$result = $adapter->calculate($address, 70.0, false);

assertSamePaymentMethodsAdapter(['bank_transfer', 'cod'], array_keys($result), 'calculate: sorted by sort_order', $failures);
assertSamePaymentMethodsAdapter([[$address, 70.0]], $cod->calls, 'calculate: getMethod(address, total)', $failures);
assertSamePaymentMethodsAdapter(['setting/extension', 'extension/payment/cod', 'extension/payment/bank_transfer'], $loader->loaded, 'calculate: models loaded like stock', $failures);

// 2. A disabled extension is never asked; a declining getMethod() is skipped.
$disabled = new FakePaymentModel($method('cod', 1));
[$adapter] = paymentAdapterFor(
    ['payment_free_checkout_status' => 1],
    ['cod' => $disabled, 'free_checkout' => new FakePaymentModel(false)]
);

assertSamePaymentMethodsAdapter([], $adapter->calculate($address, 70.0, false), 'calculate: disabled + declining yields nothing', $failures);
assertSamePaymentMethodsAdapter([], $disabled->calls, 'calculate: disabled extension not queried', $failures);

// 3. Recurring cart: only recurring-capable models survive.
[$adapter] = paymentAdapterFor(
    ['payment_cod_status' => 1, 'payment_card_status' => 1],
    ['cod' => new FakePaymentModel($method('cod', 1)), 'card' => new FakeRecurringPaymentModel($method('card', 2))]
);

assertSamePaymentMethodsAdapter(['card'], array_keys($adapter->calculate($address, 70.0, true)), 'calculate: recurring keeps only recurring-capable', $failures);

if ($failures) {
    fwrite(STDERR, "FAIL\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "OK payment-methods-adapter-test\n";
exit(0);
