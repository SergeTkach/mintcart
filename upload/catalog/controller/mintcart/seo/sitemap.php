<?php
/**
 * `mintcart/seo/sitemap` - the always-on XML sitemap (no Extensions > Feeds
 * install, no `feed_google_sitemap_status`), built by `MintCart\Seo\SitemapBuilder`
 * from the same product / category / manufacturer / information enumeration the
 * stock `ControllerExtensionFeedGoogleSitemap` uses.
 *
 * Two deliberate choices, both about a big catalog finishing in time:
 *  - no `<image:image>` blocks: resizing a picture per product on the fly is
 *    what used to stall the sitemap half way;
 *  - with language folders in the URL (docs/developer/seo/language-urls.md)
 *    every page is listed once per language with `<xhtml:link rel="alternate"
 *    hreflang>` to all of its versions, and the URLs come from one in-memory copy
 *    of `seo_url` (`MintCart\Seo\SitemapUrlMap`) instead of a query per link.
 *
 * Not done: splitting into a `sitemapIndex()` of several files past 50,000 URLs
 * (`urlset()` truncates safely, as the stock extension does).
 */
class ControllerMintcartSeoSitemap extends Controller {
	// route=mintcart/seo/sitemap
	public function index() {
		$this->load->model('catalog/product');
		$this->load->model('catalog/category');
		$this->load->model('catalog/manufacturer');
		$this->load->model('catalog/information');

		$map = $this->urlMap();

		// Each page: type, id and its own sitemap hints.
		$pages = array();

		foreach ($this->model_catalog_product->getProducts() as $product) {
			$pages[] = array('type' => 'product', 'id' => (int)$product['product_id'], 'lastmod' => date('Y-m-d\TH:i:sP', strtotime($product['date_modified'])), 'changefreq' => 'weekly', 'priority' => 1.0);
		}

		foreach ($this->categoryIds(0) as $category_id) {
			$pages[] = array('type' => 'category', 'id' => $category_id, 'changefreq' => 'weekly', 'priority' => 0.7);
		}

		foreach ($this->model_catalog_manufacturer->getManufacturers() as $manufacturer) {
			$pages[] = array('type' => 'manufacturer', 'id' => (int)$manufacturer['manufacturer_id'], 'changefreq' => 'weekly', 'priority' => 0.7);
		}

		foreach ($this->model_catalog_information->getInformations() as $information) {
			$pages[] = array('type' => 'information', 'id' => (int)$information['information_id'], 'changefreq' => 'weekly', 'priority' => 0.5);
		}

		$entries = array();

		foreach ($pages as $page) {
			$hints = array_diff_key($page, array('type' => 1, 'id' => 1));

			if (!$map) {
				$entries[] = array('loc' => $this->plainUrl($page)) + $hints;

				continue;
			}

			$urls = array();

			foreach ($map['codes'] as $code) {
				$urls[$code] = $this->mappedUrl($map['urls'], $code, $page);
			}

			$alternates = array();

			foreach ($urls as $code => $url) {
				$alternates[] = array('hreflang' => $code, 'href' => $url);
			}

			$alternates[] = array('hreflang' => 'x-default', 'href' => $urls[$map['default']]);

			foreach ($urls as $url) {
				$entries[] = array('loc' => $url, 'alternates' => $alternates) + $hints;
			}
		}

		$this->response->addHeader('Content-Type: application/xml');
		$this->response->setOutput((new \MintCart\Seo\SitemapBuilder())->urlset($entries));
	}

	/**
	 * @return array|null null without language folders (one language or SEO URLs off)
	 */
	private function urlMap() {
		$prefix = $this->load->controller('startup/mc_language_prefix/prefix');

		if (!$prefix) {
			return null;
		}

		$this->load->model('localisation/language');

		$codes = array();
		$by_id = array();

		foreach ($this->model_localisation_language->getLanguages() as $code => $language) {
			if ($language['status']) {
				$codes[] = $code;
				$by_id[(int)$language['language_id']] = $code;
			}
		}

		$keywords = array();

		$query = $this->db->query("SELECT `language_id`, `query`, `keyword` FROM `" . DB_PREFIX . "seo_url` WHERE `store_id` = '" . (int)$this->config->get('config_store_id') . "' AND `keyword` != '' AND (`query` LIKE 'product\\_id=%' OR `query` LIKE 'category\\_id=%' OR `query` LIKE 'manufacturer\\_id=%' OR `query` LIKE 'information\\_id=%')");

		foreach ($query->rows as $row) {
			if (isset($by_id[(int)$row['language_id']])) {
				$keywords[$by_id[(int)$row['language_id']]][$row['query']] = $row['keyword'];
			}
		}

		return array(
			'codes'   => $codes,
			'default' => $this->config->get('config_language'),
			'urls'    => new \MintCart\Seo\SitemapUrlMap($keywords, $this->config->get('config_url'), $prefix)
		);
	}

	private function mappedUrl($urls, $code, $page) {
		switch ($page['type']) {
			case 'product':
				return $urls->entity($code, 'product/product', 'product_id', $page['id']);
			case 'category':
				return $urls->category($code, (string)$page['id']);
			case 'manufacturer':
				return $urls->entity($code, 'product/manufacturer/info', 'manufacturer_id', $page['id']);
			default:
				return $urls->entity($code, 'information/information', 'information_id', $page['id']);
		}
	}

	private function plainUrl($page) {
		return html_entity_decode($this->plainLink($page), ENT_QUOTES, 'UTF-8');
	}

	// url->link() returns "&amp;"; the builder escapes the XML itself.
	private function plainLink($page) {
		switch ($page['type']) {
			case 'product':
				return $this->url->link('product/product', 'product_id=' . $page['id']);
			case 'category':
				return $this->url->link('product/category', 'path=' . $page['id']);
			case 'manufacturer':
				return $this->url->link('product/manufacturer/info', 'manufacturer_id=' . $page['id']);
			default:
				return $this->url->link('information/information', 'information_id=' . $page['id']);
		}
	}

	/**
	 * @return int[] enabled category ids, depth first
	 */
	protected function categoryIds($parent_id) {
		$ids = array();

		foreach ($this->model_catalog_category->getCategories($parent_id) as $result) {
			$ids[] = (int)$result['category_id'];
			$ids = array_merge($ids, $this->categoryIds($result['category_id']));
		}

		return $ids;
	}
}
