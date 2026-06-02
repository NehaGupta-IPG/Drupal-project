<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Statement;

/**
 * Class for PDO-provided results of a data query language (DQL) statement.
 */
class PdoResult extends ResultBase {

  use PdoTrait;

  /**
   * Constructor.
   *
   * @param \PDOStatement $clientStatement
   *   The PDO Statement object. PDO does not provide a separate object for
   *   results, se we need to fetch data from the Statement.
   * @param bool $valid
   *   TRUE if the statement's ::execute() call was successful, or FALSE on
   *   failure.
   */
  public function __construct(
    protected readonly \PDOStatement $clientStatement,
    bool $valid,
  ) {
    parent::__construct($valid);
  }

  /**
   * Returns the client-level database PDO statement object.
   *
   * This method should normally be used only within database driver code.
   *
   * @return \PDOStatement
   *   The client-level database PDO statement.
   */
  public function getClientStatement(): \PDOStatement {
    return $this->clientStatement;
  }

  /**
   * {@inheritdoc}
   */
  public function rowCount(): ?int {
    return $this->clientRowCount();
  }

  /**
   * {@inheritdoc}
   */
  public function setFetchMode(FetchAs $mode, array $fetchOptions): bool {
    $this->fetchMode = $mode;
    $this->fetchOptions = array_merge($this->fetchOptions, $fetchOptions);
    return match ($mode) {
      FetchAs::ClassObject => $this->clientSetFetchMode($mode, $fetchOptions['class'], $fetchOptions['constructor_args'] ?? NULL),
      FetchAs::Column => $this->clientSetFetchMode($mode, $fetchOptions['column']),
      default => $this->clientSetFetchMode($mode),
    };
  }

  /**
   * {@inheritdoc}
   */
  public function fetch(?FetchAs $mode, ?array $fetchOptions = NULL): array|object|int|float|string|bool|NULL {
    if (!empty($fetchOptions)) {
      $this->setFetchMode($mode ?? $this->fetchMode, $fetchOptions);
    }
    $row = $this->clientFetch($mode ?? $this->fetchMode);
    if ($row === FALSE) {
      $this->markResultsetFetchingComplete();
      return FALSE;
    }
    $this->setResultsetCurrentRow($row);
    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function fetchAll(?FetchAs $mode, ?array $fetchOptions = NULL): array {
    return $this->clientFetchAll($mode, $fetchOptions['column'] ?? $fetchOptions['class'] ?? NULL, $fetchOptions['constructor_args'] ?? NULL);
  }

}
