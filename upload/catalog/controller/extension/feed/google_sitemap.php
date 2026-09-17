<?php
class ControllerExtensionFeedGoogleSitemap extends Controller {
	public function index() {
		if ($this->config->get('feed_google_sitemap_status')) {
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

			$entries = array_merge($entries, $this->getCategories(0));

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

			// MintCart\Seo\SitemapBuilder (MINTCART_FUNCTIONAL_SCOPE.md §16 /
			// MINTCART_TECHNICAL_PLAN.md §31, docs/developer/seo/sitemap.md)
			// replaces this controller's own hand-built XML string
			// concatenation with a single, tested, spec-valid XML assembler
			// shared with any future MintCart-owned sitemap route. Behavior
			// is unchanged for a merchant already using this stock OpenCart
			// extension: same entries, same escaping rules (SitemapBuilder's
			// escape() uses the identical ENT_XML1 | ENT_QUOTES pair this
			// controller already used by hand), same <image:image> shape --
			// see docs/upstream-diff.md for the full comparison.
			$sitemap_builder = new \MintCart\Seo\SitemapBuilder();

			$output = $sitemap_builder->urlset($entries);

			$this->response->addHeader('Content-Type: application/xml');
			$this->response->setOutput($output);
		}
	}

	/**
	 * @return array Sitemap entries (SitemapBuilder::urlset() shape), not
	 *         pre-rendered XML -- kept recursive/method-per-level identical
	 *         to the original implementation, only the return type changed.
	 */
	protected function getCategories($parent_id) {
		$entries = array();

		$results = $this->model_catalog_category->getCategories($parent_id);

		foreach ($results as $result) {
			$entries[] = array(
				'loc'        => $this->url->link('product/category', 'path=' . $result['category_id']),
				'changefreq' => 'weekly',
				'priority'   => 0.7
			);

			$entries = array_merge($entries, $this->getCategories($result['category_id']));
		}

		return $entries;
	}
}
