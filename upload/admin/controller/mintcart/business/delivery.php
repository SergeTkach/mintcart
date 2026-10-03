<?php
/**
 * `Business → Delivery` UI facade (`MINTCART_FUNCTIONAL_SCOPE.md` §3,
 * `MINTCART_TECHNICAL_PLAN.md` §23, `MINTCART_IMPLEMENTATION_GUIDE.md` §56).
 *
 * route=mintcart/business/delivery. Fully additive - no existing OpenCart
 * controller/model/route touched (`CLAUDE.md` §8/§37).
 *
 * Deliberately a facade over the existing shipping-extension mechanism, not
 * a second shipping subsystem (`MINTCART_TECHNICAL_PLAN.md` §23 "не
 * створювати другу shipping subsystem"): the extension list itself comes
 * from `ModelSettingExtension::getInstalled('shipping')` (the exact source
 * `ControllerExtensionExtensionShipping::getList()` already uses for
 * `Extensions → Shipping Methods`), each row's display name from that
 * extension's own language file, and its enabled state from the exact
 * `shipping_<code>_status` config key every stock shipping extension
 * controller already saves under (the same convention
 * `ModelMintcartReadiness::getSignals()` already reads, Phase 14). This
 * screen adds one thing OpenCart's own extension list doesn't have: a
 * one-click enable/disable toggle without opening each extension's full
 * settings form - the settings themselves (cost, tax class, geo zone, ...)
 * still live on that extension's own `extension/shipping/<code>` screen,
 * linked from here as "Configure", not duplicated.
 *
 * Built on the legacy OpenCart admin theme (Bootstrap 3 `.panel`/`.btn`/
 * `.table` markup), same reasoning `ControllerDesignHomepage` and
 * `ControllerExtensionDashboardMintcartReadiness` both already give: the
 * shared admin `header`/`column_left`/`footer` chrome wraps every admin
 * screen, and MintCart's own compiled asset bundle
 * (`assets/dist/{css,js}`) has never been enqueued into it anywhere in
 * this repo (`CLAUDE.md` §10).
 */
class ControllerMintcartBusinessDelivery extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('mintcart/business/delivery');
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
			'href' => $this->url->link('mintcart/business/delivery', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action_toggle'] = $this->url->link('mintcart/business/delivery/toggle', 'user_token=' . $this->session->data['user_token'], true);
		$data['manage'] = $this->url->link('extension/extension/shipping', 'user_token=' . $this->session->data['user_token'], true);
		$data['extensions'] = $this->getExtensionRows('shipping');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('mintcart/business/delivery_list', $data));
	}

	// route=mintcart/business/delivery/toggle - POST code
	public function toggle() {
		$this->load->language('mintcart/business/delivery');
		$this->load->model('setting/extension');
		$this->load->model('setting/setting');

		if ($this->validateToggle()) {
			$code = (string)$this->request->post['code'];

			// Merge-then-save, never a bare editSetting('shipping_<code>', [...]) -
			// ModelSettingSetting::editSetting() DELETEs every existing row for
			// the setting code first, so toggling just the *_status key without
			// re-supplying the extension's other settings (cost, tax class, geo
			// zone, ...) would silently wipe them. Same trap
			// ControllerMintcartWizard::saveStoreStep()/
			// ControllerMintcartBusinessContacts::save() already document for
			// the 'config' code.
			$existing = $this->model_setting_setting->getSetting('shipping_' . $code);

			$existing['shipping_' . $code . '_status'] = empty($existing['shipping_' . $code . '_status']) ? '1' : '0';

			$this->model_setting_setting->editSetting('shipping_' . $code, $existing);

			$this->session->data['success'] = $this->language->get('text_success');
		}

		$this->response->redirect($this->url->link('mintcart/business/delivery', 'user_token=' . $this->session->data['user_token'], true));
	}

	protected function validateToggle() {
		if (!$this->user->hasPermission('modify', 'mintcart/business/delivery')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		$code = isset($this->request->post['code']) ? (string)$this->request->post['code'] : '';

		if ($code === '' || !in_array($code, $this->model_setting_extension->getInstalled('shipping'), true)) {
			$this->error['warning'] = $this->language->get('error_extension');
		}

		return !$this->error;
	}

	/**
	 * Same defensive "only list an extension that still has a real
	 * controller file" check `ControllerExtensionExtensionShipping::getList()`
	 * already performs (an extension can be uninstalled by deleting its
	 * file directly on disk, leaving a stale `oc_extension` row behind).
	 *
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
