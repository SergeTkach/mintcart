<?php
/**
 * Homepage Builder admin editor (Phase 10) — write access to the `mc_page`/
 * `mc_block` storage created by
 * `upload/system/mintcart/migrations/202608231405_create_mc_pages.php` and
 * `202608231406_create_mc_blocks.php` (MINTCART_TECHNICAL_PLAN.md §16-17).
 *
 * Plain OpenCart Model, not MintCart\*-namespaced — same convention
 * `upload/catalog/model/mintcart/block.php` (`ModelMintcartBlock`, the
 * catalog-side read-only counterpart this file complements) already
 * follows. That model only reads *enabled* blocks for a live storefront
 * render; this one is the admin side, so it also has to see disabled rows
 * (an admin managing a hidden-but-not-yet-deleted block) and to write.
 *
 * `settings_json`/`content_json` are still plain JSON-encoded LONGTEXT
 * columns here (MINTCART_TECHNICAL_PLAN.md §2 — no MySQL JSON column type,
 * for shared-hosting compatibility), encoded/decoded in this model so no
 * controller has to know the storage representation.
 */
class ModelDesignHomepage extends Model {
	/**
	 * @return array{page_id: int, code: string, status: int}|null
	 */
	public function getPage($code) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "mc_page WHERE `code` = '" . $this->db->escape($code) . "'");

		return $query->num_rows ? $query->row : null;
	}

	/**
	 * Creates a page row (and its current-admin-language description) on
	 * first use — Homepage Builder ships with no seeded `mc_page` row, so
	 * the controller creates the `home` page lazily the first time this
	 * screen is opened, per the same "storage lands ahead of its consumer,
	 * first real consumer creates its own row" pattern already used
	 * elsewhere in this codebase (e.g. `ControllerCommonHome::buildMintBlocks()`
	 * just reads an existing page, this is the write side that was still
	 * missing).
	 *
	 * @return int the new page_id
	 */
	public function addPage($code, $name) {
		$this->db->query("INSERT INTO " . DB_PREFIX . "mc_page SET `code` = '" . $this->db->escape($code) . "', `status` = '1', `date_added` = NOW(), `date_modified` = NOW()");

		$page_id = $this->db->getLastId();

		$this->db->query("INSERT INTO " . DB_PREFIX . "mc_page_description SET `page_id` = '" . (int)$page_id . "', `language_id` = '" . (int)$this->config->get('config_language_id') . "', `name` = '" . $this->db->escape($name) . "'");

		return (int)$page_id;
	}

	/**
	 * @return list<array{block_id: int, type: string, sort_order: int, status: bool, settings: array<string, mixed>, content: array<string, mixed>}>
	 *         Every block on the page (enabled and disabled alike — unlike
	 *         `ModelMintcartBlock::getBlocks()`, which only ever returns
	 *         enabled ones for a live render), ordered by sort_order.
	 */
	public function getBlocks($page_id, $language_id) {
		$query = $this->db->query("SELECT b.`block_id`, b.`type`, b.`sort_order`, b.`status`, b.`settings_json`, bd.`content_json` FROM " . DB_PREFIX . "mc_block b LEFT JOIN " . DB_PREFIX . "mc_block_description bd ON (b.`block_id` = bd.`block_id` AND bd.`language_id` = '" . (int)$language_id . "') WHERE b.`page_id` = '" . (int)$page_id . "' ORDER BY b.`sort_order` ASC, b.`block_id` ASC");

		$blocks = array();

		foreach ($query->rows as $result) {
			$settings = json_decode($result['settings_json'], true);
			$content = json_decode((string)$result['content_json'], true);

			$blocks[] = array(
				'block_id'   => (int)$result['block_id'],
				'type'       => $result['type'],
				'sort_order' => (int)$result['sort_order'],
				'status'     => (bool)$result['status'],
				'settings'   => is_array($settings) ? $settings : array(),
				'content'    => is_array($content) ? $content : array()
			);
		}

		return $blocks;
	}

	/**
	 * @return array{block_id: int, page_id: int, type: string, status: int, settings: array<string, mixed>}|null
	 */
	public function getBlock($block_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "mc_block WHERE `block_id` = '" . (int)$block_id . "'");

		if (!$query->num_rows) {
			return null;
		}

		$row = $query->row;

		$settings = json_decode($row['settings_json'], true);
		$row['settings'] = is_array($settings) ? $settings : array();

		return $row;
	}

	/**
	 * @return array<int, array<string, mixed>> decoded content_json keyed
	 *         by language_id, for every language a description row exists
	 *         for (a language with no row simply isn't a key here — the
	 *         form treats that as blank fields for that language).
	 */
	public function getBlockDescriptions($block_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "mc_block_description WHERE `block_id` = '" . (int)$block_id . "'");

		$descriptions = array();

		foreach ($query->rows as $result) {
			$content = json_decode($result['content_json'], true);

			$descriptions[(int)$result['language_id']] = is_array($content) ? $content : array();
		}

		return $descriptions;
	}

	/**
	 * @param array{type: string, status: bool, settings: array<string, mixed>, content: array<int, array<string, mixed>>} $data
	 * @return int the new block_id
	 */
	public function addBlock($page_id, array $data) {
		$next_sort_order = $this->db->query("SELECT MAX(`sort_order`) AS `max_sort_order` FROM " . DB_PREFIX . "mc_block WHERE `page_id` = '" . (int)$page_id . "'");

		$sort_order = $next_sort_order->row['max_sort_order'] !== null ? ((int)$next_sort_order->row['max_sort_order'] + 1) : 0;

		$this->db->query("INSERT INTO " . DB_PREFIX . "mc_block SET `page_id` = '" . (int)$page_id . "', `type` = '" . $this->db->escape($data['type']) . "', `sort_order` = '" . (int)$sort_order . "', `status` = '" . ($data['status'] ? 1 : 0) . "', `settings_json` = '" . $this->db->escape(json_encode($data['settings'])) . "', `date_added` = NOW(), `date_modified` = NOW()");

		$block_id = (int)$this->db->getLastId();

		$this->saveDescriptions($block_id, $data['content']);

		return $block_id;
	}

	/**
	 * @param array{type: string, status: bool, settings: array<string, mixed>, content: array<int, array<string, mixed>>} $data
	 */
	public function editBlock($block_id, array $data) {
		$this->db->query("UPDATE " . DB_PREFIX . "mc_block SET `type` = '" . $this->db->escape($data['type']) . "', `status` = '" . ($data['status'] ? 1 : 0) . "', `settings_json` = '" . $this->db->escape(json_encode($data['settings'])) . "', `date_modified` = NOW() WHERE `block_id` = '" . (int)$block_id . "'");

		$this->db->query("DELETE FROM " . DB_PREFIX . "mc_block_description WHERE `block_id` = '" . (int)$block_id . "'");

		$this->saveDescriptions((int)$block_id, $data['content']);
	}

	/**
	 * @param array<int, array<string, mixed>> $content_by_language
	 */
	private function saveDescriptions($block_id, array $content_by_language) {
		foreach ($content_by_language as $language_id => $content) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "mc_block_description SET `block_id` = '" . (int)$block_id . "', `language_id` = '" . (int)$language_id . "', `content_json` = '" . $this->db->escape(json_encode($content)) . "'");
		}
	}

	public function deleteBlock($block_id) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "mc_block WHERE `block_id` = '" . (int)$block_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "mc_block_description WHERE `block_id` = '" . (int)$block_id . "'");
	}

	/** Enables / disables a block (the list screen's switch). */
	public function setStatus($page_id, $block_id, $status) {
		$this->db->query("UPDATE " . DB_PREFIX . "mc_block SET `status` = '" . ($status ? 1 : 0) . "', `date_modified` = NOW() WHERE `block_id` = '" . (int)$block_id . "' AND `page_id` = '" . (int)$page_id . "'");
	}

	/**
	 * Persists the list screen's per-row `sort_order` number inputs. The
	 * `page_id` guard on the UPDATE keeps a crafted `block_id` belonging to
	 * a different page from being reordered by this call.
	 *
	 * @param array<int|string, int|string> $sort_orders block_id => sort_order
	 */
	public function updateSortOrder($page_id, array $sort_orders) {
		foreach ($sort_orders as $block_id => $sort_order) {
			$this->db->query("UPDATE " . DB_PREFIX . "mc_block SET `sort_order` = '" . (int)$sort_order . "' WHERE `block_id` = '" . (int)$block_id . "' AND `page_id` = '" . (int)$page_id . "'");
		}
	}
}
