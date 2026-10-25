<?php
/**
 * Nova Poshta delivery: pick up at a branch, at a parcel locker, or by courier.
 * The customer picks the city and the point in the checkout form this model
 * supplies (docs/developer/commerce/shipping-method-forms.md); cities and
 * points come live from the Nova Poshta API. The delivery fee is paid to the
 * carrier, so the quotes cost 0 in the order.
 */
class ModelExtensionShippingMcNovaposhta extends Model {
	const METHODS = array('warehouse', 'postomat', 'courier');

	public function getQuote($address) {
		if (!$this->config->get('shipping_mc_novaposhta_status') || !$this->config->get('shipping_mc_novaposhta_api_key')) {
			return array();
		}

		$this->load->language('extension/shipping/mc_novaposhta');

		$free_from = (float)$this->config->get('shipping_mc_novaposhta_free_from');
		$note = $this->language->get('text_carrier_rates');

		if ($free_from > 0) {
			$note = $this->cart->getTotal() >= $free_from
				? $this->language->get('text_free')
				: sprintf($this->language->get('text_free_from'), $this->currency->format($free_from, $this->session->data['currency']));
		}

		$quote_data = array();

		foreach (self::METHODS as $method) {
			$quote_data[$method] = array(
				'code'         => 'mc_novaposhta.' . $method,
				'title'        => $this->language->get('text_method_' . $method),
				'cost'         => 0,
				'tax_class_id' => 0,
				'text'         => $note
			);
		}

		return array(
			'code'       => 'mc_novaposhta',
			'title'      => $this->language->get('text_title'),
			'quote'      => $quote_data,
			'sort_order' => $this->config->get('shipping_mc_novaposhta_sort_order'),
			'error'      => false
		);
	}

	/**
	 * @return list<array{id: string, name: string, text: string}>
	 */
	public function searchCities($term) {
		$cities = $this->client()->searchCities((string)$term);

		// Remember what was offered: processCheckout() resolves the posted
		// city id against this, never against text sent by the browser.
		$seen = isset($this->session->data['mc_novaposhta_cities']) ? $this->session->data['mc_novaposhta_cities'] : array();

		foreach ($cities as $city) {
			$seen[$city['id']] = array('name' => $city['name'], 'text' => $city['text']);
		}

		$this->session->data['mc_novaposhta_cities'] = array_slice($seen, -60, null, true);

		return $cities;
	}

	/**
	 * @return list<array{id: string, text: string}> branches or lockers of a city
	 */
	public function getPoints($city_ref, $type) {
		$key = 'mc_np_points.' . strtr((string)$city_ref, '0123456789', 'ghijklmnop');
		$points = $this->cache->get($key);

		if (!is_array($points)) {
			$points = $this->client()->getPoints((string)$city_ref);

			if ($points) {
				$this->cache->set($key, $points);
			}
		}

		$result = array();

		foreach ($points as $point) {
			if ($point['postomat'] === ($type === 'postomat')) {
				$result[] = array('id' => $point['id'], 'text' => $point['text']);
			}
		}

		return $result;
	}

	public function getCheckoutForm($quote_key) {
		if (!in_array($quote_key, self::METHODS, true)) {
			return '';
		}

		$this->load->language('extension/shipping/mc_novaposhta');

		$stored = isset($this->session->data['mc_novaposhta']) ? $this->session->data['mc_novaposhta'] : array();
		$route = 'index.php?route=extension/shipping/mc_novaposhta/';

		$data = array(
			'method'        => $quote_key,
			'cities_url'    => $route . 'cities',
			'points_url'    => $route . 'points',
			'city_ref'      => isset($stored['city_ref']) ? $stored['city_ref'] : '',
			'city_text'     => isset($stored['city_text']) ? $stored['city_text'] : '',
			'point_id'      => isset($stored['point_id']) ? $stored['point_id'] : '',
			'courier'       => isset($stored['courier']) ? $stored['courier'] : '',
			'script'        => 'catalog/view/javascript/mintcart/novaposhta.js',
			'label_city'    => $this->language->get('text_city'),
			'label_point'   => $this->language->get($quote_key === 'postomat' ? 'text_postomat' : 'text_warehouse'),
			'label_courier' => $this->language->get('text_courier_address'),
			'hint_city'     => $this->language->get('text_city_hint'),
			'text_choose'   => $this->language->get('text_choose'),
			'text_loading'  => $this->language->get('text_loading'),
			'text_none'     => $this->language->get('text_no_results')
		);

		return $this->load->view('extension/shipping/mc_novaposhta', $data);
	}

	public function processCheckout($quote_key, $post, $address) {
		$this->load->language('extension/shipping/mc_novaposhta');

		$result = array('errors' => array(), 'address' => array());

		if (!in_array($quote_key, self::METHODS, true)) {
			return $result;
		}

		$stored = isset($this->session->data['mc_novaposhta']) ? $this->session->data['mc_novaposhta'] : array();
		$seen = isset($this->session->data['mc_novaposhta_cities']) ? $this->session->data['mc_novaposhta_cities'] : array();

		$city_ref = isset($post['np_city']) ? trim((string)$post['np_city']) : (isset($stored['city_ref']) ? $stored['city_ref'] : '');

		if ($city_ref === '' || !isset($seen[$city_ref])) {
			$city_ref = '';
		}

		$stored['city_ref'] = $city_ref;
		$stored['city_text'] = $city_ref !== '' ? $seen[$city_ref]['text'] : '';

		if (!$city_ref) {
			$stored['point_id'] = '';
			$stored['point_text'] = '';
			$result['errors']['np_city'] = $this->language->get('error_city');
		} else {
			$result['address']['city'] = $seen[$city_ref]['name'];
		}

		if ($quote_key === 'courier') {
			$stored['courier'] = isset($post['np_courier']) ? trim(strip_tags((string)$post['np_courier'])) : (isset($stored['courier']) ? $stored['courier'] : '');

			if (utf8_strlen($stored['courier']) < 3) {
				$result['errors']['np_courier'] = $this->language->get('error_courier');
			} else {
				$result['address']['address_1'] = utf8_substr($stored['courier'], 0, 128);
			}
		} elseif ($city_ref) {
			$point_id = isset($post['np_point']) ? trim((string)$post['np_point']) : (isset($stored['point_id']) ? $stored['point_id'] : '');

			$known = isset($stored['point_id'], $stored['point_text'], $stored['point_type'], $stored['point_city'])
				&& $stored['point_id'] === $point_id && $stored['point_type'] === $quote_key && $stored['point_city'] === $city_ref;

			// The point is resolved against the carrier's list only when it changed.
			if (!$known) {
				$stored['point_text'] = '';

				if ($point_id !== '') {
					foreach ($this->getPoints($city_ref, $quote_key) as $point) {
						if ($point['id'] === $point_id) {
							$stored['point_text'] = $point['text'];
						}
					}
				}

				$stored['point_type'] = $quote_key;
				$stored['point_city'] = $city_ref;
			}

			$stored['point_id'] = $stored['point_text'] !== '' ? $point_id : '';

			if ($stored['point_id'] === '') {
				$result['errors']['np_point'] = $this->language->get('error_point');
			} else {
				$result['address']['address_1'] = utf8_substr($stored['point_text'], 0, 128);
			}
		}

		$this->session->data['mc_novaposhta'] = $stored;

		return $result;
	}

	private function client() {
		return new \MintCart\Shipping\NovaPoshtaClient((string)$this->config->get('shipping_mc_novaposhta_api_key'), function ($url, $json) {
			$curl = curl_init($url);

			curl_setopt_array($curl, array(
				CURLOPT_POST           => true,
				CURLOPT_POSTFIELDS     => $json,
				CURLOPT_HTTPHEADER     => array('Content-Type: application/json'),
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_CONNECTTIMEOUT => 4,
				CURLOPT_TIMEOUT        => 8
			));

			$body = curl_exec($curl);

			curl_close($curl);

			return $body === false ? null : $body;
		});
	}
}
