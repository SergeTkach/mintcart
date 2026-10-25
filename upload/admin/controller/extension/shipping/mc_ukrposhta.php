<?php
class ControllerExtensionShippingMcUkrposhta extends Controller {
	private $error = array();

	private $keys = array('status', 'api_key', 'free_from', 'sort_order');

	public function index() {
		$this->load->language('extension/shipping/mc_ukrposhta');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$values = $this->model_setting_setting->getSetting('shipping_mc_ukrposhta');

			foreach ($this->keys as $key) {
				$values['shipping_mc_ukrposhta_' . $key] = trim((string)($this->request->post['shipping_mc_ukrposhta_' . $key] ?? ''));
			}

			$this->model_setting_setting->editSetting('shipping_mc_ukrposhta', $values);

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
				'href' => $this->url->link('extension/shipping/mc_ukrposhta', 'user_token=' . $this->session->data['user_token'], true)
			)
		);

		$data['action'] = $this->url->link('extension/shipping/mc_ukrposhta', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping', true);
		$data['regions_url'] = html_entity_decode($this->url->link('extension/shipping/mc_ukrposhta/regions', 'user_token=' . $this->session->data['user_token'], true), ENT_QUOTES, 'UTF-8');
		$data['sync_url'] = html_entity_decode($this->url->link('extension/shipping/mc_ukrposhta/sync', 'user_token=' . $this->session->data['user_token'], true), ENT_QUOTES, 'UTF-8');

		foreach ($this->keys as $key) {
			$name = 'shipping_mc_ukrposhta_' . $key;

			$data[$name] = isset($this->request->post[$name]) ? $this->request->post[$name] : $this->config->get($name);
		}

		$counts = $this->db->query("SELECT (SELECT COUNT(*) FROM `" . DB_PREFIX . "mc_ukrposhta_city`) AS cities, (SELECT COUNT(*) FROM `" . DB_PREFIX . "mc_ukrposhta_branch`) AS branches")->row;

		$data['directory_status'] = $counts['branches']
			? sprintf($this->language->get('text_directory_loaded'), $counts['branches'], $counts['cities'], (string)$this->config->get('shipping_mc_ukrposhta_synced'))
			: $this->language->get('text_directory_empty');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/shipping/mc_ukrposhta', $data));
	}

	// route=extension/shipping/mc_ukrposhta/regions - JSON list of regions to load
	public function regions() {
		$this->load->language('extension/shipping/mc_ukrposhta');

		$result = array('ok' => false, 'regions' => array(), 'message' => '');

		if (!$this->user->hasPermission('modify', 'extension/shipping/mc_ukrposhta')) {
			$result['message'] = $this->language->get('error_permission');
		} else {
			$result['regions'] = $this->client()->getRegions();
			$result['ok'] = (bool)$result['regions'];
			$result['message'] = $result['ok'] ? '' : $this->language->get('error_api');
		}

		$this->respond($result);
	}

	// route=extension/shipping/mc_ukrposhta/sync - POST region_id, region_name; replaces that region's data
	public function sync() {
		$this->load->language('extension/shipping/mc_ukrposhta');

		$result = array('ok' => false, 'cities' => 0, 'branches' => 0, 'message' => '');

		$region_id = (int)($this->request->post['region_id'] ?? 0);
		$region_name = utf8_substr(trim(strip_tags((string)($this->request->post['region_name'] ?? ''))), 0, 128);

		if (!$this->user->hasPermission('modify', 'extension/shipping/mc_ukrposhta')) {
			$result['message'] = $this->language->get('error_permission');
		} elseif ($region_id <= 0) {
			$result['message'] = $this->language->get('error_api');
		} else {
			set_time_limit(0);

			$directory = $this->client()->getRegionDirectory($region_id);

			// An empty answer keeps the data already stored for the region.
			if (!$directory['branches']) {
				$result['message'] = $this->language->get('error_api');
			} else {
				$this->store($region_id, $region_name, $directory);

				$this->load->model('setting/setting');
				$this->model_setting_setting->editSettingValue('shipping_mc_ukrposhta', 'shipping_mc_ukrposhta_synced', date('Y-m-d H:i'));

				$result = array('ok' => true, 'cities' => count($directory['cities']), 'branches' => count($directory['branches']), 'message' => '');
			}
		}

		$this->respond($result);
	}

	private function store($region_id, $region_name, $directory) {
		$prefix = DB_PREFIX . 'mc_ukrposhta_';

		$this->db->query("START TRANSACTION");

		$this->db->query("DELETE b FROM `" . $prefix . "branch` b INNER JOIN `" . $prefix . "city` c ON c.`city_ref` = b.`city_ref` WHERE c.`region_id` = '" . (int)$region_id . "'");
		$this->db->query("DELETE FROM `" . $prefix . "city` WHERE `region_id` = '" . (int)$region_id . "'");

		foreach ($directory['cities'] as $ref => $city) {
			$this->db->query("INSERT INTO `" . $prefix . "city` SET `city_ref` = '" . $this->db->escape($ref) . "', `region_id` = '" . (int)$region_id . "', `region_name` = '" . $this->db->escape($region_name) . "', `district` = '" . $this->db->escape($city['district']) . "', `name` = '" . $this->db->escape($city['name']) . "'");
		}

		foreach ($directory['branches'] as $branch) {
			$this->db->query("REPLACE INTO `" . $prefix . "branch` SET `post_code` = '" . $this->db->escape($branch['post_code']) . "', `city_ref` = '" . $this->db->escape($branch['city_ref']) . "', `description` = '" . $this->db->escape(utf8_substr($branch['description'], 0, 255)) . "', `phone` = '" . $this->db->escape(utf8_substr($branch['phone'], 0, 64)) . "'");
		}

		$this->db->query("COMMIT");
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/shipping/mc_ukrposhta')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		$free_from = trim((string)($this->request->post['shipping_mc_ukrposhta_free_from'] ?? ''));

		if ($free_from !== '' && (!is_numeric($free_from) || (float)$free_from < 0)) {
			$this->error['warning'] = $this->language->get('error_free_from');
		}

		if (!empty($this->request->post['shipping_mc_ukrposhta_status']) && trim((string)($this->request->post['shipping_mc_ukrposhta_api_key'] ?? '')) === '') {
			$this->error['api_key'] = $this->language->get('error_api_key');
		}

		return !$this->error;
	}

	private function client() {
		return new \MintCart\Shipping\UkrposhtaClient((string)$this->config->get('shipping_mc_ukrposhta_api_key'), function ($url, $headers) {
			$curl = curl_init($url);

			curl_setopt_array($curl, array(
				CURLOPT_HTTPHEADER     => $headers,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_CONNECTTIMEOUT => 5,
				CURLOPT_TIMEOUT        => 60
			));

			$body = curl_exec($curl);
			$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

			curl_close($curl);

			return ($body === false || $code !== 200) ? null : $body;
		});
	}

	private function respond($data) {
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($data));
	}
}
