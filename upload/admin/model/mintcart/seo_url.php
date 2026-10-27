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
	 * Whether the keyword is taken for an entity in this store and language.
	 *
	 * Uniqueness is per language (the same product may have the same keyword in every language) and,
	 * for categories, per parent: `clothes/mens` and `shoes/mens` can both exist. The scopes:
	 *  - `$parent_id` > 0 (a nested category): only its siblings (categories with the same parent) count;
	 *  - otherwise (products, pages, brands, top-level categories): the root level counts, i.e. every
	 *    keyword except those of nested categories.
	 * The incoming-URL decoder (catalog/controller/startup/seo_url.php) resolves a keyword the same way.
	 *
	 * @param string   $ignore_query the entity's own `query` (its own row is not a conflict)
	 * @param int|null $parent_id    parent category of a category, null for everything else
	 */
	public function keywordTaken($keyword, $store_id, $language_id, $ignore_query = '', $parent_id = null) {
		$sql = "SELECT `query` FROM `" . DB_PREFIX . "seo_url` WHERE `keyword` = '" . $this->db->escape($keyword) . "' AND `store_id` = '" . (int)$store_id . "' AND `language_id` = '" . (int)$language_id . "'";

		if ($ignore_query !== '') {
			$sql .= " AND `query` != '" . $this->db->escape($ignore_query) . "'";
		}

		$rows = $this->db->query($sql)->rows;

		if (!$rows) {
			return false;
		}

		$parents = $this->categoryParents(array_column($rows, 'query'));

		foreach ($rows as $row) {
			$nested_parent = isset($parents[$row['query']]) ? $parents[$row['query']] : 0;

			if ($parent_id > 0 ? $nested_parent === (int)$parent_id : $nested_parent === 0) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The parent category of every `category_id=N` query in the list (0 for a top-level category and
	 * for every query that is not a category).
	 *
	 * @param string[] $queries
	 * @return array<string, int> query => parent_id
	 */
	private function categoryParents(array $queries) {
		$ids = array();

		foreach ($queries as $query) {
			if (strpos($query, 'category_id=') === 0) {
				$ids[(int)substr($query, 12)] = $query;
			}
		}

		$result = array();

		if ($ids) {
			$rows = $this->db->query("SELECT `category_id`, `parent_id` FROM `" . DB_PREFIX . "category` WHERE `category_id` IN (" . implode(',', array_map('intval', array_keys($ids))) . ")")->rows;

			foreach ($rows as $row) {
				$result[$ids[(int)$row['category_id']]] = (int)$row['parent_id'];
			}
		}

		return $result;
	}

	/** The parent category of a category (its keyword scope); null for every other type. */
	public function parentOf($type, $id) {
		if ($type !== 'category') {
			return null;
		}

		$query = $this->db->query("SELECT `parent_id` FROM `" . DB_PREFIX . "category` WHERE `category_id` = '" . (int)$id . "'");

		return $query->num_rows ? (int)$query->row['parent_id'] : 0;
	}

	/** The free keyword for `$base` in this store and language (`base`, `base-2`, ...). */
	public function uniqueKeyword($base, $store_id, $language_id, $ignore_query = '', $parent_id = null) {
		return \MintCart\Seo\Slugger::unique($base, function ($keyword) use ($store_id, $language_id, $ignore_query, $parent_id) {
			return $this->keywordTaken($keyword, $store_id, $language_id, $ignore_query, $parent_id);
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
	 * Makes the missing keywords of one type in one language (the URL Generator screen, and a language just added
	 * in the Setup Wizard). Never touches a keyword that exists.
	 *
	 * @param string $template   a `UrlTemplates` template
	 * @param string $code       language code (the transliteration hint)
	 * @return int how many keywords were created
	 */
	public function generateMissing($type, $language_id, $code, $template, $limit) {
		$created = 0;

		foreach ($this->missing($type, 0, $language_id, $limit) as $id) {
			$base = \MintCart\Seo\UrlTemplates::keyword($template, $this->variables($type, $id, $language_id), $code);

			// An item without a usable name (or a nameless translation): the type and id.
			if ($base === '') {
				$base = $type . '-' . $id;
			}

			$this->add($this->queryOf($type, $id), $this->uniqueKeyword($base, 0, $language_id, '', $this->parentOf($type, $id)), 0, $language_id);

			$created++;
		}

		return $created;
	}

	/** Page route and link argument per type, for the redirect from a replaced keyword. */
	private function pageOf($type) {
		$pages = array(
			'product'      => array('product/product', 'product_id'),
			'category'     => array('product/category', 'path'),
			'information'  => array('information/information', 'information_id'),
			'manufacturer' => array('product/manufacturer/info', 'manufacturer_id')
		);

		return isset($pages[$type]) ? $pages[$type] : array('', '');
	}

	/**
	 * Rewrites the keywords of one type in one language to the template, for the next batch of entities
	 * after `$after_id`. A keyword that changes becomes a 301 redirect to the new one (SEO -> Redirects).
	 *
	 * @return array{last_id:int, done:bool, changed:int}
	 */
	public function regenerate($type, $language_id, $code, $template, $after_id, $limit) {
		list($table, $column) = $this->source($type);
		list($route, $arg) = $this->pageOf($type);

		$result = array('last_id' => (int)$after_id, 'done' => true, 'changed' => 0);

		if ($table === '') {
			return $result;
		}

		$this->load->model('mintcart/redirect');

		$ids = $this->db->query("SELECT `" . $column . "` AS id FROM `" . DB_PREFIX . $table . "` WHERE `" . $column . "` > '" . (int)$after_id . "' ORDER BY `" . $column . "` LIMIT " . (int)$limit)->rows;

		foreach ($ids as $row) {
			$id = (int)$row['id'];
			$query = $this->queryOf($type, $id);
			$base = \MintCart\Seo\UrlTemplates::keyword($template, $this->variables($type, $id, $language_id), $code);

			if ($base === '') {
				$base = $type . '-' . $id;
			}

			$keyword = $this->uniqueKeyword($base, 0, $language_id, $query, $this->parentOf($type, $id));

			$old = $this->db->query("SELECT `keyword` FROM `" . DB_PREFIX . "seo_url` WHERE `query` = '" . $this->db->escape($query) . "' AND `store_id` = '0' AND `language_id` = '" . (int)$language_id . "'");

			if (!$old->num_rows) {
				$this->add($query, $keyword, 0, $language_id);
				$result['changed']++;
			} elseif ($old->row['keyword'] !== $keyword) {
				$this->model_mintcart_redirect->trackKeywordChanges($this->keyOf($type), $id, $route, $arg, array(0 => array((int)$language_id => $keyword)));

				$this->db->query("UPDATE `" . DB_PREFIX . "seo_url` SET `keyword` = '" . $this->db->escape($keyword) . "' WHERE `query` = '" . $this->db->escape($query) . "' AND `store_id` = '0' AND `language_id` = '" . (int)$language_id . "'");
				$result['changed']++;
			}

			$result['last_id'] = $id;
		}

		$result['done'] = count($ids) < (int)$limit;

		return $result;
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
