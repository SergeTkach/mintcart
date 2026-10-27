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
 * single-store install). A store using OpenCart's multi-store feature
 * would need a per-store variant of this screen — not attempted this run
 * (`CLAUDE.md` §85 — no real multi-store MintCart consumer yet); see
 * `docs/developer/seo/system-route-aliases.md`'s "Known limitation"
 * section.
 *
 * Per-language, however, is handled here: one alias field per
 * (route, enabled language) pair, keyed `alias_<code>_<language_id>`. This
 * is safe now that MC-0009 (`docs/fixes/registry.md`) made
 * `ControllerStartupSeoUrl::index()`'s incoming decode prefer the row
 * saved for the request's actual resolved language before falling back to
 * a language-agnostic match — two languages can share the same alias text
 * for two different routes without an incoming request ever resolving to
 * the wrong one. Outgoing link generation
 * (`ControllerStartupSeoUrl::rewrite()`) already filtered its system-route
 * lookup by `config_language_id`, so it already produced the correct
 * per-language alias automatically once one existed — no core change
 * needed for this increment, only this admin form.
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

	/**
	 * Every enabled `oc_language` row (`admin.language` cache, the same
	 * source `ControllerLocalisationLanguage`'s own list uses), sorted the
	 * way OpenCart already sorts them (`sort_order`, `name`). A disabled
	 * language is left out entirely - matches how the storefront's own
	 * language switcher only ever offers enabled languages, so this screen
	 * never manages an alias nobody can actually reach.
	 *
	 * Falls back to a single, unnamed entry for `config_language_id` in the
	 * unreachable case of zero enabled languages (a fresh install always
	 * has at least one), so this screen never renders an empty table.
	 *
	 * @return list<array{language_id: int, name: string}>
	 */
	protected function languages(): array {
		$this->load->model('localisation/language');

		$languages = array();

		foreach ($this->model_localisation_language->getLanguages() as $language) {
			if ((int)$language['status'] === 1) {
				$languages[] = array(
					'language_id' => (int)$language['language_id'],
					'name'        => $language['name']
				);
			}
		}

		if (!$languages) {
			$languages[] = array(
				'language_id' => (int)$this->config->get('config_language_id'),
				'name'        => ''
			);
		}

		return $languages;
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'mintcart/seo/system_pages')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		$this->load->model('mintcart/seo_url');

		// language_id => [alias => code], so the same alias text can be
		// reused across different languages without tripping the
		// duplicate-in-this-form check below.
		$seen = array();

		foreach ($this->languages() as $language) {
			$language_id = $language['language_id'];

			foreach (\MintCart\Seo\SystemRoutes::ROUTES as $code => $route) {
				$field = 'alias_' . $code . '_' . $language_id;
				$alias = strtolower(trim((string)($this->request->post[$field] ?? '')));

				if (!\MintCart\Seo\SystemRoutes::isValidAlias($alias)) {
					$this->error[$field] = $this->language->get('error_alias_format');

					continue;
				}

				if ($alias === '') {
					continue;
				}

				if (isset($seen[$language_id][$alias])) {
					$this->error[$field] = $this->language->get('error_alias_duplicate');

					continue;
				}

				$seen[$language_id][$alias] = $code;

				// Root level of this store and language; the route's own current alias is not a conflict.
				if ($this->model_mintcart_seo_url->keywordTaken($alias, $this->storeId(), $language_id, $route)) {
					$this->error[$field] = $this->language->get('error_alias_exists');
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
		foreach ($this->languages() as $language) {
			$language_id = $language['language_id'];

			foreach (\MintCart\Seo\SystemRoutes::ROUTES as $code => $route) {
				$alias = strtolower(trim((string)($this->request->post['alias_' . $code . '_' . $language_id] ?? '')));

				$existing = null;

				foreach ($this->model_design_seo_url->getSeoUrlsByQuery($route) as $row) {
					if (((int)$row['store_id'] === $this->storeId()) && ((int)$row['language_id'] === $language_id)) {
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
					'language_id' => $language_id,
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

		$languages = $this->languages();

		$data['languages'] = $languages;
		$data['routes'] = array();

		foreach (\MintCart\Seo\SystemRoutes::ROUTES as $code => $route) {
			$aliases = array();

			foreach ($languages as $language) {
				$language_id = $language['language_id'];
				$field = 'alias_' . $code . '_' . $language_id;

				if ($this->request->server['REQUEST_METHOD'] == 'POST') {
					$alias = (string)($this->request->post[$field] ?? '');
				} else {
					$alias = '';

					foreach ($this->model_design_seo_url->getSeoUrlsByQuery($route) as $row) {
						if (((int)$row['store_id'] === $this->storeId()) && ((int)$row['language_id'] === $language_id)) {
							$alias = $row['keyword'];

							break;
						}
					}
				}

				$aliases[] = array(
					'field' => $field,
					'alias' => $alias,
					'error' => $this->error[$field] ?? ''
				);
			}

			$data['routes'][] = array(
				'code'    => $code,
				'route'   => $route,
				'label'   => $this->language->get(\MintCart\Seo\SystemRoutes::labelKey($code)),
				'aliases' => $aliases
			);
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('mintcart/seo/system_pages', $data));
	}
}
