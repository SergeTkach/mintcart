<?php
/**
 * Standalone regression test for MC-0019: DB\PDO::query() used to have no
 * reconnect handling at all for a dropped connection — unlike DB\MySQLi
 * (MC-0011), which already retries once on driver error code 2006 ("MySQL
 * server has gone away") or 2013 ("Lost connection to MySQL server during
 * query"). After the fix, DB\PDO::query() does the same: exactly those two
 * driver error codes trigger one reconnect + one retry of the same query
 * (and the same bound params); any other error code still behaves exactly
 * as before (throws on a real PDOException, or falls through to the
 * pre-existing empty-result fallback on a silent ERRMODE_SILENT failure —
 * this class never sets PDO::ATTR_ERRMODE, so SILENT is the real default
 * in production); a connection that drops twice in a row (retry also hits
 * a recoverable error) still doesn't reconnect a third time.
 *
 * No MySQL server available in this environment, so this never touches a
 * real \PDO. DB\PDO::connect() (the method that creates the real \PDO
 * instance) is protected specifically so this test can override it in a
 * subclass with a fake, duck-typed connection object instead — the same
 * pattern tests/mintcart/fixes/mc-0011-mysqli-reconnect-test.php already
 * established for DB\MySQLi. DB\PDO's two-object shape (a connection that
 * hands out a separate statement object) needs one fake for each: a
 * FakeConnection (duck-types \PDO::prepare()) and a FakeStatement
 * (duck-types \PDOStatement::execute()/errorInfo()/fetch()/rowCount()).
 *
 * Run: php tests/mintcart/fixes/mc-0019-pdo-reconnect-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

namespace DB {
    require __DIR__ . '/../../../upload/system/library/db/pdo.php';

    /**
     * Duck-types the slice of \PDOStatement's public surface
     * DB\PDO::runQuery() actually uses: execute($params), errorInfo() and
     * fetch($mode). Each instance is pre-loaded with one canned response so
     * a test can script exactly what a "statement" does when executed once.
     * fetch() always returns false (no rows) — row contents aren't what
     * MC-0019 is about; only the reconnect-and-retry control flow is.
     */
    final class FakeStatement
    {
        public int $executeCalls = 0;

        public function __construct(private bool $executeReturns, private array $errorInfoValue)
        {
        }

        public function execute($params = [])
        {
            $this->executeCalls++;

            return $this->executeReturns;
        }

        public function errorInfo()
        {
            return $this->errorInfoValue;
        }

        public function fetch($mode)
        {
            return false;
        }

        public function rowCount()
        {
            return 0;
        }
    }

    /**
     * A second FakeStatement shape whose execute() throws a PDOException
     * instead of returning false — covers runQuery()'s catch block (the
     * ERRMODE_EXCEPTION path), separately from FakeStatement's silent
     * (ERRMODE_SILENT) false-return path above. Not a subclass of
     * FakeStatement: PDOStatement itself isn't final, but keeping these
     * independent duck types avoids coupling the two scenarios together.
     */
    final class ThrowingStatement
    {
        public int $executeCalls = 0;

        public function __construct(private array $errorInfoForException)
        {
        }

        public function execute($params = [])
        {
            $this->executeCalls++;

            $exception = new \PDOException('SQLSTATE[HY000]: General error: ' . $this->errorInfoForException[1]);
            $exception->errorInfo = $this->errorInfoForException;

            throw $exception;
        }

        public function errorInfo()
        {
            return $this->errorInfoForException;
        }

        public function fetch($mode)
        {
            return false;
        }

        public function rowCount()
        {
            return 0;
        }
    }

    /**
     * Duck-types the one slice of \PDO's public surface DB\PDO::runQuery()
     * actually uses: prepare($sql). Hands out exactly one FakeStatement per
     * FakeConnection, matching one query() call per connection.
     */
    final class FakeConnection
    {
        public int $prepareCalls = 0;

        public function __construct(private $statement)
        {
        }

        public function prepare($sql)
        {
            $this->prepareCalls++;

            return $this->statement;
        }
    }

    /**
     * Overrides connect() (protected in DB\PDO specifically for this) to
     * pop the next FakeConnection off a queue instead of opening a real
     * \PDO socket. Counts how many times connect() ran, so a test can
     * assert exactly one reconnect happened (not zero, not two).
     */
    final class TestablePDO extends PDO
    {
        /** @var array<int, FakeConnection> */
        private array $connectionQueue;

        public int $connectCount = 0;

        public function __construct(array $connectionQueue)
        {
            $this->connectionQueue = $connectionQueue;

            parent::__construct('fake-host', 'fake-user', 'fake-pass', 'fake-db');
        }

        protected function connect()
        {
            $this->connectCount++;
            $this->connection = array_shift($this->connectionQueue);
        }
    }
}

namespace {
    $failures = [];

    function assertSame($expected, $actual, string $message, array &$failures): void
    {
        if ($expected !== $actual) {
            $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
        }
    }

    // 1. No error at all: query() returns the success result, exactly one
    //    connect() call (the constructor's), no retry.
    $db = new \DB\TestablePDO([
        new \DB\FakeConnection(new \DB\FakeStatement(true, ['00000', null, null])),
    ]);
    $result = $db->query('UPDATE x SET y = 1');
    assertSame(0, $result->num_rows, 'no error: query() returns the success result', $failures);
    assertSame(1, $db->connectCount, 'no error: connect() called exactly once (constructor only)', $failures);

    // 2. Recoverable error code (2006) via a silent (ERRMODE_SILENT)
    //    execute() === false on the first attempt, success on the retry
    //    after reconnecting: query() must return the retry's result, and
    //    connect() must have run exactly twice (constructor + one
    //    reconnect).
    $db = new \DB\TestablePDO([
        new \DB\FakeConnection(new \DB\FakeStatement(false, ['HY000', 2006, 'MySQL server has gone away'])),
        new \DB\FakeConnection(new \DB\FakeStatement(true, ['00000', null, null])),
    ]);
    $result = $db->query('SELECT * FROM x');
    assertSame(0, $result->num_rows, 'errno 2006 (silent) then success: query() returns the retry\'s result', $failures);
    assertSame(2, $db->connectCount, 'errno 2006 (silent) then success: connect() called exactly twice (initial + one reconnect)', $failures);

    // 3. Recoverable error code (2013), same story as #2 with the other
    //    recoverable code.
    $db = new \DB\TestablePDO([
        new \DB\FakeConnection(new \DB\FakeStatement(false, ['HY000', 2013, 'Lost connection to MySQL server during query'])),
        new \DB\FakeConnection(new \DB\FakeStatement(true, ['00000', null, null])),
    ]);
    $result = $db->query('SELECT * FROM x');
    assertSame(0, $result->num_rows, 'errno 2013 (silent) then success: query() returns the retry\'s result', $failures);
    assertSame(2, $db->connectCount, 'errno 2013 (silent) then success: connect() called exactly twice (initial + one reconnect)', $failures);

    // 4. Recoverable error code on BOTH the original attempt and the retry:
    //    must NOT reconnect a third time (matches pre-fix "unretried"
    //    behavior for the retry itself) — falls through to the pre-existing
    //    empty-result fallback, same as any other silent execute() failure
    //    always has.
    $db = new \DB\TestablePDO([
        new \DB\FakeConnection(new \DB\FakeStatement(false, ['HY000', 2006, 'MySQL server has gone away'])),
        new \DB\FakeConnection(new \DB\FakeStatement(false, ['HY000', 2006, 'MySQL server has gone away'])),
    ]);
    $result = $db->query('SELECT * FROM x');
    assertSame(0, $result->num_rows, 'errno 2006 twice: query() falls through to the empty-result fallback', $failures);
    assertSame([], $result->row, 'errno 2006 twice: empty-result fallback has an empty row', $failures);
    assertSame(2, $db->connectCount, 'errno 2006 twice: connect() called exactly twice, never a third reconnect', $failures);

    // 5. Non-recoverable error code (1146, "table doesn't exist") via a
    //    silent execute() === false: must fall through to the same
    //    empty-result fallback immediately, no reconnect attempted at all
    //    — identical to this class's pre-fix behavior for any query error
    //    under its default ERRMODE_SILENT configuration.
    $db = new \DB\TestablePDO([
        new \DB\FakeConnection(new \DB\FakeStatement(false, ['42S02', 1146, "Table 'x' doesn't exist"])),
    ]);
    $result = $db->query('SELECT * FROM x');
    assertSame(0, $result->num_rows, 'non-recoverable errno: query() falls through to the empty-result fallback', $failures);
    assertSame(1, $db->connectCount, 'non-recoverable errno: connect() called exactly once, no reconnect attempted', $failures);

    // 6. Recoverable error code surfaced as a thrown PDOException (the
    //    ERRMODE_EXCEPTION path, covered defensively even though this
    //    class never sets that mode itself — a future change, or a
    //    php.ini-level default, could still produce it): the catch
    //    block's own reconnect-and-retry path must behave the same as the
    //    silent path above.
    $db = new \DB\TestablePDO([
        new \DB\FakeConnection(new \DB\ThrowingStatement(['HY000', 2006, 'MySQL server has gone away'])),
        new \DB\FakeConnection(new \DB\FakeStatement(true, ['00000', null, null])),
    ]);
    $result = $db->query('SELECT * FROM x');
    assertSame(0, $result->num_rows, 'errno 2006 (thrown) then success: query() returns the retry\'s result', $failures);
    assertSame(2, $db->connectCount, 'errno 2006 (thrown) then success: connect() called exactly twice (initial + one reconnect)', $failures);

    // 7. Non-recoverable error thrown as a PDOException: must still throw
    //    (unchanged pre-fix behavior for the ERRMODE_EXCEPTION path), no
    //    reconnect attempted.
    $db = new \DB\TestablePDO([
        new \DB\FakeConnection(new \DB\ThrowingStatement(['42S02', 1146, "Table 'x' doesn't exist"])),
    ]);
    $threw = false;
    try {
        $db->query('SELECT * FROM x');
    } catch (\Exception $e) {
        $threw = true;
        assertSame(true, str_contains($e->getMessage(), 'General error: 1146'), 'non-recoverable errno (thrown): exception message carries the original PDOException text', $failures);
    }
    assertSame(true, $threw, 'non-recoverable errno (thrown): query() throws instead of retrying', $failures);
    assertSame(1, $db->connectCount, 'non-recoverable errno (thrown): connect() called exactly once, no reconnect attempted', $failures);

    if ($failures) {
        fwrite(STDERR, count($failures) . " failure(s):\n");
        foreach ($failures as $failure) {
            fwrite(STDERR, "- {$failure}\n");
        }
        exit(1);
    }

    echo "PASS: DB\\PDO reconnect-on-dropped-connection (MC-0019, 7 scenarios)\n";
    exit(0);
}
