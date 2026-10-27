<?php
/**
 * Persistence half of `mc-product-card`'s `[data-mc-action="toggle-compare"]`
 * button. Writes the same `$this->session->data['compare']` list stock
 * `ControllerProductCompare` uses (max 4, oldest dropped), so the stock
 * `product/compare` page stays in sync. Mirrors `mintcart/wishlist`'s JSON
 * shape: { count, product_ids, active }.
 */
class ControllerMintcartCompare extends Controller {
	private function ids() {
		return isset($this->session->data['compare']) ? array_values(array_unique(array_map('intval', $this->session->data['compare']))) : array();
	}

	private function respond($json) {
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	private function snapshot($product_id = null, $added = null) {
		$ids = $this->ids();

		$this->load->language('mintcart/wishlist');

		return array(
			'message'     => $added === null ? '' : $this->language->get($added ? 'text_compare_added' : 'text_compare_removed'),
			'view_text'   => $this->language->get('text_compare_view'),
			'view_url'    => $this->url->link('product/compare', '', true),
			'count'       => count($ids),
			'product_ids' => $ids,
			'active'      => $product_id === null ? null : in_array((int)$product_id, $ids, true)
		);
	}

	// route=mintcart/compare/add - POST product_id
	public function add() {
		$this->load->language('mintcart/wishlist');
		$this->load->model('catalog/product');

		$product_id = isset($this->request->post['product_id']) ? (int)$this->request->post['product_id'] : 0;

		if (!$this->model_catalog_product->getProduct($product_id)) {
			$this->respond(array('error' => $this->language->get('error_product')));
			return;
		}

		$ids = $this->ids();

		if (!in_array($product_id, $ids, true)) {
			if (count($ids) >= 4) {
				array_shift($ids);
			}

			$ids[] = $product_id;
		}

		$this->session->data['compare'] = $ids;

		$this->respond($this->snapshot($product_id, true));
	}

	// route=mintcart/compare/remove - POST product_id
	public function remove() {
		$product_id = isset($this->request->post['product_id']) ? (int)$this->request->post['product_id'] : 0;

		$this->session->data['compare'] = array_values(array_diff($this->ids(), array($product_id)));

		$this->respond($this->snapshot($product_id, false));
	}

	// route=mintcart/compare/refresh - GET, no mutation
	public function refresh() {
		$this->respond($this->snapshot());
	}
}
