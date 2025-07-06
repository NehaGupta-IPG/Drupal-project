<?php

declare(strict_types=1);

namespace Drupal\sqlite3\Driver\Database\sqlite3;

use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Database\FetchModeTrait;
use Drupal\Core\Database\Statement\FetchAs;
use Drupal\Core\Database\Statement\ResultBase;

/**
 * Class for mysqli-provided results of a data query language (DQL) statement.
 */
class Result extends ResultBase {

  use FetchModeTrait;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Database\Statement\FetchAs $fetchMode
   *   The fetch mode.
   * @param array{class: class-string, constructor_args: list<mixed>, column: int, cursor_orientation?: int, cursor_offset?: int} $fetchOptions
   *   The fetch options.
   * @param \SQLite3Result|false $sqliteResult
   *   The MySQLi result object.
   * @param \SQLite3 $sqliteConnection
   *   Client database connection object.
   */
  public function __construct(
    FetchAs $fetchMode,
    array $fetchOptions,
    protected readonly \SQLite3Result|false $sqliteResult,
    protected readonly \SQLite3 $sqliteConnection,
  ) {
    parent::__construct($fetchMode, $fetchOptions);
  }

  /**
   * {@inheritdoc}
   */
  public function rowCount(): ?int {
    return $this->sqliteConnection->changes();
  }

  /**
   * {@inheritdoc}
   */
  public function setFetchMode(FetchAs $mode, array $fetchOptions): bool {
    // There are no methods to set fetch mode in \mysqli_result.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function fetch(FetchAs $mode, array $fetchOptions): array|object|int|float|string|bool|NULL {
    assert($this->sqliteResult instanceof \SQLite3Result);

    $sqlite_row = $this->sqliteResult->fetchArray(\SQLITE3_ASSOC);

    if (!$sqlite_row) {
      return FALSE;
    }

    // Stringify all non-NULL column values.
    $row = array_map(fn ($value) => $value === NULL ? NULL : (string) $value, $sqlite_row);

    return $this->assocToFetchMode($row, $mode, $fetchOptions);
  }

}
