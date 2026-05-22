<?php

namespace Drupal\mongodb\Driver\Database\mongodb;

use Drupal\Core\Database\StatementInterface;

/**
 * The MongoDB implementation of the StatementInterface for count queries.
 */
class StatementCountQuery implements \Iterator, StatementInterface {

  /**
   * The result of the count query.
   *
   * @var int
   */
  protected $count;

  /**
   * The query in a string format.
   *
   * @var string
   */
  protected $queryString;

  /**
   * Reference to the Drupal database connection object for this statement.
   *
   * @var \Drupal\mongodb\Driver\Database\mongodb\Connection
   */
  public $connection;

  /**
   * The constructor.
   *
   * @param \Drupal\mongodb\Driver\Database\mongodb\Connection $connection
   *   Database connection object for MongoDB.
   * @param int $count
   *   The result of the count query.
   * @param array $options
   *   An array of options for the count query.
   */
  public function __construct(Connection $connection, $count, array $options) {
    // The specific variable name is needed by the database query logger.
    $this->connection = $connection;

    // SQL queries return string values.
    $this->count = (int) $count;

    // This is only needed by the database query logger.
    $this->queryString = $options['query_string'] ?? '';

    // Change the database connection for the database query logger.
    if (isset($options['target']) && $options['target'] != $this->connection->getTarget()) {
      $this->connection = Database::getConnection($options['target']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function execute($args = [], $options = []) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getConnectionTarget(): string {
    return $this->connection->getTarget();
  }

  /**
   * {@inheritdoc}
   */
  public function getQueryString() {
    return $this->queryString;
  }

  /**
   * {@inheritdoc}
   */
  public function rowCount() {
    return (int) $this->count;
  }

  /**
   * {@inheritdoc}
   */
  public function setFetchMode($mode, $a1 = NULL, $a2 = []) {
    // Do nothing.
  }

  /**
   * {@inheritdoc}
   */
  public function fetch($mode = NULL, $cursor_orientation = NULL, $cursor_offset = NULL) {
    return $this->count;
  }

  /**
   * {@inheritdoc}
   */
  public function fetchField($index = 0) {
    return $this->count;
  }

  /**
   * {@inheritdoc}
   */
  public function fetchObject(?string $class_name = NULL, array $constructor_arguments = []) {
    return $this->count;
  }

  /**
   * {@inheritdoc}
   */
  public function fetchAssoc() {
    return $this->count;
  }

  /**
   * {@inheritdoc}
   */
  public function fetchAll($mode = NULL, $column_index = NULL, $constructor_arguments = NULL) {
    return [$this->count];
  }

  /**
   * {@inheritdoc}
   */
  public function fetchCol($index = 0) {
    return [$this->count];
  }

  /**
   * {@inheritdoc}
   */
  public function fetchAllKeyed($key_index = 0, $value_index = 1) {
    return [$this->count];
  }

  /**
   * {@inheritdoc}
   */
  public function fetchAllAssoc($key, $fetch = NULL) {
    return [$this->count];
  }

  /**
   * {@inheritdoc}
   */
  public function current(): mixed {
    return $this->count;
  }

  /**
   * {@inheritdoc}
   */
  public function key(): mixed {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function rewind(): void {
    // Nothing to do: our DatabaseStatement can't be rewound.
  }

  /**
   * {@inheritdoc}
   */
  public function next(): void {
    // Do nothing, since this is an always-empty implementation.
  }

  /**
   * {@inheritdoc}
   */
  public function valid(): bool {
    return TRUE;
  }

}
