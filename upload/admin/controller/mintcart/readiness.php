<?php
/**
 * `MintCart\Onboarding\StoreReadiness` wiring (MINTCART_TECHNICAL_PLAN.md
 * §22, MINTCART_IMPLEMENTATION_GUIDE.md §55) — a normalized "how ready is
 * this store to take an order" checklist + percentage, computed from real
 * OpenCart signals (`ModelMintcartReadiness`), never hardcoded.
 *
 * route=mintcart/readiness — GET, read-only, fully additive (no existing
 * OpenCart admin controller/route touched). This is StoreReadiness's first
 * real consumer (CLAUDE.md §85 — the API exists because this endpoint
 * needs it); a dashboard widget and the Setup Wizard's "Ready" step
 * (docs/IMPLEMENTATION_STATUS.md Phase 13/14) are the expected next
 * callers of this same JSON contract, not a second copy of the
 * computation.
 */
class ControllerMintcartReadiness extends Controller {
	// route=mintcart/readiness
	public function index() {
		$this->load->language('mintcart/readiness');

		$json = array();

		if (!$this->user->hasPermission('access', 'mintcart/readiness')) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$this->load->model('mintcart/readiness');

			$readiness = new \MintCart\Onboarding\StoreReadiness();

			$json = $readiness->evaluate($this->model_mintcart_readiness->getSignals(), array(
				'logo'          => $this->language->get('text_check_logo'),
				'contacts'      => $this->language->get('text_check_contacts'),
				'language'      => $this->language->get('text_check_language'),
				'currency'      => $this->language->get('text_check_currency'),
				'shipping'      => $this->language->get('text_check_shipping'),
				'payment'       => $this->language->get('text_check_payment'),
				'first_product' => $this->language->get('text_check_first_product')
			));
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
