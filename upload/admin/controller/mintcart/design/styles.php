<?php
/**
 * `Design → Styles` (`MINTCART_FUNCTIONAL_SCOPE.md` §9, `MINTCART_TECHNICAL_PLAN.md`
 * §45, `MINTCART_IMPLEMENTATION_GUIDE.md` §31/§50).
 *
 * route=mintcart/design/styles. Fully additive — no existing OpenCart
 * controller/model/route touched (`CLAUDE.md` §8/§37).
 *
 * Phase 9 (Presets) shipped `clean`/`warm` as real, tested `:root[data-mc-preset='<code>']`
 * token blocks and each theme's `theme.json` already lists which presets it
 * supports — but `docs/developer/theme-api/presets.md`'s own "Not yet wired"
 * section named the gap this screen closes: nothing writes `data-mc-preset`
 * onto `<html>` at runtime, because no admin form existed yet to choose a
 * value from (`CLAUDE.md` §85 — no API ahead of a real consumer). This is
 * that form, the deliberately small first slice of the future Theme
 * Settings screen (`MINTCART_IMPLEMENTATION_GUIDE.md` §31): one setting
 * (which preset), not a `settings.json`-driven schema for every theme
 * option — that stays a separate, larger increment once a real theme has
 * more than a preset code to configure.
 *
 * The choice list is read live from the *active* theme's own `theme.json`
 * `presets` array (via `MintCart\Design\ActiveTheme::directory()`, the same
 * resolver `ControllerCommonHeader`/`ControllerProductCategory`/
 * `ControllerCommonFooter` already use) rather than a hardcoded
 * `clean`/`warm` list — a future theme with a different preset set, or none
 * at all, is handled correctly with no change to this file. A theme with no
 * declared presets (the legacy `default` theme, or `test_theme`, which
 * ships `"presets": []`) shows an explanatory empty state instead of a
 * broken form.
 *
 * Deliberately its own settings code group ('mintcart_design', matching the
 * single key it stores) rather than folding into 'config' — same
 * "one code, one key, no merge needed" reasoning
 * `ControllerMintcartSettingsDiagnostics` already documents for
 * 'mintcart_dev_mode'.
 *
 * Built on the legacy OpenCart admin theme (Bootstrap 3 `.panel`/`.btn`/
 * `form-horizontal` markup), same reasoning every other MintCart admin
 * screen in this repo gives (`docs/developer/blocks/homepage-admin.md`
 * "Why this screen uses the legacy admin theme") — MintCart's own compiled
 * asset bundle has never been enqueued into the shared admin
 * `header`/`column_left`/`footer` chrome anywhere in this repo
 * (`CLAUDE.md` §10).
 */
class ControllerMintcartDesignStyles extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('mintcart/design/styles');
		$this->load->model('setting/setting');

		$this->document->setTitle($this->language->get('heading_title'));

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->save();

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('mintcart/design/styles', 'user_token=' . $this->session->data['user_token'], true));

			return;
		}

		$this->getForm();
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'mintcart/design/styles')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		$preset = isset($this->request->post['mintcart_design_preset']) ? (string)$this->request->post['mintcart_design_preset'] : '';

		// Blank is always valid - it means "use the theme's own default look"
		// (Phase 9: a blank/absent data-mc-preset attribute and an explicit
		// data-mc-preset="clean" render identically, since clean.css is an
		// empty token block). Anything else must be one of the active
		// theme's own declared presets - never an arbitrary string that
		// would produce a `data-mc-preset` value no loaded CSS file
		// actually defines a :root block for.
		if (($preset !== '') && !in_array($preset, $this->availablePresets(), true)) {
			$this->error['warning'] = $this->language->get('error_preset');
		}

		foreach (array('primary', 'accent') as $key) {
			$value = trim((string)($this->request->post['mintcart_design_' . $key] ?? ''));

			if ($value !== '' && \MintCart\Design\StyleOverrides::hex($value) === '') {
				$this->error[$key] = $this->language->get('error_color');
			}
		}

		if ($this->error && !isset($this->error['warning'])) {
			$this->error['warning'] = $this->language->get('error_check');
		}

		return !$this->error;
	}

	protected function save() {
		$preset = isset($this->request->post['mintcart_design_preset']) ? (string)$this->request->post['mintcart_design_preset'] : '';

		$values = array('mintcart_design_preset' => $preset);

		$defaults = $this->lookDefaults($preset);

		foreach (\MintCart\Design\StyleOverrides::normalize($this->postedOverrides()) as $key => $value) {
			// What the chosen look already does is not stored as an override.
			$values['mintcart_design_' . $key] = ($value === ($defaults[$key] ?? '')) ? '' : $value;
		}

		$this->model_setting_setting->editSetting('mintcart_design', $values);
	}

	/**
	 * What a look itself uses for every control on this screen
	 * (`system/mintcart/data/design-presets.json`); "no look" uses the
	 * base tokens, which are the Clean look's.
	 *
	 * @return array<string, string>
	 */
	private function lookDefaults($preset) {
		$info = (array)json_decode((string)@file_get_contents(DIR_SYSTEM . 'mintcart/data/design-presets.json'), true);

		return isset($info[$preset]['defaults']) ? $info[$preset]['defaults'] : (isset($info['clean']['defaults']) ? $info['clean']['defaults'] : array());
	}

	private function postedOverrides() {
		$raw = array();

		foreach (array('primary', 'accent', 'radius', 'border', 'button_style', 'container', 'font_heading', 'font_body') as $key) {
			$raw[$key] = isset($this->request->post['mintcart_design_' . $key]) ? $this->request->post['mintcart_design_' . $key] : '';
		}

		return $raw;
	}

	/**
	 * The active theme's own declared preset codes
	 * (`docs/developer/theme-api/presets.md` "Declaring presets in a
	 * theme"), read live from its `theme.json` — never hardcoded, so a
	 * future theme with a different preset set (or none) is handled
	 * correctly with no change to this file. Empty for a theme with no
	 * manifest (the legacy `default` theme) or an empty `presets` array
	 * (`test_theme` today).
	 *
	 * @return string[]
	 */
	protected function availablePresets() {
		$directory = \MintCart\Design\ActiveTheme::directory($this->config->get('config_theme'), $this->config->get('theme_default_directory'));

		return \MintCart\Design\ActiveTheme::presets(DIR_CATALOG . 'view/theme', $directory);
	}

	protected function getForm() {
		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_edit'] = $this->language->get('text_edit');
		$data['text_intro'] = $this->language->get('text_intro');
		$data['entry_preset'] = $this->language->get('entry_preset');
		$data['help_preset'] = $this->language->get('help_preset');
		$data['text_default'] = $this->language->get('text_default');
		$data['text_no_presets'] = $this->language->get('text_no_presets');

		$data['error_warning'] = $this->error['warning'] ?? '';

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];

			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('mintcart/design/styles', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('mintcart/design/styles', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true);

		if ($this->request->server['REQUEST_METHOD'] == 'POST') {
			$selected = isset($this->request->post['mintcart_design_preset']) ? (string)$this->request->post['mintcart_design_preset'] : '';
		} else {
			$selected = (string)$this->config->get('mintcart_design_preset');
		}

		$presets = array();

		$preset_info = (array)json_decode((string)@file_get_contents(DIR_SYSTEM . 'mintcart/data/design-presets.json'), true);

		foreach ($this->availablePresets() as $code) {
			$presets[] = array(
				'code'        => $code,
				'description' => isset($preset_info[$code]['description']) ? $this->language->get('preset_' . $code) : '',
				'swatches'    => isset($preset_info[$code]['swatches']) ? $preset_info[$code]['swatches'] : array(),
				'defaults'    => json_encode(isset($preset_info[$code]['defaults']) ? $preset_info[$code]['defaults'] : new \stdClass()),
				// No per-preset language key: preset codes are an
				// open-ended, theme-declared set (CLAUDE.md §85/§86 - no
				// abstraction/translation table built ahead of more than
				// two real values), so the label is derived from the code
				// itself, the same way ControllerDesignHomepage::TYPES'
				// language-key-per-type approach would not scale to an
				// arbitrary per-theme list.
				'label' => ucfirst(str_replace(array('-', '_'), ' ', $code))
			);
		}

		$data['presets'] = $presets;
		$data['selected'] = $selected;

		// What the controls show: the owner's override, else what the chosen look uses.
		$overrides = \MintCart\Design\StyleOverrides::normalize($this->request->server['REQUEST_METHOD'] == 'POST' ? $this->postedOverrides() : array(
			'primary'      => $this->config->get('mintcart_design_primary'),
			'accent'       => $this->config->get('mintcart_design_accent'),
			'radius'       => $this->config->get('mintcart_design_radius'),
			'border'       => $this->config->get('mintcart_design_border'),
			'button_style' => $this->config->get('mintcart_design_button_style'),
			'container'    => $this->config->get('mintcart_design_container'),
			'font_heading' => $this->config->get('mintcart_design_font_heading'),
			'font_body'    => $this->config->get('mintcart_design_font_body')
		));

		$defaults = $this->lookDefaults($selected);
		$data['values'] = array();

		foreach ($overrides as $key => $value) {
			$data['values'][$key] = $value !== '' ? $value : (isset($defaults[$key]) ? $defaults[$key] : '');
		}

		$data['palettes'] = array();

		foreach ((array)json_decode((string)@file_get_contents(DIR_SYSTEM . 'mintcart/data/design-palettes.json'), true) as $palette) {
			$palette['desc'] = $this->language->get('palette_' . $palette['key']);

			$data['palettes'][] = $palette;
		}

		$data['error_primary'] = $this->error['primary'] ?? '';
		$data['error_accent'] = $this->error['accent'] ?? '';

		$data['fonts'] = array();

		foreach (\MintCart\Design\StyleOverrides::FONTS as $id => $font) {
			$data['fonts'][] = array('id' => $id, 'label' => $id === 'system' ? $this->language->get('font_system') : ($id === 'georgia' ? $this->language->get('font_georgia') : $font[0]), 'stack' => $font[1]);
		}

		$data['fonts_url'] = \MintCart\Design\StyleOverrides::fontsUrl(array_keys(array_filter(\MintCart\Design\StyleOverrides::FONTS, function ($font) {
			return $font[2];
		})));
		$data['container_widths'] = \MintCart\Design\StyleOverrides::CONTAINER;
		$data['radius_scales'] = \MintCart\Design\StyleOverrides::RADIUS;

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('mintcart/design/styles', $data));
	}
}
