<?php

namespace Drupal\sqlite3\Driver\Database\sqlite3;

use Drupal\Core\Database\Statement\FetchAs;
use Drupal\Core\Database\Statement\StatementBase;

/**
 * SQLite3 implementation of \Drupal\Core\Database\Query\StatementInterface.
 */
class Statement extends StatementBase {

  /**
   * Constructs a Statement object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   Drupal database connection object.
   * @param \SQLite3 $clientConnection
   *   Client database connection object.
   * @param string $queryString
   *   The SQL query string.
   * @param array $driverOpts
   *   (optional) Array of query options.
   * @param bool $rowCountEnabled
   *   (optional) Enables counting the rows affected. Defaults to FALSE.
   */
  public function __construct(
    Connection $connection,
    \SQLite3 $clientConnection,
    string $queryString,
    protected array $driverOpts = [],
    bool $rowCountEnabled = FALSE,
  ) {
    parent::__construct($connection, $clientConnection, $queryString, $rowCountEnabled);
    $this->setFetchMode(FetchAs::Object);
  }

  /**
   * Returns the client-level database statement object.
   *
   * This method should normally be used only within database driver code.
   *
   * @return \SQLite3Stmt
   *   The client-level database statement.
   */
  public function getClientStatement(): \SQLite3Stmt {
    if ($this->hasClientStatement()) {
      assert($this->clientStatement instanceof \SQLite3Stmt);
      return $this->clientStatement;
    }
    throw new \LogicException('\\SQLite3Stmt not initialized');
  }

  /**
   * {@inheritdoc}
   */
  public function execute($args = [], $options = []) {
    if (isset($options['fetch'])) {
      if (is_string($options['fetch'])) {
        $this->setFetchMode(FetchAs::ClassObject, $options['fetch']);
      }
      else {
        if (is_int($options['fetch'])) {
          @trigger_error("Passing the 'fetch' key as an integer to \$options in execute() is deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use a case of \Drupal\Core\Database\Statement\FetchAs enum instead. See https://www.drupal.org/node/3488338", E_USER_DEPRECATED);
        }
        $this->setFetchMode($options['fetch']);
      }
    }

    $startEvent = $this->dispatchStatementExecutionStartEvent($args ?? []);

    try {
      // Prepare the lower-level statement if it's not been prepared already.
      if (!$this->hasClientStatement()) {
        $this->clientStatement = $this->clientConnection->prepare($this->queryString);
      }

      // Associate placeholders.
      foreach ($args as $placeholder => $value) {
        $param = is_int($placeholder) ? $placeholder + 1 : $placeholder;
        $this->getClientStatement()->bindValue($param, $value);
      }
#dump([__METHOD__, $this->clientStatement->getSQL(), $args, $this->clientStatement->paramCount(), $this->clientStatement->getSQL(true)]);

      // In mysqli, the results of the statement execution are returned in a
      // different object than the statement itself.
      $return = $this->getClientStatement()->execute();
      $this->result = new Result(
        $this->fetchMode,
        $this->fetchOptions,
        $return,
        $this->clientConnection,
      );
      $this->markResultsetIterable((bool) $return);
    }
    catch (\Exception $e) {
      $this->dispatchStatementExecutionFailureEvent($startEvent, $e);
      throw $e;
    }

    $this->dispatchStatementExecutionEndEvent($startEvent);

    return $return;
  }

  /**
   * {@inheritdoc}
   *
   * The PDO SQLite layer doesn't replace numeric placeholders in queries
   * correctly, and this makes numeric expressions (such as COUNT(*) >= :count)
   * fail. We replace numeric placeholders in the query ourselves to work
   * around this bug.
   *
   * See http://bugs.php.net/bug.php?id=45259 for more details.
   */
  protected function getStatement(string $query, ?array &$args = []): object {
    if (is_array($args) && !empty($args)) {
      // Check if $args is a simple numeric array.
      if (range(0, count($args) - 1) === array_keys($args)) {
        // In that case, we have unnamed placeholders.
        $count = 0;
        $new_args = [];
        foreach ($args as $value) {
          if (is_float($value) || is_int($value)) {
            if (is_float($value)) {
              // Force the conversion to float so as not to loose precision
              // in the automatic cast.
              $value = sprintf('%F', $value);
            }
            $query = substr_replace($query, $value, strpos($query, '?'), 1);
          }
          else {
            $placeholder = ':db_statement_placeholder_' . $count++;
            $query = substr_replace($query, $placeholder, strpos($query, '?'), 1);
            $new_args[$placeholder] = $value;
          }
        }
        $args = $new_args;
      }
      else {
        // Else, this is using named placeholders.
        foreach ($args as $placeholder => $value) {
          if (is_float($value) || is_int($value)) {
            if (is_float($value)) {
              // Force the conversion to float so as not to loose precision
              // in the automatic cast.
              $value = sprintf('%F', $value);
            }

            // We will remove this placeholder from the query as PDO throws an
            // exception if the number of placeholders in the query and the
            // arguments does not match.
            unset($args[$placeholder]);
            // PDO allows placeholders to not be prefixed by a colon. See
            // http://marc.info/?l=php-internals&m=111234321827149&w=2 for
            // more.
            if ($placeholder[0] != ':') {
              $placeholder = ":$placeholder";
            }
            // When replacing the placeholders, make sure we search for the
            // exact placeholder. For example, if searching for
            // ':db_placeholder_1', do not replace ':db_placeholder_11'.
            $query = preg_replace('/' . preg_quote($placeholder, NULL) . '\b/', $value, $query);
          }
        }
      }
    }

    return $this->clientConnection->prepare($query);
  }

  /**
   * {@inheritdoc}
   */
  public function xxexecute($args = [], $options = []) {
    try {
      $return = parent::execute($args, $options);
    }
    catch (\PDOException $e) {
      // The database schema might be changed by another process in between the
      // time that the statement was prepared and the time the statement was run
      // (e.g. usually happens when running tests). In this case, we need to
      // re-run the query.
      // @see http://www.sqlite.org/faq.html#q15
      // @see http://www.sqlite.org/rescode.html#schema
      if (!empty($e->errorInfo[1]) && $e->errorInfo[1] === 17) {
        // The schema has changed. SQLite specifies that we must resend the
        // query.
        $return = parent::execute($args, $options);
      }
      else {
        // Rethrow the exception.
        throw $e;
      }
    }

    return $return;
  }

}
