<?php
/**
 * Standalone smoke test for MintCart\Update\InstalledFileLedger.
 *
 * No PHPUnit/Composer in the repo yet, no MySQL server available in this
 * environment (same pre-existing gap as every other test in this repo).
 * Exercises record()/forPackage() against a minimal in-memory DB double
 * that duck-types OpenCart's \DB (query()/escape()), understanding just
 * enough of this class's own two query shapes (INSERT ... ON DUPLICATE
 * KEY UPDATE / SELECT ... WHERE package_name = '...') to drive it —
 * same technique migration-manager-test.php and update-orchestrator-test.php
 * already use for their own FakeDb doubles.
 *
 * Run: php tests/mintcart/update/installed-file-ledger-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/update/installedfileledger.php';

use MintCart\Update\InstalledFileLedger;

/**
 * In-memory double for OpenCart's \DB, keyed exactly like the real
 * `mc_installed_file` table's unique key: (package_name, file_path).
 */
final class FakeLedgerDb
{
    /** @var array<string, array<string, array{sha256: string, installed_at: string}>> package_name => file_path => row */
    public array $rows = [];

    /** @var string[] */
    public array $log = [];

    public function query(string $sql): object
    {
        $sql = trim($sql);
        $this->log[] = preg_replace('/\s+/', ' ', $sql);

        if (str_starts_with($sql, 'INSERT INTO')) {
            $q = "((?:[^'\\\\]|\\\\.)*)";
            preg_match(
                "/VALUES \(\s*'{$q}',\s*'{$q}',\s*'{$q}',\s*'{$q}'\s*\)/s",
                $sql,
                $matches
            );

            [, $packageName, $filePath, $sha256, $installedAt] = array_map('stripslashes', $matches);

            $this->rows[$packageName][$filePath] = [
                'sha256' => $sha256,
                'installed_at' => $installedAt,
            ];

            return $this->result([]);
        }

        if (str_starts_with($sql, 'SELECT `file_path`')) {
            preg_match("/WHERE `package_name` = '((?:[^'\\\\]|\\\\.)*)'/", $sql, $matches);
            $packageName = stripslashes($matches[1] ?? '');

            $rows = [];
            foreach ($this->rows[$packageName] ?? [] as $filePath => $row) {
                $rows[] = ['file_path' => $filePath, 'sha256' => $row['sha256']];
            }

            return $this->result($rows);
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

// 1. record() writes one row per installed file, readable back via forPackage().
$db = new FakeLedgerDb();
$ledger = new InstalledFileLedger($db, 'oc_');
$ledger->record('mint_default', [
    ['path' => 'theme.json', 'sha256' => str_repeat('a', 64)],
    ['path' => 'catalog/view/theme/mint_default/template/mint/components/product-card.twig', 'sha256' => str_repeat('b', 64)],
], '2026-09-19 12:00:00');
assertSame(
    [
        'theme.json' => str_repeat('a', 64),
        'catalog/view/theme/mint_default/template/mint/components/product-card.twig' => str_repeat('b', 64),
    ],
    $ledger->forPackage('mint_default'),
    'record(): both installed files are readable back via forPackage()',
    $failures
);

// 2. A different package's files never leak into forPackage() for this one.
$ledger->record('mc_core', [['path' => 'system/mintcart/bootstrap.php', 'sha256' => str_repeat('c', 64)]], '2026-09-19 12:00:00');
assertSame(
    ['theme.json', 'catalog/view/theme/mint_default/template/mint/components/product-card.twig'],
    array_keys($ledger->forPackage('mint_default')),
    'forPackage(): scoped to the requested package only',
    $failures
);

// 3. Re-recording the same (package_name, file_path) overwrites the sha256 —
//    "overwritten on every successful install," not a version history
//    (ADR 005 decisions #1/#5), rather than accumulating duplicate rows.
$ledger->record('mint_default', [
    ['path' => 'theme.json', 'sha256' => str_repeat('d', 64)],
], '2026-09-19 13:00:00');
$forMintDefault = $ledger->forPackage('mint_default');
assertSame(str_repeat('d', 64), $forMintDefault['theme.json'], 're-record: sha256 is overwritten, not duplicated', $failures);
assertSame(2, count($forMintDefault), 're-record: still exactly one row per distinct file_path, no duplicate accumulation', $failures);

// 4. record() defaults installed_at to now() when not passed explicitly —
//    just confirm it does not throw and produces a well-formed row.
$db2 = new FakeLedgerDb();
$ledger2 = new InstalledFileLedger($db2, 'oc_');
$ledger2->record('x', [['path' => 'a.php', 'sha256' => str_repeat('0', 64)]]);
assertTrue(
    isset($db2->rows['x']['a.php']['installed_at']) && $db2->rows['x']['a.php']['installed_at'] !== '',
    'record(): installed_at defaults to a non-empty timestamp when omitted',
    $failures
);

// 5. forPackage() for a package with no recorded files returns an empty array.
assertSame([], $ledger2->forPackage('never-installed'), 'forPackage(): unknown package returns an empty array', $failures);

// 6. Values are escaped (a single quote in a path does not break the query).
$db3 = new FakeLedgerDb();
$ledger3 = new InstalledFileLedger($db3, 'oc_');
$ledger3->record("weird's package", [['path' => "it's/a/path.php", 'sha256' => str_repeat('e', 64)]], '2026-09-19 12:00:00');
assertSame(
    [str_repeat('e', 64)],
    array_values($ledger3->forPackage("weird's package")),
    'record()/forPackage(): a value containing a single quote round-trips correctly',
    $failures
);

if ($failures) {
    fwrite(STDERR, count($failures) . " failure(s):\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "- {$failure}\n");
    }
    exit(1);
}

echo "PASS: InstalledFileLedger smoke test (6 scenarios)\n";
exit(0);
