<?php

declare(strict_types=1);

namespace Drupal\mysqli\Driver\Database\mysqli;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Statement\FetchAs;
use Drupal\Core\Database\Statement\StatementBase;

/**
 * MySQLi implementation of \Drupal\Core\Database\Query\StatementInterface.
 */
class Statement extends StatementBase {

  /**
   * Holds the index position of named parameters.
   *
   * The mysqli driver only allows positional placeholders '?', whereas in
   * Drupal the SQL is generated with named placeholders ':name'. In order to
   * execute the SQL, the string containing the named placeholders is converted
   * to using positional ones, and the position (index) of each named
   * placeholder in the string is stored here.
   */
  protected array $paramsPositions;

  /**
   * The converted query string.
   *
   * @var string
   */
  protected string $convertedQueryString;

  /**
   * Constructs a Statement object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   Drupal database connection object.
   * @param \mysqli $clientConnection
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
    \mysqli $clientConnection,
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
   * @return \mysqli_stmt
   *   The client-level database statement.
   */
  public function getClientStatement(): \mysqli_stmt {
    if ($this->hasClientStatement()) {
      assert($this->clientStatement instanceof \mysqli_stmt);
      return $this->clientStatement;
    }
    throw new \LogicException('\\mysqli_stmt not initialized');
  }

  /**
   * {@inheritdoc}
   */
  public function doExecute(?array $args = [], array $options = []): bool {
    $return = $this->getClientStatement()->execute($args);
    $this->result = new Result(
      $this->fetchMode,
      $this->fetchOptions,
      $this->getClientStatement()->get_result(),
      $this->clientConnection,
    );
    $this->markResultsetIterable($return);
    return $return;
  }

  /**
   * {@inheritdoc}
   */
  protected function convertArgs(array $args): array {
    // Prepare the lower-level statement if it's not been prepared already.
    if (!$this->hasClientStatement()) {
      // Replace named placeholders with positional ones if needed.
      $this->paramsPositions = array_flip(array_keys($args));
      $converter = new NamedPlaceholderConverter();
      $converter->parse($this->queryString, $args);
      [$this->convertedQueryString, $args] = [
        $converter->getConvertedSQL(),
        $converter->getConvertedParameters(),
      ];
    }
    else {
      // Transform the $args to positional.
      $tmp = [];
      foreach ($this->paramsPositions as $param => $pos) {
        $tmp[$pos] = $args[$param];
      }
      $args = $tmp;
    }
    return $args;
  }

  /**
   * {@inheritdoc}
   */
  protected function prepare(array $options): void {
    $this->clientStatement = $this->connection
      ->getClientConnection()
      ->prepare($this->convertedQueryString);
  }

}
