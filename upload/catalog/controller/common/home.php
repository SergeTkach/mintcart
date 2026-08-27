<?php
class ControllerCommonHome extends Controller {
	public function index() {
		$this->load->language('common/home');

		$this->document->setTitle($this->config->get('config_meta_title'));
		$this->document->setDescription($this->config->get('config_meta_description'));
		$this->document->setKeywords($this->config->get('config_meta_keyword'));

		if (isset($this->request->get['route'])) {
			$this->document->addLink($this->config->get('config_url'), 'canonical');
		}

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$data['mc_blocks'] = $this->buildMintBlocks();

		$this->response->setOutput($this->load->view('common/home', $data));
	}

	/**
	 * MintCart Homepage Builder (Phase 10, MINTCART_TECHNICAL_PLAN.md
	 * §16-17): renders every enabled block on the 'home' mc_page, in
	 * sort_order, via MintCart\Blocks\BlockRenderer. Only the 'products'
	 * and 'text-image' block types have a query/provider implementation so
	 * far (MINTCART_IMPLEMENTATION_GUIDE.md §51's other first-wave block
	 * types - Hero, Categories, Banner Grid, Benefits, Brands,
	 * Testimonials, FAQ - are separate, not-yet-started-in-this-file
	 * checklist items). An unrecognized block type is skipped, not fatal,
	 * so a page mixing
	 * implemented and not-yet-implemented block types still renders what
	 * it can.
	 *
	 * Same "is the active theme Mint-API-aware" gate every other Mint
	 * rendering pipeline uses (MintCart\Design\ActiveTheme::isMintAware) -
	 * a store on the legacy 'default' theme, or an unrelated third-party
	 * theme, never queries mc_page/mc_block or pays for this at all.
	 *
	 * @return string[] Rendered block markup, in display order. Empty
	 *         when the theme isn't Mint-aware, no 'home' mc_page exists
	 *         yet, or it has no enabled blocks - all expected states on a
	 *         fresh install before Homepage Builder's admin UI exists.
	 */
	private function buildMintBlocks() {
		$mint_theme = \MintCart\Design\ActiveTheme::directory($this->config->get('config_theme'), $this->config->get('theme_default_directory'));

		if (!\MintCart\Design\ActiveTheme::isMintAware(DIR_TEMPLATE, $mint_theme)) {
			return array();
		}

		$this->load->model('mintcart/block');

		$page = $this->model_mintcart_block->getPage('home');

		if (!$page) {
			return array();
		}

		$blocks = $this->model_mintcart_block->getBlocks($page['page_id'], (int)$this->config->get('config_language_id'));

		if (!$blocks) {
			return array();
		}

		$block_renderer = new \MintCart\Blocks\BlockRenderer(DIR_TEMPLATE, $mint_theme);

		$rendered = array();

		foreach ($blocks as $block) {
			if ($block['type'] === 'products') {
				$markup = $this->renderProductsBlock($block, $block_renderer, $mint_theme);
			} elseif ($block['type'] === 'text-image') {
				$markup = $this->renderTextImageBlock($block, $block_renderer);
			} else {
				$markup = null;
			}

			if ($markup !== null && $markup !== '') {
				$rendered[] = $markup;
			}
		}

		return $rendered;
	}

	/**
	 * Queries products for one 'products' mc_block row and renders it,
	 * reusing the same MintCart\Commerce\ProductViewProvider +
	 * MintCart\Design\ProductCardListRenderer pipeline Category/Search/
	 * Special/Manufacturer already use (MINTCART_TECHNICAL_PLAN.md
	 * §15C/§15D) - the per-product image/price/special/tax mapping below
	 * mirrors ControllerProductCategory::index() exactly, the same
	 * established per-controller duplication every other listing
	 * controller carries pending a shared ProductListingService
	 * (MINTCART_TECHNICAL_PLAN.md §15C, not built yet - CLAUDE.md §85, no
	 * abstraction before enough real consumers justify it).
	 *
	 * @param array{settings: array<string, mixed>, content: array<string, mixed>} $block
	 * @param string $mint_theme
	 * @return string|null Rendered block markup, or null when the query
	 *         returned no products, or every product failed to render.
	 */
	private function renderProductsBlock(array $block, \MintCart\Blocks\BlockRenderer $block_renderer, $mint_theme) {
		$this->load->model('catalog/product');
		$this->load->model('tool/image');

		$settings = (new \MintCart\Blocks\ProductsBlockProvider())->build($block['settings'], $block['content']);

		$filter_data = array(
			'sort'  => $settings['sort'],
			'order' => $settings['order'],
			'start' => 0,
			'limit' => $settings['limit']
		);

		if ($settings['category_id'] !== null) {
			$filter_data['filter_category_id'] = $settings['category_id'];
		}

		$results = $this->model_catalog_product->getProducts($filter_data);

		if (!$results) {
			return null;
		}

		$product_card_list_renderer = new \MintCart\Design\ProductCardListRenderer(
			new \MintCart\Design\ComponentRenderer(DIR_TEMPLATE, $mint_theme)
		);

		$text_badge = array(
			'sale'         => $this->language->get('text_badge_sale'),
			'out-of-stock' => $this->language->get('text_badge_out_of_stock'),
			'new'          => $this->language->get('text_badge_new')
		);

		// Same backorder flag ControllerProductCategory checks - without
		// it, ProductViewProvider would disable add-to-cart for any
		// out-of-stock product even on a store that allows backorders.
		$allow_out_of_stock_purchase = (bool)$this->config->get('config_stock_checkout');

		$product_cards = array();

		foreach ($results as $position => $result) {
			if ($result['image']) {
				$image = $this->model_tool_image->resize($result['image'], $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_height'));
			} else {
				$image = $this->model_tool_image->resize('placeholder.png', $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_height'));
			}

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

			if ($this->config->get('config_review_status')) {
				$rating = (int)$result['rating'];
			} else {
				$rating = false;
			}

			$href = $this->url->link('product/product', 'product_id=' . $result['product_id']);

			$product_card = $product_card_list_renderer->render(
				array(
					'id'                          => $result['product_id'],
					'name'                        => $result['name'],
					'url'                         => $href,
					'image'                       => $image,
					'price'                       => $price,
					'special'                     => $special,
					'tax'                         => $tax,
					'rating'                      => $rating,
					'quantity'                    => $result['quantity'],
					'stock_label'                 => isset($result['stock_status']) ? $result['stock_status'] : null,
					'minimum'                     => $result['minimum'],
					'manufacturer'                => isset($result['manufacturer']) ? $result['manufacturer'] : null,
					'allow_out_of_stock_purchase' => $allow_out_of_stock_purchase
				),
				array(),
				array(
					'listing_type' => 'homepage_products_block',
					'position'     => $position + 1
				),
				array(
					'text_badge'             => $text_badge,
					'text_wishlist'          => $this->language->get('button_wishlist'),
					'text_compare'           => $this->language->get('button_compare'),
					'text_rated'             => $this->language->get('text_rated'),
					'text_tax'               => $this->language->get('text_tax'),
					'text_add_to_cart'       => $this->language->get('button_cart'),
					'text_quick_order'       => $this->language->get('text_quick_order'),
					'text_added_to_cart'     => $this->language->get('text_added_to_cart'),
					'text_add_to_cart_error' => $this->language->get('text_add_to_cart_error')
				),
				function (\Throwable $e, array $failed_product) {
					// Same degrade-the-grid-not-the-page behaviour as every
					// other ProductCardListRenderer consumer - a broken
					// component must not fatal the whole homepage.
					(new \MintCart\Core\Logger())->error('ComponentRenderer failed to render product-card for product_id ' . $failed_product['id'] . ' in Homepage Products block: ' . $e->getMessage());
				}
			);

			if ($product_card !== null) {
				$product_cards[] = $product_card;
			}

			if (!$product_card_list_renderer->isEnabled()) {
				break;
			}
		}

		if (!$product_cards) {
			return null;
		}

		return $block_renderer->render('products', array(
			'title'         => $settings['title'],
			'product_cards' => $product_cards
		));
	}

	/**
	 * Renders one 'text-image' mc_block row - a plain content block with no
	 * OpenCart query at all, unlike Products/Hero. Covers both the "Text"
	 * and "Text + Image" catalog entries
	 * (MINTCART_IMPLEMENTATION_GUIDE.md §33): an `image` setting is
	 * optional, so a block with none renders as plain text, exactly like a
	 * "Text" block would (MintCart\Blocks\TextImageBlockProvider's own doc
	 * comment).
	 *
	 * Image resolution mirrors renderHeroBlock() exactly - same
	 * is_file()-guarded model_tool_image->resize() call, same
	 * "degrade to no image rather than a broken <img>" behaviour.
	 *
	 * @param array{settings: array<string, mixed>, content: array<string, mixed>} $block
	 * @return string|null Rendered block markup, or null when the block has
	 *         no title, no body and no resolvable image (nothing to show).
	 */
	private function renderTextImageBlock(array $block, \MintCart\Blocks\BlockRenderer $block_renderer) {
		$settings = (new \MintCart\Blocks\TextImageBlockProvider())->build($block['settings'], $block['content']);

		$image = null;

		if ($settings['image'] !== null && is_file(DIR_IMAGE . $settings['image'])) {
			$this->load->model('tool/image');

			$image = $this->model_tool_image->resize($settings['image'], $settings['image_width'], $settings['image_height']);
		}

		if ($settings['title'] === '' && $settings['body'] === '' && $image === null) {
			return null;
		}

		return $block_renderer->render('text-image', array(
			'title'          => $settings['title'],
			'body'           => $settings['body'],
			'image'          => $image,
			'image_alt'      => $settings['image_alt'],
			'image_position' => $settings['image_position']
		));
	}
}
