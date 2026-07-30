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
    $this->clientStatement = $this->clientConnection->prepare($query, $options);
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
   * {@inheritdoc}
   */
  public function execute($args = [], $options = []) {
    assert(!isset($options['fetch']) || $options['fetch'] instanceof FetchAs || is_string($options['fetch']), 'The "fetch" option passed to execute() must contain a FetchAs enum case or a string. See https://www.drupal.org/node/3488338');

    if (isset($options['fetch'])) {
      if (is_string($options['fetch'])) {
        $this->setFetchMode(FetchAs::ClassObject, $options['fetch']);
      }
      else {
        $this->setFetchMode($options['fetch']);
      }
    }

    $startEvent = $this->dispatchStatementExecutionStartEvent($args ?? []);

    $strict = !empty($options['strict_params']);
    $this->bindArguments($args ?? [], $strict);
    try {
      // When strict params are active, arguments were already bound with
      // explicit types via bindValue(). Passing them to execute() would
      // re-bind everything as PDO::PARAM_STR, defeating strict typing.
      $return = $this->clientExecute($strict ? NULL : $args, $options);
      $this->result = new PdoResult(
        $this->fetchMode,
        $this->fetchOptions,
        $this->getClientStatement(),
      );
      $this->markResultsetIterable($return);
    }
    catch (\Exception $e) {
      $this->dispatchStatementExecutionFailureEvent($startEvent, $e);
      throw $e;
    }

    $this->dispatchStatementExecutionEndEvent($startEvent);

    return $return;
  }

  /**
   * Binds arguments to the client statement object.
   *
   * Both PDO and mysqli bind all parameters as strings by default
   * (\PDO::PARAM_STR and mysqli_stmt::execute() respectively). This causes
   * implicit type coercion when the database compares against typed values
   * (e.g. JSON-extracted integers). When $strict is TRUE, each parameter is
   * bound with its native PHP type so that comparisons are exact.
   *
   * Drivers not extending this class must implement equivalent strict binding
   * in their own statement class.
   *
   * @param mixed[] $arguments
   *   The arguments to bind to the statement object.
   * @param bool $strict
   *   (Optional) Whether strict typing should be used. Defaults to FALSE.
   *
   * @see \Drupal\Core\Database\StatementPrefetchIterator::bindArguments()
   * @see \Drupal\mysqli\Driver\Database\mysqli\Statement::bindStrictParams()
   */
  protected function bindArguments(array $arguments, bool $strict = FALSE): void {
    foreach ($arguments as $param_name => $param_value) {
      $this->clientStatement->bindValue(
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

}
