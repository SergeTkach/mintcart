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
 *
 * `state()`/`update()`/`confirm()` back the Mint single-page checkout
 * (`mint_default/template/checkout/checkout.twig`, rendered by the stock
 * `checkout/checkout` route, so every existing link keeps working). Unlike
 * the two previews above they DO write the checkout session - the same
 * keys stock `checkout/guest.php`/`shipping_method.php`/`payment_method.php`
 * write (`guest`, `payment_address`, `shipping_address`,
 * `shipping_methods`/`shipping_method`, `payment_methods`/`payment_method`,
 * `comment`), so payment and shipping extensions see a normal OpenCart
 * checkout. `update()` persists whatever has been entered so far without
 * validating it (delivery, payment and totals recalculate while the
 * customer types); `confirm()` validates everything, creates the order via
 * the same `CheckoutOrderAdapter` stock `checkout/confirm.php` uses, and
 * returns the chosen payment extension's own confirm HTML. See
 * docs/developer/commerce/checkout-service.md.
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

	// route=mintcart/checkout/state - GET, full checkout snapshot for the page boot
	public function state() {
		if (isset($this->session->data['payment_address'])) {
			$this->recalculate(null, null);
		}

		$this->respond($this->buildState(true));
	}

	// route=mintcart/checkout/update - POST any subset of the checkout form, no validation
	public function update() {
		$blocked = $this->blocked();

		if ($blocked) {
			$this->respond($blocked);

			return;
		}

		$this->persist();

		$this->respond($this->buildState(false));
	}

	// route=mintcart/checkout/confirm - POST the whole form; validates, creates the order
	public function confirm() {
		$this->load->language('checkout/checkout');

		$blocked = $this->blocked();

		if ($blocked) {
			$this->respond($blocked);

			return;
		}

		$this->persist();

		$errors = $this->validate();

		if ($errors) {
			$this->respond(array('errors' => $errors, 'state' => $this->buildState(false)));

			return;
		}

		$totals_adapter = new \MintCart\Compatibility\OpenCart3\TotalsAdapter($this->config, $this->load, $this->registry);
		$order_adapter = new \MintCart\Compatibility\OpenCart3\CheckoutOrderAdapter($this->registry);

		$order_data = $order_adapter->collect($totals_adapter->calculate($this->cart->getTaxes()));

		$this->session->data['order_id'] = $order_adapter->place($order_data);

		$this->respond(array(
			'payment_html' => $this->load->controller('extension/payment/' . $this->session->data['payment_method']['code'])
		));
	}

	// route=mintcart/checkout/code - POST code. One field for both a coupon and
	// a gift certificate: tried as a coupon first, then as a certificate.
	// Validation is the stock total models' own (dates, usage limits, cart
	// products, minimum total, balance); the codes go to the same session
	// keys the stock cart-page blocks use, so the coupon/voucher total
	// extensions pick them up unchanged.
	public function code() {
		$this->load->language('checkout/checkout');

		$blocked = $this->blocked();

		if ($blocked) {
			$this->respond($blocked);

			return;
		}

		$code = trim((string)($this->request->post['code'] ?? ''));
		$error = '';

		if ($code === '') {
			$error = $this->language->get('error_mc_code_empty');
		} elseif ($this->config->get('total_coupon_status') && $this->couponValid($code)) {
			$this->session->data['coupon'] = $code;
		} elseif ($this->config->get('total_voucher_status') && $this->voucherValid($code)) {
			$this->session->data['voucher'] = $code;
		} else {
			$error = $this->language->get('error_mc_code');
		}

		$this->respondWithCodes($error);
	}

	// route=mintcart/checkout/removeCode - POST type=coupon|voucher
	public function removeCode() {
		$this->load->language('checkout/checkout');

		$blocked = $this->blocked();

		if ($blocked) {
			$this->respond($blocked);

			return;
		}

		$type = (string)($this->request->post['type'] ?? '');

		if ($type === 'coupon' || $type === 'voucher') {
			unset($this->session->data[$type]);
		}

		$this->respondWithCodes('');
	}

	private function couponValid($code) {
		$this->load->model('extension/total/coupon');

		return (bool)$this->model_extension_total_coupon->getCoupon($code);
	}

	private function voucherValid($code) {
		$this->load->model('extension/total/voucher');

		return (bool)$this->model_extension_total_voucher->getVoucher($code);
	}

	// A code changes the total, and payment availability can depend on it.
	private function respondWithCodes($error) {
		if (isset($this->session->data['payment_address'])) {
			$this->recalculate(null, null);
		}

		$this->respond(array(
			'error' => $error,
			'state' => $this->buildState(false)
		));
	}

	private function service() {
		return new \MintCart\Commerce\CheckoutService();
	}

	private function canGuest() {
		return $this->config->get('config_checkout_guest') && !$this->config->get('config_customer_price') && !$this->cart->hasDownload();
	}

	// Same cart gate + guest gate the stock checkout/* controllers apply.
	private function blocked() {
		$can_checkout = $this->service()->canCheckout(
			$this->cart->getProducts(),
			!empty($this->session->data['vouchers']),
			(bool)$this->cart->hasStock(),
			(bool)$this->config->get('config_stock_checkout')
		);

		if (!$can_checkout) {
			return array('redirect' => $this->url->link('checkout/cart'));
		}

		if (!$this->customer->isLogged() && !$this->canGuest()) {
			return array('redirect' => $this->url->link('account/login', '', true));
		}

		return null;
	}

	private function contact() {
		if ($this->customer->isLogged()) {
			return array(
				'firstname' => $this->customer->getFirstName(),
				'lastname'  => $this->customer->getLastName(),
				'email'     => $this->customer->getEmail(),
				'telephone' => $this->customer->getTelephone()
			);
		}

		$guest = isset($this->session->data['guest']) ? $this->session->data['guest'] : array();
		$contact = array();

		foreach (\MintCart\Commerce\CheckoutService::CONTACT_FIELDS as $field) {
			$contact[$field] = isset($guest[$field]) ? $guest[$field] : '';
		}

		return $contact;
	}

	// Current address form values: the session address, else a logged-in
	// customer's default address, else the store's country/region.
	private function addressInput() {
		if (isset($this->session->data['payment_address'])) {
			$address = $this->session->data['payment_address'];
		} elseif ($this->customer->isLogged() && $this->customer->getAddressId()) {
			$this->load->model('account/address');

			$address = $this->model_account_address->getAddress($this->customer->getAddressId());
		} else {
			$address = false;
		}

		if (!$address) {
			$address = array(
				'country_id' => $this->config->get('config_country_id'),
				'zone_id'    => $this->config->get('config_zone_id')
			);
		}

		$input = array();

		foreach (\MintCart\Commerce\CheckoutService::ADDRESS_FIELDS as $field) {
			$input[$field] = isset($address[$field]) ? $address[$field] : '';
		}

		return $input;
	}

	// Writes the posted (possibly partial) form into the stock checkout
	// session keys, then recalculates delivery/payment/totals.
	private function persist() {
		$post = $this->request->post;

		if (!$this->customer->isLogged()) {
			$guest = isset($this->session->data['guest']) ? $this->session->data['guest'] : array();

			foreach (\MintCart\Commerce\CheckoutService::CONTACT_FIELDS as $field) {
				if (isset($post[$field])) {
					$guest[$field] = trim((string)$post[$field]);
				}
			}

			$guest['customer_group_id'] = $this->config->get('config_customer_group_id');
			$guest['custom_field'] = isset($guest['custom_field']) ? $guest['custom_field'] : array();
			$guest['shipping_address'] = true;

			$this->session->data['account'] = 'guest';
			$this->session->data['guest'] = $guest;
		}

		$input = $this->addressInput();

		foreach (\MintCart\Commerce\CheckoutService::ADDRESS_FIELDS as $field) {
			if (isset($post[$field])) {
				$input[$field] = trim((string)$post[$field]);
			}
		}

		$this->load->model('localisation/country');
		$this->load->model('localisation/zone');

		$country_info = $this->model_localisation_country->getCountry((int)$input['country_id']);
		$zone_info = $this->model_localisation_zone->getZone((int)$input['zone_id']);

		$address = $this->service()->buildAddress($this->contact(), $input, $country_info ? $country_info : null, $zone_info ? $zone_info : null);

		$this->session->data['payment_address'] = $address;

		if ($this->cart->hasShipping()) {
			$this->session->data['shipping_address'] = $address;
		}

		if (isset($post['comment'])) {
			$this->session->data['comment'] = strip_tags((string)$post['comment']);
		}

		$this->recalculate(
			isset($post['shipping_method']) ? (string)$post['shipping_method'] : null,
			isset($post['payment_method']) ? (string)$post['payment_method'] : null
		);
	}

	// Re-quotes shipping for the session address, then re-offers payment
	// methods against the resulting total (payment availability can depend
	// on it). A previous selection survives if it is still offered;
	// otherwise the first available method is selected.
	private function recalculate($shipping_code, $payment_code) {
		$service = $this->service();

		if ($this->cart->hasShipping() && isset($this->session->data['shipping_address'])) {
			if ($shipping_code === null) {
				$shipping_code = isset($this->session->data['shipping_method']['code']) ? $this->session->data['shipping_method']['code'] : '';
			}

			$shipping_adapter = new \MintCart\Compatibility\OpenCart3\ShippingQuoteAdapter($this->config, $this->load, $this->registry);
			$method_data = $shipping_adapter->calculate($this->session->data['shipping_address']);

			$this->session->data['shipping_methods'] = $method_data;

			$picked = $service->pickShippingMethod($shipping_code, $method_data);

			if ($picked) {
				$this->session->data['shipping_method'] = $picked['quote'];
			} else {
				unset($this->session->data['shipping_method']);
			}
		} elseif (!$this->cart->hasShipping()) {
			unset($this->session->data['shipping_address']);
			unset($this->session->data['shipping_method']);
			unset($this->session->data['shipping_methods']);
		}

		if ($payment_code === null) {
			$payment_code = isset($this->session->data['payment_method']['code']) ? $this->session->data['payment_method']['code'] : '';
		}

		$totals_adapter = new \MintCart\Compatibility\OpenCart3\TotalsAdapter($this->config, $this->load, $this->registry);
		$totals_result = $totals_adapter->calculate($this->cart->getTaxes());

		$payment_adapter = new \MintCart\Compatibility\OpenCart3\PaymentMethodsAdapter($this->config, $this->load, $this->registry);
		$payment_methods = $payment_adapter->calculate($this->session->data['payment_address'], $totals_result['total'], $this->cart->hasRecurringProducts());

		$this->session->data['payment_methods'] = $payment_methods;

		$picked = $service->pickPaymentMethod($payment_code, $payment_methods);

		if ($picked) {
			$this->session->data['payment_method'] = $picked;
		} else {
			unset($this->session->data['payment_method']);
		}
	}

	// @return array field => message
	private function validate() {
		$service = $this->service();
		$errors = array();

		if (!$this->customer->isLogged()) {
			$errors += $service->validateContact($this->contact());
		}

		$input = $this->addressInput();

		$this->load->model('localisation/country');

		$this->load->model('localisation/zone');

		$country_info = $this->model_localisation_country->getCountry((int)$input['country_id']);
		$zone_ids = array_column($this->model_localisation_zone->getZonesByCountryId((int)$input['country_id']), 'zone_id');

		$errors += $service->validateAddress($input, $country_info ? $country_info : null, $zone_ids);

		if ($this->cart->hasShipping() && !isset($this->session->data['shipping_method'])) {
			$errors['shipping_method'] = 'error_shipping';
		}

		if (!isset($this->session->data['payment_method'])) {
			$errors['payment_method'] = 'error_payment';
		}

		$messages = array();

		foreach ($errors as $field => $key) {
			$messages[$field] = $this->language->get($key);
		}

		$agree_info = $this->agreeInformation();

		if ($agree_info && empty($this->request->post['agree'])) {
			$messages['agree'] = sprintf($this->language->get('error_agree'), $agree_info['title']);
		}

		return $messages;
	}

	private function agreeInformation() {
		if (!$this->config->get('config_checkout_id')) {
			return null;
		}

		$this->load->model('catalog/information');

		$information_info = $this->model_catalog_information->getInformation($this->config->get('config_checkout_id'));

		return $information_info ? $information_info : null;
	}

	private function buildState($full) {
		$this->load->language('checkout/checkout');

		$logged = (bool)$this->customer->isLogged();

		$state = array(
			'logged'            => $logged,
			'can_guest'         => (bool)$this->canGuest(),
			'shipping_required' => (bool)$this->cart->hasShipping(),
			'initialized'       => isset($this->session->data['payment_address']),
			'contact'           => $this->contact(),
			'address'           => $this->addressInput(),
			'comment'           => isset($this->session->data['comment']) ? $this->session->data['comment'] : '',
			'login_html'        => $logged ? '' : sprintf($this->language->get('text_mc_login'), $this->url->link('account/login', '', true))
		);

		if ($full) {
			$this->load->model('localisation/country');

			$state['countries'] = array();

			foreach ($this->model_localisation_country->getCountries() as $country) {
				$state['countries'][] = array(
					'country_id'        => (int)$country['country_id'],
					'name'              => $country['name'],
					'postcode_required' => (bool)$country['postcode_required']
				);
			}
		}

		$this->load->model('localisation/zone');

		$state['zones'] = array();

		foreach ($this->model_localisation_zone->getZonesByCountryId((int)$state['address']['country_id']) as $zone) {
			$state['zones'][] = array(
				'zone_id' => (int)$zone['zone_id'],
				'name'    => $zone['name']
			);
		}

		$shipping_service = new \MintCart\Commerce\ShippingMethodService();

		$state['shipping'] = array(
			'methods'  => $shipping_service->normalize(isset($this->session->data['shipping_methods']) ? $this->session->data['shipping_methods'] : array()),
			'selected' => isset($this->session->data['shipping_method']['code']) ? $this->session->data['shipping_method']['code'] : ''
		);

		$state['payment'] = array(
			'methods'  => array(),
			'selected' => isset($this->session->data['payment_method']['code']) ? $this->session->data['payment_method']['code'] : ''
		);

		if (isset($this->session->data['payment_methods'])) {
			foreach ($this->session->data['payment_methods'] as $method) {
				$state['payment']['methods'][] = array(
					'code'  => $method['code'],
					'title' => $method['title'],
					'terms' => isset($method['terms']) ? $method['terms'] : ''
				);
			}
		}

		$agree_info = $this->agreeInformation();

		$state['agree_html'] = $agree_info ? sprintf($this->language->get('text_agree'), $this->url->link('information/information', 'information_id=' . $agree_info['information_id'], true), $agree_info['title']) : '';

		// Coupon / gift certificate field: shown when either total is on.
		$state['codes'] = array(
			'enabled' => (bool)($this->config->get('total_coupon_status') || $this->config->get('total_voucher_status')),
			'applied' => array()
		);

		foreach (array('coupon', 'voucher') as $type) {
			if (!empty($this->session->data[$type])) {
				$state['codes']['applied'][] = array('type' => $type, 'code' => $this->session->data[$type]);
			}
		}

		$cart = $this->load->controller('mintcart/cart/snapshot');

		$state['items'] = $cart['items'];

		$totals_adapter = new \MintCart\Compatibility\OpenCart3\TotalsAdapter($this->config, $this->load, $this->registry);
		$totals_result = $totals_adapter->calculate($this->cart->getTaxes());

		$currency = $this->session->data['currency'];
		$format_money = function ($value) use ($currency) {
			return $this->currency->format($value, $currency);
		};

		$totals_service = new \MintCart\Commerce\CheckoutTotalsService();

		$state['totals'] = array(
			'lines' => $totals_service->normalize($totals_result['totals'], $format_money),
			'total' => $format_money($totals_result['total'])
		);

		return $state;
	}

	private function respond($json) {
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
