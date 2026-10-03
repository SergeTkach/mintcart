<?php
/**
 * Homepage Builder admin editor (MINTCART_FUNCTIONAL_SCOPE.md §10,
 * MINTCART_TECHNICAL_PLAN.md §17, MINTCART_IMPLEMENTATION_GUIDE.md §33).
 *
 * Built on the existing legacy OpenCart admin theme (Bootstrap 3 markup —
 * `.panel`/`.btn`/`form-horizontal`, the same shape `design/banner.php` and
 * `extension/module/latest.php` already use) for its overall page chrome —
 * the shared admin `header`/`column_left`/`footer` wraps every admin
 * screen, this one included, and still only ever loads Bootstrap 3/jQuery.
 *
 * The list screen's reordering now uses `McSortable`
 * (`assets/src/js/components/sortable.js`, see
 * `docs/developer/components/sortable.md`), and the add/edit form's
 * `image`-type settings fields (Hero/Text+Image) mount `mc-media-picker`
 * (`docs/developer/components/media-picker.md`, Functional Scope §25):
 * `enqueueMintAssets()` below, called from both `getList()` and
 * `getForm()`, queues
 * `assets/dist/css/components.min.css` (the Bootstrap-5-free trim of
 * `index.css` — safe to layer on top of the legacy theme's Bootstrap 3,
 * `docs/developer/admin/asset-enqueue.md`'s "The fix: two bundles, pick the
 * right one") and `assets/dist/js/admin.min.js`, the same pattern
 * `ControllerCatalogProduct::getForm()` already established as this
 * repo's first consumer.
 *
 * `TYPES` now lists all fifteen shipped block types (Products through
 * Gallery, MINTCART_IMPLEMENTATION_GUIDE.md §51's full first-wave catalog)
 * — each has a real Provider/Renderer/template merged to `master`
 * (`upload/system/library/mintcart/blocks/*.php`,
 * `ControllerCommonHome::render*Block()`). `FIELD_SCHEMA` is what makes
 * that tractable without 15 near-identical hardcoded forms: one
 * declarative field list per type (structural `settings_json` fields vs
 * localized `content_json` fields, mirroring exactly what that type's own
 * BlockProvider::build() reads), and `getForm()`/`prepareBlockData()` walk
 * it generically instead of switching on `type`.
 *
 * Five types (Banner Grid, Benefits, Testimonials, Brands, Gallery) have a
 * repeatable `items` list — each item's own structural fields (image path,
 * url, ...) and localized fields (alt, caption, ...). Building a real
 * dynamic add/remove-row JS UI for that is a separate increment from this
 * one (now that the asset bundle is enqueued here, it no longer needs to
 * resolve an asset-enqueue question of its own, just build the repeater
 * UI itself) — `items` stays a single raw-JSON textarea per list for now, a
 * real, working escape hatch (an admin can configure any of these blocks
 * today) rather than a half-built repeater. Each such field's `help_key`
 * shows the exact JSON shape.
 */
class ControllerDesignHomepage extends Controller {
	private $error = array();

	/** Every block type with a real, merged Provider/Renderer/template. */
	const TYPES = array(
		'products'     => 'text_type_products',
		'hero'         => 'text_type_hero',
		'categories'   => 'text_type_categories',
		'banner-grid'  => 'text_type_banner_grid',
		'benefits'     => 'text_type_benefits',
		'faq'          => 'text_type_faq',
		'testimonials' => 'text_type_testimonials',
		'brands'       => 'text_type_brands',
		'text-image'   => 'text_type_text_image',
		'cta'          => 'text_type_cta',
		'newsletter'   => 'text_type_newsletter',
		'spacer'       => 'text_type_spacer',
		'text'         => 'text_type_text',
		'html'         => 'text_type_html',
		'gallery'      => 'text_type_gallery'
	);

	const PAGE_CODE = 'home';

	/**
	 * Declarative settings/content field list per block type, matching
	 * each type's own BlockProvider::build() exactly (field names, which
	 * side of settings_json/content_json each lives on, and defaults).
	 *
	 * Field shape: array{
	 *     name: string, type: 'text'|'number'|'select'|'textarea'|'json',
	 *     default: mixed, label_key: string, help_key?: string,
	 *     options?: array<string, string>, min?: int
	 * }
	 *
	 * @return array<string, array{settings: list<array>, content: list<array>}>
	 */
	private static function fieldSchema() {
		$title = array('name' => 'title', 'type' => 'text', 'default' => '', 'label_key' => 'entry_title');

		return array(
			'products' => array(
				'settings' => array(
					array('name' => 'category_id', 'type' => 'number', 'default' => null, 'label_key' => 'entry_category_id', 'help_key' => 'help_category_id'),
					array('name' => 'limit', 'type' => 'number', 'default' => 8, 'min' => 1, 'label_key' => 'entry_limit'),
					array('name' => 'sort', 'type' => 'select', 'default' => 'p.date_added', 'label_key' => 'entry_sort', 'options' => array(
						'p.date_added' => 'Date Added', 'p.viewed' => 'Viewed', 'p.price' => 'Price', 'pd.name' => 'Name'
					)),
					array('name' => 'order', 'type' => 'select', 'default' => 'DESC', 'label_key' => 'entry_order', 'options' => array('ASC' => 'ASC', 'DESC' => 'DESC'))
				),
				'content' => array($title)
			),
			'hero' => array(
				'settings' => array(
					array('name' => 'image', 'type' => 'image', 'default' => '', 'label_key' => 'entry_image', 'help_key' => 'help_image'),
					array('name' => 'image_width', 'type' => 'number', 'default' => 1920, 'min' => 1, 'label_key' => 'entry_image_width'),
					array('name' => 'image_height', 'type' => 'number', 'default' => 600, 'min' => 1, 'label_key' => 'entry_image_height'),
					array('name' => 'cta_url', 'type' => 'text', 'default' => '', 'label_key' => 'entry_cta_url')
				),
				'content' => array(
					$title,
					array('name' => 'subtitle', 'type' => 'text', 'default' => '', 'label_key' => 'entry_subtitle'),
					array('name' => 'cta_label', 'type' => 'text', 'default' => '', 'label_key' => 'entry_cta_label')
				)
			),
			'categories' => array(
				'settings' => array(
					array('name' => 'parent_id', 'type' => 'number', 'default' => 0, 'min' => 0, 'label_key' => 'entry_parent_id', 'help_key' => 'help_parent_id'),
					array('name' => 'limit', 'type' => 'number', 'default' => 6, 'min' => 1, 'label_key' => 'entry_limit')
				),
				'content' => array($title)
			),
			'banner-grid' => array(
				'settings' => array(
					array('name' => 'columns', 'type' => 'select', 'default' => '3', 'label_key' => 'entry_columns', 'options' => array('2' => '2', '3' => '3', '4' => '4')),
					array('name' => 'items', 'type' => 'json', 'default' => array(), 'label_key' => 'entry_banner_items', 'help_key' => 'help_banner_items_settings')
				),
				'content' => array(
					$title,
					array('name' => 'items', 'type' => 'json', 'default' => array(), 'label_key' => 'entry_banner_items', 'help_key' => 'help_banner_items_content')
				)
			),
			'benefits' => array(
				'settings' => array(
					array('name' => 'items', 'type' => 'json', 'default' => array(), 'label_key' => 'entry_benefit_items', 'help_key' => 'help_benefit_items_settings')
				),
				'content' => array(
					$title,
					array('name' => 'items', 'type' => 'json', 'default' => array(), 'label_key' => 'entry_benefit_items', 'help_key' => 'help_benefit_items_content')
				)
			),
			'faq' => array(
				'settings' => array(),
				'content' => array(
					$title,
					array('name' => 'items', 'type' => 'json', 'default' => array(), 'label_key' => 'entry_faq_items', 'help_key' => 'help_faq_items')
				)
			),
			'testimonials' => array(
				'settings' => array(
					array('name' => 'items', 'type' => 'json', 'default' => array(), 'label_key' => 'entry_testimonial_items', 'help_key' => 'help_testimonial_items_settings')
				),
				'content' => array(
					$title,
					array('name' => 'items', 'type' => 'json', 'default' => array(), 'label_key' => 'entry_testimonial_items', 'help_key' => 'help_testimonial_items_content')
				)
			),
			'brands' => array(
				'settings' => array(
					array('name' => 'items', 'type' => 'json', 'default' => array(), 'label_key' => 'entry_brand_items', 'help_key' => 'help_brand_items_settings')
				),
				'content' => array(
					$title,
					array('name' => 'items', 'type' => 'json', 'default' => array(), 'label_key' => 'entry_brand_items', 'help_key' => 'help_brand_items_content')
				)
			),
			'text-image' => array(
				'settings' => array(
					array('name' => 'image', 'type' => 'image', 'default' => '', 'label_key' => 'entry_image', 'help_key' => 'help_image'),
					array('name' => 'image_width', 'type' => 'number', 'default' => 800, 'min' => 1, 'label_key' => 'entry_image_width'),
					array('name' => 'image_height', 'type' => 'number', 'default' => 600, 'min' => 1, 'label_key' => 'entry_image_height'),
					array('name' => 'image_position', 'type' => 'select', 'default' => 'right', 'label_key' => 'entry_image_position', 'options' => array('left' => 'Left', 'right' => 'Right'))
				),
				'content' => array(
					$title,
					array('name' => 'body', 'type' => 'textarea', 'default' => '', 'label_key' => 'entry_body', 'help_key' => 'help_body'),
					array('name' => 'image_alt', 'type' => 'text', 'default' => '', 'label_key' => 'entry_image_alt')
				)
			),
			'cta' => array(
				'settings' => array(),
				'content' => array(
					$title,
					array('name' => 'text', 'type' => 'textarea', 'default' => '', 'label_key' => 'entry_text'),
					array('name' => 'cta_label', 'type' => 'text', 'default' => '', 'label_key' => 'entry_cta_label'),
					array('name' => 'cta_url', 'type' => 'text', 'default' => '', 'label_key' => 'entry_cta_url')
				)
			),
			'newsletter' => array(
				'settings' => array(
					array('name' => 'action', 'type' => 'text', 'default' => '', 'label_key' => 'entry_action', 'help_key' => 'help_action'),
					array('name' => 'method', 'type' => 'select', 'default' => 'post', 'label_key' => 'entry_method', 'options' => array('post' => 'POST', 'get' => 'GET'))
				),
				'content' => array(
					$title,
					array('name' => 'text', 'type' => 'textarea', 'default' => '', 'label_key' => 'entry_text'),
					array('name' => 'placeholder', 'type' => 'text', 'default' => '', 'label_key' => 'entry_placeholder'),
					array('name' => 'submit_label', 'type' => 'text', 'default' => '', 'label_key' => 'entry_submit_label')
				)
			),
			'spacer' => array(
				'settings' => array(
					array('name' => 'size', 'type' => 'select', 'default' => 'md', 'label_key' => 'entry_size', 'options' => array('sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large', 'xl' => 'Extra Large'))
				),
				'content' => array()
			),
			'text' => array(
				'settings' => array(
					array('name' => 'text_align', 'type' => 'select', 'default' => 'left', 'label_key' => 'entry_text_align', 'options' => array('left' => 'Left', 'center' => 'Center'))
				),
				'content' => array(
					$title,
					array('name' => 'content', 'type' => 'textarea', 'default' => '', 'label_key' => 'entry_content_html', 'help_key' => 'help_body')
				)
			),
			'html' => array(
				'settings' => array(),
				'content' => array(
					array('name' => 'html', 'type' => 'textarea', 'default' => '', 'label_key' => 'entry_html', 'help_key' => 'help_html')
				)
			),
			'gallery' => array(
				'settings' => array(
					array('name' => 'columns', 'type' => 'select', 'default' => '4', 'label_key' => 'entry_columns', 'options' => array('2' => '2', '3' => '3', '4' => '4', '6' => '6')),
					array('name' => 'items', 'type' => 'json', 'default' => array(), 'label_key' => 'entry_gallery_items', 'help_key' => 'help_gallery_items_settings')
				),
				'content' => array(
					$title,
					array('name' => 'items', 'type' => 'json', 'default' => array(), 'label_key' => 'entry_gallery_items', 'help_key' => 'help_gallery_items_content')
				)
			)
		);
	}

	/** @return array{settings: list<array>, content: list<array>} */
	private static function fieldsForType($type) {
		$schema = self::fieldSchema();

		return isset($schema[$type]) ? $schema[$type] : array('settings' => array(), 'content' => array());
	}

	public function index() {
		$this->load->language('design/homepage');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('design/homepage');

		$this->getList();
	}

	public function add() {
		$this->load->language('design/homepage');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('design/homepage');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$this->model_design_homepage->addBlock($this->getPageId(), $this->prepareBlockData());

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('design/homepage', 'user_token=' . $this->session->data['user_token'], true));
		}

		$this->getForm();
	}

	public function edit() {
		$this->load->language('design/homepage');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('design/homepage');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$this->model_design_homepage->editBlock($this->request->get['block_id'], $this->prepareBlockData());

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('design/homepage', 'user_token=' . $this->session->data['user_token'], true));
		}

		$this->getForm();
	}

	public function delete() {
		$this->load->language('design/homepage');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('design/homepage');

		if (isset($this->request->post['selected']) && $this->validateDelete()) {
			foreach ($this->request->post['selected'] as $block_id) {
				$this->model_design_homepage->deleteBlock($block_id);
			}

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('design/homepage', 'user_token=' . $this->session->data['user_token'], true));
		}

		$this->getList();
	}

	/**
	 * Persists the list screen's per-row `order[<block_id>]` values. Plain
	 * form POST + redirect, still no AJAX — `homepage_list.twig`'s
	 * `McSortable` list keeps one hidden `order[<block_id>]` input per row
	 * (recomputed client-side on every `mc:sortable:reorder` event) inside
	 * the same `<form>`/"Save Order" button this always had, so this
	 * method's own contract (accept an `order` array, persist it, guarded
	 * by `page_id`) did not need to change to support drag-and-drop.
	 */
	public function saveOrder() {
		$this->load->language('design/homepage');

		$this->load->model('design/homepage');

		if (isset($this->request->post['order']) && is_array($this->request->post['order']) && $this->user->hasPermission('modify', 'design/homepage')) {
			$this->model_design_homepage->updateSortOrder($this->getPageId(), $this->request->post['order']);

			$this->session->data['success'] = $this->language->get('text_success');
		}

		$this->response->redirect($this->url->link('design/homepage', 'user_token=' . $this->session->data['user_token'], true));
	}

	/** Returns the `home` page's `page_id`, creating the row on first use. */
	private function getPageId() {
		$page = $this->model_design_homepage->getPage(self::PAGE_CODE);

		if ($page) {
			return (int)$page['page_id'];
		}

		return $this->model_design_homepage->addPage(self::PAGE_CODE, $this->language->get('text_home_page_name'));
	}

	/**
	 * Queues MintCart's own admin bundle (`docs/developer/admin/asset-enqueue.md`)
	 * so the block list's drag-and-drop reordering (`McSortable`,
	 * `data-mc-sortable` markup in `homepage_list.twig`) actually has CSS/JS
	 * behind it, and the add/edit form's `image`-type settings fields
	 * (Hero/Text+Image) can mount `mc-media-picker`. `components.min.css`
	 * (not `index.min.css`) deliberately excludes Bootstrap 5's
	 * reset/grid/utility layer, so it is safe to load
	 * alongside the legacy admin theme's Bootstrap 3 — see that doc's "The
	 * fix: two bundles, pick the right one" for why. Called from both
	 * `getList()` and `getForm()`.
	 */
	protected function enqueueMintAssets() {
		$this->document->addStyle(HTTP_CATALOG . 'assets/dist/css/components.min.css');
		$this->document->addScript(HTTP_CATALOG . 'assets/dist/js/admin.min.js');
	}

	protected function getList() {
		$this->enqueueMintAssets();

		$page_id = $this->getPageId();

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('design/homepage', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['add'] = $this->url->link('design/homepage/add', 'user_token=' . $this->session->data['user_token'], true);
		$data['delete'] = $this->url->link('design/homepage/delete', 'user_token=' . $this->session->data['user_token'], true);
		$data['save_order'] = $this->url->link('design/homepage/saveOrder', 'user_token=' . $this->session->data['user_token'], true);

		$data['blocks'] = array();

		$language_id = (int)$this->config->get('config_language_id');

		foreach ($this->model_design_homepage->getBlocks($page_id, $language_id) as $block) {
			$type_name = isset(self::TYPES[$block['type']]) ? $this->language->get(self::TYPES[$block['type']]) : $block['type'];

			$data['blocks'][] = array(
				'block_id'   => $block['block_id'],
				'type'       => $type_name,
				'title'      => isset($block['content']['title']) ? $block['content']['title'] : '',
				'status'     => $block['status'] ? $this->language->get('text_enabled') : $this->language->get('text_disabled'),
				'sort_order' => $block['sort_order'],
				'edit'       => $this->url->link('design/homepage/edit', 'user_token=' . $this->session->data['user_token'] . '&block_id=' . $block['block_id'], true)
			);
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];

			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		if (isset($this->request->post['selected'])) {
			$data['selected'] = (array)$this->request->post['selected'];
		} else {
			$data['selected'] = array();
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('design/homepage_list', $data));
	}

	protected function getForm() {
		$this->enqueueMintAssets();

		$data['text_form'] = !isset($this->request->get['block_id']) ? $this->language->get('text_add') : $this->language->get('text_edit');

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
		$data['error_type'] = isset($this->error['type']) ? $this->error['type'] : '';
		$data['error_json'] = isset($this->error['json']) ? $this->error['json'] : '';

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('design/homepage', 'user_token=' . $this->session->data['user_token'], true)
		);

		if (!isset($this->request->get['block_id'])) {
			$data['action'] = $this->url->link('design/homepage/add', 'user_token=' . $this->session->data['user_token'], true);
		} else {
			$data['action'] = $this->url->link('design/homepage/edit', 'user_token=' . $this->session->data['user_token'] . '&block_id=' . $this->request->get['block_id'], true);
		}

		$data['cancel'] = $this->url->link('design/homepage', 'user_token=' . $this->session->data['user_token'], true);

		$block_info = null;
		$block_descriptions = array();

		if (isset($this->request->get['block_id']) && ($this->request->server['REQUEST_METHOD'] != 'POST')) {
			$block_info = $this->model_design_homepage->getBlock($this->request->get['block_id']);
			$block_descriptions = $this->model_design_homepage->getBlockDescriptions($this->request->get['block_id']);
		}

		$data['types'] = array();

		foreach (self::TYPES as $code => $language_key) {
			$data['types'][] = array(
				'code' => $code,
				'name' => $this->language->get($language_key)
			);
		}

		if (isset($this->request->post['type']) && array_key_exists($this->request->post['type'], self::TYPES)) {
			$data['type'] = $this->request->post['type'];
		} elseif (isset($this->request->get['type']) && array_key_exists($this->request->get['type'], self::TYPES)) {
			// A plain GET reload from the type <select>'s onchange handler
			// (see homepage_form.twig) - lets the settings/content fields
			// switch to match the newly chosen type with no JS bundle
			// needed (see this file's header comment on why one isn't
			// enqueued here yet).
			$data['type'] = $this->request->get['type'];
		} elseif (!empty($block_info) && array_key_exists($block_info['type'], self::TYPES)) {
			$data['type'] = $block_info['type'];
		} else {
			$data['type'] = 'products';
		}

		if (isset($this->request->post['status'])) {
			$data['status'] = $this->request->post['status'];
		} elseif (!empty($block_info)) {
			$data['status'] = $block_info['status'];
		} else {
			$data['status'] = true;
		}

		$fields = self::fieldsForType($data['type']);

		$data['settings_legend'] = $this->language->get(self::TYPES[$data['type']]) . ' ' . $this->language->get('text_settings_suffix');

		if (isset($this->request->post['settings']) && is_array($this->request->post['settings'])) {
			$settings_source = 'post';
			$raw_settings = $this->request->post['settings'];
		} elseif (!empty($block_info)) {
			$settings_source = 'db';
			$raw_settings = $block_info['settings'];
		} else {
			$settings_source = 'default';
			$raw_settings = array();
		}

		$data['settings_fields'] = array();

		foreach ($fields['settings'] as $field) {
			$raw = isset($raw_settings[$field['name']]) ? $raw_settings[$field['name']] : null;

			$data['settings_fields'][] = array(
				'name'    => $field['name'],
				'type'    => $field['type'],
				'label'   => $this->language->get($field['label_key']),
				'help'    => isset($field['help_key']) ? $this->language->get($field['help_key']) : '',
				'min'     => isset($field['min']) ? $field['min'] : null,
				'options' => isset($field['options']) ? $field['options'] : array(),
				'value'   => $this->fieldDisplayValue($field, $raw, $settings_source === 'post')
			);
		}

		$this->load->model('localisation/language');

		$data['languages'] = $this->model_localisation_language->getLanguages();

		$data['content_field_defs'] = array();

		foreach ($fields['content'] as $field) {
			$data['content_field_defs'][] = array(
				'name'  => $field['name'],
				'type'  => $field['type'],
				'label' => $this->language->get($field['label_key']),
				'help'  => isset($field['help_key']) ? $this->language->get($field['help_key']) : ''
			);
		}

		if (isset($this->request->post['block_content']) && is_array($this->request->post['block_content'])) {
			$content_source = 'post';
			$raw_content_by_language = $this->request->post['block_content'];
		} else {
			$content_source = 'db';
			$raw_content_by_language = $block_descriptions;
		}

		$data['block_content'] = array();

		foreach ($data['languages'] as $language) {
			$language_id = (int)$language['language_id'];
			$raw = isset($raw_content_by_language[$language_id]) ? $raw_content_by_language[$language_id] : array();

			$language_values = array();

			foreach ($fields['content'] as $field) {
				$field_raw = isset($raw[$field['name']]) ? $raw[$field['name']] : null;

				$language_values[$field['name']] = $this->fieldDisplayValue($field, $field_raw, $content_source === 'post');
			}

			$data['block_content'][$language_id] = $language_values;
		}

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('design/homepage_form', $data));
	}

	/**
	 * Renders one field's value for the form: a `json` field shows the raw
	 * text the admin just typed when it came back from a failed POST (so a
	 * typo doesn't wipe out what they wrote), otherwise a pretty-printed
	 * re-encode of the stored array; every other field type is a plain
	 * string, falling back to the field's own default when unset.
	 */
	private function fieldDisplayValue(array $field, $raw, $from_post) {
		if ($field['type'] === 'json') {
			if ($from_post) {
				return is_string($raw) ? $raw : '';
			}

			return $raw ? json_encode($raw, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
		}

		if ($field['type'] === 'select') {
			return ($raw !== null && array_key_exists($raw, $field['options'])) ? $raw : $field['default'];
		}

		if ($raw !== null && $raw !== '') {
			return $raw;
		}

		return $field['default'] === null ? '' : $field['default'];
	}

	/**
	 * @return array{type: string, status: bool, settings: array<string, mixed>, content: array<int, array<string, mixed>>}
	 */
	private function prepareBlockData() {
		$type = isset($this->request->post['type']) ? $this->request->post['type'] : '';

		$fields = self::fieldsForType($type);

		$posted_settings = isset($this->request->post['settings']) && is_array($this->request->post['settings']) ? $this->request->post['settings'] : array();

		$settings = array();

		foreach ($fields['settings'] as $field) {
			$settings[$field['name']] = $this->normalizeFieldValue($field, isset($posted_settings[$field['name']]) ? $posted_settings[$field['name']] : null);
		}

		$posted_content = isset($this->request->post['block_content']) && is_array($this->request->post['block_content']) ? $this->request->post['block_content'] : array();

		$content = array();

		foreach ($posted_content as $language_id => $value) {
			$language_content = array();

			foreach ($fields['content'] as $field) {
				$language_content[$field['name']] = $this->normalizeFieldValue($field, is_array($value) && isset($value[$field['name']]) ? $value[$field['name']] : null);
			}

			$content[(int)$language_id] = $language_content;
		}

		return array(
			'type'     => $type,
			'status'   => !empty($this->request->post['status']),
			'settings' => $settings,
			'content'  => $content
		);
	}

	/** Casts one posted field value per its schema type, ready for storage. */
	private function normalizeFieldValue(array $field, $value) {
		switch ($field['type']) {
			case 'number':
				return ($value !== null && $value !== '') ? (int)$value : $field['default'];

			case 'select':
				return (is_string($value) && array_key_exists($value, $field['options'])) ? $value : $field['default'];

			case 'json':
				if (!is_string($value) || trim($value) === '') {
					return array();
				}

				$decoded = json_decode($value, true);

				return is_array($decoded) ? $decoded : array();

			case 'text':
			case 'textarea':
			default:
				return is_string($value) ? $value : (string)$field['default'];
		}
	}

	protected function validateForm() {
		if (!$this->user->hasPermission('modify', 'design/homepage')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		$type = isset($this->request->post['type']) ? $this->request->post['type'] : '';

		if (!array_key_exists($type, self::TYPES)) {
			$this->error['type'] = $this->language->get('error_type');

			return !$this->error;
		}

		$fields = self::fieldsForType($type);

		$posted_settings = isset($this->request->post['settings']) && is_array($this->request->post['settings']) ? $this->request->post['settings'] : array();

		foreach ($fields['settings'] as $field) {
			if ($field['type'] === 'json' && $this->hasInvalidJson(isset($posted_settings[$field['name']]) ? $posted_settings[$field['name']] : null)) {
				$this->error['json'] = $this->language->get('error_json');

				return !$this->error;
			}
		}

		$posted_content = isset($this->request->post['block_content']) && is_array($this->request->post['block_content']) ? $this->request->post['block_content'] : array();

		foreach ($posted_content as $language_values) {
			if (!is_array($language_values)) {
				continue;
			}

			foreach ($fields['content'] as $field) {
				if ($field['type'] === 'json' && $this->hasInvalidJson(isset($language_values[$field['name']]) ? $language_values[$field['name']] : null)) {
					$this->error['json'] = $this->language->get('error_json');

					return !$this->error;
				}
			}
		}

		return !$this->error;
	}

	/** A blank field is valid (normalizes to an empty list) - only non-blank, malformed JSON is an error. */
	private function hasInvalidJson($value) {
		if (!is_string($value) || trim($value) === '') {
			return false;
		}

		json_decode($value, true);

		return json_last_error() !== JSON_ERROR_NONE;
	}

	protected function validateDelete() {
		if (!$this->user->hasPermission('modify', 'design/homepage')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
}
