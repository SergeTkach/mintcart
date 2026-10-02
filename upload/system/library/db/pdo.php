<?php
namespace DB;
class PDO {
	protected $connection = null;
	protected $statement = null;

	protected $hostname;
	protected $username;
	protected $password;
	protected $database;
	protected $port;

	/**
	 * MintCart MC-0019 (docs/fixes/registry.md) — same recoverable-errno
	 * set as MC-0011's DB\MySQLi fix: driver error code 2006 ("MySQL
	 * server has gone away") and 2013 ("Lost connection to MySQL server
	 * during query") both mean the underlying TCP connection was dropped
	 * (idle wait_timeout, server restart, ...), not that the query itself
	 * is bad — the same query would succeed a moment later on a fresh
	 * connection. Any other driver error code is a real query problem and
	 * still behaves exactly as before this fix (unretried).
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
	 * private — like DB\MySQLi::connect() (MC-0011) — so a test can
	 * override it with a fake connection instead of a real \PDO; see
	 * tests/mintcart/fixes/mc-0019-pdo-reconnect-test.php, which has no
	 * live MySQL server to connect to in this environment. The class
	 * itself was `final` before this fix — dropped so the test subclass
	 * is possible, same reasoning as MC-0011's visibility change; nothing
	 * in this codebase subclassed DB\PDO before or depends on it staying
	 * final.
	 */
	protected function connect() {
		try {
			$this->connection = new \PDO("mysql:host=" . $this->hostname . ";port=" . $this->port . ";dbname=" . $this->database, $this->username, $this->password, array(\PDO::ATTR_PERSISTENT => true));
		} catch (\PDOException $e) {
			throw new \Exception('Failed to connect to database. Reason: \'' . $e->getMessage() . '\'');
		}

		$this->connection->exec("SET NAMES 'utf8'");
		$this->connection->exec("SET CHARACTER SET utf8");
		$this->connection->exec("SET CHARACTER_SET_CONNECTION=utf8");
		$this->connection->exec("SET SQL_MODE = ''");
	}

	public function prepare($sql) {
		$this->statement = $this->connection->prepare($sql);
	}

	public function bindParam($parameter, $variable, $data_type = \PDO::PARAM_STR, $length = 0) {
		if ($length) {
			$this->statement->bindParam($parameter, $variable, $data_type, $length);
		} else {
			$this->statement->bindParam($parameter, $variable, $data_type);
		}
	}

	public function execute() {
		try {
			if ($this->statement && $this->statement->execute()) {
				$data = array();

				while ($row = $this->statement->fetch(\PDO::FETCH_ASSOC)) {
					$data[] = $row;
				}

				$result = new \stdClass();
				$result->row = (isset($data[0])) ? $data[0] : array();
				$result->rows = $data;
				$result->num_rows = $this->statement->rowCount();
			}
		} catch (\PDOException $e) {
			throw new \Exception('Error: ' . $e->getMessage() . ' Error Code : ' . $e->getCode());
		}
	}

	public function query($sql, $params = array()) {
		return $this->runQuery($sql, $params, true);
	}

	/**
	 * @param bool $allowReconnect Whether a RECOVERABLE_ERRNOS failure may
	 *        still trigger a reconnect-and-retry. Set to false on the
	 *        retry attempt itself so a connection that drops twice in a
	 *        row behaves exactly as before this fix instead of
	 *        reconnecting forever: a PDOException still throws, and a
	 *        silent (ERRMODE_SILENT) failure still falls through to the
	 *        pre-existing empty-result fallback below.
	 */
	private function runQuery($sql, $params, $allowReconnect) {
		$this->statement = $this->connection->prepare($sql);

		$result = false;

		try {
			if ($this->statement && $this->statement->execute($params)) {
				$data = array();

				while ($row = $this->statement->fetch(\PDO::FETCH_ASSOC)) {
					$data[] = $row;
				}

				$result = new \stdClass();
				$result->row = (isset($data[0]) ? $data[0] : array());
				$result->rows = $data;
				$result->num_rows = $this->statement->rowCount();
			} elseif ($this->statement && $allowReconnect && $this->isRecoverable($this->statement->errorInfo())) {
				// PDO's default error mode (ERRMODE_SILENT) means a
				// failed execute() returns false instead of throwing the
				// PDOException the catch block below handles — but
				// errorInfo() still reports the real driver error code
				// regardless of error mode, so this is the branch a
				// dropped connection actually takes under the default
				// configuration this class has always used (no
				// PDO::ATTR_ERRMODE set). See MC-0019.
				$this->connect();

				return $this->runQuery($sql, $params, false);
			}
		} catch (\PDOException $e) {
			if ($allowReconnect && $this->isRecoverable($e->errorInfo ?? [])) {
				$this->connect();

				return $this->runQuery($sql, $params, false);
			}

			throw new \Exception('Error: ' . $e->getMessage() . ' Error Code : ' . $e->getCode() . ' <br />' . $sql);
		}

		if ($result) {
			return $result;
		} else {
			$result = new \stdClass();
			$result->row = array();
			$result->rows = array();
			$result->num_rows = 0;
			return $result;
		}
	}

	/**
	 * @param array $errorInfo PDO's [SQLSTATE, driver error code, driver
	 *        error message] triple, from PDOStatement::errorInfo() or a
	 *        PDOException's errorInfo property.
	 */
	private function isRecoverable($errorInfo) {
		$errno = isset($errorInfo[1]) ? (int) $errorInfo[1] : 0;

		return in_array($errno, self::RECOVERABLE_ERRNOS, true);
	}

	public function escape($value) {
		return str_replace(array("\\", "\0", "\n", "\r", "\x1a", "'", '"'), array("\\\\", "\\0", "\\n", "\\r", "\Z", "\'", '\"'), $value);
	}

	public function countAffected() {
		if ($this->statement) {
			return $this->statement->rowCount();
		} else {
			return 0;
		}
	}

	public function getLastId() {
		return $this->connection->lastInsertId();
	}
	
	public function isConnected() {
		if ($this->connection) {
			return true;
		} else {
			return false;
		}
	}
	
	public function __destruct() {
		$this->connection = null;
	}
}
