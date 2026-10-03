<?php
/**
 * `Business → Payments` UI facade (`MINTCART_FUNCTIONAL_SCOPE.md` §3,
 * `MINTCART_TECHNICAL_PLAN.md` §23, `MINTCART_IMPLEMENTATION_GUIDE.md` §56).
 *
 * Same shape as `ControllerMintcartBusinessDelivery`
 * (`upload/admin/controller/mintcart/business/delivery.php`) for the
 * `payment` extension type instead of `shipping` — see that file's own
 * docblock for the full rationale (facade over the existing extension
 * mechanism, not a second payment subsystem; enable/disable toggle only,
 * full settings stay on each extension's own `extension/payment/<code>`
 * screen). Kept as an independent, near-duplicate controller rather than
 * factored into a shared base class, mirroring stock OpenCart's own
 * `admin/controller/extension/extension/shipping.php` /
 * `.../payment.php` — two separate, similarly-shaped files for the same
 * underlying problem (`CLAUDE.md` §38E: don't force an HMVC-style
 * abstraction where two direct entry points work fine).
 *
 * route=mintcart/business/payments. Fully additive - no existing OpenCart
 * controller/model/route touched (`CLAUDE.md` §8/§37).
 */
class ControllerMintcartBusinessPayments extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('mintcart/business/payments');
		$this->load->model('setting/extension');

		$this->document->setTitle($this->language->get('heading_title'));

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];

			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('mintcart/business/payments', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action_toggle'] = $this->url->link('mintcart/business/payments/toggle', 'user_token=' . $this->session->data['user_token'], true);
		$data['manage'] = $this->url->link('extension/extension/payment', 'user_token=' . $this->session->data['user_token'], true);
		$data['extensions'] = $this->getExtensionRows('payment');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('mintcart/business/payments_list', $data));
	}

	// route=mintcart/business/payments/toggle - POST code
	public function toggle() {
		$this->load->language('mintcart/business/payments');
		$this->load->model('setting/extension');
		$this->load->model('setting/setting');

		if ($this->validateToggle()) {
			$code = (string)$this->request->post['code'];

			// Merge-then-save - see
			// ControllerMintcartBusinessDelivery::toggle()'s identical comment
			// for why a bare editSetting() here would silently wipe the
			// extension's other settings.
			$existing = $this->model_setting_setting->getSetting('payment_' . $code);

			$existing['payment_' . $code . '_status'] = empty($existing['payment_' . $code . '_status']) ? '1' : '0';

			$this->model_setting_setting->editSetting('payment_' . $code, $existing);

			$this->session->data['success'] = $this->language->get('text_success');
		}

		$this->response->redirect($this->url->link('mintcart/business/payments', 'user_token=' . $this->session->data['user_token'], true));
	}

	protected function validateToggle() {
		if (!$this->user->hasPermission('modify', 'mintcart/business/payments')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		$code = isset($this->request->post['code']) ? (string)$this->request->post['code'] : '';

		if ($code === '' || !in_array($code, $this->model_setting_extension->getInstalled('payment'), true)) {
			$this->error['warning'] = $this->language->get('error_extension');
		}

		return !$this->error;
	}

	/**
	 * @return array<int, array{code: string, name: string, enabled: bool, edit: string}>
	 */
	protected function getExtensionRows($type) {
		$rows = array();

		foreach ($this->model_setting_extension->getInstalled($type) as $code) {
			if (!is_file(DIR_APPLICATION . 'controller/extension/' . $type . '/' . $code . '.php')) {
				continue;
			}

			$this->load->language('extension/' . $type . '/' . $code, 'extension');

			$rows[] = array(
				'code'    => $code,
				'name'    => $this->language->get('extension')->get('heading_title'),
				'enabled' => (bool)$this->config->get($type . '_' . $code . '_status'),
				'edit'    => $this->url->link('extension/' . $type . '/' . $code, 'user_token=' . $this->session->data['user_token'], true)
			);
		}

		return $rows;
	}
}
