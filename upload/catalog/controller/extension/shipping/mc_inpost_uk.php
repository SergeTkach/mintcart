<?php
/**
 * JSON endpoint for the InPost UK checkout form (lockers near a postcode).
 * The API call and the session bookkeeping live in the model.
 */
class ControllerExtensionShippingMcInpostUk extends Controller {
	// route=extension/shipping/mc_inpost_uk/search - GET term (a postcode)
	public function search() {
		$this->load->model('extension/shipping/mc_inpost_uk');

		$term = isset($this->request->get['term']) ? (string)$this->request->get['term'] : '';

		$items = $this->config->get('shipping_mc_inpost_uk_status') ? $this->model_extension_shipping_mc_inpost_uk->searchPoints($term) : array();

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode(array('items' => $items)));
	}
}
