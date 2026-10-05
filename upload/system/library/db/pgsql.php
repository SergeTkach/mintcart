<?php
namespace DB;
class PgSQL {
	protected $connection;
	protected $hostname;
	protected $username;
	protected $password;
	protected $database;
	protected $port;

	/**
	 * MintCart MC-0020 (docs/fixes/registry.md) — the same reconnect-on-
	 * dropped-connection gap MC-0011 (DB\MySQLi) and MC-0019 (DB\PDO) already
	 * fixed for the other two DB adaptors. pg_query() returns false both for
	 * a genuine SQL error and for a dropped connection; unlike mysqli/PDO
	 * there is no errno to inspect on the connection, so
	 * pg_connection_status() === PGSQL_CONNECTION_BAD after a failed query
	 * is the only reliable way pgsql exposes to tell the two apart. Only
	 * that case triggers the reconnect-and-retry path; any other query
	 * failure (bad SQL, missing table, permission, ...) still throws
	 * immediately, unretried.
	 */
	public function __construct($hostname, $username, $password, $database, $port = '') {
		$this->hostname = $hostname;
		$this->username = $username;
		$this->password = $password;
		$this->database = $database;
		$this->port = $port ?: '5432';

		$this->connect();
	}

	/**
	 * Establishes (or re-establishes) $this->connection. Extracted
	 * verbatim from the constructor (no behavior change) so query() can
	 * call it again after a dropped connection. protected rather than
	 * private so a test can override it with a fake connection — see
	 * tests/mintcart/fixes/mc-0020-pgsql-reconnect-test.php, which has no
	 * live PostgreSQL server to connect to in this environment.
	 */
	protected function connect() {
		try {
			$pg = @pg_connect('host=' . $this->hostname . ' port=' . $this->port . ' user=' . $this->username . ' password=' . $this->password . ' dbname=' . $this->database . ' options=\'--client_encoding=UTF8\' ');
		} catch (\Exception $e) {
			throw new \Exception('Error: Could not make a database link using ' . $this->username . '@' . $this->hostname);
		}

		if ($pg) {
			$this->connection = $pg;
			pg_query($this->connection, "SET CLIENT_ENCODING TO 'UTF8'");
		} else {
			throw new \Exception('Error: Could not make a database link using ' . $this->username . '@' . $this->hostname);
		}
	}

	public function query($sql): \stdClass {
		return $this->runQuery($sql, true);
	}

	/**
	 * @param bool $allowReconnect Whether a PGSQL_CONNECTION_BAD failure may
	 *        still trigger a reconnect-and-retry. Set to false on the retry
	 *        attempt itself so a connection that drops twice in a row (or a
	 *        server that's genuinely down) still throws, matching pre-fix
	 *        behavior, instead of reconnecting forever.
	 */
	private function runQuery($sql, $allowReconnect): \stdClass {
		$resource = pg_query($this->connection, $sql);

		if ($resource === false) {
			if ($allowReconnect && pg_connection_status($this->connection) === PGSQL_CONNECTION_BAD) {
				$this->connect();

				return $this->runQuery($sql, false);
			}

			// Pre-fix this called pg_result_error($resource), but $resource
			// is false here, not a PgSql\Result — that throws a TypeError
			// under PHP 8 instead of the intended \Exception. pg_last_error()
			// takes the connection, not the (nonexistent) result, and is
			// the correct call for a failed pg_query().
			throw new \Exception('Error: ' . pg_last_error($this->connection) . '<br/>' . $sql);
		}

		$data = [];

		while ($result = pg_fetch_assoc($resource)) {
			$data[] = $result;
		}

		pg_free_result($resource);

		$query = new \stdClass();
		$query->row = $data[0] ?? [];
		$query->rows = $data;
		$query->num_rows = count($data);

		return $query;
	}

	public function escape($value) {
		return pg_escape_string($this->connection, $value);
	}

	public function countAffected() {
		return pg_affected_rows($this->connection);
	}

	public function isConnected() {
		if (pg_connection_status($this->connection) == PGSQL_CONNECTION_OK) {
			return true;
		} else {
			return false;
		}
	}

	public function getLastId() {
		$query = $this->query("SELECT LASTVAL() AS `id`");

		return $query->row['id'];
	}

	public function __destruct() {
		if ($this->connection) {
			pg_close($this->connection);

			$this->connection = '';
		}
	}
}
