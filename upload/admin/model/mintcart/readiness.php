<?php
/**
 * Gathers the raw OpenCart signals `MintCart\Onboarding\StoreReadiness`
 * needs (MINTCART_TECHNICAL_PLAN.md §22, MINTCART_IMPLEMENTATION_GUIDE.md
 * §55). Deliberately thin: every signal here is read from OpenCart's own
 * existing storage — config settings, `oc_language`/`oc_currency` status,
 * the `extension` table plus its per-extension `<type>_<code>_status`
 * setting (the same convention every shipping/payment extension controller
 * already uses, e.g. `extension/shipping/flat.php`'s `shipping_flat_status`),
 * and `catalog/product`'s own `getTotalProducts()` — no new Mint-specific
 * table for any of this, per CLAUDE.md §39/§6. `StoreReadiness::evaluate()`
 * itself has no OpenCart dependency; this model is the only place that
 * knows where each boolean actually comes from.
 */
class ModelMintcartReadiness extends Model {
	public function getSignals() {
		$this->load->model('setting/extension');
		$this->load->model('catalog/product');

		return array(
			'logo'          => (bool)$this->config->get('config_logo'),
			'contacts'      => $this->hasContacts(),
			'language'      => $this->hasEnabledLanguage(),
			'currency'      => $this->hasEnabledCurrency(),
			'shipping'      => $this->hasEnabledExtension('shipping'),
			'payment'       => $this->hasEnabledExtension('payment'),
			'first_product' => $this->model_catalog_product->getTotalProducts(array('filter_status' => 1)) > 0
		);
	}

	private function hasContacts() {
		return (bool)$this->config->get('config_telephone')
			&& (bool)$this->config->get('config_email')
			&& (bool)$this->config->get('config_address');
	}

	private function hasEnabledLanguage() {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "language` WHERE `status` = '1'");

		return $query->row['total'] > 0;
	}

	private function hasEnabledCurrency() {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "currency` WHERE `status` = '1'");

		return $query->row['total'] > 0;
	}

	// `setting/extension`'s own `getInstalled($type)` returns every
	// installed extension code for that type, but "installed" isn't
	// "enabled" — each one has its own `<type>_<code>_status` config flag
	// (set by that extension's own admin controller when saved), so
	// readiness needs at least one *enabled* one, not merely one installed.
	// Extracted to MintCart\Compatibility\OpenCart3\ExtensionAdapter so a
	// future Business -> Delivery/Payments facade can reuse the same
	// installed-vs-enabled check instead of re-deriving it.
	private function hasEnabledExtension($type) {
		$adapter = new \MintCart\Compatibility\OpenCart3\ExtensionAdapter($this->config, $this->model_setting_extension);

		return $adapter->hasEnabled($type);
	}
}
