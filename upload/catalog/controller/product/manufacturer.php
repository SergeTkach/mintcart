<?php
class ControllerProductManufacturer extends Controller {
	public function index() {
		$this->load->language('product/manufacturer');

		$this->load->model('catalog/manufacturer');

		$this->document->setTitle($this->language->get('heading_title'));

		// MintCart\Core\Stage (MINTCART_TECHNICAL_PLAN.md §15B,
		// MINTCART_IMPLEMENTATION_GUIDE.md §38B, Phase 1). First live
		// consumer: fires documented fine-grained extension points around
		// this method's two meaningful stages, on top of OpenCart's
		// existing Event system - no OCMOD, no new event mechanism. $data
		// is still built directly below exactly as before; Stage only
		// wraps it with before/after events. See docs/developer/core/stage.md.
		$mint_stage = new \MintCart\Core\Stage($this->event, 'catalog.manufacturer');

		$data['breadcrumbs'] = array();

		$mint_stage->run('breadcrumbs', $data, function () use (&$data) {
			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('text_home'),
				'href' => $this->url->link('common/home')
			);

			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('text_brand'),
				'href' => $this->url->link('product/manufacturer')
			);
		});

		$data['categories'] = array();

		$mint_stage->run('listing', $data, function () use (&$data) {
			$results = $this->model_catalog_manufacturer->getManufacturers();

			foreach ($results as $result) {
				if (is_numeric(utf8_substr($result['name'], 0, 1))) {
					$key = '0 - 9';
				} else {
					$key = utf8_substr(utf8_strtoupper($result['name']), 0, 1);
				}

				if (!isset($data['categories'][$key])) {
					$data['categories'][$key]['name'] = $key;
				}

				$data['categories'][$key]['manufacturer'][] = array(
					'name' => $result['name'],
					'href' => $this->url->link('product/manufacturer/info', 'manufacturer_id=' . $result['manufacturer_id'])
				);
			}
		});

		// MC-0017 (docs/fixes/registry.md) - manufacturer_list.twig (both
		// the stock `default` theme and `mint_default`) references
		// heading_title/text_index/text_no_results/button_continue, but
		// this method never assigned any of them to $data - the same
		// class of bug as MC-0001 (category.php) and MC-0003
		// (manufacturer.php's own info() method), found here while
		// building mint_default's own manufacturer_list.twig.
		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_index'] = $this->language->get('text_index');
		$data['text_no_results'] = $this->language->get('text_no_results');
		$data['button_continue'] = $this->language->get('button_continue');

		$data['continue'] = $this->url->link('common/home');

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('product/manufacturer_list', $data));
	}

	public function info() {
		$this->load->language('product/manufacturer');

		$this->load->model('catalog/manufacturer');

		$this->load->model('catalog/product');

		$this->load->model('tool/image');

		if (isset($this->request->get['manufacturer_id'])) {
			$manufacturer_id = (int)$this->request->get['manufacturer_id'];
		} else {
			$manufacturer_id = 0;
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
			$limit = (int)$this->config->get('theme_' . $this->config->get('config_theme') . '_product_limit');
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_brand'),
			'href' => $this->url->link('product/manufacturer')
		);

		$manufacturer_info = $this->model_catalog_manufacturer->getManufacturer($manufacturer_id);

		if ($manufacturer_info) {
			// Marketing -> SEO -> Meta tags (no template = the plain brand name, as before).
			$mint_meta = (new \MintCart\Seo\MetaBuilder(\MintCart\Seo\MetaRules::forPage($this->config->get('mintcart_seo_meta'), 'manufacturer', (int)$this->config->get('config_language_id'))))->build(
				'',
				'',
				$manufacturer_info['name'],
				null,
				array('name' => $manufacturer_info['name'], 'store' => $this->config->get('config_name'))
			);

			$this->document->setTitle($mint_meta['title']);

			if ($mint_meta['description'] !== '') {
				$this->document->setDescription($mint_meta['description']);
			}

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
				'text' => $manufacturer_info['name'],
				'href' => $this->url->link('product/manufacturer/info', 'manufacturer_id=' . $this->request->get['manufacturer_id'] . $url)
			);

			$data['heading_title'] = $manufacturer_info['name'];

			$data['text_compare'] = sprintf($this->language->get('text_compare'), (isset($this->session->data['compare']) ? count($this->session->data['compare']) : 0));

			$data['compare'] = $this->url->link('product/compare');

			$data['products'] = array();

			// MintCart\Design\ProductCardListRenderer (which composes
			// MintCart\Commerce\ProductViewProvider + ComponentRenderer)
			// wiring (MINTCART_TECHNICAL_PLAN.md §11/§15C-D) - the fourth
			// real listing consumer sharing the same pipeline as
			// category.php/search.php/special.php rather than duplicating it
			// (CLAUDE.md §8A). Only built when the active theme ships a
			// theme.json manifest (i.e. it is Mint-API-aware), same gate the
			// three sibling controllers already use - see
			// docs/upstream-diff.md. The controller's original
			// $data['products'] array above is completely unaffected either
			// way.
			$mint_theme = \MintCart\Design\ActiveTheme::directory($this->config->get('config_theme'), $this->config->get('theme_default_directory'));
			$mint_render_product_cards = \MintCart\Design\ActiveTheme::isMintAware(DIR_TEMPLATE, $mint_theme);

			// MintCart\Core\Diagnostics (MINTCART_TECHNICAL_PLAN.md §35,
			// MINTCART_FUNCTIONAL_SCOPE.md §19 "render time") - same
			// category.php-only wiring extended to Manufacturer. Off by
			// default; see category.php's identical comment and
			// docs/developer/core/diagnostics.md.
			$mint_diagnostics = new \MintCart\Core\Diagnostics((bool)$this->config->get('mintcart_dev_mode'));

			$filter_data = array(
				'filter_manufacturer_id' => $manufacturer_id,
				'sort'                   => $sort,
				'order'                  => $order,
				'start'                  => ($page - 1) * $limit,
				'limit'                  => $limit
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
					'href'        => $this->url->link('product/product', 'manufacturer_id=' . $result['manufacturer_id'] . '&product_id=' . $result['product_id'] . $url)
				);

			}

			// Shared product-card pipeline (docs/developer/commerce/product-cards.md);
			// null on non-Mint themes, which keep using $data['products'].
			if ($mint_render_product_cards) {
				$data['product_cards'] = $this->load->controller('mintcart/product_cards', array(
					'results'      => $results,
					'listing_type' => 'manufacturer',
					'link_prefix'  => 'manufacturer_id=' . $manufacturer_id . '&',
					'url'          => $url,
					'diagnostics'  => $mint_diagnostics
				));
			}

			$url = '';

			if (isset($this->request->get['limit'])) {
				$url .= '&limit=' . $this->request->get['limit'];
			}

			$data['sorts'] = array();

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_default'),
				'value' => 'p.sort_order-ASC',
				'href'  => $this->url->link('product/manufacturer/info', 'manufacturer_id=' . $this->request->get['manufacturer_id'] . '&sort=p.sort_order&order=ASC' . $url)
			);

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_name_asc'),
				'value' => 'pd.name-ASC',
				'href'  => $this->url->link('product/manufacturer/info', 'manufacturer_id=' . $this->request->get['manufacturer_id'] . '&sort=pd.name&order=ASC' . $url)
			);

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_name_desc'),
				'value' => 'pd.name-DESC',
				'href'  => $this->url->link('product/manufacturer/info', 'manufacturer_id=' . $this->request->get['manufacturer_id'] . '&sort=pd.name&order=DESC' . $url)
			);

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_price_asc'),
				'value' => 'p.price-ASC',
				'href'  => $this->url->link('product/manufacturer/info', 'manufacturer_id=' . $this->request->get['manufacturer_id'] . '&sort=p.price&order=ASC' . $url)
			);

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_price_desc'),
				'value' => 'p.price-DESC',
				'href'  => $this->url->link('product/manufacturer/info', 'manufacturer_id=' . $this->request->get['manufacturer_id'] . '&sort=p.price&order=DESC' . $url)
			);

			if ($this->config->get('config_review_status')) {
				$data['sorts'][] = array(
					'text'  => $this->language->get('text_rating_desc'),
					'value' => 'rating-DESC',
					'href'  => $this->url->link('product/manufacturer/info', 'manufacturer_id=' . $this->request->get['manufacturer_id'] . '&sort=rating&order=DESC' . $url)
				);

				$data['sorts'][] = array(
					'text'  => $this->language->get('text_rating_asc'),
					'value' => 'rating-ASC',
					'href'  => $this->url->link('product/manufacturer/info', 'manufacturer_id=' . $this->request->get['manufacturer_id'] . '&sort=rating&order=ASC' . $url)
				);
			}

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_model_asc'),
				'value' => 'p.model-ASC',
				'href'  => $this->url->link('product/manufacturer/info', 'manufacturer_id=' . $this->request->get['manufacturer_id'] . '&sort=p.model&order=ASC' . $url)
			);

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_model_desc'),
				'value' => 'p.model-DESC',
				'href'  => $this->url->link('product/manufacturer/info', 'manufacturer_id=' . $this->request->get['manufacturer_id'] . '&sort=p.model&order=DESC' . $url)
			);

			$url = '';

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
					'href'  => $this->url->link('product/manufacturer/info', 'manufacturer_id=' . $this->request->get['manufacturer_id'] . $url . '&limit=' . $value)
				);
			}

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

			$pagination = new Pagination();
			$pagination->total = $product_total;
			$pagination->page = $page;
			$pagination->limit = $limit;
			$pagination->url = $this->url->link('product/manufacturer/info', 'manufacturer_id=' . $this->request->get['manufacturer_id'] .  $url . '&page={page}');

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
				$canonical_links = $canonical_builder->build($page, (int)$limit, (int)$product_total, 'manufacturer_id=' . $this->request->get['manufacturer_id']);

				$this->document->addLink($this->url->link('product/manufacturer/info', $canonical_links['canonical']), 'canonical');

				if ($canonical_links['prev'] !== null) {
					$this->document->addLink($this->url->link('product/manufacturer/info', $canonical_links['prev']), 'prev');
				}

				if ($canonical_links['next'] !== null) {
					$this->document->addLink($this->url->link('product/manufacturer/info', $canonical_links['next']), 'next');
				}
			} catch (\Throwable $e) {
				(new \MintCart\Core\Logger())->error('CanonicalUrlBuilder failed to build pagination links: ' . $e->getMessage());
			}

			$data['sort'] = $sort;
			$data['order'] = $order;
			$data['limit'] = $limit;

			// MC-0003 (docs/fixes/registry.md) - manufacturer_info.twig (both
			// the stock `default` theme and `mint_default`) references
			// text_sort/text_limit/text_empty/button_continue, but this
			// method never assigned any of them to $data - the same class of
			// bug as MC-0001 in category.php, found here while wiring the
			// Mint product-card pipeline below.
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

			$output = $this->load->view('product/manufacturer_info', $data);

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

			if (isset($this->request->get['manufacturer_id'])) {
				$url .= '&manufacturer_id=' . $this->request->get['manufacturer_id'];
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
				'href' => $this->url->link('product/manufacturer/info', $url)
			);

			$this->document->setTitle($this->language->get('text_error'));

			$data['heading_title'] = $this->language->get('text_error');

			$data['text_error'] = $this->language->get('text_error');

			$data['continue'] = $this->url->link('common/home');

			$this->response->addHeader($this->request->server['SERVER_PROTOCOL'] . ' 404 Not Found');

			$data['header'] = $this->load->controller('common/header');
			$data['footer'] = $this->load->controller('common/footer');
			$data['column_left'] = $this->load->controller('common/column_left');
			$data['column_right'] = $this->load->controller('common/column_right');
			$data['content_top'] = $this->load->controller('common/content_top');
			$data['content_bottom'] = $this->load->controller('common/content_bottom');

			$this->response->setOutput($this->load->view('error/not_found', $data));
		}
	}
}
