<?php

namespace Drupal\sqlite\Driver\Database\sqlite;

use Drupal\Component\Utility\FilterArray;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Database\Connection as DatabaseConnection;
use Drupal\Core\Database\ConnectionParametersFactory;
use Drupal\Core\Database\ConnectionParametersInterface;
use Drupal\Core\Database\DatabaseNotFoundException;
use Drupal\Core\Database\ExceptionHandler;
use Drupal\Core\Database\StatementInterface;
use Drupal\Core\Database\SupportsTemporaryTablesInterface;
use Drupal\Core\Database\Statement\FetchAs;
use Drupal\Core\Database\Transaction\TransactionManagerInterface;

/**
 * SQLite implementation of \Drupal\Core\Database\Connection.
 *
 * @phpstan-import-type UnprocessedConnectionInfoArray from \Drupal\Core\Database\Database
 */
class Connection extends DatabaseConnection implements SupportsTemporaryTablesInterface {

  /**
   * Error code for "Unable to open database file" error.
   */
  const DATABASE_NOT_FOUND = 14;

  /**
   * A map of condition operators to SQLite operators.
   *
   * We don't want to override any of the defaults.
   *
   * @var string[][]
   */
  protected static $sqliteConditionOperatorMap = [
    'LIKE' => ['postfix' => " ESCAPE '\\'"],
    'NOT LIKE' => ['postfix' => " ESCAPE '\\'"],
    'LIKE BINARY' => ['postfix' => " ESCAPE '\\'", 'operator' => 'GLOB'],
    'NOT LIKE BINARY' => ['postfix' => " ESCAPE '\\'", 'operator' => 'NOT GLOB'],
  ];

  /**
   * All databases attached to the current database.
   *
   * This is used to allow prefixes to be safely handled without locking the
   * table.
   *
   * @var array
   */
  protected $attachedDatabases = [];

  /**
   * Whether or not a table has been dropped this request.
   *
   * The destructor will only try to get rid of unnecessary databases if there
   * is potential of them being empty.
   *
   * This variable is set to public because Schema needs to
   * access it. However, it should not be manually set.
   *
   * @var bool
   */
  public $tableDropped = FALSE;

  /**
   * {@inheritdoc}
   */
  protected $transactionalDDLSupport = TRUE;

  /**
   * {@inheritdoc}
   */
  protected $identifierQuotes = ['"', '"'];

  /**
   * Constructs a \Drupal\sqlite\Driver\Database\sqlite\Connection object.
   */
  public function __construct(SqliteConnection $connection, ConnectionParameters $connection_options) {
    parent::__construct($connection, $connection_options);

    // Empty prefix means query the main database -- no need to attach anything.
    $prefix = $this->connectionOptions->prefix;
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
   * Opens a client connection.
   *
   * @param \Drupal\Core\Database\ConnectionParametersInterface $connectionParameters
   *   The database connection settings.
   *
   * @return object
   *   A client connection object.
   */
  public static function openWithParameters(ConnectionParametersInterface $connectionParameters): SqliteConnection {
    // Defaults for the options parameter.
    $pdo_options = $connectionParameters->pdo + [
      \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
      // Convert numeric values to strings when fetching.
      \PDO::ATTR_STRINGIFY_FETCHES => TRUE,
    ];

    try {
      $sqlite = new SqliteConnection('sqlite:' . $connectionParameters->database, '', '', $pdo_options);
    }
    catch (\PDOException $e) {
      if ($e->getCode() == static::DATABASE_NOT_FOUND) {
        throw new DatabaseNotFoundException($e->getMessage(), $e->getCode(), $e);
      }
      // SQLite doesn't have a distinct error code for access denied, so don't
      // deal with that case.
      throw $e;
    }

    // Create functions needed by SQLite.
    $sqlite->createFunction('if', self::sqlFunctionIf(...));
    $sqlite->createFunction('greatest', self::sqlFunctionGreatest(...));
    $sqlite->createFunction('least', self::sqlFunctionLeast(...));
    $sqlite->createFunction('pow', pow(...), 2);
    $sqlite->createFunction('exp', exp(...), 1);
    $sqlite->createFunction('length', strlen(...), 1);
    $sqlite->createFunction('md5', md5(...), 1);
    $sqlite->createFunction('concat', self::sqlFunctionConcat(...));
    $sqlite->createFunction('concat_ws', self::sqlFunctionConcatWs(...));
    $sqlite->createFunction('substring', self::sqlFunctionSubstring(...), 3);
    $sqlite->createFunction('substring_index', self::sqlFunctionSubstringIndex(...), 3);
    $sqlite->createFunction('rand', self::sqlFunctionRand(...));
    $sqlite->createFunction('regexp', self::sqlFunctionRegexp(...));

    // SQLite does not support the LIKE BINARY operator, so we overload the
    // non-standard GLOB operator for case-sensitive matching. Another option
    // would have been to override another non-standard operator, MATCH, but
    // that does not support the NOT keyword prefix.
    $sqlite->createFunction('glob', self::sqlFunctionLikeBinary(...));

    // Create a user-space case-insensitive collation with UTF-8 support.
    $sqlite->createCollation('NOCASE_UTF8', Unicode::strcasecmp(...));

    // Execute init_commands.
    // Enable the Write-Ahead Logging (WAL) for SQLite if not set. See
    // https://www.drupal.org/node/2348137 and
    // https://www.sqlite.org/wal.html.
    $init_commands = $connectionParameters->init_commands + [
      'wal' => "PRAGMA journal_mode=WAL",
    ];
    $sqlite->exec(implode('; ', $init_commands));

    return $sqlite;
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
          $count = $this->query('SELECT COUNT(*) FROM ' . $prefix . '.sqlite_master WHERE type = :type AND name NOT LIKE :pattern', [
            ':type' => 'table',
            ':pattern' => 'sqlite_%',
          ])->fetchField();

          // We can prune the database file if it doesn't have any tables.
          if ($count == 0 && $this->connectionOptions->database != ':memory:' && file_exists($this->connectionOptions->database . '-' . $prefix)) {
            // Detach the database.
            $this->query('DETACH DATABASE :schema', [':schema' => $prefix]);
            // Destroy the database file.
            unlink($this->connectionOptions->database . '-' . $prefix);
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
  public function attachDatabase(string $database): void {
    // Only attach the database once.
    if (!isset($this->attachedDatabases[$database])) {
      // In memory database use ':memory:' as database name. According to
      // http://www.sqlite.org/inmemorydb.html it will open a unique database so
      // attaching it twice is not a problem.
      $database_file = $this->connectionOptions->database !== ':memory:' ? $this->connectionOptions->database . '-' . $database : $this->connectionOptions->database;
      $this->query('ATTACH DATABASE :database_file AS :database', [
        ':database_file' => $database_file,
        ':database' => $database,
      ]);
      $this->attachedDatabases[$database] = $database;
    }
  }

  /**
   * Gets all the attached databases.
   *
   * @return array
   *   An array of attached database names.
   *
   * @see \Drupal\sqlite\Driver\Database\sqlite\Connection::__construct()
   */
  public function getAttachedDatabases() {
    return $this->attachedDatabases;
  }

  /**
   * SQLite compatibility implementation for the IF() SQL function.
   */
  public static function sqlFunctionIf($condition, $expr1, $expr2 = NULL) {
    return $condition ? $expr1 : $expr2;
  }

  /**
   * SQLite compatibility implementation for the GREATEST() SQL function.
   */
  public static function sqlFunctionGreatest() {
    $args = func_get_args();
    foreach ($args as $v) {
      if (!isset($v)) {
        unset($args);
      }
    }
    if (count($args)) {
      return max($args);
    }
    else {
      return NULL;
    }
  }

  /**
   * SQLite compatibility implementation for the LEAST() SQL function.
   */
  public static function sqlFunctionLeast() {
    // Remove all NULL, FALSE and empty strings values but leaves 0 (zero)
    // values.
    $values = FilterArray::removeEmptyStrings(func_get_args());

    return count($values) < 1 ? NULL : min($values);
  }

  /**
   * SQLite compatibility implementation for the CONCAT() SQL function.
   */
  public static function sqlFunctionConcat() {
    $args = func_get_args();
    return implode('', $args);
  }

  /**
   * SQLite compatibility implementation for the CONCAT_WS() SQL function.
   *
   * @see http://dev.mysql.com/doc/refman/5.6/en/string-functions.html#function_concat-ws
   */
  public static function sqlFunctionConcatWs() {
    $args = func_get_args();
    $separator = array_shift($args);
    // If the separator is NULL, the result is NULL.
    if ($separator === FALSE || is_null($separator)) {
      return NULL;
    }
    // Skip any NULL values after the separator argument.
    $args = array_filter($args, function ($value) {
      return !is_null($value);
    });
    return implode($separator, $args);
  }

  /**
   * SQLite compatibility implementation for the SUBSTRING() SQL function.
   */
  public static function sqlFunctionSubstring($string, $from, $length) {
    return substr($string, $from - 1, $length);
  }

  /**
   * SQLite compatibility implementation for the SUBSTRING_INDEX() SQL function.
   */
  public static function sqlFunctionSubstringIndex($string, $delimiter, $count) {
    // If string is empty, simply return an empty string.
    if (empty($string)) {
      return '';
    }
    $end = 0;
    for ($i = 0; $i < $count; $i++) {
      $end = strpos($string, $delimiter, $end + 1);
      if ($end === FALSE) {
        $end = strlen($string);
      }
    }
    return substr($string, 0, $end);
  }

  /**
   * SQLite compatibility implementation for the RAND() SQL function.
   */
  public static function sqlFunctionRand($seed = NULL) {
    if (isset($seed)) {
      mt_srand($seed);
    }
    return mt_rand() / mt_getrandmax();
  }

  /**
   * SQLite compatibility implementation for the REGEXP SQL operator.
   *
   * The REGEXP operator is natively known, but not implemented by default.
   *
   * @see http://www.sqlite.org/lang_expr.html#regexp
   */
  public static function sqlFunctionRegexp($pattern, $subject) {
    // preg_quote() cannot be used here, since $pattern may contain reserved
    // regular expression characters already (such as ^, $, etc). Therefore,
    // use a rare character as PCRE delimiter.
    $pattern = '#' . addcslashes($pattern, '#') . '#i';
    return preg_match($pattern, $subject);
  }

  /**
   * SQLite compatibility implementation for the LIKE BINARY SQL operator.
   *
   * SQLite supports case-sensitive LIKE operations through the
   * 'case_sensitive_like' PRAGMA statement, but only for ASCII characters, so
   * we have to provide our own implementation with UTF-8 support.
   *
   * @see https://sqlite.org/pragma.html#pragma_case_sensitive_like
   * @see https://sqlite.org/lang_expr.html#like
   */
  public static function sqlFunctionLikeBinary($pattern, $subject) {
    // Replace the SQL LIKE wildcard meta-characters with the equivalent regular
    // expression meta-characters and escape the delimiter that will be used for
    // matching.
    $pattern = str_replace(['%', '_'], ['.*?', '.'], preg_quote($pattern, '/'));
    return preg_match('/^' . $pattern . '$/', $subject);
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

    $this->query('CREATE TEMPORARY TABLE ' . $tablename . ' AS ' . $query, $args, $options);

    // Temporary tables always live in the temp database, which means that
    // they cannot be fully qualified table names since they do not live
    // in the main SQLite database. We provide the fully-qualified name
    // ourselves to prevent Drupal from applying prefixes.
    // @see https://www.sqlite.org/lang_createtable.html
    return 'temp.' . $tablename;
  }

  /**
   * {@inheritdoc}
   */
  public function driver() {
    return 'sqlite';
  }

  /**
   * {@inheritdoc}
   */
  public function databaseType() {
    return 'sqlite';
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
    // Verify the database is writable.
    $db_directory = new \SplFileInfo(dirname($database));
    if (!$db_directory->isDir() && !\Drupal::service('file_system')->mkdir($db_directory->getPathName(), 0755, TRUE)) {
      throw new DatabaseNotFoundException('Unable to create database directory ' . $db_directory->getPathName());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function mapConditionOperator($operator) {
    return static::$sqliteConditionOperatorMap[$operator] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareStatement(string $query, array $options, bool $allow_row_count = FALSE): StatementInterface {
    assert(!isset($options['return']), 'Passing "return" option to prepareStatement() has no effect. See https://www.drupal.org/node/3185520');
    assert(!isset($options['fetch']) || $options['fetch'] instanceof FetchAs || is_string($options['fetch']), 'The "fetch" option passed to query() must contain a FetchAs enum case or a string. See https://www.drupal.org/node/3488338');

    try {
      return new Statement(
        $this->connection,
        $this,
        $this->preprocessStatement($query, $options),
        $options['pdo'] ?? [],
        $allow_row_count,
      );
    }
    catch (\Exception $e) {
      $this->exceptionHandler()->handleStatementException($e, $query, $options);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getFullQualifiedTableName($table) {
    $prefix = $this->getPrefix();

    // Don't include the SQLite database file name as part of the table name.
    return $prefix . $table;
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
  public function select($table, $alias = NULL, array $options = []) {
    return new Select($this, $table, $alias, $options);
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
  public function truncate($table, array $options = []) {
    return new Truncate($this, $table, $options);
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
