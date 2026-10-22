<?php
/**
 * "Customer reviews" page: every approved review of the store, newest first,
 * each linking to its product. route=mintcart/reviews. The Testimonials home
 * block (source "reviews") links here. Documentation:
 * docs/developer/commerce/customer-reviews.md.
 *
 * Reuses the product-page review markup conventions (`mc-reviews__*`,
 * `mc-rating`); the template lives in mint_default and is inherited by child
 * themes through the usual theme fallback.
 */
class ControllerMintcartReviews extends Controller {
	const PER_PAGE = 10;

	public function index() {
		$this->load->language('mintcart/reviews');
		$this->load->model('mintcart/review');

		$this->document->setTitle($this->language->get('heading_title'));

		$page = isset($this->request->get['page']) ? max(1, (int)$this->request->get['page']) : 1;

		$total = $this->model_mintcart_review->getTotalReviews();

		$data['reviews'] = array();

		foreach ($this->model_mintcart_review->getReviews(($page - 1) * self::PER_PAGE, self::PER_PAGE) as $result) {
			$data['reviews'][] = array(
				'author'       => $result['author'],
				'rating'       => (int)$result['rating'],
				'text'         => nl2br($result['text']),
				'date_added'   => date($this->language->get('date_format_short'), strtotime($result['date_added'])),
				'product_name' => $result['product_name'],
				'product_href' => $this->url->link('product/product', 'product_id=' . $result['product_id'])
			);
		}

		$data['breadcrumbs'] = array(
			array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/home')),
			array('text' => $this->language->get('heading_title'), 'href' => $this->url->link('mintcart/reviews'))
		);

		$pagination = new Pagination();
		$pagination->total = $total;
		$pagination->page = $page;
		$pagination->limit = self::PER_PAGE;
		$pagination->url = $this->url->link('mintcart/reviews', 'page={page}');

		$data['pagination'] = $pagination->render();
		$data['results'] = sprintf($this->language->get('text_pagination'), $total ? ($page - 1) * self::PER_PAGE + 1 : 0, min($total, $page * self::PER_PAGE), $total, ceil($total / self::PER_PAGE));

		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_empty'] = $this->language->get('text_empty');
		$data['text_rated'] = $this->language->get('text_rated');
		$data['text_about'] = $this->language->get('text_about');

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('mintcart/reviews', $data));
	}
}
