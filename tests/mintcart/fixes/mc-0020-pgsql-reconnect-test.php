<?php
/**
 * Standalone regression test for MC-0020: DB\PgSQL::query() used to throw
 * immediately on ANY pg_query() failure — including a dropped TCP
 * connection — and, on top of that, built its error message by calling
 * pg_result_error($resource) where $resource is the boolean `false`
 * pg_query() just returned, which is a TypeError under PHP 8
 * (pg_result_error() requires a PgSql\Result, not a bool).
 *
 * After the fix: a failed query whose connection is now
 * PGSQL_CONNECTION_BAD (the only way pgsql exposes "the TCP connection
 * dropped" — there is no errno to inspect, unlike mysqli/PDO) triggers one
 * reconnect + one retry of the same query; any other failure (bad SQL,
 * missing table, permission, ...) still throws immediately, using
 * pg_last_error($this->connection) for the message instead of the broken
 * pg_result_error($resource) call.
 *
 * No PostgreSQL server available in this environment (and even if there
 * were, deliberately forcing a mid-query disconnect from a CLI test is not
 * practical), so this never touches a real pg_* call. DB\PgSQL lives in
 * namespace DB and calls pg_connect()/pg_query()/pg_connection_status()/
 * pg_fetch_assoc()/pg_free_result()/pg_last_error() unqualified — PHP
 * resolves an unqualified function call against the current namespace
 * first and only falls back to the global function if no such
 * DB\pg_connect()/etc. exists. Defining fake DB\pg_*() functions below
 * (before any DB\PgSQL method actually runs) therefore intercepts every
 * call DB\PgSQL makes without needing a real pgsql extension/server or any
 * subclassing — the same "duck-typed dependency, injected instead of a
 * real network/DB object" spirit as
 * tests/mintcart/fixes/mc-0011-mysqli-reconnect-test.php, adapted to
 * pgsql's procedural (not object-method) API shape.
 *
 * Run: php tests/mintcart/fixes/mc-0020-pgsql-reconnect-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

namespace DB {
    require __DIR__ . '/../../../upload/system/library/db/pgsql.php';

    /**
     * Shared mutable state the fake pg_*() functions below read/write, and
     * that the test script inspects after exercising DB\PgSQL. Reset
     * between scenarios.
     */
    final class PgTestState
    {
        public static int $connectCalls = 0;
        public static int $businessQueryCalls = 0;
        /** @var array<int, array{ok: bool, rows?: array, statusAfter?: int, error?: string}> */
        public static array $script = [];
        public static bool $connectShouldFail = false;
        public static int $connectionStatus = \PGSQL_CONNECTION_OK;
        public static string $lastError = '';

        public static function reset(): void
        {
            self::$connectCalls = 0;
            self::$businessQueryCalls = 0;
            self::$script = [];
            self::$connectShouldFail = false;
            self::$connectionStatus = \PGSQL_CONNECTION_OK;
            self::$lastError = '';
        }
    }

    /**
     * Stands in for a pg_query() success result. Only implements the one
     * slice DB\PgSQL::runQuery() actually uses (an iterable row source).
     */
    final class FakePgResult
    {
        private array $rows;

        public function __construct(array $rows)
        {
            $this->rows = $rows;
        }

        public function fetchNext(): array|false
        {
            if (empty($this->rows)) {
                return false;
            }

            return array_shift($this->rows);
        }
    }

    function pg_connect($connectionString)
    {
        PgTestState::$connectCalls++;

        return PgTestState::$connectShouldFail ? false : 'fake-connection-' . PgTestState::$connectCalls;
    }

    function pg_query($connection, $sql)
    {
        // connect()'s own housekeeping "SET CLIENT_ENCODING" query is not
        // part of what this test scripts/counts — only the business query
        // DB\PgSQL::query($sql) was actually called with.
        if (strpos($sql, 'SET CLIENT_ENCODING') === 0) {
            return new FakePgResult([]);
        }

        PgTestState::$businessQueryCalls++;

        $step = array_shift(PgTestState::$script);

        if ($step === null) {
            throw new \RuntimeException('Unexpected extra pg_query() call in test — script exhausted');
        }

        if ($step['ok']) {
            return new FakePgResult($step['rows']);
        }

        PgTestState::$connectionStatus = $step['statusAfter'];
        PgTestState::$lastError = $step['error'];

        return false;
    }

    function pg_connection_status($connection)
    {
        return PgTestState::$connectionStatus;
    }

    function pg_fetch_assoc($resource)
    {
        return $resource->fetchNext();
    }

    function pg_free_result($resource)
    {
        return true;
    }

    function pg_last_error($connection)
    {
        return PgTestState::$lastError;
    }

    function pg_close($connection)
    {
    }

    function pg_escape_string($connection, $value)
    {
        return $value;
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

    function assertTrue(bool $condition, string $message, array &$failures): void
    {
        if (!$condition) {
            $failures[] = $message;
        }
    }

    // 1. No error at all: query() returns the rows, exactly one
    //    pg_connect() call (the constructor's), exactly one business
    //    pg_query() call, no reconnect attempted.
    \DB\PgTestState::reset();
    \DB\PgTestState::$script = [
        ['ok' => true, 'rows' => [['id' => '1', 'name' => 'a'], ['id' => '2', 'name' => 'b']]],
    ];
    $db = new \DB\PgSQL('fake-host', 'fake-user', 'fake-pass', 'fake-db');
    $result = $db->query('SELECT * FROM x');
    assertSame(1, \DB\PgTestState::$connectCalls, 'no error: pg_connect() called exactly once (constructor only)', $failures);
    assertSame(1, \DB\PgTestState::$businessQueryCalls, 'no error: exactly one business pg_query() call, no retry', $failures);
    assertSame(2, $result->num_rows, 'no error: num_rows matches the scripted row count', $failures);
    assertSame('a', $result->row['name'], 'no error: ->row is the first scripted row', $failures);

    // 2. Dropped connection (PGSQL_CONNECTION_BAD) on the first attempt,
    //    success on the retry after reconnecting: query() must return the
    //    retry's result, and pg_connect() must have run exactly twice
    //    (constructor + one reconnect).
    \DB\PgTestState::reset();
    \DB\PgTestState::$script = [
        ['ok' => false, 'statusAfter' => \PGSQL_CONNECTION_BAD, 'error' => 'server closed the connection unexpectedly'],
        ['ok' => true, 'rows' => [['id' => '5']]],
    ];
    $db = new \DB\PgSQL('fake-host', 'fake-user', 'fake-pass', 'fake-db');
    $result = $db->query('SELECT * FROM x');
    assertSame(2, \DB\PgTestState::$connectCalls, 'dropped then success: pg_connect() called exactly twice (initial + one reconnect)', $failures);
    assertSame(2, \DB\PgTestState::$businessQueryCalls, 'dropped then success: two business pg_query() calls (original + retry)', $failures);
    assertSame('5', $result->row['id'], 'dropped then success: query() returns the retry\'s result', $failures);

    // 3. Dropped connection on BOTH the original attempt and the retry:
    //    must throw (not reconnect a second time / loop forever), and
    //    pg_connect() must have run exactly twice, never a third time.
    \DB\PgTestState::reset();
    \DB\PgTestState::$script = [
        ['ok' => false, 'statusAfter' => \PGSQL_CONNECTION_BAD, 'error' => 'server closed the connection unexpectedly'],
        ['ok' => false, 'statusAfter' => \PGSQL_CONNECTION_BAD, 'error' => 'server closed the connection unexpectedly'],
    ];
    $db = new \DB\PgSQL('fake-host', 'fake-user', 'fake-pass', 'fake-db');
    $threw = false;
    try {
        $db->query('SELECT * FROM x');
    } catch (\Exception $e) {
        $threw = true;
        assertTrue(
            strpos($e->getMessage(), 'server closed the connection unexpectedly') !== false,
            'dropped twice: exception message carries pg_last_error() text',
            $failures
        );
    }
    assertTrue($threw, 'dropped twice: query() throws instead of reconnecting forever', $failures);
    assertSame(2, \DB\PgTestState::$connectCalls, 'dropped twice: pg_connect() called exactly twice (initial + one reconnect, no third attempt)', $failures);
    assertSame(2, \DB\PgTestState::$businessQueryCalls, 'dropped twice: exactly two business pg_query() calls, no further retry', $failures);

    // 4. A non-recoverable failure (connection status is OK — a genuine SQL
    //    error, not a dropped connection): must throw immediately, with no
    //    reconnect attempted, and the message must come from
    //    pg_last_error() — confirming the pre-fix pg_result_error($resource)
    //    TypeError-on-PHP-8 call path is gone.
    \DB\PgTestState::reset();
    \DB\PgTestState::$script = [
        ['ok' => false, 'statusAfter' => \PGSQL_CONNECTION_OK, 'error' => 'ERROR: syntax error at or near "BAD"'],
    ];
    $db = new \DB\PgSQL('fake-host', 'fake-user', 'fake-pass', 'fake-db');
    $threw = false;
    try {
        $db->query('SELECT BAD SQL');
    } catch (\Exception $e) {
        $threw = true;
        assertTrue(
            strpos($e->getMessage(), 'syntax error at or near "BAD"') !== false,
            'non-recoverable error: exception message carries the real pg_last_error() text',
            $failures
        );
    }
    assertTrue($threw, 'non-recoverable error: query() throws', $failures);
    assertSame(1, \DB\PgTestState::$connectCalls, 'non-recoverable error: pg_connect() called exactly once, no reconnect attempted', $failures);
    assertSame(1, \DB\PgTestState::$businessQueryCalls, 'non-recoverable error: exactly one business pg_query() call, no retry', $failures);

    // 5. pg_connect() itself fails at construction time: must throw the
    //    same "could not make a database link" \Exception as before the
    //    fix — connect() being extracted out of the constructor must not
    //    change this path's behavior.
    \DB\PgTestState::reset();
    \DB\PgTestState::$connectShouldFail = true;
    $threw = false;
    try {
        new \DB\PgSQL('fake-host', 'fake-user', 'fake-pass', 'fake-db');
    } catch (\Exception $e) {
        $threw = true;
        assertTrue(
            strpos($e->getMessage(), 'Could not make a database link') !== false,
            'connect failure: constructor throws the "could not make a database link" message',
            $failures
        );
    }
    assertTrue($threw, 'connect failure: constructor throws', $failures);

    if ($failures) {
        echo 'FAIL: ' . count($failures) . " assertion(s) failed:\n";
        foreach ($failures as $failure) {
            echo '  - ' . $failure . "\n";
        }
        exit(1);
    }

    echo "PASS: mc-0020-pgsql-reconnect-test.php (9 assertions across 5 scenarios)\n";
    exit(0);
}
