<?php
namespace MintCart\Core\Cache;

/**
 * Domain-based cache invalidation on top of OpenCart's stock `\Cache` class
 * (`MINTCART_FUNCTIONAL_SCOPE.md` §19 / `MINTCART_TECHNICAL_PLAN.md` §36 —
 * "cache invalidation API"). No consumer wires this live yet: it is a
 * primitive, same "build the contract first" shape `MigrationManager`
 * (Phase 1), `PackageManifest` (Phase 11) and `HreflangBuilder`/
 * `RobotsBuilder`/`SitemapBuilder` (Phase 18) already established in this
 * repo (`CLAUDE.md` §85) — a real first caller is a bulk product/category
 * edit invalidating `catalog`, once one of those write paths exists on
 * `master`.
 *
 * Takes the OpenCart `\Cache` instance's `delete()` method as an injected
 * callable rather than depending on `\Cache`/`\Registry` directly, so this
 * class stays unit-testable with a plain spy closure — no
 * DIR_CACHE/adapter bootstrap needed, same pattern `Logger` and every
 * `MintCart\Commerce\*` service already use for their OpenCart
 * dependencies.
 *
 * IMPORTANT — verified, not assumed (`CLAUDE.md` §84), backend caveat:
 * OpenCart's `\Cache::delete($key)` behaves differently per configured
 * `cache_engine` (`upload/system/config/default.php` — default is
 * `file`):
 *
 * - `file` (`upload/system/library/cache/file.php`) and `apc`/`apcu`
 *   (`.../cache/apc.php`, `.../cache/apcu.php`) glob/scan-match on `$key`
 *   as a PREFIX, so `delete('product.latest.')` really does clear every
 *   `product.latest.<lang>.<store>.<group>.<limit>` variant, and
 *   `delete('*')` really does clear everything.
 * - `mem`/`memcached`/`redis` (`.../cache/mem.php`, `.../cache/memcached.php`,
 *   `.../cache/redis.php`) call their backend's single-key delete on the
 *   literal `$key` — there is no prefix/wildcard support, so
 *   `delete('product.latest.')` and even `delete('*')` are silent no-ops
 *   on these three adapters. This is a pre-existing stock OpenCart
 *   limitation, not something MintCart introduced — OpenCart's own
 *   `admin/controller/tool/backup.php` "clear cache" step calls
 *   `$this->cache->delete('*')` and inherits the exact same gap.
 *
 * `invalidate()`/`invalidateMany()` below are therefore only guaranteed to
 * work on `file`/`apc`/`apcu`. `invalidateKeys()` deletes each caller-
 * supplied EXACT key and is the one method that works identically on all
 * five adapters — prefer it when the caller already knows the full cache
 * key (e.g. it just wrote `product.latest.1.1.1.10` itself). Fixing the
 * underlying `mem`/`memcached`/`redis` prefix gap is out of scope here: it
 * would mean changing `tool/backup.php` (already touched by an in-flight
 * PR fixing a different bug in that same file) and cannot be verified
 * without a live memcached/redis server, which this environment doesn't
 * have. See `docs/developer/core/cache-invalidation.md`.
 */
final class CacheInvalidator
{
    /**
     * Known OpenCart cache key prefixes, grouped by product domain and
     * verified against actual `$this->cache->get(...)`/`->set(...)` call
     * sites in `upload/catalog/` and `upload/admin/` (not assumed from
     * memory — CLAUDE.md §84). Keep in sync if a future OpenCart core
     * change adds/renames a cache key.
     *
     * @var array<string, string[]>
     */
    private const DOMAINS = [
        // Product/category/manufacturer/attribute listings and lookups.
        'catalog' => [
            'product',
            'product.latest.',
            'product.popular.',
            'product.bestseller.',
            'category',
            'manufacturer',
            'manufacturer.',
            'attribute',
        ],
        // Countries/zones/currencies/units — rarely change, but a Setup
        // Wizard/Business Settings save should still be able to clear them.
        'localization' => [
            'country',
            'country.admin',
            'country.catalog',
            'currency',
            'zone',
            'zone.',
            'geo_zone',
            'length_class',
            'length_class.',
            'weight_class',
            'weight_class.',
        ],
        // Checkout-adjacent lookups: order/stock/return statuses, tax
        // classes, voucher themes.
        'checkout' => [
            'order_status',
            'order_status.',
            'return_action',
            'return_action.',
            'return_reason',
            'return_reason.',
            'return_status',
            'return_status.',
            'stock_status',
            'tax_class',
            'voucher_theme',
            'voucher_theme.',
        ],
        // Information pages and banners.
        'content' => [
            'information',
            'information.',
            'banner',
        ],
        // Store list (multi-store installs).
        'store' => [
            'store',
        ],
    ];

    /** @var callable(string): void */
    private $delete;

    /**
     * @param callable(string): void $delete Usually `[$this->cache, 'delete']`
     *   from an OpenCart controller/model context.
     */
    public function __construct(callable $delete)
    {
        $this->delete = $delete;
    }

    /**
     * @return string[] domain names this class knows how to invalidate
     */
    public static function knownDomains(): array
    {
        return array_keys(self::DOMAINS);
    }

    /**
     * @return string[] the raw cache key prefixes registered for $domain
     *   (empty array for an unknown domain)
     */
    public static function prefixesFor(string $domain): array
    {
        return self::DOMAINS[$domain] ?? [];
    }

    /**
     * Invalidate every cache key prefix registered for one domain.
     *
     * @return string[] the prefixes that were sent to the delete callback
     *
     * @throws \InvalidArgumentException if $domain isn't in knownDomains()
     */
    public function invalidate(string $domain): array
    {
        if (!array_key_exists($domain, self::DOMAINS)) {
            throw new \InvalidArgumentException(
                'Unknown cache domain "' . $domain . '". Known domains: ' . implode(', ', self::knownDomains())
            );
        }

        $prefixes = self::DOMAINS[$domain];

        foreach ($prefixes as $prefix) {
            ($this->delete)($prefix);
        }

        return $prefixes;
    }

    /**
     * Invalidate several domains in one call.
     *
     * @param string[] $domains
     *
     * @return string[] every prefix invalidated across all requested
     *   domains, de-duplicated, in first-seen order
     *
     * @throws \InvalidArgumentException if any $domains entry is unknown
     */
    public function invalidateMany(array $domains): array
    {
        $seen = [];

        foreach ($domains as $domain) {
            foreach ($this->invalidate($domain) as $prefix) {
                $seen[$prefix] = true;
            }
        }

        return array_keys($seen);
    }

    /**
     * Delete a caller-supplied list of EXACT cache keys. Unlike
     * invalidate()/invalidateMany(), this works identically on every
     * `cache_engine` (see the class docblock) since it never relies on
     * prefix/wildcard matching.
     *
     * @param string[] $keys
     */
    public function invalidateKeys(array $keys): void
    {
        foreach ($keys as $key) {
            ($this->delete)($key);
        }
    }

    /**
     * Best-effort full flush. Only reliable on the `file`/`apc`/`apcu`
     * cache_engine — see the class docblock's backend caveat.
     */
    public function invalidateAll(): void
    {
        ($this->delete)('*');
    }
}
