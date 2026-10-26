<?php
/**
 * Ukrposhta delivery: pick up at a post office. The customer picks the city
 * and the office in the checkout form this model supplies
 * (docs/developer/commerce/shipping-method-forms.md) from the extension's own
 * copy of the Ukrposhta directory (mc_ukrposhta_city / mc_ukrposhta_branch,
 * refilled from the extension's admin screen). The fee is paid to the carrier,
 * so the quote costs 0 in the order.
 */
class ModelExtensionShippingMcUkrposhta extends Model {
	public function getQuote($address) {
		if (!$this->config->get('shipping_mc_ukrposhta_status')) {
			return array();
		}

		// Nothing to choose from until the directory has been loaded in the admin.
		if (!$this->db->query("SELECT 1 FROM `" . DB_PREFIX . "mc_ukrposhta_branch` LIMIT 1")->num_rows) {
			return array();
		}

		$this->load->language('extension/shipping/mc_ukrposhta');

		$free_from = (float)$this->config->get('shipping_mc_ukrposhta_free_from');
		$note = $this->language->get('text_carrier_rates');

		if ($free_from > 0) {
			$note = $this->cart->getTotal() >= $free_from
				? $this->language->get('text_free')
				: sprintf($this->language->get('text_free_from'), $this->currency->format($free_from, $this->session->data['currency']));
		}

		return array(
			'code'       => 'mc_ukrposhta',
			'title'      => $this->language->get('text_title'),
			'quote'      => array(
				'branch' => array(
					'code'         => 'mc_ukrposhta.branch',
					'title'        => $this->language->get('text_method_branch'),
					'cost'         => 0,
					'tax_class_id' => 0,
					'text'         => $note
				)
			),
			'sort_order' => $this->config->get('shipping_mc_ukrposhta_sort_order'),
			'error'      => false
		);
	}

	/**
	 * @return array<int, array{id: string, name: string, text: string}>
	 */
	public function searchCities($term) {
		$term = trim((string)$term);
		$cities = array();

		if ($term === '') {
			return $cities;
		}

		$like = str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), $term);

		$query = $this->db->query("SELECT `city_ref`, `name`, `region_name` FROM `" . DB_PREFIX . "mc_ukrposhta_city` WHERE `name` LIKE '" . $this->db->escape($like) . "%' ORDER BY `name`, `region_name` LIMIT 30");

		foreach ($query->rows as $row) {
			$region = $row['region_name'];

			// The classifier stores the region as an adjective ("Вінницька").
			if ($region !== '' && $region !== $row['name'] && mb_stripos($region, 'область') === false) {
				$region .= ' область';
			}

			$cities[] = array(
				'id'   => $row['city_ref'],
				'name' => $row['name'],
				'text' => ($region !== '' && $region !== $row['name']) ? $row['name'] . ' (' . $region . ')' : $row['name']
			);
		}

		// Remember what was offered: processCheckout() resolves the posted
		// city id against this, never against text sent by the browser.
		$seen = isset($this->session->data['mc_ukrposhta_cities']) ? $this->session->data['mc_ukrposhta_cities'] : array();

		foreach ($cities as $city) {
			$seen[$city['id']] = array('name' => $city['name'], 'text' => $city['text']);
		}

		$this->session->data['mc_ukrposhta_cities'] = array_slice($seen, -60, null, true);

		return $cities;
	}

	/**
	 * @return array<int, array{id: string, text: string}> post offices of a city
	 */
	public function getPoints($city_ref) {
		$query = $this->db->query("SELECT `post_code`, `description` FROM `" . DB_PREFIX . "mc_ukrposhta_branch` WHERE `city_ref` = '" . $this->db->escape((string)$city_ref) . "' ORDER BY `post_code`");

		$points = array();

		foreach ($query->rows as $row) {
			$points[] = array('id' => $row['post_code'], 'text' => $row['description']);
		}

		return $points;
	}

	public function getCheckoutForm($quote_key) {
		if ($quote_key !== 'branch') {
			return '';
		}

		$this->load->language('extension/shipping/mc_ukrposhta');

		$stored = isset($this->session->data['mc_ukrposhta']) ? $this->session->data['mc_ukrposhta'] : array();
		$route = 'index.php?route=extension/shipping/mc_ukrposhta/';

		return $this->load->view('extension/shipping/mc_carrier_points', array(
			'method'        => 'branch',
			'prefix'        => 'up',
			'cities_url'    => $route . 'cities',
			'points_url'    => $route . 'points',
			'city_ref'      => isset($stored['city_ref']) ? $stored['city_ref'] : '',
			'city_text'     => isset($stored['city_text']) ? $stored['city_text'] : '',
			'point_id'      => isset($stored['point_id']) ? $stored['point_id'] : '',
			'courier'       => '',
			'script'        => 'catalog/view/javascript/mintcart/carrier-points.js',
			'label_city'    => $this->language->get('text_city'),
			'label_point'   => $this->language->get('text_branch'),
			'label_courier' => '',
			'hint_city'     => $this->language->get('text_city_hint'),
			'text_choose'   => $this->language->get('text_choose'),
			'text_loading'  => $this->language->get('text_loading'),
			'text_none'     => $this->language->get('text_no_results')
		));
	}

	public function processCheckout($quote_key, $post, $address) {
		$this->load->language('extension/shipping/mc_ukrposhta');

		$result = array('errors' => array(), 'address' => array());

		if ($quote_key !== 'branch') {
			return $result;
		}

		$stored = isset($this->session->data['mc_ukrposhta']) ? $this->session->data['mc_ukrposhta'] : array();
		$seen = isset($this->session->data['mc_ukrposhta_cities']) ? $this->session->data['mc_ukrposhta_cities'] : array();

		$city_ref = isset($post['up_city']) ? trim((string)$post['up_city']) : (isset($stored['city_ref']) ? $stored['city_ref'] : '');

		if ($city_ref === '' || !isset($seen[$city_ref])) {
			$city_ref = '';
		}

		$stored['city_ref'] = $city_ref;
		$stored['city_text'] = $city_ref !== '' ? $seen[$city_ref]['text'] : '';

		$point_id = '';
		$point_text = '';

		if ($city_ref === '') {
			$result['errors']['up_city'] = $this->language->get('error_city');
		} else {
			$result['address']['city'] = $seen[$city_ref]['name'];

			$posted = isset($post['up_point']) ? trim((string)$post['up_point']) : (isset($stored['point_id']) ? $stored['point_id'] : '');

			// The office must belong to the chosen city.
			foreach ($this->getPoints($city_ref) as $point) {
				if ($point['id'] === $posted) {
					$point_id = $point['id'];
					$point_text = $point['text'];
				}
			}

			if ($point_id === '') {
				$result['errors']['up_point'] = $this->language->get('error_point');
			} else {
				$result['address']['address_1'] = utf8_substr($point_text, 0, 128);
			}
		}

		$stored['point_id'] = $point_id;

		$this->session->data['mc_ukrposhta'] = $stored;

		return $result;
	}
}
