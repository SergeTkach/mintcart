<?php
/**
 * MintCart\Commerce\WishlistService wiring (MINTCART_FUNCTIONAL_SCOPE.md §11,
 * MINTCART_TECHNICAL_PLAN.md §24, MINTCART_IMPLEMENTATION_GUIDE.md §47) —
 * the persistence half of `mc-product-card`'s `[data-mc-action="toggle-
 * wishlist"]` button, which until now only flipped its own visual state
 * (`docs/developer/components/product-card.md` "Why wishlist/compare toggle
 * but add-to-cart does something real now").
 *
 * Deliberately a new, fully-additive controller rather than a modification
 * of `account/wishlist.php` — see docs/developer/commerce/wishlist-service.md
 * for the full rationale. Every action here delegates the actual membership
 * storage to the exact same two places stock `ControllerAccountWishList::add()`
 * already uses (`ModelAccountWishlist` for a logged-in customer,
 * `$this->session->data['wishlist']` for a guest), so the stock
 * `account/wishlist` page and header wishlist count stay in sync
 * automatically — this controller never talks to the `oc_customer_wishlist`
 * table directly.
 */
class ControllerMintcartWishlist extends Controller {
	private function currentIds() {
		if ($this->customer->isLogged()) {
			$this->load->model('account/wishlist');

			$ids = array();

			foreach ($this->model_account_wishlist->getWishlist() as $row) {
				$ids[] = $row['product_id'];
			}

			return $ids;
		}

		return isset($this->session->data['wishlist']) ? $this->session->data['wishlist'] : array();
	}

	private function respond($json) {
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	private function toggle($product_id, $add) {
		$wishlist_service = new \MintCart\Commerce\WishlistService();

		if ($this->customer->isLogged()) {
			$this->load->model('account/wishlist');

			if ($add) {
				$this->model_account_wishlist->addWishlist($product_id);
			} else {
				$this->model_account_wishlist->deleteWishlist($product_id);
			}
		} else {
			$this->session->data['wishlist'] = $wishlist_service->withProduct($this->currentIds(), $product_id, $add);
		}

		$this->load->language('mintcart/wishlist');

		$json = $wishlist_service->snapshot($this->currentIds(), $product_id);
		$json['message'] = $this->language->get($add ? 'text_wishlist_added' : 'text_wishlist_removed');
		$json['view_text'] = $this->language->get('text_wishlist_view');
		$json['view_url'] = $this->url->link('account/wishlist', '', true);

		$this->respond($json);
	}

	// route=mintcart/wishlist/add - POST product_id
	public function add() {
		$this->load->language('mintcart/wishlist');
		$this->load->model('catalog/product');

		$product_id = isset($this->request->post['product_id']) ? (int)$this->request->post['product_id'] : 0;
		$product_info = $this->model_catalog_product->getProduct($product_id);

		if (!$product_info) {
			$this->respond(array('error' => $this->language->get('error_product')));
			return;
		}

		$this->toggle($product_id, true);
	}

	// route=mintcart/wishlist/remove - POST product_id
	public function remove() {
		$this->load->language('mintcart/wishlist');

		$product_id = isset($this->request->post['product_id']) ? (int)$this->request->post['product_id'] : 0;

		$this->toggle($product_id, false);
	}

	// route=mintcart/wishlist/refresh - GET, no mutation
	public function refresh() {
		$wishlist_service = new \MintCart\Commerce\WishlistService();

		$this->respond($wishlist_service->snapshot($this->currentIds()));
	}
}
