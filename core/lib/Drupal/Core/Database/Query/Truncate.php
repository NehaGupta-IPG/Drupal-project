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
   * The "reset_serial_id" option states what is to happen to the serial ID of
   * the table, and decides the statement on its own:
   * - TRUE empties the table and resets its serial ID, so TRUNCATE runs inside
   *   a transaction too. An implicit COMMIT comes with it.
   * - FALSE empties the table and leaves its serial ID alone, so DELETE runs
   *   outside a transaction too, because TRUNCATE resets the serial ID here.
   * - Leaving the option out says nothing about the serial ID. The statement
   *   then depends on the transaction, and what becomes of the serial ID is up
   *   to the driver.
   *
   * Drivers whose TRUNCATE leaves the serial ID alone, or which have no
   * TRUNCATE, override this class to honor the option their own way. They may
   * empty a table faster than DELETE does with the option set to FALSE.
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

    // @see ::execute()
    if (isset($this->queryOptions['reset_serial_id'])) {
      // The caller stated what is to happen to the serial ID, and here only
      // TRUNCATE resets it, so the statement follows from the option alone.
      $truncate = (bool) $this->queryOptions['reset_serial_id'];
    }
    else {
      // Nothing was stated, so leave the serial ID to whatever the statement
      // does to it. TRUNCATE is not transaction safe.
      $truncate = !$this->connection->inTransaction();
    }

    if (!$truncate) {
      return $comments . 'DELETE FROM {' . $this->connection->escapeTable($this->table) . '}';
    }
    return $comments . 'TRUNCATE {' . $this->connection->escapeTable($this->table) . '} ';
  }

}
