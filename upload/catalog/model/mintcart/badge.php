<?php
/**
 * Storefront read access to the badges assigned to products (`mc_product_badge`
 * joined with the set `mc_badge`; Marketing → Product Badges and the product form). One query for a whole product list, so a
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

		$query = $this->db->query("SELECT pb.product_id, b.label, b.color FROM " . DB_PREFIX . "mc_product_badge pb JOIN " . DB_PREFIX . "mc_badge b ON (b.badge_id = pb.badge_id) WHERE pb.product_id IN (" . implode(',', $product_ids) . ") ORDER BY b.sort_order, b.badge_id");

		$badges = array();

		foreach ($query->rows as $row) {
			$badges[(int)$row['product_id']][] = $row;
		}

		return $badges;
	}
}
