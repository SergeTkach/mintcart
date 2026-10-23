<?php
/**
 * System > Maintenance > Storage: where the storage directory (cache, logs,
 * sessions, uploads, PHP libraries) lives, and moving it out of the web root.
 *
 * A fresh MintCart install already puts it there (see the installers); this
 * screen is for stores that were installed before, or on a host where the
 * installer could not. The move itself is MintCart\Core\StorageLocation, the
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

		$documentRoot = $this->documentRoot();

		$data['storage'] = rtrim(str_replace('\\', '/', DIR_STORAGE), '/');
		$data['inside'] = \MintCart\Core\StorageLocation::isInside(DIR_STORAGE, $documentRoot);
		$data['suggested'] = \MintCart\Core\StorageLocation::suggest($documentRoot);
		$data['action'] = $this->url->link('mintcart/maintenance/storage/move', 'user_token=' . $this->session->data['user_token'], true);

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('mintcart/maintenance/storage', $data));
	}

	// route=mintcart/maintenance/storage/move - POST field[path]
	public function move() {
		$this->load->language('mintcart/maintenance/storage');

		$json = array('ok' => false, 'message' => '', 'errors' => array());

		$path = trim((string)(isset($this->request->post['field']['path']) ? $this->request->post['field']['path'] : ''));

		if (!$this->user->hasPermission('modify', 'mintcart/maintenance/storage')) {
			$json['message'] = $this->language->get('error_permission');
		} elseif ($path === '') {
			$json['errors']['path'] = $this->language->get('error_invalid');
		} else {
			$error = \MintCart\Core\StorageLocation::move(DIR_STORAGE, $path, array(
				$this->documentRoot() . '/config.php',
				DIR_APPLICATION . 'config.php'
			));

			if ($error !== null) {
				$json['errors']['path'] = $this->language->get('error_' . $error);
			} else {
				$json['ok'] = true;
				$json['message'] = sprintf($this->language->get('text_success'), rtrim(str_replace('\\', '/', $path), '/'));
			}
		}

		if (!$json['ok'] && !$json['message']) {
			$json['message'] = $this->language->get('error_failed');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	private function documentRoot() {
		return rtrim(str_replace('\\', '/', realpath(DIR_APPLICATION . '../')), '/');
	}
}
