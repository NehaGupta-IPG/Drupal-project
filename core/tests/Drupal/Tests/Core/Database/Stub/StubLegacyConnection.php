<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Database\Stub;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\ExceptionHandler;
use Drupal\Core\Database\Log;
use Drupal\Core\Database\StatementWrapperIterator;
use Drupal\Tests\Core\Database\Stub\Driver\Schema;

/**
 * A stub of the abstract Connection class for testing purposes.
 *
 * Includes minimal implementations of Connection's abstract methods.
 */
class StubLegacyConnection extends Connection {

  /**
   * {@inheritdoc}
   */
  protected $statementWrapperClass = StatementWrapperIterator::class;

  /**
   * Public property so we can test driver loading mechanism.
   *
   * @var string
   * @see driver().
   */
  public $driver = 'stub';

  /**
   * Constructs a Connection object.
   *
   * @param \PDO $connection
   *   An object of the PDO class representing a database connection.
   * @param array $connection_options
   *   An array of options for the connection.
   */
  public function __construct(\PDO $connection, array $connection_options) {
    parent::__construct(
      $connection,
      $connection_options,
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function open(array &$connection_options = []) {
    return new \stdClass();
  }

  /**
   * {@inheritdoc}
   */
  public function queryRange($query, $from, $count, array $args = [], array $options = []) {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function driver() {
    return $this->driver;
  }

  /**
   * {@inheritdoc}
   */
  public function databaseType() {
    return 'stub';
  }

  /**
   * {@inheritdoc}
   */
  public function createDatabase($database) {}

  /**
   * {@inheritdoc}
   */
  public function mapConditionOperator($operator) {
    return NULL;
  }

  /**
   * Helper method to test database classes are not included in backtraces.
   *
   * @return array
   *   The caller stack entry.
   */
  public function testLogCaller() {
    return (new Log())->findCaller();
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
  public function upsert($table, array $options = []) {
    return new StubUpsert($this, $table, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function schema() {
    if (empty($this->schema)) {
      $this->schema = new Schema();
    }
    return $this->schema;
  }

  /**
   * {@inheritdoc}
   */
  public function condition($conjunction) {
    return new StubCondition($conjunction);
  }

}
