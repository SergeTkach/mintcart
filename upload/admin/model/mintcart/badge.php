<?php
/**
 * Admin access to `mc_product_badge` (migration
 * 202610221200_create_mc_product_badge.php) - manual product stickers,
 * Marketing → Product Badges. Plain OpenCart Model, same convention as
 * `ModelMintcartQuickorder`. Validation (label/colour rules) lives in
 * `MintCart\Commerce\ProductBadge`, not here.
 */
class ModelMintcartBadge extends Model {
	/**
	 * @return array<int, array<string, mixed>> Badges with the product name
	 *         in the admin language ('' when the product no longer exists).
	 */
	public function getBadges() {
		$query = $this->db->query("SELECT b.*, pd.name AS product_name FROM " . DB_PREFIX . "mc_product_badge b LEFT JOIN " . DB_PREFIX . "product_description pd ON (pd.product_id = b.product_id AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "') ORDER BY b.product_id, b.sort_order, b.badge_id");

		return $query->rows;
	}

	public function getBadge($badge_id) {
		$query = $this->db->query("SELECT b.*, pd.name AS product_name FROM " . DB_PREFIX . "mc_product_badge b LEFT JOIN " . DB_PREFIX . "product_description pd ON (pd.product_id = b.product_id AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "') WHERE b.badge_id = '" . (int)$badge_id . "'");

		return $query->row;
	}

	public function addBadge($product_id, $label, $color, $sort_order) {
		$this->db->query("INSERT INTO " . DB_PREFIX . "mc_product_badge SET product_id = '" . (int)$product_id . "', label = '" . $this->db->escape($label) . "', color = '" . $this->db->escape($color) . "', sort_order = '" . (int)$sort_order . "', date_added = NOW()");

		return $this->db->getLastId();
	}

	public function editBadge($badge_id, $label, $color, $sort_order) {
		$this->db->query("UPDATE " . DB_PREFIX . "mc_product_badge SET label = '" . $this->db->escape($label) . "', color = '" . $this->db->escape($color) . "', sort_order = '" . (int)$sort_order . "' WHERE badge_id = '" . (int)$badge_id . "'");
	}

	public function deleteBadge($badge_id) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "mc_product_badge WHERE badge_id = '" . (int)$badge_id . "'");
	}
}
