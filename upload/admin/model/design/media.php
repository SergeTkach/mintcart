<?php
/**
 * The Media UX backend for `mc-media-picker`
 * (`assets/src/js/components/media-picker.js`,
 * `docs/developer/components/media-picker.md` — Functional Scope §25,
 * priority P1). Browses/uploads/deletes inside OpenCart's own existing
 * `image/catalog/` directory — the same directory
 * `upload/admin/controller/common/filemanager.php` already manages —
 * rather than a new Mint-owned media store (`CLAUDE.md` §6/§39: don't
 * duplicate OpenCart storage).
 *
 * Every path-safety/filename/mime rule here matches
 * `common/filemanager.php`'s own rules exactly (checked against that real
 * controller before writing this, per `CLAUDE.md` §84/§10) via the shared
 * `MintCart\Design\MediaPath` primitive
 * (`upload/system/library/mintcart/design/mediapath.php`) — this model
 * does not re-derive those rules, and `common/filemanager.php` itself is
 * untouched. What differs is the *shape* of the result: this model
 * returns the normalized `{items, total}` / `{folders}` arrays
 * `ControllerDesignMedia` needs to answer `mc-media-picker`'s documented
 * `fetchItems`/`fetchFolders`/`uploadFiles`/`deleteItems` JSON contract,
 * instead of `filemanager.php`'s own admin-page `$data` shape for its
 * server-rendered dialog.
 *
 * Plain OpenCart Model, not MintCart\*-namespaced — same convention
 * `ModelDesignHomepage` already follows for the same reason (this is an
 * OpenCart MVC model, not a framework-agnostic service).
 */
class ModelDesignMedia extends Model {
	const CATALOG_SUBDIR = 'catalog';

	/**
	 * @return string the un-resolved (not yet realpath()'d) catalog image
	 *         root, e.g. DIR_IMAGE . 'catalog'
	 */
	private function catalogRoot() {
		return rtrim(DIR_IMAGE . self::CATALOG_SUBDIR, '/');
	}

	/**
	 * Resolves a caller-supplied relative directory (e.g. `'shirts'`,
	 * `''`) against the catalog root, verifying via
	 * `MintCart\Design\MediaPath::isWithinCatalog()` that the resolved
	 * real path cannot escape it (a `..`-style traversal or symlink
	 * escape) — the same containment guarantee every
	 * `common/filemanager.php` entry point enforces before touching the
	 * filesystem.
	 *
	 * @return string|null the resolved absolute directory, or null if it
	 *         does not exist or would resolve outside the catalog root
	 */
	private function resolveDirectory($relative) {
		$relative = str_replace('*', '', (string)$relative);
		$directory = rtrim($this->catalogRoot() . '/' . $relative, '/');

		$real = realpath($directory);
		$root = realpath($this->catalogRoot());

		if ($real === false || $root === false || !\MintCart\Design\MediaPath::isWithinCatalog($real, $root)) {
			return null;
		}

		return $real;
	}

	/**
	 * @param array{directory?: string, filter_name?: string, page?: int, limit?: int} $data
	 * @return array{items: list<array<string, mixed>>, total: int}
	 */
	public function getFiles($data) {
		$directory = $this->resolveDirectory(isset($data['directory']) ? $data['directory'] : '');

		if ($directory === null) {
			return array('items' => array(), 'total' => 0);
		}

		$filter = isset($data['filter_name']) ? \MintCart\Design\MediaPath::sanitizeFilterName((string)$data['filter_name']) : '';

		$directories = glob($directory . '/' . $filter . '*', GLOB_ONLYDIR);

		if (!$directories) {
			$directories = array();
		}

		$files = safe_glob($directory . '/' . $filter . '*.{jpg,jpeg,png,gif,webp,JPG,JPEG,PNG,GIF,WEBP}', GLOB_BRACE);

		if (!$files) {
			$files = array();
		}

		sort($directories);
		sort($files);

		// Folders first, then files, each alphabetical — a stable, readable
		// order for a picker grid (filemanager.php's own admin screen does
		// not sort either group before merging; this model does, since a
		// JSON API consumer has no server-rendered pagination widget to
		// mask an otherwise-unstable glob() order across requests).
		$entries = array_merge($directories, $files);

		$total = count($entries);

		$page = isset($data['page']) ? max(1, (int)$data['page']) : 1;
		$limit = isset($data['limit']) ? max(1, (int)$data['limit']) : 20;

		$slice = array_slice($entries, ($page - 1) * $limit, $limit);

		$this->load->model('tool/image');

		$server = (!empty($this->request->server['HTTPS'])) ? HTTPS_CATALOG : HTTP_CATALOG;

		$items = array();

		foreach ($slice as $entry) {
			$name = basename($entry);
			$relative_path = ltrim(utf8_substr($entry, utf8_strlen(DIR_IMAGE)), '/');

			if (is_dir($entry)) {
				$items[] = array(
					'id'   => $relative_path,
					'type' => 'directory',
					'name' => $name
				);
			} else {
				$items[] = array(
					'id'        => $relative_path,
					'type'      => 'image',
					'name'      => $name,
					'thumbnail' => $this->model_tool_image->resize($relative_path, 100, 100),
					'url'       => $server . 'image/' . $relative_path
				);
			}
		}

		return array('items' => $items, 'total' => $total);
	}

	/**
	 * Immediate (non-recursive) subdirectories of the catalog root only —
	 * enough for `mc-media-picker`'s optional folder `<select>`
	 * (Functional Scope §25) without building a full folder-tree UI a
	 * flat picker control has no use for.
	 *
	 * @return list<array{id: string, label: string}>
	 */
	public function getFolders() {
		$root = $this->catalogRoot();

		$directories = glob($root . '/*', GLOB_ONLYDIR);

		if (!$directories) {
			$directories = array();
		}

		sort($directories);

		$folders = array();

		foreach ($directories as $directory) {
			$folders[] = array(
				'id'    => ltrim(utf8_substr($directory, utf8_strlen(DIR_IMAGE)), '/'),
				'label' => basename($directory)
			);
		}

		return $folders;
	}

	/**
	 * Moves a single already-uploaded temp file into the catalog
	 * directory. Extension/mime/size validation against
	 * `MintCart\Design\MediaPath`'s whitelists and `config_file_max_size`
	 * is the controller's job (it has the raw `$_FILES` entry, including
	 * the reported mime type and any PHP upload error) — this method only
	 * re-validates the destination directory and filename, and performs
	 * the filesystem move.
	 *
	 * @return array{success: true, path: string}|array{error: string}
	 */
	public function addFile($directory_relative, $filename, $tmp_name) {
		$directory = $this->resolveDirectory($directory_relative);

		if ($directory === null) {
			return array('error' => 'directory');
		}

		$filename = \MintCart\Design\MediaPath::sanitizeName($filename);

		if (!\MintCart\Design\MediaPath::isValidFilenameLength($filename)) {
			return array('error' => 'filename');
		}

		if (!\MintCart\Design\MediaPath::hasAllowedExtension($filename)) {
			return array('error' => 'filetype');
		}

		if (!is_file($tmp_name)) {
			return array('error' => 'upload');
		}

		move_uploaded_file($tmp_name, $directory . '/' . $filename);

		return array(
			'success' => true,
			'path'    => ltrim(utf8_substr($directory . '/' . $filename, utf8_strlen(DIR_IMAGE)), '/')
		);
	}

	/**
	 * Deletes one or more files/directories, each independently validated
	 * against the catalog root (never the catalog root itself, never a
	 * path realpath() resolves outside it) before anything is deleted —
	 * same containment rule `common/filemanager.php::delete()` already
	 * enforces, and the same BFS-collect-then-`rsort()`-then-delete
	 * traversal order that method uses for a non-empty directory (deepest
	 * paths sort after their parents, so children are always removed
	 * before the directory that contains them).
	 *
	 * @param string[] $paths relative paths (as returned by `getFiles()`'s
	 *        `id` field)
	 * @return array{success: true}|array{error: string}
	 */
	public function deleteFiles($paths) {
		$root = realpath($this->catalogRoot());

		if ($root === false) {
			return array('error' => 'directory');
		}

		foreach ($paths as $path) {
			$full = rtrim(DIR_IMAGE . $path, '/');
			$real = realpath($full);

			if ($full === $this->catalogRoot() || $real === false || !\MintCart\Design\MediaPath::isWithinCatalog($real, $root)) {
				return array('error' => 'delete');
			}
		}

		foreach ($paths as $path) {
			$full = rtrim(DIR_IMAGE . $path, '/');

			if (is_file($full)) {
				unlink($full);
			} elseif (is_dir($full)) {
				$files = array();
				$queue = array($full);

				while (count($queue) != 0) {
					$next = array_shift($queue);

					foreach (glob($next) as $file) {
						if (is_dir($file)) {
							$queue[] = $file . '/*';
						}

						$files[] = $file;
					}
				}

				rsort($files);

				foreach ($files as $file) {
					if (is_file($file)) {
						unlink($file);
					} elseif (is_dir($file)) {
						rmdir($file);
					}
				}
			}
		}

		return array('success' => true);
	}
}
