<?php
namespace MintCart\Seo;

/**
 * Decides whether a page request should be redirected (301) to the one canonical SEO URL of that page,
 * the way a "SeoPro" does: `index.php?route=product/product&product_id=5` -> `/white-mug`,
 * `/kitchen/white-mug` -> `/white-mug`, `/white-mug/` -> `/white-mug`, `/mugs?page=1` -> `/mugs`.
 *
 * Pure: the caller (`startup/mc_seo_redirect`) builds the canonical URL through OpenCart's own link
 * generator and passes it in, together with what was requested. A URL without a keyword
 * (the generator still returned `route=...`) is never redirected.
 */
final class RedirectPlanner
{
    /** Routes that are never redirected: callbacks, APIs, fragments. */
    private const SKIP_ROUTE_PREFIXES = ['api/', 'extension/', 'common/', 'startup/', 'event/', 'error/', 'tool/'];

    public static function skipsRoute(string $route): bool
    {
        foreach (self::SKIP_ROUTE_PREFIXES as $prefix) {
            if (strpos($route, $prefix) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $requestUri   the requested path and query (`/lighting/?page=1`)
     * @param string $canonicalUrl the page's SEO URL from the link generator (absolute or root-relative)
     * @param array{dirty: bool, slash: bool, page1: bool} $options which kinds of duplicates to redirect
     *
     * @return string|null the URL to redirect to, null = serve the page
     */
    public static function plan(string $requestUri, string $canonicalUrl, array $options): ?string
    {
        $target = parse_url(str_replace('&amp;', '&', $canonicalUrl));

        if ($target === false || !isset($target['path']) || (isset($target['query']) && strpos($target['query'], 'route=') !== false)) {
            return null;
        }

        $current = parse_url($requestUri);

        if ($current === false || !isset($current['path'])) {
            return null;
        }

        $currentPath = self::trim(urldecode($current['path']));
        $targetPath = self::trim(urldecode($target['path']));

        if ($currentPath !== $targetPath) {
            return !empty($options['dirty']) ? $canonicalUrl : null;
        }

        $hasSlash = strlen($current['path']) > 1 && substr($current['path'], -1) === '/';

        if ($hasSlash && !empty($options['slash'])) {
            return $canonicalUrl;
        }

        $params = [];
        parse_str($current['query'] ?? '', $params);

        if (isset($params['page']) && $params['page'] === '1' && !empty($options['page1'])) {
            return $canonicalUrl;
        }

        return null;
    }

    /** `/Lighting/` -> `/Lighting`; the root stays `/`. */
    private static function trim(string $path): string
    {
        $path = rtrim($path, '/');

        return $path === '' ? '/' : $path;
    }

    /** The stored form of an old keyword path: no slashes at the ends, lower case. */
    public static function oldPath(string $path): string
    {
        return strtolower(trim($path, '/'));
    }
}
