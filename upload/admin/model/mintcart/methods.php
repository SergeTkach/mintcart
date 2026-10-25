<?php
/**
 * Shipping / payment methods as one screen sees them: the installed
 * extensions with their inline settings, the installable ones, and the two
 * writes behind them (save settings, install). Shared by the Setup Wizard and
 * `Business → Delivery / Payments`, so both read and write the same stock
 * `<type>_<code>_<key>` OpenCart settings (CLAUDE.md §39: no second storage).
 *
 * Which settings a method shows inline comes from
 * `system/mintcart/data/method-fields.json`; a method not listed there has no
 * inline form and keeps its own settings page.
 */
class ModelMintcartMethods extends Model {
	/**
	 * @return array<int, array{code: string, name: string, enabled: bool, fields: array, edit: string}>
	 */
	public function getInstalled(string $type): array {
		$this->load->model('setting/extension');

		$rows = array();

		foreach ($this->model_setting_extension->getInstalled($type) as $code) {
			// An extension can be removed by deleting its file; skip the stale row.
			if (!is_file(DIR_APPLICATION . 'controller/extension/' . $type . '/' . $code . '.php')) {
				continue;
			}

			$this->load->language('extension/' . $type . '/' . $code, 'extension');

			$rows[] = array(
				'code'    => $code,
				'name'    => $this->language->get('extension')->get('heading_title'),
				'enabled' => (bool)$this->config->get($type . '_' . $code . '_status'),
				'fields'  => $this->getFields($type, $code),
				'edit'    => $this->url->link('extension/' . $type . '/' . $code, 'user_token=' . $this->session->data['user_token'], true)
			);
		}

		return $rows;
	}

	/**
	 * Extensions whose files are present but that are not installed yet.
	 *
	 * @return array<int, array{code: string, name: string}>
	 */
	public function getAvailable(string $type): array {
		$this->load->model('setting/extension');

		$installed = $this->model_setting_extension->getInstalled($type);
		$rows = array();

		foreach (glob(DIR_APPLICATION . 'controller/extension/' . $type . '/*.php') ?: array() as $file) {
			$code = basename($file, '.php');

			if (in_array($code, $installed, true) || !is_file(DIR_APPLICATION . 'language/en-gb/extension/' . $type . '/' . $code . '.php')) {
				continue;
			}

			$this->load->language('extension/' . $type . '/' . $code, 'extension');

			$rows[] = array(
				'code' => $code,
				'name' => $this->language->get('extension')->get('heading_title')
			);
		}

		return $rows;
	}

	public function isAvailable(string $type, string $code): bool {
		foreach ($this->getAvailable($type) as $row) {
			if ($row['code'] === $code) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Same steps as `ControllerExtensionExtension<Type>::install()`.
	 */
	public function install(string $type, string $code): void {
		$this->load->model('setting/extension');
		$this->load->model('user/user_group');

		$this->model_setting_extension->install($type, $code);

		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', 'extension/' . $type . '/' . $code);
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', 'extension/' . $type . '/' . $code);

		$this->load->controller('extension/' . $type . '/' . $code . '/install');
	}

	/**
	 * @return array<int, array{key: string, type: string, label: string, value: string, options: array<int|string, string>}>
	 */
	public function getFields(string $type, string $code): array {
		$this->load->language('mintcart/methods');

		$schema = json_decode((string)@file_get_contents(DIR_SYSTEM . 'mintcart/data/method-fields.json'), true)[$type][$code] ?? array();
		$fields = array();

		foreach ($schema as $field) {
			$value = $this->config->get($type . '_' . $code . '_' . $field['key']);

			$fields[] = array(
				'key'     => $field['key'],
				'type'    => $field['type'],
				'label'   => $this->language->get('field_' . $field['key']),
				'value'   => $value !== null ? (string)$value : (string)($field['default'] ?? ''),
				'options' => $field['type'] === 'select' ? $this->getOptions($field['source']) : array()
			);
		}

		return $fields;
	}

	/**
	 * Validates and saves one method's inline settings. Only keys listed in
	 * method-fields.json are accepted; the method's other settings (status,
	 * sort order, ...) are kept, because editSetting() deletes every row of
	 * the setting code before inserting.
	 *
	 * @return array{ok: bool, message: string, errors: array<string, string>}
	 */
	public function saveFields(string $type, string $code, array $posted): array {
		$this->load->language('mintcart/methods');
		$this->load->model('setting/extension');
		$this->load->model('setting/setting');

		$result = array('ok' => false, 'message' => '', 'errors' => array());
		$fields = in_array($code, $this->model_setting_extension->getInstalled($type), true) ? $this->getFields($type, $code) : array();

		if (!$fields) {
			$result['message'] = $this->language->get('error_method');

			return $result;
		}

		$values = array();

		foreach ($fields as $field) {
			$value = trim((string)($posted[$field['key']] ?? ''));

			if ($field['type'] === 'number') {
				$value = str_replace(',', '.', $value);

				if ($value === '' || !is_numeric($value) || (float)$value < 0) {
					$result['errors'][$field['key']] = $this->language->get('error_number');
				}
			} elseif (!array_key_exists((int)$value, $field['options'])) {
				$result['errors'][$field['key']] = $this->language->get('error_option');
			}

			$values[$type . '_' . $code . '_' . $field['key']] = $value;
		}

		if ($result['errors']) {
			$result['message'] = $this->language->get('error_check_fields');

			return $result;
		}

		$this->model_setting_setting->editSetting($type . '_' . $code, array_merge($this->model_setting_setting->getSetting($type . '_' . $code), $values));

		$result['ok'] = true;
		$result['message'] = $this->language->get('text_saved');

		return $result;
	}

	/**
	 * @return array<int|string, string> id => title, with a leading "none / all" entry
	 */
	private function getOptions(string $source): array {
		if ($source === 'tax_class') {
			$this->load->model('localisation/tax_class');

			$options = array(0 => $this->language->get('text_tax_none'));

			foreach ($this->model_localisation_tax_class->getTaxClasses() as $row) {
				$options[(int)$row['tax_class_id']] = $row['title'];
			}

			return $options;
		}

		$this->load->model('localisation/geo_zone');

		$options = array(0 => $this->language->get('text_geo_all'));

		foreach ($this->model_localisation_geo_zone->getGeoZones() as $row) {
			$options[(int)$row['geo_zone_id']] = $row['name'];
		}

		return $options;
	}
}
