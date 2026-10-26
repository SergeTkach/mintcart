<?php
class ControllerCommonFooter extends Controller {
	public function index() {
		$this->load->language('common/footer');

		$this->load->model('catalog/information');

		$data['informations'] = array();

		foreach ($this->model_catalog_information->getInformations() as $result) {
			if ($result['bottom']) {
				$data['informations'][] = array(
					'title' => $result['title'],
					'href'  => $this->url->link('information/information', 'information_id=' . $result['information_id'])
				);
			}
		}

		$data['contact'] = $this->url->link('information/contact');
		$data['return'] = $this->url->link('account/return/add', '', true);
		$data['sitemap'] = $this->url->link('information/sitemap');
		$data['tracking'] = $this->url->link('information/tracking');
		$data['manufacturer'] = $this->url->link('product/manufacturer');
		$data['voucher'] = $this->url->link('account/voucher', '', true);
		$data['affiliate'] = $this->url->link('affiliate/login', '', true);
		$data['special'] = $this->url->link('product/special');
		$data['account'] = $this->url->link('account/account', '', true);
		$data['order'] = $this->url->link('account/order', '', true);
		$data['wishlist'] = $this->url->link('account/wishlist', '', true);
		$data['newsletter'] = $this->url->link('account/newsletter', '', true);

		$data['powered'] = sprintf($this->language->get('text_powered'), $this->config->get('config_name'), date('Y', time()));

		// MintCart\Design\ComponentRenderer / MintCart\Design\FooterViewProvider
		// wiring (MINTCART_TECHNICAL_PLAN.md §14, Phase 7). Only built when the
		// active theme ships a theme.json manifest (i.e. it is Mint-API-aware),
		// same gate catalog/controller/product/category.php uses for
		// product-card - see docs/upstream-diff.md. Neither the legacy
		// 'default' theme nor an unrelated third-party theme has one, and
		// neither of their templates read $data['mc_footer'], so stores on
		// either kind of theme pay no extra cost. The controller's original
		// per-field data above (informations/contact/return/.../newsletter)
		// is left completely untouched - this only regroups it, it does not
		// change what it resolves to.
		$mint_theme = \MintCart\Design\ActiveTheme::directory($this->config->get('config_theme'), $this->config->get('theme_default_directory'));
		$mint_render_footer = \MintCart\Design\ActiveTheme::isMintAware(DIR_TEMPLATE, $mint_theme);

		if ($mint_render_footer) {
			$information_links = array();

			foreach ($data['informations'] as $information) {
				$information_links[] = array(
					'label' => $information['title'],
					'href'  => $information['href']
				);
			}

			$columns = array();

			if ($information_links) {
				$columns[] = array(
					'title' => $this->language->get('text_information'),
					'links' => $information_links
				);
			}

			$columns[] = array(
				'title' => $this->language->get('text_service'),
				'links' => array(
					array('label' => $this->language->get('text_contact'), 'href' => $data['contact']),
					array('label' => $this->language->get('text_return'), 'href' => $data['return']),
					array('label' => $this->language->get('text_sitemap'), 'href' => $data['sitemap'])
				)
			);

			$columns[] = array(
				'title' => $this->language->get('text_extra'),
				'links' => array(
					array('label' => $this->language->get('text_manufacturer'), 'href' => $data['manufacturer']),
					array('label' => $this->language->get('text_voucher'), 'href' => $data['voucher']),
					array('label' => $this->language->get('text_affiliate'), 'href' => $data['affiliate']),
					array('label' => $this->language->get('text_special'), 'href' => $data['special']),
					array('label' => $this->language->get('text_mc_reviews'), 'href' => $this->url->link('mintcart/reviews'))
				)
			);

			$columns[] = array(
				'title' => $this->language->get('text_account'),
				'links' => array(
					array('label' => $this->language->get('text_account'), 'href' => $data['account']),
					array('label' => $this->language->get('text_order'), 'href' => $data['order']),
					array('label' => $this->language->get('text_wishlist'), 'href' => $data['wishlist']),
					array('label' => $this->language->get('text_newsletter'), 'href' => $data['newsletter'])
				)
			);

			// Standard OpenCart Setting storage (config_telephone/config_email/
			// config_address) - never duplicated into a Mint-owned table, per
			// CLAUDE.md §39 and MintCart\Design\FooterViewProvider's own contract.
			$contacts = array(
				'phone'   => $this->config->get('config_telephone') ?: null,
				'email'   => $this->config->get('config_email') ?: null,
				'address' => $this->config->get('config_address') ?: null
			);

			// Social profile links have no standard OpenCart storage to reuse
			// (FooterViewProvider's own doc-comment already notes this), so
			// the Setup Wizard's Contacts step (Phase 13) writes them under a
			// dedicated 'mintcart_contacts' setting code, already in the
			// exact {network, href} shape build()'s $social parameter
			// expects - no reshaping needed here. Empty on a store that
			// hasn't been through that step yet, same as before this change.
			$this->load->model('setting/setting');
			$mint_contacts_setting = $this->model_setting_setting->getSetting('mintcart_contacts');
			$social = isset($mint_contacts_setting['mintcart_contacts_social']) && is_array($mint_contacts_setting['mintcart_contacts_social'])
				? $mint_contacts_setting['mintcart_contacts_social']
				: array();

			try {
				$footer_view_provider = new \MintCart\Design\FooterViewProvider();
				$component_renderer = new \MintCart\Design\ComponentRenderer(DIR_TEMPLATE, $mint_theme);

				$footer_view = $footer_view_provider->build($columns, $contacts, $social, array(), null, $data['powered']);

				$data['mc_footer'] = $component_renderer->render('footer', $footer_view + array(
					'text_contact_us'        => $this->language->get('text_contact_us'),
					'text_newsletter'        => $this->language->get('text_newsletter'),
					'text_newsletter_email'  => $this->language->get('text_newsletter_email'),
					'text_newsletter_submit' => $this->language->get('text_newsletter_submit')
				));
			} catch (\Throwable $e) {
				// Same rationale as ControllerProductCategory's per-product
				// catch: a broken/missing footer.twig must not fatal every
				// page on the site (footer is common to all of them). Falls
				// back to no mc_footer - mint_default's own common/footer.twig
				// renders nothing rather than the legacy markup in that case,
				// same "log and disable for this request" pattern.
				(new \MintCart\Core\Logger())->error('ComponentRenderer failed to render footer: ' . $e->getMessage());
			}
		}

		// Whos Online
		if ($this->config->get('config_customer_online')) {
			$this->load->model('tool/online');

			if (isset($this->request->server['REMOTE_ADDR'])) {
				$ip = $this->request->server['REMOTE_ADDR'];
			} else {
				$ip = '';
			}

			if (isset($this->request->server['HTTP_HOST']) && isset($this->request->server['REQUEST_URI'])) {
				$url = ($this->request->server['HTTPS'] ? 'https://' : 'http://') . $this->request->server['HTTP_HOST'] . $this->request->server['REQUEST_URI'];
			} else {
				$url = '';
			}

			if (isset($this->request->server['HTTP_REFERER'])) {
				$referer = $this->request->server['HTTP_REFERER'];
			} else {
				$referer = '';
			}

			$this->model_tool_online->addOnline($ip, $this->customer->getId(), $url, $referer);
		}

		$data['scripts'] = $this->document->getScripts('footer');
		$data['styles'] = $this->document->getStyles('footer');

		return $this->load->view('common/footer', $data);
	}
}
