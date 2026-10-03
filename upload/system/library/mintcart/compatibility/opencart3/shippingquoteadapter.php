<?php
namespace MintCart\Compatibility\OpenCart3;

/**
 * Wraps OpenCart 3's "shipping" extension quote pipeline behind a
 * normalized MintCart contract (MINTCART_TECHNICAL_PLAN.md §5: "Mint code
 * -> Adapter -> OpenCart model/service/storage"), the same role
 * `TotalsAdapter` plays for the "total" extension pipeline.
 *
 * `upload/catalog/controller/checkout/shipping_method.php::index()` is the
 * only existing call site for this exact loop (load every installed
 * `shipping` extension, skip disabled ones, call each enabled one's
 * `getQuote($address)`, collect into an associative array keyed by
 * extension code, then sort by `sort_order`) - it is left completely
 * untouched by this adapter (zero upstream diff for this increment,
 * `CLAUDE.md` §11), since that loop is not itself duplicated anywhere in
 * this codebase. This class exists purely so
 * `ControllerMintcartCheckout::shippingMethods()`
 * (`docs/developer/commerce/shipping-method-service.md`) does not have to
 * re-derive the same computation to serve it as a normalized AJAX
 * response for a future Mint-native checkout UI
 * (`MINTCART_FUNCTIONAL_SCOPE.md` §12: "При зміні адреси/доставки:
 * актуалізувати shipping; payment; totals; availability").
 *
 * Deliberately does not read or write `$this->session->data`, unlike the
 * legacy controller - the caller supplies the address to quote against,
 * and gets the computed methods back; nothing is persisted as a side
 * effect. This mirrors `ControllerMintcartCheckout::totals()`'s own
 * "recompute, do not mutate session" shape.
 *
 * Takes OpenCart's own `Config` object, `Loader` object, and `Registry`
 * (duck-typed, same three collaborators `TotalsAdapter` takes and for the
 * same reason: `Loader::model()` to dynamically load each
 * `extension/shipping/<code>` model class, `Registry::get()` to read the
 * proxy `Loader::model()` just registered, `Config::get()` for each
 * extension's own `shipping_<code>_status` flag).
 */
final class ShippingQuoteAdapter
{
    private $config;
    private $loader;
    private $registry;

    /**
     * @param object $config OpenCart's `Config` object (or any object
     *        exposing `get(string $key)`).
     * @param object $loader OpenCart's `Loader` object (or any object
     *        exposing `model(string $route): void`).
     * @param object $registry OpenCart's `Registry` object (or any object
     *        exposing `get(string $key)`/`has(string $key)`) - reads back
     *        the model proxies `$loader->model()` registers.
     */
    public function __construct($config, $loader, $registry)
    {
        $this->config = $config;
        $this->loader = $loader;
        $this->registry = $registry;
    }

    /**
     * @param array<string, mixed> $address The shape every stock shipping
     *        extension's `getQuote($address)` reads - at minimum
     *        `country_id`/`zone_id` (every geo-zone-based extension checks
     *        these), some also read `postcode`/`iso_code_2`/`iso_code_3`/
     *        `zone_code`/`firstname`/`lastname`/`company`/`address_1`/
     *        `city`. The caller is responsible for filling in every key
     *        the extensions it has installed actually need - same
     *        "caller assembles the input, this class only computes"
     *        split `TotalsAdapter::calculate($taxes)` uses for its seed
     *        `$taxes` parameter.
     *
     * @return array<string, array{
     *     title: string,
     *     quote: array<string, array{code: string, title: string, cost: float, tax_class_id: int, text: string}>,
     *     sort_order: int,
     *     error: bool
     * }> Keyed by shipping extension code (e.g. `flat`, `weight`) -
     *   `array_multisort()` preserves these string keys while sorting by
     *   `sort_order`, the same behavior
     *   `checkout/shipping_method.php::index()` already relies on for its
     *   own `$this->session->data['shipping_methods'][$code]` lookups in
     *   `save()`. A disabled extension, or one whose `getQuote()` returns
     *   an empty/falsy result (no applicable geo zone, no API response),
     *   is simply absent from the returned array - never a placeholder
     *   entry.
     */
    public function calculate(array $address): array
    {
        $methodData = [];

        $this->loader->model('setting/extension');
        $modelSettingExtension = $this->registry->get('model_setting_extension');

        $results = $modelSettingExtension->getExtensions('shipping');

        foreach ($results as $result) {
            if ($this->config->get('shipping_' . $result['code'] . '_status')) {
                $this->loader->model('extension/shipping/' . $result['code']);

                $model = $this->registry->get('model_extension_shipping_' . $result['code']);
                $quote = $model->getQuote($address);

                if ($quote) {
                    $methodData[$result['code']] = [
                        'title'      => $quote['title'],
                        'quote'      => $quote['quote'],
                        'sort_order' => $quote['sort_order'],
                        'error'      => $quote['error'],
                    ];
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
