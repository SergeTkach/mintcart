<?php
namespace DB;
class MySQLi {
	protected $connection;
	protected $hostname;
	protected $username;
	protected $password;
	protected $database;
	protected $port;

	/**
	 * MintCart MC-0011 (docs/fixes/registry.md) — errno 2006 ("MySQL
	 * server has gone away") and 2013 ("Lost connection to MySQL server
	 * during query") mean the underlying TCP connection was dropped
	 * (idle wait_timeout, server restart, ...), not that $sql itself is
	 * bad. query() used to throw immediately on any error, including
	 * these two, killing the whole request even though a fresh
	 * connection would let the exact same query succeed a moment later —
	 * the "database connection / reconnect issues" Known Fix Research
	 * Queue item (MINTCART_FUNCTIONAL_SCOPE.md §18). Any other errno
	 * (bad SQL, missing table, permission, ...) is a real query problem
	 * and still throws immediately, unretried.
	 */
	private const RECOVERABLE_ERRNOS = [2006, 2013];

	public function __construct($hostname, $username, $password, $database, $port = '3306') {
		$this->hostname = $hostname;
		$this->username = $username;
		$this->password = $password;
		$this->database = $database;
		$this->port = $port;

		$this->connect();
	}

	/**
	 * Establishes (or re-establishes) $this->connection. Extracted
	 * verbatim from the constructor (no behavior change) so query() can
	 * call it again after a dropped connection. protected rather than
	 * private so a test can override it with a fake connection instead
	 * of a real \MySQLi — see
	 * tests/mintcart/fixes/mc-0011-mysqli-reconnect-test.php, which has
	 * no live MySQL server to connect to in this environment.
	 */
	protected function connect() {
		try {
			$mysqli = @new \MySQLi($this->hostname, $this->username, $this->password, $this->database, $this->port);
		} catch (\mysqli_sql_exception $e) {
			throw new \Exception('Error: Could not make a database link using ' . $this->username . '@' . $this->hostname . '!');
		}

		if (!$mysqli->connect_errno) {
			mysqli_report(MYSQLI_REPORT_ERROR);
			$this->connection = $mysqli;
//			$this->connection->report_mode = MYSQLI_REPORT_ERROR;
			$this->connection->set_charset('utf8');
			$this->connection->query("SET SESSION sql_mode = 'NO_ZERO_IN_DATE,NO_ENGINE_SUBSTITUTION'");
		} else {
			throw new \Exception('Error: Could not make a database link using ' . $this->username . '@' . $this->hostname . '!');
		}
	}

	public function query($sql) {
		return $this->runQuery($sql, true);
	}

	/**
	 * @param bool $allowReconnect Whether a RECOVERABLE_ERRNOS failure may
	 *        still trigger a reconnect-and-retry. Set to false on the
	 *        retry attempt itself so a connection that drops twice in a
	 *        row throws (matching pre-MC-0011 behavior) instead of
	 *        reconnecting forever.
	 */
	private function runQuery($sql, $allowReconnect) {
		$query = $this->connection->query($sql);

		if (!$this->connection->errno) {
			if ($query instanceof \mysqli_result) {
				$data = [];

				while ($row = $query->fetch_assoc()) {
					$data[] = $row;
				}

				$result = new \stdClass();
				$result->num_rows = $query->num_rows;
				$result->row = isset($data[0]) ? $data[0] : [];
				$result->rows = $data;

				$query->close();

				unset($data);

				return $result;
			} else {
				return true;
			}
		} elseif ($allowReconnect && in_array($this->connection->errno, self::RECOVERABLE_ERRNOS, true)) {
			$this->connect();

			return $this->runQuery($sql, false);
		} else {
			throw new \Exception('Error: ' . $this->connection->error  . '<br />Error No: ' . $this->connection->errno . '<br />' . $sql);
		}
	}

	public function escape($value) {
		return $this->connection->real_escape_string(($value===null) ? '' : $value);
	}

	public function countAffected() {
		return $this->connection->affected_rows;
	}

	public function getLastId() {
		return $this->connection->insert_id;
	}

	public function isConnected() {
		if ($this->connection) {
			return $this->connection->ping();
		} else {
			return false;
		}
	}

	/**
	 * __destruct
	 *
	 * Closes the DB connection when this object is destroyed.
	 *
	 */
	public function __destruct() {
		if ($this->connection) {
			$this->connection->close();

			$this->connection = '';
		}
	}
}
