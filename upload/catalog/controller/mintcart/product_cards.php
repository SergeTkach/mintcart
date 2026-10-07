<?php
/**
 * One place that turns `model_catalog_product` result rows into rendered
 * `product-card` components (MINTCART_TECHNICAL_PLAN.md §15C/§15D,
 * CLAUDE.md §8A). Every product list on a Mint-aware theme - category,
 * search, special, manufacturer, related, homepage block, the
 * featured/bestseller/special/latest modules - calls this instead of
 * repeating the image/price/special/tax/rating preparation.
 *
 * Not a page route: called as
 *
 *     $cards = $this->load->controller('mintcart/product_cards', array(
 *         'results'      => $results,          // getProducts()/getProduct() rows
 *         'listing_type' => 'category',
 *         'url'          => '&path=20',        // optional, appended to product links
 *         'image_width'  => 200,               // optional, theme setting by default
 *         'image_height' => 200,
 *         'diagnostics'  => $mint_diagnostics  // optional MintCart\Core\Diagnostics
 *     ));
 *
 * Returns the list of card HTML strings, or null when the active theme is
 * not Mint-aware (no theme.json) so the caller keeps its legacy
 * `products` array. A card that fails to render is logged and skipped; after
 * a failure the renderer disables itself and the rest are skipped too.
 *
 * Contract: docs/developer/commerce/product-cards.md
 */
class ControllerMintcartProductCards extends Controller {
	public function index($args = array()) {
		if (!is_array($args) || empty($args['results'])) {
			return null;
		}

		$theme = \MintCart\Design\ActiveTheme::directory($this->config->get('config_theme'), $this->config->get('theme_default_directory'));

		if (!\MintCart\Design\ActiveTheme::isMintAware(DIR_TEMPLATE, $theme)) {
			return null;
		}

		$this->load->language('mintcart/product_card');
		$this->load->model('tool/image');

		$listing_type = isset($args['listing_type']) ? (string)$args['listing_type'] : 'listing';
		$url = isset($args['url']) ? (string)$args['url'] : '';
		$theme_setting = 'theme_' . $this->config->get('config_theme');
		$width = !empty($args['image_width']) ? (int)$args['image_width'] : (int)$this->config->get($theme_setting . '_image_product_width');
		$height = !empty($args['image_height']) ? (int)$args['image_height'] : (int)$this->config->get($theme_setting . '_image_product_height');
		$diagnostics = isset($args['diagnostics']) ? $args['diagnostics'] : null;

		$renderer = new \MintCart\Design\ProductCardListRenderer(
			new \MintCart\Design\ComponentRenderer(DIR_TEMPLATE, $theme, 'components', $diagnostics)
		);

		$strings = array(
			'text_badge'             => array(
				'sale'         => $this->language->get('text_badge_sale'),
				'out-of-stock' => $this->language->get('text_badge_out_of_stock'),
				'new'          => $this->language->get('text_badge_new')
			),
			'text_wishlist'          => $this->language->get('button_wishlist'),
			'text_compare'           => $this->language->get('button_compare'),
			'text_rated'             => $this->language->get('text_rated'),
			'text_tax'               => $this->language->get('text_tax'),
			'text_add_to_cart'       => $this->language->get('button_cart'),
			'text_quick_order'       => $this->language->get('text_quick_order'),
			'text_added_to_cart'     => $this->language->get('text_added_to_cart'),
			'text_add_to_cart_error' => $this->language->get('text_add_to_cart_error'),
			'text_wishlist_error'    => $this->language->get('text_wishlist_error')
		);

		// OpenCart's own backorder flag - without it ProductViewProvider
		// disables add-to-cart for out-of-stock products even on stores
		// that allow backorders.
		$allow_out_of_stock_purchase = (bool)$this->config->get('config_stock_checkout');

		$cards = array();

		foreach (array_values($args['results']) as $position => $result) {
			$card = $renderer->render(
				$this->product($result, $url, $width, $height, $allow_out_of_stock_purchase),
				array(),
				array(
					'listing_type' => $listing_type,
					'position'     => $position + 1
				),
				$strings,
				function (\Throwable $e, array $failed_product) use ($listing_type) {
					(new \MintCart\Core\Logger())->error('ComponentRenderer failed to render product-card for product_id ' . $failed_product['id'] . ' (' . $listing_type . '): ' . $e->getMessage());
				}
			);

			if ($card !== null) {
				$cards[] = $card;
			}

			if (!$renderer->isEnabled()) {
				break;
			}
		}

		return $cards;
	}

	private function product(array $result, $url, $width, $height, $allow_out_of_stock_purchase) {
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
			$tax_price = (float)$result['special'];
		} else {
			$special = false;
			$tax_price = (float)$result['price'];
		}

		if ($this->config->get('config_tax')) {
			$tax = $this->currency->format($tax_price, $this->session->data['currency']);
		} else {
			$tax = false;
		}

		return array(
			'id'                          => $result['product_id'],
			'name'                        => $result['name'],
			'url'                         => $this->url->link('product/product', 'product_id=' . $result['product_id'] . $url),
			'image'                       => $image,
			'image_srcset'                => $image . ' 1x, ' . $image_2x . ' 2x',
			'image_width'                 => $width,
			'image_height'                => $height,
			'price'                       => $price,
			'special'                     => $special,
			'tax'                         => $tax,
			'rating'                      => $this->config->get('config_review_status') ? (int)$result['rating'] : false,
			'quantity'                    => $result['quantity'],
			'stock_label'                 => isset($result['stock_status']) ? $result['stock_status'] : null,
			'minimum'                     => $result['minimum'],
			'manufacturer'                => isset($result['manufacturer']) ? $result['manufacturer'] : null,
			'allow_out_of_stock_purchase' => $allow_out_of_stock_purchase
		);
	}
}
