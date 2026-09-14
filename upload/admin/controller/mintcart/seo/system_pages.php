<?php
/**
 * `Marketing → SEO → System Pages` (`MINTCART_FUNCTIONAL_SCOPE.md` §4,
 * `MINTCART_TECHNICAL_PLAN.md` §31, `MINTCART_IMPLEMENTATION_GUIDE.md` §65).
 *
 * route=mintcart/seo/system_pages. Fully additive — no existing OpenCart
 * controller/model/route touched (`CLAUDE.md` §8/§37).
 *
 * A friendlier facade over the exact same `seo_url` table/`ModelDesignSeoUrl`
 * the stock **Design → SEO** screen (`design/seo_url`) already edits
 * (`CLAUDE.md` §6/§39 — not a second SEO storage). The stock screen makes an
 * admin type a raw `query` value like `checkout/cart` by hand with no
 * indication which of the store's many internal routes are the handful that
 * actually matter for a friendly URL — `MintCart\Seo\SystemRoutes` is that
 * fixed, documented list of twelve; this screen is one row per route, an
 * alias field, one Save.
 *
 * Deliberately scoped to store_id = 0 (the main/default store — the same
 * value `ControllerStartupSeoUrl` matches incoming requests against for a
 * single-store install) and `config_language_id` (the storefront's default
 * language). A store using OpenCart's multi-store or multi-language
 * features would need a per-store/per-language variant of this screen —
 * not attempted this run (`CLAUDE.md` §85 — no real multi-store MintCart
 * consumer yet); see `docs/developer/seo/system-route-aliases.md`'s "Known
 * limitation" section.
 *
 * Built on the legacy OpenCart admin theme (Bootstrap 3 `.panel`/`.btn`/
 * `form-horizontal` markup), same reason `ControllerDesignHomepage` and
 * `ControllerExtensionDashboardMintcartReadiness` both give — MintCart's own
 * compiled asset bundle has never been enqueued into the shared admin
 * `header`/`column_left`/`footer` chrome anywhere in this repo (`CLAUDE.md`
 * §10).
 */
class ControllerMintcartSeoSystemPages extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('mintcart/seo/system_pages');
		$this->load->model('design/seo_url');

		$this->document->setTitle($this->language->get('heading_title'));

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->save();

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('mintcart/seo/system_pages', 'user_token=' . $this->session->data['user_token'], true));

			return;
		}

		$this->getForm();
	}

	protected function storeId(): int {
		return 0;
	}

	protected function languageId(): int {
		return (int)$this->config->get('config_language_id');
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'mintcart/seo/system_pages')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		$seen = array();

		foreach (\MintCart\Seo\SystemRoutes::ROUTES as $code => $route) {
			$alias = strtolower(trim((string)($this->request->post['alias_' . $code] ?? '')));

			if (!\MintCart\Seo\SystemRoutes::isValidAlias($alias)) {
				$this->error[$code] = $this->language->get('error_alias_format');

				continue;
			}

			if ($alias === '') {
				continue;
			}

			if (isset($seen[$alias])) {
				$this->error[$code] = $this->language->get('error_alias_duplicate');

				continue;
			}

			$seen[$alias] = $code;

			$existing = $this->model_design_seo_url->getSeoUrlsByKeyword($alias);

			foreach ($existing as $row) {
				// A row is only "in conflict" if it belongs to this store and
				// isn't simply this same route's own current value (editing a
				// route's alias to the value it already has must not error).
				if (((int)$row['store_id'] === $this->storeId()) && ($row['query'] !== $route)) {
					$this->error[$code] = $this->language->get('error_alias_exists');

					break;
				}
			}
		}

		return !$this->error;
	}

	/**
	 * One row per `MintCart\Seo\SystemRoutes::ROUTES` entry: blank alias
	 * deletes any existing `seo_url` row for that route, a non-blank alias
	 * adds or updates one. `ModelDesignSeoUrl` has no "upsert" of its own
	 * (`ControllerDesignSeoUrl` always knows in advance, via `seo_url_id`,
	 * whether it's adding or editing), so this looks up each route's
	 * current row first via `getSeoUrlsByQuery()`, the same method the
	 * stock controller's own uniqueness check already uses.
	 */
	protected function save() {
		foreach (\MintCart\Seo\SystemRoutes::ROUTES as $code => $route) {
			$alias = strtolower(trim((string)($this->request->post['alias_' . $code] ?? '')));

			$existing = null;

			foreach ($this->model_design_seo_url->getSeoUrlsByQuery($route) as $row) {
				if (((int)$row['store_id'] === $this->storeId()) && ((int)$row['language_id'] === $this->languageId())) {
					$existing = $row;

					break;
				}
			}

			if ($alias === '') {
				if ($existing !== null) {
					$this->model_design_seo_url->deleteSeoUrl($existing['seo_url_id']);
				}

				continue;
			}

			$data = array(
				'store_id'    => $this->storeId(),
				'language_id' => $this->languageId(),
				'query'       => $route,
				'keyword'     => $alias
			);

			if ($existing !== null) {
				$this->model_design_seo_url->editSeoUrl($existing['seo_url_id'], $data);
			} else {
				$this->model_design_seo_url->addSeoUrl($data);
			}
		}
	}

	protected function getForm() {
		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_edit'] = $this->language->get('text_edit');
		$data['text_intro'] = $this->language->get('text_intro');
		$data['entry_alias'] = $this->language->get('entry_alias');
		$data['entry_route'] = $this->language->get('entry_route');

		$data['error_warning'] = $this->error['warning'] ?? '';

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];

			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('mintcart/seo/system_pages', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('mintcart/seo/system_pages', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true);

		$data['routes'] = array();

		foreach (\MintCart\Seo\SystemRoutes::ROUTES as $code => $route) {
			if ($this->request->server['REQUEST_METHOD'] == 'POST') {
				$alias = (string)($this->request->post['alias_' . $code] ?? '');
			} else {
				$alias = '';

				foreach ($this->model_design_seo_url->getSeoUrlsByQuery($route) as $row) {
					if (((int)$row['store_id'] === $this->storeId()) && ((int)$row['language_id'] === $this->languageId())) {
						$alias = $row['keyword'];

						break;
					}
				}
			}

			$data['routes'][] = array(
				'code'  => $code,
				'route' => $route,
				'label' => $this->language->get(\MintCart\Seo\SystemRoutes::labelKey($code)),
				'alias' => $alias,
				'error' => $this->error[$code] ?? ''
			);
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('mintcart/seo/system_pages', $data));
	}
}
