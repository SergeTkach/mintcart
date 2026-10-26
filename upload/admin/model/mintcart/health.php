<?php
/**
 * Gathers the raw environment signals `MintCart\Core\HealthCheck` needs
 * (MINTCART_TECHNICAL_PLAN.md §4/§33). Deliberately thin: `phpversion()`/
 * `extension_loaded()`/`is_writable()` are plain PHP, mirrored 1:1 from
 * `upload/install/controller/install/step_2.php`'s own requirement list
 * (read before writing this, per CLAUDE.md §10/§84) via
 * `MintCart\Core\HealthCheck::REQUIRED_EXTENSIONS`/`REQUIRED_WRITABLE_PATHS`
 * so the two lists cannot silently drift apart. `HealthCheck::evaluate()`
 * itself has no OpenCart dependency; this model is the only place that
 * knows how each signal is actually resolved.
 */
class ModelMintcartHealth extends Model {
	public function getSignals() {
		return array(
			'php_version'        => phpversion(),
			'extensions'         => $this->getExtensionSignals(),
			'writable'           => $this->getWritableSignals(),
			'pending_migrations' => $this->getPendingMigrationCount(),
			'mint_version'       => \MintCart\Core\Version::VERSION,
			'opencart_baseline'  => \MintCart\Core\Version::OPENCART_BASELINE
		);
	}

	private function getExtensionSignals() {
		$extensions = array();

		foreach (\MintCart\Core\HealthCheck::REQUIRED_EXTENSIONS as $name) {
			$extensions[$name] = extension_loaded($name);
		}

		return $extensions;
	}

	// The paths come from the config constants, not from DIR_SYSTEM/DIR_OPENCART
	// + a relative path: DIR_OPENCART exists only in the installer, and the
	// storage directory can live outside the web root.
	private function getWritableSignals() {
		$paths = array(
			'image'            => DIR_IMAGE,
			'image_cache'      => DIR_IMAGE . 'cache/',
			'image_catalog'    => DIR_IMAGE . 'catalog/',
			'storage_cache'    => DIR_CACHE,
			'storage_logs'     => DIR_LOGS,
			'storage_download' => DIR_DOWNLOAD,
			'storage_upload'   => DIR_UPLOAD,
			'storage_modification' => DIR_MODIFICATION
		);

		$writable = array();

		foreach (\MintCart\Core\HealthCheck::REQUIRED_WRITABLE_PATHS as $path) {
			$writable[$path['key']] = isset($paths[$path['key']]) && is_writable($paths[$path['key']]);
		}

		return $writable;
	}

	// A missing mc_migration table (nothing has ever run()) is itself a
	// meaningful, non-error signal: it means every discovered migration is
	// pending, same as MigrationManager::applied() now returns (it
	// ensureSchema()s itself first — see that class for why). No try/catch
	// needed here purely because of that fix.
	private function getPendingMigrationCount() {
		$manager = new \MintCart\Core\Migration\MigrationManager(
			$this->db,
			DB_PREFIX,
			DIR_SYSTEM . 'mintcart/migrations'
		);

		return count($manager->pending());
	}
}
