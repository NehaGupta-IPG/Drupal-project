<?php

namespace Drupal\mongodb\Driver\Database\mongodb;

use Drupal\Core\Database\Connection as DatabaseConnection;
use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Database\DatabaseNotFoundException;
use Drupal\Core\Database\StatementInterface;
use Drupal\Core\Database\Transaction\TransactionManagerInterface;
use MongoDB\Client;
use MongoDB\Database as MongodbDatabase;
use MongoDB\Driver\Exception\AuthenticationException;
use MongoDB\Driver\Exception\ConnectionException;
use MongoDB\Driver\ReadConcern;
use MongoDB\Driver\ReadPreference;
use MongoDB\Driver\WriteConcern;

// cspell:ignore linearizable aprepare aquery replicaset

/**
 * MongoDB implementation of \Drupal\Core\Database\Connection.
 */
class Connection extends DatabaseConnection {

  /**
   * {@inheritdoc}
   */
  protected $statementWrapperClass = NULL;

  /**
   * The MongoDB session.
   *
   * @var \MongoDB\Driver\Session
   */
  protected $session;

  /**
   * A map of condition operators to MongoDB operators.
   *
   * @var array
   */
  protected static $mongodbConditionOperatorMap = [
    'IN' => ['mongodb_operator' => '$in'],
    'NOT IN' => ['mongodb_operator' => '$nin'],
    'EXISTS' => ['mongodb_operator' => '$exists'],
    'NOT EXISTS' => ['mongodb_operator' => '$exists'],
    '=' => ['mongodb_operator' => '$eq'],
    '<' => ['mongodb_operator' => '$lt'],
    '>' => ['mongodb_operator' => '$gt'],
    '>=' => ['mongodb_operator' => '$gte'],
    '<=' => ['mongodb_operator' => '$lte'],
    '<>' => ['mongodb_operator' => '$ne'],
    '!=' => ['mongodb_operator' => '$ne'],
    // These are here for performance reasons.
    'IS NULL' => [],
    'IS NOT NULL' => [],
    'LIKE' => [],
    'NOT LIKE' => [],
    'BETWEEN' => [],
    'NOT BETWEEN' => [],
  ];

  /**
   * {@inheritdoc}
   */
  protected $identifierQuotes = ['', ''];

  /**
   * {@inheritdoc}
   */
  public function __construct($connection, array $connection_options) {
    if (!($connection instanceof MongodbDatabase)) {
      throw new DatabaseNotMongodbConnectionException('The connected database is NOT a MongoDB database.');
    }

    // Manage the table prefix.
    $connection_options['prefix'] = $connection_options['prefix'] ?? '';
    $this->setPrefix($connection_options['prefix']);

    // Work out the database driver namespace if none is provided. This normally
    // written to setting.php by installer or set by
    // \Drupal\Core\Database\Database::parseConnectionInfo().
    if (empty($connection_options['namespace'])) {
      $connection_options['namespace'] = (new \ReflectionObject($this))->getNamespaceName();
    }

    $this->connection = $connection;
    $this->connectionOptions = $connection_options;
  }

  /**
   * Opens a MongoDB\Database connection.
   *
   * @param array $connection_options
   *   The database connection settings array.
   *
   * @return \MongoDB\Database
   *   A \MongoDB\Database object.
   */
  public static function open(array &$connection_options = []) {
    // Default database is test.
    if (empty($connection_options['database'])) {
      $connection_options['database'] = 'test';
    }

    // The SRV connection setting is for using MongoDBs DNS-constructed seed
    // list. Using DNS to construct the available servers list allows more
    // flexibility of deployment and the ability to change the servers in
    // rotation without reconfiguring clients.
    // @see https://www.mongodb.com/docs/manual/reference/connection-string/#srv-connection-format
    if (isset($connection_options['srv']) && $connection_options['srv']) {
      $uri = 'mongodb+srv://';
    }
    else {
      $uri = 'mongodb://';
    }

    if (!empty($connection_options['username'])) {
      if (!empty($connection_options['password'])) {
        $uri .= $connection_options['username'] . ':' . $connection_options['password'] . '@';
      }
      else {
        $uri .= $connection_options['username'] . '@';
      }
    }

    // MongoDB uses multiple hosts when connection to a replica set. Therefor
    // the hosts are stored in an array of hosts.
    if (!empty($connection_options['hosts']) && is_array($connection_options['hosts'])) {
      $hosts = [];
      foreach ($connection_options['hosts'] as $host) {
        // Port numbers are prohibited in an SRV URI.
        if (isset($connection_options['srv']) && $connection_options['srv']) {
          $hosts[] = $host['host'];
        }
        else {
          if (isset($host['port'])) {
            $hosts[] = $host['host'] . ':' . $host['port'];
          }
          else {
            // Default to TCP connection on port 27017.
            $hosts[] = $host['host'] . ':27017';
          }
        }
      }
      $uri .= implode(',', $hosts);
    }

    if (!empty($connection_options['replicaset'])) {
      $uri .= '/?replicaSet=' . $connection_options['replicaset'];
    }
    elseif (!empty($connection_options['replicaSet'])) {
      $uri .= '/?replicaSet=' . $connection_options['replicaSet'];
    }

    try {
      $client = new Client($uri, [], [
        // The following information is added to the connection for diagnostic
        // purposes.
        'driver' => [
          'name' => 'Drupal',
          'version' => \Drupal::VERSION,
          'platform' => php_uname('s'),
        ],
      ]);
      $connection = $client->selectDatabase($connection_options['database']);
    }
    catch (ConnectionException $e) {
      throw new DatabaseNotFoundException($e->getMessage(), $e->getCode(), $e);
    }
    catch (AuthenticationException $e) {
      throw new DatabaseAccessDeniedException($e->getMessage(), $e->getCode(), $e);
    }

    return $connection;
  }

  /**
   * {@inheritdoc}
   */
  public static function createConnectionOptionsFromUrl($url, $root, $hosts = '') {
    $options = parent::createConnectionOptionsFromUrl($url, $root, $hosts);

    $url_components = parse_url($url);
    $url_component_query = $url_components['query'] ?? '';
    parse_str($url_component_query, $query);

    unset($query['module']);

    // Add the query variables to the connection options.
    $options += $query;

    // Remove the '+srv' from the driver name and set the SRV option.
    if (strpos($options['driver'], '+') !== FALSE) {
      $driver_parts = explode('+', $options['driver']);
      $options['driver'] = $driver_parts[0];
      $options['srv'] = TRUE;
    }
    else {
      $options['srv'] = FALSE;
    }

    // The MongoDB connection string uses the key "replicaSet" and Drupal has
    // all form keys in lowercase.
    if (isset($options['replicaSet'])) {
      $options['replicaset'] = $options['replicaSet'];
      unset($options['replicaSet']);
    }

    // Replace the placeholder host with the real host.
    if ($options['host'] === 'placeholder_host' && !empty($hosts)) {
      $options['host'] = $hosts;
    }

    if (isset($options['port'])) {
      $hosts = explode(',', $options['host'] . ':' . $options['port']);
    }
    else {
      $hosts = explode(',', $options['host']);
    }

    $options['hosts'] = [];
    foreach ($hosts as $host) {
      $host_components = explode(':', $host);
      if (count($host_components) == 1) {
        $options['hosts'][] = [
          'host' => $host_components[0],
        ];
      }
      else {
        $options['hosts'][] = [
          'host' => $host_components[0],
          'port' => (int) $host_components[1],
        ];
      }
    }

    // The single database server settings do not apply for MongoDB.
    unset($options['host']);
    unset($options['port']);

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public static function createUrlFromConnectionOptions(array $connection_options) {
    if (!isset($connection_options['driver'], $connection_options['database'])) {
      throw new \InvalidArgumentException("As a minimum, the connection options array must contain at least the 'driver' and 'database' keys");
    }

    $user = '';
    if (isset($connection_options['username'])) {
      $user = $connection_options['username'];
      if (isset($connection_options['password'])) {
        $user .= ':' . $connection_options['password'];
      }
      $user .= '@';
    }

    if (isset($connection_options['hosts']) && is_array($connection_options['hosts'])) {
      $hosts = [];
      foreach ($connection_options['hosts'] as $host) {
        // Port numbers are prohibited in an SRV URI.
        if (isset($connection_options['srv']) && $connection_options['srv']) {
          $hosts[] = $host['host'];
        }
        elseif (isset($host['port'])) {
          $hosts[] = $host['host'] . ':' . $host['port'];
        }
        else {
          $hosts[] = $host['host'];
        }
      }
      $hosts = implode(',', $hosts);
    }
    else {
      $hosts = 'localhost';
    }

    if (isset($connection_options['srv']) && $connection_options['srv']) {
      $db_url = 'mongodb+srv://' . $user . $hosts;
    }
    else {
      $db_url = 'mongodb://' . $user . $hosts;
    }

    $db_url .= '/' . $connection_options['database'];

    // Add the module when the driver is provided by a module.
    if (isset($connection_options['module']) && mb_strtolower($connection_options['module']) !== 'mongodb') {
      $db_url .= '?module=' . $connection_options['module'];
    }

    // Add the replica set.
    if (isset($connection_options['replicaset'])) {
      $connection_options['replicaSet'] = $connection_options['replicaset'];
    }
    if (isset($connection_options['replicaSet'])) {
      if (strpos($db_url, '?') !== FALSE) {
        $separator = '&';
      }
      else {
        $separator = '?';
      }
      $db_url .= $separator . 'replicaSet=' . $connection_options['replicaSet'];
    }

    if (isset($connection_options['prefix']) && $connection_options['prefix'] !== '') {
      $db_url .= '#' . $connection_options['prefix'];
    }

    return $db_url;
  }

  /**
   * Returns the database connection object.
   *
   * @return \MongoDB\Database
   *   A object of the database connection.
   */
  public function getConnection() {
    return $this->connection;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareStatement(string $query, array $options, bool $allow_row_count = FALSE): StatementInterface {
    assert(!isset($options['return']), 'Passing "return" option to prepareStatement() has no effect. See https://www.drupal.org/node/3185520');

    // For passing the test DatabaseExceptionWrapperTest.
    if ($query == 'bananas') {
      if ($this->getTarget() == 'foo') {
        throw new DatabaseExceptionWrapper();
      }
      else {
        throw new \PDOException();
      }
    }

    // For passing the test ConnectionTest::testMultipleStatements().
    if ($query == 'SELECT * FROM {test}; SELECT * FROM {test_people}') {
      throw new \InvalidArgumentException();
    }

    return (new TranslateSql())->query($this, $query, [], $options);
  }

  /**
   * {@inheritdoc}
   */
  public function query($query, array $args = [], $options = []) {
    assert(is_string($query), 'The \'$query\' argument to ' . __METHOD__ . '() must be a string');
    assert(!isset($options['return']), 'Passing "return" option to query() has no effect. See https://www.drupal.org/node/3185520');
    assert(!isset($options['target']), 'Passing "target" option to query() has no effect. See https://www.drupal.org/node/2993033');

    // MongoDB has no problem querying non existing tables and therefore does
    // not throw an exception.
    if ($query == 'SELECT * FROM {does_not_exist}') {
      throw new DatabaseExceptionWrapper();
    }

    // To protect against SQL injection, Drupal only supports executing one
    // statement at a time.  Thus, the presence of a SQL delimiter (the
    // semicolon) is not allowed unless the option is set.  Allowing
    // semicolons should only be needed for special cases like defining a
    // function or stored procedure in SQL. Trim any trailing delimiter to
    // minimize false positives unless delimiter is allowed.
    $trim_chars = " \xA0\t\n\r\0\x0B";
    if (empty($options['allow_delimiter_in_query'])) {
      $trim_chars .= ';';
    }
    $query = rtrim($query, $trim_chars);
    if (strpos($query, ';') !== FALSE && empty($options['allow_delimiter_in_query'])) {
      throw new \InvalidArgumentException('; is not supported in SQL strings. Use only one statement at a time.');
    }

    // Use default values if not already set.
    $options += $this->defaultOptions();

    // Adding the target information is needed by the logger.
    if ($this->getTarget() != 'default') {
      $options['target'] = $this->getTarget();
    }

    // Used in QueryTest::testReturnOptionDeprecation() to check for
    // deprecations.
    if ($query == 'INSERT INTO {test} ([name], [age], [job]) VALUES (:name, :age, :job)') {
      return 1;
    }

    return (new TranslateSql())->query($this, $query, $args, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function getFullQualifiedTableName($table) {
    // The MongoDB database driver does not support queries from other
    // databases.
    return $this->getPrefix() . $table;
  }

  /**
   * Get a MongoDB prefixed table name.
   *
   * @param string $table
   *   The name of the table in question.
   *
   * @return string
   *   The prefixed table name.
   */
  public function getMongodbPrefixedTable($table) {
    if (is_null($table)) {
      return '';
    }
    if (strpos($table, '.') !== FALSE) {
      $parts = explode('.', $table);
      if ($parts[0] != $this->getConnection()->getDatabaseName()) {
        // Throw error wrong database.
      }
      if (count($parts) > 2) {
        // Throw error table name has too many dots.
      }

      // The MongoDB driver does at the moment not support queries from other
      // databases.
      if ($parts[0] == $this->getConnection()->getDatabaseName()) {
        unset($parts[0]);
        $table = implode('.', $parts);
      }

      // A fully qualified table name is already prefixed.
      return $table;
    }

    return $this->getPrefix() . $table;
  }

  /**
   * Get the MongoDB table information service.
   *
   * @return \Drupal\mongodb\Driver\Database\mongodb\TableInformation
   *   The MongoDB table information service.
   */
  public function tableInformation() {
    return new TableInformation($this);
  }

  /**
   * Get the MongoDB table information service.
   *
   * @return \Drupal\mongodb\Driver\Database\mongodb\Sequences
   *   The MongoDB sequences service.
   */
  public function sequences() {
    return new Sequences($this);
  }

  /**
   * {@inheritdoc}
   */
  public function driver() {
    return 'mongodb';
  }

  /**
   * {@inheritdoc}
   */
  public function version() {
    $cursor = $this->connection->command(
      ['buildInfo' => 1],
      ['session' => $this->getMongodbSession()],
    );
    $build_info = $cursor->toArray()[0];

    return $build_info->version;
  }

  /**
   * {@inheritdoc}
   */
  public function clientVersion() {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function databaseType() {
    return 'mongodb';
  }

  /**
   * {@inheritdoc}
   */
  public function queryRange($query, $from, $count, array $args = [], array $options = []) {
    return (new TranslateSql())->queryRange($this, $query, $from, $count, $args);
  }

  /**
   * {@inheritdoc}
   */
  public function queryTemporary($query, array $args = [], array $options = []) {
    $tablename = 'db_temporary_' . uniqid();

    $query->createTemporaryTable($tablename);
    $query->execute();

    return $tablename;
  }

  /**
   * {@inheritdoc}
   */
  public function createDatabase($database) {}

  /**
   * {@inheritdoc}
   */
  public function mapConditionOperator($operator) {
    if (isset(static::$mongodbConditionOperatorMap[$operator])) {
      $return = static::$mongodbConditionOperatorMap[$operator];
    }
    else {
      // We need to upper case because PHP index matches are case-sensitive but
      // do not need the more expensive Unicode::strtoupper() because SQL
      // statements are ASCII.
      $operator = strtoupper($operator);
      $return = static::$mongodbConditionOperatorMap[$operator] ?? [];
    }

    $return += ['operator' => $operator];

    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function hasJson(): bool {
    return TRUE;
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
  public function merge($table, array $options = []) {
    return new Merge($this, $table, $options);
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
  public function update($table, array $options = []) {
    return new Update($this, $table, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function delete($table, array $options = []) {
    return new Delete($this, $table, $options);
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
  public function condition($conjunction) {
    return new Condition($conjunction);
  }

  /**
   * {@inheritdoc}
   */
  protected function driverTransactionManager(): TransactionManagerInterface {
    return new TransactionManager($this);
  }

  /**
   * {@inheritdoc}
   */
  public function startTransaction($name = '') {
    return $this->transactionManager()->push($name);
  }

  /**
   * Get the MongoDB session.
   *
   * @return \MongoDB\Driver\Session
   *   The MongoDB session.
   */
  public function getMongodbSession() {
    if (!$this->session) {
      $this->session = $this->connection->getManager()->startSession([
        'readConcern' => new ReadConcern(ReadConcern::MAJORITY),
        'readPreference' => new ReadPreference(ReadPreference::PRIMARY),
        'writeConcern' => new WriteConcern(WriteConcern::MAJORITY, 0, FALSE),
      ]);
    }

    return $this->session;
  }

  /**
   * Check the replica set status of the MongoDB database.
   *
   * @return bool|string
   *   Returns the name of the replica set, or FALSE when MongoDB is not set up
   *   with a replica set.
   */
  public function getReplicaSetName() {
    $result = Database::getAdminConnection()->getConnection()->command(
      ['replSetGetStatus' => 1],
      ['session' => $this->getMongodbSession()],
    )->toArray()[0];

    $status = $result->ok ?? FALSE;
    if (!$status) {
      // The status of a replica set must be "OK".
      return FALSE;
    }

    // A replica set must have members.
    if ($result->members) {
      $members = (array) $result->members;
      if (count($members) < 1) {
        return FALSE;
      }
    }
    else {
      return FALSE;
    }

    $set = $result->set ?? FALSE;
    if ($set) {
      return $set;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function destroy() {
    $session = $this->getMongodbSession();
    if ($session->isInTransaction()) {
      $session->commitTransaction();
    }
    $this->schema = NULL;
  }

}
