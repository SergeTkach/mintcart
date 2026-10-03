<?php
/**
 * MintCart\Seo\SitemapBuilder wiring (MINTCART_FUNCTIONAL_SCOPE.md §16,
 * MINTCART_TECHNICAL_PLAN.md §31, MINTCART_IMPLEMENTATION_GUIDE.md §65) —
 * the "genuinely first-class MintCart-owned `mintcart/seo/sitemap.xml`
 * route — always available, no extra extension install" `SitemapBuilder`'s
 * own docblock and docs/developer/seo/sitemap.md's "Not done yet" section
 * both named as the real remaining gap: the stock
 * `ControllerExtensionFeedGoogleSitemap` only works when a merchant finds
 * and enables `Extensions > Feeds > Google Sitemap` and its own
 * `feed_google_sitemap_status` setting, which is exactly the kind of
 * technical decision `MINTCART_PRODUCT_VISION.md` §5 says a shop owner
 * should not have to make.
 *
 * Deliberately a new, fully-additive controller under `mintcart/seo/`
 * (matching `upload/admin/controller/mintcart/seo/system_pages.php`'s
 * namespacing on the admin side) rather than a modification of
 * `extension/feed/google_sitemap.php` — same "new controller, not a patch
 * of the thing it replaces" rationale `ControllerMintcartSearch`/
 * `ControllerMintcartCart` already established. Reuses the exact same
 * `model_catalog_product`/`model_catalog_category`/
 * `model_catalog_manufacturer`/`model_catalog_information` enumeration the
 * stock extension already does (so the two, if both enabled, never
 * disagree on what a "page" is) and the same `SitemapBuilder::urlset()`
 * XML assembly, just always on, with no admin extension install required.
 *
 * What's deliberately not done here (see docs/developer/seo/sitemap.md
 * "Not done yet", unchanged by this run beyond the one bullet this
 * increment closes): `SitemapBuilder::sitemapIndex()`-based paging once a
 * catalog exceeds `MAX_URLS_PER_SITEMAP` (`urlset()` already truncates
 * safely at that limit, same as the stock extension does today — no
 * regression, just no multi-file split yet); self-registering a
 * `Sitemap:` line in `robots.txt` (OpenCart ships no `robots.txt`
 * management at all today, a separate, larger feature); and per-language
 * `hreflang` alternate links (`MintCart\Seo\HreflangBuilder` still has no
 * consumer anywhere, a separate follow-up per its own doc).
 */
class ControllerMintcartSeoSitemap extends Controller {
	// route=mintcart/seo/sitemap - always-on XML sitemap, no
	// feed_google_sitemap_status gate and no Extensions install required.
	public function index() {
		$entries = array();

		$this->load->model('catalog/product');
		$this->load->model('tool/image');

		$products = $this->model_catalog_product->getProducts();

		foreach ($products as $product) {
			$images = array();

			if ($product['image']) {
				$name = html_entity_decode($product['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8');

				$images[] = array(
					'loc'     => $this->model_tool_image->resize($product['image'], $this->config->get('theme_' . $this->config->get('config_theme') . '_image_popup_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_popup_height')),
					'caption' => $name,
					'title'   => $name
				);
			}

			$entries[] = array(
				'loc'        => $this->url->link('product/product', 'product_id=' . $product['product_id']),
				'lastmod'    => date('Y-m-d\TH:i:sP', strtotime($product['date_modified'])),
				'changefreq' => 'weekly',
				'priority'   => 1.0,
				'images'     => $images
			);
		}

		$this->load->model('catalog/category');

		$entries = array_merge($entries, $this->getCategoryEntries(0));

		$this->load->model('catalog/manufacturer');

		$manufacturers = $this->model_catalog_manufacturer->getManufacturers();

		foreach ($manufacturers as $manufacturer) {
			$entries[] = array(
				'loc'        => $this->url->link('product/manufacturer/info', 'manufacturer_id=' . $manufacturer['manufacturer_id']),
				'changefreq' => 'weekly',
				'priority'   => 0.7
			);
		}

		$this->load->model('catalog/information');

		$informations = $this->model_catalog_information->getInformations();

		foreach ($informations as $information) {
			$entries[] = array(
				'loc'        => $this->url->link('information/information', 'information_id=' . $information['information_id']),
				'changefreq' => 'weekly',
				'priority'   => 0.5
			);
		}

		$sitemap_builder = new \MintCart\Seo\SitemapBuilder();

		$output = $sitemap_builder->urlset($entries);

		$this->response->addHeader('Content-Type: application/xml');
		$this->response->setOutput($output);
	}

	/**
	 * @return array SitemapBuilder::urlset() entries, recursive by category
	 *         parent (same recursion shape
	 *         `ControllerExtensionFeedGoogleSitemap::getCategories()` uses).
	 */
	protected function getCategoryEntries($parent_id) {
		$entries = array();

		$results = $this->model_catalog_category->getCategories($parent_id);

		foreach ($results as $result) {
			$entries[] = array(
				'loc'        => $this->url->link('product/category', 'path=' . $result['category_id']),
				'changefreq' => 'weekly',
				'priority'   => 0.7
			);

			$entries = array_merge($entries, $this->getCategoryEntries($result['category_id']));
		}

		return $entries;
	}
}
