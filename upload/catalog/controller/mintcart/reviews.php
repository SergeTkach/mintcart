<?php
/**
 * "Customer reviews" page: every approved review of the store, newest first,
 * with a summary on top (average rating and the split by stars), each review a
 * card with the product it is about. route=mintcart/reviews. The Testimonials
 * home block links here. Documentation: docs/developer/commerce/customer-reviews.md.
 *
 * The template lives in mint_default and is inherited by child themes through
 * the usual theme fallback.
 */
class ControllerMintcartReviews extends Controller {
	const PER_PAGE = 12;

	public function index() {
		$this->load->language('mintcart/reviews');
		$this->load->model('mintcart/review');
		$this->load->model('tool/image');

		$this->document->setTitle($this->language->get('heading_title'));

		$page = isset($this->request->get['page']) ? max(1, (int)$this->request->get['page']) : 1;

		$summary = $this->model_mintcart_review->getSummary();
		$total = $summary['total'];

		$data['summary'] = array(
			'total'   => $total,
			'average' => number_format($summary['average'], 1),
			'rounded' => (int)round($summary['average']),
			'bars'    => array()
		);

		foreach ($summary['stars'] as $stars => $count) {
			$data['summary']['bars'][] = array(
				'stars'   => $stars,
				'count'   => $count,
				'percent' => $total ? round($count / $total * 100) : 0
			);
		}

		$data['reviews'] = array();

		foreach ($this->model_mintcart_review->getReviews(($page - 1) * self::PER_PAGE, self::PER_PAGE) as $result) {
			$author = trim(html_entity_decode($result['author'], ENT_QUOTES, 'UTF-8'));

			$data['reviews'][] = array(
				'author'        => $result['author'],
				'initial'       => htmlspecialchars(utf8_strtoupper(utf8_substr($author, 0, 1)), ENT_QUOTES, 'UTF-8'),
				'rating'        => (int)$result['rating'],
				'text'          => nl2br($result['text']),
				'date_added'    => date($this->language->get('date_format_short'), strtotime($result['date_added'])),
				'product_name'  => $result['product_name'],
				'product_image' => $this->model_tool_image->resize($result['product_image'] ? $result['product_image'] : 'placeholder.png', 120, 120),
				'product_href'  => $this->url->link('product/product', 'product_id=' . $result['product_id'])
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
		$data['text_based_on'] = sprintf($this->language->get('text_based_on'), $total);
		$data['text_stars'] = $this->language->get('text_stars');
		$data['text_intro'] = $this->language->get('text_intro');

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('mintcart/reviews', $data));
	}
}
