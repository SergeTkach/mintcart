<?php
/**
 * InPost UK parcel lockers: the customer enters a postcode in the checkout form
 * this model supplies (docs/developer/commerce/shipping-method-forms.md) and picks
 * one of the nearest lockers, found live through the InPost points API
 * (MintCart\Shipping\InpostClient). The fee is paid to the carrier, so the quote
 * costs 0 in the order.
 *
 * The lockers the search returned are remembered in the session: the posted
 * locker is resolved against them, never against text sent by the browser.
 */
class ModelExtensionShippingMcInpostUk extends Model {
	const API_URL = 'https://api-uk-points.easypack24.net/v1';

	public function getQuote($address) {
		if (!$this->config->get('shipping_mc_inpost_uk_status')) {
			return array();
		}

		$this->load->language('extension/shipping/mc_inpost_uk');

		$free_from = (float)$this->config->get('shipping_mc_inpost_uk_free_from');
		$note = $this->language->get('text_carrier_rates');

		if ($free_from > 0) {
			$note = $this->cart->getTotal() >= $free_from
				? $this->language->get('text_free')
				: sprintf($this->language->get('text_free_from'), $this->currency->format($free_from, $this->session->data['currency']));
		}

		return array(
			'code'       => 'mc_inpost_uk',
			'title'      => $this->language->get('text_title'),
			'quote'      => array(
				'locker' => array(
					'code'         => 'mc_inpost_uk.locker',
					'title'        => $this->language->get('text_method_locker'),
					'cost'         => 0,
					'tax_class_id' => 0,
					'text'         => $note
				)
			),
			'sort_order' => $this->config->get('shipping_mc_inpost_uk_sort_order'),
			'error'      => false
		);
	}

	/**
	 * @return list<array{id: string, text: string}>
	 */
	public function searchPoints($postcode) {
		$found = $this->client()->searchPoints((string)$postcode);

		$seen = isset($this->session->data['mc_inpost_uk_seen']) ? $this->session->data['mc_inpost_uk_seen'] : array();
		$items = array();

		foreach ($found as $point) {
			$seen[$point['id']] = array('text' => $point['text'], 'city' => $point['city'], 'address' => $point['address']);
			$items[] = array('id' => $point['id'], 'text' => $point['text']);
		}

		$this->session->data['mc_inpost_uk_seen'] = array_slice($seen, -100, null, true);

		return $items;
	}

	public function getCheckoutForm($quote_key) {
		if ($quote_key !== 'locker') {
			return '';
		}

		$this->load->language('extension/shipping/mc_inpost_uk');

		$stored = isset($this->session->data['mc_inpost_uk']) ? $this->session->data['mc_inpost_uk'] : array();

		return $this->load->view('extension/shipping/mc_pickup_point', array(
			'prefix'       => 'inpost',
			'search_url'   => 'index.php?route=extension/shipping/mc_inpost_uk/search',
			'point_id'     => isset($stored['point_id']) ? $stored['point_id'] : '',
			'point_text'   => isset($stored['point_text']) ? $stored['point_text'] : '',
			'script'       => 'catalog/view/javascript/mintcart/carrier-points.js',
			'label_point'  => $this->language->get('text_locker'),
			'hint_search'  => $this->language->get('text_postcode_hint'),
			'text_choose'  => $this->language->get('text_choose'),
			'text_none'    => $this->language->get('text_no_results')
		));
	}

	public function processCheckout($quote_key, $post, $address) {
		$this->load->language('extension/shipping/mc_inpost_uk');

		$result = array('errors' => array(), 'address' => array());

		if ($quote_key !== 'locker') {
			return $result;
		}

		$seen = isset($this->session->data['mc_inpost_uk_seen']) ? $this->session->data['mc_inpost_uk_seen'] : array();
		$stored = isset($this->session->data['mc_inpost_uk']) ? $this->session->data['mc_inpost_uk'] : array();

		$point_id = isset($post['inpost_point']) ? trim((string)$post['inpost_point']) : (isset($stored['point_id']) ? $stored['point_id'] : '');

		if ($point_id === '' || !isset($seen[$point_id])) {
			$this->session->data['mc_inpost_uk'] = array('point_id' => '', 'point_text' => '');
			$result['errors']['inpost_point'] = $this->language->get('error_point');

			return $result;
		}

		$this->session->data['mc_inpost_uk'] = array('point_id' => $point_id, 'point_text' => $seen[$point_id]['text']);

		$result['address']['city'] = utf8_substr($seen[$point_id]['city'], 0, 128);
		$result['address']['address_1'] = utf8_substr('InPost ' . $point_id . ': ' . $seen[$point_id]['address'], 0, 128);

		return $result;
	}

	private function client() {
		return new \MintCart\Shipping\InpostClient(self::API_URL, (string)$this->config->get('shipping_mc_inpost_uk_api_key'), function ($url, $headers) {
			$curl = curl_init($url);

			curl_setopt_array($curl, array(
				CURLOPT_HTTPHEADER     => $headers,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_CONNECTTIMEOUT => 4,
				CURLOPT_TIMEOUT        => 8
			));

			$body = curl_exec($curl);
			$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

			curl_close($curl);

			return ($body === false || $code !== 200) ? null : $body;
		});
	}
}
