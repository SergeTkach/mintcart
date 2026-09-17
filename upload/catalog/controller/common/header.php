<?php
class ControllerCommonHeader extends Controller {
	public function index() {
		// Analytics
		$this->load->model('setting/extension');

		$data['analytics'] = array();

		$analytics = $this->model_setting_extension->getExtensions('analytics');

		foreach ($analytics as $analytic) {
			if ($this->config->get('analytics_' . $analytic['code'] . '_status')) {
				$data['analytics'][] = $this->load->controller('extension/analytics/' . $analytic['code'], $this->config->get('analytics_' . $analytic['code'] . '_status'));
			}
		}

		if ($this->request->server['HTTPS']) {
			$server = $this->config->get('config_ssl');
		} else {
			$server = $this->config->get('config_url');
		}

		if (is_file(DIR_IMAGE . $this->config->get('config_icon'))) {
			$this->document->addLink($server . 'image/' . $this->config->get('config_icon'), 'icon');
		}

		$data['title'] = $this->document->getTitle();

		$data['base'] = $server;
		$data['description'] = $this->document->getDescription();
		$data['keywords'] = $this->document->getKeywords();
		$data['links'] = $this->document->getLinks();
		$data['styles'] = $this->document->getStyles();
		$data['scripts'] = $this->document->getScripts('header');
		$data['lang'] = $this->language->get('code');
		$data['direction'] = $this->language->get('direction');

		$data['name'] = $this->config->get('config_name');

		if (is_file(DIR_IMAGE . $this->config->get('config_logo'))) {
			$data['logo'] = $server . 'image/' . $this->config->get('config_logo');
		} else {
			$data['logo'] = '';
		}

		$this->load->language('common/header');

		// Wishlist
		if ($this->customer->isLogged()) {
			$this->load->model('account/wishlist');

			$data['text_wishlist'] = sprintf($this->language->get('text_wishlist'), $this->model_account_wishlist->getTotalWishlist());
		} else {
			$data['text_wishlist'] = sprintf($this->language->get('text_wishlist'), (isset($this->session->data['wishlist']) ? count($this->session->data['wishlist']) : 0));
		}

		$data['text_logged'] = sprintf($this->language->get('text_logged'), $this->url->link('account/account', '', true), $this->customer->getFirstName(), $this->url->link('account/logout', '', true));
		
		$data['home'] = $this->url->link('common/home');
		$data['wishlist'] = $this->url->link('account/wishlist', '', true);
		$data['logged'] = $this->customer->isLogged();
		$data['account'] = $this->url->link('account/account', '', true);
		$data['register'] = $this->url->link('account/register', '', true);
		$data['login'] = $this->url->link('account/login', '', true);
		$data['order'] = $this->url->link('account/order', '', true);
		$data['transaction'] = $this->url->link('account/transaction', '', true);
		$data['download'] = $this->url->link('account/download', '', true);
		$data['logout'] = $this->url->link('account/logout', '', true);
		$data['shopping_cart'] = $this->url->link('checkout/cart');
		$data['checkout'] = $this->url->link('checkout/checkout', '', true);
		$data['contact'] = $this->url->link('information/contact');
		$data['telephone'] = $this->config->get('config_telephone');
		
		$data['language'] = $this->load->controller('common/language');
		$data['currency'] = $this->load->controller('common/currency');
		$data['search'] = $this->load->controller('common/search');
		$data['cart'] = $this->load->controller('common/cart');
		$data['menu'] = $this->load->controller('common/menu');

		// MintCart\Design\ComponentRenderer / MintCart\Design\HeaderViewProvider
		// wiring (MINTCART_TECHNICAL_PLAN.md §14, Phase 7), via the shared
		// MintCart\Design\ActiveTheme resolver every other listing/common
		// controller (footer.php/category.php/search.php/special.php/
		// manufacturer.php) already uses - only built when the active theme
		// ships a theme.json manifest (i.e. it is Mint-API-aware). Neither
		// the legacy 'default' theme nor an unrelated third-party theme has
		// one, and neither of their templates read $data['mc_header'], so
		// stores on either kind of theme pay no extra cost. Every field
		// above this point is left completely untouched - this only
		// regroups it, it does not change what it resolves to.
		$mint_theme = \MintCart\Design\ActiveTheme::directory($this->config->get('config_theme'), $this->config->get('theme_default_directory'));
		$mint_render_header = \MintCart\Design\ActiveTheme::isMintAware(DIR_TEMPLATE, $mint_theme);

		if ($mint_render_header) {
			try {
				$header_view_provider = new \MintCart\Design\HeaderViewProvider();
				$component_renderer = new \MintCart\Design\ComponentRenderer(DIR_TEMPLATE, $mint_theme);

				$header_view = $header_view_provider->build(
					array(
						'name' => $data['name'],
						'logo' => $data['logo'] ?: null,
						'home' => $data['home']
					),
					array(
						'logged' => $data['logged'],
						'first_name' => $data['logged'] ? $this->customer->getFirstName() : null,
						'wishlist_count' => $this->customer->isLogged() ? $this->model_account_wishlist->getTotalWishlist() : (isset($this->session->data['wishlist']) ? count($this->session->data['wishlist']) : 0)
					),
					array(
						'phone' => $data['telephone'] ?: null
					),
					array(
						'account' => $data['account'],
						'login' => $data['login'],
						'register' => $data['register'],
						'order' => $data['order'],
						'transaction' => $data['transaction'],
						'download' => $data['download'],
						'logout' => $data['logout'],
						'wishlist' => $data['wishlist'],
						'shopping_cart' => $data['shopping_cart'],
						'checkout' => $data['checkout'],
						'contact' => $data['contact']
					),
					array(
						'menu' => $data['menu'],
						'search' => $data['search'],
						'cart' => $data['cart'],
						'language' => $data['language'],
						'currency' => $data['currency']
					)
				);

				$data['mc_header'] = $component_renderer->render('header', $header_view + array(
					'text_account' => $this->language->get('text_account'),
					'text_register' => $this->language->get('text_register'),
					'text_login' => $this->language->get('text_login'),
					'text_order' => $this->language->get('text_order'),
					'text_transaction' => $this->language->get('text_transaction'),
					'text_download' => $this->language->get('text_download'),
					'text_logout' => $this->language->get('text_logout'),
					'text_wishlist' => $data['text_wishlist'],
					'text_menu' => $this->language->get('text_menu'),
					'text_close' => $this->language->get('text_close')
				));
			} catch (\Throwable $e) {
				// Same rationale as ControllerCommonFooter's own catch: a
				// broken/missing header.twig must not fatal every page on
				// the site (header is common to all of them). Falls back
				// to no mc_header - mint_default's own common/header.twig
				// renders nothing in <body> rather than the legacy markup
				// in that case, same "log and disable for this request"
				// pattern already used by footer/category.
				(new \MintCart\Core\Logger())->error('ComponentRenderer failed to render header: ' . $e->getMessage());
			}

			// MintCart\Seo\SchemaOrgBuilder Organization JSON-LD
			// (MINTCART_FUNCTIONAL_SCOPE.md §16, Phase 18). Site-wide (one
			// Organization per page, not per component), so it is built
			// here rather than inside HeaderViewProvider's own contract -
			// every field below reuses config values HeaderViewProvider
			// already reads (name/logo/telephone), no new OpenCart storage.
			// Guarded by the same try/catch-and-log pattern as mc_header
			// above: a malformed value must not fatal the page.
			try {
				$schema_builder = new \MintCart\Seo\SchemaOrgBuilder();

				$data['mc_schema_organization'] = $schema_builder->toJsonLd($schema_builder->organization(array(
					'name' => $data['name'],
					'url' => $data['home'],
					'logo' => $data['logo'] ?: null,
					'telephone' => $data['telephone'] ?: null,
					'address' => $this->config->get('config_address') ?: null
				)));
			} catch (\Throwable $e) {
				(new \MintCart\Core\Logger())->error('SchemaOrgBuilder failed to build Organization schema: ' . $e->getMessage());
			}
		}

		// MintCart\Seo\RobotsBuilder (MINTCART_FUNCTIONAL_SCOPE.md §16, Phase
		// 18) - unlike mc_header/mc_schema_organization above, this is not
		// gated by $mint_render_header: the robots directive is a per-route
		// SEO signal, not a Mint component DOM, so it applies to every theme
		// whose common/header.twig reads $data['mc_robots_content'] (today
		// just mint_default; a theme/legacy template that ignores the key
		// pays no cost). $this->request->get['route'] is the same route
		// string startup/router.php and every other route-aware controller
		// in this repo already reads (content_top.php, event/activity.php);
		// isset() guarded the same defensive way content_top.php does it,
		// since header.php runs as a sub-controller of every page and an
		// unset route is not expected to happen on a real page render.
		// $hasResults is deliberately left at its default (true) for every
		// route including product/search: threading the real "did this
		// search match anything" flag from search.php into header.php needs
		// a registry/session bridge between two sibling controllers, a
		// separate, slightly larger wiring task - see
		// docs/developer/seo/robots.md "Known limitation" for the follow-up.
		try {
			$route = isset($this->request->get['route']) ? (string)$this->request->get['route'] : '';

			$robots_builder = new \MintCart\Seo\RobotsBuilder();

			$data['mc_robots_content'] = $robots_builder->metaContent($route);
		} catch (\Throwable $e) {
			(new \MintCart\Core\Logger())->error('RobotsBuilder failed to build robots meta content: ' . $e->getMessage());
		}

		return $this->load->view('common/header', $data);
	}
}
