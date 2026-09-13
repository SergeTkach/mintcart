<?php
/**
 * `MintCart\Core\HealthCheck` wiring (MINTCART_TECHNICAL_PLAN.md §4/§33) —
 * "is this MintCart install healthy right now", computed from real PHP/
 * filesystem/migration signals (`ModelMintcartHealth`), never hardcoded.
 *
 * route=mintcart/health — GET, read-only, fully additive (no existing
 * OpenCart admin controller/route touched). This is HealthCheck's first
 * real consumer (CLAUDE.md §85 — the API exists because this endpoint
 * needs it); a Diagnostics screen and the future Update Manager's own
 * final "health check" step (Technical Plan §33) are the expected next
 * callers of this same JSON contract, not a second copy of the checks.
 */
class ControllerMintcartHealth extends Controller {
	// route=mintcart/health
	public function index() {
		$this->load->language('mintcart/health');

		$json = array();

		if (!$this->user->hasPermission('access', 'mintcart/health')) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$this->load->model('mintcart/health');

			$health = new \MintCart\Core\HealthCheck();

			$labels = array();

			foreach (array(
				'php_version', 'migrations', 'mint_version'
			) as $key) {
				$labels[$key] = $this->language->get('text_check_' . $key);
			}

			foreach (\MintCart\Core\HealthCheck::REQUIRED_EXTENSIONS as $name) {
				$labels['extension_' . $name] = sprintf($this->language->get('text_check_extension'), $name);
			}

			foreach (\MintCart\Core\HealthCheck::REQUIRED_WRITABLE_PATHS as $path) {
				$labels['writable_' . $path['key']] = sprintf($this->language->get('text_check_writable'), $path['relative']);
			}

			$json = $health->evaluate($this->model_mintcart_health->getSignals(), $labels);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
