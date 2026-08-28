<?php
/**
 * Homepage Builder admin editor (MINTCART_FUNCTIONAL_SCOPE.md §10,
 * MINTCART_TECHNICAL_PLAN.md §17, MINTCART_IMPLEMENTATION_GUIDE.md §33).
 *
 * Phase 10's storage (`mc_page`/`mc_block`) and its first real block type
 * (`MintCart\Blocks\ProductsBlockProvider`, wired end-to-end through
 * `ControllerCommonHome::buildMintBlocks()`) already existed on `master`;
 * this is the missing other half — until now a block instance had to be
 * inserted into the database by hand to ever be visible (see
 * `docs/developer/blocks/products.md` and `docs/IMPLEMENTATION_STATUS.md`'s
 * Phase 10 "Next step"). This screen lets an admin add/edit/reorder/
 * enable-disable/delete blocks on the `home` page.
 *
 * Deliberately built on the existing legacy OpenCart admin theme
 * (Bootstrap 3 markup — `.panel`/`.btn`/`form-horizontal`, the same shape
 * `design/banner.php` and `extension/module/latest.php` already use), not
 * MintCart's own `mc-*`/Bootstrap 5 admin primitives (`McSortable`
 * included, despite it existing specifically for this screen per its own
 * doc comment). Reason: the shared admin `header`/`column_left`/`footer`
 * chrome wraps every admin screen, this one included, and has never had
 * MintCart's asset bundle (`assets/dist/css/index.min.css` /
 * `assets/dist/js/admin.min.js`) enqueued into it anywhere in this repo.
 * Enqueuing that bundle's Bootstrap 5 CSS here would load it globally
 * across every other (Bootstrap-3-based) admin screen too, which
 * `CLAUDE.md` §10 explicitly forbids ("Do not globally load Bootstrap 3
 * and Bootstrap 5 together" — `.btn`/`.container`/`.modal`/`.dropdown`/
 * `.alert` all collide). Whether to migrate the whole admin shell to
 * Bootstrap 5, scope MintCart's own screens some other isolated way, or
 * keep new MintCart admin screens on the legacy theme until a dedicated
 * Mint Admin Shell phase is a real architectural decision this run does
 * not guess at (`CLAUDE.md` §20) — flagged for the project owner in this
 * change's pull request description. Reordering therefore uses a plain
 * per-row `sort_order` number input (the same pattern `banner_image` rows
 * already use in `design/banner_form.twig`), not McSortable drag-and-drop.
 *
 * `TYPES` currently lists only `products` — the sole block type with a
 * real Provider/Renderer/template merged to `master`. The other first-wave
 * types (Hero, Categories, Banner Grid, Benefits, Text, Text/Image,
 * Brands, Testimonials, CTA, Newsletter, FAQ, Gallery, HTML, Spacer) each
 * have their own open pull request in progress as of this run's step-0
 * duplicate-work check; adding a typed settings form for any of them here
 * ahead of knowing its merged provider's real settings shape would risk
 * guessing at (and conflicting with) that work. `mc_block` storage stays
 * type-agnostic (`type` + `settings_json`, MINTCART_TECHNICAL_PLAN.md
 * §16), so extending `TYPES` by one array entry — plus that type's own
 * settings fields in `getForm()`/`prepareBlockData()` — is all a future
 * run needs once each of those PRs lands.
 */
class ControllerDesignHomepage extends Controller {
	private $error = array();

	/** Block types with a real, merged Provider/Renderer/template. */
	const TYPES = array(
		'products' => 'text_type_products'
	);

	const PAGE_CODE = 'home';

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
	 * Persists the list screen's per-row `sort_order` number inputs
	 * (`order[<block_id>]`). Plain form POST + redirect, no AJAX — see this
	 * file's header comment for why (McSortable needs an asset bundle
	 * that's never been enqueued into the admin theme).
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

	protected function getList() {
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
		$data['text_form'] = !isset($this->request->get['block_id']) ? $this->language->get('text_add') : $this->language->get('text_edit');

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
		$data['error_type'] = isset($this->error['type']) ? $this->error['type'] : '';

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

		if (isset($this->request->post['type'])) {
			$data['type'] = $this->request->post['type'];
		} elseif (!empty($block_info)) {
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

		if (isset($this->request->post['settings'])) {
			$settings = $this->request->post['settings'];
		} elseif (!empty($block_info)) {
			$settings = $block_info['settings'];
		} else {
			$settings = array();
		}

		$data['category_id'] = isset($settings['category_id']) ? $settings['category_id'] : '';
		$data['limit'] = isset($settings['limit']) ? $settings['limit'] : 8;
		$data['sort'] = isset($settings['sort']) ? $settings['sort'] : 'p.date_added';
		$data['order'] = isset($settings['order']) ? $settings['order'] : 'DESC';

		$this->load->model('localisation/language');

		$data['languages'] = $this->model_localisation_language->getLanguages();

		if (isset($this->request->post['block_content'])) {
			$data['block_content'] = $this->request->post['block_content'];
		} else {
			$data['block_content'] = $block_descriptions;
		}

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('design/homepage_form', $data));
	}

	/**
	 * @return array{type: string, status: bool, settings: array<string, mixed>, content: array<int, array<string, mixed>>}
	 */
	private function prepareBlockData() {
		$content = array();

		if (isset($this->request->post['block_content']) && is_array($this->request->post['block_content'])) {
			foreach ($this->request->post['block_content'] as $language_id => $value) {
				$content[(int)$language_id] = array(
					'title' => isset($value['title']) ? $value['title'] : ''
				);
			}
		}

		$posted_settings = isset($this->request->post['settings']) ? $this->request->post['settings'] : array();

		return array(
			'type'     => $this->request->post['type'],
			'status'   => !empty($this->request->post['status']),
			'settings' => array(
				'category_id' => !empty($posted_settings['category_id']) ? (int)$posted_settings['category_id'] : null,
				'limit'       => !empty($posted_settings['limit']) ? (int)$posted_settings['limit'] : 8,
				'sort'        => !empty($posted_settings['sort']) ? $posted_settings['sort'] : 'p.date_added',
				'order'       => !empty($posted_settings['order']) ? $posted_settings['order'] : 'DESC'
			),
			'content'  => $content
		);
	}

	protected function validateForm() {
		if (!$this->user->hasPermission('modify', 'design/homepage')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if (!isset($this->request->post['type']) || !array_key_exists($this->request->post['type'], self::TYPES)) {
			$this->error['type'] = $this->language->get('error_type');
		}

		return !$this->error;
	}

	protected function validateDelete() {
		if (!$this->user->hasPermission('modify', 'design/homepage')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
}
