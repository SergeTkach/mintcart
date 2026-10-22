<?php
/**
 * Admin access to the product badge set (`mc_badge`) and to which products
 * carry which badge (`mc_product_badge`). Migrations
 * 202610221200_create_mc_product_badge.php and
 * 202610221202_product_badge_definitions.php.
 *
 * The set is edited in Marketing → Product Badges (this model's CRUD); a
 * product gets its badges in the product form, which calls
 * getProductBadgeIds() / setProductBadges() / deleteProductBadges().
 * Validation of label and colour lives in `MintCart\Commerce\ProductBadge`.
 */
class ModelMintcartBadge extends Model {
	/**
	 * @return array<int, array<string, mixed>> The set, with the number of
	 *         products using each badge.
	 */
	public function getBadges() {
		$query = $this->db->query("SELECT b.*, (SELECT COUNT(*) FROM " . DB_PREFIX . "mc_product_badge pb WHERE pb.badge_id = b.badge_id) AS product_total FROM " . DB_PREFIX . "mc_badge b ORDER BY b.sort_order, b.badge_id");

		return $query->rows;
	}

	public function getBadge($badge_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "mc_badge WHERE badge_id = '" . (int)$badge_id . "'");

		return $query->row;
	}

	public function addBadge($label, $color, $sort_order) {
		$this->db->query("INSERT INTO " . DB_PREFIX . "mc_badge SET label = '" . $this->db->escape($label) . "', color = '" . $this->db->escape($color) . "', sort_order = '" . (int)$sort_order . "', date_added = NOW()");

		return $this->db->getLastId();
	}

	public function editBadge($badge_id, $label, $color, $sort_order) {
		$this->db->query("UPDATE " . DB_PREFIX . "mc_badge SET label = '" . $this->db->escape($label) . "', color = '" . $this->db->escape($color) . "', sort_order = '" . (int)$sort_order . "' WHERE badge_id = '" . (int)$badge_id . "'");
	}

	/** Deletes the badge and removes it from every product. */
	public function deleteBadge($badge_id) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "mc_product_badge WHERE badge_id = '" . (int)$badge_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "mc_badge WHERE badge_id = '" . (int)$badge_id . "'");
	}

	/** @return int[] */
	public function getProductBadgeIds($product_id) {
		$query = $this->db->query("SELECT badge_id FROM " . DB_PREFIX . "mc_product_badge WHERE product_id = '" . (int)$product_id . "'");

		return array_map('intval', array_column($query->rows, 'badge_id'));
	}

	/**
	 * Replaces the badges of one product. Ids that are not in the set are
	 * ignored.
	 *
	 * @param int[] $badge_ids
	 */
	public function setProductBadges($product_id, array $badge_ids) {
		$this->deleteProductBadges($product_id);

		$badge_ids = array_values(array_unique(array_filter(array_map('intval', $badge_ids))));

		if ($badge_ids) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "mc_product_badge (product_id, badge_id) SELECT '" . (int)$product_id . "', badge_id FROM " . DB_PREFIX . "mc_badge WHERE badge_id IN (" . implode(',', $badge_ids) . ")");
		}
	}

	public function deleteProductBadges($product_id) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "mc_product_badge WHERE product_id = '" . (int)$product_id . "'");
	}
}
