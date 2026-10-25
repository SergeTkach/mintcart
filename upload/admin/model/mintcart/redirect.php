<?php
/**
 * `mc_redirect` for SEO -> Redirects: the list, manual redirects, and the automatic ones written
 * when the SEO URL keyword of a product / category / page / brand is changed.
 */
class ModelMintcartRedirect extends Model {
	public function total() {
		return (int)$this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "mc_redirect`")->row['total'];
	}

	public function getList($start, $limit) {
		return $this->db->query("SELECT * FROM `" . DB_PREFIX . "mc_redirect` ORDER BY `redirect_id` DESC LIMIT " . (int)$start . "," . (int)$limit)->rows;
	}

	public function addManual($old_path, $target_url, $code, $language_id) {
		$this->put(0, (int)$language_id, $old_path, '', '', $target_url, $code, 'manual');
	}

	public function delete($redirect_id) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "mc_redirect` WHERE `redirect_id` = '" . (int)$redirect_id . "'");
	}

	private function put($store_id, $language_id, $old_path, $route, $args, $target_url, $code, $source) {
		$old_path = \MintCart\Seo\RedirectPlanner::oldPath($old_path);

		if ($old_path === '') {
			return;
		}

		$this->db->query("INSERT INTO `" . DB_PREFIX . "mc_redirect` SET `store_id` = '" . (int)$store_id . "', `language_id` = '" . (int)$language_id . "', `old_path` = '" . $this->db->escape(utf8_substr($old_path, 0, 191)) . "', `route` = '" . $this->db->escape($route) . "', `args` = '" . $this->db->escape($args) . "', `target_url` = '" . $this->db->escape($target_url) . "', `code` = '" . ((int)$code === 302 ? 302 : 301) . "', `source` = '" . $this->db->escape($source) . "', `date_added` = NOW() ON DUPLICATE KEY UPDATE `route` = VALUES(`route`), `args` = VALUES(`args`), `target_url` = VALUES(`target_url`), `code` = VALUES(`code`), `source` = VALUES(`source`)");
	}

	/**
	 * Called right before an entity is saved with the posted keywords: a keyword that is about to change
	 * becomes a redirect to the page, and a keyword that is about to be used stops being a redirect.
	 *
	 * @param string $key      the seo_url query key of the entity: product_id, category_id, ...
	 * @param string $route    the page's route
	 * @param string $arg      the link argument that identifies the page (path for a category)
	 * @param array  $posted   store_id => language_id => keyword, as posted by the form
	 */
	public function trackKeywordChanges($key, $id, $route, $arg, array $posted) {
		$id = (int)$id;

		$old = $this->db->query("SELECT `store_id`, `language_id`, `keyword` FROM `" . DB_PREFIX . "seo_url` WHERE `query` = '" . $this->db->escape($key . '=' . $id) . "'")->rows;

		foreach ($old as $row) {
			$new = isset($posted[$row['store_id']][$row['language_id']]) ? trim((string)$posted[$row['store_id']][$row['language_id']]) : '';

			if ($row['keyword'] !== '' && $new !== '' && $new !== $row['keyword']) {
				$this->put((int)$row['store_id'], (int)$row['language_id'], $row['keyword'], $route, $arg . '=' . $id, '', 301, 'auto');
			}
		}

		// A keyword that a page has again must not redirect away.
		foreach ($posted as $store_id => $languages) {
			foreach ((array)$languages as $keyword) {
				$keyword = \MintCart\Seo\RedirectPlanner::oldPath((string)$keyword);

				if ($keyword !== '') {
					$this->db->query("DELETE FROM `" . DB_PREFIX . "mc_redirect` WHERE `old_path` = '" . $this->db->escape($keyword) . "' AND `store_id` = '" . (int)$store_id . "'");
				}
			}
		}
	}
}
