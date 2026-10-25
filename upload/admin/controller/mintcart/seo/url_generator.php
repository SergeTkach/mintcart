<?php
/**
 * `SEO -> URL Generator`: SEO URL keywords made from names (docs/developer/seo/url-generator.md).
 *
 * route=mintcart/seo/url_generator
 *   index    the screen: a template per entity type (`{brand} {name}`), and per type and language
 *            how many items have no keyword, with "Generate the missing ones" (AJAX batches).
 *   slug     POST - the keyword for what is typed in a product / category / page / brand form
 *            (`seo-url-autofill.js`): the template, transliterated, made unique.
 *   run      POST - generates the next batch of missing keywords of one type and language.
 *
 * Keywords are written to the stock `seo_url` table (store 0, the default store, like
 * `SEO -> System Pages`). Existing keywords are never overwritten: a changed URL would break
 * every link and indexed page that points to the old one. A keyword is unique across all languages
 * of the store, as OpenCart's own forms require (the second language of the same name gets `-2`).
 */
class ControllerMintcartSeoUrlGenerator extends Controller {
	private $error = array();

	const BATCH = 50;
	const SETTING_CODE = 'mintcart_seo';
	const SETTING_KEY = 'mintcart_seo_url';

	public function index() {
		$this->load->language('mintcart/seo/url_generator');
		$this->load->model('setting/setting');
		$this->load->model('localisation/language');
		$this->load->model('mintcart/seo_url');

		$this->document->setTitle($this->language->get('heading_title'));

		if ($this->request->server['REQUEST_METHOD'] == 'POST') {
			if (!$this->user->hasPermission('modify', 'mintcart/seo/url_generator')) {
				$this->error['warning'] = $this->language->get('error_permission');
			} else {
				$settings = $this->model_setting_setting->getSetting(self::SETTING_CODE);

				$settings[self::SETTING_KEY] = \MintCart\Seo\UrlTemplates::normalize(isset($this->request->post['template']) && is_array($this->request->post['template']) ? $this->request->post['template'] : array());

				$this->model_setting_setting->editSetting(self::SETTING_CODE, $settings);

				$this->session->data['success'] = $this->language->get('text_success');

				$this->response->redirect($this->url->link('mintcart/seo/url_generator', 'user_token=' . $this->session->data['user_token'], true));
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
			array('text' => $this->language->get('heading_title'), 'href' => $this->url->link('mintcart/seo/url_generator', 'user_token=' . $this->session->data['user_token'], true))
		);

		$data['action'] = $this->url->link('mintcart/seo/url_generator', 'user_token=' . $this->session->data['user_token'], true);
		$data['run_url'] = $this->url->link('mintcart/seo/url_generator/run', 'user_token=' . $this->session->data['user_token'], true);
		$data['slug_url'] = $this->url->link('mintcart/seo/url_generator/slug', 'user_token=' . $this->session->data['user_token'], true);

		$languages = $this->model_localisation_language->getLanguages();

		$data['types'] = array();

		foreach (\MintCart\Seo\UrlTemplates::TYPES as $type => $variables) {
			$variable_list = array();

			foreach ($variables as $variable) {
				$variable_list[] = array('token' => '{' . $variable . '}', 'label' => $this->language->get('var_' . $variable));
			}

			$counts = array();

			foreach ($languages as $language) {
				$counts[] = array(
					'language_id' => (int)$language['language_id'],
					'name'        => $language['name'],
					'code'        => $language['code'],
					'missing'     => $this->model_mintcart_seo_url->totalMissing($type, 0, $language['language_id'])
				);
			}

			$data['types'][] = array(
				'code'      => $type,
				'name'      => $this->language->get('text_type_' . $type),
				'template'  => \MintCart\Seo\UrlTemplates::forType($this->config->get(self::SETTING_KEY), $type),
				'variables' => $variable_list,
				'counts'    => $counts
			);
		}

		$data['user_token'] = $this->session->data['user_token'];

		$this->document->addStyle(HTTP_CATALOG . 'assets/dist/css/components.min.css');
		$this->document->addScript(HTTP_CATALOG . 'assets/dist/js/admin.min.js');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('mintcart/seo/url_generator', $data));
	}

	/**
	 * Event handler (migration 202610251400, `admin/controller/catalog/<product|category|information|manufacturer>/<add|edit>/before`):
	 * loads MintCart's admin bundle on those forms, which carries the SEO URL auto-fill
	 * (`assets/src/js/components/seo-url-autofill.js`). A queued script has a unique key, so a form that
	 * already loads the bundle (product, category) does not get it twice.
	 */
	public function assets() {
		$this->document->addScript(HTTP_CATALOG . 'assets/dist/js/admin.min.js');
	}

	/**
	 * route=mintcart/seo/url_generator/slug - POST type, language_id, name, store_id, ignore_id,
	 * and for a product brand_id / category_id, for a category parent_id.
	 * Answers {slug}: the keyword to put in the form's SEO URL field.
	 */
	public function slug() {
		$this->load->model('mintcart/seo_url');
		$this->load->model('localisation/language');

		$type = isset($this->request->post['type']) ? (string)$this->request->post['type'] : '';
		$language_id = isset($this->request->post['language_id']) ? (int)$this->request->post['language_id'] : (int)$this->config->get('config_language_id');
		$store_id = isset($this->request->post['store_id']) ? (int)$this->request->post['store_id'] : 0;
		$ignore_id = isset($this->request->post['ignore_id']) ? (int)$this->request->post['ignore_id'] : 0;
		$json = array('slug' => '');

		if (isset(\MintCart\Seo\UrlTemplates::TYPES[$type]) && $this->user->hasPermission('access', 'mintcart/seo/url_generator')) {
			$language = $this->model_localisation_language->getLanguage($language_id);
			$code = $language ? $language['code'] : '';

			$variables = array(
				'name'     => isset($this->request->post['name']) ? html_entity_decode((string)$this->request->post['name'], ENT_QUOTES, 'UTF-8') : '',
				'brand'    => '',
				'category' => '',
				'model'    => isset($this->request->post['model']) ? html_entity_decode((string)$this->request->post['model'], ENT_QUOTES, 'UTF-8') : '',
				'parent'   => ''
			);

			if ($type === 'product') {
				$variables['brand'] = $this->nameOf('manufacturer', isset($this->request->post['brand_id']) ? (int)$this->request->post['brand_id'] : 0, $language_id);
				$variables['category'] = $this->nameOf('category', isset($this->request->post['category_id']) ? (int)$this->request->post['category_id'] : 0, $language_id);
			} elseif ($type === 'category') {
				$variables['parent'] = $this->nameOf('category', isset($this->request->post['parent_id']) ? (int)$this->request->post['parent_id'] : 0, $language_id);
			}

			$base = \MintCart\Seo\UrlTemplates::keyword(\MintCart\Seo\UrlTemplates::forType($this->config->get(self::SETTING_KEY), $type), $variables, $code);

			$ignore_query = $ignore_id ? $this->model_mintcart_seo_url->queryOf($type, $ignore_id) : '';

			$avoid = isset($this->request->post['avoid']) && is_array($this->request->post['avoid']) ? array_map('strval', $this->request->post['avoid']) : array();

			$json['slug'] = $this->model_mintcart_seo_url->uniqueKeyword($base, $store_id, $ignore_query, $avoid);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	private function nameOf($what, $id, $language_id) {
		if (!$id) {
			return '';
		}

		if ($what === 'manufacturer') {
			$query = $this->db->query("SELECT `name` FROM `" . DB_PREFIX . "manufacturer` WHERE `manufacturer_id` = '" . (int)$id . "'");
		} else {
			$query = $this->db->query("SELECT `name` FROM `" . DB_PREFIX . "category_description` WHERE `category_id` = '" . (int)$id . "' AND `language_id` = '" . (int)$language_id . "'");
		}

		return $query->num_rows ? (string)$query->row['name'] : '';
	}

	/**
	 * route=mintcart/seo/url_generator/run - POST type, language_id: the next batch of missing keywords.
	 * Answers {created, remaining}.
	 */
	public function run() {
		$this->load->model('mintcart/seo_url');
		$this->load->model('localisation/language');

		$type = isset($this->request->post['type']) ? (string)$this->request->post['type'] : '';
		$language_id = isset($this->request->post['language_id']) ? (int)$this->request->post['language_id'] : 0;
		$json = array('created' => 0, 'remaining' => 0);

		$language = $this->model_localisation_language->getLanguage($language_id);

		if (isset(\MintCart\Seo\UrlTemplates::TYPES[$type]) && $language && $this->user->hasPermission('modify', 'mintcart/seo/url_generator')) {
			$template = \MintCart\Seo\UrlTemplates::forType($this->config->get(self::SETTING_KEY), $type);

			foreach ($this->model_mintcart_seo_url->missing($type, 0, $language_id, self::BATCH) as $id) {
				$variables = $this->model_mintcart_seo_url->variables($type, $id, $language_id);
				$base = \MintCart\Seo\UrlTemplates::keyword($template, $variables, $language['code']);

				// An item without a usable name (or a nameless translation): the type and id.
				if ($base === '') {
					$base = $type . '-' . $id;
				}

				$keyword = $this->model_mintcart_seo_url->uniqueKeyword($base, 0);

				$this->model_mintcart_seo_url->add($this->model_mintcart_seo_url->queryOf($type, $id), $keyword, 0, $language_id);

				$json['created']++;
			}

			$json['remaining'] = $this->model_mintcart_seo_url->totalMissing($type, 0, $language_id);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
