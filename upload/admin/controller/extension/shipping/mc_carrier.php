<?php
/**
 * Delivery services without an API: a list of services (name, price, zone, optional
 * "the customer types the pickup point"). One extension covers the long tail of
 * carriers (DHL, Hermes, Correos, UPS, ...); a carrier that needs a real
 * integration (live points, tariffs) gets its own extension instead.
 */
class ControllerExtensionShippingMcCarrier extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/shipping/mc_carrier');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		$services = $this->services();

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$values = $this->model_setting_setting->getSetting('shipping_mc_carrier');

			$values['shipping_mc_carrier_status'] = !empty($this->request->post['shipping_mc_carrier_status']) ? '1' : '0';
			$values['shipping_mc_carrier_sort_order'] = trim((string)($this->request->post['shipping_mc_carrier_sort_order'] ?? ''));
			$values['shipping_mc_carrier_services'] = json_encode($this->postedServices(), JSON_UNESCAPED_UNICODE);

			$this->model_setting_setting->editSetting('shipping_mc_carrier', $values);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping', true));
		}

		if ($this->request->server['REQUEST_METHOD'] == 'POST') {
			$services = $this->postedServices();
		}

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';

		$data['breadcrumbs'] = array(
			array(
				'text' => $this->language->get('text_home'),
				'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
			),
			array(
				'text' => $this->language->get('text_extension'),
				'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping', true)
			),
			array(
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link('extension/shipping/mc_carrier', 'user_token=' . $this->session->data['user_token'], true)
			)
		);

		$data['action'] = $this->url->link('extension/shipping/mc_carrier', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping', true);

		$data['shipping_mc_carrier_status'] = isset($this->request->post['shipping_mc_carrier_status']) ? $this->request->post['shipping_mc_carrier_status'] : $this->config->get('shipping_mc_carrier_status');
		$data['shipping_mc_carrier_sort_order'] = isset($this->request->post['shipping_mc_carrier_sort_order']) ? $this->request->post['shipping_mc_carrier_sort_order'] : $this->config->get('shipping_mc_carrier_sort_order');
		$data['services'] = $services;

		$this->load->model('localisation/geo_zone');
		$this->load->model('localisation/tax_class');

		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();
		$data['tax_classes'] = $this->model_localisation_tax_class->getTaxClasses();

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/shipping/mc_carrier', $data));
	}

	// The saved services (a JSON list), or none.
	private function services() {
		$services = json_decode((string)$this->config->get('shipping_mc_carrier_services'), true);

		return is_array($services) ? array_values($services) : array();
	}

	// The posted rows, cleaned: a row without a name is dropped, every id is kept or made.
	private function postedServices() {
		$rows = isset($this->request->post['service']) && is_array($this->request->post['service']) ? $this->request->post['service'] : array();
		$services = array();
		$used = array();

		foreach ($rows as $row) {
			$name = utf8_substr(trim(strip_tags((string)($row['name'] ?? ''))), 0, 64);

			if ($name === '') {
				continue;
			}

			$id = preg_match('/^s[0-9a-f]{6}$/', (string)($row['id'] ?? '')) ? $row['id'] : 's' . substr(md5(uniqid('', true)), 0, 6);

			if (isset($used[$id])) {
				continue;
			}

			$used[$id] = true;

			$services[] = array(
				'id'           => $id,
				'name'         => $name,
				'cost'         => (string)(float)($row['cost'] ?? 0),
				'tax_class_id' => (int)($row['tax_class_id'] ?? 0),
				'geo_zone_id'  => (int)($row['geo_zone_id'] ?? 0),
				'free_from'    => trim((string)($row['free_from'] ?? '')) === '' ? '' : (string)(float)$row['free_from'],
				'point'        => !empty($row['point']) ? 1 : 0
			);
		}

		return $services;
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/shipping/mc_carrier')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		$rows = isset($this->request->post['service']) && is_array($this->request->post['service']) ? $this->request->post['service'] : array();

		foreach ($rows as $row) {
			foreach (array('cost', 'free_from') as $field) {
				$value = trim((string)($row[$field] ?? ''));

				if (trim((string)($row['name'] ?? '')) !== '' && $value !== '' && (!is_numeric($value) || (float)$value < 0)) {
					$this->error['warning'] = $this->language->get('error_number');
				}
			}
		}

		return !$this->error;
	}
}
