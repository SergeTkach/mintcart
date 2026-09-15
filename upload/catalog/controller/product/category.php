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
			$this->document->setTitle($category_info['meta_title']);
			$this->document->setDescription($category_info['meta_description']);
			$this->document->setKeywords($category_info['meta_keyword']);

			$data['heading_title'] = $category_info['name'];

			$data['text_compare'] = sprintf($this->language->get('text_compare'), (isset($this->session->data['compare']) ? count($this->session->data['compare']) : 0));

			// Set the last category breadcrumb
			$data['breadcrumbs'][] = array(
				'text' => $category_info['name'],
				'href' => $this->url->link('product/category', 'path=' . $this->request->get['path'])
			);

			if ($category_info['image']) {
				$data['thumb'] = $this->model_tool_image->resize($category_info['image'], $this->config->get('theme_' . $this->config->get('config_theme') . '_image_category_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_category_height'));
			} else {
				$data['thumb'] = '';
			}

			$data['description'] = html_entity_decode($category_info['description'], ENT_QUOTES, 'UTF-8');
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

			if ($mint_render_product_cards) {
				$product_card_list_renderer = new \MintCart\Design\ProductCardListRenderer(
					new \MintCart\Design\ComponentRenderer(DIR_TEMPLATE, $mint_theme, 'components', $mint_diagnostics)
				);

				$text_badge = array(
					'sale'         => $this->language->get('text_badge_sale'),
					'out-of-stock' => $this->language->get('text_badge_out_of_stock'),
					'new'          => $this->language->get('text_badge_new')
				);

				// OpenCart's own backorder flag (catalog/controller/checkout/cart.php
				// checks the same setting before blocking an out-of-stock add) -
				// without this, ProductViewProvider would disable add-to-cart for
				// any out-of-stock product even on a store that allows backorders.
				$allow_out_of_stock_purchase = (bool)$this->config->get('config_stock_checkout');

				$data['product_cards'] = array();
			}

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

				if ($this->config->get('config_review_status')) {
					$rating = (int)$result['rating'];
				} else {
					$rating = false;
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

				if ($mint_render_product_cards) {
					$product_card = $product_card_list_renderer->render(
						array(
							'id'                          => $result['product_id'],
							'name'                        => $result['name'],
							'url'                         => $href,
							'image'                       => $image,
							'image_width'                 => $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_width'),
							'image_height'                => $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_height'),
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
							// 1-based: "1st product on this page", not a
							// zero-based array index.
							'listing_type' => 'category',
							'position'     => count($data['products'])
						),
						array(
							'text_badge'       => $text_badge,
							'text_wishlist'    => $this->language->get('button_wishlist'),
							'text_compare'     => $this->language->get('button_compare'),
							'text_rated'       => $this->language->get('text_rated'),
							'text_tax'         => $this->language->get('text_tax'),
							'text_add_to_cart' => $this->language->get('button_cart'),
							'text_quick_order' => $this->language->get('text_quick_order'),
							'text_added_to_cart' => $this->language->get('text_added_to_cart'),
							'text_add_to_cart_error' => $this->language->get('text_add_to_cart_error')
						),
						function (\Throwable $e, array $failed_product) {
							// A broken/missing component template (deploy
							// mistake, permissions, future refactor) must not
							// turn into a fatal error for every visitor on
							// this page - MintCart\Design\ProductCardListRenderer
							// stops attempting the pipeline for the rest of
							// this request and lets the page render without
							// product cards rather than not render at all.
							// The legacy $data['products'] array above is
							// unaffected either way.
							(new \MintCart\Core\Logger())->error('ComponentRenderer failed to render product-card for product_id ' . $failed_product['id'] . ': ' . $e->getMessage());
						}
					);

					if ($product_card !== null) {
						$data['product_cards'][] = $product_card;
					}

					$mint_render_product_cards = $product_card_list_renderer->isEnabled();
				}
			}

			$url = '';

			if (isset($this->request->get['filter'])) {
				$url .= '&filter=' . $this->request->get['filter'];
			}

			if (isset($this->request->get['limit'])) {
				$url .= '&limit=' . $this->request->get['limit'];
			}

			$data['sorts'] = array();

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_default'),
				'value' => 'p.sort_order-ASC',
				'href'  => $this->url->link('product/category', 'path=' . $this->request->get['path'] . '&sort=p.sort_order&order=ASC' . $url)
			);

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_name_asc'),
				'value' => 'pd.name-ASC',
				'href'  => $this->url->link('product/category', 'path=' . $this->request->get['path'] . '&sort=pd.name&order=ASC' . $url)
			);

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_name_desc'),
				'value' => 'pd.name-DESC',
				'href'  => $this->url->link('product/category', 'path=' . $this->request->get['path'] . '&sort=pd.name&order=DESC' . $url)
			);

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_price_asc'),
				'value' => 'p.price-ASC',
				'href'  => $this->url->link('product/category', 'path=' . $this->request->get['path'] . '&sort=p.price&order=ASC' . $url)
			);

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_price_desc'),
				'value' => 'p.price-DESC',
				'href'  => $this->url->link('product/category', 'path=' . $this->request->get['path'] . '&sort=p.price&order=DESC' . $url)
			);

			if ($this->config->get('config_review_status')) {
				$data['sorts'][] = array(
					'text'  => $this->language->get('text_rating_desc'),
					'value' => 'rating-DESC',
					'href'  => $this->url->link('product/category', 'path=' . $this->request->get['path'] . '&sort=rating&order=DESC' . $url)
				);

				$data['sorts'][] = array(
					'text'  => $this->language->get('text_rating_asc'),
					'value' => 'rating-ASC',
					'href'  => $this->url->link('product/category', 'path=' . $this->request->get['path'] . '&sort=rating&order=ASC' . $url)
				);
			}

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_model_asc'),
				'value' => 'p.model-ASC',
				'href'  => $this->url->link('product/category', 'path=' . $this->request->get['path'] . '&sort=p.model&order=ASC' . $url)
			);

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_model_desc'),
				'value' => 'p.model-DESC',
				'href'  => $this->url->link('product/category', 'path=' . $this->request->get['path'] . '&sort=p.model&order=DESC' . $url)
			);

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

			$limits = array_unique(array($this->config->get('theme_' . $this->config->get('config_theme') . '_product_limit'), 25, 50, 75, 100));

			sort($limits);

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

			// http://googlewebmastercentral.blogspot.com/2011/09/pagination-with-relnext-and-relprev.html
			if ($page == 1) {
			    $this->document->addLink($this->url->link('product/category', 'path=' . $category_info['category_id']), 'canonical');
			} else {
				$this->document->addLink($this->url->link('product/category', 'path=' . $category_info['category_id'] . '&page='. $page), 'canonical');
			}
			
			if ($page > 1) {
			    $this->document->addLink($this->url->link('product/category', 'path=' . $category_info['category_id'] . (($page - 2) ? '&page='. ($page - 1) : '')), 'prev');
			}

			if ($limit && ceil($product_total / $limit) > $page) {
			    $this->document->addLink($this->url->link('product/category', 'path=' . $category_info['category_id'] . '&page='. ($page + 1)), 'next');
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
			// visible markup.
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
