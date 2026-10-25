<?php
/**
 * Language folder in the URL (`/uk/...`), outgoing half. Registered after
 * `startup/seo_url`, so it sees the finished SEO link and puts the current
 * language's folder in front of it. `alternates()` builds the same page's URL
 * in every enabled language (language switcher, hreflang).
 * See docs/developer/seo/language-urls.md.
 */
class ControllerStartupMcLanguageUrl extends Controller {
	private $prefix = null;

	public function index() {
		$this->prefix = $this->load->controller('startup/mc_language_prefix/prefix');

		if ($this->prefix) {
			$this->url->addRewrite($this);
		}
	}

	public function rewrite($link) {
		$code = $this->config->get('mc_url_language');

		if (!$code) {
			$code = isset($this->session->data['language']) ? $this->session->data['language'] : $this->config->get('config_language');
		}

		return $this->prefix->link($link, array($this->config->get('config_url'), $this->config->get('config_ssl')), $code);
	}

	/**
	 * The current page in every enabled language.
	 *
	 * @return array<string, string> language code => absolute URL (empty when the scheme is off)
	 */
	public function alternates() {
		// A fresh instance when called through load->controller().
		if (!$this->prefix) {
			$this->prefix = $this->load->controller('startup/mc_language_prefix/prefix');
		}

		if (!$this->prefix) {
			return array();
		}

		$args = $this->request->get;
		$route = isset($args['route']) ? $args['route'] : 'common/home';

		unset($args['_route_'], $args['route']);

		$this->load->model('localisation/language');

		$current_id = $this->config->get('config_language_id');
		$alternates = array();

		foreach ($this->model_localisation_language->getLanguages() as $code => $language) {
			if (!$language['status']) {
				continue;
			}

			// The SEO rewriter reads config_language_id, this one mc_url_language.
			$this->config->set('config_language_id', $language['language_id']);
			$this->config->set('mc_url_language', $code);

			$alternates[$code] = html_entity_decode($this->url->link($route, $args ? urldecode(http_build_query($args)) : ''), ENT_QUOTES, 'UTF-8');
		}

		$this->config->set('config_language_id', $current_id);
		$this->config->set('mc_url_language', '');

		return $alternates;
	}
}
