<?php
/**
 * Standalone smoke test for MintCart\Core\Migration\MigrationManager.
 *
 * No PHPUnit/Composer in the repo yet (Phase 1 foundation only) and no
 * MySQL server available in this environment, so this exercises the
 * manager's logic against a minimal in-memory DB double that duck-types
 * OpenCart's \DB (query()/escape()).
 *
 * Run: php tests/mintcart/core/migration-manager-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/core/migration/migrationinterface.php';
require __DIR__ . '/../../../upload/system/library/mintcart/core/migration/migrationmanager.php';

use MintCart\Core\Migration\MigrationInterface;
use MintCart\Core\Migration\MigrationManager;

/**
 * In-memory double for OpenCart's \DB. Understands just enough SQL shape
 * (CREATE TABLE IF NOT EXISTS mc_migration / SELECT migration / INSERT /
 * START TRANSACTION / COMMIT / ROLLBACK) to drive MigrationManager.
 */
final class FakeDb
{
    /** @var string[] */
    public array $rowsMigration = [];

    /** @var string[] */
    public array $log = [];

    /**
     * Real `\DB\MySQLi` (mysqli_report(MYSQLI_REPORT_ERROR) enabled) throws
     * on a SELECT against a table that was never CREATEd. This double
     * reproduces that instead of always answering — otherwise it can't
     * catch a caller that queries `mc_migration` before ensuring it exists.
     */
    private bool $tableExists = false;

    public function query(string $sql): object
    {
        $sql = trim($sql);
        $this->log[] = preg_replace('/\s+/', ' ', $sql);

        if (str_starts_with($sql, 'CREATE TABLE')) {
            $this->tableExists = true;

            return $this->result([]);
        }

        if (str_starts_with($sql, 'SELECT `migration`')) {
            if (!$this->tableExists) {
                throw new \RuntimeException("Table 'oc_mc_migration' doesn't exist");
            }

            $rows = array_map(fn ($m) => ['migration' => $m], $this->rowsMigration);

            return $this->result($rows);
        }

        if (str_starts_with($sql, 'INSERT INTO')) {
            preg_match("/VALUES \('(.+)', NOW\(\)\)/", $sql, $matches);
            $this->rowsMigration[] = stripslashes($matches[1]);

            return $this->result([]);
        }

        // START TRANSACTION / COMMIT / ROLLBACK
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
    file_put_contents($path, "<?php\nuse MintCart\\Core\\Migration\\MigrationInterface;\nreturn new class implements MigrationInterface {\n public function up(object \$db): void {\n$body\n }\n};\n");
}

function fail(string $message): void
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function check(bool $condition, string $message): void
{
    if (!$condition) {
        fail($message);
    }
}

$tmpDir = sys_get_temp_dir() . '/mc_migration_test_' . uniqid();
mkdir($tmpDir);

try {
    write_fixture_migration($tmpDir . '/202601010000_create_thing.php', '$db->query("CREATE TABLE IF NOT EXISTS mc_thing (id INT)");');
    write_fixture_migration($tmpDir . '/202601020000_create_other.php', '$db->query("CREATE TABLE IF NOT EXISTS mc_other (id INT)");');
    // Should be ignored: does not match the naming convention.
    file_put_contents($tmpDir . '/not-a-migration.php', "<?php\nreturn null;\n");

    // A caller other than run() (e.g. MintCart\Core\HealthCheck's pending-
    // migrations signal, via ModelMintcartHealth) may ask before any
    // migration has ever been run — the tracking table doesn't exist yet.
    // Must not throw, and must report every discovered migration as pending.
    // Uses its own DB double so it doesn't affect the "tracking table
    // created once per run()" count checked further down.
    $freshDb = new FakeDb();
    $freshManager = new MigrationManager($freshDb, 'oc_', $tmpDir);
    check($freshManager->applied() === [], 'applied() before any run() must return empty, not throw, when the tracking table does not exist yet');
    check($freshManager->pending() === ['202601010000_create_thing.php', '202601020000_create_other.php'], 'pending() before any run() must list every discovered migration');

    $db = new FakeDb();
    $manager = new MigrationManager($db, 'oc_', $tmpDir);

    // First run applies both, in filename order.
    $result = $manager->run();

    check($result['applied'] === ['202601010000_create_thing.php', '202601020000_create_other.php'], 'first run should apply both migrations in order, got: ' . json_encode($result['applied']));
    check($result['skipped'] === [], 'first run should have nothing already applied');
    check(count(array_filter($db->log, fn ($q) => str_starts_with($q, 'CREATE TABLE IF NOT EXISTS `oc_mc_migration`'))) === 1, 'tracking table should be created once');

    // Second run: everything already applied, nothing new.
    $result2 = $manager->run();

    check($result2['applied'] === [], 'second run should apply nothing (already applied), got: ' . json_encode($result2['applied']));
    check($result2['skipped'] === ['202601010000_create_thing.php', '202601020000_create_other.php'], 'second run should report both as already applied');

    // A new migration dropped in afterwards is picked up on the next run.
    write_fixture_migration($tmpDir . '/202601030000_create_third.php', '$db->query("CREATE TABLE IF NOT EXISTS mc_third (id INT)");');
    $result3 = $manager->run();

    check($result3['applied'] === ['202601030000_create_third.php'], 'third run should only apply the newly added migration');

    // A failing migration rolls back and does not get recorded as applied.
    write_fixture_migration($tmpDir . '/202601040000_broken.php', 'throw new \\RuntimeException("boom");');

    $threw = false;

    try {
        $manager->run();
    } catch (\RuntimeException $e) {
        $threw = true;
        check(str_contains($e->getMessage(), '202601040000_broken.php'), 'exception should name the failing migration file');
    }

    check($threw, 'a failing migration must throw, not fail silently');
    check(!in_array('202601040000_broken.php', $manager->applied(), true), 'a failed migration must not be recorded as applied');
    check(in_array('ROLLBACK', $db->log, true), 'a failed migration must trigger a ROLLBACK');

    echo "PASS: MigrationManager smoke test (" . count($db->log) . " queries logged)\n";
} finally {
    array_map('unlink', glob($tmpDir . '/*'));
    rmdir($tmpDir);
}
