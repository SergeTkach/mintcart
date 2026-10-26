<?php
/**
 * `SEO -> Redirects`: 301 redirects to the one SEO URL of a page (docs/developer/seo/redirects.md).
 *
 * route=mintcart/seo/redirects
 *   index    the screen: what to redirect (dirty `index.php?route=...` URLs, trailing slash, `?page=1`),
 *            the redirect list with hit counts, and a form for a manual redirect.
 *   delete   POST selected[] - remove redirects.
 *   track    event handler (migration 202610251500, `admin/model/catalog/<product|category|information|
 *            manufacturer>/edit<...>/before`): a changed SEO URL keyword becomes a redirect from the old one.
 *
 * The redirects themselves run on the storefront in `startup/mc_seo_redirect`.
 */
class ControllerMintcartSeoRedirects extends Controller {
	private $error = array();

	const SETTING_CODE = 'mintcart_seo';
	const SETTING_KEY = 'mintcart_seo_redirects';
	const PER_PAGE = 25;

	public function index() {
		$this->load->language('mintcart/seo/redirects');
		$this->load->model('setting/setting');
		$this->load->model('localisation/language');
		$this->load->model('mintcart/redirect');

		$this->document->setTitle($this->language->get('heading_title'));

		if ($this->request->server['REQUEST_METHOD'] == 'POST') {
			if (!$this->user->hasPermission('modify', 'mintcart/seo/redirects')) {
				$this->error['warning'] = $this->language->get('error_permission');
			} elseif (isset($this->request->post['add_old_path'])) {
				$this->addManual();
			} else {
				$settings = $this->model_setting_setting->getSetting(self::SETTING_CODE);

				$post = isset($this->request->post['option']) && is_array($this->request->post['option']) ? $this->request->post['option'] : array();

				$settings[self::SETTING_KEY] = json_encode(array(
					'dirty' => !empty($post['dirty']),
					'slash' => !empty($post['slash']),
					'page1' => !empty($post['page1'])
				));

				$this->model_setting_setting->editSetting(self::SETTING_CODE, $settings);

				$this->session->data['success'] = $this->language->get('text_success');

				$this->response->redirect($this->url->link('mintcart/seo/redirects', 'user_token=' . $this->session->data['user_token'], true));
			}
		}

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];

			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		$data['breadcrumbs'] = array(
			array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)),
			array('text' => $this->language->get('heading_title'), 'href' => $this->url->link('mintcart/seo/redirects', 'user_token=' . $this->session->data['user_token'], true))
		);

		$saved = json_decode((string)$this->config->get(self::SETTING_KEY), true);
		$saved = is_array($saved) ? $saved : array();

		$data['options'] = array();

		foreach (array('dirty', 'slash', 'page1') as $option) {
			$data['options'][] = array(
				'code'    => $option,
				'label'   => $this->language->get('option_' . $option),
				'help'    => $this->language->get('help_' . $option),
				'checked' => !isset($saved[$option]) || !empty($saved[$option])
			);
		}

		$page = isset($this->request->get['page']) ? max(1, (int)$this->request->get['page']) : 1;

		$total = $this->model_mintcart_redirect->total();

		$languages = array();

		foreach ($this->model_localisation_language->getLanguages() as $language) {
			$languages[(int)$language['language_id']] = $language['name'];
		}

		$data['languages'] = $languages;
		$data['redirects'] = array();

		foreach ($this->model_mintcart_redirect->getList(($page - 1) * self::PER_PAGE, self::PER_PAGE) as $row) {
			$data['redirects'][] = array(
				'id'       => $row['redirect_id'],
				'old'      => '/' . $row['old_path'],
				'target'   => $row['target_url'] !== '' ? $row['target_url'] : $row['route'] . '?' . $row['args'],
				'language' => $row['language_id'] ? (isset($languages[(int)$row['language_id']]) ? $languages[(int)$row['language_id']] : '') : $this->language->get('text_any_language'),
				'code'     => $row['code'],
				'source'   => $row['source'] === 'auto' ? $this->language->get('text_source_auto') : $this->language->get('text_source_manual'),
				'hits'     => $row['hits'],
				'last_hit' => $row['last_hit'] ? $row['last_hit'] : ''
			);
		}

		$pagination = new Pagination();
		$pagination->total = $total;
		$pagination->page = $page;
		$pagination->limit = self::PER_PAGE;
		$pagination->url = $this->url->link('mintcart/seo/redirects', 'user_token=' . $this->session->data['user_token'] . '&page={page}', true);

		$data['pagination'] = $pagination->render();
		$data['results'] = sprintf($this->language->get('text_pagination'), $total ? (($page - 1) * self::PER_PAGE) + 1 : 0, ((($page - 1) * self::PER_PAGE) > ($total - self::PER_PAGE)) ? $total : ((($page - 1) * self::PER_PAGE) + self::PER_PAGE), $total, ceil($total / self::PER_PAGE));

		$data['action'] = $this->url->link('mintcart/seo/redirects', 'user_token=' . $this->session->data['user_token'], true);
		$data['delete'] = $this->url->link('mintcart/seo/redirects/delete', 'user_token=' . $this->session->data['user_token'], true);

		$data['user_token'] = $this->session->data['user_token'];

		$this->document->addStyle(HTTP_CATALOG . 'assets/dist/css/components.min.css');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('mintcart/seo/redirects', $data));
	}

	private function addManual() {
		$old = isset($this->request->post['add_old_path']) ? trim(html_entity_decode((string)$this->request->post['add_old_path'], ENT_QUOTES, 'UTF-8')) : '';
		$target = isset($this->request->post['add_target']) ? trim(html_entity_decode((string)$this->request->post['add_target'], ENT_QUOTES, 'UTF-8')) : '';
		$code = isset($this->request->post['add_code']) ? (int)$this->request->post['add_code'] : 301;
		$language_id = isset($this->request->post['add_language_id']) ? (int)$this->request->post['add_language_id'] : 0;

		// A full address of the old page: keep only its path.
		if (preg_match('#^https?://#i', $old)) {
			$old = (string)parse_url($old, PHP_URL_PATH);
		}

		$old = trim(strtok($old, '?'), '/');

		if ($old === '' || $target === '') {
			$this->error['warning'] = $this->language->get('error_manual');

			return;
		}

		$this->model_mintcart_redirect->addManual($old, $target, $code, $language_id);

		$this->session->data['success'] = $this->language->get('text_success_added');

		$this->response->redirect($this->url->link('mintcart/seo/redirects', 'user_token=' . $this->session->data['user_token'], true));
	}

	public function delete() {
		$this->load->language('mintcart/seo/redirects');
		$this->load->model('mintcart/redirect');

		if (isset($this->request->post['selected']) && $this->user->hasPermission('modify', 'mintcart/seo/redirects')) {
			foreach ((array)$this->request->post['selected'] as $redirect_id) {
				$this->model_mintcart_redirect->delete($redirect_id);
			}

			$this->session->data['success'] = $this->language->get('text_success_deleted');
		}

		$this->response->redirect($this->url->link('mintcart/seo/redirects', 'user_token=' . $this->session->data['user_token'], true));
	}

	/**
	 * Event handler: runs before a product / category / page / brand is saved. `$args` = [entity id, posted data].
	 */
	public function track($route, $args) {
		$map = array(
			'catalog/product/editProduct'           => array('product_id', 'product_seo_url', 'product/product', 'product_id'),
			'catalog/category/editCategory'         => array('category_id', 'category_seo_url', 'product/category', 'path'),
			'catalog/information/editInformation'   => array('information_id', 'information_seo_url', 'information/information', 'information_id'),
			'catalog/manufacturer/editManufacturer' => array('manufacturer_id', 'manufacturer_seo_url', 'product/manufacturer/info', 'manufacturer_id')
		);

		if (!isset($map[$route]) || !is_array($args) || count($args) < 2 || !is_array($args[1])) {
			return;
		}

		list($key, $field, $page_route, $page_arg) = $map[$route];

		if (!isset($args[1][$field]) || !is_array($args[1][$field])) {
			return;
		}

		$this->load->model('mintcart/redirect');

		$this->model_mintcart_redirect->trackKeywordChanges($key, $args[0], $page_route, $page_arg, $args[1][$field]);
	}
}
