<?php
/**
 * Nova Poshta delivery: pick up at a branch, at a parcel locker, or by courier.
 * The customer picks the city and the point in the checkout form this model
 * supplies (docs/developer/commerce/shipping-method-forms.md); cities and
 * points come from the local directory (`mc_novaposhta_city` / `_warehouse`),
 * refilled from the Nova Poshta API on the extension's admin screen. The
 * delivery fee is paid to the carrier, so the quotes cost 0 in the order.
 */
class ModelExtensionShippingMcNovaposhta extends Model {
	const METHODS = array('warehouse', 'postomat', 'courier');

	public function getQuote($address) {
		if (!$this->config->get('shipping_mc_novaposhta_status') || !$this->hasDirectory()) {
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

	public function hasDirectory() {
		return (bool)$this->db->query("SELECT 1 FROM `" . DB_PREFIX . "mc_novaposhta_warehouse` LIMIT 1")->num_rows;
	}

	// Russian-language storefronts get the Russian names; everything else Ukrainian.
	private function russian() {
		return strpos((string)$this->config->get('config_language'), 'ru') === 0;
	}

	private function cityRow($row) {
		$ru = $this->russian();
		$name = ($ru && $row['name_ru'] !== '') ? $row['name_ru'] : $row['name'];
		$area = ($ru && $row['area_ru'] !== '') ? $row['area_ru'] : $row['area'];

		return array(
			'id'   => $row['city_ref'],
			'name' => $name,
			'text' => $area !== '' ? $name . ', ' . $area : $name
		);
	}

	/**
	 * @return list<array{id: string, name: string, text: string}>
	 */
	public function searchCities($term) {
		$term = trim((string)$term);

		if ($term === '') {
			return array();
		}

		$like = $this->db->escape(addcslashes(utf8_strtolower($term), '%_\\')) . '%';

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "mc_novaposhta_city` WHERE `name` LIKE '" . $like . "' OR `name_ru` LIKE '" . $like . "' ORDER BY CHAR_LENGTH(`name`), `name`, `area` LIMIT 20");

		$cities = array();

		foreach ($query->rows as $row) {
			$cities[] = $this->cityRow($row);
		}

		return $cities;
	}

	// A city by ref, or null: the posted ref is always resolved here, never trusted.
	public function getCity($city_ref) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "mc_novaposhta_city` WHERE `city_ref` = '" . $this->db->escape((string)$city_ref) . "'");

		return $query->num_rows ? $this->cityRow($query->row) : null;
	}

	private function pointRow($row) {
		$text = ($this->russian() && $row['description_ru'] !== '') ? $row['description_ru'] : $row['description'];

		return array('id' => $row['point_ref'], 'text' => $text);
	}

	/**
	 * @return list<array{id: string, text: string}> branches or lockers of a city
	 */
	public function getPoints($city_ref, $type) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "mc_novaposhta_warehouse` WHERE `city_ref` = '" . $this->db->escape((string)$city_ref) . "' AND `postomat` = '" . (int)($type === 'postomat') . "' ORDER BY `number`, `description`");

		$result = array();

		foreach ($query->rows as $row) {
			$result[] = $this->pointRow($row);
		}

		return $result;
	}

	// A branch/locker by ref that belongs to the city and the chosen type, or null.
	public function getPoint($point_ref, $city_ref, $type) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "mc_novaposhta_warehouse` WHERE `point_ref` = '" . $this->db->escape((string)$point_ref) . "' AND `city_ref` = '" . $this->db->escape((string)$city_ref) . "' AND `postomat` = '" . (int)($type === 'postomat') . "'");

		return $query->num_rows ? $this->pointRow($query->row) : null;
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
			'prefix'        => 'np',
			'cities_url'    => $route . 'cities',
			'points_url'    => $route . 'points',
			'city_ref'      => isset($stored['city_ref']) ? $stored['city_ref'] : '',
			'city_text'     => isset($stored['city_text']) ? $stored['city_text'] : '',
			'point_id'      => isset($stored['point_id']) ? $stored['point_id'] : '',
			'courier'       => isset($stored['courier']) ? $stored['courier'] : '',
			'script'        => 'catalog/view/javascript/mintcart/carrier-points.js',
			'label_city'    => $this->language->get('text_city'),
			'label_point'   => $this->language->get($quote_key === 'postomat' ? 'text_postomat' : 'text_warehouse'),
			'label_courier' => $this->language->get('text_courier_address'),
			'hint_city'     => $this->language->get('text_city_hint'),
			'text_choose'   => $this->language->get('text_choose'),
			'text_loading'  => $this->language->get('text_loading'),
			'text_none'     => $this->language->get('text_no_results')
		);

		return $this->load->view('extension/shipping/mc_carrier_points', $data);
	}

	public function processCheckout($quote_key, $post, $address) {
		$this->load->language('extension/shipping/mc_novaposhta');

		$result = array('errors' => array(), 'address' => array());

		if (!in_array($quote_key, self::METHODS, true)) {
			return $result;
		}

		$stored = isset($this->session->data['mc_novaposhta']) ? $this->session->data['mc_novaposhta'] : array();

		$city_ref = isset($post['np_city']) ? trim((string)$post['np_city']) : (isset($stored['city_ref']) ? $stored['city_ref'] : '');
		$city = $city_ref !== '' ? $this->getCity($city_ref) : null;

		if (!$city) {
			$city_ref = '';
		}

		$stored['city_ref'] = $city_ref;
		$stored['city_text'] = $city ? $city['text'] : '';

		if (!$city_ref) {
			$stored['point_id'] = '';
			$stored['point_text'] = '';
			$result['errors']['np_city'] = $this->language->get('error_city');
		} else {
			$result['address']['city'] = $city['name'];
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

			$point = $point_id !== '' ? $this->getPoint($point_id, $city_ref, $quote_key) : null;

			$stored['point_text'] = $point ? $point['text'] : '';
			$stored['point_type'] = $quote_key;
			$stored['point_city'] = $city_ref;

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
}
