<?php
/**
 * Language folder in the URL (`/uk/...`), incoming half. Runs right after
 * `startup/session`, before `startup/startup` picks the language, so the
 * folder decides it:
 *
 *   /uk/foo       -> language uk-ua, route `foo`
 *   /foo, /       -> the default language (never the session / cookie /
 *                    Accept-Language guess: one URL, one language)
 *   /en/foo       -> 301 to /foo when `en` is the default language's folder
 *   index.php?route=... (no `_route_`) -> session language kept, so AJAX
 *                    calls (built without a folder) answer in the page language
 *
 * Active only with SEO URLs on and at least two enabled languages. Outgoing
 * links: `startup/mc_language_url`. See docs/developer/seo/language-urls.md.
 */
class ControllerStartupMcLanguagePrefix extends Controller {
	public function index() {
		$prefix = $this->prefix();

		if (!$prefix) {
			return;
		}

		$get = &$this->request->get;

		if (isset($get['_route_'])) {
			$split = $prefix->split($get['_route_']);

			if ($split['code'] !== null && $split['code'] === $this->setting('config_language')) {
				$query = $get;

				unset($query['_route_']);

				$this->response->redirect($this->setting('config_url', HTTP_SERVER) . $split['rest'] . ($query ? '?' . http_build_query($query) : ''), 301);
			}

			if ($split['code'] !== null) {
				$this->session->data['language'] = $split['code'];

				// "/uk/index.php?route=..." is a non-SEO link with the folder in front.
				if ($split['rest'] === '' || $split['rest'] === 'index.php') {
					unset($get['_route_']);
				} else {
					$get['_route_'] = $split['rest'];
				}

				return;
			}
		}

		// A page request without a folder is the default language.
		if (isset($get['_route_']) || !isset($get['route'])) {
			$this->session->data['language'] = $this->setting('config_language');
		}
	}

	// This controller runs before startup/startup, which is what loads the
	// store settings into config; read the few it needs from the table then.
	private function setting($key, $fallback = '') {
		$value = $this->config->get($key);

		if ($value === null) {
			$query = $this->db->query("SELECT `value` FROM `" . DB_PREFIX . "setting` WHERE `store_id` = '0' AND `key` = '" . $this->db->escape($key) . "'");

			$value = $query->num_rows ? $query->row['value'] : $fallback;
		}

		return $value;
	}

	/**
	 * @return \MintCart\Seo\LanguagePrefix|null null when the scheme is off
	 */
	public function prefix() {
		if (!$this->setting('config_seo_url')) {
			return null;
		}

		$this->load->model('localisation/language');

		$codes = array();

		foreach ($this->model_localisation_language->getLanguages() as $code => $language) {
			if ($language['status']) {
				$codes[] = $code;
			}
		}

		return count($codes) > 1 ? new \MintCart\Seo\LanguagePrefix($codes, $this->setting('config_language')) : null;
	}
}
