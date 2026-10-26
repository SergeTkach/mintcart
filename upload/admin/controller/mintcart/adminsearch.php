<?php
/**
 * Admin "quick settings search" backend (`MINTCART_FUNCTIONAL_SCOPE.md`
 * §23, P1). `ControllerCommonHeader::index()` enqueues a small search box
 * (`assets/src/js/components/admin-search.js`) on every logged-in admin
 * page; this is the one GET endpoint it calls.
 *
 * Deliberately a new, fully-additive controller under `mintcart/*` (same
 * shape as `mintcart/entity`, `mintcart/settings/diagnostics`,
 * `mintcart/seo/system_pages`) rather than touching any existing
 * controller — nothing here changes an existing route's behavior.
 *
 * Like every other MintCart admin route, this one is gated by OpenCart's
 * own route-based ACL (`admin/controller/startup/permission.php`) under
 * the route name `mintcart/adminsearch` — a fresh install's user groups do
 * not have this listed yet (new admin controllers never do; OpenCart
 * discovers them by scanning `admin/controller/**` and lists them in
 * Users > User Groups > Permissions for an admin to grant), so
 * `mintcart/adminsearch` access must be granted to a user group before the
 * search box returns any result for it — see
 * `docs/developer/admin/settings-search.md`.
 */
class ControllerMintcartAdminsearch extends Controller {
	// route=mintcart/adminsearch - GET q
	public function index() {
		$this->response->addHeader('Content-Type: application/json');

		// admin/controller/startup/login.php already redirects to
		// common/login before this method runs for any route (this one
		// included) with a missing/mismatched user_token, so only the
		// permission check (same shape as every other mintcart/* admin
		// controller, e.g. ControllerMintcartEntity) is needed here.
		if (!$this->user->hasPermission('access', 'mintcart/adminsearch')) {
			$this->response->setOutput(json_encode(array('results' => array())));
			return;
		}

		$query = isset($this->request->get['q']) ? (string)$this->request->get['q'] : '';

		$this->load->language('mintcart/adminsearch');

		$index = new \MintCart\Admin\SettingsSearchIndex();

		$results = array();

		foreach ($index->search($query) as $match) {
			$results[] = array(
				'label' => $this->label($match['label']),
				'url'   => str_replace('&amp;', '&', $this->url->link($match['route'], 'user_token=' . $this->session->data['user_token'], true)) . ($match['field'] !== '' ? '#mc-field=' . $match['field'] : '')
			);
		}

		$this->response->setOutput(json_encode(array('results' => $results)));
	}

	// The index holds English labels; the language file translates them (the English text is the fallback).
	private function label($label) {
		$key = 'label_' . trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($label)), '_');
		$text = $this->language->get($key);

		return $text !== $key ? $text : $label;
	}
}
