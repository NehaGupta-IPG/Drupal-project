<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Statement;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Event\StatementExecutionEndEvent;
use Drupal\Core\Database\Event\StatementExecutionFailureEvent;
use Drupal\Core\Database\Event\StatementExecutionStartEvent;
use Drupal\Core\Database\FetchModeTrait;
use Drupal\Core\Database\RowCountException;
use Drupal\Core\Database\StatementInterface;

/**
 * StatementInterface base implementation.
 *
 * This class is meant to be generic enough for any type of database client,
 * even if all Drupal core database drivers currently use PDO clients. We
 * implement \Iterator instead of \IteratorAggregate to allow iteration to be
 * kept in sync with the underlying database resultset cursor. PDO is not able
 * to execute a database operation while a cursor is open on the result of an
 * earlier select query, so Drupal by default uses buffered queries setting
 * \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY to TRUE on the connection. This forces
 * the query to return all the results in a buffer local to the client library,
 * potentially leading to memory issues in case of large datasets being
 * returned by a query. Other database clients, however, could allow
 * multithread queries, or developers could disable buffered queries in PDO:
 * in that case, this class prevents the resultset to be entirely fetched in
 * PHP memory (that an \IteratorAggregate implementation would force) and
 * therefore optimize memory usage while iterating the resultset.
 */
abstract class StatementBase implements \Iterator, StatementInterface {

  use FetchModeTrait;

  /**
   * The client database Statement object.
   *
   * For a \PDO client connection, this will be a \PDOStatement object.
   */
  protected ?object $clientStatement;

  /**
   * The results of a data query language (DQL) statement.
   */
  protected ?ResultBase $result = NULL;

  /**
   * Holds the default fetch mode.
   *
   * @deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. Use
   *   ResultBase::setFetchMode() instead.
   *
   * @see https://www.drupal.org/node/3613105
   */
  protected FetchAs $fetchMode = FetchAs::Object;

  /**
   * Holds fetch options.
   *
   * @var array{'class': class-string, 'constructor_args': list<mixed>, 'column': int}
   *
   * @deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. Use
   *   ResultBase::setFetchMode() instead.
   *
   * @see https://www.drupal.org/node/3613105
   */
  protected array $fetchOptions = [
    'class' => 'stdClass',
    'constructor_args' => [],
    'column' => 0,
  ];

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   Drupal database connection object.
   * @param object $clientConnection
   *   Client database connection object, for example \PDO.
   * @param string $queryString
   *   The query string.
   * @param bool $rowCountEnabled
   *   (optional) Enables counting the rows matched. Defaults to FALSE.
   */
  public function __construct(
    protected readonly Connection $connection,
    protected readonly object $clientConnection,
    protected readonly string $queryString,
    protected readonly bool $rowCountEnabled = FALSE,
  ) {
  }

  /**
   * Determines if the client-level database statement object exists.
   *
   * This method should normally be used only within database driver code.
   *
   * @return bool
   *   TRUE if the client statement exists, FALSE otherwise.
   */
  public function hasClientStatement(): bool {
    return isset($this->clientStatement);
  }

  /**
   * Returns the client-level database statement object.
   *
   * This method should normally be used only within database driver code.
   *
   * @return object
   *   The client-level database statement.
   *
   * @throws \RuntimeException
   *   If the client-level statement is not set.
   */
  public function getClientStatement(): object {
    if ($this->hasClientStatement()) {
      return $this->clientStatement;
    }
    throw new \LogicException('Client statement not initialized');
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
  abstract public function execute(?array $args = [], array $options = []);

  /**
   * Dispatches an event informing that the statement execution begins.
   *
   * @param array $args
   *   An array of values with as many elements as there are bound parameters in
   *   the SQL statement being executed. This can be empty.
   *
   * @return \Drupal\Core\Database\Event\StatementExecutionStartEvent|null
   *   The dispatched event or NULL if event dispatching is not enabled.
   */
  protected function dispatchStatementExecutionStartEvent(array $args): ?StatementExecutionStartEvent {
    if ($this->connection->isEventEnabled(StatementExecutionStartEvent::class)) {
      $startEvent = new StatementExecutionStartEvent(
        spl_object_id($this),
        $this->connection->getKey(),
        $this->connection->getTarget(),
        $this->getQueryString(),
        $args,
        $this->connection->findCallerFromDebugBacktrace()
      );
      $this->connection->dispatchEvent($startEvent);
      return $startEvent;
    }
    return NULL;
  }

  /**
   * Dispatches an event informing that the statement execution succeeded.
   *
   * @param \Drupal\Core\Database\Event\StatementExecutionStartEvent|null $startEvent
   *   The start event or NULL if event dispatching is not enabled.
   */
  protected function dispatchStatementExecutionEndEvent(?StatementExecutionStartEvent $startEvent): void {
    if (isset($startEvent) && $this->connection->isEventEnabled(StatementExecutionEndEvent::class)) {
      $this->connection->dispatchEvent(new StatementExecutionEndEvent(
        $startEvent->statementObjectId,
        $startEvent->key,
        $startEvent->target,
        $startEvent->queryString,
        $startEvent->args,
        $startEvent->caller,
        $startEvent->time
      ));
    }
  }

  /**
   * Dispatches an event informing of the statement execution failure.
   *
   * @param \Drupal\Core\Database\Event\StatementExecutionStartEvent|null $startEvent
   *   The start event or NULL if event dispatching is not enabled.
   * @param \Exception $e
   *   The statement exception thrown.
   */
  protected function dispatchStatementExecutionFailureEvent(?StatementExecutionStartEvent $startEvent, \Exception $e): void {
    if (isset($startEvent) && $this->connection->isEventEnabled(StatementExecutionFailureEvent::class)) {
      $this->connection->dispatchEvent(new StatementExecutionFailureEvent(
        $startEvent->statementObjectId,
        $startEvent->key,
        $startEvent->target,
        $startEvent->queryString,
        $startEvent->args,
        $startEvent->caller,
        $startEvent->time,
        get_class($e),
        $e->getCode(),
        $e->getMessage(),
      ));
    }
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
  public function setFetchMode(FetchAs $mode, string|int|null $a1 = NULL, array $a2 = []) {
    $fetchOptions = NULL;
    switch ($mode) {
      case FetchAs::ClassObject:
        $fetchOptions['class'] = $a1;
        if ($a2) {
          $fetchOptions['constructor_args'] = $a2;
        }
        break;

      case FetchAs::Column:
        $fetchOptions['column'] = $a1;
        break;

    }

    try {
      if ($this->result) {
        return $this->result->setFetchMode($mode, $fetchOptions ?? []);
      }
      return TRUE;
    }
    catch (\LogicException) {
      return TRUE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function fetch(?FetchAs $mode = NULL) {
    return $this->result->fetch($mode ?? $this->result->fetchMode, $this->result->fetchOptions);
  }

  /**
   * {@inheritdoc}
   */
  public function fetchObject(?string $className = NULL, array $constructorArguments = []) {
    return $className === NULL ?
      $this->result->fetch(FetchAs::Object, []) :
      $this->result->fetch(FetchAs::ClassObject, [
        'class' => $className,
        'constructor_args' => $constructorArguments,
      ]);
  }

  /**
   * {@inheritdoc}
   */
  public function fetchAssoc() {
    return $this->fetch(FetchAs::Associative);
  }

  /**
   * {@inheritdoc}
   */
  public function fetchField(int $index = 0) {
    return $this->result->fetch(FetchAs::Column, ['column' => $index]);
  }

  /**
   * {@inheritdoc}
   */
  public function fetchAll(?FetchAs $mode = NULL, ?int $columnIndex = NULL, ?array $constructorArguments = NULL) {
    $fetchOptions = NULL;
    if (isset($columnIndex)) {
      $fetchOptions['column'] = $columnIndex;
    }
    if (isset($constructorArguments)) {
      $fetchOptions['constructor_args'] = $constructorArguments;
    }

    return $this->result->fetchAll($mode ?? $this->result->fetchMode, $fetchOptions ?? $this->result->fetchOptions);
  }

  /**
   * {@inheritdoc}
   */
  public function fetchCol(int $index = 0) {
    return $this->fetchAll(FetchAs::Column, $index);
  }

  /**
   * {@inheritdoc}
   */
  public function fetchAllAssoc(string $key, ?FetchAs $fetch = NULL) {
    return $this->result->fetchAllAssoc($key, $fetch ?? $this->result->fetchMode, $this->result->fetchOptions);
  }

  /**
   * {@inheritdoc}
   */
  public function fetchAllKeyed(int $keyIndex = 0, int $valueIndex = 1) {
    return $this->result->fetchAllKeyed($keyIndex, $valueIndex);
  }

  /**
   * {@inheritdoc}
   */
  public function rowCount() {
    // SELECT query should not use the method.
    if ($this->rowCountEnabled) {
      return $this->result->rowCount();
    }
    else {
      throw new RowCountException();
    }
  }

  /**
   * Returns the current element.
   *
   * @see https://www.php.net/manual/en/iterator.current.php
   *
   * @internal This method should not be called directly.
   */
  public function current(): mixed {
    return $this->result->current();
  }

  /**
   * Returns the key of the current element.
   *
   * @see https://www.php.net/manual/en/iterator.key.php
   *
   * @internal This method should not be called directly.
   */
  public function key(): mixed {
    return $this->result->key();
  }

  /**
   * Rewinds back to the first element of the Iterator.
   *
   * This is the first method called when starting a foreach loop. It will not
   * be executed after foreach loops.
   *
   * @see https://www.php.net/manual/en/iterator.rewind.php
   *
   * @internal This method should not be called directly.
   */
  public function rewind(): void {
    $this->result->rewind();
  }

  /**
   * Moves the current position to the next element.
   *
   * This method is called after each foreach loop.
   *
   * @see https://www.php.net/manual/en/iterator.next.php
   *
   * @internal This method should not be called directly.
   */
  public function next(): void {
    $this->result->next();
  }

  /**
   * Checks if current position is valid.
   *
   * This method is called after ::rewind() and ::next() to check if the
   * current position is valid.
   *
   * @see https://www.php.net/manual/en/iterator.valid.php
   *
   * @internal This method should not be called directly.
   */
  public function valid(): bool {
    return $this->result->valid();
  }

}
