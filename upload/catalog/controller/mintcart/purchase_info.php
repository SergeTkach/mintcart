<?php
/**
 * Delivery / payment / returns summary for the product page (Functional
 * Scope §11 "delivery/payment/return information on product page").
 *
 * Built from what the store already has configured, so it needs no extra
 * content to be useful on day one:
 * - delivery: enabled shipping extensions, by sort order (Free Shipping
 *   with its minimum order total);
 * - payment: enabled payment extensions, by sort order (free_checkout is
 *   left out - it only applies to zero-total orders);
 * - returns: the "Return Terms" information page (config_return_id), if set.
 *
 * Called with $this->load->controller('mintcart/purchase_info') and returns
 * an array; empty lists mean "don't show that row".
 */
class ControllerMintcartPurchaseInfo extends Controller {
	public function index() {
		return array(
			'delivery' => $this->methods('shipping', array()),
			'payment'  => $this->methods('payment', array('free_checkout')),
			'returns'  => $this->returnsPage()
		);
	}

	private function methods($type, array $skip) {
		$this->load->model('setting/extension');

		$methods = array();

		foreach ($this->model_setting_extension->getExtensions($type) as $extension) {
			$code = $extension['code'];

			if (in_array($code, $skip, true) || !$this->config->get($type . '_' . $code . '_status')) {
				continue;
			}

			$this->load->language('extension/' . $type . '/' . $code, 'mc_purchase_info');

			$title = $this->language->get('mc_purchase_info')->get('text_title');

			if (!$title || $title === 'text_title') {
				continue;
			}

			if ($type === 'shipping' && $code === 'free' && (float)$this->config->get('shipping_free_total') > 0) {
				$title .= ' (' . sprintf($this->language->get('text_mc_from'), $this->currency->format($this->config->get('shipping_free_total'), $this->session->data['currency'])) . ')';
			}

			$methods[] = array(
				'title'      => strip_tags(html_entity_decode($title, ENT_QUOTES, 'UTF-8')),
				'sort_order' => (int)$this->config->get($type . '_' . $code . '_sort_order')
			);
		}

		usort($methods, function ($a, $b) {
			return $a['sort_order'] <=> $b['sort_order'];
		});

		return array_column($methods, 'title');
	}

	private function returnsPage() {
		$information_id = (int)$this->config->get('config_return_id');

		if (!$information_id) {
			return null;
		}

		$this->load->model('catalog/information');

		$information = $this->model_catalog_information->getInformation($information_id);

		if (!$information) {
			return null;
		}

		return array(
			'title' => $information['title'],
			'href'  => $this->url->link('information/information', 'information_id=' . $information_id)
		);
	}
}
