<?php
/**
 * Write access to the Phase 15 (Commerce API) `mc_quick_order` storage
 * (MINTCART_TECHNICAL_PLAN.md §28, migration
 * 202609041200_create_mc_quick_order.php).
 *
 * Plain OpenCart Model, not a MintCart\* namespaced class — same convention
 * `ModelMintcartBlock` already established for a brand-new Mint-owned table
 * with no legacy OpenCart model to adapt (CLAUDE.md §38).
 */
class ModelMintcartQuickorder extends Model {
	/**
	 * @param array{
	 *     product_id: int,
	 *     product_name: string,
	 *     quantity: int,
	 *     name: string,
	 *     telephone: string,
	 *     email: string,
	 *     store_id: int,
	 *     language_id: int,
	 *     ip: string
	 * } $data Already-validated/normalized fields — this method does not
	 *        re-run `MintCart\Commerce\QuickOrderService::validate()`.
	 *
	 * @return int The new `quick_order_id`.
	 */
	public function addRequest($data) {
		$this->db->query("INSERT INTO " . DB_PREFIX . "mc_quick_order SET
			`product_id` = '" . (int)$data['product_id'] . "',
			`product_name` = '" . $this->db->escape($data['product_name']) . "',
			`quantity` = '" . (int)$data['quantity'] . "',
			`name` = '" . $this->db->escape($data['name']) . "',
			`telephone` = '" . $this->db->escape($data['telephone']) . "',
			`email` = '" . $this->db->escape($data['email']) . "',
			`store_id` = '" . (int)$data['store_id'] . "',
			`language_id` = '" . (int)$data['language_id'] . "',
			`ip` = '" . $this->db->escape($data['ip']) . "',
			`status` = 'new',
			`date_added` = NOW()
		");

		return (int)$this->db->getLastId();
	}
}
