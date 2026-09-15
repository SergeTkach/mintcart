<?php
/**
 * Standalone regression test for MC-0011: DB\MySQLi::query() used to throw
 * immediately on ANY mysqli error, including errno 2006 ("MySQL server has
 * gone away") and 2013 ("Lost connection to MySQL server during query") —
 * both mean the TCP connection dropped, not that the query itself is bad.
 * After the fix, exactly those two errnos trigger one reconnect + one
 * retry of the same query; any other errno still throws immediately, and a
 * connection that drops twice in a row (retry also hits a recoverable
 * errno) still throws rather than reconnecting forever.
 *
 * No MySQL server available in this environment, so this never touches a
 * real \MySQLi. DB\MySQLi::connect() (the method that creates the real
 * \MySQLi instance) is protected specifically so this test can override it
 * in a subclass with a fake, duck-typed connection object instead — the
 * same "duck-typed OpenCart dependency, injected instead of a real
 * network/DB object" shape tests/mintcart/core/migration-manager-test.php
 * and tests/mintcart/fixes/mc-0006-db-connected-adaptor-mismatch-test.php
 * already established.
 *
 * Run: php tests/mintcart/fixes/mc-0011-mysqli-reconnect-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

namespace DB {
    require __DIR__ . '/../../../upload/system/library/db/mysqli.php';

    /**
     * Duck-types the one slice of \MySQLi's public surface DB\MySQLi::query()
     * actually uses: $errno, $error and query($sql). Each instance is
     * pre-loaded with one canned response so a test can script exactly what
     * a "connection" does when queried once.
     */
    final class FakeConnection
    {
        public int $errno = 0;
        public string $error = '';
        public int $queryCalls = 0;

        public function __construct(int $respondErrno, string $respondError, private $returnValue)
        {
            $this->errno = 0;
            $this->respondErrno = $respondErrno;
            $this->respondError = $respondError;
        }

        private int $respondErrno;
        private string $respondError;

        public function query($sql)
        {
            $this->queryCalls++;
            $this->errno = $this->respondErrno;
            $this->error = $this->respondError;

            return $this->returnValue;
        }

        // DB\MySQLi::__destruct() calls $this->connection->close() on
        // whatever the last connect() produced.
        public function close(): void
        {
        }
    }

    /**
     * Overrides connect() (protected in DB\MySQLi specifically for this)
     * to pop the next FakeConnection off a queue instead of opening a real
     * \MySQLi socket. Counts how many times connect() ran, so a test can
     * assert exactly one reconnect happened (not zero, not two).
     */
    final class TestableMySQLi extends MySQLi
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

    // 1. No error at all: query() returns true (non-SELECT statement,
    //    matching the pre-existing "not a mysqli_result" branch), exactly
    //    one connect() call (the constructor's), no retry attempted.
    $db = new \DB\TestableMySQLi([
        new \DB\FakeConnection(0, '', true),
    ]);
    assertSame(true, $db->query('UPDATE x SET y = 1'), 'no error: query() returns the connection\'s result', $failures);
    assertSame(1, $db->connectCount, 'no error: connect() called exactly once (constructor only)', $failures);

    // 2. Recoverable errno (2006) on the first attempt, success on the
    //    retry after reconnecting: query() must return the retry's result,
    //    and connect() must have run exactly twice (constructor + one
    //    reconnect).
    $db = new \DB\TestableMySQLi([
        new \DB\FakeConnection(2006, 'MySQL server has gone away', false),
        new \DB\FakeConnection(0, '', true),
    ]);
    assertSame(true, $db->query('SELECT * FROM x'), 'errno 2006 then success: query() returns the retry\'s result', $failures);
    assertSame(2, $db->connectCount, 'errno 2006 then success: connect() called exactly twice (initial + one reconnect)', $failures);

    // 3. Recoverable errno (2013) on the first attempt, same story as #2
    //    with the other recoverable errno.
    $db = new \DB\TestableMySQLi([
        new \DB\FakeConnection(2013, 'Lost connection to MySQL server during query', false),
        new \DB\FakeConnection(0, '', true),
    ]);
    assertSame(true, $db->query('SELECT * FROM x'), 'errno 2013 then success: query() returns the retry\'s result', $failures);
    assertSame(2, $db->connectCount, 'errno 2013 then success: connect() called exactly twice (initial + one reconnect)', $failures);

    // 4. Recoverable errno on BOTH the original attempt and the retry:
    //    must throw (not reconnect a second time / loop forever), and
    //    connect() must have run exactly twice, never a third time.
    $db = new \DB\TestableMySQLi([
        new \DB\FakeConnection(2006, 'MySQL server has gone away', false),
        new \DB\FakeConnection(2006, 'MySQL server has gone away', false),
    ]);
    $threw = false;
    try {
        $db->query('SELECT * FROM x');
    } catch (\Exception $e) {
        $threw = true;
        assertSame(true, str_contains($e->getMessage(), 'Error No: 2006'), 'errno 2006 twice: exception message names the errno', $failures);
    }
    assertSame(true, $threw, 'errno 2006 twice: query() throws instead of retrying forever', $failures);
    assertSame(2, $db->connectCount, 'errno 2006 twice: connect() called exactly twice, never a third reconnect', $failures);

    // 5. Non-recoverable errno (e.g. 1146, "table doesn't exist"): must
    //    throw immediately, no reconnect attempted at all.
    $db = new \DB\TestableMySQLi([
        new \DB\FakeConnection(1146, "Table 'x' doesn't exist", false),
    ]);
    $threw = false;
    try {
        $db->query('SELECT * FROM x');
    } catch (\Exception $e) {
        $threw = true;
        assertSame(true, str_contains($e->getMessage(), 'Error No: 1146'), 'non-recoverable errno: exception message names the errno', $failures);
    }
    assertSame(true, $threw, 'non-recoverable errno: query() throws immediately', $failures);
    assertSame(1, $db->connectCount, 'non-recoverable errno: connect() called exactly once, no reconnect attempted', $failures);

    if ($failures) {
        fwrite(STDERR, count($failures) . " failure(s):\n");
        foreach ($failures as $failure) {
            fwrite(STDERR, "- {$failure}\n");
        }
        exit(1);
    }

    echo "PASS: DB\\MySQLi reconnect-on-dropped-connection (MC-0011, 5 scenarios)\n";
    exit(0);
}
