<?php
/**
 * MintCart\Commerce\SearchService wiring (MINTCART_TECHNICAL_PLAN.md §26,
 * MINTCART_IMPLEMENTATION_GUIDE.md §59) — the single normalized AJAX
 * Search endpoint Functional Scope §14 asks for, so a theme never has to
 * patch a controller to add search-as-you-type/header suggestions.
 *
 * Deliberately a new, fully-additive controller under `mintcart/search`
 * rather than a change to `product/search.php`'s full search-results page
 * (breadcrumbs, category tree, sort/limit options, pagination, search
 * history logging) — this endpoint answers a different question (a short
 * quick-suggestions list for a search box) with a much smaller response
 * shape (see SearchService), not a page render. `product/search.php`
 * itself is untouched; a shopper who submits the full search form still
 * lands there exactly as before. Zero upstream diff.
 *
 * Reuses the *same* `ModelCatalogProduct::getProducts()`/
 * `getTotalProducts()` calls `product/search.php` already uses (status/
 * date_available/store filtering baked into the model, never
 * re-implemented here) so both endpoints stay in sync with one source of
 * truth.
 */
class ControllerMintcartSearch extends Controller {
	// route=mintcart/search - GET search, limit
	public function index() {
		$this->load->model('catalog/product');
		$this->load->model('tool/image');

		$search = isset($this->request->get['search']) ? trim((string)$this->request->get['search']) : '';

		$limit = isset($this->request->get['limit']) ? (int)$this->request->get['limit'] : 10;

		if ($limit < 1) {
			$limit = 10;
		}

		// A quick-suggestions endpoint has no legitimate use for a large
		// page size - clamped well below product/search.php's own 100-item
		// ceiling to keep the response fast and the dropdown usable.
		if ($limit > 20) {
			$limit = 20;
		}

		if ($search === '') {
			$this->respond((new \MintCart\Commerce\SearchService())->results([], 0));
			return;
		}

		$filter_data = array(
			'filter_name' => $search,
			'sort'        => 'p.sort_order',
			'order'       => 'ASC',
			'start'       => 0,
			'limit'       => $limit
		);

		$total = (int)$this->model_catalog_product->getTotalProducts(array('filter_name' => $search));
		$results = $this->model_catalog_product->getProducts($filter_data);

		$rows = array();

		foreach ($results as $result) {
			// Same small-thumbnail size the header cart/mini-cart already
			// use (theme_*_image_cart_width/height) - a quick-suggestions
			// row is closer in size to a cart line than a full product-card
			// grid image.
			if ($result['image']) {
				$image = $this->model_tool_image->resize($result['image'], $this->config->get('theme_' . $this->config->get('config_theme') . '_image_cart_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_cart_height'));
			} else {
				$image = $this->model_tool_image->resize('placeholder.png', $this->config->get('theme_' . $this->config->get('config_theme') . '_image_cart_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_cart_height'));
			}

			// Same customer-group price visibility and special-price rules
			// product/search.php's own listing loop already applies -
			// deliberately not reimplemented differently here.
			if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
				$price = $this->currency->format($this->tax->calculate($result['price'], $result['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
			} else {
				$price = false;
			}

			if (!is_null($result['special']) && (float)$result['special'] >= 0) {
				$special = $this->currency->format($this->tax->calculate($result['special'], $result['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
			} else {
				$special = false;
			}

			$rows[] = array(
				'id'      => $result['product_id'],
				'name'    => $result['name'],
				'url'     => $this->url->link('product/product', 'product_id=' . $result['product_id']),
				'image'   => $image,
				'price'   => $price,
				'special' => $special
			);
		}

		$this->respond((new \MintCart\Commerce\SearchService())->results($rows, $total));
	}

	private function respond($json) {
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
