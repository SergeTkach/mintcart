<?php
/**
 * MintCart viewed-products wiring (MINTCART_FUNCTIONAL_SCOPE.md §11 "viewed
 * products", Commerce Essentials, priority P0) — a single normalized
 * endpoint the `mc-viewed-products` storefront widget calls with the list of
 * product IDs it already tracked client-side (localStorage), so the widget
 * always renders live name/price/image/availability instead of a stale
 * snapshot captured at view time.
 *
 * Deliberately a new, fully-additive controller rather than a modification
 * of `product/product.php` — same rationale already established by
 * `ControllerMintcartSearch` (docs/developer/commerce/search-service.md).
 * Reuses `model_catalog_product->getProduct()`, which already enforces the
 * same status/date_available/store visibility rule every product page
 * applies, so a since-disabled or deleted product silently drops out of the
 * response instead of needing its own filtering logic here.
 */
class ControllerMintcartViewed extends Controller {
	// A "recently viewed" strip has no real use for more than a handful of
	// items — caps both the client-side history (viewed-products.js) and how
	// many getProduct() lookups a single request can trigger, so a
	// manipulated query string can't force an unbounded number of them.
	const MAX_IDS = 12;

	// route=mintcart/viewed/products - GET product_id[]=1&product_id[]=2...
	public function products() {
		$this->load->model('catalog/product');
		$this->load->model('tool/image');

		$product_ids = array();

		if (isset($this->request->get['product_id']) && is_array($this->request->get['product_id'])) {
			foreach ($this->request->get['product_id'] as $product_id) {
				if ((int)$product_id > 0) {
					$product_ids[] = (int)$product_id;
				}

				if (count($product_ids) >= self::MAX_IDS) {
					break;
				}
			}
		}

		$items = array();

		foreach ($product_ids as $product_id) {
			$result = $this->model_catalog_product->getProduct($product_id);

			if (!$result) {
				continue;
			}

			// The strip shows full product cards, so it uses the card picture size
			// (and a 2x picture for sharp screens), not the 47 px cart thumbnail.
			$width = (int)$this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_width');
			$height = (int)$this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_height');
			$source = $result['image'] ? $result['image'] : 'placeholder.png';

			$image = $this->model_tool_image->resize($source, $width, $height);
			$image_2x = $this->model_tool_image->resize($source, $width * 2, $height * 2);

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
				'id'      => (int)$result['product_id'],
				'name'    => html_entity_decode($result['name'], ENT_QUOTES, 'UTF-8'),
				'url'     => str_replace('&amp;', '&', $this->url->link('product/product', 'product_id=' . $result['product_id'])),
				'image'   => $image,
				'srcset'  => $image . ' 1x, ' . $image_2x . ' 2x',
				'width'   => $width,
				'height'  => $height,
				'price'   => $price,
				'special' => $special
			);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode(array('items' => $items)));
	}
}
