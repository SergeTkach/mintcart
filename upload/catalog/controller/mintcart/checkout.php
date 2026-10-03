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
 *
 * `shippingMethods()` is the same idea applied to shipping method quotes
 * (see docs/developer/commerce/shipping-method-service.md) — a normalized,
 * non-mutating preview of available shipping methods for a given address,
 * via `MintCart\Compatibility\OpenCart3\ShippingQuoteAdapter`. Unlike
 * `totals()`, which reflects whatever is already in session,
 * `shippingMethods()` takes the address as request parameters — it does
 * not read or write `$this->session->data['shipping_address']`/
 * `['shipping_methods']`; `checkout/shipping_address.php`/
 * `checkout/shipping_method.php` remain the only code that persists a
 * customer's actual checkout selection.
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

	// route=mintcart/checkout/shipping_methods - GET, no mutation
	public function shippingMethods() {
		$address = array(
			'country_id' => (int)($this->request->get['country_id'] ?? 0),
			'zone_id'    => (int)($this->request->get['zone_id'] ?? 0),
			'postcode'   => (string)($this->request->get['postcode'] ?? ''),
			'city'       => (string)($this->request->get['city'] ?? ''),
			'address_1'  => (string)($this->request->get['address_1'] ?? ''),
			'firstname'  => (string)($this->request->get['firstname'] ?? ''),
			'lastname'   => (string)($this->request->get['lastname'] ?? ''),
			'company'    => (string)($this->request->get['company'] ?? ''),
			'zone_code'  => (string)($this->request->get['zone_code'] ?? ''),
			'iso_code_2' => (string)($this->request->get['iso_code_2'] ?? ''),
			'iso_code_3' => (string)($this->request->get['iso_code_3'] ?? '')
		);

		$adapter = new \MintCart\Compatibility\OpenCart3\ShippingQuoteAdapter($this->config, $this->load, $this->registry);
		$method_data = $adapter->calculate($address);

		$service = new \MintCart\Commerce\ShippingMethodService();

		$this->respond(array(
			'methods' => $service->normalize($method_data)
		));
	}

	private function respond($json) {
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
