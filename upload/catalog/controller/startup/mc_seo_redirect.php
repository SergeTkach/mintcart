<?php
/**
 * 301 redirects to the one canonical SEO URL of a page (SEO -> Redirects, docs/developer/seo/redirects.md).
 * Runs after `startup/seo_url` and `startup/mc_language_url`, so the request is already decoded and
 * OpenCart's own link generator knows the keyword and language folder.
 *
 *  1. A page reached by any other address - `index.php?route=product/product&product_id=5`,
 *     `/kitchen/white-mug`, `/white-mug/`, `/mugs?page=1` - is redirected to `/white-mug` (what
 *     `$this->url->link()` returns for it). A page without a keyword is left alone.
 *  2. A keyword that no page has any more (`error/not_found`) is looked up in `mc_redirect`: an old keyword
 *     of a page, written automatically when the keyword was changed in the admin, or a manual redirect.
 *
 * Only GET page navigations: never POST, AJAX, payment callbacks (`extension/`) or API calls. The three
 * kinds of duplicate in point 1 can be switched off in the admin (setting `mintcart_seo_redirects`).
 */
class ControllerStartupMcSeoRedirect extends Controller {
	public function index() {
		if (!$this->config->get('config_seo_url') || !$this->isPageNavigation()) {
			return;
		}

		$options = $this->options();

		if (!empty($this->request->get['_route_']) && isset($this->request->get['route']) && $this->request->get['route'] === 'error/not_found') {
			$this->fromTable();

			return;
		}

		if (empty($options['dirty']) && empty($options['slash']) && empty($options['page1'])) {
			return;
		}

		$route = isset($this->request->get['route']) ? (string)$this->request->get['route'] : '';

		if ($route === '' || \MintCart\Seo\RedirectPlanner::skipsRoute($route)) {
			return;
		}

		$args = $this->request->get;

		unset($args['route'], $args['_route_']);

		// A product has one address, whatever category the visitor came from.
		if ($route === 'product/product') {
			unset($args['path'], $args['manufacturer_id'], $args['category_id'], $args['sub_category'], $args['search'], $args['tag'], $args['description']);
		}

		if (isset($args['page']) && (string)$args['page'] === '1') {
			unset($args['page']);
		}

		$canonical = $this->url->link($route, $args ? urldecode(http_build_query($args)) : '');

		$to = \MintCart\Seo\RedirectPlanner::plan(isset($this->request->server['REQUEST_URI']) ? $this->request->server['REQUEST_URI'] : '', $canonical, $options);

		if ($to !== null) {
			$this->response->redirect(str_replace('&amp;', '&', $to), 301);
		}
	}

	/** GET, a browser navigation (not fetch / XHR), not an API / payment callback request. */
	private function isPageNavigation() {
		$server = $this->request->server;

		if ((isset($server['REQUEST_METHOD']) ? $server['REQUEST_METHOD'] : 'GET') !== 'GET') {
			return false;
		}

		if (strtolower(isset($server['HTTP_X_REQUESTED_WITH']) ? $server['HTTP_X_REQUESTED_WITH'] : '') === 'xmlhttprequest') {
			return false;
		}

		$mode = strtolower(isset($server['HTTP_SEC_FETCH_MODE']) ? $server['HTTP_SEC_FETCH_MODE'] : '');

		if ($mode !== '') {
			return $mode === 'navigate';
		}

		$accept = isset($server['HTTP_ACCEPT']) ? $server['HTTP_ACCEPT'] : '';

		return $accept === '' || strpos($accept, 'text/html') !== false;
	}

	/** @return array{dirty: bool, slash: bool, page1: bool} all on until the admin switches one off */
	private function options() {
		$saved = json_decode((string)$this->config->get('mintcart_seo_redirects'), true);
		$saved = is_array($saved) ? $saved : array();

		return array(
			'dirty' => !isset($saved['dirty']) || !empty($saved['dirty']),
			'slash' => !isset($saved['slash']) || !empty($saved['slash']),
			'page1' => !isset($saved['page1']) || !empty($saved['page1'])
		);
	}

	private function fromTable() {
		$old_path = \MintCart\Seo\RedirectPlanner::oldPath((string)$this->request->get['_route_']);

		if ($old_path === '') {
			return;
		}

		try {
			$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "mc_redirect` WHERE `old_path` = '" . $this->db->escape($old_path) . "' AND `store_id` = '" . (int)$this->config->get('config_store_id') . "' AND `language_id` IN (0, '" . (int)$this->config->get('config_language_id') . "') ORDER BY `language_id` DESC LIMIT 1");
		} catch (\Throwable $e) {
			// The table does not exist yet (migrations not run): nothing to redirect.
			return;
		}

		if (!$query->num_rows) {
			return;
		}

		$row = $query->row;

		if ($row['target_url'] !== '') {
			$target = $row['target_url'];

			if (strpos($target, 'http') !== 0) {
				$target = rtrim($this->config->get('config_url'), '/') . '/' . ltrim($target, '/');
			}
		} else {
			$target = str_replace('&amp;', '&', $this->url->link($row['route'], $row['args']));
		}

		// A redirect to the address that was asked for would loop.
		$current = parse_url(isset($this->request->server['REQUEST_URI']) ? $this->request->server['REQUEST_URI'] : '', PHP_URL_PATH);
		$to = parse_url($target, PHP_URL_PATH);

		if (rtrim((string)$current, '/') === rtrim((string)$to, '/')) {
			return;
		}

		$this->db->query("UPDATE `" . DB_PREFIX . "mc_redirect` SET `hits` = `hits` + 1, `last_hit` = NOW() WHERE `redirect_id` = '" . (int)$row['redirect_id'] . "'");

		$this->response->redirect($target, (int)$row['code'] === 302 ? 302 : 301);
	}
}
