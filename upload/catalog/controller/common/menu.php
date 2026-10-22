<?php
class ControllerCommonMenu extends Controller {
	public function index() {
		$this->load->language('common/menu');

		// Menu
		$this->load->model('catalog/category');

		$this->load->model('catalog/product');

		// MintCart\Catalog\CategoryTree: one place that walks the tree (three
		// levels). Same keys the stock loop produced (name/href/column/children),
		// plus category_id and a third level in children[i]['children'].
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

		$data['categories'] = $tree->build(true);

		return $this->load->view('common/menu', $data);
	}
}
