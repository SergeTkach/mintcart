<?php
/**
 * Storefront read access to `mc_product_badge` (manual product stickers,
 * Marketing → Product Badges). One query for a whole product list, so a
 * listing of N cards costs one extra query, not N.
 */
class ModelMintcartBadge extends Model {
	/**
	 * @param int[] $product_ids
	 * @return array<int, array<int, array<string, mixed>>> product_id => badge rows in sort order
	 */
	public function getByProductIds(array $product_ids) {
		$product_ids = array_values(array_unique(array_filter(array_map('intval', $product_ids))));

		if (!$product_ids) {
			return array();
		}

		$query = $this->db->query("SELECT product_id, label, color FROM " . DB_PREFIX . "mc_product_badge WHERE product_id IN (" . implode(',', $product_ids) . ") ORDER BY sort_order, badge_id");

		$badges = array();

		foreach ($query->rows as $row) {
			$badges[(int)$row['product_id']][] = $row;
		}

		return $badges;
	}
}
