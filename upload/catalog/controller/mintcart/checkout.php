<?php
/**
 * MintCart\Commerce\CheckoutTotalsService wiring (MINTCART_TECHNICAL_PLAN.md
 * §27, MINTCART_IMPLEMENTATION_GUIDE.md §61, MINTCART_FUNCTIONAL_SCOPE.md
 * §12: "При зміні адреси/доставки: актуалізувати shipping; payment;
 * totals; availability") — a normalized AJAX endpoint a future Mint-native
 * checkout UI can call to re-show order totals the moment the cart,
 * shipping method or address changes, without a full page reload.
 *
 * Deliberately a new, fully-additive controller — no existing checkout
 * controller/template is modified for this (see
 * docs/developer/commerce/checkout-totals-service.md). It is also the
 * third real consumer of the total-extension computation
 * `checkout/payment_method.php` and `checkout/confirm.php` each used to
 * inline separately — see `MintCart\Compatibility\OpenCart3\TotalsAdapter`
 * and this run's `docs/upstream-diff.md` entries for both of those files.
 */
class ControllerMintcartCheckout extends Controller {
	// route=mintcart/checkout/totals - GET, no mutation
	public function totals() {
		$taxes = $this->cart->getTaxes();

		$adapter = new \MintCart\Compatibility\OpenCart3\TotalsAdapter($this->config, $this->load, $this->registry);
		$result = $adapter->calculate($taxes);

		$currency = $this->session->data['currency'];
		$format_money = function ($value) use ($currency) {
			return $this->currency->format($value, $currency);
		};

		$service = new \MintCart\Commerce\CheckoutTotalsService();

		$this->respond(array(
			'lines' => $service->normalize($result['totals'], $format_money),
			'total' => $format_money($result['total'])
		));
	}

	private function respond($json) {
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
