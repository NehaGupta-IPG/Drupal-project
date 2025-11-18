<?php

namespace Drupal\sqlite3\Driver\Database\sqlite3;

use Drupal\Component\Utility\FilterArray;
use Drupal\Core\Database\Connection as BaseConnection;
use Drupal\Core\Database\DatabaseNotFoundException;
use Drupal\Core\Database\StatementInterface;
use Drupal\Core\Database\Transaction\TransactionManagerInterface;
use Drupal\sqlite\Driver\Database\sqlite\Connection as BaseSqliteConnection;

/**
 * SQLite implementation of \Drupal\Core\Database\Connection.
 */
class Connection extends BaseSqliteConnection {

  /**
   * Constructs a \Drupal\sqlite3\Driver\Database\sqlite3\Connection object.
   */
  public function __construct(\SQLite3 $connection, array $connection_options) {
    BaseConnection::__construct($connection, $connection_options);

    // Empty prefix means query the main database -- no need to attach anything.
    $prefix = $this->connectionOptions['prefix'] ?? '';
    if ($prefix !== '') {
      $this->attachDatabase($prefix);
      // Add a ., so queries become prefix.table, which is proper syntax for
      // querying an attached database.
      $prefix .= '.';
    }

    // Regenerate the prefix.
    $this->setPrefix($prefix);
  }

  /**
   * {@inheritdoc}
   */
  public static function open(array &$connection_options = []) {
    try {
      $connection = new \SQLite3($connection_options['database']);
      $connection->enableExceptions(TRUE);
    }
    catch (\SQLite3Exception $e) {
      if ($e->getCode() == static::DATABASE_NOT_FOUND) {
        throw new DatabaseNotFoundException($e->getMessage(), $e->getCode(), $e);
      }
      // SQLite doesn't have a distinct error code for access denied, so don't
      // deal with that case.
      throw $e;
    }

    // Create functions needed by SQLite.
    $connection->createFunction('if', [__CLASS__, 'sqlFunctionIf']);
    $connection->createFunction('greatest', [__CLASS__, 'sqlFunctionGreatest']);
    $connection->createFunction('least', [__CLASS__, 'sqlFunctionLeast']);
    $connection->createFunction('pow', 'pow', 2);
    $connection->createFunction('exp', 'exp', 1);
    $connection->createFunction('length', 'strlen', 1);
    $connection->createFunction('md5', 'md5', 1);
    $connection->createFunction('concat', [__CLASS__, 'sqlFunctionConcat']);
    $connection->createFunction('concat_ws', [__CLASS__, 'sqlFunctionConcatWs']);
    $connection->createFunction('substring', [__CLASS__, 'sqlFunctionSubstring'], 3);
    $connection->createFunction('substring_index', [__CLASS__, 'sqlFunctionSubstringIndex'], 3);
    $connection->createFunction('rand', [__CLASS__, 'sqlFunctionRand']);
    $connection->createFunction('regexp', [__CLASS__, 'sqlFunctionRegexp']);

    // SQLite does not support the LIKE BINARY operator, so we overload the
    // non-standard GLOB operator for case-sensitive matching. Another option
    // would have been to override another non-standard operator, MATCH, but
    // that does not support the NOT keyword prefix.
    $connection->createFunction('glob', [__CLASS__, 'sqlFunctionLikeBinary']);

    // Create a user-space case-insensitive collation with UTF-8 support.
    $connection->createCollation('NOCASE_UTF8', ['Drupal\Component\Utility\Unicode', 'strcasecmp']);

    // Set SQLite init_commands if not already defined. Enable the Write-Ahead
    // Logging (WAL) for SQLite. See https://www.drupal.org/node/2348137 and
    // https://www.sqlite.org/wal.html.
    $connection_options += [
      'init_commands' => [],
    ];
    $connection_options['init_commands'] += [
      'wal' => "PRAGMA journal_mode=WAL",
    ];

    // Execute sqlite init_commands.
    if (isset($connection_options['init_commands'])) {
      $connection->exec(implode('; ', $connection_options['init_commands']));
    }

    return $connection;
  }

  /**
   * Destructor for the SQLite connection.
   *
   * We prune empty databases on destruct, but only if tables have been
   * dropped. This is especially needed when running the test suite, which
   * creates and destroy databases several times in a row.
   */
  public function __destruct() {
    if ($this->tableDropped && !empty($this->attachedDatabases)) {
      foreach ($this->attachedDatabases as $prefix) {
        // Check if the database is now empty, ignore the internal SQLite
        // tables.
        try {
          $count = $this->query('SELECT COUNT(*) FROM ' . $prefix . '.sqlite_master WHERE type = :type AND name NOT LIKE :pattern', [':type' => 'table', ':pattern' => 'sqlite_%'])->fetchField();

          // We can prune the database file if it doesn't have any tables.
          if ($count == 0 && $this->connectionOptions['database'] != ':memory:' && file_exists($this->connectionOptions['database'] . '-' . $prefix)) {
            // Detach the database.
            $this->query('DETACH DATABASE :schema', [':schema' => $prefix]);
            // Destroy the database file.
            unlink($this->connectionOptions['database'] . '-' . $prefix);
          }
        }
        catch (\Exception) {
          // Ignore the exception and continue. There is nothing we can do here
          // to report the error or fail safe.
        }
      }
    }
    parent::__destruct();
  }

  /**
   * {@inheritdoc}
   */
  public function driver() {
    return 'sqlite3';
  }

  /**
   * {@inheritdoc}
   */
  public function version() {
    return $this->connection->version()['versionString'];
  }

  /**
   * {@inheritdoc}
   */
  public function clientVersion() {
    return $this->version();
  }

  /**
   * {@inheritdoc}
   */
  public function quote($string, $parameter_type = \PDO::PARAM_STR) {
    return "'" . $this->connection->escapeString((string) $string) . "'";
  }

  /**
   * {@inheritdoc}
   */
  public function prepareStatement(string $query, array $options, bool $allow_row_count = FALSE): StatementInterface {
global $xxx;
    try {
      $query = $this->preprocessStatement($query, $options);
#      $query = str_replace("\n", " ", $query);
#      $query = str_replace(" ,", ",", $query);
#      $query = str_replace(
#        '( "collection" VARCHAR(255) NOT NULL DEFAULT,  "name" VARCHAR(255) NOT NULL DEFAULT,  "data" BLOB NULL DEFAULT NULL,   PRIMARY KEY ("collection", "name") )',
#        '( "collection" VARCHAR(255) NOT NULL DEFAULT,  "name" VARCHAR(255) NOT NULL )',
#         $query
#      );
#if (!isset($xxx)) $xxx=0; else $xxx++; if ($xxx<40) dump([__METHOD__, $query]); #if ($xxx===40) die();
if (isset($xxx) && $xxx) dump([__METHOD__, $query]);
      $statement = new Statement($this, $this->connection, $query, $options['pdo'] ?? [], $allow_row_count);
    }
    catch (\Exception $e) {
      $this->exceptionHandler()->handleStatementException($e, $query, $options);
    }
    return $statement;
  }

  /**
   * {@inheritdoc}
   */
  public function lastInsertId(?string $name = NULL): string {
    return (string) $this->connection->lastInsertRowID();
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
  protected function driverTransactionManager(): TransactionManagerInterface {
    return new TransactionManager($this);
  }

}
