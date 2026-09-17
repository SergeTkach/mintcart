<?php
namespace MintCart\Update;

use MintCart\Core\Cache\CacheInvalidator;
use MintCart\Core\HealthCheck;
use MintCart\Core\Migration\MigrationManager;

/**
 * Chains the Update Manager flow's `download` through `health check` steps
 * (`MINTCART_TECHNICAL_PLAN.md` §33 / `MINTCART_IMPLEMENTATION_GUIDE.md`
 * §69):
 *
 * ```text
 * check → compatibility → backup → download → verify → maintenance →
 * files → migrations → cache clear → health check → done
 * ```
 *
 * Every individual step already exists as its own pure, unit-tested
 * primitive (`PackageDownloader`, `PackageVerifier`, `MaintenanceMode`,
 * `PackageFileInstaller`, `MigrationManager`, `CacheInvalidator`,
 * `HealthCheck`) — each one built "primitive first, no caller yet"
 * (`CLAUDE.md` §85), and each one's own docblock names "an orchestrator
 * that chains these steps end to end" as the next increment once they all
 * exist. This class is that orchestrator: it owns sequencing and failure
 * handling, not any of the steps' own logic.
 *
 * Deliberately out of scope, same as every constituent step already
 * documents:
 *
 * - `check` (`UpdateChecker`), `compatibility` (requirement matching, also
 *   `UpdateChecker`) and `backup` (`BackupPlanner`) are earlier decision
 *   points a caller makes BEFORE deciding to apply an update at all (e.g.
 *   showing the admin user "an update is available and compatible" and
 *   letting them confirm) — this class starts from "the operator has
 *   already decided to apply this exact manifest," the same way
 *   `PackageVerifier`/`PackageFileInstaller` already assume verification
 *   inputs are already resolved rather than re-deciding them.
 * - Archive extraction. No extractor exists anywhere in this codebase yet
 *   (`PackageDownloader`'s and `PackageFileInstaller`'s own docblocks both
 *   name this the same still-open gap) — this class takes an
 *   already-extracted directory as an input, exactly like
 *   `PackageVerifier::missingFiles()`/`PackageFileInstaller::install()`
 *   already do.
 * - The `mc_installed_file` ledger (ADR 005 decision #1). `apply()`'s
 *   result carries every installed file's sha256 (from
 *   `PackageFileInstaller::install()`), the exact input that ledger needs,
 *   but this class does not write it — no table exists yet, and adding one
 *   is a Migration Manager change, not this class's job.
 * - Any admin controller/route. No `mintcart/update/*` route exists yet —
 *   this class is the flow's orchestration primitive; wiring an admin
 *   screen around it is later work.
 *
 * Maintenance-mode safety: `MaintenanceMode::enable()`'s own contract is
 * "the caller decides the flow's end state" — this class never disables
 * maintenance mode after a failure (a half-applied update must never be
 * silently reopened to shoppers) and never disables it at all if the store
 * was already in maintenance mode before `apply()` was called (an operator
 * who manually put the store into maintenance for other reasons must not
 * have it silently reopened by an unrelated update).
 */
final class UpdateOrchestrator
{
    public const STEP_DOWNLOAD = 'download';
    public const STEP_VERIFY = 'verify';
    public const STEP_MAINTENANCE = 'maintenance';
    public const STEP_FILES = 'files';
    public const STEP_MIGRATIONS = 'migrations';
    public const STEP_CACHE_CLEAR = 'cache_clear';
    public const STEP_HEALTH_CHECK = 'health_check';
    public const STEP_DONE = 'done';

    private PackageDownloader $downloader;

    private MaintenanceMode $maintenance;

    private PackageFileInstaller $installer;

    private MigrationManager $migrations;

    private ?CacheInvalidator $cache;

    private ?HealthCheck $healthCheck;

    public function __construct(
        PackageDownloader $downloader,
        MaintenanceMode $maintenance,
        PackageFileInstaller $installer,
        MigrationManager $migrations,
        ?CacheInvalidator $cache = null,
        ?HealthCheck $healthCheck = null
    ) {
        $this->downloader = $downloader;
        $this->maintenance = $maintenance;
        $this->installer = $installer;
        $this->migrations = $migrations;
        $this->cache = $cache;
        $this->healthCheck = $healthCheck;
    }

    /**
     * Runs `download → verify → maintenance → files → migrations →
     * cache clear → health check → done` for one manifest. Stops at the
     * first failed step — every step already completed stays in effect
     * (e.g. a `migrations` failure does not undo the `files` step, matching
     * `MigrationManager::run()`'s own "stops on first failure, earlier
     * migrations stay applied" contract one level up).
     *
     * `$extractedDir` is assumed to already hold the package extracted from
     * the file `download` just wrote to `$downloadPath` — this class does
     * not extract archives (see class docblock).
     *
     * @param array<string, mixed> $healthSignals Passed through verbatim to
     *        `HealthCheck::evaluate()` (php_version/extensions/writable/
     *        pending_migrations/mint_version/opencart_baseline) — this
     *        class does not gather them itself (no filesystem/`phpversion()`
     *        calls belong in an OpenCart-independent primitive, same
     *        reasoning `HealthCheck`'s own docblock gives for
     *        `ModelMintcartHealth` gathering signals instead of
     *        `HealthCheck` itself).
     *
     * @return array{
     *     status: 'done'|'failed',
     *     completed_steps: string[],
     *     failed_step?: string,
     *     error?: string,
     *     download?: null,
     *     verify?: bool,
     *     maintenance?: array{was_already_enabled: bool},
     *     files?: array{installed: array<int, array{path: string, sha256: string}>, removed: string[]},
     *     migrations?: array{applied: string[], skipped: string[]},
     *     cache_clear?: bool,
     *     health_check?: array{status: string, checks: list<array{key: string, label: string, status: string, detail: string}>}
     * }
     */
    public function apply(
        PackageManifest $manifest,
        string $downloadUrl,
        string $downloadPath,
        string $extractedDir,
        string $destRoot,
        array $healthSignals = []
    ): array {
        $result = ['completed_steps' => []];

        try {
            $this->downloader->download($downloadUrl, $downloadPath);
        } catch (\Throwable $e) {
            return $this->failure($result, self::STEP_DOWNLOAD, $e);
        }
        $result['download'] = null;
        $result['completed_steps'][] = self::STEP_DOWNLOAD;

        try {
            $this->assertVerified($downloadPath, $extractedDir, $manifest);
        } catch (\Throwable $e) {
            return $this->failure($result, self::STEP_VERIFY, $e);
        }
        $result['verify'] = true;
        $result['completed_steps'][] = self::STEP_VERIFY;

        // From here on, a failure must leave the store in maintenance mode
        // rather than risk shoppers hitting a half-applied update.
        $wasAlreadyInMaintenance = $this->maintenance->enable();
        $result['maintenance'] = ['was_already_enabled' => $wasAlreadyInMaintenance];
        $result['completed_steps'][] = self::STEP_MAINTENANCE;

        try {
            $result['files'] = $this->installer->install($manifest, $extractedDir, $destRoot);
            $result['completed_steps'][] = self::STEP_FILES;

            $result['migrations'] = $this->migrations->run();
            $result['completed_steps'][] = self::STEP_MIGRATIONS;

            if ($this->cache !== null) {
                $this->cache->invalidateAll();
            }
            $result['cache_clear'] = $this->cache !== null;
            $result['completed_steps'][] = self::STEP_CACHE_CLEAR;

            if ($this->healthCheck !== null) {
                $result['health_check'] = $this->healthCheck->evaluate($healthSignals);
            }
            $result['completed_steps'][] = self::STEP_HEALTH_CHECK;
        } catch (\Throwable $e) {
            $lastCompleted = end($result['completed_steps']);
            $failedStep = $this->nextStepAfter($lastCompleted !== false ? $lastCompleted : self::STEP_MAINTENANCE);

            return $this->failure($result, $failedStep, $e);
        }

        if (!$wasAlreadyInMaintenance) {
            $this->maintenance->disable();
        }
        $result['completed_steps'][] = self::STEP_DONE;
        $result['status'] = 'done';

        return $result;
    }

    /**
     * @throws \RuntimeException if the checksum doesn't match or the
     *                            extracted package is missing a declared file.
     */
    private function assertVerified(string $downloadPath, string $extractedDir, PackageManifest $manifest): void
    {
        if (!PackageVerifier::verifyChecksum($downloadPath, $manifest->checksum())) {
            throw new \RuntimeException("Downloaded package at \"{$downloadPath}\" failed checksum verification.");
        }

        $missing = PackageVerifier::missingFiles($extractedDir, $manifest);

        if ($missing !== []) {
            throw new \RuntimeException(
                'Extracted package is missing declared file(s): ' . implode(', ', $missing)
            );
        }
    }

    private function nextStepAfter(string $completedStep): string
    {
        $order = [
            self::STEP_MAINTENANCE => self::STEP_FILES,
            self::STEP_FILES => self::STEP_MIGRATIONS,
            self::STEP_MIGRATIONS => self::STEP_CACHE_CLEAR,
            self::STEP_CACHE_CLEAR => self::STEP_HEALTH_CHECK,
        ];

        return $order[$completedStep] ?? self::STEP_FILES;
    }

    /**
     * @param array<string, mixed> $result
     *
     * @return array<string, mixed>
     */
    private function failure(array $result, string $failedStep, \Throwable $e): array
    {
        $result['status'] = 'failed';
        $result['failed_step'] = $failedStep;
        $result['error'] = $e->getMessage();

        return $result;
    }
}
