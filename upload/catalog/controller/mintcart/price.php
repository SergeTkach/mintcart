<?php
/**
 * Live product page price (route=mintcart/price) - the total for the chosen
 * quantity and options, as the cart will charge it.
 *
 * Rules live in MintCart\Commerce\ProductPriceCalculator; this controller
 * only loads the same rows OpenCart's cart loads (system/library/cart/
 * cart.php: product_discount / product_special for the customer group and
 * date window, same ORDER BY) and applies tax + currency per unit, then
 * multiplies - the same order the cart and cart page use, so rounding
 * matches too.
 *
 * Fully additive: no stock controller is touched. Contract:
 * docs/developer/commerce/product-price.md
 */
class ControllerMintcartPrice extends Controller {
	// POST product_id, quantity, option[...] (the product form as-is)
	public function index() {
		// current  = what the cart will charge for this quantity (total)
		// original = the regular total (base + options), only when it is
		//            higher than current - i.e. a special or a quantity
		//            discount tier is saving the customer money
		// tax      = current without tax, when prices are shown with tax
		$json = array('current' => false, 'original' => false, 'tax' => false);

		$product_id = isset($this->request->post['product_id']) ? (int)$this->request->post['product_id'] : 0;
		$quantity = isset($this->request->post['quantity']) ? max(1, (int)$this->request->post['quantity']) : 1;
		$selected = isset($this->request->post['option']) && is_array($this->request->post['option']) ? $this->request->post['option'] : array();

		$this->load->model('catalog/product');

		$product_info = $this->model_catalog_product->getProduct($product_id);

		if ($product_info && ($this->customer->isLogged() || !$this->config->get('config_customer_price'))) {
			$customer_group_id = (int)$this->config->get('config_customer_group_id');
			$active = "customer_group_id = '" . $customer_group_id . "' AND ((date_start = '0000-00-00' OR date_start < NOW()) AND (date_end = '0000-00-00' OR date_end > NOW()))";

			$base = $this->db->query("SELECT price FROM " . DB_PREFIX . "product WHERE product_id = '" . $product_id . "'");
			$discounts = $this->db->query("SELECT quantity, price FROM " . DB_PREFIX . "product_discount WHERE product_id = '" . $product_id . "' AND " . $active . " ORDER BY quantity DESC, priority ASC, price ASC");
			$special = $this->db->query("SELECT price FROM " . DB_PREFIX . "product_special WHERE product_id = '" . $product_id . "' AND " . $active . " ORDER BY priority ASC, price ASC LIMIT 1");

			// The cart picks the discount tier by the product's total quantity
			// across all its cart lines, so what is already there counts.
			$total_quantity = $quantity;

			foreach ($this->cart->getProducts() as $cart_product) {
				if ((int)$cart_product['product_id'] === $product_id) {
					$total_quantity += (int)$cart_product['quantity'];
				}
			}

			$calculator = new \MintCart\Commerce\ProductPriceCalculator();

			$unit = $calculator->unitPrice(
				(float)$base->row['price'],
				$discounts->rows,
				$special->num_rows ? (float)$special->row['price'] : null,
				$calculator->optionPrice($this->model_catalog_product->getProductOptions($product_id), $selected),
				$total_quantity
			);

			$currency = $this->session->data['currency'];
			$tax_class_id = $product_info['tax_class_id'];
			$show_tax = $this->config->get('config_tax');

			$json['current'] = $this->currency->format($this->tax->calculate($unit['charged'], $tax_class_id, $show_tax) * $quantity, $currency);

			if ($unit['regular'] > $unit['charged']) {
				$json['original'] = $this->currency->format($this->tax->calculate($unit['regular'], $tax_class_id, $show_tax) * $quantity, $currency);
			}

			if ($show_tax) {
				$json['tax'] = $this->currency->format($unit['charged'] * $quantity, $currency);
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
