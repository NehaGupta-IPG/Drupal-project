<?php

namespace Drupal\Core\Database;

use Drupal\Core\Database\Statement\FetchAs;
use Drupal\Core\Database\Statement\PdoResult;
use Drupal\Core\Database\Statement\PdoTrait;
use Drupal\Core\Database\Statement\StatementBase;

@trigger_error('\\Drupal\\Core\\Database\\StatementWrapperIterator is deprecated in drupal:12.1.0 and is removed from drupal:13.0.0. Extend from \\Drupal\\Core\\Database\\Statement\\StatementBase instead. See https://www.drupal.org/node/3633349', E_USER_DEPRECATED);

/**
 * StatementInterface iterator implementation.
 *
 * @deprecated in drupal:12.1.0 and is removed from drupal:13.0.0. Extend from
 *   \Drupal\Core\Database\Statement\StatementBase instead.
 *
 * @see https://www.drupal.org/node/3633349
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
    @trigger_error(__METHOD__ . '() is deprecated in drupal:12.1.0 and is removed from drupal:13.0.0. Extend from \\Drupal\\Core\\Database\\Statement\\StatementBase instead. See https://www.drupal.org/node/3633349', E_USER_DEPRECATED);
    parent::__construct($connection, $clientConnection, $query, $rowCountEnabled);
    $this->clientStatement = $this->clientConnection->prepare($query, $options);
    $this->setFetchMode(FetchAs::Object);
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

    try {
      $return = $this->clientExecute($args, $options);
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

}
