<?php
/**
 * `System → Diagnostics` — an admin UI toggle for the `mintcart_dev_mode`
 * setting (`MINTCART_FUNCTIONAL_SCOPE.md` §19, `MINTCART_TECHNICAL_PLAN.md`
 * §35, `MINTCART_IMPLEMENTATION_GUIDE.md` §67).
 *
 * `MintCart\Core\Diagnostics` (query/mark/duration collection) and its
 * `mintcart_dev_mode`-gated wiring into `ControllerProductCategory`/
 * `ControllerProductSearch`/`ControllerProductSpecial`/
 * `ControllerProductManufacturer::info()`/`ControllerProductProduct` already
 * exist (`docs/developer/core/diagnostics.md`) — the flag could previously
 * only be turned on by writing directly to `oc_setting`. This screen is
 * exactly the "admin UI toggle for `mintcart_dev_mode`" that doc's own
 * "Next step" section named, nothing more: one checkbox, one Save.
 *
 * route=mintcart/settings/diagnostics. Fully additive — no existing
 * OpenCart controller/model/route touched (`CLAUDE.md` §8/§37).
 *
 * Deliberately its own settings code group ('mintcart_dev_mode', matching
 * the single key it stores) rather than folding into the stock 'config'
 * group: `ModelSettingSetting::editSetting()` only ever persists a $key
 * whose name starts with the given $code (checked against the real model,
 * `upload/admin/model/setting/setting.php`, per `CLAUDE.md` §84 — not
 * assumed from memory), and a bare boolean flag has no natural relationship
 * to the dozens of other `config_*` keys a merge-then-save against 'config'
 * would risk (the same trap `ControllerMintcartBusinessContacts::save()`'s
 * own docblock already documents for that group). One code, one key, no
 * merge needed — same isolated-group shape `ControllerMintcartBusinessContacts`
 * already uses for `mintcart_contacts`/`mintcart_contacts_social`.
 *
 * Built on the legacy OpenCart admin theme (Bootstrap 3 `.panel`/`.btn`/
 * `form-horizontal` markup), the same reason every other MintCart admin
 * screen in this repo gives (see
 * `docs/developer/blocks/homepage-admin.md`'s "Why this screen uses the
 * legacy admin theme") — the shared admin `header`/`column_left`/`footer`
 * chrome wraps every admin screen, and MintCart's own compiled asset bundle
 * has never been enqueued into it anywhere in this repo (`CLAUDE.md` §10).
 */
class ControllerMintcartSettingsDiagnostics extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('mintcart/settings/diagnostics');
		$this->load->model('setting/setting');

		$this->document->setTitle($this->language->get('heading_title'));

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->save();

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('mintcart/settings/diagnostics', 'user_token=' . $this->session->data['user_token'], true));

			return;
		}

		$this->getForm();
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'mintcart/settings/diagnostics')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	protected function save() {
		$this->model_setting_setting->editSetting('mintcart_dev_mode', array(
			'mintcart_dev_mode' => !empty($this->request->post['mintcart_dev_mode']) ? 1 : 0
		));
	}

	protected function getForm() {
		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_edit'] = $this->language->get('text_edit');
		$data['text_intro'] = $this->language->get('text_intro');
		$data['entry_dev_mode'] = $this->language->get('entry_dev_mode');
		$data['help_dev_mode'] = $this->language->get('help_dev_mode');

		$data['error_warning'] = $this->error['warning'] ?? '';

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];

			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('mintcart/settings/diagnostics', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('mintcart/settings/diagnostics', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true);

		if ($this->request->server['REQUEST_METHOD'] == 'POST') {
			$data['mintcart_dev_mode'] = !empty($this->request->post['mintcart_dev_mode']);
		} else {
			$data['mintcart_dev_mode'] = (bool)$this->config->get('mintcart_dev_mode');
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('mintcart/settings/diagnostics', $data));
	}
}
