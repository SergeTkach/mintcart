<?php
/**
 * Read/status-update access to the Phase 15 (Commerce API) `mc_quick_order`
 * storage (`MINTCART_TECHNICAL_PLAN.md` §28, migration
 * `202609041200_create_mc_quick_order.php`) for the new
 * `Sale → Quick Order Requests` admin screen.
 *
 * Plain OpenCart Model, not a MintCart\* namespaced class — same
 * convention `ModelMintcartQuickorder` (the catalog-side write model,
 * `upload/catalog/model/mintcart/quickorder.php`) and `ModelMintcartBlock`
 * already established for a brand-new Mint-owned table with no legacy
 * OpenCart model to adapt (`CLAUDE.md` §38). This is the admin-side
 * sibling of that catalog-side model — same table, opposite MVC tree
 * (OpenCart 3 admin/catalog controllers each auto-load models from their
 * own `model/` directory, so the two `ModelMintcartQuickorder` classes
 * never collide: only one of the two processes ever has either one
 * loaded). The catalog-side model stays write-only (`addRequest()`); this
 * one is deliberately read/status-only — it never touches
 * `product_id`/`product_name`/`quantity`/`name`/`telephone`/`email`, the
 * fields a customer submitted, only the staff-owned `status` column.
 */
class ModelMintcartQuickorder extends Model {
	/**
	 * @param array{
	 *     filter_status?: string,
	 *     sort?: string,
	 *     order?: string,
	 *     start?: int,
	 *     limit?: int
	 * } $data
	 * @return array<int, array<string, mixed>>
	 */
	public function getRequests($data = array()) {
		$sql = "SELECT * FROM " . DB_PREFIX . "mc_quick_order q WHERE 1";

		if (!empty($data['filter_status'])) {
			$sql .= " AND q.status = '" . $this->db->escape($data['filter_status']) . "'";
		}

		$sort_data = array(
			'q.product_name',
			'q.name',
			'q.status',
			'q.date_added'
		);

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY " . $data['sort'];
		} else {
			$sql .= " ORDER BY q.date_added";
		}

		if (isset($data['order']) && ($data['order'] == 'ASC')) {
			$sql .= " ASC";
		} else {
			$sql .= " DESC";
		}

		if (isset($data['start']) || isset($data['limit'])) {
			if ($data['start'] < 0) {
				$data['start'] = 0;
			}

			if ($data['limit'] < 1) {
				$data['limit'] = 20;
			}

			$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
		}

		$query = $this->db->query($sql);

		return $query->rows;
	}

	/**
	 * @param array{filter_status?: string} $data
	 */
	public function getTotalRequests($data = array()) {
		$sql = "SELECT COUNT(*) AS total FROM " . DB_PREFIX . "mc_quick_order q WHERE 1";

		if (!empty($data['filter_status'])) {
			$sql .= " AND q.status = '" . $this->db->escape($data['filter_status']) . "'";
		}

		$query = $this->db->query($sql);

		return (int)$query->row['total'];
	}

	public function getRequest($quick_order_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "mc_quick_order WHERE quick_order_id = '" . (int)$quick_order_id . "'");

		return $query->row;
	}

	/**
	 * Caller must validate `$status` against
	 * `MintCart\Commerce\QuickOrderStatus::isValid()` first — this method
	 * writes whatever string it is given, the same "controller validates,
	 * model just writes" split `ModelCatalogProduct::updateProductField()`
	 * already established for `MintCart\Catalog\BulkProductUpdateValidator`.
	 */
	public function editStatus($quick_order_id, $status) {
		$this->db->query("UPDATE " . DB_PREFIX . "mc_quick_order SET status = '" . $this->db->escape($status) . "' WHERE quick_order_id = '" . (int)$quick_order_id . "'");
	}
}
