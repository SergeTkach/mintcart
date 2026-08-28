<?php
/**
 * Phase 12 (Entity Picker, `MINTCART_FUNCTIONAL_SCOPE.md` §8,
 * `MINTCART_TECHNICAL_PLAN.md` §20) — real `fetchItems` backends for
 * `McEntityPicker` (Phase 11), one action per entity type. `category()` is
 * the first.
 *
 * Deliberately a new, fully-additive controller under `mintcart/entity/*`
 * rather than a change to `catalog/category.php`'s existing `autocomplete()`
 * action: that action already has five other admin-template consumers
 * (`product_form.twig`, `category_form.twig`, `coupon_form.twig`, and two
 * extension templates) returning a plain `[{category_id, name}]` array with
 * no pagination — reshaping it to McEntityPicker's `{items, total}`
 * contract would be a breaking change to all five (`CLAUDE.md` §10). Every
 * action here instead wraps the *same* underlying model call
 * (`ModelCatalogCategory::getCategories()`), never queries the database
 * directly, so both endpoints stay in sync with the same source of truth.
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
}
