<?php

namespace Drupal\Core\Database;

use Drupal\Core\Database\Statement\FetchAs;
use Drupal\Core\Database\Statement\PdoTrait;
use Drupal\Core\Database\Statement\PrefetchedResult;
use Drupal\Core\Database\Statement\StatementBase;

/**
 * An implementation of StatementInterface that prefetches all data.
 *
 * This class behaves very similar to a StatementWrapperIterator of a
 * \PDOStatement but as it always fetches every row it is possible to
 * manipulate those results.
 */
class StatementPrefetchIterator extends StatementBase {

  use PdoTrait;

  /**
   * Constructs a StatementPrefetchIterator object.
   *
   * @param object $clientConnection
   *   Client database connection object, for example \PDO.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param string $queryString
   *   The query string.
   * @param array $driverOptions
   *   Driver-specific options.
   * @param bool $rowCountEnabled
   *   (optional) Enables counting the rows matched. Defaults to FALSE.
   */
  public function __construct(
    object $clientConnection,
    Connection $connection,
    string $queryString,
    protected array $driverOptions = [],
    bool $rowCountEnabled = FALSE,
  ) {
    parent::__construct($connection, $clientConnection, $queryString, $rowCountEnabled);
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
   * {@inheritdoc}
   */
  public function execute($args = [], $options = []) {
    assert(!isset($options['fetch']) || $options['fetch'] instanceof FetchAs || is_string($options['fetch']), 'The "fetch" option passed to execute() must contain a FetchAs enum case or a string. See https://www.drupal.org/node/3488338');

    $startEvent = $this->dispatchStatementExecutionStartEvent($args ?? []);

    // Prepare and execute the statement.
    try {
      $this->clientStatement = $this->getStatement($this->queryString, $args);
      // Arguments are bound before executing, so that strict typing can be
      // applied. getStatement() may have removed arguments that it inlined in
      // the query string, so bind what is left of them.
      $strict = !empty($options['strict_params']);
      $this->bindArguments($args ?? [], $strict);
      // When strict params are active, arguments were already bound with
      // explicit types via bindValue(). Passing them to execute() would
      // re-bind everything as PDO::PARAM_STR, defeating strict typing.
      $return = $this->clientExecute($strict ? NULL : $args, $options);
    }
    catch (\Exception $e) {
      $this->dispatchStatementExecutionFailureEvent($startEvent, $e);
      // @phpstan-ignore unset.possiblyHookedProperty
      unset($this->clientStatement);
      throw $e;
    }

    // Fetch all the data from the reply, in order to release any lock as soon
    // as possible. Then, destroy the client statement. See the documentation
    // of \Drupal\sqlite\Driver\Database\sqlite\Statement for an explanation.
    $this->result = new PrefetchedResult(
      $this->fetchMode,
      $this->fetchOptions,
      $this->clientFetchAll(FetchAs::Associative),
      $this->rowCountEnabled ? $this->clientRowCount() : NULL,
    );
    // @phpstan-ignore unset.possiblyHookedProperty
    unset($this->clientStatement);
    $this->markResultsetIterable($return);

    if (isset($options['fetch'])) {
      if (is_string($options['fetch'])) {
        // Default to an object. Note: db fields will be added to the object
        // before the constructor is run. If you need to assign fields after
        // the constructor is run. See https://www.drupal.org/node/315092.
        $this->setFetchMode(FetchAs::ClassObject, $options['fetch']);
      }
      else {
        $this->setFetchMode($options['fetch']);
      }
    }

    $this->dispatchStatementExecutionEndEvent($startEvent);

    return $return;
  }

  /**
   * Binds arguments to the client statement object.
   *
   * Works the same way as in StatementWrapperIterator, so that queries needing
   * strict typing (for example those with a JSON condition) behave the same on
   * drivers that prefetch their results.
   *
   * getStatement() may have replaced arguments with a literal in the query
   * string, in which case they are no longer part of $arguments.
   *
   * @param mixed[] $arguments
   *   The arguments to bind to the statement object.
   * @param bool $strict
   *   (Optional) Whether strict typing should be used. Defaults to FALSE.
   *
   * @see \Drupal\Core\Database\StatementWrapperIterator::bindArguments()
   */
  protected function bindArguments(array $arguments, bool $strict = FALSE): void {
    foreach ($arguments as $param_name => $param_value) {
      $this->getClientStatement()->bindValue(
        array_is_list($arguments) ? $param_name + 1 : $param_name,
        $param_value,
        $strict
          // Explicitly bind named arguments to match their PDO param type.
          ? match(gettype($param_value)) {
            'boolean' => \PDO::PARAM_BOOL,
            'integer' => \PDO::PARAM_INT,
            default => \PDO::PARAM_STR,
          }
          : \PDO::PARAM_STR
      );
    }
  }

  /**
   * Grab a PDOStatement object from a given query and its arguments.
   *
   * Some drivers (including SQLite) will need to perform some preparation
   * themselves to get the statement right.
   *
   * @param string $query
   *   The query.
   * @param array|null $args
   *   An array of arguments. This can be NULL.
   *
   * @return object
   *   A PDOStatement object.
   */
  protected function getStatement(string $query, ?array &$args = []): object {
    return $this->connection->prepare($query, $this->driverOptions);
  }

}
