<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Statement;

/**
 * Class for PDO-provided results of a data query language (DQL) statement.
 *
 * @phpstan-import-type FetchOptions from \Drupal\Core\Database\Statement\ResultBase
 */
class PdoResult extends ResultBase {

  use PdoTrait;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Database\Statement\FetchAs $fetchMode
   *   The fetch mode.
   * @param FetchOptions $fetchOptions
   *   The fetch options.
   * @param \PDOStatement $clientStatement
   *   The PDO Statement object. PDO does not provide a separate object for
   *   results, se we need to fetch data from the Statement.
   * @param bool $valid
   *   TRUE if the statement's ::execute() call was successful, or FALSE on
   *   failure.
   */
  public function __construct(
    FetchAs $fetchMode,
    array $fetchOptions,
    protected readonly \PDOStatement $clientStatement,
    bool $valid,
  ) {
    parent::__construct($fetchMode, $fetchOptions, $valid);
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
    $this->fetchOptions = $fetchOptions;
    return match ($mode) {
      FetchAs::ClassObject => $this->clientSetFetchMode($mode, $fetchOptions['class'], $fetchOptions['constructor_args'] ?? NULL),
      FetchAs::Column => $this->clientSetFetchMode($mode, $fetchOptions['column']),
      default => $this->clientSetFetchMode($mode),
    };
  }

  /**
   * {@inheritdoc}
   */
  public function fetch(FetchAs $mode, array $fetchOptions): array|object|int|float|string|bool|NULL {
    if (!empty($fetchOptions)) {
      $this->setFetchMode($mode, $fetchOptions);
    }
    $row = $this->clientFetch($mode);
    // PostgreSql returns a \stdClass object with no content when fetching on
    // a result from a DML statement.
    if ($row === FALSE || ($mode === FetchAs::Object && empty((array) $row))) {
      $this->markResultsetFetchingComplete();
      return FALSE;
    }
    $this->setResultsetCurrentRow($row);
    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function fetchAll(FetchAs $mode, array $fetchOptions): array {
    $result = $this->clientFetchAll($mode, ...match ($mode) {
      FetchAs::Column => [$fetchOptions['column'] ?? NULL],
      FetchAs::ClassObject => [
        $fetchOptions['class'] ?? NULL,
        $fetchOptions['constructor_args'] ?? NULL,
      ],
      default => [],
    });
    $this->markResultsetFetchingComplete();
    return $result;
  }

}
