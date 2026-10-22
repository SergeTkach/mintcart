<?php
class ControllerCommonHome extends Controller {
	public function index() {
		$this->load->language('common/home');

		// MintCart\Seo\MetaBuilder (MINTCART_FUNCTIONAL_SCOPE.md §16, Phase
		// 18): config_meta_title/config_meta_description are blank on a
		// fresh install until a merchant fills them in under Settings, so
		// without a fallback the homepage ships an empty <title> from
		// day one - the store name (config_name, already set by Setup
		// Wizard's Store step) is a sensible fallback title. No fallback
		// description exists (there is no second, shorter store-level
		// description field in stock OpenCart), so the description simply
		// stays '' when config_meta_description is blank, same as today.
		// The third argument is title-only; see docs/developer/seo/meta-builder.md.
		$mint_meta = (new \MintCart\Seo\MetaBuilder())->build(
			$this->config->get('config_meta_title'),
			$this->config->get('config_meta_description'),
			$this->config->get('config_name')
		);

		$this->document->setTitle($mint_meta['title']);
		$this->document->setDescription($mint_meta['description']);
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
	 * sort_order, via MintCart\Blocks\BlockRenderer. Every first-wave block
	 * type from MINTCART_IMPLEMENTATION_GUIDE.md §51 now has a
	 * query/provider implementation: 'products', 'hero', 'categories',
	 * 'banner-grid', 'benefits', 'faq', 'testimonials', 'brands',
	 * 'text-image', 'cta', 'newsletter', 'spacer', 'text', 'html' and
	 * 'gallery'. An unrecognized block type is skipped, not fatal, so a
	 * page mixing implemented and not-yet-implemented block types still
	 * renders what it can.
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
			} elseif ($block['type'] === 'faq') {
				$markup = $this->renderFaqBlock($block, $block_renderer);
			} elseif ($block['type'] === 'testimonials') {
				$markup = $this->renderTestimonialsBlock($block, $block_renderer);
			} elseif ($block['type'] === 'brands') {
				$markup = $this->renderBrandsBlock($block, $block_renderer);
			} elseif ($block['type'] === 'text-image') {
				$markup = $this->renderTextImageBlock($block, $block_renderer);
			} elseif ($block['type'] === 'cta') {
				$markup = $this->renderCtaBlock($block, $block_renderer);
			} elseif ($block['type'] === 'newsletter') {
				$markup = $this->renderNewsletterBlock($block, $block_renderer);
			} elseif ($block['type'] === 'spacer') {
				$markup = $this->renderSpacerBlock($block, $block_renderer);
			} elseif ($block['type'] === 'text') {
				$markup = $this->renderTextBlock($block, $block_renderer);
			} elseif ($block['type'] === 'html') {
				$markup = $this->renderHtmlBlock($block, $block_renderer);
			} elseif ($block['type'] === 'gallery') {
				$markup = $this->renderGalleryBlock($block, $block_renderer);
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

		$icons = new \MintCart\Design\Icon(DIR_SYSTEM . 'mintcart/icons');

		foreach ($settings['items'] as $key => $item) {
			$settings['items'][$key]['svg'] = $icons->svg($item['icon']);
		}

		return $block_renderer->render('benefits', $settings);
	}

	/**
	 * Renders one 'testimonials' mc_block row - a pure content block (no
	 * product query), following the same
	 * TestimonialsBlockProvider::build() normalize -> BlockRenderer::render()
	 * shape as renderProductsBlock(), minus the product-listing step.
	 *
	 * Each item's optional `avatar` is resized the same is_file()-guarded
	 * way the stock Banner module resizes its images - a missing/deleted
	 * file degrades to no avatar for that testimonial rather than a broken
	 * <img>, the item itself is still rendered.
	 *
	 * @param array{settings: array<string, mixed>, content: array<string, mixed>} $block
	 * @return string|null Rendered block markup, or null when the block has
	 *         no renderable items (missing quote/author dropped by the
	 *         provider).
	 */
	private function renderTestimonialsBlock(array $block, \MintCart\Blocks\BlockRenderer $block_renderer) {
		$settings = (new \MintCart\Blocks\TestimonialsBlockProvider())->build($block['settings'], $block['content']);

		if (!$settings['items']) {
			return null;
		}

		$this->load->model('tool/image');

		foreach ($settings['items'] as &$item) {
			if ($item['avatar'] && is_file(DIR_IMAGE . $item['avatar'])) {
				$item['avatar'] = $this->model_tool_image->resize($item['avatar'], $item['avatar_width'], $item['avatar_height']);
			} else {
				$item['avatar'] = null;
			}
		}
		unset($item);

		return $block_renderer->render('testimonials', array(
			'title' => $settings['title'],
			'items' => $settings['items']
		));
	}

	/**
	 * Queries products for one 'products' mc_block row and renders it through
	 * the shared mintcart/product_cards pipeline (MINTCART_TECHNICAL_PLAN.md
	 * §15C/§15D, docs/developer/commerce/product-cards.md).
	 *
	 * @param array{settings: array<string, mixed>, content: array<string, mixed>} $block
	 * @param string $mint_theme
	 * @return string|null Rendered block markup, or null when the query
	 *         returned no products, or every product failed to render.
	 */
	private function renderProductsBlock(array $block, \MintCart\Blocks\BlockRenderer $block_renderer, $mint_theme) {
		$this->load->model('catalog/product');

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

		// Shared product-card pipeline (docs/developer/commerce/product-cards.md).
		$product_cards = $this->load->controller('mintcart/product_cards', array(
			'results'      => $results,
			'listing_type' => 'homepage_products_block'
		));

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
				$image = $this->model_tool_image->resize($result['image'], 300, 300); // block tiles are wider than the 80px theme category size
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

	/**
	 * Renders one 'gallery' mc_block row: resolves each item's image via
	 * OpenCart's tool/image model (same is_file-guarded resize() call every
	 * other image-referencing block uses), dropping any item whose image no
	 * longer exists on disk rather than rendering a broken <img>. No product
	 * query/ProductViewProvider involved - every item's data already comes
	 * from the block's own settings/content, normalized by
	 * MintCart\Blocks\GalleryBlockProvider.
	 *
	 * @param array{settings: array<string, mixed>, content: array<string, mixed>} $block
	 * @return string|null Rendered block markup, or null when every item's
	 *         image failed to resolve.
	 */
	private function renderGalleryBlock(array $block, \MintCart\Blocks\BlockRenderer $block_renderer) {
		$this->load->model('tool/image');

		$settings = (new \MintCart\Blocks\GalleryBlockProvider())->build($block['settings'], $block['content']);

		$items = array();

		foreach ($settings['items'] as $item) {
			$width = $item['image_width'] ?: 600;
			$height = $item['image_height'] ?: 600;

			$image = $this->model_tool_image->resize($item['image'], $width, $height);

			if (!$image) {
				continue;
			}

			$items[] = array(
				'image'        => $image,
				'image_width'  => $width,
				'image_height' => $height,
				'alt'          => $item['alt'],
				'caption'      => $item['caption']
			);
		}

		if (!$items) {
			return null;
		}

		return $block_renderer->render('gallery', array(
			'title'     => $settings['title'],
			'columns'   => $settings['columns'],
			'col_width' => $settings['col_width'],
			'items'     => $items
		));
	}

	/**
	 * Renders one 'faq' mc_block row - a pure content block, no product
	 * query or image resize involved (MintCart\Blocks\FaqBlockProvider
	 * only normalizes already-decoded settings/content), same shape as
	 * renderProductsBlock() above but with no OpenCart model dependency.
	 *
	 * @param array{settings: array<string, mixed>, content: array<string, mixed>} $block
	 * @return string|null Rendered block markup, or null when the block
	 *         has no valid question/answer items to show.
	 */
	private function renderFaqBlock(array $block, \MintCart\Blocks\BlockRenderer $block_renderer) {
		$settings = (new \MintCart\Blocks\FaqBlockProvider())->build($block['settings'], $block['content']);

		if (!$settings['items']) {
			return null;
		}

		return $block_renderer->render('faq', array(
			'title' => $settings['title'],
			'items' => $settings['items']
		));
	}

	/**
	 * Renders one 'brands' mc_block row: resolves each item's logo image
	 * via OpenCart's tool/image model (same is_file-guarded resize() call
	 * every other image-bearing block uses), dropping any item whose image
	 * no longer exists on disk rather than rendering a broken <img>. No
	 * product query/ProductViewProvider involved - a brand item's data all
	 * comes from the block's own settings/content, already normalized by
	 * MintCart\Blocks\BrandsBlockProvider.
	 *
	 * @param array{settings: array<string, mixed>, content: array<string, mixed>} $block
	 * @return string|null Rendered block markup, or null when every item's
	 *         image failed to resolve.
	 */
	private function renderBrandsBlock(array $block, \MintCart\Blocks\BlockRenderer $block_renderer) {
		$this->load->model('tool/image');

		$settings = (new \MintCart\Blocks\BrandsBlockProvider())->build($block['settings'], $block['content']);

		$items = array();

		foreach ($settings['items'] as $item) {
			$image = $this->model_tool_image->resize($item['image'], $item['image_width'], $item['image_height']);

			if (!$image) {
				continue;
			}

			$items[] = array(
				'image'        => $image,
				'image_width'  => $item['image_width'],
				'image_height' => $item['image_height'],
				'url'          => $item['url'],
				'alt'          => $item['alt']
			);
		}

		if (!$items) {
			return null;
		}

		return $block_renderer->render('brands', array(
			'title' => $settings['title'],
			'items' => $items
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
			'image_position' => $settings['image_position'],
			// MintCart: threaded through so text-image.twig can render
			// explicit <img width>/<height> (Functional Scope §19
			// "responsive images; width/height"), same convention
			// banner-grid/gallery blocks already use. Only meaningful
			// alongside $image - a null image means the template never
			// reads them.
			'image_width'    => $settings['image_width'],
			'image_height'   => $settings['image_height']
		));
	}

	/**
	 * Renders one 'cta' mc_block row. A CTA block is pure content (title,
	 * supporting text, optional button) with no query/model involved at
	 * all - MintCart\Blocks\CtaBlockProvider only normalizes the already-
	 * decoded settings/content, so this method just does that and hands
	 * the result to blocks/cta.twig via BlockRenderer.
	 *
	 * @param array{settings: array<string, mixed>, content: array<string, mixed>} $block
	 * @return string|null Rendered block markup. blocks/cta.twig itself
	 *         renders nothing when title/text/button are all empty, so this
	 *         can legitimately return an empty string, which the caller
	 *         already treats the same as null.
	 */
	private function renderCtaBlock(array $block, \MintCart\Blocks\BlockRenderer $block_renderer) {
		$settings = (new \MintCart\Blocks\CtaBlockProvider())->build($block['settings'], $block['content']);

		return $block_renderer->render('cta', $settings);
	}

	/**
	 * Renders one 'newsletter' mc_block row: a pure content block, no
	 * OpenCart query or image resize involved at all - just normalizes via
	 * MintCart\Blocks\NewsletterBlockProvider and renders through
	 * BlockRenderer, same simplest-case shape as a text-only block.
	 *
	 * @param array{settings: array<string, mixed>, content: array<string, mixed>} $block
	 * @return string|null Rendered block markup, or null when title, text
	 *         and the subscribe form are all empty (nothing to show).
	 */
	private function renderNewsletterBlock(array $block, \MintCart\Blocks\BlockRenderer $block_renderer) {
		$settings = (new \MintCart\Blocks\NewsletterBlockProvider())->build($block['settings'], $block['content']);

		if ($settings['title'] === '' && $settings['text'] === '' && !$settings['enabled']) {
			return null;
		}

		return $block_renderer->render('newsletter', $settings);
	}

	/**
	 * Renders one 'spacer' mc_block row - the simplest block type, purely
	 * structural (MintCart\Blocks\SpacerBlockProvider has no localized
	 * content and this method makes no OpenCart query at all), unlike
	 * renderProductsBlock() above.
	 *
	 * @param array{settings: array<string, mixed>, content: array<string, mixed>} $block
	 * @return string Rendered block markup. Never null/empty - a spacer
	 *         always has something to render (a size, defaulting to 'md').
	 */
	private function renderSpacerBlock(array $block, \MintCart\Blocks\BlockRenderer $block_renderer) {
		$settings = (new \MintCart\Blocks\SpacerBlockProvider())->build($block['settings']);

		return $block_renderer->render('spacer', array(
			'size' => $settings['size']
		));
	}

	/**
	 * Renders one 'text' mc_block row - the simplest block type, no
	 * product/OpenCart data dependency at all: MintCart\Blocks\TextBlockProvider
	 * only normalizes the block's own settings/content, and
	 * blocks/text.twig renders it directly via `mc-prose`
	 * (MINTCART_IMPLEMENTATION_GUIDE.md §20).
	 *
	 * @param array{settings: array<string, mixed>, content: array<string, mixed>} $block
	 * @return string|null Rendered block markup, or null when the block
	 *         has no content (an empty text block renders nothing, same
	 *         "never an empty section" rule renderProductsBlock() follows).
	 */
	private function renderTextBlock(array $block, \MintCart\Blocks\BlockRenderer $block_renderer) {
		$settings = (new \MintCart\Blocks\TextBlockProvider())->build($block['settings'], $block['content']);

		if ($settings['content'] === '') {
			return null;
		}

		return $block_renderer->render('text', $settings);
	}

	/**
	 * Renders one 'html' mc_block row - the simplest block type, no product
	 * query and no OpenCart model dependency beyond the already-loaded
	 * block row itself (MINTCART_IMPLEMENTATION_GUIDE.md §33's first-wave
	 * block list includes "HTML" alongside Hero/Products/Categories/...).
	 *
	 * @param array{settings: array<string, mixed>, content: array<string, mixed>} $block
	 * @return string|null Rendered block markup, or null when the block's
	 *         html content is empty (html.twig's own {% if html %} guard
	 *         would render nothing anyway - returning null here lets the
	 *         caller's "was anything actually rendered" check stay uniform
	 *         across every block type).
	 */
	private function renderHtmlBlock(array $block, \MintCart\Blocks\BlockRenderer $block_renderer) {
		$settings = (new \MintCart\Blocks\HtmlBlockProvider())->build($block['settings'], $block['content']);

		if ($settings['html'] === '') {
			return null;
		}

		return $block_renderer->render('html', array(
			'html' => $settings['html']
		));
	}
}
