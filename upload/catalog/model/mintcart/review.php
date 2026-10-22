<?php
/**
 * Store-wide approved reviews (all products), newest first - the data behind
 * the "Customer reviews" page (`mintcart/reviews`) and the Testimonials home
 * block. OpenCart's own `catalog/review` model only lists reviews of one
 * product. Same visibility rules as that model: review and product enabled,
 * product available and in the current store.
 */
class ModelMintcartReview extends Model {
	/**
	 * @return array<int, array<string, mixed>> review_id, author, rating, text,
	 *         date_added, product_id, product_name, product_image
	 */
	public function getReviews($start = 0, $limit = 10) {
		$query = $this->db->query("SELECT r.review_id, r.author, r.rating, r.text, r.date_added, p.product_id, p.image AS product_image, pd.name AS product_name FROM " . DB_PREFIX . "review r " . $this->joins() . " ORDER BY r.date_added DESC, r.review_id DESC LIMIT " . max(0, (int)$start) . "," . max(1, (int)$limit));

		return $query->rows;
	}

	public function getTotalReviews() {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "review r " . $this->joins());

		return (int)$query->row['total'];
	}

	/**
	 * Number of reviews and the average rating, plus the count per star
	 * (5 down to 1) - the summary at the top of the reviews page.
	 *
	 * @return array{total: int, average: float, stars: array<int, int>}
	 */
	public function getSummary() {
		$query = $this->db->query("SELECT COUNT(*) AS total, AVG(r.rating) AS average, SUM(r.rating = 5) AS s5, SUM(r.rating = 4) AS s4, SUM(r.rating = 3) AS s3, SUM(r.rating = 2) AS s2, SUM(r.rating = 1) AS s1 FROM " . DB_PREFIX . "review r " . $this->joins());

		$row = $query->row;

		return array(
			'total'   => (int)$row['total'],
			'average' => $row['total'] ? round((float)$row['average'], 1) : 0.0,
			'stars'   => array(5 => (int)$row['s5'], 4 => (int)$row['s4'], 3 => (int)$row['s3'], 2 => (int)$row['s2'], 1 => (int)$row['s1'])
		);
	}

	private function joins() {
		return "JOIN " . DB_PREFIX . "product p ON (p.product_id = r.product_id) JOIN " . DB_PREFIX . "product_description pd ON (pd.product_id = p.product_id AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "') JOIN " . DB_PREFIX . "product_to_store p2s ON (p2s.product_id = p.product_id AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "') WHERE r.status = '1' AND p.status = '1' AND p.date_available <= NOW()";
	}
}
