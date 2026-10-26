<?php
/**
 * JSON endpoints for the Ukrposhta checkout form (city search, post offices of
 * a city). The data and the session bookkeeping live in the model.
 */
class ControllerExtensionShippingMcUkrposhta extends Controller {
	// route=extension/shipping/mc_ukrposhta/cities - GET term
	public function cities() {
		$this->load->model('extension/shipping/mc_ukrposhta');

		$term = isset($this->request->get['term']) ? (string)$this->request->get['term'] : '';

		$this->respond(array('items' => $this->config->get('shipping_mc_ukrposhta_status') ? $this->model_extension_shipping_mc_ukrposhta->searchCities($term) : array()));
	}

	// route=extension/shipping/mc_ukrposhta/points - GET city
	public function points() {
		$this->load->model('extension/shipping/mc_ukrposhta');

		$city = isset($this->request->get['city']) ? (string)$this->request->get['city'] : '';

		$this->respond(array('items' => $this->config->get('shipping_mc_ukrposhta_status') ? $this->model_extension_shipping_mc_ukrposhta->getPoints($city) : array()));
	}

	private function respond($data) {
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($data));
	}
}
