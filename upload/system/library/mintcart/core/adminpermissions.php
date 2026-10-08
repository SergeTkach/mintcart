<?php

declare(strict_types=1);

namespace MintCart\Core;

/**
 * Grants full admin user groups access to MintCart's own admin screens
 * (`admin/controller/mintcart/**`).
 *
 * OpenCart only grants permissions for routes listed in the install SQL or
 * granted when an extension is installed; MintCart screens are neither, so
 * on a fresh install the Administrator group got "Permission denied" on the
 * Setup Wizard and every other Mint screen. A migration calls grant(); a
 * later migration can call it again when new screens ship (idempotent).
 *
 * "Full admin" = a group that already has access to user/user_permission
 * (the stock Administrator group, also when renamed). Other groups are left
 * to the store owner, as with any OpenCart screen.
 */
final class AdminPermissions
{
    /**
     * Permission routes for controller files given relative to
     * admin/controller/ (e.g. "mintcart/business/delivery.php").
     *
     * Both the full route and its first two parts: admin startup/permission
     * checks only "mintcart/business" for "mintcart/business/delivery",
     * while the controller itself may check the full route.
     *
     * @param string[] $files
     * @return string[]
     */
    public static function routes(array $files): array
    {
        $routes = [];

        foreach ($files as $file) {
            $route = preg_replace('/\.php$/', '', str_replace('\\', '/', $file));
            $parts = explode('/', $route);

            $routes[] = $route;
            $routes[] = implode('/', array_slice($parts, 0, 2));
        }

        $routes = array_values(array_unique($routes));
        sort($routes);

        return $routes;
    }

    /**
     * @param string $controllerDir absolute path to admin/controller/
     * @return string[] routes granted
     */
    public static function grant(object $db, string $prefix, string $controllerDir): array
    {
        $base = rtrim(str_replace('\\', '/', $controllerDir), '/') . '/';
        $files = [];

        if (is_dir($base . 'mintcart')) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base . 'mintcart', \FilesystemIterator::SKIP_DOTS));

            foreach ($iterator as $file) {
                if ($file->getExtension() === 'php') {
                    $files[] = substr(str_replace('\\', '/', $file->getPathname()), strlen($base));
                }
            }
        }

        $routes = self::routes($files);

        if (!$routes) {
            return [];
        }

        $groups = $db->query("SELECT user_group_id, permission FROM `" . $prefix . "user_group`");

        foreach ($groups->rows as $group) {
            $permission = json_decode((string)$group['permission'], true);

            if (!is_array($permission) || !in_array('user/user_permission', $permission['access'] ?? [], true)) {
                continue;
            }

            foreach (['access', 'modify'] as $type) {
                $permission[$type] = array_values(array_unique(array_merge($permission[$type] ?? [], $routes)));
            }

            $db->query("UPDATE `" . $prefix . "user_group` SET permission = '" . $db->escape(json_encode($permission)) . "' WHERE user_group_id = '" . (int)$group['user_group_id'] . "'");
        }

        return $routes;
    }
}
