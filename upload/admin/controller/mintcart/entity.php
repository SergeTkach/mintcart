<?php
/**
 * Phase 12 (Entity Picker, `MINTCART_FUNCTIONAL_SCOPE.md` §8,
 * `MINTCART_TECHNICAL_PLAN.md` §20) — real `fetchItems` backends for
 * `McEntityPicker` (Phase 11), one action per entity type. `category()` is
 * the first, `manufacturer()` the second, `product()` the third,
 * `information()` the fourth, `attribute()` the fifth, `option()` the
 * sixth, `customer()` the seventh.
 *
 * Deliberately a new, fully-additive controller under `mintcart/entity/*`
 * rather than a change to `catalog/category.php`'s existing `autocomplete()`
 * action (and, for manufacturers, `catalog/manufacturer.php`'s own
 * `autocomplete()`): those actions already have other admin-template
 * consumers (`product_form.twig`, `category_form.twig`, `coupon_form.twig`,
 * and two extension templates for category; `product_form.twig` for
 * manufacturer) returning a plain `[{*_id, name}]` array with no
 * pagination — reshaping either to McEntityPicker's `{items, total}`
 * contract would be a breaking change to their existing consumers
 * (`CLAUDE.md` §10). Every action here instead wraps the *same* underlying
 * model call (`ModelCatalogCategory::getCategories()` /
 * `ModelCatalogManufacturer::getManufacturers()` /
 * `ModelCatalogInformation::getInformations()`), never queries the
 * database directly, so every pair of endpoints stays in sync with the
 * same source of truth.
 *
 * Like every other MintCart admin route, this one is gated by OpenCart's
 * own route-based ACL (`admin/controller/startup/permission.php`) under the
 * route name `mintcart/entity` — a fresh install's user groups do not have
 * this listed yet (new admin controllers never do; OpenCart discovers them
 * by scanning `admin/controller/**` and lists them in Users > User Groups >
 * Permissions for an admin to grant), so `mintcart/entity` access must be
 * granted to a user group before any of these actions can be reached. See
 * `docs/developer/components/entity-picker.md` "Admin backends".
 */
class ControllerMintcartEntity extends Controller {
	private function respond($json) {
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	// route=mintcart/entity/category - GET filter_name, page, limit
	public function category() {
		if (!$this->user->hasPermission('access', 'mintcart/entity')) {
			$this->respond(array('error' => 'Permission denied'));
			return;
		}

		$this->load->model('catalog/category');

		$filter_name = isset($this->request->get['filter_name']) ? (string)$this->request->get['filter_name'] : '';
		$page = isset($this->request->get['page']) ? (int)$this->request->get['page'] : 1;
		$limit = isset($this->request->get['limit']) ? (int)$this->request->get['limit'] : 20;

		$rows = $this->model_catalog_category->getCategories(array(
			'filter_name' => $filter_name,
			'sort'        => 'name',
			'order'       => 'ASC'
		));

		$provider = new \MintCart\Design\CategoryPickerProvider();

		$this->respond($provider->build($rows, $page, $limit));
	}

	// route=mintcart/entity/manufacturer - GET filter_name, page, limit
	public function manufacturer() {
		if (!$this->user->hasPermission('access', 'mintcart/entity')) {
			$this->respond(array('error' => 'Permission denied'));
			return;
		}

		$this->load->model('catalog/manufacturer');
		$this->load->model('tool/image');

		$filter_name = isset($this->request->get['filter_name']) ? (string)$this->request->get['filter_name'] : '';
		$page = isset($this->request->get['page']) ? (int)$this->request->get['page'] : 1;
		$limit = isset($this->request->get['limit']) ? (int)$this->request->get['limit'] : 20;

		$rows = $this->model_catalog_manufacturer->getManufacturers(array(
			'filter_name' => $filter_name,
			'sort'        => 'name',
			'order'       => 'ASC'
		));

		foreach ($rows as &$row) {
			$row['image_url'] = (!empty($row['image']) && is_file(DIR_IMAGE . $row['image']))
				? $this->model_tool_image->resize($row['image'], 100, 100)
				: null;
		}
		unset($row);

		$provider = new \MintCart\Design\ManufacturerPickerProvider();

		$this->respond($provider->build($rows, $page, $limit));
	}

	// route=mintcart/entity/manufacturerCreate - POST name
	//
	// Phase 12 (Entity Picker) "Create-new flow via drawer/modal"
	// (`MINTCART_FUNCTIONAL_SCOPE.md` §8) - the first entity type to get
	// it, picked because `ModelCatalogManufacturer::addManufacturer()` has
	// the smallest required-field set of any entity type here: only
	// `name` (`sort_order`/`image`/`manufacturer_store`/
	// `manufacturer_seo_url` are all `isset()`-guarded optional in the
	// model). Deliberately omits image/SEO-URL - a human can fill those in
	// later from the manufacturer's own edit screen; the quick-create's
	// job is only to unblock product creation without leaving the page,
	// not to replace the full form.
	//
	// Checks `catalog/manufacturer` `modify` (not a new `mintcart/entity`
	// "modify" permission) in addition to `mintcart/entity` `access` -
	// this creates a real catalog row, so it must stay behind the exact
	// same ACL `ControllerCatalogManufacturer::add()`'s own
	// `validateForm()` already requires, independent of who may merely
	// browse manufacturers for picking (`mintcart/entity` access).
	//
	// `manufacturer_store = array(0)` matches
	// `ControllerCatalogManufacturer::getForm()`'s own default for a
	// brand-new manufacturer (default store pre-checked) - see that
	// controller's `$data['manufacturer_store'] = array(0);` fallback.
	public function manufacturerCreate() {
		if (!$this->user->hasPermission('access', 'mintcart/entity') || !$this->user->hasPermission('modify', 'catalog/manufacturer')) {
			$this->respond(array('error' => 'Permission denied'));
			return;
		}

		$name = isset($this->request->post['name']) ? trim((string)$this->request->post['name']) : '';

		// Same 1-64 char rule as ControllerCatalogManufacturer::validateForm()
		// (CLAUDE.md §84 - mirrored, not guessed from memory).
		if (utf8_strlen($name) < 1 || utf8_strlen($name) > 64) {
			$this->respond(array('error' => 'Invalid name'));
			return;
		}

		$this->load->model('catalog/manufacturer');

		$manufacturer_id = $this->model_catalog_manufacturer->addManufacturer(array(
			'name'               => $name,
			'sort_order'         => 0,
			'manufacturer_store' => array(0)
		));

		$this->respond(array('item' => array(
			'id'    => (int)$manufacturer_id,
			'name'  => $name,
			'image' => null
		)));
	}

	// route=mintcart/entity/product - GET filter_name, filter_status, page, limit
	public function product() {
		if (!$this->user->hasPermission('access', 'mintcart/entity')) {
			$this->respond(array('error' => 'Permission denied'));
			return;
		}

		$this->load->model('catalog/product');
		$this->load->model('tool/image');

		$filter_name = isset($this->request->get['filter_name']) ? (string)$this->request->get['filter_name'] : '';
		$page = isset($this->request->get['page']) ? (int)$this->request->get['page'] : 1;
		$limit = isset($this->request->get['limit']) ? (int)$this->request->get['limit'] : 20;

		if ($page < 1) {
			$page = 1;
		}

		if ($limit < 1) {
			$limit = 20;
		}

		// Entity Picker Filters (docs/developer/components/entity-picker.md
		// "Filters", MINTCART_FUNCTIONAL_SCOPE.md §8) - optional, beyond
		// filter_name. '' (not present, or present-but-blank) means "every
		// status", matching ModelCatalogProduct::getProducts()/
		// getTotalProducts()'s own !empty()/isset()-and-not-''-guarded
		// filter_status handling - this endpoint passes the raw value
		// straight through rather than re-validating it, same trust level
		// `filter_name` already gets.
		$filter_data = array(
			'filter_name' => $filter_name,
			'sort'        => 'pd.name',
			'order'       => 'ASC',
			'start'       => ($page - 1) * $limit,
			'limit'       => $limit
		);

		if (isset($this->request->get['filter_status']) && $this->request->get['filter_status'] !== '') {
			$filter_data['filter_status'] = $this->request->get['filter_status'];
		}

		$rows = $this->model_catalog_product->getProducts($filter_data);

		$total_filter_data = array('filter_name' => $filter_name);

		if (isset($filter_data['filter_status'])) {
			$total_filter_data['filter_status'] = $filter_data['filter_status'];
		}

		$total = (int)$this->model_catalog_product->getTotalProducts($total_filter_data);

		foreach ($rows as &$row) {
			$row['image_url'] = (!empty($row['image']) && is_file(DIR_IMAGE . $row['image']))
				? $this->model_tool_image->resize($row['image'], 100, 100)
				: null;
		}
		unset($row);

		$provider = new \MintCart\Design\ProductPickerProvider();

		$this->respond($provider->build($rows, $total));
	}

	// route=mintcart/entity/information - GET filter_name, page, limit
	public function information() {
		if (!$this->user->hasPermission('access', 'mintcart/entity')) {
			$this->respond(array('error' => 'Permission denied'));
			return;
		}

		$this->load->model('catalog/information');

		$filter_name = isset($this->request->get['filter_name']) ? (string)$this->request->get['filter_name'] : '';
		$page = isset($this->request->get['page']) ? (int)$this->request->get['page'] : 1;
		$limit = isset($this->request->get['limit']) ? (int)$this->request->get['limit'] : 20;

		// getInformations() called with no args intentionally - it has no
		// filter_name support (see InformationPickerProvider's own docblock),
		// so this fetches every information page (via the model's own cache)
		// and lets the provider filter/paginate in PHP.
		$rows = $this->model_catalog_information->getInformations();

		$provider = new \MintCart\Design\InformationPickerProvider();

		$this->respond($provider->build($rows, $filter_name, $page, $limit));
	}

	// route=mintcart/entity/attribute - GET filter_name, page, limit
	public function attribute() {
		if (!$this->user->hasPermission('access', 'mintcart/entity')) {
			$this->respond(array('error' => 'Permission denied'));
			return;
		}

		$this->load->model('catalog/attribute');

		$filter_name = isset($this->request->get['filter_name']) ? (string)$this->request->get['filter_name'] : '';
		$page = isset($this->request->get['page']) ? (int)$this->request->get['page'] : 1;
		$limit = isset($this->request->get['limit']) ? (int)$this->request->get['limit'] : 20;

		$rows = $this->model_catalog_attribute->getAttributes(array(
			'filter_name' => $filter_name,
			'sort'        => 'ad.name',
			'order'       => 'ASC'
		));

		$provider = new \MintCart\Design\AttributePickerProvider();

		$this->respond($provider->build($rows, $page, $limit));
	}

	// route=mintcart/entity/option - GET filter_name, page, limit
	public function option() {
		if (!$this->user->hasPermission('access', 'mintcart/entity')) {
			$this->respond(array('error' => 'Permission denied'));
			return;
		}

		$this->load->model('catalog/option');

		$filter_name = isset($this->request->get['filter_name']) ? (string)$this->request->get['filter_name'] : '';
		$page = isset($this->request->get['page']) ? (int)$this->request->get['page'] : 1;
		$limit = isset($this->request->get['limit']) ? (int)$this->request->get['limit'] : 20;

		$rows = $this->model_catalog_option->getOptions(array(
			'filter_name' => $filter_name,
			'sort'        => 'od.name',
			'order'       => 'ASC'
		));

		$provider = new \MintCart\Design\OptionPickerProvider();

		$result = $provider->build($rows, $page, $limit);

		// OptionPickerProvider is deliberately OpenCart-independent, so it
		// can't call ModelCatalogOption::getOptionValues() itself - that
		// per-row DB call happens here instead, only for the types
		// product_form.twig's Option tab actually needs a value list for
		// (select/radio/checkbox/image; text/textarea/file/date/time/
		// datetime options have no oc_option_value rows at all). Same
		// value-type filter catalog/option.php's own autocomplete() action
		// already uses. Only `option_value_id`/`name` are kept - the only
		// two fields the tab-building JS actually reads (unlike the legacy
		// autocomplete's response, this list has no per-value image
		// thumbnail to build, since nothing consumes one).
		$value_types = array('select', 'radio', 'checkbox', 'image');

		foreach ($result['items'] as &$item) {
			$item['option_value'] = array();

			if (in_array($item['type'], $value_types, true)) {
				foreach ($this->model_catalog_option->getOptionValues($item['id']) as $option_value) {
					$item['option_value'][] = array(
						'option_value_id' => $option_value['option_value_id'],
						'name'            => strip_tags(html_entity_decode((string)$option_value['name'], ENT_QUOTES, 'UTF-8'))
					);
				}
			}
		}
		unset($item);

		$this->respond($result);
	}

	// route=mintcart/entity/customer - GET filter_name, page, limit
	public function customer() {
		if (!$this->user->hasPermission('access', 'mintcart/entity')) {
			$this->respond(array('error' => 'Permission denied'));
			return;
		}

		$this->load->model('customer/customer');

		$filter_name = isset($this->request->get['filter_name']) ? (string)$this->request->get['filter_name'] : '';
		$page = isset($this->request->get['page']) ? (int)$this->request->get['page'] : 1;
		$limit = isset($this->request->get['limit']) ? (int)$this->request->get['limit'] : 20;

		$page = ($page < 1) ? 1 : $page;
		$limit = ($limit < 1) ? 20 : $limit;

		// Unlike category/manufacturer, ModelCustomerCustomer::getTotalCustomers()
		// accepts filter_name and returns a real filtered count, so paging
		// happens at the SQL level (start/limit) rather than in PHP - see
		// CustomerPickerProvider's own doc comment for why.
		$rows = $this->model_customer_customer->getCustomers(array(
			'filter_name' => $filter_name,
			'sort'        => 'name',
			'order'       => 'ASC',
			'start'       => ($page - 1) * $limit,
			'limit'       => $limit
		));

		$total = $this->model_customer_customer->getTotalCustomers(array(
			'filter_name' => $filter_name
		));

		$provider = new \MintCart\Design\CustomerPickerProvider();

		$this->respond($provider->build($rows, (int)$total));
	}
}
