<?php
namespace MintCart\Update;

/**
 * The `mc_installed_file` ledger
 * (`docs/adr/005-theme-extension-update-rollback.md` decision #1).
 *
 * Records, per package, exactly which files were written to disk by the
 * Update Manager `files` step and the sha256 of what actually landed there
 * — "one row per (package_name, file_path), overwritten on every
 * successful install of that package," per the ADR. This is the ledger a
 * future rollback increment needs to detect a file changed since install
 * (comparing the file's *current* on-disk sha256 against this table's last
 * recorded value for that path — never against the *incoming* package's
 * expected content, which would flag every routine content update as a
 * manual edit).
 *
 * `MintCart\Update\PackageFileInstaller::install()`'s `installed` entries
 * (`array{path: string, sha256: string}`) are exactly this table's input —
 * that class's own docblock named this table as "the exact input the
 * future `mc_installed_file` ledger needs once a real caller writes it."
 * This class is that caller's write path; `UpdateOrchestrator::apply()` is
 * the real caller, invoking `record()` right after its `files` step
 * succeeds (see `docs/developer/update/update-orchestrator.md`).
 *
 * $db only needs to duck-type OpenCart's \DB (query()/escape()) — same
 * reasoning as `MintCart\Core\Migration\MigrationManager`, kept testable
 * with a lightweight double rather than a new MintCart-owned DB interface.
 *
 * Deliberately not attempted here: restore/rollback itself (still a later
 * increment per the ADR — this class only ever writes/reads the ledger,
 * never touches a live file), and any "changed since install" comparison
 * (no real consumer yet — `CLAUDE.md` §85).
 */
final class InstalledFileLedger
{
    private object $db;

    private string $prefix;

    public function __construct(object $db, string $dbPrefix)
    {
        $this->db = $db;
        $this->prefix = $dbPrefix;
    }

    /**
     * Upserts one ledger row per installed file, keyed by
     * (package_name, file_path) — a re-install of the same package
     * overwrites its previous rows' `sha256`/`installed_at` rather than
     * accumulating history, matching the ADR's "not a version history"
     * framing (decision #5 makes the same call for snapshot retention).
     *
     * @param array<int, array{path: string, sha256: string}> $installedFiles
     *        Exactly `PackageFileInstaller::install()`'s (or
     *        `UpdateOrchestrator::apply()`'s `files` step result's) own
     *        `installed` entries — this method does not re-derive sha256
     *        itself, it only records what the `files` step already wrote.
     */
    public function record(string $packageName, array $installedFiles, ?string $installedAt = null): void
    {
        $installedAt = $installedAt ?? date('Y-m-d H:i:s');

        foreach ($installedFiles as $file) {
            $this->db->query("
                INSERT INTO `{$this->prefix}mc_installed_file`
                    (`package_name`, `file_path`, `sha256`, `installed_at`)
                VALUES (
                    '" . $this->db->escape($packageName) . "',
                    '" . $this->db->escape($file['path']) . "',
                    '" . $this->db->escape($file['sha256']) . "',
                    '" . $this->db->escape($installedAt) . "'
                )
                ON DUPLICATE KEY UPDATE
                    `sha256` = VALUES(`sha256`),
                    `installed_at` = VALUES(`installed_at`)
            ");
        }
    }

    /**
     * Every currently-recorded (file_path => sha256) pair for one package —
     * the ledger snapshot a future "changed since install" check compares
     * disk state against.
     *
     * @return array<string,string>
     */
    public function forPackage(string $packageName): array
    {
        $query = $this->db->query("
            SELECT `file_path`, `sha256`
            FROM `{$this->prefix}mc_installed_file`
            WHERE `package_name` = '" . $this->db->escape($packageName) . "'
        ");

        $result = [];

        foreach ($query->rows as $row) {
            $result[$row['file_path']] = $row['sha256'];
        }

        return $result;
    }
}
