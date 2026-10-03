<?php
class ControllerProductSearch extends Controller {
	public function index() {
		$this->load->language('product/search');

		$this->load->model('catalog/category');

		$this->load->model('catalog/product');

		$this->load->model('tool/image');

		if (isset($this->request->get['search'])) {
			$search = $this->request->get['search'];
		} else {
			$search = '';
		}

		if (isset($this->request->get['tag'])) {
			$tag = $this->request->get['tag'];
		} elseif (isset($this->request->get['search'])) {
			$tag = $this->request->get['search'];
		} else {
			$tag = '';
		}

		if (isset($this->request->get['description'])) {
			$description = $this->request->get['description'];
		} else {
			$description = '';
		}

		if (isset($this->request->get['category_id'])) {
			$category_id = $this->request->get['category_id'];
		} else {
			$category_id = 0;
		}

		if (isset($this->request->get['sub_category'])) {
			$sub_category = $this->request->get['sub_category'];
		} else {
			$sub_category = '';
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

		if (isset($this->request->get['search'])) {
			$this->document->setTitle($this->language->get('heading_title') .  ' - ' . $this->request->get['search']);
		} elseif (isset($this->request->get['tag'])) {
			$this->document->setTitle($this->language->get('heading_title') .  ' - ' . $this->language->get('heading_tag') . $this->request->get['tag']);
		} else {
			$this->document->setTitle($this->language->get('heading_title'));
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);

		$url = '';

		if (isset($this->request->get['search'])) {
			$url .= '&search=' . urlencode(html_entity_decode($this->request->get['search'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['tag'])) {
			$url .= '&tag=' . urlencode(html_entity_decode(trim($this->request->get['tag']), ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['description'])) {
			$url .= '&description=' . $this->request->get['description'];
		}

		if (isset($this->request->get['category_id'])) {
			$url .= '&category_id=' . $this->request->get['category_id'];
		}

		if (isset($this->request->get['sub_category'])) {
			$url .= '&sub_category=' . $this->request->get['sub_category'];
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
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('product/search', $url)
		);

		if (isset($this->request->get['search'])) {
			$data['heading_title'] = $this->language->get('heading_title') .  ' - ' . $this->request->get['search'];
		} else {
			$data['heading_title'] = $this->language->get('heading_title');
		}

		$data['text_compare'] = sprintf($this->language->get('text_compare'), (isset($this->session->data['compare']) ? count($this->session->data['compare']) : 0));

		$data['compare'] = $this->url->link('product/compare');

		// mint_default/template/product/search.twig's own refine-search form
		// and empty/sort/limit labels (MINTCART_TECHNICAL_PLAN.md §14). Not
		// business logic - just exposing already-loaded language strings the
		// new Twig template reads, the same way $data['text_compare'] above
		// already does.
		$data['text_search'] = $this->language->get('text_search');
		$data['entry_search'] = $this->language->get('entry_search');
		$data['text_keyword'] = $this->language->get('text_keyword');
		$data['text_category'] = $this->language->get('text_category');
		$data['text_sub_category'] = $this->language->get('text_sub_category');
		$data['entry_description'] = $this->language->get('entry_description');
		$data['button_search'] = $this->language->get('button_search');
		$data['text_sort'] = $this->language->get('text_sort');
		$data['text_limit'] = $this->language->get('text_limit');
		$data['text_empty'] = $this->language->get('text_empty');
		$data['button_continue'] = $this->language->get('button_continue');
		$data['continue'] = $this->url->link('common/home');
		$data['search_performed'] = (isset($this->request->get['search']) || isset($this->request->get['tag']));

		// 3 Level Category Search
		$data['categories'] = array();

		$categories_1 = $this->model_catalog_category->getCategories(0);

		foreach ($categories_1 as $category_1) {
			$level_2_data = array();

			$categories_2 = $this->model_catalog_category->getCategories($category_1['category_id']);

			foreach ($categories_2 as $category_2) {
				$level_3_data = array();

				$categories_3 = $this->model_catalog_category->getCategories($category_2['category_id']);

				foreach ($categories_3 as $category_3) {
					$level_3_data[] = array(
						'category_id' => $category_3['category_id'],
						'name'        => $category_3['name'],
					);
				}

				$level_2_data[] = array(
					'category_id' => $category_2['category_id'],
					'name'        => $category_2['name'],
					'children'    => $level_3_data
				);
			}

			$data['categories'][] = array(
				'category_id' => $category_1['category_id'],
				'name'        => $category_1['name'],
				'children'    => $level_2_data
			);
		}

		$data['products'] = array();

		// MintCart\Seo\RobotsBuilder (MINTCART_FUNCTIONAL_SCOPE.md §16,
		// Phase 18) - "did this search actually match anything" for the
		// registry bridge below. Defaults to true (the RobotsBuilder
		// default too) for a bare `product/search` request with no
		// `search`/`tag` param at all, which never runs the query below -
		// that's the plain search form, not a "no results" page.
		$mint_search_has_results = true;

		// MintCart\Design\ProductCardListRenderer (which composes
		// MintCart\Commerce\ProductViewProvider + ComponentRenderer) wiring
		// (MINTCART_TECHNICAL_PLAN.md §11/§15C-D) - the second real listing
		// consumer after Category (Phase 8), sharing the same pipeline
		// rather than duplicating it (CLAUDE.md §8A). Only built when the
		// active theme ships a theme.json manifest (i.e. it is
		// Mint-API-aware), same gate category.php/footer.php already use -
		// see docs/upstream-diff.md. The controller's original
		// $data['products'] array above is completely unaffected either way.
		$mint_theme = \MintCart\Design\ActiveTheme::directory($this->config->get('config_theme'), $this->config->get('theme_default_directory'));
		$mint_render_product_cards = \MintCart\Design\ActiveTheme::isMintAware(DIR_TEMPLATE, $mint_theme);

		// MintCart\Core\Diagnostics (MINTCART_TECHNICAL_PLAN.md §35,
		// MINTCART_FUNCTIONAL_SCOPE.md §19 "render time") - same
		// category.php-only wiring extended to Search, the second listing
		// controller to carry it. Off by default; see category.php's
		// identical comment and docs/developer/core/diagnostics.md.
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

			// OpenCart's own backorder flag - see category.php's identical
			// comment and docs/developer/commerce/product-view-provider.md.
			$allow_out_of_stock_purchase = (bool)$this->config->get('config_stock_checkout');

			$data['product_cards'] = array();
		}

		if (isset($this->request->get['search']) || isset($this->request->get['tag'])) {
			$filter_data = array(
				'filter_name'         => $search,
				'filter_tag'          => $tag,
				'filter_description'  => $description,
				'filter_category_id'  => $category_id,
				'filter_sub_category' => $sub_category,
				'sort'                => $sort,
				'order'               => $order,
				'start'               => ($page - 1) * $limit,
				'limit'               => $limit
			);

			$product_total = $this->model_catalog_product->getTotalProducts($filter_data);

			$mint_search_has_results = ($product_total > 0);

			$results = $this->model_catalog_product->getProducts($filter_data);

			foreach ($results as $result) {
				if ($result['image']) {
					$image = $this->model_tool_image->resize($result['image'], $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_height'));
				} else {
					$image = $this->model_tool_image->resize('placeholder.png', $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_height'));
				}

				// 2x variant for a high-DPI srcset - see category.php's
				// identical comment (docs/developer/commerce/product-view-provider.md
				// "Image dimensions").
				$image_2x = $this->model_tool_image->resize($result['image'] ? $result['image'] : 'placeholder.png', $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_width') * 2, $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_height') * 2);
				$image_srcset = $image . ' 1x, ' . $image_2x . ' 2x';

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

				$href = $this->url->link('product/product', 'product_id=' . $result['product_id'] . $url);

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
							'image_srcset'                => $image_srcset,
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
							'listing_type' => 'search',
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

			if (isset($this->request->get['search'])) {
				$url .= '&search=' . urlencode(html_entity_decode($this->request->get['search'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['tag'])) {
				$url .= '&tag=' . urlencode(html_entity_decode($this->request->get['tag'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['description'])) {
				$url .= '&description=' . $this->request->get['description'];
			}

			if (isset($this->request->get['category_id'])) {
				$url .= '&category_id=' . $this->request->get['category_id'];
			}

			if (isset($this->request->get['sub_category'])) {
				$url .= '&sub_category=' . $this->request->get['sub_category'];
			}

			if (isset($this->request->get['limit'])) {
				$url .= '&limit=' . $this->request->get['limit'];
			}

			$data['sorts'] = array();

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_default'),
				'value' => 'p.sort_order-ASC',
				'href'  => $this->url->link('product/search', 'sort=p.sort_order&order=ASC' . $url)
			);

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_name_asc'),
				'value' => 'pd.name-ASC',
				'href'  => $this->url->link('product/search', 'sort=pd.name&order=ASC' . $url)
			);

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_name_desc'),
				'value' => 'pd.name-DESC',
				'href'  => $this->url->link('product/search', 'sort=pd.name&order=DESC' . $url)
			);

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_price_asc'),
				'value' => 'p.price-ASC',
				'href'  => $this->url->link('product/search', 'sort=p.price&order=ASC' . $url)
			);

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_price_desc'),
				'value' => 'p.price-DESC',
				'href'  => $this->url->link('product/search', 'sort=p.price&order=DESC' . $url)
			);

			if ($this->config->get('config_review_status')) {
				$data['sorts'][] = array(
					'text'  => $this->language->get('text_rating_desc'),
					'value' => 'rating-DESC',
					'href'  => $this->url->link('product/search', 'sort=rating&order=DESC' . $url)
				);

				$data['sorts'][] = array(
					'text'  => $this->language->get('text_rating_asc'),
					'value' => 'rating-ASC',
					'href'  => $this->url->link('product/search', 'sort=rating&order=ASC' . $url)
				);
			}

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_model_asc'),
				'value' => 'p.model-ASC',
				'href'  => $this->url->link('product/search', 'sort=p.model&order=ASC' . $url)
			);

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_model_desc'),
				'value' => 'p.model-DESC',
				'href'  => $this->url->link('product/search', 'sort=p.model&order=DESC' . $url)
			);

			$url = '';

			if (isset($this->request->get['search'])) {
				$url .= '&search=' . urlencode(html_entity_decode($this->request->get['search'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['tag'])) {
				$url .= '&tag=' . urlencode(html_entity_decode($this->request->get['tag'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['description'])) {
				$url .= '&description=' . $this->request->get['description'];
			}

			if (isset($this->request->get['category_id'])) {
				$url .= '&category_id=' . $this->request->get['category_id'];
			}

			if (isset($this->request->get['sub_category'])) {
				$url .= '&sub_category=' . $this->request->get['sub_category'];
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
					'href'  => $this->url->link('product/search', $url . '&limit=' . $value)
				);
			}

			$url = '';

			if (isset($this->request->get['search'])) {
				$url .= '&search=' . urlencode(html_entity_decode($this->request->get['search'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['tag'])) {
				$url .= '&tag=' . urlencode(html_entity_decode($this->request->get['tag'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['description'])) {
				$url .= '&description=' . $this->request->get['description'];
			}

			if (isset($this->request->get['category_id'])) {
				$url .= '&category_id=' . $this->request->get['category_id'];
			}

			if (isset($this->request->get['sub_category'])) {
				$url .= '&sub_category=' . $this->request->get['sub_category'];
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
			$pagination->url = $this->url->link('product/search', $url . '&page={page}');

			$data['pagination'] = $pagination->render();

			$data['results'] = sprintf($this->language->get('text_pagination'), ($product_total) ? (($page - 1) * $limit) + 1 : 0, ((($page - 1) * $limit) > ($product_total - $limit)) ? $product_total : ((($page - 1) * $limit) + $limit), $product_total, ceil($product_total / $limit));

			// MC-0002: search results have no canonical/prev/next <link> tags
			// in stock OpenCart 3.0.5.1 (category.php/manufacturer.php/
			// special.php all have this, search.php never did) - see
			// docs/fixes/registry.md. Mirrors those controllers' identical
			// pattern: only the result-defining params (search/tag/
			// description/category_id/sub_category) go into the canonical
			// URL, not display-only params (sort/order/limit), so different
			// sort orders of the same search canonicalize to one URL.
			$canonical_url = '';

			if (isset($this->request->get['search'])) {
				$canonical_url .= '&search=' . urlencode(html_entity_decode($this->request->get['search'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['tag'])) {
				$canonical_url .= '&tag=' . urlencode(html_entity_decode($this->request->get['tag'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['description'])) {
				$canonical_url .= '&description=' . $this->request->get['description'];
			}

			if (isset($this->request->get['category_id'])) {
				$canonical_url .= '&category_id=' . $this->request->get['category_id'];
			}

			if (isset($this->request->get['sub_category'])) {
				$canonical_url .= '&sub_category=' . $this->request->get['sub_category'];
			}

			// MintCart\Seo\CanonicalUrlBuilder (MINTCART_FUNCTIONAL_SCOPE.md
			// §16, MINTCART_TECHNICAL_PLAN.md §31, Phase 18) - shared, tested
			// primitive replacing the inline http://googlewebmastercentral.
			// blogspot.com/2011/09/pagination-with-relnext-and-relprev.html
			// block duplicated across category.php/manufacturer.php/
			// search.php/special.php (MC-0002's fix above still owns which
			// params belong in $canonical_url; this only owns the
			// page-aware canonical/prev/next variants of it). Canonical/
			// prev/next links are a per-route SEO signal, not a Mint
			// component DOM contract, so this runs unconditionally
			// regardless of active theme, matching RobotsBuilder's wiring
			// in common/header.php.
			try {
				$canonical_builder = new \MintCart\Seo\CanonicalUrlBuilder();
				$canonical_links = $canonical_builder->build($page, (int)$limit, (int)$product_total, $canonical_url);

				$this->document->addLink($this->url->link('product/search', $canonical_links['canonical']), 'canonical');

				if ($canonical_links['prev'] !== null) {
					$this->document->addLink($this->url->link('product/search', $canonical_links['prev']), 'prev');
				}

				if ($canonical_links['next'] !== null) {
					$this->document->addLink($this->url->link('product/search', $canonical_links['next']), 'next');
				}
			} catch (\Throwable $e) {
				(new \MintCart\Core\Logger())->error('CanonicalUrlBuilder failed to build pagination links: ' . $e->getMessage());
			}

			if (isset($this->request->get['search']) && $this->config->get('config_customer_search')) {
				$this->load->model('account/search');

				if ($this->customer->isLogged()) {
					$customer_id = $this->customer->getId();
				} else {
					$customer_id = 0;
				}

				if (isset($this->request->server['REMOTE_ADDR'])) {
					$ip = $this->request->server['REMOTE_ADDR'];
				} else {
					$ip = '';
				}

				$search_data = array(
					'keyword'       => $search,
					'category_id'   => $category_id,
					'sub_category'  => $sub_category,
					'description'   => $description,
					'products'      => $product_total,
					'customer_id'   => $customer_id,
					'ip'            => $ip
				);

				$this->model_account_search->addSearch($search_data);
			}
		}

		$data['search'] = $search;
		$data['description'] = $description;
		$data['category_id'] = $category_id;
		$data['sub_category'] = $sub_category;

		$data['sort'] = $sort;
		$data['order'] = $order;
		$data['limit'] = $limit;

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');

		// MintCart\Seo\RobotsBuilder (MINTCART_FUNCTIONAL_SCOPE.md §16,
		// Phase 18) - registry bridge closing the "Known limitation"
		// docs/developer/seo/robots.md documented: common/header.php runs
		// as our sibling sub-controller (loaded below, after this search
		// has already run), with no other way to learn whether this
		// specific search matched anything. See RobotsBuilder::
		// HAS_RESULTS_REGISTRY_KEY's own docblock for why this is a plain
		// registry value, not a new table/session/event.
		$this->registry->set(\MintCart\Seo\RobotsBuilder::HAS_RESULTS_REGISTRY_KEY, $mint_search_has_results);

		$data['header'] = $this->load->controller('common/header');

		// MintCart\Seo\SchemaOrgBuilder BreadcrumbList JSON-LD
		// (MINTCART_FUNCTIONAL_SCOPE.md §16, Phase 18). Re-checks
		// Mint-awareness directly (not $mint_render_product_cards, which
		// above this point may have been flipped to false by
		// ProductCardListRenderer::isEnabled() after an unrelated
		// per-product render failure) - the breadcrumb list is independent
		// of whether product-card rendering succeeded. $data['breadcrumbs']
		// is complete by this point (built at the top of this method).
		if (\MintCart\Design\ActiveTheme::isMintAware(DIR_TEMPLATE, $mint_theme)) {
			try {
				$schema_builder = new \MintCart\Seo\SchemaOrgBuilder();

				$data['mc_schema_breadcrumb'] = $schema_builder->toJsonLd($schema_builder->breadcrumbList($data['breadcrumbs']));
			} catch (\Throwable $e) {
				(new \MintCart\Core\Logger())->error('SchemaOrgBuilder failed to build BreadcrumbList schema: ' . $e->getMessage());
			}
		}

		$mint_diagnostics->mark('view_rendered');

		$output = $this->load->view('product/search', $data);

		// Functional Scope §19: "Production UI не повинен показувати
		// sensitive debug data" - see category.php's identical comment,
		// including the `&mintcart_diagnostics=json` alternative surface.
		if ($mint_diagnostics->isEnabled() && isset($this->request->get['mintcart_diagnostics']) && $this->request->get['mintcart_diagnostics'] === 'json') {
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($mint_diagnostics->summary()));

			return;
		}

		if ($mint_diagnostics->isEnabled()) {
			$output .= "\n<!-- mintcart-diagnostics " . json_encode($mint_diagnostics->summary()) . " -->";
		}

		$this->response->setOutput($output);
	}
}
