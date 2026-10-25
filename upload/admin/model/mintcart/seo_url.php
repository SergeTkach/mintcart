<?php
/**
 * SEO URL keywords for `SEO -> URL Generator` and the auto-fill in the product / category /
 * information / brand forms. Reads and writes the stock `seo_url` table (no second storage).
 */
class ModelMintcartSeoUrl extends Model {
	/** The seo_url `query` of an entity: product_id=5, category_id=12, information_id=3, manufacturer_id=2. */
	public function queryOf($type, $id) {
		return $this->keyOf($type) . '=' . (int)$id;
	}

	private function keyOf($type) {
		$keys = array('product' => 'product_id', 'category' => 'category_id', 'information' => 'information_id', 'manufacturer' => 'manufacturer_id');

		return isset($keys[$type]) ? $keys[$type] : '';
	}

	/**
	 * Whether the keyword is in use in this store - in ANY language: OpenCart's product / category /
	 * page forms refuse a keyword that another page, or another language of the same page, already has.
	 *
	 * @param string[] $avoid keywords typed in the form but not saved yet
	 */
	public function keywordTaken($keyword, $store_id, $ignore_query = '', array $avoid = array()) {
		if (in_array($keyword, $avoid, true)) {
			return true;
		}

		$sql = "SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "seo_url` WHERE `keyword` = '" . $this->db->escape($keyword) . "' AND `store_id` = '" . (int)$store_id . "'";

		if ($ignore_query !== '') {
			$sql .= " AND `query` != '" . $this->db->escape($ignore_query) . "'";
		}

		return (int)$this->db->query($sql)->row['total'] > 0;
	}

	/** The free keyword for `$base` in this store (`base`, `base-2`, ...). */
	public function uniqueKeyword($base, $store_id, $ignore_query = '', array $avoid = array()) {
		return \MintCart\Seo\Slugger::unique($base, function ($keyword) use ($store_id, $ignore_query, $avoid) {
			return $this->keywordTaken($keyword, $store_id, $ignore_query, $avoid);
		});
	}

	public function add($query, $keyword, $store_id, $language_id) {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "seo_url` SET `store_id` = '" . (int)$store_id . "', `language_id` = '" . (int)$language_id . "', `query` = '" . $this->db->escape($query) . "', `keyword` = '" . $this->db->escape($keyword) . "'");
	}

	/** Table and id column per type (for the "missing keyword" queries). */
	private function source($type) {
		$sources = array(
			'product'      => array('product', 'product_id'),
			'category'     => array('category', 'category_id'),
			'information'  => array('information', 'information_id'),
			'manufacturer' => array('manufacturer', 'manufacturer_id')
		);

		return isset($sources[$type]) ? $sources[$type] : array('', '');
	}

	private function missingWhere($type, $store_id, $language_id) {
		list($table, $column) = $this->source($type);

		return "FROM `" . DB_PREFIX . $table . "` e WHERE NOT EXISTS (SELECT 1 FROM `" . DB_PREFIX . "seo_url` s WHERE s.`query` = CONCAT('" . $this->keyOf($type) . "=', e.`" . $column . "`) AND s.`store_id` = '" . (int)$store_id . "' AND s.`language_id` = '" . (int)$language_id . "')";
	}

	/** How many entities of this type have no keyword in this store and language. */
	public function totalMissing($type, $store_id, $language_id) {
		list($table, $column) = $this->source($type);

		if ($table === '') {
			return 0;
		}

		return (int)$this->db->query("SELECT COUNT(*) AS total " . $this->missingWhere($type, $store_id, $language_id))->row['total'];
	}

	/** @return int[] ids without a keyword (a batch) */
	public function missing($type, $store_id, $language_id, $limit) {
		list($table, $column) = $this->source($type);

		if ($table === '') {
			return array();
		}

		$query = $this->db->query("SELECT e.`" . $column . "` AS id " . $this->missingWhere($type, $store_id, $language_id) . " ORDER BY e.`" . $column . "` LIMIT " . (int)$limit);

		return array_map('intval', array_column($query->rows, 'id'));
	}

	/**
	 * The template variables of one entity in one language (empty values for what the store does not have).
	 *
	 * @return array<string, string>
	 */
	public function variables($type, $id, $language_id) {
		$id = (int)$id;
		$language_id = (int)$language_id;
		$variables = array('name' => '', 'brand' => '', 'category' => '', 'model' => '', 'parent' => '');

		if ($type === 'product') {
			$query = $this->db->query("SELECT pd.`name`, p.`model`, m.`name` AS brand, (SELECT cd.`name` FROM `" . DB_PREFIX . "product_to_category` p2c LEFT JOIN `" . DB_PREFIX . "category_description` cd ON (cd.`category_id` = p2c.`category_id` AND cd.`language_id` = '" . $language_id . "') WHERE p2c.`product_id` = p.`product_id` AND cd.`name` IS NOT NULL ORDER BY p2c.`category_id` LIMIT 1) AS category FROM `" . DB_PREFIX . "product` p LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (pd.`product_id` = p.`product_id` AND pd.`language_id` = '" . $language_id . "') LEFT JOIN `" . DB_PREFIX . "manufacturer` m ON (m.`manufacturer_id` = p.`manufacturer_id`) WHERE p.`product_id` = '" . $id . "'");

			if ($query->num_rows) {
				$variables = array('name' => (string)$query->row['name'], 'brand' => (string)$query->row['brand'], 'category' => (string)$query->row['category'], 'model' => (string)$query->row['model'], 'parent' => '');
			}
		} elseif ($type === 'category') {
			$query = $this->db->query("SELECT cd.`name`, (SELECT pd2.`name` FROM `" . DB_PREFIX . "category_description` pd2 WHERE pd2.`category_id` = c.`parent_id` AND pd2.`language_id` = '" . $language_id . "') AS parent FROM `" . DB_PREFIX . "category` c LEFT JOIN `" . DB_PREFIX . "category_description` cd ON (cd.`category_id` = c.`category_id` AND cd.`language_id` = '" . $language_id . "') WHERE c.`category_id` = '" . $id . "'");

			if ($query->num_rows) {
				$variables['name'] = (string)$query->row['name'];
				$variables['parent'] = (string)$query->row['parent'];
			}
		} elseif ($type === 'information') {
			$query = $this->db->query("SELECT `title` FROM `" . DB_PREFIX . "information_description` WHERE `information_id` = '" . $id . "' AND `language_id` = '" . $language_id . "'");

			if ($query->num_rows) {
				$variables['name'] = (string)$query->row['title'];
			}
		} elseif ($type === 'manufacturer') {
			$query = $this->db->query("SELECT `name` FROM `" . DB_PREFIX . "manufacturer` WHERE `manufacturer_id` = '" . $id . "'");

			if ($query->num_rows) {
				$variables['name'] = (string)$query->row['name'];
			}
		}

		return $variables;
	}
}
