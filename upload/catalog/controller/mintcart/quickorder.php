<?php
/**
 * MintCart\Commerce\QuickOrderService wiring (MINTCART_TECHNICAL_PLAN.md
 * §28, MINTCART_IMPLEMENTATION_GUIDE.md §60) — the Quick Order JS client's
 * `submitQuickOrder()` hits this single action and gets back either a
 * success acknowledgement, a field-error map, or a `redirect` (same
 * "can't be answered from a listing card" fallback `ControllerMintcartCart`
 * already uses for required options/recurring plans).
 *
 * Fully additive — no existing OpenCart file is modified. Recording the
 * request writes to the new, Mint-owned `mc_quick_order` table via
 * `ModelMintcartQuickorder`, never `oc_order` — see the migration's
 * docblock for why a Quick Order request is a lead, not a real order, and
 * `docs/developer/commerce/quick-order-service.md` for the full contract.
 */
class ControllerMintcartQuickorder extends Controller {
	private function respond($json) {
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	// route=mintcart/quickorder/submit - POST product_id, quantity, option[], name, telephone, email
	public function submit() {
		$this->load->language('mintcart/quickorder');
		$this->load->model('catalog/product');

		$product_id = isset($this->request->post['product_id']) ? (int)$this->request->post['product_id'] : 0;
		$product_adapter = new \MintCart\Compatibility\OpenCart3\ProductAdapter($this->model_catalog_product);
		$product_info = $product_adapter->find($product_id);

		if (!$product_info) {
			$this->respond(array('error' => $this->language->get('error_product')));
			return;
		}

		// Same "can't offer an option/recurring-plan picker from a listing
		// card" fallback ControllerMintcartCart::add() already uses - a
		// Quick Order button lives on the same product-card grid, with the
		// same lack of a picker UI.
		$option = isset($this->request->post['option']) ? array_filter($this->request->post['option']) : array();
		$product_options = $this->model_catalog_product->getProductOptions($product_id);

		$cart_service = new \MintCart\Commerce\CartService();
		$missing_options = $cart_service->validateOptions($product_options, $option);

		if ($missing_options) {
			$this->respond(array(
				'error'    => $this->language->get('error_option'),
				'redirect' => str_replace('&amp;', '&', $this->url->link('product/product', 'product_id=' . $product_id))
			));
			return;
		}

		$quick_order_service = new \MintCart\Commerce\QuickOrderService();
		$result = $quick_order_service->validate($this->request->post);

		if (!$result['valid']) {
			$errors = array();

			foreach ($result['errors'] as $field => $key) {
				$errors[$field] = $this->language->get($key);
			}

			$this->respond(array('errors' => $errors));
			return;
		}

		$this->load->model('mintcart/quickorder');

		$this->model_mintcart_quickorder->addRequest(array(
			'product_id'   => $product_id,
			'product_name' => $product_info['name'],
			'quantity'     => $result['quantity'],
			'name'         => $result['name'],
			'telephone'    => $result['telephone'],
			'email'        => $result['email'],
			'store_id'     => $this->config->get('config_store_id'),
			'language_id'  => $this->config->get('config_language_id'),
			'ip'           => $this->request->server['REMOTE_ADDR'] ?? ''
		));

		$this->respond(array(
			'success' => true,
			'message' => $this->language->get('text_success')
		));
	}
}
