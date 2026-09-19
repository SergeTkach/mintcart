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
 * `PackageExtractor` (`extract`, between `download` and `verify`) and
 * `BackupPlanner`+`BackupExecutor` (`backup`, between `verify` and
 * `maintenance`) are both wired in as **optional** steps — each pair of
 * constructor arguments defaults to `null`, in which case `apply()` behaves
 * exactly as it always has (an already-extracted `$extractedDir` is
 * assumed, no snapshot is taken), so every caller/test written before this
 * class knew about either primitive keeps working unchanged. `check`/
 * `compatibility` (`UpdateChecker`) remain earlier decision points a caller
 * makes *before* deciding to apply a specific manifest at all — e.g.
 * showing the admin user "an update is available and compatible" and
 * letting them confirm — this class still starts from "the operator has
 * already decided to apply this exact manifest."
 *
 * The `mc_installed_file` ledger (ADR 005 decision #1) is wired in as
 * another **optional** step: an `?InstalledFileLedger $installedFiles`
 * constructor argument, defaulting to `null`. When injected, `apply()`
 * calls `InstalledFileLedger::record()` with the `files` step's own
 * `installed` entries right after that step succeeds — the exact input
 * `PackageFileInstaller::install()`'s docblock already named as "the exact
 * input the future `mc_installed_file` ledger needs once a real caller
 * writes it." When omitted, behavior is unchanged from before this ledger
 * existed.
 *
 * Deliberately out of scope, same as every constituent step already
 * documents:
 *
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
    public const STEP_EXTRACT = 'extract';
    public const STEP_VERIFY = 'verify';
    public const STEP_BACKUP = 'backup';
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

    private ?PackageExtractor $extractor;

    private ?BackupPlanner $backupPlanner;

    private ?BackupExecutor $backupExecutor;

    private ?InstalledFileLedger $installedFiles;

    /** @var callable(): string */
    private $timestampFactory;

    /**
     * `$extractor` and the `$backupPlanner`/`$backupExecutor` pair are each
     * all-or-nothing: passing only one half of the backup pair throws
     * immediately (a partial backup configuration is a caller bug, not a
     * "skip the step" signal — unlike `$cache`/`$healthCheck`, which are
     * independently optional). `$timestampFactory` (default: `date('YmdHis')`)
     * is injectable so `BackupPlanner::plan()`'s snapshot directory name is
     * deterministic in tests, the same reason `PackageDownloader`'s
     * transport is injectable. `$installedFiles` is independently optional
     * (like `$cache`/`$healthCheck`) — no ledger write is attempted when
     * omitted.
     */
    public function __construct(
        PackageDownloader $downloader,
        MaintenanceMode $maintenance,
        PackageFileInstaller $installer,
        MigrationManager $migrations,
        ?CacheInvalidator $cache = null,
        ?HealthCheck $healthCheck = null,
        ?PackageExtractor $extractor = null,
        ?BackupPlanner $backupPlanner = null,
        ?BackupExecutor $backupExecutor = null,
        ?callable $timestampFactory = null,
        ?InstalledFileLedger $installedFiles = null
    ) {
        if (($backupPlanner === null) !== ($backupExecutor === null)) {
            throw new \InvalidArgumentException(
                '$backupPlanner and $backupExecutor must be passed together, both null or both set.'
            );
        }

        $this->downloader = $downloader;
        $this->maintenance = $maintenance;
        $this->installer = $installer;
        $this->migrations = $migrations;
        $this->cache = $cache;
        $this->healthCheck = $healthCheck;
        $this->extractor = $extractor;
        $this->backupPlanner = $backupPlanner;
        $this->backupExecutor = $backupExecutor;
        $this->timestampFactory = $timestampFactory ?? static fn (): string => date('YmdHis');
        $this->installedFiles = $installedFiles;
    }

    /**
     * Runs `download → verify → maintenance → files → migrations →
     * cache clear → health check → done` for one manifest. Stops at the
     * first failed step — every step already completed stays in effect
     * (e.g. a `migrations` failure does not undo the `files` step, matching
     * `MigrationManager::run()`'s own "stops on first failure, earlier
     * migrations stay applied" contract one level up).
     *
     * `$extractedDir` is where the extracted package ends up. When no
     * `PackageExtractor` was injected, it is assumed to already hold the
     * package extracted from the file `download` just wrote to
     * `$downloadPath` (this class's original, still-supported behavior). When
     * one was injected, `extract()` is called between `download` and
     * `verify` to populate it from `$downloadPath` instead — the caller
     * passes the same already-empty-or-stale directory either way.
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
     *     extract?: string[],
     *     verify?: bool,
     *     backup?: array{snapshot_dir: string, backed_up: string[]},
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

        if ($this->extractor !== null) {
            try {
                $result['extract'] = $this->extractor->extract($downloadPath, $extractedDir);
            } catch (\Throwable $e) {
                return $this->failure($result, self::STEP_EXTRACT, $e);
            }
            $result['completed_steps'][] = self::STEP_EXTRACT;
        }

        try {
            $this->assertVerified($downloadPath, $extractedDir, $manifest);
        } catch (\Throwable $e) {
            return $this->failure($result, self::STEP_VERIFY, $e);
        }
        $result['verify'] = true;
        $result['completed_steps'][] = self::STEP_VERIFY;

        if ($this->backupPlanner !== null && $this->backupExecutor !== null) {
            try {
                $existingFiles = $this->resolveExistingFiles($manifest, $destRoot);
                $timestamp = ($this->timestampFactory)();
                $plan = $this->backupPlanner->plan($manifest, $existingFiles, $timestamp);
                $result['backup'] = $this->backupExecutor->backup($plan, $destRoot);
            } catch (\Throwable $e) {
                return $this->failure($result, self::STEP_BACKUP, $e);
            }
            $result['completed_steps'][] = self::STEP_BACKUP;
        }

        // From here on, a failure must leave the store in maintenance mode
        // rather than risk shoppers hitting a half-applied update.
        $wasAlreadyInMaintenance = $this->maintenance->enable();
        $result['maintenance'] = ['was_already_enabled' => $wasAlreadyInMaintenance];
        $result['completed_steps'][] = self::STEP_MAINTENANCE;

        try {
            $result['files'] = $this->installer->install($manifest, $extractedDir, $destRoot);
            $result['completed_steps'][] = self::STEP_FILES;

            if ($this->installedFiles !== null) {
                $this->installedFiles->record($manifest->name(), $result['files']['installed']);
            }

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

    /**
     * Resolves the `$existingFiles` map `BackupPlanner::plan()` needs
     * (relative path => current size in bytes) by checking, for every path
     * $manifest's `files()`/`removes()` declare, whether it currently
     * exists under $destRoot — the same "caller resolves which declared
     * paths currently exist on disk" contract `BackupPlanner`'s own
     * docblock already assumes an upstream caller performs. A path the
     * manifest declares that is not currently installed is simply absent
     * from the returned map (nothing to snapshot for it), matching
     * `plan()`'s own "not currently installed" handling.
     *
     * @return array<string,int>
     */
    private function resolveExistingFiles(PackageManifest $manifest, string $destRoot): array
    {
        $destRoot = rtrim($destRoot, '/\\');
        $existingFiles = [];

        foreach (array_merge($manifest->files(), $manifest->removes()) as $relativePath) {
            $path = $destRoot . '/' . ltrim($relativePath, '/\\');

            if (is_file($path)) {
                $existingFiles[$relativePath] = (int) filesize($path);
            }
        }

        return $existingFiles;
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
