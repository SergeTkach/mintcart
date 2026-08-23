<?php
class ControllerEventTheme extends Controller {
	public function index(&$route, &$args, &$code) {

		if (!$this->config->get('theme_' . $this->config->get('config_theme') . '_status')) {
			exit('Error: A theme has not been assigned to this store!');
		}

		// If the default theme is selected we need to know which directory its pointing to
		if ($this->config->get('config_theme') == 'default') {
			$directory = $this->config->get('theme_default_directory');
		} else {
			$directory = $this->config->get('config_theme');
		}

		// MintCart\Design\ComponentRenderer already resolves *component*
		// overrides through active theme -> parent chain -> mint_default
		// (MINTCART_TECHNICAL_PLAN.md Sec.11). Whole-PAGE routes resolved here
		// only ever had a two-tier active-theme -> 'default' fallback, so a
		// Mint-aware theme that overrides only a component and ships no page
		// templates of its own (e.g. catalog/view/theme/test_theme/, exactly
		// the CLAUDE.md Sec.19 milestone scenario) fell straight through to the
		// legacy 'default' theme's page template for every route - silently
		// discarding anything a Mint controller built via ComponentRenderer
		// for that page, since 'default' templates never read $product_cards.
		// Insert 'mint_default' as a middle tier, gated on the active theme
		// actually shipping a theme.json (Mint-aware), so an unrelated
		// third-party non-Mint theme keeps falling straight to 'default',
		// unaffected - same gate ControllerProductCategory already uses.
		$chain = array();

		if ($directory != 'default') {
			$chain[] = $directory;

			if ($directory != 'mint_default' && is_file(DIR_TEMPLATE . $directory . '/theme.json')) {
				$chain[] = 'mint_default';
			}
		}

		$chain[] = 'default';

		foreach ($chain as $candidate) {
			if (is_file(DIR_TEMPLATE . $candidate . '/template/' . $route . '.twig')) {
				$this->config->set('template_directory', $candidate . '/template/');
				break;
			}
		}

		// If there is a theme override we should get it
		$this->load->model('design/theme');

		$theme_info = $this->model_design_theme->getTheme($route, $directory);

		if ($theme_info) {
			$code = html_entity_decode($theme_info['code'], ENT_QUOTES, 'UTF-8');
		}
	}
}