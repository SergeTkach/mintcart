<?php
namespace Cache;
class File {
	private $expire;

	public function __construct($expire = 3600) {
		$this->expire = $expire;

		$files = glob(DIR_CACHE . 'cache.*');

		if ($files) {
			foreach ($files as $file) {
				$time = substr(strrchr($file, '.'), 1);

				if ($time < time()) {
					if (file_exists($file)) {
						@unlink($file);
					}
				}
			}
		}
	}

	public function get($key) {
		$files = glob(DIR_CACHE . 'cache.' . preg_replace('/[^A-Z0-9\._-]/i', '', $key) . '.*');

		if ($files) {
			// MC-0007 (docs/fixes/registry.md): the file glob() just
			// matched can be unlinked or replaced by a concurrent
			// set()/delete() before this fopen() runs (a concurrent
			// set() for the same key begins with its own delete($key)) -
			// treat that as a cache miss instead of passing a false
			// handle to flock()/fread(), which fatals. filesize($files[0])
			// used to be a second, independent stat-by-path call subject
			// to the same race (the path could change again between
			// fopen() and filesize()); stream_get_contents($handle)
			// reads through the already-open handle instead, which is
			// unaffected by the path being unlinked/replaced afterwards.
			$handle = @fopen($files[0], 'r');

			if ($handle === false) {
				return false;
			}

			flock($handle, LOCK_SH);

			$data = stream_get_contents($handle);

			flock($handle, LOCK_UN);

			fclose($handle);

			return json_decode($data, true);
		}

		return false;
	}

	public function set($key, $value) {
		$this->delete($key);

		$file = DIR_CACHE . 'cache.' . preg_replace('/[^A-Z0-9\._-]/i', '', $key) . '.' . (time() + $this->expire);

		// MC-0007 (docs/fixes/registry.md): write to a uniquely-named
		// temp file first, then atomically rename() it into place,
		// instead of fopen($file, 'w') truncating the final filename
		// directly. rename() on the same filesystem is atomic, so a
		// concurrent get() only ever sees the previous complete file or
		// this fully-written one - never a 0-byte/partially-written
		// file. It also fixes the same-second collision case: the cache
		// filename only has second-granularity (time() + $this->expire),
		// so two set() calls for the same key within the same second
		// used to unlink() and overwrite() each other's in-progress
		// write via the shared final filename; each writer now has its
		// own temp file until the moment it replaces the target.
		//
		// The temp filename deliberately does NOT start with "cache."
		// (unlike an earlier version of this fix, which appended
		// ".<uniqid>.tmp" onto $file): get()'s glob('cache.<key>.*') and
		// delete()'s glob('cache.*.*') would otherwise match the
		// in-progress temp file too, handing a reader/deleter a
		// half-written or about-to-vanish file - the exact race this fix
		// exists to remove. It also must not start with "cache." for the
		// constructor's own stale-file sweep (glob('cache.*') above),
		// which would otherwise treat another process's in-flight temp
		// file as an expired cache entry and unlink it out from under
		// that write.
		$temp = DIR_CACHE . '.cache-tmp.' . uniqid('', true);

		$handle = fopen($temp, 'w');

		if ($handle === false) {
			return;
		}

		fwrite($handle, json_encode($value));

		fflush($handle);

		fclose($handle);

		rename($temp, $file);
	}

	public function delete($key) {
		if ($key == '*') {
			$files = glob(DIR_CACHE . 'cache.*.*');
		} else {
			$files = glob(DIR_CACHE . 'cache.' . preg_replace('/[^A-Z0-9\._-]/i', '', $key) . '.*');
		}

		if ($files) {
			foreach ($files as $file) {
				if (file_exists($file)) {
					@unlink($file);
				}
			}
		}
	}
}
