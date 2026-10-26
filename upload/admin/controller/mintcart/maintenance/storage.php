<?php
/**
 * System > Maintenance > Storage: where the storage directory (cache, logs,
 * sessions, uploads, PHP libraries) lives. A fresh install keeps it in the site
 * root, next to `system`; this screen moves it to `system/storage` or out of the
 * web root. The move itself is MintCart\Core\StorageLocation, the
 * same service the installers use.
 */
class ControllerMintcartMaintenanceStorage extends Controller {
	public function index() {
		$this->load->language('mintcart/maintenance/storage');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('mintcart/maintenance/storage', 'user_token=' . $this->session->data['user_token'], true)
		);

		$current = rtrim(str_replace('\\', '/', DIR_STORAGE), '/');
		$locations = $this->locations();

		$data['storage'] = $current;
		$data['current'] = 'custom';
		$data['locations'] = array();

		foreach ($locations as $key => $path) {
			if (strtolower($path) === strtolower($current)) {
				$data['current'] = $key;
			}

			$data['locations'][] = array(
				'key'  => $key,
				'path' => $path,
				'name' => $this->language->get('text_location_' . $key)
			);
		}

		$data['action'] = $this->url->link('mintcart/maintenance/storage/move', 'user_token=' . $this->session->data['user_token'], true);

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('mintcart/maintenance/storage', $data));
	}

	// route=mintcart/maintenance/storage/move - POST field[location] = system | root | outside
	public function move() {
		$this->load->language('mintcart/maintenance/storage');

		$json = array('ok' => false, 'message' => '', 'errors' => array());

		$locations = $this->locations();
		$location = isset($this->request->post['field']['location']) ? (string)$this->request->post['field']['location'] : '';
		$path = isset($locations[$location]) ? $locations[$location] : '';

		if (!$this->user->hasPermission('modify', 'mintcart/maintenance/storage')) {
			$json['message'] = $this->language->get('error_permission');
		} elseif ($path === '') {
			$json['errors']['location'] = $this->language->get('error_invalid');
		} else {
			$error = \MintCart\Core\StorageLocation::move(DIR_STORAGE, $path, array(
				$this->documentRoot() . '/config.php',
				DIR_APPLICATION . 'config.php'
			));

			if ($error !== null) {
				$json['errors']['location'] = $this->language->get('error_' . $error);
			} else {
				$json['ok'] = true;
				$json['message'] = sprintf($this->language->get('text_success'), $path);
			}
		}

		if (!$json['ok'] && !$json['message']) {
			$json['message'] = $this->language->get('error_failed');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * The three places the storage directory can live: next to `system` inside the site root (the default),
	 * inside `system` (stock OpenCart), or next to the site folder, out of the web server's reach.
	 */
	private function locations() {
		return array(
			'root'    => $this->documentRoot() . '/storage',
			'system'  => $this->documentRoot() . '/system/storage',
			'outside' => \MintCart\Core\StorageLocation::suggest($this->documentRoot())
		);
	}

	private function documentRoot() {
		return rtrim(str_replace('\\', '/', realpath(DIR_APPLICATION . '../')), '/');
	}
}
