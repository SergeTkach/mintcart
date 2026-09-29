<?php
/**
 * Update Manager's `check` step, first real consumer
 * (`CLAUDE.md` §85) of `MintCart\Update\PackageManifest` +
 * `MintCart\Update\UpdateChecker` (`MINTCART_TECHNICAL_PLAN.md` §33 /
 * `MINTCART_IMPLEMENTATION_GUIDE.md` §69).
 *
 * route=mintcart/update/check — POST, `manifest` (raw package manifest
 * JSON text). Deliberately pasted rather than fetched from a remote update
 * server: MintCart has no update-server integration yet, and resolving
 * *how* to fetch a remote manifest is the flow's own `download` step, a
 * separate later increment — same "paste text in, avoid inventing an
 * upload/fetch mechanism this increment doesn't need" scope decision
 * `ControllerMintcartCompatibility` already made for `install.xml`.
 *
 * Only checks whether the pasted manifest describes an available,
 * compatible **MintCart Core** update (`\MintCart\Core\Version::VERSION`
 * vs. the manifest's own `version`, and its `requirements` against
 * `mint_version`/`php_version`) — never calls
 * `MintCart\Update\UpdateOrchestrator::apply()`. Actually applying a
 * manifest performs real file writes/migrations against the live install;
 * wiring that into an admin action is deliberately a separate, larger
 * increment (needs a resolved download URL and destination root this
 * check-only screen has no reason to require), matching every constituent
 * `Update\*` primitive's own "not attempted here" note
 * (`docs/developer/update/update-orchestrator.md`). A future
 * theme/extension update target (as opposed to MintCart Core itself) would
 * need its own "currently installed version" lookup — not attempted here,
 * no such registry exists yet (`CLAUDE.md` §85).
 *
 * No admin menu entry yet — same reasoning
 * `ControllerMintcartCompatibility`/`ControllerMintcartWizard`/
 * `ControllerMintcartReadiness` already documented: this route is reachable
 * directly for now, wiring it into `common/column_left.php` is separate,
 * later work.
 */
class ControllerMintcartUpdate extends Controller {
	// route=mintcart/update/check
	public function check() {
		$this->load->language('mintcart/update');

		$json = array();

		if (!$this->user->hasPermission('access', 'mintcart/update')) {
			$json['error'] = $this->language->get('error_permission');
			$this->respond($json);
			return;
		}

		$manifest_json = isset($this->request->post['manifest']) ? (string)$this->request->post['manifest'] : '';

		if (trim($manifest_json) === '') {
			$json['error'] = $this->language->get('error_empty');
			$this->respond($json);
			return;
		}

		$data = json_decode($manifest_json, true);

		if (!is_array($data)) {
			$json['error'] = $this->language->get('error_invalid_json');
			$this->respond($json);
			return;
		}

		try {
			$manifest = \MintCart\Update\PackageManifest::fromArray($data);

			$checker = new \MintCart\Update\UpdateChecker();

			$result = $checker->check(
				$manifest,
				\MintCart\Core\Version::VERSION,
				array(
					'mint_version' => \MintCart\Core\Version::VERSION,
					'php_version'  => PHP_VERSION
				)
			);

			$json += $result;
			$json['manifest'] = array(
				'name'    => $manifest->name(),
				'version' => $manifest->version()
			);
		} catch (\InvalidArgumentException $e) {
			$json['error'] = $e->getMessage();
		}

		$this->respond($json);
	}

	private function respond($json) {
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
