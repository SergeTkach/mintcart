<?php
class ControllerExtensionShippingMcInpostUk extends Controller {
	private $error = array();

	private $keys = array('status', 'api_key', 'free_from', 'sort_order');

	public function index() {
		$this->load->language('extension/shipping/mc_inpost_uk');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$values = $this->model_setting_setting->getSetting('shipping_mc_inpost_uk');

			foreach ($this->keys as $key) {
				$values['shipping_mc_inpost_uk_' . $key] = trim((string)($this->request->post['shipping_mc_inpost_uk_' . $key] ?? ''));
			}

			$this->model_setting_setting->editSetting('shipping_mc_inpost_uk', $values);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping', true));
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
				'href' => $this->url->link('extension/shipping/mc_inpost_uk', 'user_token=' . $this->session->data['user_token'], true)
			)
		);

		$data['action'] = $this->url->link('extension/shipping/mc_inpost_uk', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping', true);

		foreach ($this->keys as $key) {
			$name = 'shipping_mc_inpost_uk_' . $key;

			$data[$name] = isset($this->request->post[$name]) ? $this->request->post[$name] : $this->config->get($name);
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/shipping/mc_inpost_uk', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/shipping/mc_inpost_uk')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		$free_from = trim((string)($this->request->post['shipping_mc_inpost_uk_free_from'] ?? ''));

		if ($free_from !== '' && (!is_numeric($free_from) || (float)$free_from < 0)) {
			$this->error['warning'] = $this->language->get('error_free_from');
		}

		return !$this->error;
	}
}
