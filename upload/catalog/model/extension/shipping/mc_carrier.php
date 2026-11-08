<?php
/**
 * Delivery services without an API (admin: extension/shipping/mc_carrier): each
 * configured service is one delivery option with a fixed price, limited to a geo
 * zone. A service marked "point" asks the customer for the pickup point (a
 * locker or shop number and the city) in a small form: nothing is looked up, the
 * text goes into the order's delivery address as typed.
 */
class ModelExtensionShippingMcCarrier extends Model {
	private function services() {
		$services = json_decode((string)$this->config->get('shipping_mc_carrier_services'), true);

		return is_array($services) ? $services : array();
	}

	private function service($id) {
		foreach ($this->services() as $service) {
			if ($service['id'] === $id) {
				return $service;
			}
		}

		return null;
	}

	private function inZone($geo_zone_id, $address) {
		if (!$geo_zone_id) {
			return true;
		}

		$query = $this->db->query("SELECT 1 FROM `" . DB_PREFIX . "zone_to_geo_zone` WHERE `geo_zone_id` = '" . (int)$geo_zone_id . "' AND `country_id` = '" . (int)$address['country_id'] . "' AND (`zone_id` = '" . (int)$address['zone_id'] . "' OR `zone_id` = '0')");

		return (bool)$query->num_rows;
	}

	public function getQuote($address) {
		if (!$this->config->get('shipping_mc_carrier_status')) {
			return array();
		}

		$this->load->language('extension/shipping/mc_carrier');

		$quote_data = array();

		foreach ($this->services() as $service) {
			if (!$this->inZone($service['geo_zone_id'], $address)) {
				continue;
			}

			$cost = (float)$service['cost'];

			if ((float)$service['free_from'] > 0 && $this->cart->getSubTotal() >= (float)$service['free_from']) {
				$cost = 0;
			}

			$quote_data[$service['id']] = array(
				'code'         => 'mc_carrier.' . $service['id'],
				'title'        => $service['name'],
				'cost'         => $cost,
				'tax_class_id' => (int)$service['tax_class_id'],
				'text'         => $cost > 0
					? $this->currency->format($this->tax->calculate($cost, (int)$service['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency'])
					: $this->language->get('text_free')
			);
		}

		if (!$quote_data) {
			return array();
		}

		return array(
			'code'       => 'mc_carrier',
			'title'      => $this->language->get('text_title'),
			'quote'      => $quote_data,
			'sort_order' => $this->config->get('shipping_mc_carrier_sort_order'),
			'error'      => false
		);
	}

	public function getCheckoutForm($quote_key) {
		$service = $this->service($quote_key);

		if (!$service || empty($service['point'])) {
			return '';
		}

		$this->load->language('extension/shipping/mc_carrier');

		$stored = isset($this->session->data['mc_carrier']) ? $this->session->data['mc_carrier'] : array();

		return $this->load->view('extension/shipping/mc_carrier_point', array(
			'city'        => isset($stored['city']) ? $stored['city'] : '',
			'point'       => isset($stored['point']) ? $stored['point'] : '',
			'label_city'  => $this->language->get('text_city'),
			'label_point' => $this->language->get('text_point'),
			'hint_point'  => $this->language->get('text_point_hint')
		));
	}

	public function processCheckout($quote_key, $post, $address) {
		$this->load->language('extension/shipping/mc_carrier');

		$result = array('errors' => array(), 'address' => array());
		$service = $this->service($quote_key);

		if (!$service || empty($service['point'])) {
			return $result;
		}

		$stored = isset($this->session->data['mc_carrier']) ? $this->session->data['mc_carrier'] : array();

		$city = isset($post['carrier_city']) ? trim(strip_tags((string)$post['carrier_city'])) : (isset($stored['city']) ? $stored['city'] : '');
		$point = isset($post['carrier_point']) ? trim(strip_tags((string)$post['carrier_point'])) : (isset($stored['point']) ? $stored['point'] : '');

		$this->session->data['mc_carrier'] = array('city' => $city, 'point' => $point);

		if (utf8_strlen($city) < 2) {
			$result['errors']['carrier_city'] = $this->language->get('error_city');
		} else {
			$result['address']['city'] = utf8_substr($city, 0, 128);
		}

		if (utf8_strlen($point) < 2) {
			$result['errors']['carrier_point'] = $this->language->get('error_point');
		} else {
			$result['address']['address_1'] = utf8_substr($service['name'] . ': ' . $point, 0, 128);
		}

		return $result;
	}
}
