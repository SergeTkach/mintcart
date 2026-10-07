<?php
/**
 * MintCart\Commerce\CartService wiring (MINTCART_TECHNICAL_PLAN.md §24-25,
 * MINTCART_IMPLEMENTATION_GUIDE.md §57-58) — the Cart JS client's
 * `add()`/`remove()`/`update()`/`refresh()` methods each hit one of these
 * four actions and expect a normalized `CartService::snapshot()` shape
 * back, not the plain "total" string `checkout/cart.php`'s own AJAX
 * actions return.
 *
 * Deliberately a new, fully-additive controller rather than a modification
 * of `checkout/cart.php` — see docs/developer/commerce/cart-service.md for
 * the full rationale. Every action here delegates the actual cart mutation
 * to the existing `Cart\Cart` model (`$this->cart`), the same one
 * `checkout/cart.php` and the legacy header cart widget
 * (`common/cart.php`) already use, so all three stay in sync automatically
 * — this controller never talks to the `oc_cart` table directly.
 */
class ControllerMintcartCart extends Controller {
	private function buildSnapshot() {
		$this->load->model('tool/image');

		$cart_service = new \MintCart\Commerce\CartService();

		$items = array();

		foreach ($this->cart->getProducts() as $product) {
			if ($product['image']) {
				$thumb = $this->model_tool_image->resize($product['image'], $this->config->get('theme_' . $this->config->get('config_theme') . '_image_cart_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_cart_height'));
			} else {
				$thumb = $this->model_tool_image->resize('placeholder.png', $this->config->get('theme_' . $this->config->get('config_theme') . '_image_cart_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_cart_height'));
			}

			$unit_price = $this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax'));

			$option_summary = array();

			foreach ($product['option'] as $option) {
				$option_summary[] = array(
					'name'  => $option['name'],
					'value' => $option['value']
				);
			}

			$items[] = array(
				'cart_id'        => $product['cart_id'],
				'product_id'     => $product['product_id'],
				'name'           => $product['name'],
				'url'            => str_replace('&amp;', '&', $this->url->link('product/product', 'product_id=' . $product['product_id'])),
				'thumb'          => $thumb,
				'quantity'       => (int)$product['quantity'],
				'price'          => $this->currency->format($unit_price, $this->session->data['currency']),
				'total'          => $this->currency->format($unit_price * $product['quantity'], $this->session->data['currency']),
				'option_summary' => $option_summary ? $option_summary : null
			);
		}

		$totals = array(
			'subtotal' => $this->currency->format($this->cart->getSubTotal(), $this->session->data['currency']),
			'total'    => $this->currency->format($this->cart->getTotal(), $this->session->data['currency'])
		);

		return $cart_service->snapshot($items, $totals);
	}

	private function respond($json) {
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	// route=mintcart/cart/add - POST product_id, quantity, option[]
	public function add() {
		$this->load->language('mintcart/cart');
		$this->load->model('catalog/product');

		$product_id = isset($this->request->post['product_id']) ? (int)$this->request->post['product_id'] : 0;
		$product_adapter = new \MintCart\Compatibility\OpenCart3\ProductAdapter($this->model_catalog_product);
		$product_info = $product_adapter->find($product_id);

		if (!$product_info) {
			$this->respond(array('error' => $this->language->get('error_product')));
			return;
		}

		$quantity = isset($this->request->post['quantity']) ? (int)$this->request->post['quantity'] : 1;
		$option = isset($this->request->post['option']) ? array_filter($this->request->post['option']) : array();

		$product_options = $this->model_catalog_product->getProductOptions($product_id);

		$cart_service = new \MintCart\Commerce\CartService();
		$missing_options = $cart_service->validateOptions($product_options, $option);

		// Recurring plans aren't "options" in CartService's sense (they're a
		// separate product_recurring/recurring table, not product_option),
		// but a required-and-unselected recurring plan needs the exact same
		// "can't be answered from a listing-page Add to Cart button, send
		// the shopper to the full product page instead" fallback.
		$recurrings = $this->model_catalog_product->getProfiles($product_id);
		$recurring_id = isset($this->request->post['recurring_id']) ? $this->request->post['recurring_id'] : 0;
		$recurring_missing = false;

		if ($recurrings) {
			$recurring_ids = array();

			foreach ($recurrings as $recurring) {
				$recurring_ids[] = $recurring['recurring_id'];
			}

			$recurring_missing = !in_array($recurring_id, $recurring_ids);
		}

		if ($missing_options || $recurring_missing) {
			$this->respond(array(
				'error'    => $this->language->get('error_option'),
				'redirect' => str_replace('&amp;', '&', $this->url->link('product/product', 'product_id=' . $product_id))
			));
			return;
		}

		$this->cart->add($product_id, $quantity, $option, $recurring_id);

		// Same checkout-invalidation side effect checkout/cart.php's own
		// add() applies - a changed cart can change shipping/payment
		// eligibility, so those session choices can't be trusted anymore.
		unset($this->session->data['shipping_method']);
		unset($this->session->data['shipping_methods']);
		unset($this->session->data['payment_method']);
		unset($this->session->data['payment_methods']);

		$this->respond($this->buildSnapshot());
	}

	// route=mintcart/cart/update - POST key (cart_id), quantity
	public function update() {
		$key = isset($this->request->post['key']) ? (int)$this->request->post['key'] : 0;
		$quantity = isset($this->request->post['quantity']) ? (int)$this->request->post['quantity'] : 1;

		if ($key) {
			$this->cart->update($key, $quantity);
		}

		$this->respond($this->buildSnapshot());
	}

	// route=mintcart/cart/remove - POST key (cart_id)
	public function remove() {
		$key = isset($this->request->post['key']) ? (int)$this->request->post['key'] : 0;

		if ($key) {
			$this->cart->remove($key);
		}

		$this->respond($this->buildSnapshot());
	}

	// route=mintcart/cart/refresh - GET, no mutation
	public function refresh() {
		$this->respond($this->buildSnapshot());
	}
}
