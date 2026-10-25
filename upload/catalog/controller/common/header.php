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

		// MintCart's own compiled asset bundle (ADR 010,
		// docs/developer/storefront-bootstrap5-migration.md). A Mint-aware
		// theme (theme.json present) runs on Bootstrap 5: index.min.css is
		// Bootstrap 5 + Mint CSS + the Bootstrap 3 class shim, catalog.min.js
		// is Bootstrap 5 JS + Mint JS + the Bootstrap 3 jQuery/data-toggle
		// shim. Such a theme's common/header.twig must NOT load Bootstrap 3
		// itself (mint_default's doesn't); jQuery/common.js stay for legacy
		// code. Computed before $data['styles']/$data['scripts'] snapshot the
		// Document queue below. A store on the legacy 'default' theme, or an
		// unrelated third-party theme, keeps its own Bootstrap 3 stack and
		// gets nothing from here.
		$mint_theme = \MintCart\Design\ActiveTheme::directory($this->config->get('config_theme'), $this->config->get('theme_default_directory'));
		$mint_render_header = \MintCart\Design\ActiveTheme::isMintAware(DIR_TEMPLATE, $mint_theme);

		if ($mint_render_header) {
			$this->document->addStyle($server . 'assets/dist/css/index.min.css');
			$this->document->addScript($server . 'assets/dist/js/catalog.min.js');
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

		// MintCart: hreflang alternates when the store has language folders in
		// the URL (startup/mc_language_url). Pages that are not for search
		// engines (cart, checkout, account, errors, ...) get none.
		$data['mc_hreflang'] = array();

		$hreflang_route = isset($this->request->get['route']) ? $this->request->get['route'] : 'common/home';

		if (!preg_match('#^(error|checkout|account|mintcart|extension|api)/#', $hreflang_route)) {
			$alternates = array();

			foreach ($this->load->controller('startup/mc_language_url/alternates') as $code => $href) {
				$alternates[] = array('hreflang' => $code, 'href' => $href);
			}

			$data['mc_hreflang'] = (new \MintCart\Seo\HreflangBuilder())->build($alternates, $this->config->get('config_language'));
		}

		// MintCart: phone input mask (the store's own format, else the country default).
		$this->load->model('localisation/country');

		$country_info = $this->model_localisation_country->getCountry((int)$this->config->get('config_country_id'));

		$data['mc_phone_mask'] = \MintCart\Commerce\PhoneMask::resolve(
			(string)$this->config->get('config_mc_phone_mask'),
			$country_info ? (string)$country_info['iso_code_2'] : '',
			(array)json_decode((string)@file_get_contents(DIR_SYSTEM . 'mintcart/data/phone-masks.json'), true)
		);

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
		// `$mint_theme`/`$mint_render_header` are already resolved above
		// (the asset-bundle enqueue needed them earlier than this point, to
		// run before the styles/scripts snapshot) - reused here as-is.
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
						'compare' => $this->url->link('product/compare', '', true),
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
					),
					array(
						'text' => $this->config->get('mintcart_announcement_text'),
						'url' => $this->config->get('mintcart_announcement_url')
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
					'text_compare' => $this->language->get('text_mc_compare'),
					'text_call' => $this->language->get('text_mc_call'),
					'text_menu' => $this->language->get('text_menu'),
					'text_close' => $this->language->get('text_close'),
					'text_search' => $this->language->get('text_search'),
					'text_search_empty' => $this->language->get('text_mc_search_empty'),
					'text_search_all' => $this->language->get('text_mc_search_all')
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
		// $hasResults: for product/search only, threaded from
		// ControllerProductSearch::index() via the registry (it runs
		// before this sub-controller and has no other way to hand us "did
		// this search match anything" - see RobotsBuilder::
		// HAS_RESULTS_REGISTRY_KEY's own docblock). Every other route
		// never sets this key, so the registry read is null and this
		// falls back to RobotsBuilder's own default (true), unchanged for
		// every route except product/search - see
		// docs/developer/seo/robots.md for the full writeup.
		try {
			$route = isset($this->request->get['route']) ? (string)$this->request->get['route'] : '';

			$robots_builder = new \MintCart\Seo\RobotsBuilder();

			$mint_search_has_results = $this->registry->get(\MintCart\Seo\RobotsBuilder::HAS_RESULTS_REGISTRY_KEY);

			if ($mint_search_has_results === null) {
				$mint_search_has_results = true;
			}

			$data['mc_robots_content'] = $robots_builder->metaContent($route, (bool)$mint_search_has_results);
		} catch (\Throwable $e) {
			(new \MintCart\Core\Logger())->error('RobotsBuilder failed to build robots meta content: ' . $e->getMessage());
		}

		// Design → Styles (MINTCART_FUNCTIONAL_SCOPE.md §9, Phase 9/31,
		// docs/developer/theme-api/presets.md) - writes data-mc-preset onto
		// <html> from the store's own choice, once ControllerMintcartDesignStyles
		// exists to make that choice from. Not gated by $mint_render_header
		// like mc_header above: this is a plain CSS attribute selector any
		// theme's `<html>` tag can carry (mint_default's is the only one
		// that does today; a legacy/third-party theme's own header.twig
		// simply never reads $data['mc_preset']). Re-validated here against
		// the *active* theme's own declared presets (not merely "is this a
		// non-empty string") so a stale setting left over from a since-
		// switched theme, or from a theme that dropped a preset code, never
		// emits a data-mc-preset value no loaded CSS actually declares a
		// :root block for.
		$mc_preset = (string)$this->config->get('mintcart_design_preset');

		if ($mc_preset !== '') {
			$manifest_path = rtrim(DIR_TEMPLATE, '/') . '/' . $mint_theme . '/theme.json';

			$manifest = is_file($manifest_path) ? json_decode((string)file_get_contents($manifest_path), true) : null;

			$available_presets = (is_array($manifest) && isset($manifest['presets']) && is_array($manifest['presets'])) ? $manifest['presets'] : array();

			if (!in_array($mc_preset, $available_presets, true)) {
				$mc_preset = '';
			}
		}

		$data['mc_preset'] = $mc_preset;

		// Design > Styles: the owner's colours / corners / fonts on top of the preset.
		$mc_styles = \MintCart\Design\StyleOverrides::build(array(
			'primary'      => $this->config->get('mintcart_design_primary'),
			'accent'       => $this->config->get('mintcart_design_accent'),
			'radius'       => $this->config->get('mintcart_design_radius'),
			'border'       => $this->config->get('mintcart_design_border'),
			'button_style' => $this->config->get('mintcart_design_button_style'),
			'container'    => $this->config->get('mintcart_design_container'),
			'font_heading' => $this->config->get('mintcart_design_font_heading'),
			'font_body'    => $this->config->get('mintcart_design_font_body')
		));

		$data['mc_style_css'] = $mint_render_header ? $mc_styles['css'] : '';
		$data['mc_fonts_url'] = $mint_render_header ? $mc_styles['fonts_url'] : '';

		// <html data-mc-theme>: lets a theme scope its own CSS to its pages
		// (e.g. mint_gallery's product page) without a page-template copy.
		$data['mc_theme'] = $mint_render_header ? $mint_theme : '';

		$data['mc_slots'] = \MintCart\Design\Slots::shared()->renderMany(array('header.before', 'header.after'));

		return $this->load->view('common/header', $data);
	}
}
