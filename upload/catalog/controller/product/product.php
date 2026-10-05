<?php
class ControllerProductProduct extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('product/product');

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);

		$this->load->model('catalog/category');

		if (isset($this->request->get['path'])) {
			$path = '';

			$parts = explode('_', (string)$this->request->get['path']);

			$category_id = (int)array_pop($parts);

			foreach ($parts as $path_id) {
				if (!$path) {
					$path = $path_id;
				} else {
					$path .= '_' . $path_id;
				}

				$category_info = $this->model_catalog_category->getCategory($path_id);

				if ($category_info) {
					$data['breadcrumbs'][] = array(
						'text' => $category_info['name'],
						'href' => $this->url->link('product/category', 'path=' . $path)
					);
				}
			}

			// Set the last category breadcrumb
			$category_info = $this->model_catalog_category->getCategory($category_id);

			if ($category_info) {
				$url = '';

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
					'text' => $category_info['name'],
					'href' => $this->url->link('product/category', 'path=' . $this->request->get['path'] . $url)
				);
			}
		}

		$this->load->model('catalog/manufacturer');

		if (isset($this->request->get['manufacturer_id'])) {
			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('text_brand'),
				'href' => $this->url->link('product/manufacturer')
			);

			$url = '';

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

			$manufacturer_info = $this->model_catalog_manufacturer->getManufacturer($this->request->get['manufacturer_id']);

			if ($manufacturer_info) {
				$data['breadcrumbs'][] = array(
					'text' => $manufacturer_info['name'],
					'href' => $this->url->link('product/manufacturer/info', 'manufacturer_id=' . $this->request->get['manufacturer_id'] . $url)
				);
			}
		}

		if (isset($this->request->get['search']) || isset($this->request->get['tag'])) {
			$url = '';

			if (isset($this->request->get['search'])) {
				$url .= '&search=' . $this->request->get['search'];
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
				'text' => $this->language->get('text_search'),
				'href' => $this->url->link('product/search', $url)
			);
		}

		if (isset($this->request->get['product_id'])) {
			$product_id = (int)$this->request->get['product_id'];
		} else {
			$product_id = 0;
		}

		$this->load->model('catalog/product');

		$product_info = $this->model_catalog_product->getProduct($product_id);

		//check product page open from cateory page
		if (isset($this->request->get['path'])) {
			$parts = explode('_', (string)$this->request->get['path']);
						
			if(empty($this->model_catalog_product->checkProductCategory($product_id, $parts))) {
				$product_info = array();
			}
		}

		//check product page open from manufacturer page
		if (isset($this->request->get['manufacturer_id']) && !empty($product_info)) {
			if($product_info['manufacturer_id'] !=  $this->request->get['manufacturer_id']) {
				$product_info = array();
			}
		}

		if ($product_info) {
			$url = '';

			if (isset($this->request->get['path'])) {
				$url .= '&path=' . $this->request->get['path'];
			}

			if (isset($this->request->get['filter'])) {
				$url .= '&filter=' . $this->request->get['filter'];
			}

			if (isset($this->request->get['manufacturer_id'])) {
				$url .= '&manufacturer_id=' . $this->request->get['manufacturer_id'];
			}

			if (isset($this->request->get['search'])) {
				$url .= '&search=' . $this->request->get['search'];
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
				'text' => $product_info['name'],
				'href' => $this->url->link('product/product', $url . '&product_id=' . $this->request->get['product_id'])
			);

			// MintCart\Seo\MetaBuilder (MINTCART_FUNCTIONAL_SCOPE.md §16,
			// Phase 18, MC-0016): a blank "Meta Tag Title" falls back to the
			// product name. The optional fourth argument is omitted
			// deliberately: a blank meta description stays blank, not a
			// copy of the title. Mirrors category.php/home.php's MC-0015
			// wiring. See docs/developer/seo/meta-builder.md, "Live wiring".
			// Pure string logic, cannot throw - no try/catch needed.
			$mint_meta = (new \MintCart\Seo\MetaBuilder())->build(
				$product_info['meta_title'],
				$product_info['meta_description'],
				$product_info['name']
			);

			$this->document->setTitle($mint_meta['title']);
			$this->document->setDescription($mint_meta['description']);
			$this->document->setKeywords($product_info['meta_keyword']);
			$this->document->addLink($this->url->link('product/product', 'product_id=' . $this->request->get['product_id']), 'canonical');
			$this->document->addScript('catalog/view/javascript/jquery/magnific/jquery.magnific-popup.min.js');
			$this->document->addStyle('catalog/view/javascript/jquery/magnific/magnific-popup.css');
			$this->document->addScript('catalog/view/javascript/jquery/datetimepicker/moment/moment.min.js');
			$this->document->addScript('catalog/view/javascript/jquery/datetimepicker/moment/moment-with-locales.min.js');
			$this->document->addScript('catalog/view/javascript/jquery/datetimepicker/bootstrap-datetimepicker.min.js');
			$this->document->addStyle('catalog/view/javascript/jquery/datetimepicker/bootstrap-datetimepicker.min.css');

			$data['heading_title'] = $product_info['name'];

			$data['text_minimum'] = sprintf($this->language->get('text_minimum'), $product_info['minimum']);
			$data['text_login'] = sprintf($this->language->get('text_login'), $this->url->link('account/login', '', true), $this->url->link('account/register', '', true));

			// mint_default/template/product/product.twig's own hero section
			// (wishlist/compare/add-to-cart/tax label) reads these directly -
			// the related-products grid further down needs the exact same
			// strings but passes its own copy straight into
			// ProductCardListRenderer::render() rather than $data (that
			// array feeds ComponentRenderer, not this page template).
			$data['text_tax'] = $this->language->get('text_tax');
			$data['text_wishlist'] = $this->language->get('button_wishlist');
			$data['text_compare'] = $this->language->get('button_compare');
			$data['text_add_to_cart'] = $this->language->get('button_cart');
			$data['text_rated'] = $this->language->get('text_rated');
			$data['text_badge_sale'] = $this->language->get('text_badge_sale');
			$data['text_badge_out_of_stock'] = $this->language->get('text_badge_out_of_stock');
			$data['text_added_to_cart'] = $this->language->get('text_added_to_cart');
			$data['text_add_to_cart_error'] = $this->language->get('text_add_to_cart_error');
			$data['text_wishlist_error'] = $this->language->get('text_wishlist_error');

			// Plain field labels product/product.php's own language file
			// already defines but this controller never assigned to $data
			// for any theme - the same pre-existing gap
			// docs/upstream-diff.md's search.php entry already documented
			// for category.twig/search.twig ("silently renders several
			// language-string variables as blank because the controller
			// never assigns them"). mint_default/product.twig is a brand
			// new template with no prior broken behavior to preserve, so
			// this run supplies the missing values it needs rather than
			// repeating the same blank-label gap in a template being
			// written from scratch. Purely additive $data keys - the
			// legacy default theme's own template is completely unaffected
			// either way.
			$data['text_manufacturer'] = $this->language->get('text_manufacturer');
			$data['text_model'] = $this->language->get('text_model');
			$data['text_reward'] = $this->language->get('text_reward');
			$data['text_stock'] = $this->language->get('text_stock');
			$data['text_points'] = $this->language->get('text_points');
			$data['text_discount'] = $this->language->get('text_discount');
			$data['text_option'] = $this->language->get('text_option');
			$data['text_related'] = $this->language->get('text_related');
			// mc-viewed-products (MINTCART_FUNCTIONAL_SCOPE.md §11) heading
			// label - the strip's items themselves come from
			// mintcart/viewed/products (JSON) and are rendered client-side,
			// same split product_cards/text_related already uses above.
			$data['text_viewed'] = $this->language->get('text_viewed');
			$data['text_tags'] = $this->language->get('text_tags');
			$data['tab_description'] = $this->language->get('tab_description');
			$data['tab_attribute'] = $this->language->get('tab_attribute');
			$data['text_no_reviews'] = $this->language->get('text_no_reviews');
			$data['entry_qty'] = $this->language->get('entry_qty');

			$this->load->model('catalog/review');

			$data['tab_review'] = sprintf($this->language->get('tab_review'), $product_info['reviews']);

			$data['product_id'] = (int)$this->request->get['product_id'];
			$data['manufacturer'] = $product_info['manufacturer'];
			$data['manufacturers'] = $this->url->link('product/manufacturer/info', 'manufacturer_id=' . $product_info['manufacturer_id']);
			$data['model'] = $product_info['model'];
			$data['reward'] = $product_info['reward'];
			$data['points'] = $product_info['points'];
			$data['description'] = html_entity_decode($product_info['description'], ENT_QUOTES, 'UTF-8');

			if ($product_info['quantity'] <= 0) {
				$data['stock'] = $product_info['stock_status'];
			} elseif ($this->config->get('config_stock_display')) {
				$data['stock'] = $product_info['quantity'];
			} else {
				$data['stock'] = $this->language->get('text_instock');
			}

			$this->load->model('tool/image');

			if ($product_info['image']) {
				$data['popup'] = $this->model_tool_image->resize($product_info['image'], $this->config->get('theme_' . $this->config->get('config_theme') . '_image_popup_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_popup_height'));
			} else {
				$data['popup'] = '';
			}

			// MintCart: width/height alongside each resized image so
			// product.twig can render explicit <img width>/<height>
			// (Functional Scope §19 "responsive images; width/height" —
			// prevents layout shift while the gallery loads), same
			// convention ProductViewProvider already uses for
			// product.image_width/product.image_height on the listing
			// product card.
			$data['thumb_width'] = (int)$this->config->get('theme_' . $this->config->get('config_theme') . '_image_thumb_width');
			$data['thumb_height'] = (int)$this->config->get('theme_' . $this->config->get('config_theme') . '_image_thumb_height');

			if ($product_info['image']) {
				$data['thumb'] = $this->model_tool_image->resize($product_info['image'], $data['thumb_width'], $data['thumb_height']);
			} else {
				$data['thumb'] = '';
			}

			$data['images'] = array();

			$results = $this->model_catalog_product->getProductImages($this->request->get['product_id']);

			$additional_width = (int)$this->config->get('theme_' . $this->config->get('config_theme') . '_image_additional_width');
			$additional_height = (int)$this->config->get('theme_' . $this->config->get('config_theme') . '_image_additional_height');

			foreach ($results as $result) {
				$data['images'][] = array(
					'popup'        => $this->model_tool_image->resize($result['image'], $this->config->get('theme_' . $this->config->get('config_theme') . '_image_popup_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_popup_height')),
					'thumb'        => $this->model_tool_image->resize($result['image'], $additional_width, $additional_height),
					'thumb_width'  => $additional_width,
					'thumb_height' => $additional_height
				);
			}

			if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
				$data['price'] = $this->currency->format($this->tax->calculate($product_info['price'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
			} else {
				$data['price'] = false;
			}

			if (!is_null($product_info['special']) && (float)$product_info['special'] >= 0) {
				$data['special'] = $this->currency->format($this->tax->calculate($product_info['special'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
				$tax_price = (float)$product_info['special'];
			} else {
				$data['special'] = false;
				$tax_price = (float)$product_info['price'];
			}

			if ($this->config->get('config_tax')) {
				$data['tax'] = $this->currency->format($tax_price, $this->session->data['currency']);
			} else {
				$data['tax'] = false;
			}

			$discounts = $this->model_catalog_product->getProductDiscounts($this->request->get['product_id']);

			$data['discounts'] = array();

			foreach ($discounts as $discount) {
				$data['discounts'][] = array(
					'quantity' => $discount['quantity'],
					'price'    => $this->currency->format($this->tax->calculate($discount['price'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency'])
				);
			}

			$data['options'] = array();

			foreach ($this->model_catalog_product->getProductOptions($this->request->get['product_id']) as $option) {
				$product_option_value_data = array();

				foreach ($option['product_option_value'] as $option_value) {
					if (!$option_value['subtract'] || ($option_value['quantity'] > 0)) {
						if ((($this->config->get('config_customer_price') && $this->customer->isLogged()) || !$this->config->get('config_customer_price')) && (float)$option_value['price']) {
							$price = $this->currency->format($this->tax->calculate($option_value['price'], $product_info['tax_class_id'], $this->config->get('config_tax') ? 'P' : false), $this->session->data['currency']);
						} else {
							$price = false;
						}

						$product_option_value_data[] = array(
							'product_option_value_id' => $option_value['product_option_value_id'],
							'option_value_id'         => $option_value['option_value_id'],
							'name'                    => $option_value['name'],
							'image'                   => $this->model_tool_image->resize($option_value['image'], 50, 50),
							'price'                   => $price,
							'price_prefix'            => $option_value['price_prefix']
						);
					}
				}

				$data['options'][] = array(
					'product_option_id'    => $option['product_option_id'],
					'product_option_value' => $product_option_value_data,
					'option_id'            => $option['option_id'],
					'name'                 => $option['name'],
					'type'                 => $option['type'],
					'value'                => $option['value'],
					'required'             => $option['required']
				);
			}

			if ($product_info['minimum']) {
				$data['minimum'] = $product_info['minimum'];
			} else {
				$data['minimum'] = 1;
			}

			$data['review_status'] = $this->config->get('config_review_status');

			if ($this->config->get('config_review_guest') || $this->customer->isLogged()) {
				$data['review_guest'] = true;
			} else {
				$data['review_guest'] = false;
			}

			if ($this->customer->isLogged()) {
				$data['customer_name'] = $this->customer->getFirstName() . '&nbsp;' . $this->customer->getLastName();
			} else {
				$data['customer_name'] = '';
			}

			$data['reviews'] = sprintf($this->language->get('text_reviews'), (int)$product_info['reviews']);
			$data['rating'] = (int)$product_info['rating'];

			// Captcha
			if ($this->config->get('captcha_' . $this->config->get('config_captcha') . '_status') && in_array('review', (array)$this->config->get('config_captcha_page'))) {
				$data['captcha'] = $this->load->controller('extension/captcha/' . $this->config->get('config_captcha'));
			} else {
				$data['captcha'] = '';
			}

			$data['share'] = $this->url->link('product/product', 'product_id=' . (int)$this->request->get['product_id']);

			$data['attribute_groups'] = $this->model_catalog_product->getProductAttributes($this->request->get['product_id']);

			$data['products'] = array();

			$results = $this->model_catalog_product->getProductRelated($this->request->get['product_id']);

			// MintCart\Design\ProductCardListRenderer wiring
			// (MINTCART_TECHNICAL_PLAN.md §11/§15C-D, Phase 8) for the
			// "Related Products" list below - same gate/pattern
			// ControllerProductCategory/ControllerProductSearch already use.
			// The main product itself is rendered directly from $data
			// further down (image/price/options/etc.), not through
			// ProductViewProvider - that contract is shaped for a
			// listing-grid card, not a full product page's richer hero
			// section (gallery, options, discounts, attributes), and
			// mint_default/product.twig reads $data directly the same way
			// category.twig reads $data['thumb']/$data['description'] for
			// the category's own hero block.
			$mint_theme = \MintCart\Design\ActiveTheme::directory($this->config->get('config_theme'), $this->config->get('theme_default_directory'));
			$mint_render_product_cards = \MintCart\Design\ActiveTheme::isMintAware(DIR_TEMPLATE, $mint_theme);

			// MintCart\Core\Diagnostics (MINTCART_TECHNICAL_PLAN.md §35,
			// MINTCART_FUNCTIONAL_SCOPE.md §19 "render time") - same
			// category.php/search.php/special.php/manufacturer.php wiring,
			// extended here to product.php's related-products
			// ComponentRenderer call site (the one listing-grid context
			// that still built a bare ComponentRenderer). Off by default;
			// see category.php's identical comment and
			// docs/developer/core/diagnostics.md.
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

				// Same backorder flag ControllerProductCategory/Search
				// already resolve before calling ProductViewProvider.
				$allow_out_of_stock_purchase = (bool)$this->config->get('config_stock_checkout');

				$data['product_cards'] = array();
			}

			foreach ($results as $result) {
				if ($result['image']) {
					$image = $this->model_tool_image->resize($result['image'], $this->config->get('theme_' . $this->config->get('config_theme') . '_image_related_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_related_height'));
				} else {
					$image = $this->model_tool_image->resize('placeholder.png', $this->config->get('theme_' . $this->config->get('config_theme') . '_image_related_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_related_height'));
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

				$related_href = $this->url->link('product/product', 'product_id=' . $result['product_id']);

				$data['products'][] = array(
					'product_id'  => $result['product_id'],
					'thumb'       => $image,
					'name'        => $result['name'],
					'description' => utf8_substr(trim(strip_tags(html_entity_decode($result['description'], ENT_QUOTES, 'UTF-8'))), 0, $this->config->get('theme_' . $this->config->get('config_theme') . '_product_description_length')) . '..',
					'price'       => $price,
					'special'     => $special,
					'tax'         => $tax,
					'minimum'     => $result['minimum'] > 0 ? $result['minimum'] : 1,
					'rating'      => $rating,
					'href'        => $related_href
				);

				if ($mint_render_product_cards) {
					$product_card = $product_card_list_renderer->render(
						array(
							'id'                          => $result['product_id'],
							'name'                        => $result['name'],
							'url'                         => $related_href,
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
							'listing_type' => 'related',
							'position'     => count($data['products'])
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
							'text_add_to_cart_error' => $this->language->get('text_add_to_cart_error'),
							'text_wishlist_error'    => $this->language->get('text_wishlist_error')
						),
						function (\Throwable $e, array $failed_product) {
							// Same "log and disable for the rest of this
							// request" degrade behavior every other
							// ProductCardListRenderer call site uses - a
							// broken related-product card must not fatal
							// the whole product page.
							(new \MintCart\Core\Logger())->error('ComponentRenderer failed to render product-card for related product_id ' . $failed_product['id'] . ': ' . $e->getMessage());
						}
					);

					if ($product_card !== null) {
						$data['product_cards'][] = $product_card;
					}

					$mint_render_product_cards = $product_card_list_renderer->isEnabled();
				}
			}

			$data['tags'] = array();

			if ($product_info['tag']) {
				$tags = explode(',', $product_info['tag']);

				foreach ($tags as $tag) {
					$data['tags'][] = array(
						'tag'  => trim($tag),
						'href' => $this->url->link('product/search', 'tag=' . urlencode(html_entity_decode(trim($tag), ENT_QUOTES, 'UTF-8')))
					);
				}
			}

			$data['recurrings'] = $this->model_catalog_product->getProfiles($this->request->get['product_id']);

			$this->model_catalog_product->updateViewed($this->request->get['product_id']);

			$data['column_left'] = $this->load->controller('common/column_left');
			$data['column_right'] = $this->load->controller('common/column_right');
			$data['content_top'] = $this->load->controller('common/content_top');
			$data['content_bottom'] = $this->load->controller('common/content_bottom');
			$data['footer'] = $this->load->controller('common/footer');
			$data['header'] = $this->load->controller('common/header');

			// MintCart\Seo\SchemaOrgBuilder Product JSON-LD
			// (MINTCART_FUNCTIONAL_SCOPE.md §16, Phase 18). Re-checks
			// Mint-awareness directly (not $mint_render_product_cards,
			// which above this point may have been flipped to false by
			// ProductCardListRenderer::isEnabled() after an unrelated
			// related-product render failure) - the product schema is
			// independent of whether the related-products cards
			// rendered, same reasoning as category.php/search.php's
			// BreadcrumbList wiring.
			if (\MintCart\Design\ActiveTheme::isMintAware(DIR_TEMPLATE, $mint_theme)) {
				try {
					// Raw numeric price (not currency-formatted, not the
					// tax-inclusive display string in $data['price']/
					// $data['tax']) - same special-overrides-price rule
					// $data['price']/$data['special'] above already
					// apply, and the same "false means hidden from this
					// customer group" convention ProductViewProvider and
					// SchemaOrgBuilder::product()'s own contract use.
					if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
						$mint_schema_price = (!is_null($product_info['special']) && (float)$product_info['special'] >= 0)
							? (float)$product_info['special']
							: (float)$product_info['price'];
					} else {
						$mint_schema_price = false;
					}

					$schema_builder = new \MintCart\Seo\SchemaOrgBuilder();

					$data['mc_schema_product'] = $schema_builder->toJsonLd($schema_builder->product(array(
						'name'         => $product_info['name'],
						'url'          => $data['share'],
						'image'        => $data['popup'],
						'description'  => trim(strip_tags($data['description'])),
						'sku'          => $product_info['sku'],
						'brand'        => $product_info['manufacturer'],
						'price'        => $mint_schema_price,
						'currency'     => $this->session->data['currency'],
						'in_stock'     => (float)$product_info['quantity'] > 0,
						'rating_value' => $product_info['rating'],
						'rating_count' => $product_info['reviews']
					)));
				} catch (\Throwable $e) {
					(new \MintCart\Core\Logger())->error('SchemaOrgBuilder failed to build Product schema: ' . $e->getMessage());
				}

				// MintCart\Seo\SchemaOrgBuilder BreadcrumbList JSON-LD
				// (MINTCART_FUNCTIONAL_SCOPE.md §16, Phase 18) - the wiring
				// the comment above already anticipated ("same reasoning as
				// category.php/search.php's BreadcrumbList wiring") but
				// never added. $data['breadcrumbs'] is complete by this
				// point (built at the top of this method and appended to
				// throughout, same as category.php/search.php/
				// manufacturer.php/special.php), same try/catch-and-log
				// pattern as the Product schema block just above so a
				// malformed value can't fatal the page. A fresh builder
				// instance is used (not the Product schema block's
				// `$schema_builder`) so this block never depends on that
				// block having reached its own `new` statement first.
				try {
					$breadcrumb_schema_builder = new \MintCart\Seo\SchemaOrgBuilder();

					$data['mc_schema_breadcrumb'] = $breadcrumb_schema_builder->toJsonLd($breadcrumb_schema_builder->breadcrumbList($data['breadcrumbs']));
				} catch (\Throwable $e) {
					(new \MintCart\Core\Logger())->error('SchemaOrgBuilder failed to build BreadcrumbList schema: ' . $e->getMessage());
				}
			}

			$mint_diagnostics->mark('view_rendered');

			$output = $this->load->view('product/product', $data);

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
		} else {
			$url = '';

			if (isset($this->request->get['path'])) {
				$url .= '&path=' . $this->request->get['path'];
			}

			if (isset($this->request->get['filter'])) {
				$url .= '&filter=' . $this->request->get['filter'];
			}

			if (isset($this->request->get['manufacturer_id'])) {
				$url .= '&manufacturer_id=' . $this->request->get['manufacturer_id'];
			}

			if (isset($this->request->get['search'])) {
				$url .= '&search=' . $this->request->get['search'];
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
				'text' => $this->language->get('text_error'),
				'href' => $this->url->link('product/product', $url . '&product_id=' . $product_id)
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

	public function review() {
		$this->load->language('product/product');

		$this->load->model('catalog/review');

		if (isset($this->request->get['page'])) {
			$page = (int)$this->request->get['page'];
		} else {
			$page = 1;
		}

		$data['reviews'] = array();

		$review_total = $this->model_catalog_review->getTotalReviewsByProductId($this->request->get['product_id']);

		$results = $this->model_catalog_review->getReviewsByProductId($this->request->get['product_id'], ($page - 1) * 5, 5);

		foreach ($results as $result) {
			$data['reviews'][] = array(
				'author'     => $result['author'],
				'text'       => nl2br($result['text']),
				'rating'     => (int)$result['rating'],
				'date_added' => date($this->language->get('date_format_short'), strtotime($result['date_added']))
			);
		}

		$pagination = new Pagination();
		$pagination->total = $review_total;
		$pagination->page = $page;
		$pagination->limit = 5;
		$pagination->url = $this->url->link('product/product/review', 'product_id=' . $this->request->get['product_id'] . '&page={page}');

		$data['pagination'] = $pagination->render();

		$data['results'] = sprintf($this->language->get('text_pagination'), ($review_total) ? (($page - 1) * 5) + 1 : 0, ((($page - 1) * 5) > ($review_total - 5)) ? $review_total : ((($page - 1) * 5) + 5), $review_total, ceil($review_total / 5));

		$this->response->setOutput($this->load->view('product/review', $data));
	}

	public function write() {
		$this->load->language('product/product');

		$json = array();

		if (isset($this->request->get['product_id']) && $this->request->get['product_id']) {
			if ($this->request->server['REQUEST_METHOD'] == 'POST') {
				if ((utf8_strlen($this->request->post['name']) < 3) || (utf8_strlen($this->request->post['name']) > 25)) {
					$json['error'] = $this->language->get('error_name');
				}

				if ((utf8_strlen($this->request->post['text']) < 25) || (utf8_strlen($this->request->post['text']) > 1000)) {
					$json['error'] = $this->language->get('error_text');
				}
			
				if (empty($this->request->post['rating']) || $this->request->post['rating'] < 0 || $this->request->post['rating'] > 5) {
					$json['error'] = $this->language->get('error_rating');
				}

				// Captcha
				if ($this->config->get('captcha_' . $this->config->get('config_captcha') . '_status') && in_array('review', (array)$this->config->get('config_captcha_page'))) {
					$captcha = $this->load->controller('extension/captcha/' . $this->config->get('config_captcha') . '/validate');

					if ($captcha) {
						$json['error'] = $captcha;
					}
				}

				if (!isset($json['error'])) {
					$this->load->model('catalog/review');

					$this->model_catalog_review->addReview($this->request->get['product_id'], $this->request->post);

					$json['success'] = $this->language->get('text_success');
				}
			}
		} else {
			$json['error'] = $this->language->get('error_product');
		} 

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function getRecurringDescription() {
		$this->load->language('product/product');
		$this->load->model('catalog/product');

		if (isset($this->request->post['product_id'])) {
			$product_id = $this->request->post['product_id'];
		} else {
			$product_id = 0;
		}

		if (isset($this->request->post['recurring_id'])) {
			$recurring_id = $this->request->post['recurring_id'];
		} else {
			$recurring_id = 0;
		}

		if (isset($this->request->post['quantity'])) {
			$quantity = $this->request->post['quantity'];
		} else {
			$quantity = 1;
		}

		$product_info = $this->model_catalog_product->getProduct($product_id);
		
		$recurring_info = $this->model_catalog_product->getProfile($product_id, $recurring_id);

		$json = array();

		if ($product_info && $recurring_info) {
			if (!$json) {
				$frequencies = array(
					'day'        => $this->language->get('text_day'),
					'week'       => $this->language->get('text_week'),
					'semi_month' => $this->language->get('text_semi_month'),
					'month'      => $this->language->get('text_month'),
					'year'       => $this->language->get('text_year'),
				);

				if ($recurring_info['trial_status'] == 1) {
					$price = $this->currency->format($this->tax->calculate($recurring_info['trial_price'] * $quantity, $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
					$trial_text = sprintf($this->language->get('text_trial_description'), $price, $recurring_info['trial_cycle'], $frequencies[$recurring_info['trial_frequency']], $recurring_info['trial_duration']) . ' ';
				} else {
					$trial_text = '';
				}

				$price = $this->currency->format($this->tax->calculate($recurring_info['price'] * $quantity, $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);

				if ($recurring_info['duration']) {
					$text = $trial_text . sprintf($this->language->get('text_payment_description'), $price, $recurring_info['cycle'], $frequencies[$recurring_info['frequency']], $recurring_info['duration']);
				} else {
					$text = $trial_text . sprintf($this->language->get('text_payment_cancel'), $price, $recurring_info['cycle'], $frequencies[$recurring_info['frequency']], $recurring_info['duration']);
				}

				$json['success'] = $text;
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
