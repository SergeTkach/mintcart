<?php
class ControllerExtensionModuleCategory extends Controller {
	public function index() {
		$this->load->language('extension/module/category');

		if (isset($this->request->get['path'])) {
			$parts = explode('_', (string)$this->request->get['path']);
		} else {
			$parts = array();
		}

		if (isset($parts[0])) {
			$data['category_id'] = $parts[0];
		} else {
			$data['category_id'] = 0;
		}

		if (isset($parts[1])) {
			$data['child_id'] = $parts[1];
		} else {
			$data['child_id'] = 0;
		}

		$this->load->model('catalog/category');

		$this->load->model('catalog/product');

		// MintCart\Catalog\CategoryTree: every root category, only the active
		// branch expanded (up to three levels), counts in all names like the
		// stock module. Keys: category_id/name/href/children, children nest.
		$tree = new \MintCart\Catalog\CategoryTree(
			function ($parent_id) {
				return $this->model_catalog_category->getCategories($parent_id);
			},
			function ($path) {
				return $this->url->link('product/category', 'path=' . $path);
			},
			$this->config->get('config_product_count') ? function ($category_id) {
				return $this->model_catalog_product->getTotalProducts(array(
					'filter_category_id'  => $category_id,
					'filter_sub_category' => true
				));
			} : null,
			3
		);

		$data['categories'] = $tree->build(false, true, (int)$data['category_id']);

		$data['grandchild_id'] = isset($parts[2]) ? $parts[2] : 0;

		return $this->load->view('extension/module/category', $data);
	}
}