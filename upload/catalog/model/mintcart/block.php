<?php
/**
 * Read access to the Phase 10 (Homepage Builder) `mc_page`/`mc_block`
 * storage (MINTCART_TECHNICAL_PLAN.md §16-17, migrations
 * 202608231405_create_mc_pages.php / 202608231406_create_mc_blocks.php).
 *
 * Plain OpenCart Model, not a MintCart\* namespaced class — same convention
 * every other `upload/catalog/model/**` file follows, so it loads through
 * OpenCart's existing `$this->load->model('mintcart/block')` autoloader
 * without a MintCart-owned DB abstraction (CLAUDE.md §38: OpenCart-specific
 * calls stay behind an adapter only where a Compatibility layer already
 * exists; a brand-new Mint-owned table has no legacy OpenCart model to
 * adapt in the first place).
 *
 * `settings_json`/`content_json` are decoded here (JSON -> array) since
 * every consumer needs the decoded shape, not the raw column value — kept
 * as a model responsibility rather than duplicated per controller.
 */
class ModelMintcartBlock extends Model {
	/**
	 * @return array{page_id: int, code: string}|null Null when the page
	 *         does not exist or is disabled.
	 */
	public function getPage($code) {
		$query = $this->db->query("SELECT `page_id`, `code` FROM " . DB_PREFIX . "mc_page WHERE `code` = '" . $this->db->escape($code) . "' AND `status` = '1'");

		return $query->num_rows ? $query->row : null;
	}

	/**
	 * @return list<array{block_id: int, type: string, sort_order: int, settings: array<string, mixed>, content: array<string, mixed>}>
	 *         Enabled blocks for the given page, ordered by sort_order.
	 *         A block with no matching `mc_block_description` row for
	 *         `$language_id` still comes back, with `content` as an empty
	 *         array — a missing translation degrades to blank localized
	 *         fields (title, ...) rather than hiding the block entirely.
	 */
	public function getBlocks($page_id, $language_id) {
		$query = $this->db->query("SELECT b.`block_id`, b.`type`, b.`sort_order`, b.`settings_json`, bd.`content_json` FROM " . DB_PREFIX . "mc_block b LEFT JOIN " . DB_PREFIX . "mc_block_description bd ON (b.`block_id` = bd.`block_id` AND bd.`language_id` = '" . (int)$language_id . "') WHERE b.`page_id` = '" . (int)$page_id . "' AND b.`status` = '1' ORDER BY b.`sort_order` ASC");

		$blocks = array();

		foreach ($query->rows as $result) {
			$settings = json_decode($result['settings_json'], true);
			$content = json_decode((string)$result['content_json'], true);

			$blocks[] = array(
				'block_id'   => (int)$result['block_id'],
				'type'       => $result['type'],
				'sort_order' => (int)$result['sort_order'],
				'settings'   => is_array($settings) ? $settings : array(),
				'content'    => is_array($content) ? $content : array()
			);
		}

		return $blocks;
	}
}
