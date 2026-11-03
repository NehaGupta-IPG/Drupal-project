<?php

namespace Drupal\Core\Database\Query;

use Drupal\Core\Database\Connection;

/**
 * General class for an abstracted TRUNCATE operation.
 */
class Truncate extends Query implements QueryExecutionVoidInterface {

  /**
   * The table to truncate.
   *
   * @var string
   */
  protected $table;

  /**
   * Constructs a Truncate query object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   A Connection object.
   * @param string $table
   *   Name of the table to associate with this query.
   * @param array $options
   *   Array of database options.
   */
  public function __construct(Connection $connection, $table, array $options = []) {
    parent::__construct($connection, $options);
    $this->table = $table;
  }

  /**
   * Executes the TRUNCATE query.
   *
   * In most cases, TRUNCATE is not a transaction safe statement as it is a DDL
   * statement which results in an implicit COMMIT. When we are in a
   * transaction, fallback to the slower, but transactional, DELETE.
   * PostgreSQL also locks the entire table for a TRUNCATE strongly reducing
   * the concurrency with other transactions.
   *
   * Emptying a table resets its serial ID, which only TRUNCATE does here. The
   * reset_serial_id option states what is to happen to the serial ID, and
   * decides the statement on its own:
   * - TRUE resets the serial ID, so TRUNCATE runs inside a transaction too,
   *   and the implicit COMMIT comes with it.
   * - FALSE leaves the serial ID alone, so DELETE runs outside a transaction
   *   too.
   * - Leaving the option out states nothing, so the statement stays the one
   *   the transaction calls for. A table emptied inside a transaction then
   *   keeps its serial ID, as it did before the option existed.
   *
   * @see https://learnsql.com/blog/difference-between-truncate-delete-and-drop-table-in-sql
   */
  public function execute(): void {
    $statement = $this->connection->prepareStatement((string) $this, $this->queryOptions, TRUE);
    try {
      $statement->execute([], $this->queryOptions);
    }
    catch (\Exception $e) {
      $this->connection->exceptionHandler()->handleExecutionException($e, $statement, [], $this->queryOptions);
    }
  }

  /**
   * Implements PHP magic __toString method to convert the query to a string.
   *
   * @return string
   *   The prepared statement.
   */
  public function __toString() {
    // Create a sanitized comment string to prepend to the query.
    $comments = $this->connection->makeComment($this->comments);

    // If no explicit directive was provided about the serial ID, we need to
    // check whether a transaction is active, in which case we cannot rely on
    // TRUNCATE, as that would automatically commit the transaction.
    // @see ::execute()
    $resetSerialId = $this->queryOptions['reset_serial_id'] ?? NULL;
    if (isset($resetSerialId)) {
      $truncate = $resetSerialId;
    }
    else {
      $truncate = !$this->connection->inTransaction();
    }
    if (!$truncate) {
      return $comments . 'DELETE FROM {' . $this->connection->escapeTable($this->table) . '}';
    }
    else {
      return $comments . 'TRUNCATE {' . $this->connection->escapeTable($this->table) . '} ';
    }
  }

}
