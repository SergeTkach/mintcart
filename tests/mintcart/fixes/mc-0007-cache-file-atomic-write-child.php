<?php
/**
 * Child process helper for mc-0007-cache-file-atomic-write-test.php.
 *
 * Writes to the same cache key the parent process is concurrently
 * reading, so the two processes race on the same
 * cache.<key>.<expire-second> filename. Not a standalone test - invoked
 * via proc_open() by the parent test only.
 *
 * Usage: php mc-0007-cache-file-atomic-write-child.php <cache-dir> <n>
 */

declare(strict_types=1);

$cacheDir = $argv[1] ?? null;
$n = $argv[2] ?? '0';

if ($cacheDir === null) {
    fwrite(STDERR, "Missing <cache-dir> argument\n");
    exit(1);
}

define('DIR_CACHE', $cacheDir);

require __DIR__ . '/../../../upload/system/library/cache/file.php';

$cache = new Cache\File(3600);
$cache->set('race-key', ['iteration' => $n, 'payload' => str_repeat('x', 2048)]);

exit(0);
