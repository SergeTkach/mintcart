<?php
/**
 * JSON endpoints for the Nova Poshta checkout form (city search, points of a
 * city). The data and the session bookkeeping live in the model.
 */
class ControllerExtensionShippingMcNovaposhta extends Controller {
	// route=extension/shipping/mc_novaposhta/cities - GET term
	public function cities() {
		$this->load->model('extension/shipping/mc_novaposhta');

		$term = isset($this->request->get['term']) ? (string)$this->request->get['term'] : '';

		$this->respond(array('items' => $this->config->get('shipping_mc_novaposhta_status') ? $this->model_extension_shipping_mc_novaposhta->searchCities($term) : array()));
	}

	// route=extension/shipping/mc_novaposhta/points - GET city, type=warehouse|postomat
	public function points() {
		$this->load->model('extension/shipping/mc_novaposhta');

		$city = isset($this->request->get['city']) ? (string)$this->request->get['city'] : '';
		$type = isset($this->request->get['type']) && $this->request->get['type'] === 'postomat' ? 'postomat' : 'warehouse';

		$this->respond(array('items' => $this->config->get('shipping_mc_novaposhta_status') ? $this->model_extension_shipping_mc_novaposhta->getPoints($city, $type) : array()));
	}

	private function respond($data) {
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($data));
	}
}
