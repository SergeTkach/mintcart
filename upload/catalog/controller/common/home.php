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
	 * sort_order, via MintCart\Blocks\BlockRenderer. Only 'products',
	 * 'hero', 'categories', 'banner-grid' and 'benefits' have a
	 * query/provider implementation so far (MINTCART_IMPLEMENTATION_GUIDE.md
	 * §51's other first-wave block types - Text/Image, Brands, Testimonials,
	 * FAQ - are separate, not-yet-started checklist items). An unrecognized
	 * block type is skipped, not fatal, so a page mixing implemented and
	 * not-yet-implemented block types still renders what it can.
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
			} elseif ($block['type'] === 'hero') {
				$markup = $this->renderHeroBlock($block, $block_renderer);
			} elseif ($block['type'] === 'categories') {
				$markup = $this->renderCategoriesBlock($block, $block_renderer);
			} elseif ($block['type'] === 'banner-grid') {
				$markup = $this->renderBannerGridBlock($block, $block_renderer);
			} elseif ($block['type'] === 'benefits') {
				$markup = $this->renderBenefitsBlock($block, $block_renderer);
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
	 * Renders one 'benefits' mc_block row - a pure content block (icon +
	 * title + optional description per item, e.g. "Free shipping",
	 * "Secure payment"). No query, no image resize, no OpenCart model:
	 * MintCart\Blocks\BenefitsBlockProvider does all the normalization,
	 * so this method only calls it and hands the result to BlockRenderer,
	 * same "controller orchestrates, provider/renderer do the work"
	 * shape as renderProductsBlock() but without any of that method's
	 * OpenCart querying since Benefits has none.
	 *
	 * @param array{settings: array<string, mixed>, content: array<string, mixed>} $block
	 * @return string|null Rendered block markup, or null when the block
	 *         has no items (e.g. a freshly-created row with no content
	 *         filled in yet) - matches renderProductsBlock()'s "skip
	 *         empty blocks rather than render an empty section" contract.
	 */
	private function renderBenefitsBlock(array $block, \MintCart\Blocks\BlockRenderer $block_renderer) {
		$settings = (new \MintCart\Blocks\BenefitsBlockProvider())->build($block['settings'], $block['content']);

		if (!$settings['items']) {
			return null;
		}

		return $block_renderer->render('benefits', $settings);
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
	 * Renders one 'hero' mc_block row - a settings-only banner (title,
	 * subtitle, optional CTA, optional background image), no product query
	 * needed. Image resizing mirrors the stock Banner module's own pattern
	 * (extension/module/banner.php): guard with is_file(DIR_IMAGE . ...)
	 * before resizing, since a configured image path can point at a file
	 * that was since deleted from the filesystem.
	 *
	 * @param array{settings: array<string, mixed>, content: array<string, mixed>} $block
	 * @return string|null Rendered block markup, or null when the block has
	 *         no title, subtitle or resolvable image (nothing to show).
	 */
	private function renderHeroBlock(array $block, \MintCart\Blocks\BlockRenderer $block_renderer) {
		$settings = (new \MintCart\Blocks\HeroBlockProvider())->build($block['settings'], $block['content']);

		$image = null;

		if ($settings['image'] !== null && is_file(DIR_IMAGE . $settings['image'])) {
			$this->load->model('tool/image');

			$image = $this->model_tool_image->resize($settings['image'], $settings['image_width'], $settings['image_height']);
		}

		if ($settings['title'] === '' && $settings['subtitle'] === '' && $image === null) {
			return null;
		}

		return $block_renderer->render('hero', array(
			'title'     => $settings['title'],
			'subtitle'  => $settings['subtitle'],
			'cta_label' => $settings['cta_label'],
			'cta_url'   => $settings['cta_url'],
			'image'     => $image
		));
	}

	/**
	 * Queries categories for one 'categories' mc_block row and renders it.
	 * Uses the same `ModelCatalogCategory::getCategories()` +
	 * `product/category` path-link convention every other core category
	 * listing (common/menu, extension/module/category, product/category's
	 * own breadcrumb) already relies on - a category's link path is just
	 * its own category_id at the top level, or `<parent_id>_<category_id>`
	 * one level down, per MintCart\Blocks\CategoriesBlockProvider's
	 * `parent_id` setting.
	 *
	 * @param array{settings: array<string, mixed>, content: array<string, mixed>} $block
	 * @return string|null Rendered block markup, or null when the query
	 *         returned no categories for the configured parent_id.
	 */
	private function renderCategoriesBlock(array $block, \MintCart\Blocks\BlockRenderer $block_renderer) {
		$this->load->model('catalog/category');
		$this->load->model('tool/image');

		$settings = (new \MintCart\Blocks\CategoriesBlockProvider())->build($block['settings'], $block['content']);

		$results = $this->model_catalog_category->getCategories($settings['parent_id']);

		if (!$results) {
			return null;
		}

		$results = array_slice($results, 0, $settings['limit']);

		$categories = array();

		foreach ($results as $result) {
			if ($result['image']) {
				$image = $this->model_tool_image->resize($result['image'], $this->config->get('theme_' . $this->config->get('config_theme') . '_image_category_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_category_height'));
			} else {
				$image = '';
			}

			$path = $settings['parent_id'] ? $settings['parent_id'] . '_' . $result['category_id'] : $result['category_id'];

			$categories[] = array(
				'id'    => $result['category_id'],
				'name'  => $result['name'],
				'url'   => $this->url->link('product/category', 'path=' . $path),
				'image' => $image
			);
		}

		if (!$categories) {
			return null;
		}

		return $block_renderer->render('categories', array(
			'title'      => $settings['title'],
			'categories' => $categories
		));
	}

	/**
	 * Renders one 'banner-grid' mc_block row: resolves each item's image via
	 * OpenCart's tool/image model (same is_file-guarded resize() call the
	 * stock Banner module uses), dropping any item whose image no longer
	 * exists on disk rather than rendering a broken <img>. No product
	 * query/ProductViewProvider involved - a banner item's data all comes
	 * from the block's own settings/content, already normalized by
	 * MintCart\Blocks\BannerGridBlockProvider.
	 *
	 * @param array{settings: array<string, mixed>, content: array<string, mixed>} $block
	 * @return string|null Rendered block markup, or null when every item's
	 *         image failed to resolve.
	 */
	private function renderBannerGridBlock(array $block, \MintCart\Blocks\BlockRenderer $block_renderer) {
		$this->load->model('tool/image');

		$settings = (new \MintCart\Blocks\BannerGridBlockProvider())->build($block['settings'], $block['content']);

		$items = array();

		foreach ($settings['items'] as $item) {
			$width = $item['image_width'] ?: 600;
			$height = $item['image_height'] ?: 400;

			$image = $this->model_tool_image->resize($item['image'], $width, $height);

			if (!$image) {
				continue;
			}

			$items[] = array(
				'image'        => $image,
				'image_width'  => $width,
				'image_height' => $height,
				'url'          => $item['url'],
				'alt'          => $item['alt'],
				'caption'      => $item['caption']
			);
		}

		if (!$items) {
			return null;
		}

		return $block_renderer->render('banner-grid', array(
			'title'     => $settings['title'],
			'columns'   => $settings['columns'],
			'col_width' => $settings['col_width'],
			'items'     => $items
		));
	}
}
