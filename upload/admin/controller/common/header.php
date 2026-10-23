<?php
class ControllerCommonHeader extends Controller {
	public function index() {
		$data['title'] = $this->document->getTitle();

		if ($this->request->server['HTTPS']) {
			$data['base'] = HTTPS_SERVER;
		} else {
			$data['base'] = HTTP_SERVER;
		}

		if ($this->request->server['HTTPS']) {
            $server = HTTPS_CATALOG;
        } else {
            $server = HTTP_CATALOG;
        }

        if (is_file(DIR_IMAGE . $this->config->get('config_icon'))) {
			$this->document->addLink($server . 'image/' . $this->config->get('config_icon'), 'icon');
        }

		$data['description'] = $this->document->getDescription();
		$data['keywords'] = $this->document->getKeywords();
		$data['links'] = $this->document->getLinks();
		$data['styles'] = $this->document->getStyles();
		$data['scripts'] = $this->document->getScripts();
		$data['lang'] = $this->language->get('code');
		$data['direction'] = $this->language->get('direction');

		$this->load->language('common/header');

		// MC-0018 (docs/fixes/registry.md) - none of these eight keys were
		// ever assigned to $data even though common/header.twig references
		// all of them, so they rendered blank on every admin page. Same
		// "loaded a language file but never assigned the keys to $data"
		// bug class as MC-0001/MC-0003/MC-0017 (and the same explicit
		// per-key shape, rather than a blanket array_merge() of
		// $this->load->language()'s return value, which would also pull in
		// every other language file already loaded earlier in this same
		// request).
		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_profile'] = $this->language->get('text_profile');
		$data['text_store'] = $this->language->get('text_store');
		$data['text_help'] = $this->language->get('text_help');
		$data['text_homepage'] = $this->language->get('text_homepage');
		$data['text_support'] = $this->language->get('text_support');
		$data['text_documentation'] = $this->language->get('text_documentation');
		$data['text_logout'] = $this->language->get('text_logout');

		$data['text_logged'] = sprintf($this->language->get('text_logged'), $this->user->getUserName());

		if (!isset($this->request->get['user_token']) || !isset($this->session->data['user_token']) || ($this->request->get['user_token'] != $this->session->data['user_token'])) {
			$data['logged'] = '';

			$data['home'] = $this->url->link('common/login', '', true);
		} else {
			$data['logged'] = true;

			$data['home'] = $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true);
			$data['logout'] = $this->url->link('common/logout', 'user_token=' . $this->session->data['user_token'], true);
			$data['profile'] = $this->url->link('common/profile', 'user_token=' . $this->session->data['user_token'], true);

			// MintCart Functional Scope §23 (P1) - quick settings search,
			// shown on every logged-in admin page. enqueueMintAssets()
			// below queues the "components" bundle - see
			// docs/developer/admin/asset-enqueue.md for why that one, not
			// "index", on a legacy Bootstrap 3 admin screen like this one.
			$this->enqueueMintAssets();

			// $data['styles'] / ['scripts'] were read above, before the
			// queue grew - refresh them or the bundle never reaches the page.
			$data['styles'] = $this->document->getStyles();
			$data['scripts'] = $this->document->getScripts();

			$data['mc_admin_search_url'] = $this->url->link('mintcart/adminsearch', 'user_token=' . $this->session->data['user_token'], true);

			$this->load->model('user/user');

			$this->load->model('tool/image');

			$user_info = $this->model_user_user->getUser($this->user->getId());

			if ($user_info) {
				$data['firstname'] = $user_info['firstname'];
				$data['lastname'] = $user_info['lastname'];
				$data['username']  = $user_info['username'];
				$data['user_group'] = $user_info['user_group'];

				if (is_file(DIR_IMAGE . $user_info['image'])) {
					$data['image'] = $this->model_tool_image->resize($user_info['image'], 45, 45);
				} else {
					$data['image'] = $this->model_tool_image->resize('profile.png', 45, 45);
				}
			} else {
				$data['firstname'] = '';
				$data['lastname'] = '';
				$data['user_group'] = '';
				$data['image'] = '';
			}

			// Online Stores
			$data['stores'] = array();

			$data['stores'][] = array(
				'name' => $this->config->get('config_name'),
				'href' => HTTP_CATALOG
			);

			$this->load->model('setting/store');

			$results = $this->model_setting_store->getStores();

			foreach ($results as $result) {
				$data['stores'][] = array(
					'name' => $result['name'],
					'href' => $result['url']
				);
			}
		}

		return $this->load->view('common/header', $data);
	}

	/**
	 * Same copy-paste-per-controller shape every other MintCart admin
	 * controller uses (e.g. `ControllerCatalogProduct::enqueueMintAssets()`)
	 * rather than a shared trait/base class — see
	 * docs/developer/admin/asset-enqueue.md for why "components", not
	 * "index": this page already runs the full legacy Bootstrap 3 admin
	 * theme, and `index.min.css` bundles Bootstrap 5 in full, which
	 * conflicts with it. header.twig's existing
	 * {% for style in styles %}/{% for script in scripts %} loops render
	 * whatever this call queues, no template change needed for the bundle
	 * itself.
	 */
	protected function enqueueMintAssets() {
		$this->document->addStyle(HTTP_CATALOG . 'assets/dist/css/components.min.css');
		$this->document->addScript(HTTP_CATALOG . 'assets/dist/js/admin.min.js');
	}
}
