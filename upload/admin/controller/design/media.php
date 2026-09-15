<?php
/**
 * JSON API for `mc-media-picker` (`assets/src/js/components/media-picker.js`,
 * `docs/developer/components/media-picker.md`) — the real backend that
 * component's documented `fetchItems`/`fetchFolders`/`uploadFiles`/
 * `deleteItems` usage example was always written against, not built until
 * now (`CLAUDE.md` §85). Browses/uploads/deletes inside OpenCart's own
 * `image/catalog/` directory via `ModelDesignMedia`
 * (`upload/admin/model/design/media.php`) — the same directory
 * `common/filemanager.php` already manages, left completely untouched by
 * this controller (`CLAUDE.md` §6/§39: no second media store).
 *
 * Fully additive — no existing OpenCart controller, model or template is
 * modified. Same "0 controller patches" shape as `ControllerMintcartCart`/
 * `ControllerMintcartWizard`/`ControllerDesignHomepage`.
 *
 * Not wired into any admin screen yet — `mc-media-picker` itself is not
 * mounted anywhere (its own doc's "Not wired into catalog.js/admin.js
 * yet" section, and the still-unresolved admin asset-enqueue question
 * `docs/developer/blocks/homepage-admin.md` flags), so these routes have
 * no live caller today beyond direct testing. Same "ship the backend
 * before the UI that calls it" precedent as `SearchService` before
 * `mc-search-box` existed.
 */
class ControllerDesignMedia extends Controller {
	/**
	 * `fetchItems({ query, folder, page, pageSize })` — GET
	 * `design/media/list`. Returns `{ items: [...], total: int }`.
	 *
	 * Route is slash-separated (`design/media/list`), not the dot form
	 * (`design/media.list`) `media-picker.md`'s original usage example
	 * used — OpenCart 3's own `Action::__construct()`
	 * (`upload/system/engine/action.php`) strips any character that
	 * isn't a letter/digit/underscore/slash from the route *before*
	 * parsing it, so a `.` is silently deleted rather than treated as a
	 * separator; `design/media.list` would actually resolve to
	 * `design/medialist` (a nonexistent controller), never to this
	 * method. Caught by checking the real routing code before wiring this
	 * (`CLAUDE.md` §84) — fixed in that doc's example alongside adding
	 * this controller.
	 */
	public function list() {
		$this->load->language('design/media');

		$json = array();

		if (!$this->user->hasPermission('access', 'design/media')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			$this->load->model('design/media');

			$result = $this->model_design_media->getFiles(array(
				'directory'   => isset($this->request->get['folder']) ? $this->request->get['folder'] : '',
				'filter_name' => isset($this->request->get['query']) ? $this->request->get['query'] : '',
				'page'        => isset($this->request->get['page']) ? $this->request->get['page'] : 1,
				'limit'       => isset($this->request->get['pageSize']) ? $this->request->get['pageSize'] : 20
			));

			$json['items'] = $result['items'];
			$json['total'] = $result['total'];
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * `fetchFolders()` — GET `design/media.folders`. Returns
	 * `{ folders: [{ id, label }, ...] }`.
	 */
	public function folders() {
		$this->load->language('design/media');

		$json = array();

		if (!$this->user->hasPermission('access', 'design/media')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			$this->load->model('design/media');

			$json['folders'] = $this->model_design_media->getFolders();
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * `uploadFiles(files)` — POST `design/media.upload`. Same
	 * extension/mime/size/upload-error checks
	 * `common/filemanager.php::upload()` already enforces (verified
	 * against that real controller, `CLAUDE.md` §84), reusing
	 * `MintCart\Design\MediaPath`'s whitelists rather than a second copy
	 * of them. A single bad file in a multi-file selection stops the
	 * whole request with an error (same as `common/filemanager.php` — no
	 * partial-success reporting today).
	 */
	public function upload() {
		$this->load->language('design/media');

		$json = array();

		if (!$this->user->hasPermission('modify', 'design/media')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			$this->load->model('design/media');

			$directory = isset($this->request->get['folder']) ? $this->request->get['folder'] : '';

			$files = array();

			if (!empty($this->request->files['file']['name']) && is_array($this->request->files['file']['name'])) {
				foreach (array_keys($this->request->files['file']['name']) as $key) {
					$files[] = array(
						'name'     => $this->request->files['file']['name'][$key],
						'type'     => $this->request->files['file']['type'][$key],
						'tmp_name' => $this->request->files['file']['tmp_name'][$key],
						'error'    => $this->request->files['file']['error'][$key],
						'size'     => $this->request->files['file']['size'][$key]
					);
				}
			}

			if (!$files) {
				$json['error'] = $this->language->get('error_upload');
			}

			$uploaded = array();

			foreach ($files as $file) {
				if ($json) {
					break;
				}

				if ($file['error'] != UPLOAD_ERR_OK) {
					$json['error'] = $this->language->get('error_upload');
					break;
				}

				if (!\MintCart\Design\MediaPath::hasAllowedMimeType($file['type'])) {
					$json['error'] = $this->language->get('error_filetype');
					break;
				}

				if ($file['size'] > $this->config->get('config_file_max_size')) {
					$json['error'] = $this->language->get('error_filesize');
					break;
				}

				$result = $this->model_design_media->addFile($directory, $file['name'], $file['tmp_name']);

				if (isset($result['error'])) {
					$json['error'] = $this->language->get('error_' . $result['error']);
					break;
				}

				$uploaded[] = $result['path'];
			}

			if (!$json) {
				$json['success'] = $this->language->get('text_uploaded');
				$json['paths'] = $uploaded;
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * `deleteItems(items)` — POST `design/media.delete`. Accepts
	 * `{ paths: string[] }` (the same relative-path `id` values
	 * `getFiles()`/`fetchItems()` returned).
	 */
	public function delete() {
		$this->load->language('design/media');

		$json = array();

		if (!$this->user->hasPermission('modify', 'design/media')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			$paths = isset($this->request->post['paths']) && is_array($this->request->post['paths']) ? $this->request->post['paths'] : array();

			if (!$paths) {
				$json['error'] = $this->language->get('error_delete');
			}
		}

		if (!$json) {
			$this->load->model('design/media');

			$result = $this->model_design_media->deleteFiles($paths);

			if (isset($result['error'])) {
				$json['error'] = $this->language->get('error_delete');
			} else {
				$json['success'] = $this->language->get('text_deleted');
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
