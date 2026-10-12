<?php

namespace Drupal\mysql\Driver\Database\mysql;

use Drupal\Core\Database\Connection as DatabaseConnection;
use Drupal\Core\Database\ConnectionParametersFactory;
use Drupal\Core\Database\ConnectionParametersInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Database\DatabaseAccessDeniedException;
use Drupal\Core\Database\DatabaseConnectionRefusedException;
use Drupal\Core\Database\DatabaseNotFoundException;
use Drupal\Core\Database\StatementWrapperIterator;
use Drupal\Core\Database\SupportsTemporaryTablesInterface;
use Drupal\Core\Database\Transaction\TransactionManagerInterface;
use Pdo\Mysql;

/**
 * @addtogroup database
 * @{
 */

/**
 * MySQL implementation of \Drupal\Core\Database\Connection.
 *
 * @phpstan-import-type UnprocessedConnectionInfoArray from \Drupal\Core\Database\Database
 */
class Connection extends DatabaseConnection implements SupportsTemporaryTablesInterface {

  /**
   * Error code for "Unknown database" error.
   */
  const DATABASE_NOT_FOUND = 1049;

  /**
   * Error code for "Access denied" error.
   */
  const ACCESS_DENIED = 1045;

  /**
   * Error code for "Connection refused".
   */
  const CONNECTION_REFUSED = 2002;

  /**
   * {@inheritdoc}
   */
  protected $statementWrapperClass = StatementWrapperIterator::class;

  /**
   * Stores the server version after it has been retrieved from the database.
   *
   * @var string
   *
   * @see \Drupal\mysql\Driver\Database\mysql\Connection::version
   */
  private $serverVersion;

  /**
   * The minimal possible value for the max_allowed_packet setting of MySQL.
   *
   * @link https://mariadb.com/kb/en/mariadb/server-system-variables/#max_allowed_packet
   * @link https://dev.mysql.com/doc/refman/8.0/en/server-system-variables.html#sysvar_max_allowed_packet
   *
   * @var int
   */
  const MIN_MAX_ALLOWED_PACKET = 1024;

  /**
   * {@inheritdoc}
   */
  protected $identifierQuotes = ['"', '"'];

  /**
   * {@inheritdoc}
   */
  public function __construct(Mysql $connection, ConnectionParameters $connection_options) {
    // If the SQL mode doesn't include 'ANSI_QUOTES' (explicitly or via a
    // combination mode), then MySQL doesn't interpret a double quote as an
    // identifier quote, in which case use the non-ANSI-standard backtick.
    //
    // @see https://dev.mysql.com/doc/refman/8.0/en/sql-mode.html#sqlmode_ansi_quotes
    $ansi_quotes_modes = ['ANSI_QUOTES', 'ANSI'];
    $is_ansi_quotes_mode = FALSE;
    if (isset($connection_options->init_commands['sql_mode'])) {
      foreach ($ansi_quotes_modes as $mode) {
        // None of the modes in $ansi_quotes_modes are substrings of other modes
        // that are not in $ansi_quotes_modes, so a simple stripos() does not
        // return false positives.
        if (stripos($connection_options->init_commands['sql_mode'], $mode) !== FALSE) {
          $is_ansi_quotes_mode = TRUE;
          break;
        }
      }
    }

    if ($this->identifierQuotes === ['"', '"'] && !$is_ansi_quotes_mode) {
      $this->identifierQuotes = ['`', '`'];
    }
    parent::__construct($connection, $connection_options);
  }

  /**
   * Opens a client connection.
   *
   * @param \Drupal\Core\Database\ConnectionParametersInterface $connectionParameters
   *   The database connection settings.
   *
   * @return \Pdo\Mysql
   *   A client connection object.
   */
  public static function openWithParameters(ConnectionParametersInterface $connectionParameters): Mysql {
    assert($connectionParameters instanceof ConnectionParameters);

    // The DSN should use either a socket or a host/port.
    if (isset($connectionParameters->unix_socket)) {
      $dsn = 'mysql:unix_socket=' . $connectionParameters->unix_socket;
    }
    else {
      // Default to TCP connection on port 3306.
      $dsn = 'mysql:host=' . $connectionParameters->host . ';port=' . $connectionParameters->port;
    }
    // Character set is added to dsn to ensure PDO uses the proper character
    // set when escaping. This has security implications. See
    // https://www.drupal.org/node/1201452 for further discussion.
    $dsn .= ';charset=utf8mb4;dbname=' . $connectionParameters->database;

    try {
      $mysql = new Mysql($dsn, $connectionParameters->username, $connectionParameters->password, $connectionParameters->pdo);
    }
    catch (\PDOException $e) {
      switch ($e->getCode()) {
        case static::CONNECTION_REFUSED:
          if (isset($connectionParameters->unix_socket)) {
            // Show message for socket connection via 'unix_socket' option.
            $message = 'Drupal is configured to connect to the database server via a socket, but the socket file could not be found.';
            $message .= ' This message normally means that there is no MySQL server running on the system or that you are using an incorrect Unix socket file name when trying to connect to the server.';
            throw new DatabaseConnectionRefusedException($e->getMessage() . ' [Tip: ' . $message . '] ', $e->getCode(), $e);
          }
          if (isset($connectionParameters->host) && in_array(strtolower($connectionParameters->host), ['', 'localhost'], TRUE)) {
            // Show message for socket connection via 'host' option.
            $message = 'Drupal was attempting to connect to the database server via a socket, but the socket file could not be found.';
            $message .= ' A Unix socket file is used if you do not specify a host name or if you specify the special host name localhost.';
            $message .= ' To connect via TCP/IP use an IP address (127.0.0.1 for IPv4) instead of "localhost".';
            $message .= ' This message normally means that there is no MySQL server running on the system or that you are using an incorrect Unix socket file name when trying to connect to the server.';
            throw new DatabaseConnectionRefusedException($e->getMessage() . ' [Tip: ' . $message . '] ', $e->getCode(), $e);
          }
          // Show message for TCP/IP connection.
          $message = 'This message normally means that there is no MySQL server running on the system or that you are using an incorrect host name or port number when trying to connect to the server.';
          $message .= ' You should also check that the TCP/IP port you are using has not been blocked by a firewall or port blocking service.';
          throw new DatabaseConnectionRefusedException($e->getMessage() . ' [Tip: ' . $message . '] ', $e->getCode(), $e);

        case static::DATABASE_NOT_FOUND:
          throw new DatabaseNotFoundException($e->getMessage(), $e->getCode(), $e);

        case static::ACCESS_DENIED:
          throw new DatabaseAccessDeniedException($e->getMessage(), $e->getCode(), $e);

        default:
          throw $e;
      }
    }

    // Force MySQL to use the UTF-8 character set. Also set the collation, if a
    // certain one has been set; otherwise, MySQL defaults to
    // 'utf8mb4_general_ci' (MySQL 5) or 'utf8mb4_0900_ai_ci' (MySQL 8) for
    // utf8mb4.
    if (!empty($connectionParameters->collation)) {
      $mysql->exec('SET NAMES utf8mb4 COLLATE ' . $connectionParameters->collation);
    }
    else {
      $mysql->exec('SET NAMES utf8mb4');
    }

    // Execute initial commands.
    foreach ($connectionParameters->init_commands as $sql) {
      $mysql->exec($sql);
    }

    return $mysql;
  }

  /**
   * {@inheritdoc}
   *
   * @deprecated in drupal:12.1.0 and is removed from drupal:13.0.0. Use
   *   ::openWithParameters() instead.
   *
   * @see https://www.drupal.org/node/3626243
   */
  public static function open(#[\SensitiveParameter] array &$connection_options = []) {
    @trigger_error(__METHOD__ . '() is deprecated in drupal:12.1.0 and is removed from drupal:13.0.0. Use ::openWithParameters() instead. See https://www.drupal.org/node/3626243', E_USER_DEPRECATED);
    /** @var UnprocessedConnectionInfoArray|list<UnprocessedConnectionInfoArray> $connection_options */
    return static::openWithParameters(ConnectionParametersFactory::createFromOptionsArray($connection_options));
  }

  /**
   * {@inheritdoc}
   */
  public function queryRange($query, $from, $count, array $args = [], array $options = []) {
    return $this->query($query . ' LIMIT ' . (int) $from . ', ' . (int) $count, $args, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function queryTemporary($query, array $args = [], array $options = []) {
    $tablename = 'db_temporary_' . bin2hex(random_bytes(12));
    $this->query('CREATE TEMPORARY TABLE {' . $tablename . '} Engine=MEMORY ' . $query, $args, $options);
    return $tablename;
  }

  /**
   * {@inheritdoc}
   */
  public function driver() {
    return 'mysql';
  }

  /**
   * {@inheritdoc}
   */
  public function version() {
    if ($this->isMariaDb()) {
      return $this->getMariaDbVersionMatch();
    }

    return $this->getServerVersion();
  }

  /**
   * Determines whether the MySQL distribution is MariaDB or not.
   *
   * @return bool
   *   Returns TRUE if the distribution is MariaDB, or FALSE if not.
   */
  public function isMariaDb(): bool {
    return (bool) $this->getMariaDbVersionMatch();
  }

  /**
   * Gets the MariaDB portion of the server version.
   *
   * @return string
   *   The MariaDB portion of the server version if present, or NULL if not.
   */
  protected function getMariaDbVersionMatch(): ?string {
    // MariaDB may prefix its version string with '5.5.5-', which should be
    // ignored.
    // @see https://github.com/MariaDB/server/blob/f6633bf058802ad7da8196d01fd19d75c53f7274/include/mysql_com.h#L42.
    $regex = '/^(?:5\.5\.5-)?(\d+\.\d+\.\d+.*-mariadb.*)/i';

    preg_match($regex, $this->getServerVersion(), $matches);
    return (empty($matches[1])) ? NULL : $matches[1];
  }

  /**
   * Gets the server version.
   *
   * @return string
   *   The PDO server version.
   */
  protected function getServerVersion(): string {
    if (!$this->serverVersion) {
      $this->serverVersion = $this->query('SELECT VERSION()')->fetchField();
    }
    return $this->serverVersion;
  }

  /**
   * {@inheritdoc}
   */
  public function databaseType() {
    return 'mysql';
  }

  /**
   * Overrides \Drupal\Core\Database\Connection::createDatabase().
   *
   * @param string $database
   *   The name of the database to create.
   *
   * @throws \Drupal\Core\Database\DatabaseNotFoundException
   */
  public function createDatabase($database) {
    // Escape the database name.
    $database = Database::getConnection()->escapeDatabase($database);

    try {
      // Create the database and set it as active.
      $this->connection->exec("CREATE DATABASE $database");
      $this->connection->exec("USE $database");
    }
    catch (\Exception $e) {
      throw new DatabaseNotFoundException($e->getMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function mapConditionOperator($operator) {
    // We don't want to override any of the defaults.
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function exceptionHandler() {
    return new ExceptionHandler();
  }

  /**
   * {@inheritdoc}
   */
  public function insert($table, array $options = []) {
    return new Insert($this, $table, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function upsert($table, array $options = []) {
    return new Upsert($this, $table, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function schema() {
    if (empty($this->schema)) {
      $this->schema = new Schema($this);
    }
    return $this->schema;
  }

  /**
   * {@inheritdoc}
   */
  protected function driverTransactionManager(): TransactionManagerInterface {
    return new TransactionManager($this);
  }

}


/**
 * @} End of "addtogroup database".
 */
