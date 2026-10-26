<?php
class ControllerProductCategory extends Controller {
	public function index() {
		$this->load->language('product/category');

		$this->load->model('catalog/category');

		$this->load->model('catalog/product');

		$this->load->model('tool/image');

		if (isset($this->request->get['filter'])) {
			$filter = $this->request->get['filter'];
		} else {
			$filter = '';
		}

		if (isset($this->request->get['sort'])) {
			$sort = $this->request->get['sort'];
		} else {
			$sort = 'p.sort_order';
		}

		if (isset($this->request->get['order'])) {
			$order = $this->request->get['order'];
		} else {
			$order = 'ASC';
		}

		if (isset($this->request->get['page'])) {
			$page = (int)$this->request->get['page'];
		} else {
			$page = 1;
		}

		if (isset($this->request->get['limit']) && (int)$this->request->get['limit'] > 0) {
			$limit = (int)$this->request->get['limit'];
		} else {
			$limit = $this->config->get('theme_' . $this->config->get('config_theme') . '_product_limit');
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);

		if (isset($this->request->get['path'])) {
			$url = '';

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['limit'])) {
				$url .= '&limit=' . $this->request->get['limit'];
			}

			$path = '';

			$parts = explode('_', (string)$this->request->get['path']);

			$category_id = (int)array_pop($parts);

			foreach ($parts as $path_id) {
				if (!$path) {
					$path = (int)$path_id;
				} else {
					$path .= '_' . (int)$path_id;
				}

				$category_info = $this->model_catalog_category->getCategory($path_id);

				if ($category_info) {
					$data['breadcrumbs'][] = array(
						'text' => $category_info['name'],
						'href' => $this->url->link('product/category', 'path=' . $path . $url)
					);
				}
			}
		} else {
			$category_id = 0;
		}

		$category_info = $this->model_catalog_category->getCategory($category_id);

		if ($category_info) {
			// MintCart\Seo\MetaBuilder (MINTCART_FUNCTIONAL_SCOPE.md §16,
			// Phase 18): a blank "Meta Tag Title" falls back to the category
			// name. The optional fourth argument is omitted deliberately:
			// a blank meta description stays blank, not a copy of the title.
			// See docs/developer/seo/meta-builder.md, "Live wiring".
			// Pure string logic, cannot throw - no try/catch needed, same
			// as every other Diagnostics/CanonicalUrlBuilder-shaped pure
			// value object wired into this controller.
			// Marketing -> SEO -> Meta tags: the merchant's templates, with the category's values.
			$meta_parent = '';

			if (!empty($category_info['parent_id'])) {
				$meta_parent_info = $this->model_catalog_category->getCategory($category_info['parent_id']);

				$meta_parent = $meta_parent_info ? $meta_parent_info['name'] : '';
			}

			$mint_meta = (new \MintCart\Seo\MetaBuilder(\MintCart\Seo\MetaRules::forPage($this->config->get('mintcart_seo_meta'), 'category', (int)$this->config->get('config_language_id'))))->build(
				$category_info['meta_title'],
				$category_info['meta_description'],
				$category_info['name'],
				null,
				array(
					'name'        => $category_info['name'],
					'parent'      => $meta_parent,
					'store'       => $this->config->get('config_name'),
					'description' => $category_info['description']
				)
			);

			$this->document->setTitle($mint_meta['title']);
			$this->document->setDescription($mint_meta['description']);
			$this->document->setKeywords($category_info['meta_keyword']);

			$data['heading_title'] = $category_info['name'];

			$data['text_compare'] = sprintf($this->language->get('text_compare'), (isset($this->session->data['compare']) ? count($this->session->data['compare']) : 0));

			// Set the last category breadcrumb
			$data['breadcrumbs'][] = array(
				'text' => $category_info['name'],
				'href' => $this->url->link('product/category', 'path=' . $this->request->get['path'])
			);

			// MintCart: width/height alongside the resized thumb so
			// category.twig can render explicit <img width>/<height>
			// (Functional Scope §19 "responsive images; width/height" —
			// prevents layout shift while the image loads), same
			// convention ProductViewProvider already uses for
			// product.image_width/product.image_height.
			$data['thumb_width'] = (int)$this->config->get('theme_' . $this->config->get('config_theme') . '_image_category_width');
			$data['thumb_height'] = (int)$this->config->get('theme_' . $this->config->get('config_theme') . '_image_category_height');

			if ($category_info['image']) {
				$data['thumb'] = $this->model_tool_image->resize($category_info['image'], $data['thumb_width'], $data['thumb_height']);
			} else {
				$data['thumb'] = '';
			}

			$data['description'] = html_entity_decode($category_info['description'], ENT_QUOTES, 'UTF-8');

			// MintCart: a short intro above the product list, the rest below it.
			$description_parts = (new \MintCart\Catalog\DescriptionSplitter())->split($data['description'], 360);
			$data['description_intro'] = $description_parts['intro'];
			$data['description_rest'] = $description_parts['rest'];
			$data['compare'] = $this->url->link('product/compare');

			$url = '';

			if (isset($this->request->get['filter'])) {
				$url .= '&filter=' . $this->request->get['filter'];
			}

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['limit'])) {
				$url .= '&limit=' . $this->request->get['limit'];
			}

			$data['categories'] = array();

			$results = $this->model_catalog_category->getCategories($category_id);

			foreach ($results as $result) {
				$filter_data = array(
					'filter_category_id'  => $result['category_id'],
					'filter_sub_category' => true
				);

				$data['categories'][] = array(
					'name' => $result['name'] . ($this->config->get('config_product_count') ? ' (' . $this->model_catalog_product->getTotalProducts($filter_data) . ')' : ''),
					'href' => $this->url->link('product/category', 'path=' . $this->request->get['path'] . '_' . $result['category_id'] . $url)
				);
			}

			$data['products'] = array();

			// MintCart\Design\ProductCardListRenderer (which composes
			// MintCart\Commerce\ProductViewProvider + ComponentRenderer)
			// wiring (MINTCART_TECHNICAL_PLAN.md §11/§15C-D, Phase 8). Only
			// built when the active theme ships a theme.json manifest (i.e.
			// it is Mint-API-aware - Mint Default, or any future/custom Mint
			// theme). Neither the legacy 'default' theme nor an unrelated
			// third-party OpenCart theme has one, and neither of their
			// templates reads the array below this comment, so stores on
			// either kind of theme pay no extra cost and carry no extra risk
			// from this still-new pipeline - checking for 'default' by name
			// alone would have left every non-Mint third-party theme paying
			// that cost for nothing.
			$mint_theme = \MintCart\Design\ActiveTheme::directory($this->config->get('config_theme'), $this->config->get('theme_default_directory'));
			$mint_render_product_cards = \MintCart\Design\ActiveTheme::isMintAware(DIR_TEMPLATE, $mint_theme);

			// MintCart\Core\Diagnostics (MINTCART_TECHNICAL_PLAN.md §35,
			// MINTCART_FUNCTIONAL_SCOPE.md §19 "render time"). Off by
			// default - every mark()/increment()/time() call becomes a
			// cheap no-op unless a store explicitly sets the
			// 'mintcart_dev_mode' setting (no admin toggle exists for it
			// yet; docs/developer/core/diagnostics.md's "Next step" tracks
			// that as a separate increment). First real consumer of both
			// this flag and ComponentRenderer's optional 4th constructor
			// argument - see that class's own doc comment.
			$mint_diagnostics = new \MintCart\Core\Diagnostics((bool)$this->config->get('mintcart_dev_mode'));

			$filter_data = array(
				'filter_category_id' => $category_id,
				'filter_filter'      => $filter,
				'sort'               => $sort,
				'order'              => $order,
				'start'              => ($page - 1) * $limit,
				'limit'              => $limit
			);

			$product_total = $this->model_catalog_product->getTotalProducts($filter_data);

			$results = $this->model_catalog_product->getProducts($filter_data);

			foreach ($results as $result) {
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

				$href = $this->url->link('product/product', 'path=' . $this->request->get['path'] . '&product_id=' . $result['product_id'] . $url);

				$data['products'][] = array(
					'product_id'  => $result['product_id'],
					'thumb'       => $image,
					'name'        => $result['name'],
					'description' => utf8_substr(trim(strip_tags(html_entity_decode($result['description'], ENT_QUOTES, 'UTF-8'))), 0, $this->config->get('theme_' . $this->config->get('config_theme') . '_product_description_length')) . '..',
					'price'       => $price,
					'special'     => $special,
					'tax'         => $tax,
					'minimum'     => $result['minimum'] > 0 ? $result['minimum'] : 1,
					'rating'      => $result['rating'],
					'href'        => $href
				);

			}

			// Shared product-card pipeline (docs/developer/commerce/product-cards.md);
			// null on non-Mint themes, which keep using $data['products'].
			if ($mint_render_product_cards) {
				$data['product_cards'] = $this->load->controller('mintcart/product_cards', array(
					'results'      => $results,
					'listing_type' => 'category',
					'link_prefix'  => 'path=' . $this->request->get['path'] . '&',
					'url'          => $url,
					'diagnostics'  => $mint_diagnostics
				));
			}

			$url = '';

			if (isset($this->request->get['filter'])) {
				$url .= '&filter=' . $this->request->get['filter'];
			}

			if (isset($this->request->get['limit'])) {
				$url .= '&limit=' . $this->request->get['limit'];
			}

			$data['sorts'] = array();

			// MintCart\Catalog\SortOptionsBuilder (MINTCART_TECHNICAL_PLAN.md
			// §15C/§15E, §38C "SortOptionsBuilder" candidate) — pure
			// extraction of this exact sort/order/text_key list and its
			// review-status gate, already proven byte-for-byte identical to
			// this controller's own prior inline block by
			// tests/mintcart/catalog/sort-options-builder-test.php. This
			// controller still owns the language lookup and href building —
			// the builder never calls $this->language or $this->url itself.
			foreach ((new \MintCart\Catalog\SortOptionsBuilder())->build((bool)$this->config->get('config_review_status')) as $sort_option) {
				$data['sorts'][] = array(
					'text'  => $this->language->get($sort_option['text_key']),
					'value' => $sort_option['sort'] . '-' . $sort_option['order'],
					'href'  => $this->url->link('product/category', 'path=' . $this->request->get['path'] . '&sort=' . $sort_option['sort'] . '&order=' . $sort_option['order'] . $url)
				);
			}

			$url = '';

			if (isset($this->request->get['filter'])) {
				$url .= '&filter=' . $this->request->get['filter'];
			}

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			$data['limits'] = array();

			// MintCart\Catalog\LimitOptionsBuilder (MINTCART_TECHNICAL_PLAN.md
			// §15C/§15E, §38C "LimitOptionsBuilder" candidate) — pure
			// extraction of the array_unique()+sort() merge, already proven
			// byte-for-byte identical to this controller's own prior inline
			// block by tests/mintcart/catalog/limit-options-builder-test.php.
			$limits = (new \MintCart\Catalog\LimitOptionsBuilder())->build((int)$this->config->get('theme_' . $this->config->get('config_theme') . '_product_limit'));

			foreach($limits as $value) {
				$data['limits'][] = array(
					'text'  => $value,
					'value' => $value,
					'href'  => $this->url->link('product/category', 'path=' . $this->request->get['path'] . $url . '&limit=' . $value)
				);
			}

			$url = '';

			if (isset($this->request->get['filter'])) {
				$url .= '&filter=' . $this->request->get['filter'];
			}

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['limit'])) {
				$url .= '&limit=' . $this->request->get['limit'];
			}

			$pagination = new Pagination();
			$pagination->total = $product_total;
			$pagination->page = $page;
			$pagination->limit = $limit;
			$pagination->url = $this->url->link('product/category', 'path=' . $this->request->get['path'] . $url . '&page={page}');

			$data['pagination'] = $pagination->render();

			$data['results'] = sprintf($this->language->get('text_pagination'), ($product_total) ? (($page - 1) * $limit) + 1 : 0, ((($page - 1) * $limit) > ($product_total - $limit)) ? $product_total : ((($page - 1) * $limit) + $limit), $product_total, ceil($product_total / $limit));

			// MintCart\Seo\CanonicalUrlBuilder (MINTCART_FUNCTIONAL_SCOPE.md
			// §16, MINTCART_TECHNICAL_PLAN.md §31, Phase 18) - shared, tested
			// primitive replacing the inline http://googlewebmastercentral.
			// blogspot.com/2011/09/pagination-with-relnext-and-relprev.html
			// block duplicated across category.php/manufacturer.php/
			// search.php/special.php. Canonical/prev/next links are a
			// per-route SEO signal, not a Mint component DOM contract, so
			// this runs unconditionally regardless of active theme, matching
			// RobotsBuilder's wiring in common/header.php.
			try {
				$canonical_builder = new \MintCart\Seo\CanonicalUrlBuilder();
				$canonical_links = $canonical_builder->build($page, (int)$limit, (int)$product_total, 'path=' . $category_info['category_id']);

				$this->document->addLink($this->url->link('product/category', $canonical_links['canonical']), 'canonical');

				if ($canonical_links['prev'] !== null) {
					$this->document->addLink($this->url->link('product/category', $canonical_links['prev']), 'prev');
				}

				if ($canonical_links['next'] !== null) {
					$this->document->addLink($this->url->link('product/category', $canonical_links['next']), 'next');
				}
			} catch (\Throwable $e) {
				(new \MintCart\Core\Logger())->error('CanonicalUrlBuilder failed to build pagination links: ' . $e->getMessage());
			}

			$data['sort'] = $sort;
			$data['order'] = $order;
			$data['limit'] = $limit;

			$data['text_refine'] = $this->language->get('text_refine');
			$data['text_sort'] = $this->language->get('text_sort');
			$data['text_limit'] = $this->language->get('text_limit');
			$data['text_empty'] = $this->language->get('text_empty');
			$data['button_continue'] = $this->language->get('button_continue');
			$data['continue'] = $this->url->link('common/home');

			$data['column_left'] = $this->load->controller('common/column_left');
			$data['column_right'] = $this->load->controller('common/column_right');
			$data['content_top'] = $this->load->controller('common/content_top');
			$data['content_bottom'] = $this->load->controller('common/content_bottom');
			$data['footer'] = $this->load->controller('common/footer');
			$data['header'] = $this->load->controller('common/header');

			// MintCart\Seo\SchemaOrgBuilder BreadcrumbList JSON-LD
			// (MINTCART_FUNCTIONAL_SCOPE.md §16, Phase 18). Re-checks
			// Mint-awareness directly (not $mint_render_product_cards,
			// which above this point may have been flipped to false by
			// ProductCardListRenderer::isEnabled() after an unrelated
			// per-product render failure) - the breadcrumb list is
			// independent of whether product-card rendering succeeded.
			// $data['breadcrumbs'] is complete by this point (built at the
			// top of this method and appended to throughout).
			if (\MintCart\Design\ActiveTheme::isMintAware(DIR_TEMPLATE, $mint_theme)) {
				try {
					$schema_builder = new \MintCart\Seo\SchemaOrgBuilder();

					$data['mc_schema_breadcrumb'] = $schema_builder->toJsonLd($schema_builder->breadcrumbList($data['breadcrumbs']));
				} catch (\Throwable $e) {
					(new \MintCart\Core\Logger())->error('SchemaOrgBuilder failed to build BreadcrumbList schema: ' . $e->getMessage());
				}
			}

			$mint_diagnostics->mark('view_rendered');

			$output = $this->load->view('product/category', $data);

			// Functional Scope §19: "Production UI не повинен показувати
			// sensitive debug data" - the summary is only ever appended
			// when dev mode is on (isEnabled() mirrors the constructor
			// flag above), and only as an HTML comment appended to the
			// final response body, never written into $data/the template
			// itself, so no theme override can accidentally surface it in
			// visible markup. A `&mintcart_diagnostics=json` request (still
			// gated by dev mode) gets the same summary back as a standalone
			// JSON response instead of the HTML comment - same shape as
			// `mintcart/readiness`, for tooling that would rather fetch
			// diagnostics than scrape a page's markup
			// (docs/developer/core/diagnostics.md "JSON diagnostics
			// surface").
			if ($mint_diagnostics->isEnabled() && isset($this->request->get['mintcart_diagnostics']) && $this->request->get['mintcart_diagnostics'] === 'json') {
				$this->response->addHeader('Content-Type: application/json');
				$this->response->setOutput(json_encode($mint_diagnostics->summary()));

				return;
			}

			if ($mint_diagnostics->isEnabled()) {
				$output .= "\n<!-- mintcart-diagnostics " . json_encode($mint_diagnostics->summary()) . " -->";
			}

			$this->response->setOutput($output);
		} else {
			$url = '';

			if (isset($this->request->get['path'])) {
				$url .= '&path=' . $this->request->get['path'];
			}

			if (isset($this->request->get['filter'])) {
				$url .= '&filter=' . $this->request->get['filter'];
			}

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			if (isset($this->request->get['limit'])) {
				$url .= '&limit=' . $this->request->get['limit'];
			}

			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('text_error'),
				'href' => $this->url->link('product/category', $url)
			);

			$this->document->setTitle($this->language->get('text_error'));

			$data['continue'] = $this->url->link('common/home');

			$this->response->addHeader($this->request->server['SERVER_PROTOCOL'] . ' 404 Not Found');

			$data['column_left'] = $this->load->controller('common/column_left');
			$data['column_right'] = $this->load->controller('common/column_right');
			$data['content_top'] = $this->load->controller('common/content_top');
			$data['content_bottom'] = $this->load->controller('common/content_bottom');
			$data['footer'] = $this->load->controller('common/footer');
			$data['header'] = $this->load->controller('common/header');

			$this->response->setOutput($this->load->view('error/not_found', $data));
		}
	}
}
