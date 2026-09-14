<?php
/**
 * `MintCart\Onboarding\StoreReadiness` wiring (MINTCART_TECHNICAL_PLAN.md
 * §22, MINTCART_IMPLEMENTATION_GUIDE.md §55/§62 "dashboard widget") — a
 * dashboard-extension consumer of the already-shipped `mintcart/readiness`
 * computation, per `docs/developer/onboarding/store-readiness.md`'s own
 * "expected next callers" note.
 *
 * A standard OpenCart 3 **dashboard extension**, not OCMOD and not a Mint
 * core file: `admin/controller/common/dashboard.php` auto-discovers any
 * `admin/controller/extension/dashboard/*.php` file (via
 * `marketplace/extension.php`'s directory glob), and grants it an
 * "Install" button under Extensions -> Dashboard, the same way every
 * stock widget (`recent.php`, `online.php`, `sale.php`, ...) already
 * works. A store admin must still click Install + enable it once, exactly
 * like any other dashboard widget - this is deliberately the standard
 * OpenCart mechanism, not a new MintCart-specific one.
 *
 * Deliberately reuses `MintCart\Onboarding\StoreReadiness` +
 * `ModelMintcartReadiness::getSignals()` (both already shipped, Phase 14)
 * rather than re-deriving the checklist - `ControllerMintcartReadiness`'s
 * own JSON endpoint and this widget's `dashboard()` action now share one
 * computation, not two.
 *
 * Styled with the same stock Bootstrap 3 `.tile`/`.progress` classes every
 * other dashboard widget already uses (see `online_info.twig`/
 * `sale_info.twig`) - deliberately NOT `mc-*` component markup. MintCart's
 * own compiled bundle still isn't enqueued on any admin page
 * (`docs/developer/components/entity-picker.md` "Admin backends", Phase
 * 12/17's identical blocker) - a plain Bootstrap-3-styled widget sidesteps
 * that gap entirely rather than shipping unusable/unstyled `mc-*` markup
 * on a page that can't load its CSS.
 */
class ControllerExtensionDashboardMintcartReadiness extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/dashboard/mintcart_readiness');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('dashboard_mintcart_readiness', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=dashboard', true));
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=dashboard', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/dashboard/mintcart_readiness', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/dashboard/mintcart_readiness', 'user_token=' . $this->session->data['user_token'], true);

		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=dashboard', true);

		if (isset($this->request->post['dashboard_mintcart_readiness_width'])) {
			$data['dashboard_mintcart_readiness_width'] = $this->request->post['dashboard_mintcart_readiness_width'];
		} else {
			$data['dashboard_mintcart_readiness_width'] = $this->config->get('dashboard_mintcart_readiness_width');
		}

		$data['columns'] = array();

		for ($i = 3; $i <= 12; $i++) {
			$data['columns'][] = $i;
		}

		if (isset($this->request->post['dashboard_mintcart_readiness_status'])) {
			$data['dashboard_mintcart_readiness_status'] = $this->request->post['dashboard_mintcart_readiness_status'];
		} else {
			$data['dashboard_mintcart_readiness_status'] = $this->config->get('dashboard_mintcart_readiness_status');
		}

		if (isset($this->request->post['dashboard_mintcart_readiness_sort_order'])) {
			$data['dashboard_mintcart_readiness_sort_order'] = $this->request->post['dashboard_mintcart_readiness_sort_order'];
		} else {
			$data['dashboard_mintcart_readiness_sort_order'] = $this->config->get('dashboard_mintcart_readiness_sort_order');
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/dashboard/mintcart_readiness_form', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/dashboard/mintcart_readiness')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	// Called by ControllerCommonDashboard::index() once the widget is
	// installed + enabled - same contract every other dashboard widget's
	// dashboard() action follows (return rendered HTML, or nothing to be
	// skipped).
	public function dashboard() {
		$this->load->language('extension/dashboard/mintcart_readiness');
		$this->load->language('mintcart/readiness');

		$this->load->model('mintcart/readiness');

		$readiness = new \MintCart\Onboarding\StoreReadiness();

		$data = $readiness->evaluate($this->model_mintcart_readiness->getSignals(), array(
			'logo'          => $this->language->get('text_check_logo'),
			'contacts'      => $this->language->get('text_check_contacts'),
			'language'      => $this->language->get('text_check_language'),
			'currency'      => $this->language->get('text_check_currency'),
			'shipping'      => $this->language->get('text_check_shipping'),
			'payment'       => $this->language->get('text_check_payment'),
			'first_product' => $this->language->get('text_check_first_product')
		));

		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_ready_for_test_order'] = $this->language->get('text_ready_for_test_order');
		$data['text_not_ready_for_test_order'] = $this->language->get('text_not_ready_for_test_order');
		$data['wizard'] = $this->url->link('mintcart/wizard', 'user_token=' . $this->session->data['user_token'], true);
		$data['text_view'] = $this->language->get('text_view');

		return $this->load->view('extension/dashboard/mintcart_readiness_info', $data);
	}
}
