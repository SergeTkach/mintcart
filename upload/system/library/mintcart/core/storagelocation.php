<?php

declare(strict_types=1);

namespace MintCart\Core;

/**
 * Where OpenCart's `storage` directory (cache, logs, sessions, uploads and the
 * PHP libraries such as Twig) lives, and moving it.
 *
 * It must not be reachable from the web, so a fresh MintCart install puts it
 * next to the document root instead of inside it (OpenCart's own security
 * notice nags about exactly that). The same move is available afterwards under
 * System > Maintenance > Storage. One service, two callers: the installers
 * (`install/cli_install.php`, `install/controller/install/step_3.php`) and
 * `ControllerMintcartMaintenanceStorage`.
 *
 * `move()` copies, rewrites `DIR_STORAGE` in the given config files, and only
 * then removes the old directory, so a failure half way leaves the store
 * running from the old location.
 */
final class StorageLocation
{
    public const ERROR_EXISTS = 'exists';
    public const ERROR_NOT_WRITABLE = 'not_writable';
    public const ERROR_CONFIG = 'config';
    public const ERROR_COPY = 'copy';
    public const ERROR_INVALID = 'invalid';

    /**
     * A free sibling of the document root: `<parent>/storage`, or
     * `<parent>/storage_<random>` when that name is taken (several sites in
     * one parent folder must not share a storage directory).
     */
    public static function suggest(string $documentRoot): string
    {
        $parent = dirname(rtrim(self::normalize($documentRoot), '/'));
        $path = $parent . '/storage';

        return !file_exists($path) ? $path : $parent . '/storage_' . bin2hex(random_bytes(4));
    }

    public static function isInside(string $storage, string $documentRoot): bool
    {
        $storage = strtolower(rtrim(self::normalize((string)(realpath($storage) ?: $storage)), '/')) . '/';
        $root = strtolower(rtrim(self::normalize((string)(realpath($documentRoot) ?: $documentRoot)), '/')) . '/';

        return strpos($storage, $root) === 0;
    }

    /**
     * @param string[] $configFiles config.php files whose DIR_STORAGE line is rewritten
     * @return string|null null on success, one of the ERROR_* codes otherwise
     */
    public static function move(string $from, string $to, array $configFiles): ?string
    {
        $from = rtrim(self::normalize($from), '/');
        $to = rtrim(self::normalize($to), '/');

        if ($to === '' || !preg_match('~^(?:[A-Za-z]:)?/~', $to) || !is_dir($from) || strtolower($to) === strtolower($from) || strpos(strtolower($to) . '/', strtolower($from) . '/') === 0) {
            return self::ERROR_INVALID;
        }

        if (file_exists($to) && (!is_dir($to) || count(array_diff(scandir($to) ?: [], ['.', '..'])) > 0)) {
            return self::ERROR_EXISTS;
        }

        if (!is_dir(dirname($to)) || !is_writable(dirname($to))) {
            return self::ERROR_NOT_WRITABLE;
        }

        foreach ($configFiles as $file) {
            if (!is_file($file) || !is_writable($file)) {
                return self::ERROR_CONFIG;
            }
        }

        if (!is_dir($to) && !@mkdir($to, 0775, true)) {
            return self::ERROR_NOT_WRITABLE;
        }

        if (!self::copyTree($from, $to)) {
            self::deleteTree($to);

            return self::ERROR_COPY;
        }

        foreach ($configFiles as $file) {
            if (!self::rewriteConfig($file, $to . '/')) {
                return self::ERROR_CONFIG;
            }
        }

        self::deleteTree($from);

        return null;
    }

    private static function normalize(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    private static function copyTree(string $from, string $to): bool
    {
        foreach (array_diff(scandir($from) ?: [], ['.', '..']) as $name) {
            $source = $from . '/' . $name;
            $target = $to . '/' . $name;

            if (is_dir($source)) {
                if (!is_dir($target) && !@mkdir($target, 0775)) {
                    return false;
                }

                if (!self::copyTree($source, $target)) {
                    return false;
                }
            } elseif (!@copy($source, $target)) {
                return false;
            }
        }

        return true;
    }

    private static function deleteTree(string $path): void
    {
        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $name) {
            $item = $path . '/' . $name;

            is_dir($item) && !is_link($item) ? self::deleteTree($item) : @unlink($item);
        }

        @rmdir($path);
    }

    private static function rewriteConfig(string $file, string $storage): bool
    {
        $lines = file($file);

        if ($lines === false) {
            return false;
        }

        $output = '';
        $found = false;

        foreach ($lines as $line) {
            if (strpos($line, "define('DIR_STORAGE'") !== false) {
                $output .= "define('DIR_STORAGE', '" . addslashes($storage) . "');" . "\n";
                $found = true;
            } else {
                $output .= $line;
            }
        }

        return $found && file_put_contents($file, $output) !== false;
    }
}
