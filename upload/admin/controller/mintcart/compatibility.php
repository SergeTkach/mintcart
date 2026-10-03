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
 * Admin menu entry: `System → Compatibility Checker`
 * (`admin/controller/common/column_left.php`'s `System` group, right after
 * the existing `mintcart/settings/diagnostics` entry — no dedicated
 * "Advanced" top-level menu group exists in `column_left.php` yet, so this
 * follows the same precedent Diagnostics already set rather than inventing
 * one) — `index()` (below) is the rendered form/results screen the menu
 * entry links to; `check()` remains the standalone JSON API for a future
 * upload-based flow or REST client, unchanged.
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

		$json += $this->analyze($xml);

		$this->respond($json);
	}

	/**
	 * `System → Compatibility Checker` — a rendered form (paste
	 * install.xml, submit, see the same report `check()` returns as JSON)
	 * over the legacy admin theme, the same "GET shows the form / POST
	 * re-renders it with results" shape `ControllerMintcartBusinessContacts`
	 * already established, rather than a JS/AJAX consumer of `check()`
	 * (`docs/developer/admin/asset-enqueue.md`'s enqueue blocker doesn't
	 * apply to a plain form POST). `check()` itself is untouched — this is
	 * a second, independent consumer of the same underlying `analyze()`
	 * helper, not a replacement for the JSON API.
	 *
	 * route=mintcart/compatibility
	 */
	public function index() {
		$this->load->language('mintcart/compatibility');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_intro'] = $this->language->get('text_intro');
		$data['entry_xml'] = $this->language->get('entry_xml');
		$data['text_no_results'] = $this->language->get('text_no_results');
		$data['button_check'] = $this->language->get('button_check');

		foreach (array(
			'text_type_controller', 'text_type_template', 'text_type_other',
			'text_flag_theme', 'text_flag_conflict',
			'text_summary', 'text_summary_files', 'text_summary_controllers',
			'text_summary_templates', 'text_summary_theme', 'text_summary_bootstrap3',
			'text_summary_conflicts', 'text_bootstrap3_heading', 'text_conflicts_heading',
			'column_path', 'column_type', 'column_flags'
		) as $key) {
			$data[$key] = $this->language->get($key);
		}

		$data['error_warning'] = '';
		$data['xml'] = '';
		$data['manifest'] = null;
		$data['report'] = null;

		if (!$this->user->hasPermission('access', 'mintcart/compatibility')) {
			$data['error_warning'] = $this->language->get('error_permission');
		} elseif ($this->request->server['REQUEST_METHOD'] == 'POST') {
			$data['xml'] = isset($this->request->post['xml']) ? (string)$this->request->post['xml'] : '';

			if (trim($data['xml']) === '') {
				$data['error_warning'] = $this->language->get('error_empty');
			} else {
				$result = $this->analyze($data['xml']);

				if (isset($result['error'])) {
					$data['error_warning'] = $result['error'];
				} else {
					$data['manifest'] = $result['manifest'];
					$data['report'] = $result;
				}
			}
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('mintcart/compatibility', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('mintcart/compatibility', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true);

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('mintcart/compatibility', $data));
	}

	/**
	 * Shared by `check()` (JSON) and `index()` (rendered form) so the two
	 * consumers can never drift apart on what "analyze this install.xml"
	 * actually does — same `InstallManifestParser` + `CompatibilityChecker`
	 * pipeline either way. Caller already validated the permission/blank
	 * checks; this only ever returns either `{error: string}` or the full
	 * `{manifest: {...}} + CompatibilityChecker::check()` report.
	 *
	 * @return array<string, mixed>
	 */
	private function analyze(string $xml): array {
		try {
			$parser = new \MintCart\Compatibility\InstallManifestParser();
			$manifest = $parser->parse($xml);

			$checker = new \MintCart\Compatibility\CompatibilityChecker();
			$report = $checker->check($manifest);

			$result = array(
				'manifest' => array(
					'name'    => $manifest['name'],
					'code'    => $manifest['code'],
					'version' => $manifest['version'],
					'author'  => $manifest['author'],
					'link'    => $manifest['link']
				)
			);
			$result += $report;

			return $result;
		} catch (\InvalidArgumentException $e) {
			return array('error' => $e->getMessage());
		}
	}

	private function respond($json) {
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
