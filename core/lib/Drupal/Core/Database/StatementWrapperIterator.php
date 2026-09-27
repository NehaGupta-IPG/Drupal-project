<?php

namespace Drupal\Core\Database;

use Drupal\Core\Database\Statement\FetchAs;
use Drupal\Core\Database\Statement\PdoResult;
use Drupal\Core\Database\Statement\PdoTrait;
use Drupal\Core\Database\Statement\StatementBase;

/**
 * StatementInterface iterator implementation.
 */
class StatementWrapperIterator extends StatementBase {

  use PdoTrait;

  /**
   * Constructs a StatementWrapperIterator object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   Drupal database connection object.
   * @param object $clientConnection
   *   Client database connection object, for example \PDO.
   * @param string $query
   *   The SQL query string.
   * @param array $options
   *   Array of query options.
   * @param bool $rowCountEnabled
   *   (optional) Enables counting the rows matched. Defaults to FALSE.
   */
  public function __construct(
    Connection $connection,
    object $clientConnection,
    string $query,
    array $options,
    bool $rowCountEnabled = FALSE,
  ) {
    parent::__construct($connection, $clientConnection, $query, $rowCountEnabled);
    $this->prepare($options);
    $this->setFetchMode(FetchAs::Object);
  }

  /**
   * Returns the client-level database PDO statement object.
   *
   * This method should normally be used only within database driver code.
   *
   * @return \PDOStatement
   *   The client-level database PDO statement.
   *
   * @throws \RuntimeException
   *   If the client-level statement is not set.
   */
  public function getClientStatement(): \PDOStatement {
    if (isset($this->clientStatement)) {
      assert($this->clientStatement instanceof \PDOStatement);
      return $this->clientStatement;
    }
    throw new \LogicException('\\PDOStatement not initialized');
  }

  /**
   * Executes a prepared statement.
   *
   * @param array|null $args
   *   (Optional) An array of values with as many elements as there are bound
   *   parameters in the SQL statement being executed. This can be NULL.
   * @param array $options
   *   (Optional) An array of options for this query.
   *
   * @return bool
   *   TRUE on success, or FALSE on failure.
   */
  public function doExecute(?array $args = [], array $options = []): bool {
    $return = $this->clientExecute($args, $options);
    $this->result = new PdoResult(
      $this->fetchMode,
      $this->fetchOptions,
      $this->getClientStatement(),
    );
    $this->markResultsetIterable($return);
    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function prepare(array $options): void {
    $this->clientStatement = $this->connection
      ->getClientConnection()
      ->prepare($this->getQueryString(), $options);
  }

}
