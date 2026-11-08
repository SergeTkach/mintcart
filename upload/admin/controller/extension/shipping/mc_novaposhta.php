<?php
class ControllerExtensionShippingMcNovaposhta extends Controller {
	private $error = array();

	private $keys = array('status', 'api_key', 'free_from', 'sort_order');

	public function index() {
		$this->load->language('extension/shipping/mc_novaposhta');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$values = $this->model_setting_setting->getSetting('shipping_mc_novaposhta');

			foreach ($this->keys as $key) {
				$values['shipping_mc_novaposhta_' . $key] = trim((string)($this->request->post['shipping_mc_novaposhta_' . $key] ?? ''));
			}

			$this->model_setting_setting->editSetting('shipping_mc_novaposhta', $values);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping', true));
		}

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
		$data['error_api_key'] = isset($this->error['api_key']) ? $this->error['api_key'] : '';

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
				'href' => $this->url->link('extension/shipping/mc_novaposhta', 'user_token=' . $this->session->data['user_token'], true)
			)
		);

		$data['action'] = $this->url->link('extension/shipping/mc_novaposhta', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping', true);

		$data['sync_url'] = html_entity_decode($this->url->link('extension/shipping/mc_novaposhta/sync', 'user_token=' . $this->session->data['user_token'], true), ENT_QUOTES, 'UTF-8');

		foreach ($this->keys as $key) {
			$name = 'shipping_mc_novaposhta_' . $key;

			$data[$name] = isset($this->request->post[$name]) ? $this->request->post[$name] : $this->config->get($name);
		}

		$data['directory_status'] = $this->directoryStatus();

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/shipping/mc_novaposhta', $data));
	}

	private function directoryStatus() {
		$counts = $this->db->query("SELECT (SELECT COUNT(*) FROM `" . DB_PREFIX . "mc_novaposhta_city`) AS cities, (SELECT COUNT(*) FROM `" . DB_PREFIX . "mc_novaposhta_warehouse`) AS points")->row;

		return $counts['points']
			? sprintf($this->language->get('text_directory_loaded'), $counts['points'], $counts['cities'], (string)$this->config->get('shipping_mc_novaposhta_synced'))
			: $this->language->get('text_directory_empty');
	}

	/**
	 * route=extension/shipping/mc_novaposhta/sync - POST step, page, batch.
	 *
	 * The screen calls it page after page: step "cities" and "warehouses" write
	 * one API page each (rows tagged with `batch`), then step "finish" deletes
	 * the rows of older refreshes and stamps the time. A page that fails leaves
	 * the stored data as it was.
	 */
	public function sync() {
		$this->load->language('extension/shipping/mc_novaposhta');

		$step = (string)($this->request->post['step'] ?? '');
		$page = max(1, (int)($this->request->post['page'] ?? 1));
		$batch = (int)($this->request->post['batch'] ?? 0);

		$result = array('ok' => false, 'last' => true, 'count' => 0, 'message' => '');

		if (!$this->user->hasPermission('modify', 'extension/shipping/mc_novaposhta')) {
			$result['message'] = $this->language->get('error_permission');
		} elseif ($batch <= 0 || !in_array($step, array('cities', 'warehouses', 'finish'), true)) {
			$result['message'] = $this->language->get('error_api');
		} else {
			set_time_limit(0);

			$result = $step === 'finish' ? $this->finishSync($batch) : $this->syncPage($step, $page, $batch);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($result));
	}

	private function syncPage($step, $page, $batch) {
		$client = $this->client();
		$result = array('ok' => false, 'last' => true, 'count' => 0, 'message' => $this->language->get('error_api'));

		if ($step === 'cities') {
			$areas = $client->getAreas();
			$data = $client->getCitiesPage($page);

			// An empty first page is an API failure: keep what is stored.
			if (!$data['raw'] && $page === 1) {
				return $result;
			}

			foreach ($data['rows'] as $row) {
				$area = isset($areas[$row['area_ref']]) ? $areas[$row['area_ref']] : array('name' => '', 'name_ru' => '');

				$this->db->query("REPLACE INTO `" . DB_PREFIX . "mc_novaposhta_city` SET `city_ref` = '" . $this->db->escape($row['city_ref']) . "', `name` = '" . $this->db->escape(utf8_substr($row['name'], 0, 128)) . "', `name_ru` = '" . $this->db->escape(utf8_substr($row['name_ru'], 0, 128)) . "', `area` = '" . $this->db->escape($area['name'] !== '' ? utf8_substr($area['name'] . ' обл.', 0, 128) : '') . "', `area_ru` = '" . $this->db->escape($area['name_ru'] !== '' ? utf8_substr($area['name_ru'] . ' обл.', 0, 128) : '') . "', `sync_id` = '" . (int)$batch . "'");
			}
		} else {
			$data = $client->getWarehousesPage($page);

			if (!$data['raw'] && $page === 1) {
				return $result;
			}

			foreach ($data['rows'] as $row) {
				$this->db->query("REPLACE INTO `" . DB_PREFIX . "mc_novaposhta_warehouse` SET `point_ref` = '" . $this->db->escape($row['point_ref']) . "', `city_ref` = '" . $this->db->escape($row['city_ref']) . "', `number` = '" . (int)$row['number'] . "', `postomat` = '" . (int)$row['postomat'] . "', `description` = '" . $this->db->escape(utf8_substr($row['description'], 0, 255)) . "', `description_ru` = '" . $this->db->escape(utf8_substr($row['description_ru'], 0, 255)) . "', `sync_id` = '" . (int)$batch . "'");
			}
		}

		return array('ok' => true, 'last' => $data['raw'] < \MintCart\Shipping\NovaPoshtaClient::PAGE_SIZE, 'count' => count($data['rows']), 'message' => '');
	}

	private function finishSync($batch) {
		foreach (array('city', 'warehouse') as $table) {
			$name = DB_PREFIX . 'mc_novaposhta_' . $table;

			// Only a table this refresh wrote to is cleaned.
			if ($this->db->query("SELECT 1 FROM `" . $name . "` WHERE `sync_id` = '" . (int)$batch . "' LIMIT 1")->num_rows) {
				$this->db->query("DELETE FROM `" . $name . "` WHERE `sync_id` != '" . (int)$batch . "'");
			}
		}

		$this->load->model('setting/setting');
		$this->model_setting_setting->editSettingValue('shipping_mc_novaposhta', 'shipping_mc_novaposhta_synced', date('Y-m-d H:i'));

		return array('ok' => true, 'last' => true, 'count' => 0, 'message' => $this->directoryStatus());
	}

	private function client() {
		return new \MintCart\Shipping\NovaPoshtaClient((string)$this->config->get('shipping_mc_novaposhta_api_key'), function ($url, $json) {
			$curl = curl_init($url);

			curl_setopt_array($curl, array(
				CURLOPT_POST           => true,
				CURLOPT_POSTFIELDS     => $json,
				CURLOPT_HTTPHEADER     => array('Content-Type: application/json'),
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_CONNECTTIMEOUT => 5,
				CURLOPT_TIMEOUT        => 60
			));

			$body = curl_exec($curl);

			curl_close($curl);

			return $body === false ? null : $body;
		});
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/shipping/mc_novaposhta')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		$free_from = trim((string)($this->request->post['shipping_mc_novaposhta_free_from'] ?? ''));

		if ($free_from !== '' && (!is_numeric($free_from) || (float)$free_from < 0)) {
			$this->error['warning'] = $this->language->get('error_free_from');
		}

		if (!empty($this->request->post['shipping_mc_novaposhta_status']) && trim((string)($this->request->post['shipping_mc_novaposhta_api_key'] ?? '')) === '') {
			$this->error['api_key'] = $this->language->get('error_api_key');
		}

		return !$this->error;
	}
}
