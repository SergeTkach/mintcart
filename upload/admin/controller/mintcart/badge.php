<?php
/**
 * `Marketing → Product Badges` - the set of stickers ("Bestseller", "-20%")
 * a store owner can put on products (`MINTCART_FUNCTIONAL_SCOPE.md` §11,
 * storage `mc_badge`). Which products carry which badge is chosen in the
 * product form (Links tab), not here. Documentation:
 * docs/developer/commerce/product-badges.md.
 *
 * route=mintcart/badge. One page: the list of badges plus an add/edit form.
 * Plain form-POST-then-redirect like `ControllerMintcartQuickorder`.
 * Label/colour validation is `MintCart\Commerce\ProductBadge::normalize()`.
 */
class ControllerMintcartBadge extends Controller {
	public function index() {
		$this->load->language('mintcart/badge');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('mintcart/badge');

		$this->getPage();
	}

	// route=mintcart/badge/save - POST badge_id (0 = new), label, color, sort_order
	public function save() {
		$this->load->language('mintcart/badge');

		$this->load->model('mintcart/badge');

		$badge_id = isset($this->request->post['badge_id']) ? (int)$this->request->post['badge_id'] : 0;
		$sort_order = isset($this->request->post['sort_order']) ? (int)$this->request->post['sort_order'] : 0;

		$error = '';
		$badge = \MintCart\Commerce\ProductBadge::normalize($this->request->post);

		if (!$this->user->hasPermission('modify', 'mintcart/badge')) {
			$error = $this->language->get('error_permission');
		} elseif ($badge === null) {
			$error = $this->language->get('error_label');
		} elseif ($badge_id && !$this->model_mintcart_badge->getBadge($badge_id)) {
			$error = $this->language->get('error_not_found');
		}

		if ($error) {
			$this->session->data['error'] = $error;
		} else {
			if ($badge_id) {
				$this->model_mintcart_badge->editBadge($badge_id, $badge['label'], $badge['color'], $sort_order);
			} else {
				$this->model_mintcart_badge->addBadge($badge['label'], $badge['color'], $sort_order);
			}

			$this->session->data['success'] = $this->language->get('text_success');
		}

		$this->response->redirect($this->url->link('mintcart/badge', 'user_token=' . $this->session->data['user_token'], true));
	}

	// route=mintcart/badge/delete - POST badge_id
	public function delete() {
		$this->load->language('mintcart/badge');

		$this->load->model('mintcart/badge');

		if (!$this->user->hasPermission('modify', 'mintcart/badge')) {
			$this->session->data['error'] = $this->language->get('error_permission');
		} elseif (isset($this->request->post['badge_id'])) {
			$this->model_mintcart_badge->deleteBadge((int)$this->request->post['badge_id']);

			$this->session->data['success'] = $this->language->get('text_success');
		}

		$this->response->redirect($this->url->link('mintcart/badge', 'user_token=' . $this->session->data['user_token'], true));
	}

	protected function getPage() {
		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('mintcart/badge', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['badges'] = array();

		foreach ($this->model_mintcart_badge->getBadges() as $result) {
			$view = \MintCart\Commerce\ProductBadge::toView($result);

			$data['badges'][] = array(
				'badge_id'      => $result['badge_id'],
				'label'         => $result['label'],
				'color'         => $result['color'],
				'on_color'      => $view['on_color'],
				'sort_order'    => $result['sort_order'],
				'product_total' => $result['product_total']
			);
		}

		$data['action_save'] = $this->url->link('mintcart/badge/save', 'user_token=' . $this->session->data['user_token'], true);
		$data['action_delete'] = $this->url->link('mintcart/badge/delete', 'user_token=' . $this->session->data['user_token'], true);
		$data['user_token'] = $this->session->data['user_token'];
		$data['max_label_length'] = \MintCart\Commerce\ProductBadge::MAX_LABEL_LENGTH;

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

		$data['heading_title'] = $this->language->get('heading_title');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('mintcart/badge', $data));
	}
}
