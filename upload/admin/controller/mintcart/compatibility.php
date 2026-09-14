<?php
/**
 * Compatibility Checker (`MINTCART_FUNCTIONAL_SCOPE.md` §29,
 * `MINTCART_IMPLEMENTATION_GUIDE.md` §70, `MINTCART_TECHNICAL_PLAN.md`
 * §39) — the first real consumer (`CLAUDE.md` §85) of
 * `MintCart\Compatibility\InstallManifestParser` +
 * `MintCart\Compatibility\CompatibilityChecker`: paste an OCMOD
 * `install.xml`'s contents, get back which files/controllers/templates it
 * touches, which of those collide with a file MintCart's own core already
 * modifies (`docs/upstream-diff.md`), and which look like they assume
 * Bootstrap 3 markup (the same marker list
 * `assets/src/css/compatibility/bootstrap3-shim.css` already treats as
 * needing a shim).
 *
 * Deliberately a heuristic-warning-only report, never a hard block
 * (Functional Scope §29: "Не блокувати install лише за heuristic
 * warning") — this endpoint never touches the real OCMOD install
 * pipeline, it only analyzes text the admin pastes in.
 *
 * route=mintcart/compatibility/check — POST, `xml` (raw install.xml
 * text). Deliberately pasted rather than uploaded as a file: avoids
 * adding file-upload/storage handling to this first increment. An
 * upload-based admin screen with a result list is a natural follow-up —
 * see `docs/developer/compatibility/compatibility-checker.md`.
 *
 * No admin menu entry yet: `admin/controller/common/column_left.php` is
 * currently being edited by three other open pull requests, each adding
 * its own Business/Design menu entry — so this route is reachable
 * directly for now, the same way `mintcart/wizard` and `mintcart/readiness`
 * shipped before their own menu entries existed.
 */
class ControllerMintcartCompatibility extends Controller {
	// route=mintcart/compatibility/check
	public function check() {
		$this->load->language('mintcart/compatibility');

		$json = array();

		if (!$this->user->hasPermission('access', 'mintcart/compatibility')) {
			$json['error'] = $this->language->get('error_permission');
			$this->respond($json);
			return;
		}

		$xml = isset($this->request->post['xml']) ? (string)$this->request->post['xml'] : '';

		if (trim($xml) === '') {
			$json['error'] = $this->language->get('error_empty');
			$this->respond($json);
			return;
		}

		try {
			$parser = new \MintCart\Compatibility\InstallManifestParser();
			$manifest = $parser->parse($xml);

			$checker = new \MintCart\Compatibility\CompatibilityChecker();
			$report = $checker->check($manifest);

			$json['manifest'] = array(
				'name'    => $manifest['name'],
				'code'    => $manifest['code'],
				'version' => $manifest['version'],
				'author'  => $manifest['author'],
				'link'    => $manifest['link']
			);
			$json += $report;
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
