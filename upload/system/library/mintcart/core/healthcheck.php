<?php
namespace MintCart\Core;

/**
 * Foundation-level "is this MintCart install healthy" evaluator
 * (MINTCART_TECHNICAL_PLAN.md §4 "health checks", §33's Update Manager flow
 * ending in a health-check step before "done"; CLAUDE.md §21 starting
 * sequence lists a migration manager next to this).
 *
 * Deliberately has **no OpenCart dependency** (no \Registry, no filesystem
 * calls, no \DB) — same "resolved values in, normalized contract out" shape
 * as `MintCart\Onboarding\StoreReadiness`/`CartService`/`ProductViewProvider`,
 * so it stays unit-testable without a real server environment.
 * `ModelMintcartHealth` (admin/model/mintcart/health.php) is the one place
 * that actually calls `phpversion()`/`extension_loaded()`/`is_writable()`
 * and asks `MintCart\Core\Migration\MigrationManager` for pending
 * migrations — no new Mint-specific storage for any of this.
 *
 * Unlike `StoreReadiness` (missing signal = "not ready yet", a normal state
 * during onboarding), a missing signal here means "not checked" and must
 * never be silently reported as healthy — a health check that can go quiet
 * and still say "ok" is worse than not having one. Missing extension/
 * writable-path signals surface as their own 'unknown' check instead.
 */
final class HealthCheck
{
    /**
     * Mirrors `upload/system/startup.php`'s own hard PHP version gate
     * (`version_compare(phpversion(), '8.1.0', '<')`) — deliberately the
     * same threshold, not a separately-invented one, so this check can
     * never disagree with what the running install already enforces to
     * even boot.
     */
    public const MIN_PHP_VERSION = '8.1.0';

    /**
     * Mirrors the exact extension list OpenCart's own installer already
     * requires (`upload/install/controller/install/step_2.php`), read
     * before writing this class per CLAUDE.md §10/§84 — not a separately
     * invented list. `pdo`/`pgsql` are omitted here since MintCart's own
     * `\DB\MySQLi` (system/library/db/mysqli.php) only ever uses `mysqli`.
     */
    public const REQUIRED_EXTENSIONS = [
        'mysqli', 'gd', 'curl', 'zlib', 'zip', 'mbstring', 'dom', 'hash', 'xmlwriter', 'json',
    ];

    /**
     * Mirrors the exact writable-path list OpenCart's own installer already
     * requires (`upload/install/controller/install/step_2.php`). `base`
     * says which OpenCart directory constant the path is relative to —
     * resolved by `ModelMintcartHealth`, never by this class.
     */
    public const REQUIRED_WRITABLE_PATHS = [
        ['key' => 'image', 'base' => 'opencart', 'relative' => 'image/'],
        ['key' => 'image_cache', 'base' => 'opencart', 'relative' => 'image/cache/'],
        ['key' => 'image_catalog', 'base' => 'opencart', 'relative' => 'image/catalog/'],
        ['key' => 'storage_cache', 'base' => 'system', 'relative' => 'storage/cache/'],
        ['key' => 'storage_logs', 'base' => 'system', 'relative' => 'storage/logs/'],
        ['key' => 'storage_download', 'base' => 'system', 'relative' => 'storage/download/'],
        ['key' => 'storage_upload', 'base' => 'system', 'relative' => 'storage/upload/'],
        ['key' => 'storage_modification', 'base' => 'system', 'relative' => 'storage/modification/'],
    ];

    private const STATUS_RANK = ['ok' => 0, 'warning' => 1, 'unknown' => 2, 'error' => 3];

    /**
     * @param array{
     *     php_version?: string,
     *     extensions?: array<string, bool>,
     *     writable?: array<string, bool>,
     *     pending_migrations?: int,
     *     mint_version?: string,
     *     opencart_baseline?: string,
     * } $signals
     * @param array<string, string> $labels Optional label overrides, keyed
     *        by the same checklist keys this class produces (e.g.
     *        translated strings). Unknown keys are ignored; missing keys
     *        fall back to a built-in English default.
     *
     * @return array{
     *     status: 'ok'|'warning'|'error',
     *     checks: list<array{key: string, label: string, status: string, detail: string}>,
     * }
     */
    public function evaluate(array $signals, array $labels = []): array
    {
        $checks = [];

        $checks[] = $this->phpVersionCheck($signals['php_version'] ?? null, $labels);
        $checks = array_merge($checks, $this->extensionChecks($signals['extensions'] ?? [], $labels));
        $checks = array_merge($checks, $this->writableChecks($signals['writable'] ?? [], $labels));
        $checks[] = $this->migrationsCheck($signals['pending_migrations'] ?? null, $labels);
        $checks[] = $this->versionCheck($signals['mint_version'] ?? null, $signals['opencart_baseline'] ?? null, $labels);

        $worst = 'ok';

        foreach ($checks as $check) {
            if (self::STATUS_RANK[$check['status']] > self::STATUS_RANK[$worst]) {
                $worst = $check['status'];
            }
        }

        return [
            'status' => $worst,
            'checks' => $checks,
        ];
    }

    private function phpVersionCheck(?string $version, array $labels): array
    {
        $label = $labels['php_version'] ?? 'PHP version';

        if ($version === null || $version === '') {
            return $this->check('php_version', $label, 'unknown', 'not checked');
        }

        $ok = version_compare($version, self::MIN_PHP_VERSION, '>=');

        return $this->check(
            'php_version',
            $label,
            $ok ? 'ok' : 'error',
            $ok ? $version : "{$version} (>= " . self::MIN_PHP_VERSION . ' required)'
        );
    }

    /**
     * @return list<array{key: string, label: string, status: string, detail: string}>
     */
    private function extensionChecks(array $extensions, array $labels): array
    {
        $checks = [];

        foreach (self::REQUIRED_EXTENSIONS as $name) {
            $key = "extension_{$name}";
            $label = $labels[$key] ?? "PHP extension: {$name}";

            if (!array_key_exists($name, $extensions)) {
                $checks[] = $this->check($key, $label, 'unknown', 'not checked');
                continue;
            }

            $loaded = (bool) $extensions[$name];

            $checks[] = $this->check($key, $label, $loaded ? 'ok' : 'error', $loaded ? 'loaded' : 'missing');
        }

        return $checks;
    }

    /**
     * @return list<array{key: string, label: string, status: string, detail: string}>
     */
    private function writableChecks(array $writable, array $labels): array
    {
        $checks = [];

        foreach (self::REQUIRED_WRITABLE_PATHS as $path) {
            $key = "writable_{$path['key']}";
            $label = $labels[$key] ?? "Writable: {$path['relative']}";

            if (!array_key_exists($path['key'], $writable)) {
                $checks[] = $this->check($key, $label, 'unknown', 'not checked');
                continue;
            }

            $ok = (bool) $writable[$path['key']];

            $checks[] = $this->check($key, $label, $ok ? 'ok' : 'error', $ok ? 'writable' : 'not writable');
        }

        return $checks;
    }

    private function migrationsCheck(?int $pending, array $labels): array
    {
        $label = $labels['migrations'] ?? 'Database migrations';

        if ($pending === null) {
            return $this->check('migrations', $label, 'unknown', 'not checked');
        }

        if ($pending === 0) {
            return $this->check('migrations', $label, 'ok', 'up to date');
        }

        return $this->check('migrations', $label, 'warning', "{$pending} pending");
    }

    private function versionCheck(?string $mintVersion, ?string $opencartBaseline, array $labels): array
    {
        $label = $labels['mint_version'] ?? 'MintCart version';

        if ($mintVersion === null || $mintVersion === '') {
            return $this->check('mint_version', $label, 'unknown', 'not checked');
        }

        $detail = $opencartBaseline ? "{$mintVersion} (OpenCart {$opencartBaseline} baseline)" : $mintVersion;

        return $this->check('mint_version', $label, 'ok', $detail);
    }

    private function check(string $key, string $label, string $status, string $detail): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'status' => $status,
            'detail' => $detail,
        ];
    }
}
