<?php
namespace MintCart\Core\Migration;

/**
 * MintCart schema migration runner.
 *
 * Migration files live in a single flat directory and are named:
 *
 *   YYYYMMDDHHMM_description.php
 *
 * e.g. 202608211200_create_mc_page.php
 *
 * Each file is a plain PHP script that `return`s an object implementing
 * MigrationInterface (an anonymous class is the expected shape — see
 * upload/system/mintcart/migrations/README.md for a template). Files are
 * applied in filename order, each exactly once, tracked in the
 * `{DB_PREFIX}mc_migration` table. A failing migration rolls back and stops
 * the run — later migrations are not attempted.
 *
 * The tracking table itself is created on demand (CREATE TABLE IF NOT
 * EXISTS) rather than shipped as migration #1, the same way most migration
 * runners bootstrap their own bookkeeping table.
 *
 * $db only needs to duck-type OpenCart's \DB (query()/escape()) — kept as
 * `object` rather than a hard `\DB` type so this class stays testable with
 * a lightweight double, without introducing a MintCart-owned DB interface
 * that has no other consumer.
 */
final class MigrationManager
{
    private object $db;

    private string $prefix;

    private string $migrationsPath;

    /**
     * Memoizes ensureSchema() per instance so calling applied() repeatedly
     * within a single run() (once directly for `skipped`, once again inside
     * pending()) — or from any other caller, e.g. HealthCheck's
     * pending-migrations signal — issues one `CREATE TABLE IF NOT EXISTS`
     * query, not one per call. Idempotent either way; this is purely to
     * avoid an avoidable duplicate query on every run().
     */
    private bool $schemaEnsured = false;

    public function __construct(object $db, string $dbPrefix, string $migrationsPath)
    {
        $this->db = $db;
        $this->prefix = $dbPrefix;
        $this->migrationsPath = rtrim($migrationsPath, '/');
    }

    /**
     * Create the migration tracking table if it does not exist yet.
     */
    public function ensureSchema(): void
    {
        if ($this->schemaEnsured) {
            return;
        }

        $this->db->query("
            CREATE TABLE IF NOT EXISTS `{$this->prefix}mc_migration` (
                `migration_id` INT(11) NOT NULL AUTO_INCREMENT,
                `migration` VARCHAR(255) NOT NULL,
                `applied_at` DATETIME NOT NULL,
                PRIMARY KEY (`migration_id`),
                UNIQUE KEY `migration` (`migration`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $this->schemaEnsured = true;
    }

    /**
     * @return string[] Filenames already recorded as applied, ascending.
     */
    public function applied(): array
    {
        // Ensures the tracking table exists before selecting from it. Not
        // just belt-and-braces: on a fresh install where run() has never
        // been called, a real `\DB\MySQLi` (mysqli_report(MYSQLI_REPORT_ERROR)
        // is enabled) throws on a SELECT against a table that doesn't exist
        // yet, which pending()/applied() would otherwise hit as soon as any
        // caller other than run() (e.g. MintCart\Core\HealthCheck's
        // migrations signal) asks before the first migration has ever run.
        $this->ensureSchema();

        $query = $this->db->query("SELECT `migration` FROM `{$this->prefix}mc_migration` ORDER BY `migration` ASC");

        return array_column($query->rows, 'migration');
    }

    /**
     * @return string[] Filenames found on disk but not yet applied, ascending.
     */
    public function pending(): array
    {
        return array_values(array_diff($this->discover(), $this->applied()));
    }

    /**
     * Run every pending migration, in order. Stops on the first failure.
     *
     * @return array{applied: string[], skipped: string[]}
     */
    public function run(): array
    {
        // applied() (called right below) now ensures the schema itself, so
        // an explicit call here would just be a redundant duplicate
        // CREATE TABLE IF NOT EXISTS on every run().
        $result = [
            'applied' => [],
            'skipped' => $this->applied(),
        ];

        foreach ($this->pending() as $file) {
            $this->runOne($file);
            $result['applied'][] = $file;
        }

        return $result;
    }

    /**
     * @return string[] Migration filenames on disk matching the naming convention, ascending.
     */
    private function discover(): array
    {
        if (!is_dir($this->migrationsPath)) {
            return [];
        }

        $files = glob($this->migrationsPath . '/*.php') ?: [];
        $names = [];

        foreach ($files as $file) {
            $name = basename($file);

            if (preg_match('/^\d{12}_[a-z0-9_]+\.php$/', $name) === 1) {
                $names[] = $name;
            }
        }

        sort($names);

        return $names;
    }

    private function runOne(string $file): void
    {
        $migration = require $this->migrationsPath . '/' . $file;

        if (!$migration instanceof MigrationInterface) {
            throw new \RuntimeException("Migration {$file} must return an instance of " . MigrationInterface::class);
        }

        // Note: CREATE/ALTER TABLE statements implicitly commit in MySQL, so
        // this transaction only buys atomicity for DML-only migrations. Kept
        // anyway per CLAUDE.md's "critical multi-step operations use
        // transactions" — it is a no-op cost for DDL migrations, not a hazard.
        $this->db->query('START TRANSACTION');

        try {
            $migration->up($this->db);

            $this->db->query("
                INSERT INTO `{$this->prefix}mc_migration` (`migration`, `applied_at`)
                VALUES ('" . $this->db->escape($file) . "', NOW())
            ");

            $this->db->query('COMMIT');
        } catch (\Throwable $e) {
            $this->db->query('ROLLBACK');

            throw new \RuntimeException("Migration {$file} failed: " . $e->getMessage(), 0, $e);
        }
    }
}
