<?php
/**
 * `Marketing -> SEO -> Meta tags` (MINTCART_FUNCTIONAL_SCOPE.md §16 "SEO Core": meta title,
 * meta description).
 *
 * route=mintcart/seo/meta. Per page type (home, product, category, brand, information page) and
 * per language the merchant writes a title and a description template, e.g.
 * `{name} - {store}` or `{description:160}`, and chooses whether it applies only when the page's
 * own "Meta Tag Title / Description" is empty (the default) or always. The storefront controllers
 * hand the template to `MintCart\Seo\MetaBuilder`; without a template nothing changes (the title
 * falls back to the page name, the description stays empty).
 *
 * Storage: one setting `mintcart_seo_meta` (JSON) under the setting code `mintcart_seo`, shape in
 * `MintCart\Seo\MetaRules`. Fully additive - no OpenCart screen, model or table touched.
 *
 * `preview()` renders the templates for a real example (the first product / category / brand /
 * page of the store), so the merchant sees the result while typing.
 */
class ControllerMintcartSeoMeta extends Controller {
	private $error = array();

	const SETTING_CODE = 'mintcart_seo';
	const SETTING_KEY = 'mintcart_seo_meta';

	public function index() {
		$this->load->language('mintcart/seo/meta');
		$this->load->model('setting/setting');
		$this->load->model('localisation/language');

		$this->document->setTitle($this->language->get('heading_title'));

		$languages = $this->model_localisation_language->getLanguages();

		if ($this->request->server['REQUEST_METHOD'] == 'POST') {
			if (!$this->user->hasPermission('modify', 'mintcart/seo/meta')) {
				$this->error['warning'] = $this->language->get('error_permission');
			} else {
				$settings = $this->model_setting_setting->getSetting(self::SETTING_CODE);

				$settings[self::SETTING_KEY] = \MintCart\Seo\MetaRules::normalize(
					isset($this->request->post['meta']) && is_array($this->request->post['meta']) ? $this->request->post['meta'] : array(),
					array_map('intval', array_column($languages, 'language_id'))
				);

				$this->model_setting_setting->editSetting(self::SETTING_CODE, $settings);

				$this->session->data['success'] = $this->language->get('text_success');

				$this->response->redirect($this->url->link('mintcart/seo/meta', 'user_token=' . $this->session->data['user_token'], true));
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
			array('text' => $this->language->get('heading_title'), 'href' => $this->url->link('mintcart/seo/meta', 'user_token=' . $this->session->data['user_token'], true))
		);

		$data['action'] = $this->url->link('mintcart/seo/meta', 'user_token=' . $this->session->data['user_token'], true);
		$data['preview_url'] = $this->url->link('mintcart/seo/meta/preview', 'user_token=' . $this->session->data['user_token'], true);

		$saved = json_decode((string)$this->config->get(self::SETTING_KEY), true);

		if (!is_array($saved)) {
			$saved = array();
		}

		$data['languages'] = array_values($languages);

		$data['types'] = array();

		foreach (\MintCart\Seo\MetaRules::TYPES as $type => $variables) {
			$type_languages = array();

			foreach ($languages as $language) {
				$language_id = (int)$language['language_id'];
				$fields = array();

				foreach (\MintCart\Seo\MetaRules::FIELDS as $field) {
					$rule = isset($saved[$type][$language_id][$field]) && is_array($saved[$type][$language_id][$field]) ? $saved[$type][$language_id][$field] : array();

					$fields[$field] = array(
						'template' => isset($rule['template']) && is_string($rule['template']) ? $rule['template'] : '',
						'always'   => !empty($rule['always'])
					);
				}

				$type_languages[$language_id] = $fields;
			}

			$variable_list = array();

			foreach ($variables as $variable) {
				$variable_list[] = array('token' => '{' . $variable . '}', 'label' => $this->language->get('var_' . $variable));
			}

			$data['types'][] = array(
				'code'      => $type,
				'name'      => $this->language->get('text_type_' . $type),
				'hint'      => $this->language->get('hint_type_' . $type),
				'variables' => $variable_list,
				'languages' => $type_languages
			);
		}

		$data['user_token'] = $this->session->data['user_token'];

		$this->document->addStyle(HTTP_CATALOG . 'assets/dist/css/components.min.css');
		$this->document->addScript(HTTP_CATALOG . 'assets/dist/js/admin.min.js');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('mintcart/seo/meta', $data));
	}

	/**
	 * route=mintcart/seo/meta/preview - POST type, language_id, title, description (the templates being typed).
	 * Answers {title, description, sample} for a real example of that page type.
	 */
	public function preview() {
		$this->load->language('mintcart/seo/meta');

		$type = isset($this->request->post['type']) ? (string)$this->request->post['type'] : '';
		$language_id = isset($this->request->post['language_id']) ? (int)$this->request->post['language_id'] : (int)$this->config->get('config_language_id');

		$json = array('title' => '', 'description' => '', 'sample' => '');

		if (isset(\MintCart\Seo\MetaRules::TYPES[$type])) {
			$sample = $this->sample($type, $language_id);

			$json['sample'] = $sample['label'];

			foreach (array('title', 'description') as $field) {
				$template = isset($this->request->post[$field]) ? trim(html_entity_decode((string)$this->request->post[$field], ENT_QUOTES, 'UTF-8')) : '';

				$json[$field] = $template === '' ? '' : \MintCart\Seo\MetaTemplate::render($template, $sample['variables']);
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Variables of the first real page of this type in the store (an empty set when the store has none yet).
	 *
	 * @return array{label: string, variables: array<string, string>}
	 */
	private function sample($type, $language_id) {
		$store = (string)$this->config->get('config_name');
		$variables = array('store' => $store);
		$label = '';

		if ($type === 'product') {
			$this->load->model('catalog/product');
			$this->load->model('catalog/manufacturer');
			$this->load->model('catalog/category');

			$products = $this->model_catalog_product->getProducts(array('start' => 0, 'limit' => 1));

			if ($products) {
				$product = reset($products);
				$descriptions = $this->model_catalog_product->getProductDescriptions($product['product_id']);
				$description = isset($descriptions[$language_id]) ? $descriptions[$language_id] : reset($descriptions);
				$info = $this->model_catalog_product->getProduct($product['product_id']);
				$brand = $info && $info['manufacturer_id'] ? $this->model_catalog_manufacturer->getManufacturer($info['manufacturer_id']) : null;
				$category = '';

				foreach ($this->model_catalog_product->getProductCategories($product['product_id']) as $category_id) {
					$category_info = $this->model_catalog_category->getCategory($category_id);

					if ($category_info) {
						$category = $category_info['name'];

						break;
					}
				}

				$label = $description['name'];
				$variables += array(
					'name'        => $description['name'],
					'brand'       => $brand ? $brand['name'] : '',
					'category'    => $category,
					'model'       => $info ? $info['model'] : '',
					'price'       => $info ? $this->currency->format($info['price'], $this->config->get('config_currency')) : '',
					'description' => $description['description']
				);
			}
		} elseif ($type === 'category') {
			$this->load->model('catalog/category');

			$categories = $this->model_catalog_category->getCategories(array('start' => 0, 'limit' => 1));

			if ($categories) {
				$category = reset($categories);
				$descriptions = $this->model_catalog_category->getCategoryDescriptions($category['category_id']);
				$description = isset($descriptions[$language_id]) ? $descriptions[$language_id] : reset($descriptions);
				$info = $this->model_catalog_category->getCategory($category['category_id']);
				$parent = $info && $info['parent_id'] ? $this->model_catalog_category->getCategory($info['parent_id']) : null;

				$label = $description['name'];
				$variables += array(
					'name'        => $description['name'],
					'parent'      => $parent ? $parent['name'] : '',
					'description' => $description['description']
				);
			}
		} elseif ($type === 'manufacturer') {
			$this->load->model('catalog/manufacturer');

			$brands = $this->model_catalog_manufacturer->getManufacturers(array('start' => 0, 'limit' => 1));

			if ($brands) {
				$brand = reset($brands);

				$label = $brand['name'];
				$variables += array('name' => $brand['name']);
			}
		} elseif ($type === 'information') {
			$this->load->model('catalog/information');

			$pages = $this->model_catalog_information->getInformations(array('start' => 0, 'limit' => 1));

			if ($pages) {
				$page = reset($pages);
				$descriptions = $this->model_catalog_information->getInformationDescriptions($page['information_id']);
				$description = isset($descriptions[$language_id]) ? $descriptions[$language_id] : reset($descriptions);

				$label = $description['title'];
				$variables += array('name' => $description['title'], 'description' => $description['description']);
			}
		} else {
			$label = $store;
		}

		return array('label' => $label, 'variables' => $variables);
	}
}
