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
 * Types with a repeatable list (Slider slides and promo cards, Banner Grid,
 * Benefits, Testimonials, Brands, Gallery, FAQ) describe it in `listSchema()`:
 * one row per item, each row holding the item's shared fields (picture, link,
 * colour - `settings_json`) and its per-language texts (`content_json`) side by
 * side, so a reorder keeps both in step. `homepage_form.twig` renders the rows
 * with drag & drop, add / remove and the OpenCart file manager for pictures
 * (`assets/src/js/components/homepage-items.js`).
 */
class ControllerDesignHomepage extends Controller {
	private $error = array();

	/** Every block type with a real, merged Provider/Renderer/template. */
	const TYPES = array(
		'slider'       => 'text_type_slider',
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

	/** Font Awesome icon of each block type in the list. */
	const TYPE_ICONS = array(
		'slider' => 'fa-picture-o', 'products' => 'fa-th-large', 'hero' => 'fa-flag', 'categories' => 'fa-folder-open-o',
		'banner-grid' => 'fa-th', 'benefits' => 'fa-check-circle-o', 'faq' => 'fa-question-circle', 'testimonials' => 'fa-comments-o',
		'brands' => 'fa-tags', 'text-image' => 'fa-columns', 'cta' => 'fa-bullhorn', 'newsletter' => 'fa-envelope-o',
		'spacer' => 'fa-arrows-v', 'text' => 'fa-font', 'html' => 'fa-code', 'gallery' => 'fa-camera'
	);

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
					array('name' => 'order', 'type' => 'select', 'default' => 'DESC', 'label_key' => 'entry_order', 'options' => array('ASC' => 'ASC', 'DESC' => 'DESC')),
					array('name' => 'more_url', 'type' => 'text', 'default' => '', 'label_key' => 'entry_more_url', 'help_key' => 'help_more_url')
				),
				'content' => array($title, array('name' => 'more_label', 'type' => 'text', 'default' => '', 'label_key' => 'entry_more_label'))
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
			'slider' => array(
				'settings' => array(
					array('name' => 'layout', 'type' => 'select', 'default' => 'split', 'before_list' => 'cards', 'label_key' => 'entry_slider_layout', 'options' => array('split' => 'Text + picture', 'full' => 'Picture behind the text', 'cards' => 'Slider + 2 promo cards')),
					array('name' => 'autoplay', 'type' => 'number', 'default' => 6000, 'min' => 0, 'label_key' => 'entry_autoplay', 'help_key' => 'help_autoplay'),
					array('name' => 'items', 'type' => 'json', 'default' => array(), 'label_key' => 'entry_slider_items', 'help_key' => 'help_slider_items_settings'),
					array('name' => 'cards', 'type' => 'json', 'default' => array(), 'label_key' => 'entry_slider_cards', 'help_key' => 'help_slider_cards_settings')
				),
				'content' => array(
					array('name' => 'items', 'type' => 'json', 'default' => array(), 'label_key' => 'entry_slider_items', 'help_key' => 'help_slider_items_content'),
					array('name' => 'cards', 'type' => 'json', 'default' => array(), 'label_key' => 'entry_slider_cards', 'help_key' => 'help_slider_cards_content')
				)
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
					array('name' => 'source', 'type' => 'select', 'default' => 'manual', 'label_key' => 'entry_testimonial_source', 'help_key' => 'help_testimonial_source', 'options' => array('manual' => 'Written here', 'reviews' => 'Latest customer reviews')),
					array('name' => 'limit', 'type' => 'number', 'default' => 3, 'min' => 1, 'label_key' => 'entry_limit', 'help_key' => 'help_testimonial_limit')
				),
				'content' => array(
					$title,
					array('name' => 'more_label', 'type' => 'text', 'default' => '', 'label_key' => 'entry_more_label')
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

	/**
	 * Repeatable lists per block type. Each item field has a `scope`:
	 * `settings` = the same in every language (picture, link, colour),
	 * `content` = translated. `extra` fields sit behind a "More options"
	 * toggle in the row. `options_lang` = the select's option labels are
	 * language keys.
	 *
	 * @return array<string, array<string, array{label_key: string, fields: list<array>, show_when?: array{0: string, 1: string}, max?: int}>>
	 */
	private static function listSchema() {
		$image = array('name' => 'image', 'type' => 'image', 'scope' => 'settings', 'label_key' => 'entry_item_image');
		$alt = array('name' => 'alt', 'type' => 'text', 'scope' => 'content', 'label_key' => 'entry_item_alt');
		$caption = array('name' => 'caption', 'type' => 'text', 'scope' => 'content', 'label_key' => 'entry_item_caption');
		$url = array('name' => 'url', 'type' => 'text', 'scope' => 'settings', 'label_key' => 'entry_item_url', 'placeholder' => 'index.php?route=...');

		return array(
			'slider' => array(
				'items' => array(
					'label_key' => 'legend_slider_items',
					'fields' => array(
						$image,
						array('name' => 'link', 'type' => 'text', 'scope' => 'settings', 'label_key' => 'entry_item_link', 'placeholder' => 'index.php?route=...'),
						array('name' => 'color', 'type' => 'select', 'scope' => 'settings', 'label_key' => 'entry_item_color', 'options_lang' => true, 'options' => array('navy' => 'text_color_navy', 'light' => 'text_color_light')),
						array('name' => 'btn2_link', 'type' => 'text', 'scope' => 'settings', 'label_key' => 'entry_item_btn2_link', 'extra' => true, 'placeholder' => 'index.php?route=...'),
						array('name' => 'title', 'type' => 'text', 'scope' => 'content', 'label_key' => 'entry_item_title', 'help_key' => 'help_item_slide_title'),
						array('name' => 'subtitle', 'type' => 'textarea', 'scope' => 'content', 'label_key' => 'entry_item_subtitle'),
						array('name' => 'btn_text', 'type' => 'text', 'scope' => 'content', 'label_key' => 'entry_item_btn_text'),
						array('name' => 'eyebrow', 'type' => 'text', 'scope' => 'content', 'label_key' => 'entry_item_eyebrow', 'extra' => true),
						array('name' => 'price', 'type' => 'text', 'scope' => 'content', 'label_key' => 'entry_item_price', 'extra' => true),
						array('name' => 'old_price', 'type' => 'text', 'scope' => 'content', 'label_key' => 'entry_item_old_price', 'extra' => true),
						array('name' => 'btn2_text', 'type' => 'text', 'scope' => 'content', 'label_key' => 'entry_item_btn2_text', 'extra' => true),
						array('name' => 'image_alt', 'type' => 'text', 'scope' => 'content', 'label_key' => 'entry_item_alt', 'extra' => true)
					)
				),
				'cards' => array(
					'label_key' => 'legend_slider_cards',
					'show_when' => array('layout', 'cards'),
					'max' => 2,
					'fields' => array(
						$image,
						array('name' => 'link', 'type' => 'text', 'scope' => 'settings', 'label_key' => 'entry_item_link', 'placeholder' => 'index.php?route=...'),
						array('name' => 'kicker', 'type' => 'text', 'scope' => 'content', 'label_key' => 'entry_item_kicker'),
						array('name' => 'title', 'type' => 'text', 'scope' => 'content', 'label_key' => 'entry_item_title'),
						array('name' => 'link_text', 'type' => 'text', 'scope' => 'content', 'label_key' => 'entry_item_link_text')
					)
				)
			),
			'banner-grid' => array(
				'items' => array('label_key' => 'legend_banner_items', 'fields' => array($image, $url, $alt, $caption))
			),
			'benefits' => array(
				'items' => array('label_key' => 'legend_benefit_items', 'fields' => array(
					array('name' => 'icon', 'type' => 'select', 'scope' => 'settings', 'label_key' => 'entry_item_icon', 'options' => array(
						'truck' => 'truck', 'arrow-repeat' => 'arrow-repeat', 'shield-check' => 'shield-check', 'headset' => 'headset', 'credit-card' => 'credit-card',
						'gift' => 'gift', 'box-seam' => 'box-seam', 'heart' => 'heart', 'star' => 'star', 'tag' => 'tag', 'telephone' => 'telephone', 'geo-alt' => 'geo-alt', 'check' => 'check'
					)),
					array('name' => 'title', 'type' => 'text', 'scope' => 'content', 'label_key' => 'entry_item_title'),
					array('name' => 'description', 'type' => 'textarea', 'scope' => 'content', 'label_key' => 'entry_item_description')
				))
			),
			'testimonials' => array(
				'items' => array('label_key' => 'legend_testimonial_items', 'show_when' => array('source', 'manual'), 'fields' => array(
					array('name' => 'avatar', 'type' => 'image', 'scope' => 'settings', 'label_key' => 'entry_item_avatar'),
					array('name' => 'rating', 'type' => 'select', 'scope' => 'settings', 'label_key' => 'entry_item_rating', 'options' => array('' => '-', '5' => '5', '4' => '4', '3' => '3', '2' => '2', '1' => '1')),
					array('name' => 'quote', 'type' => 'textarea', 'scope' => 'content', 'label_key' => 'entry_item_quote'),
					array('name' => 'author_name', 'type' => 'text', 'scope' => 'content', 'label_key' => 'entry_item_author_name'),
					array('name' => 'author_role', 'type' => 'text', 'scope' => 'content', 'label_key' => 'entry_item_author_role')
				))
			),
			'brands' => array(
				'items' => array('label_key' => 'legend_brand_items', 'fields' => array($image, $url, $alt))
			),
			'gallery' => array(
				'items' => array('label_key' => 'legend_gallery_items', 'fields' => array($image, $alt, $caption))
			),
			'faq' => array(
				'items' => array('label_key' => 'legend_faq_items', 'fields' => array(
					array('name' => 'question', 'type' => 'text', 'scope' => 'content', 'label_key' => 'entry_item_question'),
					array('name' => 'answer', 'type' => 'textarea', 'scope' => 'content', 'label_key' => 'entry_item_answer')
				))
			)
		);
	}

	/** @return array{settings: list<array>, content: list<array>, lists: array<string, array>} */
	private static function fieldsForType($type) {
		$schema = self::fieldSchema();
		$lists = self::listSchema();

		$fields = isset($schema[$type]) ? $schema[$type] : array('settings' => array(), 'content' => array());

		// The raw-JSON `items` / `cards` fields are replaced by the row editor (listSchema()).
		foreach (array('settings', 'content') as $scope) {
			$fields[$scope] = array_values(array_filter($fields[$scope], function ($field) {
				return $field['type'] !== 'json';
			}));
		}

		$fields['lists'] = isset($lists[$type]) ? $lists[$type] : array();

		return $fields;
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

		$saved = isset($this->request->post['order']) && is_array($this->request->post['order']) && $this->user->hasPermission('modify', 'design/homepage');

		if ($saved) {
			$this->model_design_homepage->updateSortOrder($this->getPageId(), $this->request->post['order']);
		}

		if ($this->isAjax()) {
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode(array('success' => $saved)));

			return;
		}

		if ($saved) {
			$this->session->data['success'] = $this->language->get('text_success');
		}

		$this->response->redirect($this->url->link('design/homepage', 'user_token=' . $this->session->data['user_token'], true));
	}

	private function isAjax() {
		return isset($this->request->server['HTTP_X_REQUESTED_WITH']) && strtolower($this->request->server['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
	}

	/** route=design/homepage/status - POST block_id, status (0/1): the list's enable / disable switch. */
	public function status() {
		$this->load->model('design/homepage');

		$ok = $this->user->hasPermission('modify', 'design/homepage') && isset($this->request->post['block_id']);

		if ($ok) {
			$this->model_design_homepage->setStatus($this->getPageId(), (int)$this->request->post['block_id'], !empty($this->request->post['status']));
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode(array('success' => $ok)));
	}

	/** One line under a block's name in the list: its title and how many rows its lists hold. */
	private function summarizeBlock(array $block) {
		$parts = array();

		$title = isset($block['content']['title']) && is_string($block['content']['title']) ? trim(strip_tags($block['content']['title'])) : '';

		if ($title !== '') {
			$parts[] = $title;
		}

		$lists = self::listSchema();

		if (isset($lists[$block['type']])) {
			foreach ($lists[$block['type']] as $name => $list) {
				$count = max(
					isset($block['settings'][$name]) && is_array($block['settings'][$name]) ? count($block['settings'][$name]) : 0,
					isset($block['content'][$name]) && is_array($block['content'][$name]) ? count($block['content'][$name]) : 0
				);

				if ($count) {
					$parts[] = $this->language->get($list['label_key']) . ': ' . $count;
				}
			}
		}

		if (!$parts && isset($block['content']) && is_array($block['content'])) {
			foreach ($block['content'] as $value) {
				if (is_string($value) && trim(strip_tags($value)) !== '') {
					$parts[] = mb_strimwidth(trim(strip_tags($value)), 0, 90, '…');

					break;
				}
			}
		}

		return implode(' · ', $parts);
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
		$data['status_url'] = $this->url->link('design/homepage/status', 'user_token=' . $this->session->data['user_token'], true);

		$data['blocks'] = array();

		$language_id = (int)$this->config->get('config_language_id');

		foreach ($this->model_design_homepage->getBlocks($page_id, $language_id) as $block) {
			$type_name = isset(self::TYPES[$block['type']]) ? $this->language->get(self::TYPES[$block['type']]) : $block['type'];

			$data['blocks'][] = array(
				'block_id'   => $block['block_id'],
				'type'       => $type_name,
				'icon'       => isset(self::TYPE_ICONS[$block['type']]) ? self::TYPE_ICONS[$block['type']] : 'fa-square-o',
				'summary'    => $this->summarizeBlock($block),
				'status_on'  => $block['status'],
				'delete'     => $this->url->link('design/homepage/delete', 'user_token=' . $this->session->data['user_token'], true),
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

		$this->load->model('tool/image');

		$data['placeholder'] = $this->model_tool_image->resize('no_image.png', 100, 100);

		$data['settings_fields'] = array();

		foreach ($fields['settings'] as $field) {
			$raw = isset($raw_settings[$field['name']]) ? $raw_settings[$field['name']] : null;

			$data['settings_fields'][] = array(
				'name'    => $field['name'],
				'before_list' => isset($field['before_list']) ? $field['before_list'] : '',
				'thumb'   => ($field['type'] === 'image' && is_string($raw) && $raw !== '' && is_file(DIR_IMAGE . $raw)) ? $this->model_tool_image->resize($raw, 100, 100) : $data['placeholder'],
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

		$data['ai_languages_count'] = count($data['languages']);
		$data['ai_languages'] = json_encode(array_map(function ($language) {
			return array('id' => (int)$language['language_id'], 'code' => $language['code'], 'name' => $language['name']);
		}, array_values($data['languages'])), JSON_UNESCAPED_UNICODE);

		$data['lists'] = $this->buildLists($fields['lists'], $raw_settings, $raw_content_by_language, $data['languages'], $data['placeholder']);

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('design/homepage_form', $data));
	}

	/**
	 * The row editor's data: for every list of the block type, one row per
	 * item with its shared values, its per-language texts and the thumbnails
	 * of its picture fields, plus an empty row the JS clones for "Add".
	 *
	 * @param array<string, array> $lists listSchema() entry of the type
	 * @param array<int, array> $languages
	 * @return list<array>
	 */
	/**
	 * Which column of a row a field sits in (picture fields get their own narrow column):
	 * 1 = the main texts, 2 = links and buttons, 3 = the small choices (colour, icon, rating).
	 */
	const ITEM_COLUMN = array(
		'btn_text' => 2, 'btn2_text' => 2, 'link' => 2, 'btn2_link' => 2, 'url' => 2, 'link_text' => 2,
		'color' => 3, 'icon' => 3, 'rating' => 3, 'image_alt' => 3
	);

	private function buildLists(array $lists, $raw_settings, $raw_content_by_language, array $languages, $placeholder) {
		$result = array();

		foreach ($lists as $name => $list) {
			$shared = isset($raw_settings[$name]) && is_array($raw_settings[$name]) ? array_values($raw_settings[$name]) : array();
			$texts = array();

			foreach ($languages as $language) {
				$language_id = (int)$language['language_id'];

				$texts[$language_id] = isset($raw_content_by_language[$language_id][$name]) && is_array($raw_content_by_language[$language_id][$name])
					? array_values($raw_content_by_language[$language_id][$name])
					: array();
			}

			$count = count($shared);

			foreach ($texts as $items) {
				$count = max($count, count($items));
			}

			$defs = array();
			$images = array();
			$columns = array();

			foreach ($list['fields'] as $field) {
				$options = isset($field['options']) ? $field['options'] : array();

				if (!empty($field['options_lang'])) {
					foreach ($options as $value => $key) {
						$options[$value] = $this->language->get($key);
					}
				}

				$def = array(
					'name'        => $field['name'],
					'type'        => $field['type'],
					'scope'       => $field['scope'],
					'label'       => $this->language->get($field['label_key']),
					'help'        => isset($field['help_key']) ? $this->language->get($field['help_key']) : '',
					'options'     => $options,
					'extra'       => !empty($field['extra']),
					'placeholder' => isset($field['placeholder']) ? $field['placeholder'] : ''
				);

				$defs[] = $def;

				if ($field['type'] === 'image') {
					$images[] = $def;
				} else {
					$column = isset(self::ITEM_COLUMN[$field['name']]) ? self::ITEM_COLUMN[$field['name']] : 1;

					$columns[$column][] = $def;
				}
			}

			ksort($columns);

			$rows = array();

			for ($i = 0; $i < $count; $i++) {
				$row = array('settings' => array(), 'content' => array(), 'thumbs' => array(), 'extra_filled' => false);

				foreach ($list['fields'] as $field) {
					if ($field['scope'] === 'settings') {
						$value = isset($shared[$i][$field['name']]) && is_scalar($shared[$i][$field['name']]) ? (string)$shared[$i][$field['name']] : '';

						$row['settings'][$field['name']] = $value;

						if ($field['type'] === 'image') {
							$row['thumbs'][$field['name']] = ($value !== '' && is_file(DIR_IMAGE . $value)) ? $this->model_tool_image->resize($value, 100, 100) : $placeholder;
						}

						if (!empty($field['extra']) && $value !== '') {
							$row['extra_filled'] = true;
						}
					} else {
						foreach ($texts as $language_id => $items) {
							$value = isset($items[$i][$field['name']]) && is_scalar($items[$i][$field['name']]) ? (string)$items[$i][$field['name']] : '';

							$row['content'][$language_id][$field['name']] = $value;

							if (!empty($field['extra']) && $value !== '') {
								$row['extra_filled'] = true;
							}
						}
					}
				}

				$rows[] = $row;
			}

			$blank = array('settings' => array(), 'content' => array(), 'thumbs' => array(), 'extra_filled' => false);

			foreach ($list['fields'] as $field) {
				if ($field['scope'] === 'settings') {
					$blank['settings'][$field['name']] = '';

					if ($field['type'] === 'image') {
						$blank['thumbs'][$field['name']] = $placeholder;
					}
				} else {
					foreach ($texts as $language_id => $items) {
						$blank['content'][$language_id][$field['name']] = '';
					}
				}
			}

			$result[] = array(
				'name'      => $name,
				'label'     => $this->language->get($list['label_key']),
				'fields'    => $defs,
				'images'    => $images,
				'has_extra' => (bool)array_filter($defs, function ($def) {
					return $def['extra'];
				}),
				'columns'   => $columns,
				'rows'      => $rows,
				'blank'     => $blank,
				'max'       => isset($list['max']) ? (int)$list['max'] : 0,
				'show_when' => isset($list['show_when']) ? $list['show_when'] : array()
			);
		}

		return $result;
	}

	/**
	 * Posted list rows -> the stored shape. Rows are kept in the posted order;
	 * a row with every field empty (in every language) is dropped from the
	 * shared list and from every language alike, so the parallel arrays the
	 * block providers read by index stay aligned.
	 *
	 * @param array<string, array> $lists
	 * @param array<string, mixed> $posted_settings
	 * @param array<int, mixed> $posted_content
	 * @return array{settings: array<string, list<array>>, content: array<int, array<string, list<array>>>}
	 */
	private function normalizeLists(array $lists, array $posted_settings, array $posted_content) {
		$settings = array();
		$content = array();

		foreach ($posted_content as $language_id => $value) {
			$content[(int)$language_id] = array();
		}

		foreach ($lists as $name => $list) {
			$shared = isset($posted_settings[$name]) && is_array($posted_settings[$name]) ? $posted_settings[$name] : array();
			$keys = array_keys($shared);

			foreach ($posted_content as $value) {
				if (is_array($value) && isset($value[$name]) && is_array($value[$name])) {
					$keys = array_merge($keys, array_keys($value[$name]));
				}
			}

			$keys = array_values(array_unique($keys));

			usort($keys, function ($a, $b) {
				return (int)$a <=> (int)$b;
			});

			$settings[$name] = array();
			$kept = 0;

			foreach (array_keys($content) as $language_id) {
				$content[$language_id][$name] = array();
			}

			foreach ($keys as $key) {
				$row_settings = array();
				$row_content = array();
				$filled = false;

				foreach ($list['fields'] as $field) {
					if ($field['scope'] === 'settings') {
						$value = $this->normalizeItemValue($field, isset($shared[$key][$field['name']]) ? $shared[$key][$field['name']] : null);

						$row_settings[$field['name']] = $value;

						// A select always posts a value (its first option): it does not make a row a row.
						if ($value !== '' && $field['type'] !== 'select') {
							$filled = true;
						}
					}
				}

				foreach (array_keys($content) as $language_id) {
					$row_content[$language_id] = array();

					foreach ($list['fields'] as $field) {
						if ($field['scope'] === 'content') {
							$raw = isset($posted_content[$language_id][$name][$key][$field['name']]) ? $posted_content[$language_id][$name][$key][$field['name']] : null;
							$value = $this->normalizeItemValue($field, $raw);

							$row_content[$language_id][$field['name']] = $value;

							if ($value !== '') {
								$filled = true;
							}
						}
					}
				}

				// A row with nothing typed or picked is not a row.
				if (!$filled) {
					continue;
				}

				if (!empty($list['max']) && $kept >= $list['max']) {
					break;
				}

				$kept++;

				// FAQ and other text-only lists have no shared fields: nothing to store beside the texts.
				if ($row_settings) {
					$settings[$name][] = $row_settings;
				}

				foreach ($row_content as $language_id => $values) {
					$content[$language_id][$name][] = $values;
				}
			}
		}

		return array('settings' => $settings, 'content' => $content);
	}

	/**
	 * OpenCart HTML-escapes every posted string (Request::clean); the block data is stored as typed,
	 * and escaped again when it is put into a form control (homepage_form.twig `|e`).
	 */
	private function unescapePosted($value) {
		return html_entity_decode((string)$value, ENT_QUOTES, 'UTF-8');
	}

	/** One item field: always a trimmed string; a select only keeps a known option. */
	private function normalizeItemValue(array $field, $value) {
		$value = is_string($value) ? trim($this->unescapePosted($value)) : '';

		if ($field['type'] === 'select') {
			return array_key_exists($value, $field['options']) ? $value : '';
		}

		return $value;
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

		$rows = $this->normalizeLists($fields['lists'], $posted_settings, $posted_content);

		$settings += $rows['settings'];

		foreach ($rows['content'] as $language_id => $lists) {
			$content[$language_id] = (isset($content[$language_id]) ? $content[$language_id] : array()) + $lists;
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
				return is_string($value) ? $this->unescapePosted($value) : (string)$field['default'];
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
