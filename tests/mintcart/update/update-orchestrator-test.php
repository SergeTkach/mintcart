<?php
/**
 * Standalone smoke test for MintCart\Update\UpdateOrchestrator.
 *
 * No PHPUnit/Composer in the repo yet, no MySQL server available in this
 * environment (same pre-existing gap as every other test in this repo).
 * Every dependency this class wires together is already independently
 * tested elsewhere; this test exercises the *sequencing* — does step N
 * only run after step N-1 succeeded, does a failure leave the store in
 * maintenance mode, does a success restore the pre-update maintenance
 * state — using the same in-memory doubles those other tests established
 * (FakeDb from migration-manager-test.php, FakeSettingModel from
 * maintenance-mode-test.php).
 *
 * Run: php tests/mintcart/update/update-orchestrator-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/compatibility/opencart3/settingsadapter.php';
require __DIR__ . '/../../../upload/system/library/mintcart/core/migration/migrationinterface.php';
require __DIR__ . '/../../../upload/system/library/mintcart/core/migration/migrationmanager.php';
require __DIR__ . '/../../../upload/system/library/mintcart/core/cache/cacheinvalidator.php';
require __DIR__ . '/../../../upload/system/library/mintcart/core/healthcheck.php';
require __DIR__ . '/../../../upload/system/library/mintcart/update/packagemanifest.php';
require __DIR__ . '/../../../upload/system/library/mintcart/update/packagedownloader.php';
require __DIR__ . '/../../../upload/system/library/mintcart/update/packageverifier.php';
require __DIR__ . '/../../../upload/system/library/mintcart/update/maintenancemode.php';
require __DIR__ . '/../../../upload/system/library/mintcart/update/packagefileinstaller.php';
require __DIR__ . '/../../../upload/system/library/mintcart/update/updateorchestrator.php';

use MintCart\Compatibility\OpenCart3\SettingsAdapter;
use MintCart\Core\Cache\CacheInvalidator;
use MintCart\Core\HealthCheck;
use MintCart\Core\Migration\MigrationManager;
use MintCart\Update\MaintenanceMode;
use MintCart\Update\PackageDownloader;
use MintCart\Update\PackageFileInstaller;
use MintCart\Update\PackageManifest;
use MintCart\Update\UpdateOrchestrator;

/** Duck-types OpenCart's ModelSettingSetting — see maintenance-mode-test.php. */
final class FakeSettingModel
{
    /** @var array<string, array<string, mixed>> */
    public array $storage = [];

    public function getSetting(string $code): array
    {
        return $this->storage[$code] ?? [];
    }

    public function editSetting(string $code, array $data): void
    {
        $this->storage[$code] = $data;
    }
}

/** Duck-types OpenCart's \DB — see migration-manager-test.php. */
final class FakeDb
{
    /** @var string[] */
    public array $rowsMigration = [];

    private bool $tableExists = false;

    public function query(string $sql): object
    {
        $sql = trim($sql);

        if (str_starts_with($sql, 'CREATE TABLE')) {
            $this->tableExists = true;

            return $this->result([]);
        }

        if (str_starts_with($sql, 'SELECT `migration`')) {
            if (!$this->tableExists) {
                throw new \RuntimeException("Table 'oc_mc_migration' doesn't exist");
            }

            return $this->result(array_map(fn ($m) => ['migration' => $m], $this->rowsMigration));
        }

        if (str_starts_with($sql, 'INSERT INTO')) {
            preg_match("/VALUES \('(.+)', NOW\(\)\)/", $sql, $matches);
            $this->rowsMigration[] = stripslashes($matches[1]);

            return $this->result([]);
        }

        return $this->result([]);
    }

    public function escape(string $value): string
    {
        return addslashes($value);
    }

    private function result(array $rows): object
    {
        $result = new stdClass();
        $result->rows = $rows;
        $result->row = $rows[0] ?? [];
        $result->num_rows = count($rows);

        return $result;
    }
}

function write_fixture_migration(string $path, string $body): void
{
    file_put_contents($path, "<?php\nuse MintCart\\Core\\Migration\\MigrationInterface;\nreturn new class implements MigrationInterface {\n public function up(object \$db): void {\n{$body}\n }\n};\n");
}

function rrmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    foreach (scandir($dir) as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $path = $dir . '/' . $entry;
        is_dir($path) ? rrmdir($path) : unlink($path);
    }

    rmdir($dir);
}

function fresh_workspace(): string
{
    $workspace = sys_get_temp_dir() . '/mc_update_orchestrator_test_' . uniqid('', true);
    mkdir($workspace . '/source', 0777, true);
    mkdir($workspace . '/dest', 0777, true);
    mkdir($workspace . '/migrations', 0777, true);
    mkdir($workspace . '/downloads', 0777, true);

    return $workspace;
}

/**
 * Plain object (not an array) so the caller's reference to $deletedKeys
 * keeps seeing pushes the CacheInvalidator closure makes later, without
 * needing an explicit by-reference return.
 */
final class DeletedKeysSpy
{
    /** @var string[] */
    public array $keys = [];
}

/**
 * Builds a fully-wired orchestrator plus its doubles for one scenario, so
 * each scenario gets fresh, independent state.
 *
 * @return array{0: UpdateOrchestrator, 1: FakeSettingModel, 2: FakeDb, 3: DeletedKeysSpy, 4: PackageDownloader}
 */
function build_orchestrator(string $workspace, ?callable $transport = null): array
{
    $settingModel = new FakeSettingModel();
    $maintenance = new MaintenanceMode(new SettingsAdapter($settingModel));
    $db = new FakeDb();
    $migrations = new MigrationManager($db, 'oc_', $workspace . '/migrations');
    $deletedKeys = new DeletedKeysSpy();
    $cache = new CacheInvalidator(function (string $key) use ($deletedKeys) {
        $deletedKeys->keys[] = $key;
    });
    $healthCheck = new HealthCheck();
    $downloader = new PackageDownloader($transport ?? static fn (string $url): string => 'archive-bytes');

    $orchestrator = new UpdateOrchestrator($downloader, $maintenance, new PackageFileInstaller(), $migrations, $cache, $healthCheck);

    return [$orchestrator, $settingModel, $db, $deletedKeys, $downloader];
}

$failures = [];

function assertTrue(bool $condition, string $message, array &$failures): void
{
    if (!$condition) {
        $failures[] = $message;
    }
}

function assertSame($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

// 1. Happy path: every step runs in order, maintenance mode is enabled
//    then disabled again (store was NOT already in maintenance), migration
//    runs, cache is invalidated, health check attached, status "done".
$workspace = fresh_workspace();
file_put_contents($workspace . '/source/theme.json', 'hello');
write_fixture_migration($workspace . '/migrations/202601010000_create_thing.php', '$db->query("CREATE TABLE IF NOT EXISTS mc_thing (id INT)");');
$manifest = PackageManifest::fromArray([
    'name' => 'mint_default',
    'version' => '1.1.0',
    'files' => ['theme.json'],
]);
[$orchestrator, $settingModel, $db, $deletedKeys] = build_orchestrator($workspace);
$result = $orchestrator->apply($manifest, 'https://example.test/pkg.zip', $workspace . '/downloads/pkg.zip', $workspace . '/source', $workspace . '/dest', ['php_version' => '8.1.0']);
assertSame('done', $result['status'], 'happy path: status is done', $failures);
assertSame(
    ['download', 'verify', 'maintenance', 'files', 'migrations', 'cache_clear', 'health_check', 'done'],
    $result['completed_steps'],
    'happy path: every step completes in order',
    $failures
);
assertTrue(is_file($workspace . '/downloads/pkg.zip'), 'happy path: download step wrote the archive to disk', $failures);
assertSame('hello', @file_get_contents($workspace . '/dest/theme.json'), 'happy path: files step copied the declared file', $failures);
assertSame(['202601010000_create_thing.php'], $result['migrations']['applied'], 'happy path: migrations step applied the pending migration', $failures);
assertTrue(in_array('*', $deletedKeys->keys, true), 'happy path: cache_clear step called invalidateAll()', $failures);
assertTrue(array_key_exists('health_check', $result), 'happy path: health_check step attached a HealthCheck::evaluate() result', $failures);
$phpVersionCheck = array_values(array_filter($result['health_check']['checks'], static fn ($c) => $c['key'] === 'php_version'))[0] ?? null;
assertSame('ok', $phpVersionCheck['status'] ?? null, 'happy path: health_check reports the injected php_version signal as ok', $failures);
assertSame('0', $settingModel->storage['config']['config_maintenance'] ?? null, 'happy path: maintenance mode is turned back off at the end (store was not already in maintenance)', $failures);
rrmdir($workspace);

// 2. Store was already in maintenance mode before apply(): must stay in
//    maintenance mode after a successful run, not be silently reopened.
$workspace = fresh_workspace();
file_put_contents($workspace . '/source/theme.json', 'hello');
$manifest = PackageManifest::fromArray(['name' => 'x', 'version' => '1.0.0', 'files' => ['theme.json']]);
[$orchestrator, $settingModel] = build_orchestrator($workspace);
$settingModel->storage['config'] = ['config_maintenance' => '1'];
$result = $orchestrator->apply($manifest, 'https://example.test/pkg.zip', $workspace . '/downloads/pkg.zip', $workspace . '/source', $workspace . '/dest');
assertSame('done', $result['status'], 'already-in-maintenance: status is still done', $failures);
assertSame('1', $settingModel->storage['config']['config_maintenance'], 'already-in-maintenance: store stays in maintenance mode after success (was not this update\'s to close)', $failures);
rrmdir($workspace);

// 3. Checksum mismatch fails at the verify step, before maintenance mode is
//    ever touched.
$workspace = fresh_workspace();
file_put_contents($workspace . '/source/theme.json', 'hello');
$manifest = PackageManifest::fromArray([
    'name' => 'x',
    'version' => '1.0.0',
    'files' => ['theme.json'],
    'checksum' => 'sha256:' . str_repeat('0', 64),
]);
[$orchestrator, $settingModel] = build_orchestrator($workspace);
$result = $orchestrator->apply($manifest, 'https://example.test/pkg.zip', $workspace . '/downloads/pkg.zip', $workspace . '/source', $workspace . '/dest');
assertSame('failed', $result['status'], 'checksum mismatch: status is failed', $failures);
assertSame('verify', $result['failed_step'], 'checksum mismatch: failed at the verify step', $failures);
assertSame(['download'], $result['completed_steps'], 'checksum mismatch: only download completed', $failures);
assertSame([], $settingModel->storage['config'] ?? [], 'checksum mismatch: maintenance mode was never touched', $failures);
rrmdir($workspace);

// 4. Extracted package missing a declared file also fails at verify, same
//    as scenario 3.
$workspace = fresh_workspace();
// theme.json intentionally absent from source/.
$manifest = PackageManifest::fromArray(['name' => 'x', 'version' => '1.0.0', 'files' => ['theme.json']]);
[$orchestrator] = build_orchestrator($workspace);
$result = $orchestrator->apply($manifest, 'https://example.test/pkg.zip', $workspace . '/downloads/pkg.zip', $workspace . '/source', $workspace . '/dest');
assertSame('failed', $result['status'], 'missing extracted file: status is failed', $failures);
assertSame('verify', $result['failed_step'], 'missing extracted file: failed at the verify step', $failures);
assertTrue(str_contains($result['error'], 'theme.json'), 'missing extracted file: error names the missing file', $failures);
rrmdir($workspace);

// 5. A failure during the files step (a declared file missing from the
//    extracted dir, reusing the same source/dest mismatch a real caller
//    could hit if verify's own missingFiles() check were bypassed) leaves
//    the store in maintenance mode rather than reopening it.
$workspace = fresh_workspace();
file_put_contents($workspace . '/source/theme.json', 'hello');
$manifest = PackageManifest::fromArray(['name' => 'x', 'version' => '1.0.0', 'files' => ['theme.json']]);
[$orchestrator, $settingModel] = build_orchestrator($workspace);
// Make the destination root unwritable-equivalent by pointing files at a
// path PackageFileInstaller cannot create (a file where a directory needs
// to go), forcing a mid-flow failure after maintenance mode is enabled.
rmdir($workspace . '/dest');
file_put_contents($workspace . '/dest', 'not a directory');
$result = $orchestrator->apply($manifest, 'https://example.test/pkg.zip', $workspace . '/downloads/pkg.zip', $workspace . '/source', $workspace . '/dest/nested');
assertSame('failed', $result['status'], 'files step failure: status is failed', $failures);
assertSame('files', $result['failed_step'], 'files step failure: failed at the files step', $failures);
assertTrue(in_array('maintenance', $result['completed_steps'], true), 'files step failure: maintenance step had already completed', $failures);
assertTrue(!in_array('files', $result['completed_steps'], true), 'files step failure: files step itself is not marked completed', $failures);
assertSame('1', $settingModel->storage['config']['config_maintenance'] ?? null, 'files step failure: store is left in maintenance mode, not reopened', $failures);
rrmdir($workspace);

// 6. A downloader failure (bad URL scheme) fails at the download step with
//    nothing else touched.
$workspace = fresh_workspace();
$manifest = PackageManifest::fromArray(['name' => 'x', 'version' => '1.0.0']);
[$orchestrator, $settingModel] = build_orchestrator($workspace);
$result = $orchestrator->apply($manifest, 'ftp://example.test/pkg.zip', $workspace . '/downloads/pkg.zip', $workspace . '/source', $workspace . '/dest');
assertSame('failed', $result['status'], 'bad URL scheme: status is failed', $failures);
assertSame('download', $result['failed_step'], 'bad URL scheme: failed at the download step', $failures);
assertSame([], $result['completed_steps'], 'bad URL scheme: no step completed at all', $failures);
rrmdir($workspace);

// 7. No cache/health-check dependency injected: those two steps are simply
//    skipped (recorded as not run), the flow still reaches "done".
$workspace = fresh_workspace();
file_put_contents($workspace . '/source/theme.json', 'hello');
$manifest = PackageManifest::fromArray(['name' => 'x', 'version' => '1.0.0', 'files' => ['theme.json']]);
$settingModel2 = new FakeSettingModel();
$maintenance2 = new MaintenanceMode(new SettingsAdapter($settingModel2));
$db2 = new FakeDb();
$migrations2 = new MigrationManager($db2, 'oc_', $workspace . '/migrations');
$orchestratorNoOptional = new UpdateOrchestrator(
    new PackageDownloader(static fn (string $url): string => 'bytes'),
    $maintenance2,
    new PackageFileInstaller(),
    $migrations2
);
$result = $orchestratorNoOptional->apply($manifest, 'https://example.test/pkg.zip', $workspace . '/downloads/pkg.zip', $workspace . '/source', $workspace . '/dest');
assertSame('done', $result['status'], 'no optional deps: status is still done', $failures);
assertSame(false, $result['cache_clear'], 'no optional deps: cache_clear reports false (nothing to invalidate)', $failures);
assertTrue(!array_key_exists('health_check', $result), 'no optional deps: health_check key is absent, not a fabricated result', $failures);
rrmdir($workspace);

if ($failures) {
    fwrite(STDERR, count($failures) . " failure(s):\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "- {$failure}\n");
    }
    exit(1);
}

echo "PASS: UpdateOrchestrator smoke test (7 scenarios)\n";
exit(0);
