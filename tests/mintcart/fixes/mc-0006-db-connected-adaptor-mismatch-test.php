<?php
/**
 * Standalone regression test for MC-0006: `DB::connected()` called
 * `$this->adaptor->connected()`, but every real adaptor (`DB\MySQLi`,
 * `DB\PDO`, `DB\PgSQL`) implements `isConnected()`, not `connected()`.
 * Before the fix, calling `DB::connected()` against a real adaptor threw a
 * fatal "call to undefined method" error instead of returning a bool.
 *
 * No MySQL server available in this environment, so this exercises the
 * bug/fix purely through PHP's own method-dispatch behavior: a fake
 * `DB\FakeAdaptor` that duck-types the real adaptors (implements
 * `isConnected()` only, exactly like `DB\MySQLi`/`DB\PDO`/`DB\PgSQL` do) is
 * installed into a real `DB` instance via reflection (bypassing the
 * constructor's real connection attempt), then `DB::connected()` is called
 * for real.
 *
 * Run: php tests/mintcart/fixes/mc-0006-db-connected-adaptor-mismatch-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

namespace DB {
    final class FakeAdaptor
    {
        public function __construct(private bool $isConnected)
        {
        }

        // Deliberately named isConnected(), matching MySQLi/PDO/PgSQL —
        // never connected(), which is the bug this test guards against.
        public function isConnected(): bool
        {
            return $this->isConnected;
        }
    }
}

namespace {
    require __DIR__ . '/../../../upload/system/library/db.php';

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

    function db_with_fake_adaptor(\DB\FakeAdaptor $adaptor): \DB
    {
        // DB's constructor tries a real 'DB\\<adaptor>' connection, which
        // this test can't provide (no MySQL server here) — build an empty
        // DB instance without running the constructor, then inject the
        // fake adaptor into its private $adaptor property.
        $reflectionClass = new \ReflectionClass(\DB::class);
        $db = $reflectionClass->newInstanceWithoutConstructor();

        $adaptorProperty = $reflectionClass->getProperty('adaptor');
        $adaptorProperty->setAccessible(true);
        $adaptorProperty->setValue($db, $adaptor);

        return $db;
    }

    // Before the MC-0006 fix, this call fatals with "Call to undefined
    // method DB\FakeAdaptor::connected()" — no exception is thrown (a
    // fatal \Error), so this also proves the call site now resolves at all.
    $connectedDb = db_with_fake_adaptor(new \DB\FakeAdaptor(true));
    check($connectedDb->connected() === true, 'DB::connected() must return true when the adaptor reports isConnected() === true');

    $disconnectedDb = db_with_fake_adaptor(new \DB\FakeAdaptor(false));
    check($disconnectedDb->connected() === false, 'DB::connected() must return false when the adaptor reports isConnected() === false');

    // Guard against the bug's exact shape reappearing: DB\FakeAdaptor above
    // intentionally has no connected() method at all, so if DB::connected()
    // ever again calls $this->adaptor->connected() instead of
    // isConnected(), the two checks above would fatal before reaching here.
    echo "PASS: DB::connected() dispatches to the adaptor's isConnected(), matching MySQLi/PDO/PgSQL\n";
}
