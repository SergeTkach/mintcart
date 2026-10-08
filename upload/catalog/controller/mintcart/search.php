<?php
/**
 * MintCart\Commerce\SearchService wiring (MINTCART_TECHNICAL_PLAN.md §26,
 * MINTCART_IMPLEMENTATION_GUIDE.md §59, MINTCART_FUNCTIONAL_SCOPE.md §14)
 * — a single normalized AJAX search endpoint a theme's search box/typeahead
 * can call without ever patching `product/search.php` for its own markup.
 *
 * Deliberately a new, fully-additive controller rather than a modification
 * of `product/search.php` — same rationale `ControllerMintcartCart` already
 * established (docs/developer/commerce/cart-service.md): the full search
 * *results page* stays `product/search.php`'s job (it already has its own
 * `product_cards` pipeline via `ProductCardListRenderer`, see
 * `docs/developer/theme-api/product-card-list-renderer.md`); this endpoint
 * only serves a lightweight typeahead/suggest use case, reusing the exact
 * same `model_catalog_product` filter shape `product/search.php` uses so
 * the two never drift apart on what counts as a match.
 */
class ControllerMintcartSearch extends Controller {
	// How many results a suggest/typeahead dropdown needs — deliberately
	// small and not caller-configurable yet (no real consumer asking for a
	// different limit; CLAUDE.md §85).
	const SUGGEST_LIMIT = 8;

	// route=mintcart/search/suggest - GET search
	public function suggest() {
		$this->load->model('catalog/product');
		$this->load->model('tool/image');

		$search_service = new \MintCart\Commerce\SearchService();

		$raw = isset($this->request->get['search']) ? $this->request->get['search'] : '';
		$query = $search_service->normalizeQuery($raw);

		if (!$search_service->isQueryValid($query)) {
			$this->respond($search_service->results([], $query, 0));
			return;
		}

		$filter_data = array(
			'filter_name' => $query,
			'sort'        => 'p.sort_order',
			'order'       => 'ASC',
			'start'       => 0,
			'limit'       => self::SUGGEST_LIMIT
		);

		$total = (int)$this->model_catalog_product->getTotalProducts($filter_data);
		$results = $this->model_catalog_product->getProducts($filter_data);

		$items = array();

		foreach ($results as $result) {
			if ($result['image']) {
				$image = $this->model_tool_image->resize($result['image'], $this->config->get('theme_' . $this->config->get('config_theme') . '_image_cart_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_cart_height'));
			} else {
				$image = $this->model_tool_image->resize('placeholder.png', $this->config->get('theme_' . $this->config->get('config_theme') . '_image_cart_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_cart_height'));
			}

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

			$items[] = array(
				'id'      => $result['product_id'],
				'name'    => html_entity_decode($result['name'], ENT_QUOTES, 'UTF-8'),
				'url'     => str_replace('&amp;', '&', $this->url->link('product/product', 'product_id=' . $result['product_id'])),
				'image'   => $image,
				'price'   => $price,
				'special' => $special
			);
		}

		$this->respond($search_service->results($items, $query, $total));
	}

	private function respond($json) {
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
