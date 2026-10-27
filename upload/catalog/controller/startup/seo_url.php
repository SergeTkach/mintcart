<?php
class ControllerStartupSeoUrl extends Controller {
	public function index() {
		// Add rewrite to url class
		if ($this->config->get('config_seo_url')) {
			$this->url->addRewrite($this);
		}

		// Decode URL
		if (isset($this->request->get['_route_'])) {
			$parts = explode('/', $this->request->get['_route_']);

			// remove any empty arrays from trailing
			if (utf8_strlen(end($parts)) == 0) {
				array_pop($parts);
			}

			// The category the previous segment resolved to (clothes/mens: `mens` is looked up among the
			// children of `clothes` first). null at the start and after any other kind of page.
			$parent_category = null;

			foreach ($parts as $part) {
				// MintCart fix MC-0009 (docs/fixes/registry.md): prefer the
				// seo_url row saved for the request's actual resolved
				// language (config_language_id is already set by
				// startup/startup.php, which runs before this pre-action -
				// see the fix registry entry for why). rewrite() below
				// already filters outgoing links by language_id; this
				// mirrors that on the incoming decode side, which
				// previously matched on keyword + store_id only and could
				// silently resolve to a different language's entity that
				// happens to share the same keyword text.
				//
				// No row for the current language - fall back to the
				// original language-agnostic match so a store that only
				// ever set up SEO keywords for one language keeps working
				// exactly as before for every other language.
				//
				// MintCart keyword scope (docs/developer/seo/keyword-scope.md): a keyword is unique per
				// language, and a nested category only among its siblings, so several rows can share
				// a keyword; findKeyword() picks the one that belongs to the URL's hierarchy.
				$row = $this->findKeyword($part, $parent_category);

				$query = new stdClass();
				$query->num_rows = $row ? 1 : 0;
				$query->row = $row ? $row : array();

				if ($query->num_rows) {
					$url = explode('=', $query->row['query']);

					$parent_category = $url[0] == 'category_id' ? (int)$url[1] : null;

					if ($url[0] == 'product_id') {
						$this->request->get['product_id'] = $url[1];
					}

					if ($url[0] == 'category_id') {
						if (!isset($this->request->get['path'])) {
							$this->request->get['path'] = $url[1];
						} else {
							$this->request->get['path'] .= '_' . $url[1];
						}
					}

					if ($url[0] == 'manufacturer_id') {
						$this->request->get['manufacturer_id'] = $url[1];
					}

					if ($url[0] == 'information_id') {
						$this->request->get['information_id'] = $url[1];
					}

					if ($query->row['query'] && $url[0] != 'information_id' && $url[0] != 'manufacturer_id' && $url[0] != 'category_id' && $url[0] != 'product_id') {
						$this->request->get['route'] = $query->row['query'];
					}
				} else {
					$this->request->get['route'] = 'error/not_found';

					break;
				}
			}

			if (!isset($this->request->get['route'])) {
				if (isset($this->request->get['product_id'])) {
					$this->request->get['route'] = 'product/product';
				} elseif (isset($this->request->get['path'])) {
					$this->request->get['route'] = 'product/category';
				} elseif (isset($this->request->get['manufacturer_id'])) {
					$this->request->get['route'] = 'product/manufacturer/info';
				} elseif (isset($this->request->get['information_id'])) {
					$this->request->get['route'] = 'information/information';
				}
			}
		}
	}

	/**
	 * The seo_url row of one URL segment: the current language first, then any language; among several
	 * rows with that keyword the child category of the previous segment's category wins, then a root-level
	 * row (not a nested category), then - so an old bookmark to a bare sub-category still works - any row.
	 *
	 * @return array<string, mixed>|null
	 */
	private function findKeyword($part, $parent_category) {
		$store_id = (int)$this->config->get('config_store_id');

		foreach (array((int)$this->config->get('config_language_id'), 0) as $language_id) {
			$rows = $this->db->query("SELECT * FROM " . DB_PREFIX . "seo_url WHERE keyword = '" . $this->db->escape($part) . "' AND store_id = '" . $store_id . "'" . ($language_id ? " AND language_id = '" . $language_id . "'" : "") . " ORDER BY seo_url_id")->rows;

			if (!$rows) {
				continue;
			}

			if (count($rows) == 1) {
				return $rows[0];
			}

			$parents = $this->categoryParents($rows);

			if ($parent_category) {
				foreach ($rows as $row) {
					if (isset($parents[$row['query']]) && $parents[$row['query']] == $parent_category) {
						return $row;
					}
				}
			}

			foreach ($rows as $row) {
				if (empty($parents[$row['query']])) {
					return $row;
				}
			}

			return $rows[0];
		}

		return null;
	}

	/**
	 * parent_id of each category_id=N row (query => parent_id); rows that are not categories are left out.
	 *
	 * @param array<int, array<string, mixed>> $rows
	 * @return array<string, int>
	 */
	private function categoryParents(array $rows) {
		$ids = array();

		foreach ($rows as $row) {
			if (strpos($row['query'], 'category_id=') === 0) {
				$ids[(int)substr($row['query'], 12)] = $row['query'];
			}
		}

		$parents = array();

		if ($ids) {
			$query = $this->db->query("SELECT category_id, parent_id FROM " . DB_PREFIX . "category WHERE category_id IN (" . implode(',', array_keys($ids)) . ")");

			foreach ($query->rows as $category) {
				$parents[$ids[(int)$category['category_id']]] = (int)$category['parent_id'];
			}
		}

		return $parents;
	}

	public function rewrite($link) {
		$url_info = parse_url(str_replace('&amp;', '&', $link));

		$url = '';

		$data = array();

		parse_str($url_info['query'], $data);

		foreach ($data as $key => $value) {
			if (isset($data['route'])) {
				if (($data['route'] == 'product/product' && $key == 'product_id') || (($data['route'] == 'product/manufacturer/info' || $data['route'] == 'product/product') && $key == 'manufacturer_id') || ($data['route'] == 'information/information' && $key == 'information_id')) {
					$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "seo_url WHERE `query` = '" . $this->db->escape($key . '=' . (int)$value) . "' AND store_id = '" . (int)$this->config->get('config_store_id') . "' AND language_id = '" . (int)$this->config->get('config_language_id') . "'");

					if ($query->num_rows && $query->row['keyword']) {
						$url .= '/' . $query->row['keyword'];

						unset($data[$key]);
					}
				} elseif ($key == 'path') {
					$categories = explode('_', $value);

					foreach ($categories as $category) {
						$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "seo_url WHERE `query` = 'category_id=" . (int)$category . "' AND store_id = '" . (int)$this->config->get('config_store_id') . "' AND language_id = '" . (int)$this->config->get('config_language_id') . "'");

						if ($query->num_rows && $query->row['keyword']) {
							$url .= '/' . $query->row['keyword'];
						} else {
							$url = '';

							break;
						}
					}

					unset($data[$key]);
				}
			}
		}

		// MintCart\Seo\SystemRoutes (MINTCART_FUNCTIONAL_SCOPE.md §4): a
		// fixed whitelist of "system pages" (cart, checkout, search, ...)
		// that carry no id parameter of their own, so the loop above never
		// matches them - only product_id/manufacturer_id/path/
		// information_id links get a pretty URL today. Incoming requests
		// already decode a plain keyword=>route seo_url row correctly (see
		// this class's own index() method, the generic branch after the
		// product_id/category_id/manufacturer_id/information_id checks);
		// this is the missing other half - generating that same pretty URL
		// when a controller calls $this->url->link('checkout/cart', ...).
		// Documented in docs/upstream-diff.md.
		if (!$url && isset($data['route']) && in_array($data['route'], \MintCart\Seo\SystemRoutes::ROUTES, true)) {
			$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "seo_url WHERE `query` = '" . $this->db->escape($data['route']) . "' AND store_id = '" . (int)$this->config->get('config_store_id') . "' AND language_id = '" . (int)$this->config->get('config_language_id') . "'");

			if ($query->num_rows && $query->row['keyword']) {
				$url .= '/' . $query->row['keyword'];

				unset($data['route']);
			}
		}

		if ($url) {
			unset($data['route']);

			$query = '';

			if ($data) {
				foreach ($data as $key => $value) {
					$query .= '&' . rawurlencode((string)$key) . '=' . rawurlencode((is_array($value) ? http_build_query($value) : (string)$value));
				}

				if ($query) {
					$query = '?' . str_replace('&', '&amp;', trim($query, '&'));
				}
			}

			return $url_info['scheme'] . '://' . $url_info['host'] . (isset($url_info['port']) ? ':' . $url_info['port'] : '') . str_replace('/index.php', '', $url_info['path']) . $url . $query;
		} else {
			return $link;
		}
	}
}
