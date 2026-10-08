<?php
namespace MintCart\Compatibility\OpenCart3;

/**
 * Adapter over OpenCart 3's payment extensions: asks every enabled
 * `extension/payment/*` model's `getMethod($address, $total)` whether it is
 * available, keeps only recurring-capable methods when the cart has
 * recurring products, and sorts by `sort_order` — the loop moved verbatim
 * out of `checkout/payment_method.php::index()` (which now calls this, see
 * docs/upstream-diff.md), so stock checkout and the Mint checkout
 * (`ControllerMintcartCheckout`) share one implementation.
 *
 * Returns OpenCart's raw `payment_methods` shape (code => method row), the
 * same array both checkouts store in `session['payment_methods']` and
 * `checkout/confirm.php` reads back. Same duck-typed constructor as
 * ShippingQuoteAdapter/TotalsAdapter.
 */
final class PaymentMethodsAdapter
{
    private $config;
    private $loader;
    private $registry;

    public function __construct($config, $loader, $registry)
    {
        $this->config = $config;
        $this->loader = $loader;
        $this->registry = $registry;
    }

    public function calculate(array $address, $total, bool $recurring): array
    {
        $methodData = [];

        $this->loader->model('setting/extension');
        $modelSettingExtension = $this->registry->get('model_setting_extension');

        $results = $modelSettingExtension->getExtensions('payment');

        foreach ($results as $result) {
            if ($this->config->get('payment_' . $result['code'] . '_status')) {
                $this->loader->model('extension/payment/' . $result['code']);

                $model = $this->registry->get('model_extension_payment_' . $result['code']);
                $method = $model->getMethod($address, $total);

                if ($method) {
                    if ($recurring) {
                        if (property_exists($model, 'recurringPayments') && $model->recurringPayments()) {
                            $methodData[$result['code']] = $method;
                        }
                    } else {
                        $methodData[$result['code']] = $method;
                    }
                }
            }
        }

        $sortOrder = [];

        foreach ($methodData as $key => $value) {
            $sortOrder[$key] = $value['sort_order'];
        }

        array_multisort($sortOrder, SORT_ASC, $methodData);

        return $methodData;
    }
}
