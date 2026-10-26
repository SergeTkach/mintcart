<?php
/**
 * `Business → Contacts` UI facade (`MINTCART_FUNCTIONAL_SCOPE.md` §3,
 * `MINTCART_TECHNICAL_PLAN.md` §23, `MINTCART_IMPLEMENTATION_GUIDE.md` §56).
 *
 * route=mintcart/business/contacts. Fully additive - no existing OpenCart
 * controller/model/route touched (`CLAUDE.md` §8/§37).
 *
 * Deliberately a facade, not a second data store (`CLAUDE.md` §6/§39):
 * `config_telephone`/`config_email`/`config_address` are OpenCart's own
 * existing `config` setting keys (the exact ones `ControllerMintcartWizard`'s
 * Store step already writes, `ControllerCommonFooter`/`ControllerCommonHeader`
 * already read); this screen edits the same rows, it doesn't duplicate them.
 * Social profile links (`mintcart_contacts_social`) were introduced by the
 * Wizard's own Contacts step and already share `MintCart\Design\FooterViewProvider`'s
 * `{network, href}` shape via the new `MintCart\Onboarding\SocialProfileLinks`
 * helper - the point of this screen is to let a shop owner come back and
 * edit contact details/social links any time after onboarding, not just
 * once during the Wizard.
 *
 * Built on the legacy OpenCart admin theme (Bootstrap 3 `.panel`/`.btn`/
 * `form-horizontal` markup), the same reason `ControllerDesignHomepage` and
 * `ControllerExtensionDashboardMintcartReadiness` both give: the shared
 * admin `header`/`column_left`/`footer` chrome wraps every admin screen,
 * and MintCart's own compiled asset bundle (`assets/dist/{css,js}`) has
 * never been enqueued into it anywhere in this repo (`CLAUDE.md` §10) - see
 * `docs/developer/blocks/homepage-admin.md`'s "Why this screen uses the
 * legacy admin theme" for the full writeup, unchanged by this run.
 *
 * Deliberately does NOT touch `upload/admin/controller/mintcart/wizard.php`
 * even though its Store/Contacts steps validate/save the exact same fields:
 * PR #52 ("Setup Wizard: Contacts step") already has that file open for a
 * different change at the time this was written, and folding the two into
 * one shared implementation now would risk a merge collision for no
 * functional benefit this run. `MintCart\Onboarding\SocialProfileLinks`
 * (new this run) exists specifically so a future run can retarget the
 * Wizard's own inline copy at it once #52 has landed, without this screen
 * needing to change.
 */
class ControllerMintcartBusinessContacts extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('mintcart/business/contacts');
		$this->load->model('setting/setting');

		$this->document->setTitle($this->language->get('heading_title'));

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->save();

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('mintcart/business/contacts', 'user_token=' . $this->session->data['user_token'], true));

			return;
		}

		$this->getForm();
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'mintcart/business/contacts')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		$telephone = trim((string)($this->request->post['config_telephone'] ?? ''));
		$email = trim((string)($this->request->post['config_email'] ?? ''));
		$address = trim((string)($this->request->post['config_address'] ?? ''));
		$phone_mask = trim((string)($this->request->post['config_mc_phone_mask'] ?? ''));

		if ($phone_mask !== '' && !\MintCart\Commerce\PhoneMask::isValid($phone_mask)) {
			$this->error['config_mc_phone_mask'] = $this->language->get('error_phone_mask');
		}

		if ((utf8_strlen($telephone) < 3) || (utf8_strlen($telephone) > 32)) {
			$this->error['config_telephone'] = $this->language->get('error_telephone');
		}

		if ((utf8_strlen($email) > 96) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
			$this->error['config_email'] = $this->language->get('error_email');
		}

		if ((utf8_strlen($address) < 3) || (utf8_strlen($address) > 256)) {
			$this->error['config_address'] = $this->language->get('error_address');
		}

		$announcement_url = trim((string)($this->request->post['announcement_url'] ?? ''));

		if (utf8_strlen(trim((string)($this->request->post['announcement_text'] ?? ''))) > 200) {
			$this->error['announcement_text'] = $this->language->get('error_announcement_text');
		}

		if ($announcement_url !== '' && !preg_match('#^(https?://|/(?!/))#i', $announcement_url)) {
			$this->error['announcement_url'] = $this->language->get('error_announcement_url');
		}

		foreach (\MintCart\Onboarding\SocialProfileLinks::NETWORKS as $network) {
			$url = trim((string)($this->request->post['social_' . $network] ?? ''));

			if (!\MintCart\Onboarding\SocialProfileLinks::isValidUrl($url)) {
				$this->error['social_' . $network] = sprintf($this->language->get('error_social'), $this->language->get('entry_social_' . $network));
			}
		}

		return !$this->error;
	}

	/**
	 * Merge-then-save against the 'config' code, never a bare
	 * editSetting('config', $partialData) - ModelSettingSetting::editSetting()
	 * DELETEs every existing row for the given code before re-inserting only
	 * the keys it's handed, so saving a 3-field partial without merging the
	 * store's full existing 'config' settings first would silently wipe out
	 * every other config_* setting (name, currency, tax, checkout, ...) on
	 * save. Same trap ControllerMintcartWizard::saveStoreStep() already
	 * documents and guards against - checked against the real model
	 * (upload/admin/model/setting/setting.php) before writing this, not
	 * assumed from memory (CLAUDE.md §84/§10).
	 */
	protected function save() {
		$existing = $this->model_setting_setting->getSetting('config');

		$merged = array_merge($existing, array(
			'config_telephone' => trim((string)$this->request->post['config_telephone']),
			'config_email'     => trim((string)$this->request->post['config_email']),
			'config_address'   => trim((string)$this->request->post['config_address']),
			'config_mc_phone_mask' => trim((string)($this->request->post['config_mc_phone_mask'] ?? ''))
		));

		$this->model_setting_setting->editSetting('config', $merged);

		$social = array();

		foreach (\MintCart\Onboarding\SocialProfileLinks::NETWORKS as $network) {
			$social[$network] = trim((string)($this->request->post['social_' . $network] ?? ''));
		}

		// Own setting code: the Wizard's Contacts step rewrites the whole
		// 'mintcart_contacts' code, which would wipe the announcement.
		$this->model_setting_setting->editSetting('mintcart_announcement', array(
			'mintcart_announcement_text' => trim((string)$this->request->post['announcement_text']),
			'mintcart_announcement_url'  => trim((string)$this->request->post['announcement_url'])
		));

		$this->model_setting_setting->editSetting('mintcart_contacts', array(
			'mintcart_contacts_social' => \MintCart\Onboarding\SocialProfileLinks::normalize($social)
		));
	}

	protected function getForm() {
		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_edit'] = $this->language->get('text_edit');
		$data['text_contact_details'] = $this->language->get('text_contact_details');
		$data['text_social_links'] = $this->language->get('text_social_links');
		$data['text_announcement'] = $this->language->get('text_announcement');
		$data['help_announcement'] = $this->language->get('help_announcement');
		$data['entry_announcement_text'] = $this->language->get('entry_announcement_text');
		$data['entry_announcement_url'] = $this->language->get('entry_announcement_url');
		$data['error_announcement_text'] = $this->error['announcement_text'] ?? '';
		$data['error_announcement_url'] = $this->error['announcement_url'] ?? '';

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		foreach (array('config_telephone', 'config_email', 'config_address', 'config_mc_phone_mask') as $key) {
			$data['error_' . $key] = $this->error[$key] ?? '';
		}

		foreach (\MintCart\Onboarding\SocialProfileLinks::NETWORKS as $network) {
			$data['error_social_' . $network] = $this->error['social_' . $network] ?? '';
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('mintcart/business/contacts', 'user_token=' . $this->session->data['user_token'], true)
		);

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];

			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		$data['action'] = $this->url->link('mintcart/business/contacts', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true);

		if ($this->request->server['REQUEST_METHOD'] == 'POST') {
			$data['config_telephone'] = $this->request->post['config_telephone'] ?? '';
			$data['config_email'] = $this->request->post['config_email'] ?? '';
			$data['config_address'] = $this->request->post['config_address'] ?? '';
			$data['config_mc_phone_mask'] = $this->request->post['config_mc_phone_mask'] ?? '';
			$data['announcement_text'] = $this->request->post['announcement_text'] ?? '';
			$data['announcement_url'] = $this->request->post['announcement_url'] ?? '';

			foreach (\MintCart\Onboarding\SocialProfileLinks::NETWORKS as $network) {
				$data['social_' . $network] = $this->request->post['social_' . $network] ?? '';
			}
		} else {
			$data['config_telephone'] = $this->config->get('config_telephone');
			$data['config_email'] = $this->config->get('config_email');
			$data['config_address'] = $this->config->get('config_address');
			$data['config_mc_phone_mask'] = $this->config->get('config_mc_phone_mask');

			$this->load->model('setting/setting');

			$announcement_setting = $this->model_setting_setting->getSetting('mintcart_announcement');
			$data['announcement_text'] = $announcement_setting['mintcart_announcement_text'] ?? '';
			$data['announcement_url'] = $announcement_setting['mintcart_announcement_url'] ?? '';

			$contacts_setting = $this->model_setting_setting->getSetting('mintcart_contacts');
			$social = isset($contacts_setting['mintcart_contacts_social']) && is_array($contacts_setting['mintcart_contacts_social'])
				? $contacts_setting['mintcart_contacts_social']
				: array();

			$byNetwork = \MintCart\Onboarding\SocialProfileLinks::byNetwork($social);

			foreach (\MintCart\Onboarding\SocialProfileLinks::NETWORKS as $network) {
				$data['social_' . $network] = $byNetwork[$network] ?? '';
			}
		}

		foreach (\MintCart\Onboarding\SocialProfileLinks::NETWORKS as $network) {
			$data['entry_social_' . $network] = $this->language->get('entry_social_' . $network);
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('mintcart/business/contacts_form', $data));
	}
}
