<?php
namespace MintCart\Compatibility\OpenCart3;

/**
 * Wraps OpenCart 3's "total" extension pipeline behind a normalized
 * MintCart contract (MINTCART_TECHNICAL_PLAN.md §5: "Mint code -> Adapter
 * -> OpenCart model/service/storage").
 *
 * `upload/catalog/controller/checkout/payment_method.php` and
 * `upload/catalog/controller/checkout/confirm.php` each inlined an
 * identical loop: load every installed `total` extension, sort by its own
 * `total_<code>_sort_order` config value, then call each enabled one's
 * `getTotal($total_data)` with `totals`/`taxes`/`total` passed by
 * reference (OpenCart's own `__call` proxy can't keep references across a
 * dynamic method call, hence the array-of-references wrapper). This
 * adapter is the single place that loop now lives - extracted once a
 * third real consumer needed the same computation
 * (`ControllerMintcartCheckout::totals()`, `docs/developer/commerce/checkout-totals-service.md`),
 * the same "wait for a third call site" threshold `ActiveTheme`/
 * `ProductCardListRenderer` were extracted under (CLAUDE.md §85/§86).
 *
 * Takes OpenCart's own `Config` object, `Loader` object, and `Registry`
 * (duck-typed - OpenCart's core engine classes have no common interface),
 * rather than a full `\Registry`-only or `\Loader`-only dependency: the
 * loop needs `Loader::model()` to dynamically load each `extension/total/<code>`
 * model class and `Registry::get()` to read back the proxy `Loader::model()`
 * just registered (the same `$this->{'model_extension_total_' . $code}`
 * magic-property trick `Controller`/`Model`'s `__get` normally hides -
 * this class has neither, so it reads the registry directly instead).
 * Any caller that already has all three (any Controller, via `$this->config`/
 * `$this->load`/`$this->registry`) needs nothing heavier.
 */
final class TotalsAdapter
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
     * @param array<int|string, float> $taxes Seed value for the `taxes`
     *        slot `getTotal()` implementations accumulate into - both
     *        existing call sites seed this from `$this->cart->getTaxes()`
     *        before the loop runs, so this stays a parameter rather than
     *        this class reaching for `$this->cart` itself (cart access
     *        stays the caller's job, same split `CartService` uses).
     *
     * @return array{
     *     totals: list<array{code: string, title: string, value: float, sort_order: int}>,
     *     taxes: array<int|string, float>,
     *     total: float
     * } `totals` is always returned sorted by `sort_order` - both original
     *   call sites computed this sort, one of them (`confirm.php`) just
     *   applied it a second time after the loop; doing it once here is not
     *   a behavior change for either.
     */
    public function calculate(array $taxes = []): array
    {
        $totals = [];
        $total = 0;

        // Because __call can not keep var references, so we put them into an array.
        $total_data = [
            'totals' => &$totals,
            'taxes'  => &$taxes,
            'total'  => &$total,
        ];

        $this->loader->model('setting/extension');
        $modelSettingExtension = $this->registry->get('model_setting_extension');

        $results = $modelSettingExtension->getExtensions('total');

        $sortOrder = [];

        foreach ($results as $key => $value) {
            $sortOrder[$key] = $this->config->get('total_' . $value['code'] . '_sort_order');
        }

        array_multisort($sortOrder, SORT_ASC, $results);

        foreach ($results as $result) {
            if ($this->config->get('total_' . $result['code'] . '_status')) {
                $this->loader->model('extension/total/' . $result['code']);

                $model = $this->registry->get('model_extension_total_' . $result['code']);
                $model->getTotal($total_data);
            }
        }

        $sortOrder = [];

        foreach ($totals as $key => $value) {
            $sortOrder[$key] = $value['sort_order'];
        }

        array_multisort($sortOrder, SORT_ASC, $totals);

        return [
            'totals' => $totals,
            'taxes'  => $taxes,
            'total'  => $total,
        ];
    }
}
