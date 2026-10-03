<?php
/**
 * `Sale → Quick Order Requests` (`MINTCART_FUNCTIONAL_SCOPE.md` §13,
 * `MINTCART_TECHNICAL_PLAN.md` §28, `MINTCART_IMPLEMENTATION_GUIDE.md` §60).
 *
 * route=mintcart/quickorder. Fully additive — no existing OpenCart
 * controller/model/route touched (`CLAUDE.md` §8/§37); the one existing
 * file this increment modifies, `common/column_left.php`, only gains one
 * new conditional menu entry (see `docs/upstream-diff.md`).
 *
 * `docs/developer/commerce/quick-order-service.md`'s "Not built" section
 * named this as the natural next consumer of the `mc_quick_order` table:
 * requests were already being recorded by `ControllerMintcartQuickorder`
 * (the catalog-side submit endpoint, `mintcart/quickorder/submit`) with no
 * way for staff to see or act on them. This screen is read/status-only —
 * it never edits the customer-submitted fields
 * (`product_id`/`product_name`/`quantity`/`name`/`telephone`/`email`),
 * only `status`, the one field this table's own docblock describes as
 * "staff follow-up", not an order status. Lead -> real-order conversion
 * stays deliberately out of scope (same doc, "Not done yet").
 *
 * Same simple form-POST-then-redirect shape `ControllerCatalogProduct::bulkUpdate()`
 * (Phase 16) already established for a narrow, single-purpose admin
 * action — one `<select>` + Save per row, not a JS/AJAX status editor, and
 * no new admin asset bundle enqueued (`docs/developer/admin/asset-enqueue.md`'s
 * "only call this on the specific action(s) that actually need the
 * bundle" — a plain list+status-select screen doesn't).
 *
 * Built on the legacy OpenCart admin theme (Bootstrap 3 `.panel`/`.btn`/
 * `.table` markup), same reason every other MintCart admin list screen in
 * this repo gives.
 */
class ControllerMintcartQuickorder extends Controller {
	public function index() {
		$this->load->language('mintcart/quickorder');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('mintcart/quickorder');

		$this->getList();
	}

	// route=mintcart/quickorder.status - POST quick_order_id, status
	public function status() {
		$this->load->language('mintcart/quickorder');

		$this->load->model('mintcart/quickorder');

		if ($this->validateStatus()) {
			$this->model_mintcart_quickorder->editStatus(
				(int)$this->request->post['quick_order_id'],
				$this->request->post['status']
			);

			$this->session->data['success'] = $this->language->get('text_success');
		}

		$this->response->redirect($this->url->link('mintcart/quickorder', 'user_token=' . $this->session->data['user_token'] . $this->listUrl(), true));
	}

	protected function validateStatus() {
		if (!$this->user->hasPermission('modify', 'mintcart/quickorder')) {
			$this->session->data['error'] = $this->language->get('error_permission');

			return false;
		}

		$quick_order_id = isset($this->request->post['quick_order_id']) ? (int)$this->request->post['quick_order_id'] : 0;
		$status = isset($this->request->post['status']) ? (string)$this->request->post['status'] : '';

		if (!$quick_order_id || !\MintCart\Commerce\QuickOrderStatus::isValid($status)) {
			$this->session->data['error'] = $this->language->get('error_status');

			return false;
		}

		if (!$this->model_mintcart_quickorder->getRequest($quick_order_id)) {
			$this->session->data['error'] = $this->language->get('error_not_found');

			return false;
		}

		return true;
	}

	/**
	 * Query-string fragment (`&filter_status=...&sort=...&order=...&page=...`)
	 * every action's redirect appends - same "preserve the list's current
	 * view across a write action" pattern
	 * `ControllerCatalogProduct::bulkUpdate()`/`ControllerCatalogReview::delete()`
	 * already use.
	 */
	protected function listUrl() {
		$url = '';

		if (isset($this->request->get['filter_status'])) {
			$url .= '&filter_status=' . $this->request->get['filter_status'];
		}

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		return $url;
	}

	protected function getList() {
		$filter_status = isset($this->request->get['filter_status']) ? (string)$this->request->get['filter_status'] : '';

		if (isset($this->request->get['sort'])) {
			$sort = $this->request->get['sort'];
		} else {
			$sort = 'q.date_added';
		}

		if (isset($this->request->get['order'])) {
			$order = $this->request->get['order'];
		} else {
			$order = 'DESC';
		}

		if (isset($this->request->get['page'])) {
			$page = (int)$this->request->get['page'];
		} else {
			$page = 1;
		}

		$url = $this->listUrl();

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('mintcart/quickorder', 'user_token=' . $this->session->data['user_token'] . $url, true)
		);

		$filter_data = array(
			'filter_status' => $filter_status,
			'sort'          => $sort,
			'order'         => $order,
			'start'         => ($page - 1) * $this->config->get('config_limit_admin'),
			'limit'         => $this->config->get('config_limit_admin')
		);

		$request_total = $this->model_mintcart_quickorder->getTotalRequests($filter_data);

		$results = $this->model_mintcart_quickorder->getRequests($filter_data);

		$data['requests'] = array();

		foreach ($results as $result) {
			$data['requests'][] = array(
				'quick_order_id' => $result['quick_order_id'],
				'product_name'   => $result['product_name'],
				'quantity'       => $result['quantity'],
				'name'           => $result['name'],
				'telephone'      => $result['telephone'],
				'email'          => $result['email'],
				'status'         => $result['status'],
				'date_added'     => date($this->language->get('date_format_short'), strtotime($result['date_added'])),
				'action'         => $this->url->link('mintcart/quickorder.status', 'user_token=' . $this->session->data['user_token'] . $url, true)
			);
		}

		$data['statuses'] = array();

		foreach (\MintCart\Commerce\QuickOrderStatus::STATUSES as $status) {
			$data['statuses'][] = array(
				'value' => $status,
				'text'  => $this->language->get(\MintCart\Commerce\QuickOrderStatus::labelKey($status))
			);
		}

		$data['user_token'] = $this->session->data['user_token'];

		if (isset($this->session->data['error'])) {
			$data['error_warning'] = $this->session->data['error'];

			unset($this->session->data['error']);
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];

			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		$sort_url = '';

		if ($filter_status !== '') {
			$sort_url .= '&filter_status=' . $filter_status;
		}

		if ($order == 'ASC') {
			$sort_url .= '&order=DESC';
		} else {
			$sort_url .= '&order=ASC';
		}

		$data['sort_product'] = $this->url->link('mintcart/quickorder', 'user_token=' . $this->session->data['user_token'] . '&sort=q.product_name' . $sort_url, true);
		$data['sort_name'] = $this->url->link('mintcart/quickorder', 'user_token=' . $this->session->data['user_token'] . '&sort=q.name' . $sort_url, true);
		$data['sort_status'] = $this->url->link('mintcart/quickorder', 'user_token=' . $this->session->data['user_token'] . '&sort=q.status' . $sort_url, true);
		$data['sort_date_added'] = $this->url->link('mintcart/quickorder', 'user_token=' . $this->session->data['user_token'] . '&sort=q.date_added' . $sort_url, true);

		$pagination = new Pagination();
		$pagination->total = $request_total;
		$pagination->page = $page;
		$pagination->limit = $this->config->get('config_limit_admin');
		$pagination->url = $this->url->link('mintcart/quickorder', 'user_token=' . $this->session->data['user_token'] . '&filter_status=' . $filter_status . '&sort=' . $sort . '&order=' . $order . '&page={page}', true);

		$data['pagination'] = $pagination->render();

		$data['results'] = sprintf($this->language->get('text_pagination'), ($request_total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($request_total - $this->config->get('config_limit_admin'))) ? $request_total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $request_total, ceil($request_total / $this->config->get('config_limit_admin')));

		$data['filter_status'] = $filter_status;
		$data['sort'] = $sort;
		$data['order'] = $order;

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('mintcart/quickorder_list', $data));
	}
}
