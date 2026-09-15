<?php
/**
 * Standalone smoke test for MC-0012: ModelSettingSetting::editSetting()
 * wraps its delete-then-reinsert sequence in a transaction.
 *
 * No PHPUnit/Composer and no MySQL server in this environment (same gap
 * every previous phase's test carries), so this drives the real model
 * class against an in-memory FakeDb double that duck-types OpenCart's \DB
 * (query()/escape()) and actually implements transaction semantics
 * (buffers writes issued between START TRANSACTION and COMMIT, discards
 * them on ROLLBACK) - the only way to prove atomicity rather than merely
 * asserting the right SQL strings were sent. Same approach as
 * tests/mintcart/core/migration-manager-test.php and
 * tests/mintcart/design/homepage-model-test.php (loads the real
 * upload/system/engine/model.php base class rather than re-implementing
 * it).
 *
 * Run: php tests/mintcart/fixes/mc-0012-setting-transaction-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

define('DB_PREFIX', 'oc_');

require __DIR__ . '/../../../upload/system/engine/model.php';
require __DIR__ . '/../../../upload/admin/model/setting/setting.php';

$failures = [];

function assertSame__($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

function assertTrue__(bool $condition, string $message, array &$failures): void
{
    if (!$condition) {
        $failures[] = $message;
    }
}

/**
 * In-memory double for OpenCart's \DB that actually implements transaction
 * semantics: DELETE/INSERT issued between START TRANSACTION and COMMIT are
 * buffered and only applied to the "committed" row set on COMMIT; ROLLBACK
 * discards them. A committed getSetting()-equivalent read only ever sees
 * the committed state, so this can prove a failed editSetting() call
 * leaves the previous settings intact instead of half-deleted - the exact
 * defect MC-0012 fixes.
 *
 * A special sentinel key ('config_boom' - it must start with the code
 * prefix, e.g. 'config', to pass editSetting()'s own
 * substr($key, 0, strlen($code)) == $code filter) makes the simulated
 * INSERT throw, so a failure partway through the loop can be exercised
 * without a real DB.
 */
final class FakeDb
{
    /** @var array<int, array{store_id:int, code:string, key:string, value:string, serialized:string}> */
    private array $committedRows = [];

    /** @var list<callable():void>|null Buffered ops while inside a transaction; null when not in one. */
    private ?array $pending = null;

    /** @var string[] */
    public array $log = [];

    public function query(string $sql): object
    {
        $sql = trim($sql);
        $this->log[] = preg_replace('/\s+/', ' ', $sql);

        if ($sql === 'START TRANSACTION') {
            $this->pending = [];

            return $this->result([]);
        }

        if ($sql === 'COMMIT') {
            foreach ($this->pending ?? [] as $op) {
                $op();
            }

            $this->pending = null;

            return $this->result([]);
        }

        if ($sql === 'ROLLBACK') {
            $this->pending = null;

            return $this->result([]);
        }

        if (preg_match('/^DELETE FROM `' . DB_PREFIX . "setting` WHERE store_id = '(\d+)' AND `code` = '([^']*)'$/", $sql, $m)) {
            $storeId = (int) $m[1];
            $code = stripslashes($m[2]);

            $this->apply(function () use ($storeId, $code): void {
                $this->committedRows = array_values(array_filter(
                    $this->committedRows,
                    fn ($row) => !($row['store_id'] === $storeId && $row['code'] === $code)
                ));
            });

            return $this->result([]);
        }

        if (preg_match(
            "/^INSERT INTO " . DB_PREFIX . "setting SET store_id = '(\d+)', `code` = '([^']*)', `key` = '([^']*)', `value` = '((?:[^'\\\\]|\\\\.)*)'(, serialized = '1')?\$/",
            $sql,
            $m
        )) {
            $storeId = (int) $m[1];
            $code = stripslashes($m[2]);
            $key = stripslashes($m[3]);
            $value = stripslashes($m[4]);
            $serialized = isset($m[5]) && $m[5] !== '' ? '1' : '0';

            if ($key === 'config_boom') {
                throw new \Exception('FakeDb: simulated query failure for key "config_boom"');
            }

            $this->apply(function () use ($storeId, $code, $key, $value, $serialized): void {
                $this->committedRows[] = [
                    'store_id' => $storeId,
                    'code' => $code,
                    'key' => $key,
                    'value' => $value,
                    'serialized' => $serialized,
                ];
            });

            return $this->result([]);
        }

        if (preg_match('/^SELECT \* FROM ' . DB_PREFIX . "setting WHERE store_id = '(\d+)' AND `code` = '([^']*)'$/", $sql, $m)) {
            $storeId = (int) $m[1];
            $code = stripslashes($m[2]);

            $rows = array_values(array_filter(
                $this->committedRows,
                fn ($row) => $row['store_id'] === $storeId && $row['code'] === $code
            ));

            return $this->result($rows);
        }

        throw new \RuntimeException('FakeDb: unrecognized query: ' . $sql);
    }

    /** Buffers $op while inside a transaction, applies it immediately otherwise. */
    private function apply(callable $op): void
    {
        if ($this->pending !== null) {
            $this->pending[] = $op;
        } else {
            $op();
        }
    }

    public function escape(string $value): string
    {
        return addslashes($value);
    }

    private function result(array $rows): object
    {
        return (object) [
            'rows' => $rows,
            'row' => $rows[0] ?? [],
            'num_rows' => count($rows),
        ];
    }
}

final class FakeRegistry
{
    public function __construct(private FakeDb $db)
    {
    }

    public function get(string $key)
    {
        return $key === 'db' ? $this->db : null;
    }
}

$db = new FakeDb();
$model = new ModelSettingSetting(new FakeRegistry($db));

// 1. editSetting() wraps the sequence in a transaction: START TRANSACTION
// first, COMMIT last, in that order.
$model->editSetting('config', ['config_name' => 'My Store', 'config_email' => 'shop@example.test']);

assertSame__('START TRANSACTION', $db->log[0], 'Scenario 1: editSetting() starts a transaction before any DELETE/INSERT', $failures);
assertSame__('COMMIT', $db->log[count($db->log) - 1], 'Scenario 1: editSetting() commits after every INSERT succeeds', $failures);
assertTrue__(!in_array('ROLLBACK', $db->log, true), 'Scenario 1: a successful call never rolls back', $failures);

$saved = $model->getSetting('config');
assertSame__('My Store', $saved['config_name'], 'Scenario 1: the new value is readable after commit', $failures);
assertSame__('shop@example.test', $saved['config_email'], 'Scenario 1: every key in the batch is saved, not just the first', $failures);

// 2. A second, unrelated code group is unaffected by the first call.
$model->editSetting('mintcart_wizard', ['mintcart_wizard_completed_steps' => ['welcome']]);
assertSame__(['welcome'], $model->getSetting('mintcart_wizard')['mintcart_wizard_completed_steps'], 'Scenario 2: a different code group saves independently', $failures);
assertSame__('My Store', $model->getSetting('config')['config_name'], 'Scenario 2: editing one code group does not disturb another', $failures);

// 3. THE FIX: a failure partway through the INSERT loop (simulated via the
// 'config_boom' sentinel key, standing in for e.g. a dropped DB connection mid-
// write, MC-0011) rolls back instead of leaving 'config' half-deleted.
$threw = false;
$db->log = [];

try {
    $model->editSetting('config', [
        'config_name' => 'Renamed Store',
        'config_boom' => 'this insert fails',
        'config_telephone' => '555-0100',
    ]);
} catch (\Exception $e) {
    $threw = true;
    assertSame__('FakeDb: simulated query failure for key "config_boom"', $e->getMessage(), 'Scenario 3: the original exception propagates to the caller unchanged', $failures);
}

assertTrue__($threw, 'Scenario 3: a mid-loop failure must throw, not fail silently', $failures);
assertTrue__(in_array('ROLLBACK', $db->log, true), 'Scenario 3: a mid-loop failure must trigger a ROLLBACK', $failures);
assertTrue__(!in_array('COMMIT', $db->log, true), 'Scenario 3: a rolled-back call must never also commit', $failures);

// The critical assertion: without MC-0012, the DELETE at the top of
// editSetting() would already have removed every 'config' row before the
// loop even started, so a failure here would leave getSetting('config')
// returning an empty array - every existing setting in that group lost,
// not just the one that failed to save. With the transaction, the DELETE
// itself is part of the rolled-back work, so the pre-existing values must
// still be there.
$afterFailure = $model->getSetting('config');
assertSame__('My Store', $afterFailure['config_name'] ?? null, 'Scenario 3 (the fix): config_name from before the failed call is still present - not lost by the half-completed delete+reinsert', $failures);
assertSame__('shop@example.test', $afterFailure['config_email'] ?? null, 'Scenario 3 (the fix): config_email from before the failed call is still present too', $failures);
assertTrue__(!isset($afterFailure['config_telephone']), 'Scenario 3: none of the new batch (including the keys that would have inserted fine) was partially applied', $failures);

// 4. A subsequent, unrelated call still works normally after a rollback
// (the model itself holds no broken state across calls).
$model->editSetting('config', ['config_name' => 'Final Store Name']);
assertSame__('Final Store Name', $model->getSetting('config')['config_name'], 'Scenario 4: a later successful call works normally after an earlier rollback', $failures);

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo "PASS: MC-0012 ModelSettingSetting::editSetting() transaction test (4 scenarios)\n";
exit(0);
