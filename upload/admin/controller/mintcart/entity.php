<?php
/**
 * Phase 12 (Entity Picker, `MINTCART_FUNCTIONAL_SCOPE.md` §8,
 * `MINTCART_TECHNICAL_PLAN.md` §20) — real `fetchItems` backends for
 * `McEntityPicker` (Phase 11), one action per entity type. `category()` is
 * the first, `manufacturer()` the second, `product()` the third,
 * `information()` the fourth.
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

	// route=mintcart/entity/product - GET filter_name, page, limit
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

		$filter_data = array(
			'filter_name' => $filter_name,
			'sort'        => 'pd.name',
			'order'       => 'ASC',
			'start'       => ($page - 1) * $limit,
			'limit'       => $limit
		);

		$rows = $this->model_catalog_product->getProducts($filter_data);
		$total = (int)$this->model_catalog_product->getTotalProducts(array('filter_name' => $filter_name));

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
}
