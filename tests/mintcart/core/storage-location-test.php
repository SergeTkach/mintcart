<?php
/**
 * Standalone smoke test for MintCart\Core\StorageLocation: runs against a
 * throw-away directory tree, no OpenCart and no database.
 *
 * Run: php tests/mintcart/core/storage-location-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/core/storagelocation.php';

use MintCart\Core\StorageLocation;

$failures = [];

function check(bool $condition, string $message, array &$failures): void
{
    if (!$condition) {
        $failures[] = $message;
    }
}

function rrmdir(string $path): void
{
    foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $name) {
        is_dir("$path/$name") ? rrmdir("$path/$name") : unlink("$path/$name");
    }

    rmdir($path);
}

$base = str_replace('\\', '/', sys_get_temp_dir()) . '/mc-storage-' . bin2hex(random_bytes(4));
$site = $base . '/site';

mkdir($site . '/system/storage/vendor/twig', 0777, true);
mkdir($site . '/system/storage/cache', 0777, true);
file_put_contents($site . '/system/storage/vendor/twig/Env.php', '<?php // twig');
file_put_contents($site . '/system/storage/cache/index.html', '');
file_put_contents($site . '/config.php', "<?php\ndefine('DIR_SYSTEM', 'x');\ndefine('DIR_STORAGE', DIR_SYSTEM . 'storage/');\ndefine('DIR_CACHE', DIR_STORAGE . 'cache/');\n");
file_put_contents($site . '/admin.config.php', "<?php\ndefine('DIR_STORAGE', DIR_SYSTEM . 'storage/');\n");

$from = $site . '/system/storage';

// suggest(): a sibling of the document root, a fresh name when it is taken.
$first = StorageLocation::suggest($site);
check($first === $base . '/storage', 'suggests <parent>/storage', $failures);
mkdir($base . '/storage');
check(preg_match('~/storage_[0-9a-f]{8}$~', StorageLocation::suggest($site)) === 1, 'a taken name gets a random suffix', $failures);
rmdir($base . '/storage');

// isInside()
check(StorageLocation::isInside($from, $site), 'system/storage is inside the web root', $failures);
check(!StorageLocation::isInside($base . '/storage', $site), 'a sibling is outside the web root', $failures);

// refuses nonsense and a non-empty target
check(StorageLocation::move($from, $from . '/inner', []) === StorageLocation::ERROR_INVALID, 'cannot move into itself', $failures);
check(StorageLocation::move($from, 'relative/path', []) === StorageLocation::ERROR_INVALID, 'target must be absolute', $failures);
mkdir($base . '/taken');
file_put_contents($base . '/taken/x', 'x');
check(StorageLocation::move($from, $base . '/taken', []) === StorageLocation::ERROR_EXISTS, 'a non-empty target is refused', $failures);
check(StorageLocation::move($from, $base . '/missing-parent/storage', []) === StorageLocation::ERROR_NOT_WRITABLE, 'a missing parent folder is refused', $failures);
check(is_file($from . '/vendor/twig/Env.php'), 'a refused move leaves the source alone', $failures);

// the real move
$target = $base . '/storage';
$result = StorageLocation::move($from, $target, [$site . '/config.php', $site . '/admin.config.php']);

check($result === null, 'move succeeds (got ' . var_export($result, true) . ')', $failures);
check(is_file($target . '/vendor/twig/Env.php') && is_file($target . '/cache/index.html'), 'files are in the new place', $failures);
check(!is_dir($from), 'the old directory is gone', $failures);
check(strpos((string)file_get_contents($site . '/config.php'), "define('DIR_STORAGE', '" . $target . "/');") !== false, 'config.php points at the new storage', $failures);
check(strpos((string)file_get_contents($site . '/config.php'), "define('DIR_CACHE'") !== false, 'the rest of config.php is kept', $failures);
check(strpos((string)file_get_contents($site . '/admin.config.php'), $target . '/') !== false, 'the admin config is rewritten too', $failures);

rrmdir($base);

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "OK\n";
